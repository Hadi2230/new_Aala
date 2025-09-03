<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// اطمینان از ارسال هدر UTF-8 برای جلوگیری از به‌هم‌ریختگی حروف
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// ثبت لاگ
logAction($pdo, 'VIEW_ASSETS', 'مشاهده صفحه مدیریت دارایی‌ها');

// افزودن دارایی جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_asset'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        // دریافت داده‌های اصلی
        $name = sanitizeInput($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $serial_number = sanitizeInput($_POST['serial_number']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);
        $status = sanitizeInput($_POST['status']);
        $brand = sanitizeInput($_POST['brand'] ?? '');
        $model = sanitizeInput($_POST['model'] ?? '');
        
        // دریافت نوع دارایی
        $stmt = $pdo->prepare("SELECT name FROM asset_types WHERE id = ?");
        $stmt->execute([$type_id]);
        $asset_type = $stmt->fetch();
        $asset_type_name = $asset_type['name'] ?? '';
        
        // فیلدهای خاص بر اساس نوع دارایی
        $power_capacity = sanitizeInput($_POST['power_capacity'] ?? '');
        $engine_type = sanitizeInput($_POST['engine_type'] ?? '');
        $consumable_type = sanitizeInput($_POST['consumable_type'] ?? '');
        
        // فیلدهای مخصوص ژنراتور
        $engine_model = sanitizeInput($_POST['engine_model'] ?? '');
        $engine_serial = sanitizeInput($_POST['engine_serial'] ?? '');
        $alternator_model = sanitizeInput($_POST['alternator_model'] ?? '');
        $alternator_serial = sanitizeInput($_POST['alternator_serial'] ?? '');
        $device_model = sanitizeInput($_POST['device_model'] ?? '');
        $device_serial = sanitizeInput($_POST['device_serial'] ?? '');
        $control_panel_model = sanitizeInput($_POST['control_panel_model'] ?? '');
        $breaker_model = sanitizeInput($_POST['breaker_model'] ?? '');
        $fuel_tank_specs = sanitizeInput($_POST['fuel_tank_specs'] ?? '');
        $battery = sanitizeInput($_POST['battery'] ?? '');
        $battery_charger = sanitizeInput($_POST['battery_charger'] ?? '');
        $heater = sanitizeInput($_POST['heater'] ?? '');
        $oil_capacity = sanitizeInput($_POST['oil_capacity'] ?? '');
        $radiator_capacity = sanitizeInput($_POST['radiator_capacity'] ?? '');
        $antifreeze = sanitizeInput($_POST['antifreeze'] ?? '');
        $other_items = sanitizeInput($_POST['other_items'] ?? '');
        $workshop_entry_date = sanitizeInput($_POST['workshop_entry_date'] ?? '');
        $workshop_exit_date = sanitizeInput($_POST['workshop_exit_date'] ?? '');
        $datasheet_link = sanitizeInput($_POST['datasheet_link'] ?? '');
        $engine_manual_link = sanitizeInput($_POST['engine_manual_link'] ?? '');
        $alternator_manual_link = sanitizeInput($_POST['alternator_manual_link'] ?? '');
        $control_panel_manual_link = sanitizeInput($_POST['control_panel_manual_link'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        // پارت نامبرها
        $oil_filter_part = sanitizeInput($_POST['oil_filter_part'] ?? '');
        $fuel_filter_part = sanitizeInput($_POST['fuel_filter_part'] ?? '');
        $water_fuel_filter_part = sanitizeInput($_POST['water_fuel_filter_part'] ?? '');
        $air_filter_part = sanitizeInput($_POST['air_filter_part'] ?? '');
        $water_filter_part = sanitizeInput($_POST['water_filter_part'] ?? '');
        
        // فیلدهای مخصوص قطعات و اقلام مصرفی
        $part_description = sanitizeInput($_POST['part_description'] ?? '');
        $part_serial = sanitizeInput($_POST['part_serial'] ?? '');
        $part_register_date = sanitizeInput($_POST['part_register_date'] ?? '');
        $part_notes = sanitizeInput($_POST['part_notes'] ?? '');
        
        // فیلدهای مخصوص نحوه تامین
        $supply_method = sanitizeInput($_POST['supply_method'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $supplier_name = sanitizeInput($_POST['supplier_name'] ?? '');
        $supplier_contact = sanitizeInput($_POST['supplier_contact'] ?? '');
        
        // درج دارایی اصلی
        $stmt = $pdo->prepare("INSERT INTO assets (name, type_id, serial_number, purchase_date, status, brand, model, 
                              power_capacity, engine_type, consumable_type, engine_model, engine_serial, 
                              alternator_model, alternator_serial, device_model, device_serial, control_panel_model, 
                              breaker_model, fuel_tank_specs, battery, battery_charger, heater, oil_capacity, 
                              radiator_capacity, antifreeze, other_items, workshop_entry_date, workshop_exit_date, 
                              datasheet_link, engine_manual_link, alternator_manual_link, control_panel_manual_link, 
                              description, oil_filter_part, fuel_filter_part, water_fuel_filter_part, air_filter_part, 
                              water_filter_part, part_description, part_serial, part_register_date, part_notes,
                              supply_method, location, quantity, supplier_name, supplier_contact) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $name, $type_id, $serial_number, $purchase_date, $status, $brand, $model,
            $power_capacity, $engine_type, $consumable_type, $engine_model, $engine_serial,
            $alternator_model, $alternator_serial, $device_model, $device_serial, $control_panel_model,
            $breaker_model, $fuel_tank_specs, $battery, $battery_charger, $heater, $oil_capacity,
            $radiator_capacity, $antifreeze, $other_items, $workshop_entry_date, $workshop_exit_date,
            $datasheet_link, $engine_manual_link, $alternator_manual_link, $control_panel_manual_link,
            $description, $oil_filter_part, $fuel_filter_part, $water_fuel_filter_part, $air_filter_part,
            $water_filter_part, $part_description, $part_serial, $part_register_date, $part_notes,
            $supply_method, $location, $quantity, $supplier_name, $supplier_contact
        ]);
        
        $asset_id = $pdo->lastInsertId();
        
        // آپلود عکس‌ها
        $upload_dir = 'uploads/assets/';
        $image_fields = [
            'oil_filter', 'fuel_filter', 'water_fuel_filter', 
            'air_filter', 'water_filter', 'device_image'
        ];
        
        foreach ($image_fields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                try {
                    $image_path = uploadFile($_FILES[$field], $upload_dir);
                    
                    $stmt = $pdo->prepare("INSERT INTO asset_images (asset_id, field_name, image_path) VALUES (?, ?, ?)");
                    $stmt->execute([$asset_id, $field, $image_path]);
                } catch (Exception $e) {
                    // خطا در آپلود عکس - ادامه می‌دهیم
                    error_log("خطا در آپلود عکس $field: " . $e->getMessage());
                }
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "دارایی با موفقیت افزوده شد!";
        logAction($pdo, 'ADD_ASSET', "افزودن دارایی جدید: $name (ID: $asset_id)");
        
        header('Location: assets.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در افزودن دارایی: " . $e->getMessage();
        logAction($pdo, 'ADD_ASSET_ERROR', "خطا در افزودن دارایی: " . $e->getMessage());
    }
}

// حذف دارایی
if (isset($_GET['delete_id'])) {
    checkPermission('ادمین');
    
    $delete_id = (int)$_GET['delete_id'];
    
    try {
        // دریافت اطلاعات دارایی برای ثبت در لاگ
        $stmt = $pdo->prepare("SELECT name FROM assets WHERE id = ?");
        $stmt->execute([$delete_id]);
        $asset = $stmt->fetch();
        
        if ($asset) {
            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            $_SESSION['success'] = "دارایی با موفقیت حذف شد!";
            logAction($pdo, 'DELETE_ASSET', "حذف دارایی: " . $asset['name'] . " (ID: $delete_id)");
        } else {
            $_SESSION['error'] = "دارایی مورد نظر یافت نشد!";
        }
        
        header('Location: assets.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "خطا در حذف دارایی: " . $e->getMessage();
        logAction($pdo, 'DELETE_ASSET_ERROR', "خطا در حذف دارایی ID: $delete_id - " . $e->getMessage());
    }
}

// اطمینان از وجود انواع پیش‌فرض دارایی در دیتابیس
try {
    $defaults = [
        ['generator',   'ژنراتور'],
        ['power_motor', 'موتور برق'],
        ['consumable',  'اقلام مصرفی'],
        ['parts',       'قطعات']
    ];
    foreach ($defaults as [$name, $display]) {
        $chk = $pdo->prepare('SELECT id FROM asset_types WHERE name = ? LIMIT 1');
        $chk->execute([$name]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare('INSERT INTO asset_types (name, display_name) VALUES (?, ?)');
            $ins->execute([$name, $display]);
        }
    }
} catch (Throwable $e) {}

// دریافت انواع دارایی‌ها
$asset_types = $pdo->query("SELECT * FROM asset_types ORDER BY display_name")->fetchAll();

// جستجو و فیلتر
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// ساخت کوئری بر اساس فیلترها
$query = "SELECT a.*, at.display_name as type_display_name 
          FROM assets a 
          JOIN asset_types at ON a.type_id = at.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.name LIKE ? OR a.serial_number LIKE ? OR a.model LIKE ? OR a.brand LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($type_filter)) {
    $query .= " AND a.type_id = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assets = $stmt->fetchAll();

// دریافت تعداد کل دارایی‌ها برای نمایش
$total_assets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
$filtered_count = count($assets);
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دارایی‌ها - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        html, body {
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
        }
        .form-select, .form-control, .form-label, .btn, .card, option {
            font-family: inherit;
        }
        /* اطمینان از راست‌چین بودن و هم‌تراز راست در فهرست‌ها */
        .form-select, .form-control { direction: rtl; text-align: right; }
        option { direction: rtl; text-align: right; }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .search-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .dynamic-field {
            display: none;
        }
        .badge-status {
            font-size: 0.9rem;
            padding: 0.5em 0.8em;
        }
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            margin-left: 5px;
        }
        .filter-active {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 5px 10px;
            font-weight: 600;
        }
        #add-asset-form {
            display: none;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .preview-item {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        .preview-label {
            font-weight: bold;
            color: #555;
        }
        .supply-method-fields {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-server"></i> مدیریت دارایی‌ها</h2>
                    <div>
                        <span class="filter-active">
                            <i class="fas fa-filter"></i> 
                            <?php echo $filtered_count ?> از <?php echo $total_assets ?> مورد
                        </span>
                    </div>
                </div>

                <!-- کارت‌های عملیاتی -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-1">افزودن دارایی جدید</h5>
                                    <p class="text-muted mb-2">ثبت دستگاه با تمام مشخصات و تصاویر</p>
                                    <a href="javascript:void(0);" onclick="showAddAssetForm()" class="btn btn-primary">شروع ثبت</a>
                                </div>
                                <div class="display-4 text-primary"><i class="fas fa-plus-circle"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title mb-1">پروفایل دستگاه‌ها</h5>
                                    <p class="text-muted mb-2">مدیریت سرویس و نگهداشت هر دستگاه</p>
                                    <a href="profiles_list.php" class="btn btn-outline-primary">مشاهده پروفایل‌ها</a>
                                </div>
                                <div class="display-4 text-info"><i class="fas fa-id-card"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جستجو و فیلتر -->
                <div class="card search-box">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="جستجو بر اساس نام، سریال، مدل یا برند..." value="<?php echo htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="type_filter" class="form-select">
                                    <option value="">همه انواع</option>
                                    <?php foreach ($asset_types as $type): ?>
                                        <option value="<?php echo $type['id'] ?>" <?php echo $type_filter == $type['id'] ? 'selected' : '' ?>>
                                            <?php echo $type['display_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status_filter" class="form-select">
                                    <option value="">همه وضعیت‌ها</option>
                                    <option value="فعال" <?php echo $status_filter == 'فعال' ? 'selected' : '' ?>>فعال</option>
                                    <option value="غیرفعال" <?php echo $status_filter == 'غیرفعال' ? 'selected' : '' ?>>غیرفعال</option>
                                    <option value="در حال تعمیر" <?php echo $status_filter == 'در حال تعمیر' ? 'selected' : '' ?>>در حال تعمیر</option>
                                    <option value="آماده بهره‌برداری" <?php echo $status_filter == 'آماده بهره‌برداری' ? 'selected' : '' ?>>آماده بهره‌برداری</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> اعمال فیلتر
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="assets.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times"></i> حذف فیلتر
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- نمایش پیام‌ها -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- فرم افزودن دارایی -->
                <div class="card" id="add-asset-form">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> افزودن دارایی جدید</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assetForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                            
                            <!-- مرحله 1: انتخاب نوع دارایی -->
                            <div class="step active" id="step1">
                                <h4 class="mb-4 text-primary">انتخاب نوع دارایی</h4>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="type_id" class="form-label">نوع دارایی *</label>
                                            <select class="form-select" id="type_id" name="type_id" required onchange="showStep2()">
                                                <option value="">-- انتخاب کنید --</option>
                                                <?php foreach ($asset_types as $type): ?>
                                                    <option value="<?php echo $type['id'] ?>"><?php echo $type['display_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary" onclick="nextStep(2)">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                                </div>
                            </div>
                            
                            <!-- مرحله 2: اطلاعات خاص نوع دارایی -->
                            <div class="step" id="step2">
                                <h4 class="mb-4 text-primary" id="step2-title">اطلاعات دارایی</h4>
                                
                                <!-- فیلدهای مخصوص ژنراتور -->
                                <div id="generator_fields" class="dynamic-field">
                                    <h5 class="mb-3 text-secondary">مشخصات ژنراتور</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">نام دستگاه *</label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="serial_number" class="form-label">شماره سریال *</label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="purchase_date" class="form-label">تاریخ خرید</label>
                                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">وضعیت *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="فعال">فعال</option>
                                                    <option value="غیرفعال">غیرفعال</option>
                                                    <option value="در حال تعمیر">در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="brand" class="form-label">برند</label>
                                                <input type="text" class="form-control" id="brand" name="brand">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="model" class="form-label">مدل</label>
                                                <input type="text" class="form-control" id="model" name="model">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="power_capacity" class="form-label">ظرفیت توان (کیلووات) *</label>
                                                <input type="text" class="form-control" id="power_capacity" name="power_capacity">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="engine_model" class="form-label">مدل موتور *</label>
                                                <input type="text" class="form-control" id="engine_model" name="engine_model">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="engine_serial" class="form-label">سریال موتور *</label>
                                                <input type="text" class="form-control" id="engine_serial" name="engine_serial">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- بقیه فیلدهای ژنراتور -->
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="alternator_model" class="form-label">مدل آلترناتور *</label>
                                                <input type="text" class="form-control" id="alternator_model" name="alternator_model">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="alternator_serial" class="form-label">سریال آلترناتور *</label>
                                                <input type="text" class="form-control" id="alternator_serial" name="alternator_serial">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="device_model" class="form-label">مدل دستگاه *</label>
                                                <input type="text" class="form-control" id="device_model" name="device_model">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- بقیه فیلدهای ژنراتور -->
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="device_serial" class="form-label">سریال دستگاه *</label>
                                                <input type="text" class="form-control" id="device_serial" name="device_serial">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="control_panel_model" class="form-label">مدل کنترل پنل</label>
                                                <input type="text" class="form-control" id="control_panel_model" name="control_panel_model">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="breaker_model" class="form-label">مدل بریکر</label>
                                                <input type="text" class="form-control" id="breaker_model" name="breaker_model">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- فیلدهای دیگر ژنراتور -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="fuel_tank_specs" class="form-label">مشخصات تانک سوخت</label>
                                                <textarea class="form-control" id="fuel_tank_specs" name="fuel_tank_specs" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="battery" class="form-label">باتری</label>
                                                <input type="text" class="form-control" id="battery" name="battery">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="battery_charger" class="form-label">باتری شارژر</label>
                                                <input type="text" class="form-control" id="battery_charger" name="battery_charger">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="heater" class="form-label">هیتر</label>
                                                <input type="text" class="form-control" id="heater" name="heater">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="oil_capacity" class="form-label">حجم روغن</label>
                                                <input type="text" class="form-control" id="oil_capacity" name="oil_capacity">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="radiator_capacity" class="form-label">حجم آب رادیاتور</label>
                                                <input type="text" class="form-control" id="radiator_capacity" name="radiator_capacity">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="antifreeze" class="form-label">ضدیخ</label>
                                                <input type="text" class="form-control" id="antifreeze" name="antifreeze">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="mt-4 mb-3 text-secondary">پارت نامبر فیلترها</h5>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="oil_filter_part" class="form-label">پارت نامبر فیلتر روغن</label>
                                                <input type="text" class="form-control" id="oil_filter_part" name="oil_filter_part">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="oil_filter" class="form-label">عکس فیلتر روغن</label>
                                                <input type="file" class="form-control" id="oil_filter" name="oil_filter" accept="image/*" onchange="previewImage(this, 'oil_filter_preview')">
                                                <img id="oil_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="fuel_filter_part" class="form-label">پارت نامبر فیلتر سوخت</label>
                                                <input type="text" class="form-control" id="fuel_filter_part" name="fuel_filter_part">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="fuel_filter" class="form-label">عکس فیلتر سوخت</label>
                                                <input type="file" class="form-control" id="fuel_filter" name="fuel_filter" accept="image/*" onchange="previewImage(this, 'fuel_filter_preview')">
                                                <img id="fuel_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="water_fuel_filter_part" class="form-label">پارت نامبر فیلتر سوخت آبی</label>
                                                <input type="text" class="form-control" id="water_fuel_filter_part" name="water_fuel_filter_part">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="water_fuel_filter" class="form-label">عکس فیلتر سوخت آبی</label>
                                                <input type="file" class="form-control" id="water_fuel_filter" name="water_fuel_filter" accept="image/*" onchange="previewImage(this, 'water_fuel_filter_preview')">
                                                <img id="water_fuel_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="air_filter_part" class="form-label">پارت نامبر فیلتر هوا</label>
                                                <input type="text" class="form-control" id="air_filter_part" name="air_filter_part">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="air_filter" class="form-label">عکس فیلتر هوا</label>
                                                <input type="file" class="form-control" id="air_filter" name="air_filter" accept="image/*" onchange="previewImage(this, 'air_filter_preview')">
                                                <img id="air_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="water_filter_part" class="form-label">پارت نامبر فیلتر آب</label>
                                                <input type="text" class="form-control" id="water_filter_part" name="water_filter_part">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="water_filter" class="form-label">عکس فیلتر آب</label>
                                                <input type="file" class="form-control" id="water_filter" name="water_filter" accept="image/*" onchange="previewImage(this, 'water_filter_preview')">
                                                <img id="water_filter_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="other_items" class="form-label">سایر اقلام مولد</label>
                                                <textarea class="form-control" id="other_items" name="other_items" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="device_image" class="form-label">عکس دستگاه</label>
                                                <input type="file" class="form-control" id="device_image" name="device_image" accept="image/*" onchange="previewImage(this, 'device_image_preview')">
                                                <img id="device_image_preview" class="image-preview" src="#" alt="پیش‌نمایش">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="workshop_entry_date" class="form-label">تاریخ ورود به کارگاه</label>
                                                <input type="date" class="form-control" id="workshop_entry_date" name="workshop_entry_date">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="workshop_exit_date" class="form-label">تاریخ خروج از کارگاه</label>
                                                <input type="date" class="form-control" id="workshop_exit_date" name="workshop_exit_date">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">توضیحات</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                                    </div>
                                </div>

                                <!-- فیلدهای مخصوص موتور برق -->
                                <div id="motor_fields" class="dynamic-field">
                                    <h5 class="mb-3 text-secondary">مشخصات موتور برق</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">نام دستگاه *</label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="serial_number" class="form-label">شماره سریال *</label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="purchase_date" class="form-label">تاریخ خرید</label>
                                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">وضعیت *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="فعال">فعال</option>
                                                    <option value="غیرفعال">غیرفعال</option>
                                                    <option value="در حال تعمیر">در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="motor_brand" class="form-label">نام موتور برق *</label>
                                                <select class="form-select" id="motor_brand" name="brand" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="Cummins">Cummins</option>
                                                    <option value="Volvo">Volvo</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="motor_type" class="form-label">نوع موتور *</label>
                                                <select class="form-select" id="motor_type" name="engine_type" required>
                                                    <option value="">-- انتخاب کنید --</option>
                                                    <option value="P4500">P4500</option>
                                                    <option value="P5000e">P5000e</option>
                                                    <option value="P2200">P2200</option>
                                                    <option value="P2600">P2600</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="motor_serial" class="form-label">شماره سریال موتور *</label>
                                                <input type="text" class="form-control" id="motor_serial" name="engine_serial" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلدهای مخصوص اقلام مصرفی -->
                                <div id="consumable_fields" class="dynamic-field">
                                    <h5 class="mb-3 text-secondary">مشخصات اقلام مصرفی</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">نام کالا *</label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="serial_number" class="form-label">شماره سریال</label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="purchase_date" class="form-label">تاریخ ثبت</label>
                                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">وضعیت *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="فعال">فعال</option>
                                                    <option value="غیرفعال">غیرفعال</option>
                                                    <option value="در حال تعمیر">در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="consumable_type" class="form-label">نوع کالای مصرفی *</label>
                                                <input type="text" class="form-control" id="consumable_type" name="consumable_type" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="consumable_part" class="form-label">پارت نامبر</label>
                                                <input type="text" class="form-control" id="consumable_part" name="oil_filter_part">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- فیلدهای مخصوص قطعات -->
                                <div id="parts_fields" class="dynamic-field">
                                    <h5 class="mb-3 text-secondary">مشخصات قطعات</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">نام قطعه *</label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="serial_number" class="form-label">شماره سریال</label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="purchase_date" class="form-label">تاریخ ثبت</label>
                                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">وضعیت *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="فعال">فعال</option>
                                                    <option value="غیرفعال">غیرفعال</option>
                                                    <option value="در حال تعمیر">در حال تعمیر</option>
                                                    <option value="آماده بهره‌برداری">آماده بهره‌برداری</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="part_description" class="form-label">شرح کالا *</label>
                                                <input type="text" class="form-control" id="part_description" name="part_description" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="part_serial" class="form-label">شماره سریال</label>
                                                <input type="text" class="form-control" id="part_serial" name="part_serial">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="part_register_date" class="form-label">تاریخ ثبت</label>
                                                <input type="date" class="form-control" id="part_register_date" name="part_register_date">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="part_notes" class="form-label">توضیحات</label>
                                                <textarea class="form-control" id="part_notes" name="part_notes" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="prevStep(1)"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                    <button type="button" class="btn btn-primary" onclick="nextStep(3)">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                                </div>
                            </div>
                            
                            <!-- مرحله 3: نحوه تامین (فقط برای اقلام مصرفی و قطعات) -->
                            <div class="step" id="step3">
                                <h4 class="mb-4 text-primary">نحوه تامین</h4>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="supply_method" class="form-label">نحوه تامین *</label>
                                            <select class="form-select" id="supply_method" name="supply_method" required onchange="toggleSupplyFields()">
                                                <option value="">-- انتخاب کنید --</option>
                                                <option value="انبار">انبار</option>
                                                <option value="third_party">Third Party</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- فیلدهای مخصوص انبار -->
                                <div id="warehouse_fields" class="supply-method-fields">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="location" class="form-label">لوکیشن *</label>
                                                <input type="text" class="form-control" id="location" name="location">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">تعداد *</label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" min="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- فیلدهای مخصوص Third Party -->
                                <div id="third_party_fields" class="supply-method-fields">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="supplier_name" class="form-label">نام تامین کننده *</label>
                                                <input type="text" class="form-control" id="supplier_name" name="supplier_name">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="supplier_contact" class="form-label">شماره تماس تامین کننده *</label>
                                                <input type="text" class="form-control" id="supplier_contact" name="supplier_contact">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                    <button type="button" class="btn btn-primary" onclick="nextStep(4)">مرحله بعد <i class="fas fa-arrow-left"></i></button>
                                </div>
                            </div>
                            
                            <!-- مرحله 4: پیش‌نمایش و تأیید -->
                            <div class="step" id="step4">
                                <h4 class="mb-4 text-primary">پیش‌نمایش و تأیید اطلاعات</h4>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> لطفاً اطلاعات زیر را بررسی کرده و در صورت صحیح بودن، ثبت نهایی را انجام دهید.
                                </div>
                                
                                <div class="preview-container" id="previewContainer">
                                    <!-- اطلاعات پیش‌نمایش اینجا نمایش داده می‌شود -->
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary" onclick="prevStep(3)"><i class="fas fa-arrow-right"></i> مرحله قبل</button>
                                    <div>
                                        <button type="button" class="btn btn-warning" onclick="editForm()"><i class="fas fa-edit"></i> ویرایش اطلاعات</button>
                                        <button type="submit" name="add_asset" class="btn btn-success">
                                            <i class="fas fa-save"></i> ثبت نهایی
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- لیست دارایی‌ها (فقط پس از جستجو نمایش داده شود) -->
                <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> نتایج جستجو</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($assets) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>نام دستگاه</th>
                                            <th>نوع</th>
                                            <th>سریال</th>
                                            <th>برند/مدل</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ خرید</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assets as $asset): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($asset['name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($asset['type_display_name']) ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($asset['serial_number']) ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($asset['brand']) ?>
                                                <?php echo $asset['model'] ? ' / ' . htmlspecialchars($asset['model']) : '' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($asset['status'] == 'فعال') $status_class = 'success';
                                                if ($asset['status'] == 'غیرفعال') $status_class = 'danger';
                                                if ($asset['status'] == 'در حال تعمیر') $status_class = 'warning';
                                                if ($asset['status'] == 'آماده بهره‌برداری') $status_class = 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class ?> badge-status"><?php echo $asset['status'] ?></span>
                                            </td>
                                            <td><?php echo $asset['purchase_date'] ? jalaliDate($asset['purchase_date']) : '--' ?></td>
                                            <td class="action-buttons">
                                                <a href="profile.php?id=<?php echo $asset['id'] ?>" class="btn btn-sm btn-info" title="پروفایل دستگاه">
                                                    <i class="fas fa-id-card"></i>
                                                </a>
                                                <a href="edit_asset.php?id=<?php echo $asset['id'] ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($_SESSION['role'] == 'ادمین'): ?>
                                                <a href="assets.php?delete_id=<?php echo $asset['id'] ?>" class="btn btn-sm btn-danger" title="حذف"
                                                   onclick="return confirm('آیا از حذف این دارایی مطمئن هستید؟')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> هیچ دارایی یافت نشد.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // متغیرهای全局 برای ذخیره وضعیت فعلی
    let currentStep = 1;
    let assetType = '';
    
    // نمایش فرم افزودن دارایی
    function showAddAssetForm() {
        document.getElementById('add-asset-form').style.display = 'block';
        document.getElementById('add-asset-form').scrollIntoView({ behavior: 'smooth' });
        resetForm();
    }
    
    // مخفی کردن فرم افزودن دارایی
    function hideAddAssetForm() {
        document.getElementById('add-asset-form').style.display = 'none';
        resetForm();
    }
    
    // بازنشانی فرم
    function resetForm() {
        currentStep = 1;
        document.querySelectorAll('.step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('step1').classList.add('active');
        document.getElementById('assetForm').reset();
        hideAllDynamicFields();
        hideAllSupplyFields();
    }
    
    // مخفی کردن همه فیلدهای پویا
    function hideAllDynamicFields() {
        document.querySelectorAll('.dynamic-field').forEach(field => {
            field.style.display = 'none';
        });
    }
    
    // مخفی کردن همه فیلدهای نحوه تامین
    function hideAllSupplyFields() {
        document.querySelectorAll('.supply-method-fields').forEach(field => {
            field.style.display = 'none';
        });
    }
    
    // نمایش مرحله بعد
    function nextStep(step) {
        if (!validateStep(currentStep)) {
            return;
        }

        // اگر نوع، ژنراتور یا موتور برق است، مرحله \"نحوه تامین\" را رد کن
        let target = step;
        const isConsumableOrParts = (assetType.includes('مصرفی') || assetType.includes('قطعات'));
        if (currentStep === 2 && !isConsumableOrParts && step === 3) {
            target = 4;
        }

        document.getElementById('step' + currentStep).classList.remove('active');
        document.getElementById('step' + target).classList.add('active');
        currentStep = target;

        if (currentStep === 4) {
            generatePreview();
        }
    }
    
    // بازگشت به مرحله قبل
    function prevStep(step) {
        document.getElementById('step' + currentStep).classList.remove('active');
        document.getElementById('step' + step).classList.add('active');
        currentStep = step;
    }
    
    // اعتبارسنجی مرحله فعلی
    function validateStep(step) {
        let isValid = true;
        let errorMessage = '';
        
        if (step === 1) {
            const typeSelect = document.getElementById('type_id');
            if (!typeSelect.value) {
                isValid = false;
                errorMessage = 'لطفاً نوع دارایی را انتخاب کنید.';
            } else {
                assetType = typeSelect.options[typeSelect.selectedIndex].text.toLowerCase();
            }
        } else if (step === 2) {
            // اعتبارسنجی بر اساس نوع دارایی
            if (assetType.includes('ژنراتور')) {
                const name = document.getElementById('name');
                const serial = document.getElementById('serial_number');
                const status = document.getElementById('status');
                const powerCapacity = document.getElementById('power_capacity');
                const engineModel = document.getElementById('engine_model');
                const engineSerial = document.getElementById('engine_serial');
                
                if (!name.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً نام دستگاه را وارد کنید.';
                } else if (!serial.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً شماره سریال را وارد کنید.';
                } else if (!status.value) {
                    isValid = false;
                    errorMessage = 'لطفاً وضعیت را انتخاب کنید.';
                } else if (!powerCapacity.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً ظرفیت توان را وارد کنید.';
                } else if (!engineModel.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً مدل موتور را وارد کنید.';
                } else if (!engineSerial.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً سریال موتور را وارد کنید.';
                }
            } else if (assetType.includes('موتور برق')) {
                const name = document.getElementById('name');
                const serial = document.getElementById('serial_number');
                const status = document.getElementById('status');
                const motorBrand = document.getElementById('motor_brand');
                const motorType = document.getElementById('motor_type');
                const motorSerial = document.getElementById('motor_serial');
                
                if (!name.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً نام دستگاه را وارد کنید.';
                } else if (!serial.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً شماره سریال را وارد کنید.';
                } else if (!status.value) {
                    isValid = false;
                    errorMessage = 'لطفاً وضعیت را انتخاب کنید.';
                } else if (!motorBrand.value) {
                    isValid = false;
                    errorMessage = 'لطفاً نام موتور برق را انتخاب کنید.';
                } else if (!motorType.value) {
                    isValid = false;
                    errorMessage = 'لطفاً نوع موتور را انتخاب کنید.';
                } else if (!motorSerial.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً شماره سریال موتور را وارد کنید.';
                }
            } else if (assetType.includes('مصرفی')) {
                const name = document.getElementById('name');
                const status = document.getElementById('status');
                const consumableType = document.getElementById('consumable_type');
                
                if (!name.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً نام کالا را وارد کنید.';
                } else if (!status.value) {
                    isValid = false;
                    errorMessage = 'لطفاً وضعیت را انتخاب کنید.';
                } else if (!consumableType.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً نوع کالای مصرفی را وارد کنید.';
                }
            } else if (assetType.includes('قطعات')) {
                const name = document.getElementById('name');
                const status = document.getElementById('status');
                const partDescription = document.getElementById('part_description');
                
                if (!name.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً نام قطعه را وارد کنید.';
                } else if (!status.value) {
                    isValid = false;
                    errorMessage = 'لطفاً وضعیت را انتخاب کنید.';
                } else if (!partDescription.value.trim()) {
                    isValid = false;
                    errorMessage = 'لطفاً شرح کالا را وارد کنید.';
                }
            }
        } else if (step === 3) {
            // فقط برای اقلام مصرفی و قطعات
            if (assetType.includes('مصرفی') || assetType.includes('قطعات')) {
                const supplyMethod = document.getElementById('supply_method');
                
                if (!supplyMethod.value) {
                    isValid = false;
                    errorMessage = 'لطفاً نحوه تامین را انتخاب کنید.';
                } else if (supplyMethod.value === 'انبار') {
                    const location = document.getElementById('location');
                    const quantity = document.getElementById('quantity');
                    
                    if (!location.value.trim()) {
                        isValid = false;
                        errorMessage = 'لطفاً لوکیشن را وارد کنید.';
                    } else if (!quantity.value || quantity.value <= 0) {
                        isValid = false;
                        errorMessage = 'لطفاً تعداد را وارد کنید.';
                    }
                } else if (supplyMethod.value === 'third_party') {
                    const supplierName = document.getElementById('supplier_name');
                    const supplierContact = document.getElementById('supplier_contact');
                    
                    if (!supplierName.value.trim()) {
                        isValid = false;
                        errorMessage = 'لطفاً نام تامین کننده را وارد کنید.';
                    } else if (!supplierContact.value.trim()) {
                        isValid = false;
                        errorMessage = 'لطفاً شماره تماس تامین کننده را وارد کنید.';
                    }
                }
            }
        }
        
        if (!isValid) {
            alert(errorMessage);
        }
        
        return isValid;
    }
    
    // نمایش فیلدهای مناسب بر اساس نوع دارایی
    function showStep2() {
        const typeSelect = document.getElementById('type_id');
        const selectedOption = typeSelect.options[typeSelect.selectedIndex];
        const typeName = selectedOption.text.toLowerCase();
        assetType = typeName;
        
        // به روز رسانی عنوان مرحله 2
        document.getElementById('step2-title').textContent = `اطلاعات ${selectedOption.text}`;
        
        // مخفی کردن همه فیلدهای پویا
        hideAllDynamicFields();
        
        // نمایش فیلدهای مربوطه در مرحله 2
        if (typeName.includes('ژنراتور')) {
            document.getElementById('generator_fields').style.display = 'block';
        } else if (typeName.includes('موتور برق')) {
            document.getElementById('motor_fields').style.display = 'block';
        } else if (typeName.includes('مصرفی')) {
            document.getElementById('consumable_fields').style.display = 'block';
        } else if (typeName.includes('قطعات')) {
            document.getElementById('parts_fields').style.display = 'block';
        }
    }
    
    // نمایش/مخفی کردن فیلدهای نحوه تامین
    function toggleSupplyFields() {
        const supplyMethod = document.getElementById('supply_method').value;
        
        // مخفی کردن همه فیلدهای نحوه تامین
        hideAllSupplyFields();
        
        // نمایش فیلدهای مربوطه
        if (supplyMethod === 'انبار') {
            document.getElementById('warehouse_fields').style.display = 'block';
        } else if (supplyMethod === 'third_party') {
            document.getElementById('third_party_fields').style.display = 'block';
        }
    }
    
    // تولید پیش‌نمایش اطلاعات
    function generatePreview() {
        const previewContainer = document.getElementById('previewContainer');
        let previewHTML = '';
        
        // اطلاعات عمومی
        previewHTML += `
            <div class="preview-section mb-4">
                <h5 class="text-primary">اطلاعات عمومی</h5>
                <div class="row">
                    <div class="col-md-6 preview-item">
                        <span class="preview-label">نام:</span>
                        <span>${document.getElementById('name').value}</span>
                    </div>
                    <div class="col-md-6 preview-item">
                        <span class="preview-label">شماره سریال:</span>
                        <span>${document.getElementById('serial_number').value || '--'}</span>
                    </div>
                    <div class="col-md-6 preview-item">
                        <span class="preview-label">تاریخ خرید/ثبت:</span>
                        <span>${document.getElementById('purchase_date').value || '--'}</span>
                    </div>
                    <div class="col-md-6 preview-item">
                        <span class="preview-label">وضعیت:</span>
                        <span>${document.getElementById('status').value}</span>
                    </div>
                </div>
            </div>
        `;
        
        // اطلاعات خاص بر اساس نوع دارایی
        if (assetType.includes('ژنراتور')) {
            previewHTML += `
                <div class="preview-section mb-4">
                    <h5 class="text-primary">مشخصات ژنراتور</h5>
                    <div class="row">
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">برند:</span>
                            <span>${document.getElementById('brand').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">مدل:</span>
                            <span>${document.getElementById('model').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">ظرفیت توان:</span>
                            <span>${document.getElementById('power_capacity').value || '--'} کیلووات</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">مدل موتور:</span>
                            <span>${document.getElementById('engine_model').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">سریال موتور:</span>
                            <span>${document.getElementById('engine_serial').value || '--'}</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (assetType.includes('موتور برق')) {
            previewHTML += `
                <div class="preview-section mb-4">
                    <h5 class="text-primary">مشخصات موتور برق</h5>
                    <div class="row">
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">نام موتور برق:</span>
                            <span>${document.getElementById('motor_brand').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">نوع موتور:</span>
                            <span>${document.getElementById('motor_type').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">شماره سریال موتور:</span>
                            <span>${document.getElementById('motor_serial').value || '--'}</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (assetType.includes('مصرفی')) {
            previewHTML += `
                <div class="preview-section mb-4">
                    <h5 class="text-primary">مشخصات اقلام مصرفی</h5>
                    <div class="row">
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">نوع کالای مصرفی:</span>
                            <span>${document.getElementById('consumable_type').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">پارت نامبر:</span>
                            <span>${document.getElementById('consumable_part').value || '--'}</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (assetType.includes('قطعات')) {
            previewHTML += `
                <div class="preview-section mb-4">
                    <h5 class="text-primary">مشخصات قطعات</h5>
                    <div class="row">
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">شرح کالا:</span>
                            <span>${document.getElementById('part_description').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">شماره سریال:</span>
                            <span>${document.getElementById('part_serial').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">تاریخ ثبت:</span>
                            <span>${document.getElementById('part_register_date').value || '--'}</span>
                        </div>
                        <div class="col-md-12 preview-item">
                            <span class="preview-label">توضیحات:</span>
                            <span>${document.getElementById('part_notes').value || '--'}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // اطلاعات نحوه تامین (فقط برای اقلام مصرفی و قطعات)
        if (assetType.includes('مصرفی') || assetType.includes('قطعات')) {
            const supplyMethod = document.getElementById('supply_method').value;
            
            previewHTML += `
                <div class="preview-section mb-4">
                    <h5 class="text-primary">نحوه تامین</h5>
                    <div class="row">
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">نحوه تامین:</span>
                            <span>${supplyMethod === 'انبار' ? 'انبار' : 'Third Party'}</span>
                        </div>
            `;
            
            if (supplyMethod === 'انبار') {
                previewHTML += `
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">لوکیشن:</span>
                            <span>${document.getElementById('location').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">تعداد:</span>
                            <span>${document.getElementById('quantity').value || '--'}</span>
                        </div>
                `;
            } else if (supplyMethod === 'third_party') {
                previewHTML += `
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">نام تامین کننده:</span>
                            <span>${document.getElementById('supplier_name').value || '--'}</span>
                        </div>
                        <div class="col-md-6 preview-item">
                            <span class="preview-label">شماره تماس تامین کننده:</span>
                            <span>${document.getElementById('supplier_contact').value || '--'}</span>
                        </div>
                `;
            }
            
            previewHTML += `
                    </div>
                </div>
            `;
        }
        
        previewContainer.innerHTML = previewHTML;
    }
    
    // ویرایش اطلاعات
    function editForm() {
        if (assetType.includes('مصرفی') || assetType.includes('قطعات')) {
            document.getElementById('step4').classList.remove('active');
            document.getElementById('step3').classList.add('active');
            currentStep = 3;
        } else {
            document.getElementById('step4').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            currentStep = 2;
        }
    }
    
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    }

    // اجرای اولیه
    document.addEventListener('DOMContentLoaded', function() {
        hideAllDynamicFields();
        hideAllSupplyFields();
        
        // اگر خطایی در فرم وجود دارد، فرم را نمایش بده
        <?php if (isset($_SESSION['error']) && strpos($_SESSION['error'], 'دارایی') !== false): ?>
            showAddAssetForm();
        <?php endif; ?>
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>