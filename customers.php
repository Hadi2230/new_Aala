<?php
/******************************************************
 * customers.php  —  مدیریت مشتریان (حقیقی/حقوقی)
 * نکات پیاده‌سازی:
 * - CSRF کامل برای همه فرم‌ها (افزودن/حذف)
 * - حذف مشتری از طریق POST (نه GET)
 * - مدیریت نقش تمیزتر با توابع can_delete و is_admin
 * - اعتبارسنجی سروری + فیلتر ورودی‌ها
 * - فرم پویا: ابتدا نوع مشتری پرسیده می‌شود
 * - جستجو روی فیلدهای کلیدی جدید و قدیم + pagination
 ******************************************************/

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config.php'; // باید $pdo را بسازد (PDO)

//////////////////////////////
// CSRF: تولید و بررسی
//////////////////////////////
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function require_csrf($tokenFromPost)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$tokenFromPost)) {
        http_response_code(400);
        die('درخواست نامعتبر (CSRF)!');
    }
}

//////////////////////////////
// مدیریت نقش‌ها
//////////////////////////////
function current_user_role(): string
{
    // بسته به معماری شما می‌تونه از DB هم خوانده بشه
    return isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
}
function is_admin(): bool
{
    // پشتیبانی از چند برچسب رایج برای ادمین
    $role = mb_strtolower(current_user_role(), 'UTF-8');
    return in_array($role, ['admin', 'ادمین', 'superadmin', 'مدیر'], true);
}
function can_delete_customers(): bool
{
    return is_admin();
}

//////////////////////////////
// ابزارهای فیلترینگ و اعتبارسنجی
//////////////////////////////
function sanitize_text(?string $v): string
{
    // اجازه حروف فارسی/لاتین/اعداد/فاصله و علائم رایج، حذف تگ‌ها
    $v = trim((string)$v);
    $v = strip_tags($v);
    // می‌توانید در صورت نیاز محدودیت بیشتری اعمال کنید
    return $v;
}
function digits_only(?string $v): string
{
    return preg_replace('/\D+/', '', (string)$v);
}
function valid_phone(string $digits): bool
{
    // 10 تا 11 رقم (مثال: 9123456789 یا 09123456789)
    return (bool)preg_match('/^\d{10,11}$/', $digits);
}
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

//////////////////////////////
// پیام‌ها
//////////////////////////////
$success = isset($_GET['success']) ? urldecode($_GET['success']) : null;
$error   = isset($_GET['error'])   ? urldecode($_GET['error'])   : null;

//////////////////////////////
// افزودن مشتری
//////////////////////////////
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    try {
        require_csrf($_POST['csrf_token'] ?? '');

        $customer_type = sanitize_text($_POST['customer_type'] ?? '');
        if (!in_array($customer_type, ['حقیقی', 'حقوقی'], true)) {
            throw new RuntimeException('نوع مشتری نامعتبر است.');
        }

        // فیلدهای مشترک
        $address        = sanitize_text($_POST['address'] ?? '');
        $operator_name  = sanitize_text($_POST['operator_name'] ?? '');
        $operator_phone = digits_only($_POST['operator_phone'] ?? '');

        // اعتبارسنجی پایه مشترک
        if ($operator_name === '' || !valid_phone($operator_phone)) {
            throw new RuntimeException('نام و شماره تماس اپراتور معتبر را وارد کنید.');
        }
        if ($address === '') {
            throw new RuntimeException('آدرس الزامی است.');
        }

        if ($customer_type === 'حقیقی') {
            // حقیقی
            $full_name = sanitize_text($_POST['full_name'] ?? '');
            $phone     = digits_only($_POST['phone'] ?? '');

            if ($full_name === '') {
                throw new RuntimeException('نام و نام خانوادگی الزامی است.');
            }
            if (!valid_phone($phone)) {
                throw new RuntimeException('شماره تماس معتبر نیست (۱۰ تا ۱۱ رقم).');
            }

            // درج
            $sql = "INSERT INTO customers
                    (customer_type, full_name, phone, address, operator_name, operator_phone)
                    VALUES (:ctype, :full_name, :phone, :address, :op_name, :op_phone)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ctype'    => $customer_type,
                ':full_name'=> $full_name,
                ':phone'    => $phone,
                ':address'  => $address,
                ':op_name'  => $operator_name,
                ':op_phone' => $operator_phone,
            ]);

        } else {
            // حقوقی
            $company           = sanitize_text($_POST['company'] ?? '');
            $responsible_name  = sanitize_text($_POST['responsible_name'] ?? '');
            $company_phone     = digits_only($_POST['company_phone'] ?? '');
            $responsible_phone = digits_only($_POST['responsible_phone'] ?? '');
            $notes             = sanitize_text($_POST['notes'] ?? '');

            if ($company === '') {
                throw new RuntimeException('نام شرکت الزامی است.');
            }
            if ($responsible_name === '') {
                throw new RuntimeException('نام مسئول شرکت الزامی است.');
            }
            if (!valid_phone($company_phone)) {
                throw new RuntimeException('شماره تماس شرکت معتبر نیست (۱۰ تا ۱۱ رقم).');
            }
            if (!valid_phone($responsible_phone)) {
                throw new RuntimeException('شماره تماس مسئول شرکت معتبر نیست (۱۰ تا ۱۱ رقم).');
            }

            // درج
           // در بخش درج مشتری حقوقی، این کوئری را جایگزین کنید:
		$sql = "INSERT INTO customers
     		   (customer_type, company, responsible_name, address, company_phone, responsible_phone,
   	 	     operator_name, operator_phone, notes, full_name, phone)
   		     VALUES (:ctype, :company, :resp_name, :address, :cphone, :rphone, :op_name, :op_phone, :notes, '', '')";
		$stmt = $pdo->prepare($sql);
		$stmt->execute([
   		 ':ctype'    => $customer_type,
    		':company'  => $company,
    		':resp_name'=> $responsible_name,
    		':address'  => $address,
   		 ':cphone'   => $company_phone,
   		 ':rphone'   => $responsible_phone,
   		 ':op_name'  => $operator_name,
   		 ':op_phone' => $operator_phone,
   		 ':notes'    => $notes,
	]);
        }

        $msg = 'مشتری با موفقیت افزوده شد!';
        header('Location: customers.php?success='.urlencode($msg));
        exit();

    } catch (PDOException $e) {
        // 23000 -> نقض یکتا
        $error = ($e->getCode() === '23000')
            ? 'شماره تلفن تکراری است!'
            : ('خطا در افزودن مشتری: '.$e->getMessage());

    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = 'خطای غیرمنتظره رخ داد.';
    }
}

