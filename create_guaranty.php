<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config.php'; // شامل csrf_field() و verifyCsrfToken()

// هم‌ترازسازی ایمن اسکیما برای جدول گارانتی (بدون نیاز به تغییر config.php)
function ensureGuarantySchema(PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS guaranty_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            issue_date DATE NOT NULL,
            asset_id INT NOT NULL,
            coupler_company VARCHAR(255) NOT NULL,
            customer_id INT NOT NULL,
            guaranty_number VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            INDEX idx_issue_date (issue_date),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
    } catch (Throwable $e) {}

    // افزودن ستون‌های جدید در صورت نبود
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guaranty_cards'");
        $stmt->execute();
        $cols = array_map(static fn($r) => $r['COLUMN_NAME'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $alter = [];
        if (!in_array('alternator_model', $cols, true))   $alter[] = "ADD COLUMN alternator_model VARCHAR(255) NULL";
        if (!in_array('alternator_serial', $cols, true))  $alter[] = "ADD COLUMN alternator_serial VARCHAR(255) NULL";
        if (!in_array('updated_at', $cols, true))         $alter[] = "ADD COLUMN updated_at TIMESTAMP NULL";

        if (!empty($alter)) {
            $pdo->exec("ALTER TABLE guaranty_cards " . implode(', ', $alter));
        }
    } catch (Throwable $e) {}
}
ensureGuarantySchema($pdo);

