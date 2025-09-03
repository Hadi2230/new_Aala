<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$asset_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($asset_id <= 0) { header('Location: reports.php'); exit(); }

// اطلاعات دستگاه
try {
    $stmt = $pdo->prepare("SELECT a.*, at.display_name AS type_name FROM assets a LEFT JOIN asset_types at ON a.type_id = at.id WHERE a.id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
} catch (Throwable $ex) {
    error_log('profile.php asset fetch error: ' . $ex->getMessage());
    $asset = false;
}
if (!$asset) { header('Location: reports.php'); exit(); }

// انتساب‌های مرتبط و مشتری فعلی (آخرین انتساب)
$assign = $pdo->prepare("SELECT aa.*, c.full_name, c.phone, c.company FROM asset_assignments aa JOIN customers c ON aa.customer_id=c.id WHERE aa.asset_id = ? ORDER BY aa.created_at DESC LIMIT 1");
$assign->execute([$asset_id]);
$current = $assign->fetch();

// تصاویر
try {
    $images = $pdo->prepare("SELECT * FROM asset_images WHERE asset_id = ?");
    $images->execute([$asset_id]);
    $images = $images->fetchAll();
} catch (Throwable $ex) {
    error_log('profile.php images fetch error: ' . $ex->getMessage());
    $images = [];
}

// سرویس‌ها و تسک‌ها
try {
    $svc = $pdo->prepare("SELECT * FROM asset_services WHERE asset_id = ? ORDER BY service_date DESC, id DESC");
    $svc->execute([$asset_id]);
    $services = $svc->fetchAll();
} catch (Throwable $ex) {
    error_log('profile.php services fetch error: ' . $ex->getMessage());
    $services = [];
}

try {
    $tsk = $pdo->prepare("SELECT * FROM maintenance_tasks WHERE asset_id = ? ORDER BY FIELD(status,'برنامه‌ریزی','در حال انجام','انجام شده','لغو'), planned_date ASC, id DESC");
    $tsk->execute([$asset_id]);
    $tasks = $tsk->fetchAll();
} catch (Throwable $ex) {
    error_log('profile.php tasks fetch error: ' . $ex->getMessage());
    $tasks = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل دستگاه #<?= $asset['id'] ?> - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            --border-radius: 8px;
            --border-radius-sm: 6px;
            --border-radius-lg: 12px;
            
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            background-color: #f8f9fa;
            color: #111827;
            line-height: 1.6;
        }

        /* استایل‌های عادی صفحه */
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            border-bottom: 3px solid #ffc107;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }

        .card {
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: #f8f9fc;
            color: #4e73df;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0 !important;
            padding: 0.75rem 1.25rem;
            font-weight: 700;
        }

        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #111827;
            background-color: #ffffff;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .table th {
            background-color: #f8f9fc;
            color: #111827;
            font-weight: 700;
            padding: 0.75rem;
            border-bottom: 2px solid #e3e6f0;
            text-align: right;
        }

        .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }

        .btn {
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        /* استایل‌های چاپ - این بخش مشکل را حل می‌کند */
        @media print {
            /* مخفی کردن عناصر غیر ضروری در چاپ */
            .no-print, .navbar, .btn, .theme-switch, .alert {
                display: none !important;
            }
            
            /* تنظیمات کلی برای چاپ */
            body, html {
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                color: black !important;
                font-size: 12pt !important;
                direction: rtl !important;
            }
            
            .container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* نمایش هدر چاپ */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #000;
            }
            
            .print-title {
                font-weight: 700;
                font-size: 20px;
                margin: 0;
            }
            
            .print-meta {
                font-size: 12px;
                color: #555;
                margin: 5px 0;
            }
            
            /* بهبود نمایش کارت‌ها در چاپ */
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                break-inside: avoid;
                margin-bottom: 15px;
            }
            
            .card-header {
                background: #f2f2f2 !important;
                color: #000 !important;
                border-bottom: 1px solid #000 !important;
            }
            
            /* بهبود نمایش جداول در چاپ */
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: avoid;
            }
            
            .table th, .table td {
                border: 1px solid #000 !important;
                padding: 6px 8px !important;
                font-size: 11pt;
            }
            
            .table th {
                background-color: #f2f2f2 !important;
                color: #000 !important;
            }
            
            /* جلوگیری از تقسیم سطرهای جدول بین صفحات */
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            
            /* مخفی کردن لینک‌ها در چاپ */
            a[href]:after {
                content: "" !important;
            }
            
            /* بهبود نمایش تصاویر در چاپ */
            img {
                max-width: 100% !important;
                height: auto !important;
            }
            
            /* جلوگیری از تقسیم عناصر بین صفحات */
            .row {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            
            /* نمایش تمام محتوای مهم */
            #print-area {
                display: block !important;
                width: 100% !important;
                opacity: 1 !important;
                visibility: visible !important;
            }
            
            /* نمایش دکمه‌های modal در چاپ */
            .modal, .modal-backdrop {
                display: none !important;
            }
        }

        /* استایل‌های ویژه برای نمایش عادی */
        .print-header {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4" id="print-area">
        <!-- هدر مخصوص چاپ -->
        <div class="print-header">
            <div class="print-title">شرکت اعلا نیرو - پروفایل دستگاه</div>
            <div class="print-meta">تاریخ چاپ: <?= date('Y/m/d H:i') ?></div>
            <div class="print-meta">نام دستگاه: <?= htmlspecialchars($asset['name']) ?></div>
            <div class="print-meta">شماره سریال: <?= htmlspecialchars($asset['serial_number'] ?? '-') ?></div>
            <?php if(isset($_SESSION['username'])): ?>
                <div class="print-meta">چاپ شده توسط: <?= htmlspecialchars($_SESSION['username']) ?></div>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h2><i class="fas fa-id-card"></i> پروفایل دستگاه #<?= $asset['id'] ?></h2>
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> چاپ</button>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">اطلاعات کلی</div>
                    <div class="card-body">
                        <p><strong>نام:</strong> <?= htmlspecialchars($asset['name']) ?></p>
                        <p><strong>نوع:</strong> <?= htmlspecialchars($asset['type_name'] ?? '-') ?></p>
                        <p><strong>وضعیت:</strong> <?= htmlspecialchars($asset['status']) ?></p>
                        <p><strong>سریال:</strong> <?= htmlspecialchars($asset['serial_number'] ?? '-') ?></p>
                        <p><strong>تاریخ خرید:</strong> <?= htmlspecialchars($asset['purchase_date'] ?? '-') ?></p>
                        <p><strong>برند/مدل:</strong> <?= htmlspecialchars(($asset['brand'] ?? '') . (($asset['model'] ?? '') ? ' / ' . $asset['model'] : '')) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">مشتری و انتساب فعلی</div>
                    <div class="card-body">
                        <?php if ($current): ?>
                            <p><strong>مشتری:</strong> <?= htmlspecialchars($current['full_name']) ?> (<?= htmlspecialchars($current['company'] ?? '-') ?>)</p>
                            <p><strong>تلفن:</strong> <?= htmlspecialchars($current['phone'] ?? '-') ?></p>
                            <p><strong>تاریخ انتساب:</strong> <?= htmlspecialchars($current['assignment_date'] ?? '-') ?></p>
                            <p><strong>یادداشت:</strong> <?= htmlspecialchars($current['notes'] ?? '-') ?></p>
                        <?php else: ?>
                            <p class="text-muted">فعلاً به مشتریی منتسب نیست.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">مشخصات فنی</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th style="width:25%">مدل موتور</th>
                                        <td><?= htmlspecialchars($asset['engine_model'] ?? '-') ?></td>
                                        <th style="width:25%">سریال موتور</th>
                                        <td><?= htmlspecialchars($asset['engine_serial'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مدل آلترناتور</th>
                                        <td><?= htmlspecialchars($asset['alternator_model'] ?? '-') ?></td>
                                        <th>سریال آلترناتور</th>
                                        <td><?= htmlspecialchars($asset['alternator_serial'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مدل دستگاه</th>
                                        <td><?= htmlspecialchars($asset['device_model'] ?? '-') ?></td>
                                        <th>سریال دستگاه</th>
                                        <td><?= htmlspecialchars($asset['device_serial'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>ظرفیت توان</th>
                                        <td><?= htmlspecialchars($asset['power_capacity'] ?? '-') ?></td>
                                        <th>نوع سوخت</th>
                                        <td><?= htmlspecialchars($asset['engine_type'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مدل کنترل پنل</th>
                                        <td><?= htmlspecialchars($asset['control_panel_model'] ?? '-') ?></td>
                                        <th>مدل بریکر</th>
                                        <td><?= htmlspecialchars($asset['breaker_model'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>مشخصات تانک سوخت</th>
                                        <td><?= htmlspecialchars($asset['fuel_tank_specs'] ?? '-') ?></td>
                                        <th>باتری</th>
                                        <td><?= htmlspecialchars($asset['battery'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>باتری شارژر</th>
                                        <td><?= htmlspecialchars($asset['battery_charger'] ?? '-') ?></td>
                                        <th>هیتر</th>
                                        <td><?= htmlspecialchars($asset['heater'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>حجم روغن</th>
                                        <td><?= htmlspecialchars($asset['oil_capacity'] ?? '-') ?></td>
                                        <th>حجم آب رادیاتور</th>
                                        <td><?= htmlspecialchars($asset['radiator_capacity'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>ضدیخ</th>
                                        <td><?= htmlspecialchars($asset['antifreeze'] ?? '-') ?></td>
                                        <th>سایر اقلام مولد</th>
                                        <td><?= htmlspecialchars($asset['other_items'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>تاریخ ورود کارگاه</th>
                                        <td><?= htmlspecialchars($asset['workshop_entry_date'] ?? '-') ?></td>
                                        <th>تاریخ خروج کارگاه</th>
                                        <td><?= htmlspecialchars($asset['workshop_exit_date'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>لینک دیتاشیت</th>
                                        <td><?= htmlspecialchars($asset['datasheet_link'] ?? '-') ?></td>
                                        <th>منوال موتور</th>
                                        <td><?= htmlspecialchars($asset['engine_manual_link'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>منوال آلترناتور</th>
                                        <td><?= htmlspecialchars($asset['alternator_manual_link'] ?? '-') ?></td>
                                        <th>منوال کنترل پنل</th>
                                        <td><?= htmlspecialchars($asset['control_panel_manual_link'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <th>توضیحات</th>
                                        <td colspan="3"><?php echo nl2br(htmlspecialchars($asset['description'] ?? '-')); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($images): ?>
        <div class="row g-3 mt-2">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">تصاویر</div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($images as $img): ?>
                                <div class="col-md-3 mb-2">
                                    <div class="border p-2 rounded">
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($img['field_name']) ?></div>
                                        <img src="<?= htmlspecialchars($img['image_path']) ?>" class="img-fluid rounded">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>سوابق سرویس</span>
                        <button class="btn btn-sm btn-primary no-print" data-bs-toggle="modal" data-bs-target="#addServiceModal">ثبت سرویس</button>
                    </div>
                    <div class="card-body">
                        <?php if (!$services): ?>
                            <p class="text-muted">سرویسی ثبت نشده است.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>نوع</th>
                                        <th>مجری</th>
                                        <th>خلاصه</th>
                                        <th>بعدی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($services as $s): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['service_date']) ?></td>
                                            <td><?= htmlspecialchars($s['service_type']) ?></td>
                                            <td><?= htmlspecialchars($s['performed_by'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($s['summary'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($s['next_due_date'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>تسک‌های نگهداشت</span>
                        <button class="btn btn-sm btn-outline-primary no-print" data-bs-toggle="modal" data-bs-target="#addTaskModal">افزودن تسک</button>
                    </div>
                    <div class="card-body">
                        <?php if (!$tasks): ?>
                            <p class="text-muted">تسکی ثبت نشده است.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>عنوان</th>
                                            <th>وضعیت</th>
                                            <th>اولویت</th>
                                            <th>تاریخ برنامه</th>
                                            <th>انجام</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tasks as $t): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($t['title']) ?></td>
                                                <td><span class="badge bg-info"><?= htmlspecialchars($t['status']) ?></span></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($t['priority']) ?></span></td>
                                                <td><?= htmlspecialchars($t['planned_date'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($t['done_date'] ?? '-') ?></td>
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

        <!-- Modals -->
        <div class="modal fade no-print" id="addServiceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="save_service.php">
                        <div class="modal-header">
                            <h5 class="modal-title">ثبت سرویس</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
                            <div class="mb-3">
                                <label class="form-label">تاریخ سرویس</label>
                                <input type="text" class="form-control jalali-date" name="service_date" required placeholder="YYYY/MM/DD">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">نوع سرویس</label>
                                <select class="form-select" name="service_type">
                                    <option value="دوره‌ای">دوره‌ای</option>
                                    <option value="اضطراری">اضطراری</option>
                                    <option value="نصب">نصب</option>
                                    <option value="بازدید">بازدید</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">مجری</label>
                                <input type="text" class="form-control" name="performed_by">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">خلاصه</label>
                                <input type="text" class="form-control" name="summary">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">تاریخ سررسید بعدی</label>
                                <input type="text" class="form-control jalali-date" name="next_due_date" placeholder="YYYY/MM/DD">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">انصراف</button>
                            <button class="btn btn-primary" type="submit">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade no-print" id="addTaskModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="save_task.php">
                        <div class="modal-header">
                            <h5 class="modal-title">افزودن تسک نگهداشت</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
                            <div class="mb-3">
                                <label class="form-label">عنوان *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">وضعیت</label>
                                        <select class="form-select" name="status">
                                            <option value="برنامه‌ریزی">برنامه‌ریزی</option>
                                            <option value="در حال انجام">در حال انجام</option>
                                            <option value="انجام شده">انجام شده</option>
                                            <option value="لغو">لغو</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">اولویت</label>
                                        <select class="form-select" name="priority">
                                            <option value="متوسط">متوسط</option>
                                            <option value="بالا">بالا</option>
                                            <option value="کم">کم</option>
                                            <option value="فوری">فوری</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">تاریخ برنامه</label>
                                        <input type="text" class="form-control jalali-date" name="planned_date" placeholder="YYYY/MM/DD">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">تاریخ انجام</label>
                                        <input type="text" class="form-control jalali-date" name="done_date" placeholder="YYYY/MM/DD">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">انصراف</button>
                            <button class="btn btn-primary" type="submit">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تابع بهبود یافته برای چاپ
        function printPage() {
            // ذخیره حالت اصلی body
            const originalStyles = {
                overflow: document.body.style.overflow,
                height: document.body.style.height
            };
            
            // تنظیم حالت مناسب برای چاپ
            document.body.style.overflow = 'visible';
            document.body.style.height = 'auto';
            
            // چاپ صفحه
            window.print();
            
            // بازگرداندن حالت اصلی پس از چاپ
            setTimeout(() => {
                document.body.style.overflow = originalStyles.overflow;
                document.body.style.height = originalStyles.height;
            }, 500);
        }
        
        // اضافه کردن event listener برای دکمه چاپ
        document.addEventListener('DOMContentLoaded', function() {
            const printButton = document.querySelector('button[onclick="window.print()"]');
            if (printButton) {
                printButton.onclick = printPage;
            }
        });
    </script>
</body>
</html>