<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// حذف دستگاه
if (isset($_GET['delete_id']) && $_SESSION['role'] == 'ادمین') {
    $delete_id = (int)$_GET['delete_id'];
    
    // شروع تراکنش برای حذف ایمن
    $pdo->beginTransaction();
    try {
        // ابتدا تصاویر را حذف کنید
        $stmt = $pdo->prepare("DELETE FROM asset_images WHERE asset_id = ?");
        $stmt->execute([$delete_id]);
        
        // سپس دستگاه را حذف کنید
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        $pdo->commit();
        $success = "دستگاه با موفقیت حذف شد!";
        header('Location: reports.php?success=' . urlencode($success));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در حذف دستگاه: " . $e->getMessage();
        header('Location: reports.php?error=' . urlencode($error));
        exit();
    }
}

// دریافت همه دستگاه‌ها با اطلاعات کامل
try {
    $stmt = $pdo->query("
        SELECT a.*, at.display_name as type_name, at.name as type_code 
        FROM assets a
        LEFT JOIN asset_types at ON a.type_id = at.id
        ORDER BY a.created_at DESC
    ");
    $assets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("خطا در دریافت اطلاعات: " . $e->getMessage());
}

// دریافت فیلدهای هر نوع برای نمایش در Modal
$field_names = [];
try {
    $types = $pdo->query("SELECT * FROM asset_types")->fetchAll();
    foreach ($types as $type) {
        $stmt = $pdo->prepare("SELECT id, field_name FROM asset_fields WHERE type_id = ?");
        $stmt->execute([$type['id']]);
        $fields = $stmt->fetchAll();
        foreach ($fields as $field) {
            $field_names[$type['name']][$field['id']] = $field['field_name'];
        }
    }
} catch (PDOException $e) {
    // خطا را لاگ کنید اما اجرا ادامه یابد
    error_log("Error getting field names: " . $e->getMessage());
}

// دریافت عکس‌های هر دستگاه
$asset_images = [];
foreach ($assets as $asset) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM asset_images WHERE asset_id = ?");
        $stmt->execute([$asset['id']]);
        $asset_images[$asset['id']] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $asset_images[$asset['id']] = [];
        error_log("Error getting images for asset {$asset['id']}: " . $e->getMessage());
    }
}