//////////////////////////////
// حذف مشتری با POST + CSRF
//////////////////////////////
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    try {
        require_csrf($_POST['csrf_token'] ?? '');
        if (!can_delete_customers()) {
            throw new RuntimeException('شما مجاز به حذف مشتری نیستید.');
        }

        $delete_id = (int)($_POST['delete_id'] ?? 0);
        if ($delete_id <= 0) {
            throw new RuntimeException('شناسه مشتری نامعتبر است.');
        }

        // بررسی وجود مشتری
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
        $stmt->execute([$delete_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            throw new RuntimeException('مشتری مورد نظر یافت نشد.');
        }

        // حذف
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$delete_id]);

        $msg = 'مشتری با موفقیت حذف شد!';
        header('Location: customers.php?success='.urlencode($msg));
        exit();

    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'خطا در حذف مشتری.';
    }
}

//////////////////////////////
// جستجو + صفحه‌بندی
//////////////////////////////
$search  = isset($_GET['search']) ? trim($_GET['search']) : '';
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// عبارت جستجو روی چند ستون
$searchableCols = [
    'name', // ستون Generated برای سازگاری (company یا full_name)
    'full_name', 'phone', 'company', 'responsible_name',
    'company_phone', 'responsible_phone', 'operator_name', 'operator_phone', 'address', 'notes'
];

$params = [];
$where  = '';
if ($search !== '') {
    $like = "%$search%";
    $parts = [];
    foreach ($searchableCols as $i => $col) {
        $key = ":term$i";
        $parts[] = "$col LIKE $key";
        $params[$key] = $like;
    }
    $where = 'WHERE '.implode(' OR ', $parts);
}

// مجموع
$countSql = "SELECT COUNT(*) FROM customers ".($where ? $where : '');
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_STR);
$countStmt->execute();
$total_customers = (int)$countStmt->fetchColumn();
$total_pages     = max(1, (int)ceil($total_customers / $perPage));