// کمکی
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// بارگذاری لیست‌ها
$assets = [];
$customers = [];
try {
    $assets = $pdo->query("SELECT id, name, serial_number, model FROM assets ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
try {
    $customers = $pdo->query("SELECT id, customer_type, full_name, company, address FROM customers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$success = '';
$error = '';
$created_card = null;
$editingCard = null;

// حالت ویرایش
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
if ($edit_id > 0) {
    $st = $pdo->prepare("SELECT * FROM guaranty_cards WHERE id = ?");
    $st->execute([$edit_id]);
    $editingCard = $st->fetch(PDO::FETCH_ASSOC);
}

// پردازش عملیات
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // بررسی CSRF از config.php
    verifyCsrfToken();

    // حذف
    if (isset($_POST['delete_id'])) {
        $did = (int)$_POST['delete_id'];
        try {
            $pdo->prepare("DELETE FROM guaranty_cards WHERE id = ?")->execute([$did]);
            $success = "کارت گارانتی حذف شد.";
        } catch (Throwable $e) {
            $error = "خطا در حذف: " . $e->getMessage();
        }
    }

    // ایجاد
    if (isset($_POST['create_guaranty'])) {
        $issue_date = $_POST['issue_date'] ?? '';
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        $coupler_company = trim($_POST['coupler_company'] ?? '');
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $alternator_model = trim($_POST['alternator_model'] ?? '');
        $alternator_serial = trim($_POST['alternator_serial'] ?? '');

        if ($issue_date === '' || $asset_id <= 0 || $customer_id <= 0 || $coupler_company === '') {
            $error = 'لطفاً همه فیلدهای الزامی را تکمیل کنید.';
        } else {
            $ast = $pdo->prepare("SELECT id, name, serial_number, model FROM assets WHERE id = ?");
            $ast->execute([$asset_id]);
            $asset = $ast->fetch(PDO::FETCH_ASSOC);

            $cst = $pdo->prepare("SELECT id, customer_type, full_name, company, address FROM customers WHERE id = ?");
            $cst->execute([$customer_id]);
            $customer = $cst->fetch(PDO::FETCH_ASSOC);

            if (!$asset || !$customer) {
                $error = 'دستگاه یا مشتری معتبر نیست.';
            } else {
                $guaranty_number = 'GRT-' . date('Ymd') . '-' . random_int(1000, 9999);
                try {
                    $ins = $pdo->prepare("INSERT INTO guaranty_cards (issue_date, asset_id, coupler_company, customer_id, guaranty_number, alternator_model, alternator_serial) VALUES (?,?,?,?,?,?,?)");
                    $ins->execute([$issue_date, $asset_id, $coupler_company, $customer_id, $guaranty_number, $alternator_model, $alternator_serial]);

                    $newId = (int)$pdo->lastInsertId();
                    $success = "کارت گارانتی با موفقیت صادر شد.";
                    $st = $pdo->prepare("SELECT gc.*, a.name AS asset_name, a.serial_number, a.model AS asset_model,
                                                CASE WHEN c.customer_type='حقوقی' AND COALESCE(c.company,'')<>'' THEN c.company ELSE c.full_name END AS customer_name,
                                                c.address AS customer_address
                                         FROM guaranty_cards gc
                                         JOIN assets a ON a.id = gc.asset_id
                                         JOIN customers c ON c.id = gc.customer_id
                                         WHERE gc.id = ?");
                    $st->execute([$newId]);
                    $created_card = $st->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    $error = "خطا در ذخیره اطلاعات گارانتی: " . $e->getMessage();
                }
            }
        }
    }

    // به‌روزرسانی
    if (isset($_POST['update_guaranty'])) {
        $gid = (int)($_POST['id'] ?? 0);
        $issue_date = $_POST['issue_date'] ?? '';
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        $coupler_company = trim($_POST['coupler_company'] ?? '');
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $alternator_model = trim($_POST['alternator_model'] ?? '');
        $alternator_serial = trim($_POST['alternator_serial'] ?? '');

        if ($gid <= 0 || $issue_date === '' || $asset_id <= 0 || $customer_id <= 0 || $coupler_company === '') {
            $error = 'لطفاً فیلدهای الزامی را تکمیل کنید.';
        } else {
            try {
                $pdo->prepare("UPDATE guaranty_cards SET issue_date=?, asset_id=?, coupler_company=?, customer_id=?, alternator_model=?, alternator_serial=?, updated_at=NOW() WHERE id=?")
                    ->execute([$issue_date, $asset_id, $coupler_company, $customer_id, $alternator_model, $alternator_serial, $gid]);

                $success = "کارت گارانتی به‌روزرسانی شد.";
                $st = $pdo->prepare("SELECT gc.*, a.name AS asset_name, a.serial_number, a.model AS asset_model,
                                            CASE WHEN c.customer_type='حقوقی' AND COALESCE(c.company,'')<>'' THEN c.company ELSE c.full_name END AS customer_name,
                                            c.address AS customer_address
                                     FROM guaranty_cards gc
                                     JOIN assets a ON a.id = gc.asset_id
                                     JOIN customers c ON c.id = gc.customer_id
                                     WHERE gc.id = ?");
                $st->execute([$gid]);
                $created_card = $st->fetch(PDO::FETCH_ASSOC);
                $editingCard = $created_card;
            } catch (Throwable $e) {
                $error = "خطا در به‌روزرسانی: " . $e->getMessage();
            }
        }
    }
}

// لیست گارانتی‌ها برای گزارش
$cards = [];
try {
    $cards = $pdo->query("SELECT gc.id, gc.issue_date, gc.guaranty_number, gc.coupler_company,
                                 a.name AS asset_name, a.serial_number,
                                 CASE WHEN c.customer_type='حقوقی' AND COALESCE(c.company,'')<>'' THEN c.company ELSE c.full_name END AS customer_name
                          FROM guaranty_cards gc
                          JOIN assets a ON a.id = gc.asset_id
                          JOIN customers c ON c.id = gc.customer_id
                          ORDER BY gc.id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کارت گارانتی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- کارت 1: صدور/ویرایش کارت گارانتی -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-file-contract me-2"></i>
                    <?php echo $editingCard ? 'ویرایش کارت گارانتی' : 'صدور کارت گارانتی'; ?>
                </div>
                <div class="card-body">
                    <form method="post" id="guarantyForm">
                        <?php csrf_field(); ?>
                        <?php if ($editingCard): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$editingCard['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">تاریخ صدور</label>
                            <input type="date" class="form-control" name="issue_date" required value="<?php
                                echo h($editingCard['issue_date'] ?? ($_POST['issue_date'] ?? ''));
                            ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">دستگاه</label>
                            <select class="form-select" name="asset_id" required id="asset_id">
                                <option value="">انتخاب دستگاه</option>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?php echo (int)$a['id']; ?>"
                                            data-serial="<?php echo h($a['serial_number']); ?>"
                                            data-model="<?php echo h($a['model']); ?>"
                                        <?php
                                            $sel = (string)($editingCard['asset_id'] ?? ($_POST['asset_id'] ?? '')) === (string)$a['id'];
                                            echo $sel ? 'selected' : '';
                                        ?>>
                                        <?php echo h($a['name'] . ' - ' . $a['serial_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">شرکت کوپل کننده</label>
                            <input type="text" class="form-control" name="coupler_company" required value="<?php
                                echo h($editingCard['coupler_company'] ?? ($_POST['coupler_company'] ?? ''));
                            ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">خریدار</label>
                            <select class="form-select" name="customer_id" required id="customer_id">
                                <option value="">انتخاب خریدار</option>
                                <?php foreach ($customers as $c): ?>
                                    <?php $dn = ($c['customer_type']==='حقوقی' && trim((string)$c['company'])!=='') ? $c['company'] : $c['full_name']; ?>
                                    <option value="<?php echo (int)$c['id']; ?>"
                                            data-address="<?php echo h($c['address']); ?>"
                                        <?php
                                            $sel = (string)($editingCard['customer_id'] ?? ($_POST['customer_id'] ?? '')) === (string)$c['id'];
                                            echo $sel ? 'selected' : '';
                                        ?>>
                                        <?php echo h($dn ?: '(بدون نام)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مدل آلترناتور</label>
                                <input type="text" class="form-control" name="alternator_model" id="alternator_model" required value="<?php
                                    echo h($editingCard['alternator_model'] ?? ($_POST['alternator_model'] ?? ''));
                                ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره سریال آلترناتور</label>
                                <input type="text" class="form-control" name="alternator_serial" id="alternator_serial" required value="<?php
                                    echo h($editingCard['alternator_serial'] ?? ($_POST['alternator_serial'] ?? ''));
                                ?>">
                            </div>
                        </div>

                        <!-- فیلدهای فقط نمایشی برای پیش‌نمایش سریع -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">شماره سریال پکیج موتور</label>
                                <input type="text" class="form-control" id="package_serial" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">برند/مدل موتور</label>
                                <input type="text" class="form-control" id="motor_brand" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">محل نصب (از آدرس مشتری)</label>
                            <input type="text" class="form-control" id="installation_place" readonly>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if ($editingCard): ?>
                                <button type="button" id="updateBtn" class="btn btn-warning">
                                    <i class="fas fa-save me-1"></i>به‌روزرسانی کارت
                                </button>
                                <a class="btn btn-outline-secondary" href="create_guaranty.php">لغو و ایجاد جدید</a>
                            <?php else: ?>
                                <button type="button" id="createBtn" class="btn btn-success">
                                    <i class="fas fa-check-circle me-1"></i>صدور کارت گارانتی
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" id="previewBtn">
                                <i class="fas fa-eye me-1"></i>پیش‌نمایش
                            </button>
                        </div>

                        <!-- دکمه‌های واقعی submit (پنهان) -->
                        <button type="submit" name="create_guaranty" id="createSubmit" class="d-none"></button>
                        <button type="submit" name="update_guaranty" id="updateSubmit" class="d-none"></button>
                    </form>

                    <!-- مودال تایید -->
                    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">تایید نهایی</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            آیا از اطلاعات وارد شده مطمئن هستید؟
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">خیر</button>
                            <button type="button" class="btn btn-primary" id="confirmYes">بله</button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- پیش‌نمایش بعد از ذخیره -->
                    <?php if ($created_card): ?>
                        <hr>
                        <div class="card">
                            <div class="card-header">پیش‌نمایش کارت ثبت‌شده</div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>تاریخ صدور:</strong> <?php echo h($created_card['issue_date']); ?></div>
                                    <div class="col-md-6"><strong>شماره کارت:</strong> <?php echo h($created_card['guaranty_number']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>خریدار:</strong> <?php echo h($created_card['customer_name']); ?></div>
                                    <div class="col-md-6"><strong>محل نصب:</strong> <?php echo h($created_card['customer_address']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>دستگاه:</strong> <?php echo h($created_card['asset_name'] . ' - ' . $created_card['serial_number']); ?></div>
                                    <div class="col-md-6"><strong>کوپل‌کننده:</strong> <?php echo h($created_card['coupler_company']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>مدل آلترناتور:</strong> <?php echo h($created_card['alternator_model']); ?></div>
                                    <div class="col-md-6"><strong>سریال آلترناتور:</strong> <?php echo h($created_card['alternator_serial']); ?></div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <a target="_blank" class="btn btn-outline-primary" href="print_guaranty.php?id=<?php echo (int)$created_card['id']; ?>">
                                        نمایش/چاپ
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">چاپ صفحه</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- کارت 2: گزارش گارانتی‌ها -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>گزارش گارانتی‌ها
                </div>
                <div class="card-body">
                    <?php if (empty($cards)): ?>
                        <p class="text-muted m-0">گارانتی ثبت نشده است.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>تاریخ</th>
                                        <th>خریدار</th>
                                        <th>دستگاه</th>
                                        <th>شماره کارت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cards as $c): ?>
                                    <tr>
                                        <td><?php echo (int)$c['id']; ?></td>
                                        <td><?php echo h($c['issue_date']); ?></td>
                                        <td><?php echo h($c['customer_name']); ?></td>
                                        <td><?php echo h($c['asset_name'] . ' - ' . $c['serial_number']); ?></td>
                                        <td><?php echo h($c['guaranty_number']); ?></td>
                                        <td class="d-flex gap-1">
                                            <a class="btn btn-sm btn-outline-primary" target="_blank" href="print_guaranty.php?id=<?php echo (int)$c['id']; ?>">نمایش/چاپ</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="create_guaranty.php?edit_id=<?php echo (int)$c['id']; ?>">ویرایش</a>
                                            <form method="post" onsubmit="return confirm('حذف این کارت؟');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="delete_id" value="<?php echo (int)$c['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">حذف</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const assetSelect = document.getElementById('asset_id');
  const customerSelect = document.getElementById('customer_id');
  const packageSerial = document.getElementById('package_serial');
  const motorBrand = document.getElementById('motor_brand');
  const installPlace = document.getElementById('installation_place');

  function updateAssetFields() {
    const opt = assetSelect.options[assetSelect.selectedIndex] || {};
    packageSerial.value = opt.getAttribute ? (opt.getAttribute('data-serial') || '') : '';
    motorBrand.value = opt.getAttribute ? (opt.getAttribute('data-model') || '') : '';
  }
  function updateCustomerFields() {
    const opt = customerSelect.options[customerSelect.selectedIndex] || {};
    installPlace.value = opt.getAttribute ? (opt.getAttribute('data-address') || '') : '';
  }
  if (assetSelect) assetSelect.addEventListener('change', updateAssetFields);
  if (customerSelect) customerSelect.addEventListener('change', updateCustomerFields);
  updateAssetFields();
  updateCustomerFields();

  // پیش‌نمایش سریع (اطلاع)
  const previewBtn = document.getElementById('previewBtn');
  if (previewBtn) {
    previewBtn.addEventListener('click', function(){
      alert('پیش‌نمایش PDF پس از ثبت در «نمایش/چاپ» در دسترس است. همچنین می‌توانید با «چاپ صفحه» همین فرم را چاپ کنید.');
    });
  }

  // تایید نهایی قبل از ارسال
  const createBtn = document.getElementById('createBtn');
  const updateBtn = document.getElementById('updateBtn');
  const confirmModal = document.getElementById('confirmModal');
  const confirmYes = document.getElementById('confirmYes');
  const createSubmit = document.getElementById('createSubmit');
  const updateSubmit = document.getElementById('updateSubmit');
  let pendingAction = null;

  function openConfirm(action) {
    pendingAction = action;
    const m = new bootstrap.Modal(confirmModal);
    m.show();
  }
  if (createBtn) createBtn.addEventListener('click', ()=>openConfirm('create'));
  if (updateBtn) updateBtn.addEventListener('click', ()=>openConfirm('update'));
  if (confirmYes) confirmYes.addEventListener('click', function(){
    const modal = bootstrap.Modal.getInstance(confirmModal);
    if (modal) modal.hide();
    if (pendingAction === 'create' && createSubmit) createSubmit.click();
    if (pendingAction === 'update' && updateSubmit) updateSubmit.click();
  });
});
</script>
</body>
</html>