// پردازش داده‌های JSON با بررسی خطا
foreach ($assets as &$asset) {
    if (!empty($asset['custom_data'])) {
        $decoded = json_decode($asset['custom_data'], true);
        $asset['custom_data'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
    } else {
        $asset['custom_data'] = [];
    }
}
unset($asset);

// نمایش پیام‌ها
$success = isset($_GET['success']) ? urldecode($_GET['success']) : '';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات دستگاه‌ها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .table-responsive {
            overflow-x: auto;
        }
        .badge {
            font-size: 0.85em;
        }
        .modal-lg {
            max-width: 90%;
        }
        .img-thumbnail {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="text-center mb-4">گزارشات دستگاه‌های ثبت شده</h2>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>همه دستگاه‌ها (<?php echo count($assets); ?> دستگاه)</span>
                <a href="assets.php" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle"></i> ثبت دستگاه جدید
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($assets)): ?>
                    <div class="alert alert-info text-center">
                        هیچ دستگاهی ثبت نشده است.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>نام دستگاه</th>
                                    <th>نوع دستگاه</th>
                                    <th>شماره سریال</th>
                                    <th>تاریخ خرید</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['id']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['type_name'] ?? 'نامشخص'); ?></td>
                                    <td><?php echo htmlspecialchars($asset['serial_number'] ?? 'ندارد'); ?></td>
                                    <td><?php echo htmlspecialchars($asset['purchase_date'] ?? 'ثبت نشده'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            if ($asset['status'] == 'فعال') echo 'success';
                                            elseif ($asset['status'] == 'غیرفعال') echo 'danger';
                                            elseif ($asset['status'] == 'در حال تعمیر') echo 'warning';
                                            else echo 'secondary';
                                        ?>">
                                            <?php echo htmlspecialchars($asset['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal<?php echo $asset['id']; ?>">
                                                جزئیات
                                            </button>
                                            <a href="edit_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-warning">ویرایش</a>
                                            <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                            <a href="reports.php?delete_id=<?php echo $asset['id']; ?>" class="btn btn-danger" 
                                               onclick="return confirm('آیا از حذف این دستگاه مطمئن هستید؟ این عمل غیرقابل بازگشت است.')">حذف</a>
                                            <?php endif; ?>
                                        </div>
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

    <!-- Modal های جزئیات -->
    <?php foreach ($assets as $asset): ?>
    <div class="modal fade" id="detailsModal<?php echo $asset['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">جزئیات دستگاه #<?php echo htmlspecialchars($asset['id']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>اطلاعات اصلی</h6>
                            <p><strong>نام دستگاه:</strong> <?php echo htmlspecialchars($asset['name']); ?></p>
                            <p><strong>نوع دستگاه:</strong> <?php echo htmlspecialchars($asset['type_name'] ?? 'نامشخص'); ?></p>
                            <p><strong>شماره سریال:</strong> <?php echo htmlspecialchars($asset['serial_number'] ?? 'ندارد'); ?></p>
                            <p><strong>تاریخ خرید:</strong> <?php echo htmlspecialchars($asset['purchase_date'] ?? 'ثبت نشده'); ?></p>
                            <p><strong>وضعیت:</strong> 
                                <span class="badge bg-<?php 
                                    if ($asset['status'] == 'فعال') echo 'success';
                                    elseif ($asset['status'] == 'غیرفعال') echo 'danger';
                                    elseif ($asset['status'] == 'در حال تعمیر') echo 'warning';
                                    else echo 'secondary';
                                ?>">
                                    <?php echo htmlspecialchars($asset['status']); ?>
                                </span>
                            </p>
                            <p><strong>تاریخ ثبت:</strong> <?php echo htmlspecialchars($asset['created_at']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if (($asset['type_code'] ?? '') === 'generator'): ?>
                                <h6>مشخصات ژنراتور</h6>
                                <?php if (!empty($asset['brand'])): ?><p><strong>برند:</strong> <?php echo htmlspecialchars($asset['brand']); ?></p><?php endif; ?>
                                <?php if (!empty($asset['engine_model'])): ?><p><strong>مدل موتور:</strong> <?php echo htmlspecialchars($asset['engine_model']); ?></p><?php endif; ?>
                                <?php if (!empty($asset['engine_serial'])): ?><p><strong>سریال موتور:</strong> <?php echo htmlspecialchars($asset['engine_serial']); ?></p><?php endif; ?>
                                <?php if (!empty($asset['alternator_model'])): ?><p><strong>مدل آلترناتور:</strong> <?php echo htmlspecialchars($asset['alternator_model']); ?></p><?php endif; ?>
                                <?php if (!empty($asset['alternator_serial'])): ?><p><strong>سریال آلترناتور:</strong> <?php echo htmlspecialchars($asset['alternator_serial']); ?></p><?php endif; ?>
                                <?php if (!empty($asset['power_capacity'])): ?><p><strong>ظرفیت توان:</strong> <?php echo htmlspecialchars($asset['power_capacity']); ?> کیلووات</p><?php endif; ?>
                            <?php else: ?>
                                <h6>مشخصات اختصاصی</h6>
                                <?php 
                                $hasCustomData = false;
                                if (!empty($asset['custom_data'])): 
                                    foreach ($asset['custom_data'] as $field_id => $value): 
                                        if (!empty($value)): 
                                            $hasCustomData = true;
                                            $fieldName = $field_names[$asset['type_code']][$field_id] ?? 'فیلد ' . $field_id;
                                ?>
                                            <p><strong><?php echo htmlspecialchars($fieldName); ?>:</strong> 
                                            <?php echo htmlspecialchars($value); ?></p>
                                <?php 
                                        endif;
                                    endforeach;
                                endif;
                                
                                if (!$hasCustomData): ?>
                                    <p class="text-muted">هیچ مشخصه اختصاصی ثبت نشده است.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- نمایش عکس‌ها -->
                    <?php if (!empty($asset_images[$asset['id']])): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>عکس‌های دستگاه</h6>
                            <div class="row">
                                <?php foreach ($asset_images[$asset['id']] as $image): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <strong><?php echo htmlspecialchars($image['field_name']); ?>:</strong>
                                                <div class="text-center mt-2">
                                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                         class="img-thumbnail" 
                                                         style="max-width: 100%; height: 150px; object-fit: cover;"
                                                         onerror="this.src='assets/images/placeholder.jpg'">
                                                </div>
                                                <div class="text-center mt-2">
                                                    <a href="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                                        مشاهده کامل
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="edit_asset.php?id=<?php echo $asset['id']; ?>" class="btn btn-warning">ویرایش این دستگاه</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // مدیریت alertهای قابل dismiss
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>