// داده‌ها
$dataSql = "SELECT * FROM customers ".($where ? $where.' ' : '')."ORDER BY id DESC LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) $dataStmt->bindValue($k, $v, PDO::PARAM_STR);
$dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$dataStmt->execute();
$customers = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مشتریان - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome برای آیکن‌ها -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .table-hover tbody tr:hover { background-color: rgba(52, 152, 219, 0.08); }
        .badge-success { background-color: #28a745; }
        .badge-warning { background-color: #ffc107; color:#000; }
        .search-box { max-width: 320px; }
        .truncate { max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display:inline-block; vertical-align: bottom;}
        .required::after { content:" *"; color:#dc3545; }
        .form-section { display: none; }
        .form-section.active { display: block; }
        .badge-type { font-weight: 600; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h2 class="text-center mb-4">مدیریت مشتریان</h2>

    <!-- پیام‌ها -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= h($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- جستجو -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>جستجو و فیلتر</span>
            <span class="badge bg-info">تعداد کل: <?= (int)$total_customers ?></span>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search"
                               placeholder="جستجو در نام، شرکت، تلفن، مسئول، اپراتور، آدرس..."
                               value="<?= h($search) ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> جستجو
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <?php if ($search !== ''): ?>
                        <a href="customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> پاک کردن فیلتر
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- فرم افزودن مشتری -->
    <div class="card mb-4">
        <div class="card-header">افزودن مشتری جدید</div>
        <div class="card-body">
            <form method="POST" id="customerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="add_customer" value="1">

                <!-- انتخاب نوع مشتری -->
                <?php
                $postedType = $_POST['customer_type'] ?? 'حقیقی';
                ?>
                <div class="mb-3">
                    <label class="form-label required">نوع مشتری</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="type_individual" name="customer_type" value="حقیقی"
                                <?= $postedType === 'حقوقی' ? '' : 'checked' ?>>
                            <label class="form-check-label" for="type_individual">حقیقی</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="type_legal" name="customer_type" value="حقوقی"
                                <?= $postedType === 'حقوقی' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="type_legal">حقوقی (شرکت)</label>
                        </div>
                    </div>
                </div>

                <!-- بخش حقیقی -->
                <div id="section_individual" class="form-section <?= $postedType === 'حقوقی' ? '' : 'active' ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label required">نام و نام خانوادگی</label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?= h($_POST['full_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label required">شماره تماس</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       placeholder="مثال: 09123456789"
                                       value="<?= h($_POST['phone'] ?? '') ?>">
                                <small class="form-text text-muted">۱۰ تا ۱۱ رقم، فقط عدد</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- بخش حقوقی -->
                <div id="section_legal" class="form-section <?= $postedType === 'حقوقی' ? 'active' : '' ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company" class="form-label required">اسم شرکت</label>
                                <input type="text" class="form-control" id="company" name="company"
                                       value="<?= h($_POST['company'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="responsible_name" class="form-label required">نام مسئول شرکت</label>
                                <input type="text" class="form-control" id="responsible_name" name="responsible_name"
                                       value="<?= h($_POST['responsible_name'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="company_phone" class="form-label required">شماره تماس شرکت</label>
                                <input type="text" class="form-control" id="company_phone" name="company_phone"
                                       placeholder="مثال: 02112345678"
                                       value="<?= h($_POST['company_phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="responsible_phone" class="form-label required">شماره تماس مسئول شرکت</label>
                                <input type="text" class="form-control" id="responsible_phone" name="responsible_phone"
                                       placeholder="مثال: 09121234567"
                                       value="<?= h($_POST['responsible_phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <input type="text" class="form-control" id="notes" name="notes"
                                       value="<?= h($_POST['notes'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- فیلدهای مشترک -->
                <div class="row">
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label for="address" class="form-label required">آدرس</label>
                            <textarea class="form-control" id="address" name="address" rows="1"><?= h($_POST['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="operator_name" class="form-label required">نام اپراتور تجهیزات</label>
                            <input type="text" class="form-control" id="operator_name" name="operator_name"
                                   value="<?= h($_POST['operator_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="operator_phone" class="form-label required">شماره تماس اپراتور</label>
                            <input type="text" class="form-control" id="operator_phone" name="operator_phone"
                                   value="<?= h($_POST['operator_phone'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> افزودن مشتری
                </button>
            </form>
        </div>
    </div>

    <!-- جدول نمایش مشتریان -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>لیست مشتریان</span>
            <div>
                <span class="badge bg-secondary">صفحه <?= (int)$page ?> از <?= (int)$total_pages ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($customers) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>نوع</th>
                            <th>نام / شرکت</th>
                            <th>مسئول</th>
                            <th>تلفن‌ها</th>
                            <th>اپراتور</th>
                            <th>آدرس</th>
                            <th>توضیحات</th>
                            <th>عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($customers as $c): ?>
                            <?php
                            $ctype = $c['customer_type'] ?? 'حقیقی';
                            $isLegal = ($ctype === 'حقوقی');
                            ?>
                            <tr>
                                <td><?= (int)$c['id'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $isLegal ? 'warning' : 'success' ?> badge-type">
                                        <?= h($ctype) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isLegal): ?>
                                        <?= $c['company'] ? h($c['company']) : '<span class="text-muted">ندارد</span>' ?>
                                    <?php else: ?>
                                        <?= $c['full_name'] ? h($c['full_name']) : '<span class="text-muted">ندارد</span>' ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isLegal): ?>
                                        <?= $c['responsible_name'] ? h($c['responsible_name']) : '<span class="text-muted">ندارد</span>' ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isLegal): ?>
                                        <?php if (!empty($c['company_phone'])): ?>
                                            <span class="badge bg-primary me-1"><?= h($c['company_phone']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($c['responsible_phone'])): ?>
                                            <span class="badge bg-info"><?= h($c['responsible_phone']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($c['phone'])): ?>
                                            <span class="badge bg-primary"><?= h($c['phone']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">ندارد</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['operator_name'])): ?>
                                        <div><?= h($c['operator_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['operator_phone'])): ?>
                                        <span class="badge bg-dark"><?= h($c['operator_phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['address'])): ?>
                                        <span class="truncate" title="<?= h($c['address']) ?>"><?= h($c['address']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">ندارد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['notes'])): ?>
                                        <span class="truncate" title="<?= h($c['notes']) ?>"><?= h($c['notes']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_customer.php?id=<?= (int)$c['id'] ?>" class="btn btn-warning" title="ویرایش">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (can_delete_customers()): ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('آیا از حذف این مشتری مطمئن هستید؟');">
                                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" name="delete_customer" value="1" class="btn btn-danger" title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- صفحه‌بندی -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted">هیچ مشتری یافت نشد.</p>
                    <?php if ($search !== ''): ?>
                        <a href="customers.php" class="btn btn-primary">مشاهده همه مشتریان</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const sectionIndividual = document.getElementById('section_individual');
    const sectionLegal      = document.getElementById('section_legal');
    const rInd              = document.getElementById('type_individual');
    const rLeg              = document.getElementById('type_legal');

    function toggleSections(){
        if (rLeg.checked){
            sectionLegal.classList.add('active');
            sectionIndividual.classList.remove('active');
            // required حقوقی
            setReq('company', true);
            setReq('responsible_name', true);
            setReq('company_phone', true);
            setReq('responsible_phone', true);
            // غیرلازم حقیقی
            setReq('full_name', false);
            setReq('phone', false);
        } else {
            sectionIndividual.classList.add('active');
            sectionLegal.classList.remove('active');
            // required حقیقی
            setReq('full_name', true);
            setReq('phone', true);
            // غیرلازم حقوقی
            setReq('company', false);
            setReq('responsible_name', false);
            setReq('company_phone', false);
            setReq('responsible_phone', false);
        }
        // مشترک‌ها همیشه لازم
        setReq('address', true);
        setReq('operator_name', true);
        setReq('operator_phone', true);
    }
    function setReq(id, isReq){
        const el = document.getElementById(id);
        if (el){
            if (isReq) el.setAttribute('required', 'required');
            else el.removeAttribute('required');
        }
    }

    rInd.addEventListener('change', toggleSections);
    rLeg.addEventListener('change', toggleSections);
    toggleSections();

    // اعتبارسنجی ساده شماره‌ها سمت کلاینت
    document.getElementById('customerForm').addEventListener('submit', function(e){
        const digits = s => (s || '').replace(/\D+/g, '');
        const isPhone = s => /^\d{10,11}$/.test(s);

        const isLegal = rLeg.checked;

        // مشترک
        const opPhone = digits(document.getElementById('operator_phone').value);
        if (!isPhone(opPhone)){
            e.preventDefault();
            alert('شماره تماس اپراتور باید ۱۰ تا ۱۱ رقم و فقط شامل عدد باشد.');
            document.getElementById('operator_phone').focus();
            return;
        }

        if (isLegal){
            const cph = digits(document.getElementById('company_phone').value);
            const rph = digits(document.getElementById('responsible_phone').value);
            if (!isPhone(cph)){
                e.preventDefault();
                alert('شماره تماس شرکت معتبر نیست.');
                document.getElementById('company_phone').focus();
                return;
            }
            if (!isPhone(rph)){
                e.preventDefault();
                alert('شماره تماس مسئول شرکت معتبر نیست.');
                document.getElementById('responsible_phone').focus();
                return;
            }
        } else {
            const ph = digits(document.getElementById('phone').value);
            if (!isPhone(ph)){
                e.preventDefault();
                alert('شماره تماس باید ۱۰ تا ۱۱ رقم و فقط شامل عدد باشد.');
                document.getElementById('phone').focus();
                return;
            }
        }
    });

    // بستن خودکار پیام‌ها
    setTimeout(function(){
        document.querySelectorAll('.alert').forEach(function(alertEl){
            try { new bootstrap.Alert(alertEl).close(); } catch(e){}
        });
    }, 5000);
})();
</script>
</body>
</html>
