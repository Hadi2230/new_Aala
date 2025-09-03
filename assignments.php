<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// تابع برای تولید شماره سریال کارت گارانتی
function generateWarrantySerial() {
    return 'WN' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// متغیرهای خطا و موفقیت
$success = $error = '';

try {
    $pdo->beginTransaction();

    // ثبت انتساب دستگاه
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_asset'])) {
        $asset_id = $_POST['asset_id'];
        $customer_id = $_POST['customer_id'];
        $assignment_date = $_POST['assignment_date'];
        $notes = $_POST['notes'];

        // درج انتساب اصلی
        $stmt = $pdo->prepare("INSERT INTO asset_assignments (asset_id, customer_id, assignment_date, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$asset_id, $customer_id, $assignment_date, $notes]);
        $assignment_id = $pdo->lastInsertId();

        // اطلاعات مشتری
        $stmt = $pdo->prepare("SELECT full_name, phone FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();

        // اطلاعات دستگاه
        $stmt = $pdo->prepare("SELECT name, model, serial_number FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();

        // دریافت داده‌های فرم
        $fields = [
            'installation_date', 'delivery_person', 'installation_address',
            'warranty_start_date', 'warranty_end_date', 'warranty_conditions', 'recipient_name',
            'recipient_phone', 'installer_name', 'installation_start_date',
            'installation_end_date', 'temporary_delivery_date', 'permanent_delivery_date',
            'first_service_date', 'post_installation_commitments', 'additional_notes'
        ];
        
        foreach ($fields as $field) {
            $$field = $_POST[$field] ?? null;
        }

        $employer_name = $customer['full_name'];
        $employer_phone = $customer['phone'];
        
        // تولید شماره سریال کارت گارانتی
        $warranty_serial = generateWarrantySerial();

        // آپلود عکس نصب
        $installation_photo = '';
        $upload_dir = 'uploads/installations/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        if (!empty($_FILES['installation_photo']['name'])) {
            $file_ext = pathinfo($_FILES['installation_photo']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . uniqid() . '_installation.' . $file_ext;
            $target_file = $upload_dir . $file_name;

            if (in_array(strtolower($file_ext), ['jpg','jpeg','png','gif']) &&
                move_uploaded_file($_FILES['installation_photo']['tmp_name'], $target_file)) {
                $installation_photo = $target_file;
            }
        }

        // درج جزئیات انتساب
        $stmt = $pdo->prepare("
            INSERT INTO assignment_details
            (assignment_id, installation_date, delivery_person, installation_address,
             warranty_start_date, warranty_end_date, warranty_serial, warranty_conditions, employer_name, employer_phone,
             recipient_name, recipient_phone, installer_name, installation_start_date,
             installation_end_date, temporary_delivery_date, permanent_delivery_date,
             first_service_date, post_installation_commitments, notes, installation_photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $assignment_id, $installation_date, $delivery_person, $installation_address,
            $warranty_start_date, $warranty_end_date, $warranty_serial, $warranty_conditions, $employer_name, $employer_phone,
            $recipient_name, $recipient_phone, $installer_name, $installation_start_date,
            $installation_end_date, $temporary_delivery_date, $permanent_delivery_date,
            $first_service_date, $post_installation_commitments, $notes, $installation_photo
        ]);

        $pdo->commit();
        $success = "دستگاه با موفقیت به مشتری انتساب شد و اطلاعات نصب ثبت گردید!";
        $_SESSION['last_assignment_id'] = $assignment_id;
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $error = "خطا در انتساب دستگاه: " . $e->getMessage();
}

// دریافت تمام اطلاعات انتساب با join
try {
    $stmt = $pdo->query("
        SELECT aa.*, a.name AS asset_name, a.model AS asset_model, a.serial_number AS asset_serial,
               c.full_name AS customer_name, c.phone AS customer_phone,
               ad.*
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.id
        JOIN customers c ON aa.customer_id = c.id
        LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
        ORDER BY aa.created_at DESC
    ");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت انتساب‌ها: " . $e->getMessage();
    $assignments = [];
}

// دریافت اطلاعات آخرین انتساب برای چاپ کارت گارانتی
$last_assignment = null;
if (isset($_SESSION['last_assignment_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT aa.*, a.name AS asset_name, a.model AS asset_model, a.serial_number AS asset_serial,
                   c.full_name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
                   ad.*
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.id
            JOIN customers c ON aa.customer_id = c.id
            LEFT JOIN assignment_details ad ON aa.id = ad.assignment_id
            WHERE aa.id = ?
        ");
        $stmt->execute([$_SESSION['last_assignment_id']]);
        $last_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "خطا در دریافت اطلاعات انتساب: " . $e->getMessage();
    }
}

// دریافت لیست دستگاه‌ها (بدون محدودکردن به وضعیت 'فعال' برای سازگاری با داده‌های قدیمی)
try {
    $assets_stmt = $pdo->query("SELECT id, name, model, serial_number FROM assets 
                                 WHERE status = 'فعال' OR status IS NULL OR status IN ('Active','active','ACTIVE')
                                 ORDER BY name");
    $assets = $assets_stmt->fetchAll(PDO::FETCH_ASSOC);
    // اگر چیزی برنگشت، یک تلاش دوم بدون فیلتر وضعیت انجام بده
    if (!$assets) {
        $assets_stmt = $pdo->query("SELECT id, name, model, serial_number FROM assets ORDER BY name");
        $assets = $assets_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "خطا در دریافت دستگاه‌ها: " . $e->getMessage();
    $assets = [];
}

// دریافت لیست مشتریان از دیتابیس
try {
    $customers_stmt = $pdo->query("SELECT id, full_name, phone FROM customers ORDER BY full_name");
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت مشتریان: " . $e->getMessage();
    $customers = [];
}

// دیباگ: نمایش اطلاعات دستگاه‌ها برای اطمینان از دریافت صحیح
error_log("تعداد دستگاه‌های دریافت شده: " . count($assets));
foreach ($assets as $asset) {
    error_log("دستگاه: " . $asset['name'] . " - " . $asset['serial_number']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>انتساب دستگاه به مشتری - اعلا نیرو</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.assignment-details { display: none; }
.image-preview { max-width: 200px; max-height: 200px; margin-top: 10px; display: none; }

/* استایل کارت گارانتی */
.warranty-card {
    border: 2px solid #007bff;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.warranty-header {
    text-align: center;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.warranty-logo {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
}
.warranty-body {
    margin-bottom: 20px;
}
.warranty-field {
    margin-bottom: 10px;
    display: flex;
}
.warranty-label {
    font-weight: bold;
    min-width: 120px;
}
.warranty-footer {
    text-align: center;
    border-top: 1px dashed #ccc;
    padding-top: 10px;
    font-size: 12px;
    color: #6c757d;
}
@media print {
    body * {
        visibility: hidden;
    }
    .warranty-card, .warranty-card * {
        visibility: visible;
    }
    .warranty-card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
        box-shadow: none;
    }
    .no-print {
        display: none !important;
    }
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-5">
<h2 class="text-center">انتساب دستگاه به مشتری</h2>

<?php if(!empty($success)): ?>
    <div class='alert alert-success'><?php echo $success; ?>
        <?php if(isset($last_assignment) && !empty($last_assignment['warranty_serial'])): ?>
        <div class="mt-2">
            <a href="javascript:void(0);" onclick="printWarranty()" class="btn btn-success btn-sm">
                <i class="fas fa-print"></i> چاپ کارت گارانتی
            </a>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if(!empty($error)): ?>
    <div class='alert alert-danger'><?php echo $error; ?></div>
<?php endif; ?>

<!-- نمایش کارت گارانتی برای چاپ -->
<?php if(isset($last_assignment) && !empty($last_assignment['warranty_serial'])): ?>
<div class="warranty-card no-print" id="warrantyCard">
    <div class="warranty-header">
        <div class="warranty-logo">اعلا نیرو</div>
        <h3>کارت گارانتی دستگاه</h3>
    </div>
    <div class="warranty-body">
        <div class="warranty-field">
            <span class="warranty-label">شماره کارت:</span>
            <span><?php echo $last_assignment['warranty_serial']; ?></span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">مشتری:</span>
            <span><?php echo $last_assignment['customer_name']; ?></span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">دستگاه:</span>
            <span><?php echo $last_assignment['asset_name']; ?> (<?php echo $last_assignment['asset_model']; ?>)</span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">سریال دستگاه:</span>
            <span><?php echo $last_assignment['asset_serial']; ?></span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">تاریخ نصب:</span>
            <span><?php echo $last_assignment['installation_date']; ?></span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">شروع گارانتی:</span>
            <span><?php echo $last_assignment['warranty_start_date']; ?></span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">پایان گارانتی:</span>
            <span><?php echo $last_assignment['warranty_end_date']; ?></span>
        </div>
        <div class="warranty-field">
            <span class="warranty-label">شرایط گارانتی:</span>
            <span><?php echo $last_assignment['warranty_conditions']; ?></span>
        </div>
    </div>
    <div class="warranty-footer">
        <p>این سند به منزله تأیید گارانتی دستگاه فوق می‌باشد | شماره تماس: ۰۲۱-۱۲۳۴۵۶۷۸</p>
    </div>
</div>
<?php endif; ?>

<div class="card mt-4">
<div class="card-header"><i class="fas fa-link"></i> انتساب جدید</div>
<div class="card-body">
<form method="POST" id="assignmentForm" enctype="multipart/form-data">
<div class="row">
<div class="col-md-6">
<div class="mb-3">
<label for="customer_id" class="form-label">انتخاب مشتری *</label>
<select class="form-select" id="customer_id" name="customer_id" required>
    <option value="">-- انتخاب مشتری --</option>
    <?php foreach ($customers as $customer): ?>
        <option value="<?php echo $customer['id']; ?>" data-phone="<?php echo htmlspecialchars($customer['phone']); ?>">
            <?php echo htmlspecialchars($customer['phone'] ?? ''); ?>
        </option>
    <?php endforeach; ?>
</select>
</div>
</div>
<div class="col-md-6">
<div class="mb-3">
<label for="asset_id" class="form-label">انتخاب دستگاه *</label>
<select class="form-select" id="asset_id" name="asset_id" required>
    <option value="">-- انتخاب دستگاه --</option>
    <?php foreach ($assets as $asset): ?>
        <option value="<?php echo $asset['id']; ?>" 
                data-model="<?php echo htmlspecialchars($asset['model']); ?>" 
                data-serial="<?php echo htmlspecialchars($asset['serial_number']); ?>">
            <?php echo htmlspecialchars($asset['name']); ?> (<?php echo htmlspecialchars($asset['serial_number']); ?>)
        </option>
    <?php endforeach; ?>
</select>
</div>
</div>
</div>

<div class="row">
<div class="col-md-4">
<div class="mb-3">
<label for="assignment_date" class="form-label">تاریخ انتساب *</label>
<input type="date" class="form-control" id="assignment_date" name="assignment_date" required value="<?php echo date('Y-m-d'); ?>">
</div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label class="form-label">مدل دستگاه</label>
        <input type="text" class="form-control" id="device_model_display" readonly>
    </div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label class="form-label">سریال دستگاه</label>
        <input type="text" class="form-control" id="device_serial_display" readonly>
    </div>
</div>
</div>

<div class="mb-3">
<label for="notes" class="form-label">توضیحات اولیه (اختیاری)</label>
<textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
</div>

<!-- اطلاعات کامل انتساب -->
<div id="assignmentDetails" class="assignment-details">
<h4 class="mb-4 mt-4">اطلاعات کامل نصب و راه‌اندازی</h4>

<div class="row">
<div class="col-md-4">
    <div class="mb-3">
        <label for="installation_date">تاریخ نصب</label>
        <input type="date" class="form-control" id="installation_date" name="installation_date">
    </div>
</div>

<div class="col-md-4">
    <div class="mb-3">
        <label for="delivery_person">نام تحویل دهنده</label>
        <input type="text" class="form-control" id="delivery_person" name="delivery_person">
    </div>
</div>

<div class="col-md-4">
    <div class="mb-3">
        <label for="recipient_name">نام تحویل گیرنده</label>
        <input type="text" class="form-control" id="recipient_name" name="recipient_name">
    </div>
</div>
</div>

<div class="row">
<div class="col-md-6">
    <div class="mb-3">
        <label for="recipient_phone">شماره تماس تحویل گیرنده</label>
        <input type="text" class="form-control" id="recipient_phone" name="recipient_phone">
    </div>
</div>

<div class="col-md-6">
    <div class="mb-3">
        <label for="installer_name">نام نصاب</label>
        <input type="text" class="form-control" id="installer_name" name="installer_name">
    </div>
</div>
</div>

<div class="mb-3">
    <label for="installation_address">آدرس محل نصب</label>
    <textarea class="form-control" id="installation_address" name="installation_address" rows="3"></textarea>
</div>

<div class="row">
<div class="col-md-4">
    <div class="mb-3">
        <label for="warranty_start_date">تاریخ آغاز گارانتی</label>
        <input type="date" class="form-control" id="warranty_start_date" name="warranty_start_date">
    </div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label for="warranty_end_date">تاریخ پایان گارانتی</label>
        <input type="date" class="form-control" id="warranty_end_date" name="warranty_end_date">
    </div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label for="installation_start_date">تاریخ آغاز نصب</label>
        <input type="date" class="form-control" id="installation_start_date" name="installation_start_date">
    </div>
</div>
</div>

<div class="row">
<div class="col-md-4">
    <div class="mb-3">
        <label for="installation_end_date">تاریخ اتمام نصب</label>
        <input type="date" class="form-control" id="installation_end_date" name="installation_end_date">
    </div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label for="temporary_delivery_date">تاریخ تحویل موقت</label>
        <input type="date" class="form-control" id="temporary_delivery_date" name="temporary_delivery_date">
    </div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label for="permanent_delivery_date">تاریخ تحویل دائم</label>
        <input type="date" class="form-control" id="permanent_delivery_date" name="permanent_delivery_date">
    </div>
</div>
</div>

<div class="row">
<div class="col-md-4">
    <div class="mb-3">
        <label for="first_service_date">تاریخ سرویس اولیه</label>
        <input type="date" class="form-control" id="first_service_date" name="first_service_date">
    </div>
</div>
</div>

<div class="mb-3">
    <label for="warranty_conditions">شرایط گارانتی</label>
    <textarea class="form-control" id="warranty_conditions" name="warranty_conditions" rows="3" placeholder="شرایط و ضوابط گارانتی دستگاه"></textarea>
</div>

<div class="mb-3">
    <label for="post_installation_commitments">تعهدات پس از راه‌اندازی</label>
    <textarea class="form-control" id="post_installation_commitments" name="post_installation_commitments" rows="3"></textarea>
</div>

<div class="mb-3">
    <label for="additional_notes">توضیحات تکمیلی</label>
    <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3"></textarea>
</div>

<div class="mb-3">
    <label for="installation_photo">عکس نصب نهایی دستگاه</label>
    <input type="file" class="form-control" id="installation_photo" name="installation_photo" accept="image/*" onchange="previewImage(this,'installation_photo_preview')">
    <img id="installation_photo_preview" class="image-preview" src="#" alt="پیش‌نمایش عکس نصب">
</div>

<div class="row">
<div class="col-md-6">
    <div class="mb-3">
        <label for="employer_name">نام کارفرما (از اطلاعات مشتری)</label>
        <input type="text" class="form-control" id="employer_name" name="employer_name" readonly>
    </div>
</div>
<div class="col-md-6">
    <div class="mb-3">
        <label for="employer_phone">شماره تماس کارفرما (از اطلاعات مشتری)</label>
        <input type="text" class="form-control" id="employer_phone" name="employer_phone" readonly>
    </div>
</div>
</div>

</div>

<button type="submit" name="assign_asset" class="btn btn-primary">انتساب دستگاه و ثبت اطلاعات</button>
</form>
</div>
</div>

<!-- جدول لیست انتساب‌ها -->
<div class="card mt-5">
<div class="card-header">لیست انتساب‌های انجام شده</div>
<div class="card-body">
<table class="table table-striped">
<thead>
<tr>
    <th>#</th>
    <th>دستگاه</th>
    <th>مشتری</th>
    <th>تاریخ انتساب</th>
    <th>تحویل گیرنده</th>
    <th>آدرس نصب</th>
    <th>عملیات</th>
</tr>
</thead>
<tbody>
<?php foreach ($assignments as $assignment): ?>
<tr>
<td><?php echo $assignment['id']; ?></td>
<td><?php echo $assignment['asset_name']; ?></td>
<td><?php echo $assignment['customer_name']; ?></td>
<td><?php echo $assignment['assignment_date']; ?></td>
<td><?php echo $assignment['recipient_name'] ?? '-'; ?></td>
<td>
    <?php 
    if (!empty($assignment['installation_address'])) {
        $address = $assignment['installation_address'];
        echo strlen($address) > 30 ? substr($address, 0, 30) . '...' : $address;
    } else {
        echo '-';
    }
    ?>
</td>
<td>
<div class="btn-group">
<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $assignment['id']; ?>">جزئیات</button>
<a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
<?php if(!empty($assignment['warranty_serial'])): ?>
<a href="javascript:void(0);" onclick="printWarranty(<?php echo $assignment['id']; ?>)" class="btn btn-sm btn-success">چاپ گارانتی</a>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- Modal جزئیات -->
<?php foreach ($assignments as $assignment): ?>
<div class="modal fade" id="detailsModal<?php echo $assignment['id']; ?>" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">جزئیات انتساب #<?php echo $assignment['id']; ?></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row">
<div class="col-md-6">
<p><strong>دستگاه:</strong> <?php echo $assignment['asset_name']; ?></p>
<p><strong>مشتری:</strong> <?php echo $assignment['customer_name']; ?></p>
<p><strong>تاریخ انتساب:</strong> <?php echo $assignment['assignment_date']; ?></p>
<p><strong>نام تحویل دهنده:</strong> <?php echo $assignment['delivery_person'] ?? '-'; ?></p>
<p><strong>نام تحویل گیرنده:</strong> <?php echo $assignment['recipient_name'] ?? '-'; ?></p>
<p><strong>تلفن تحویل گیرنده:</strong> <?php echo $assignment['recipient_phone'] ?? '-'; ?></p>
</div>
<div class="col-md-6">
<p><strong>تاریخ نصب:</strong> <?php echo $assignment['installation_date'] ?? '-'; ?></p>
<p><strong>تاریخ آغاز گارانتی:</strong> <?php echo $assignment['warranty_start_date'] ?? '-'; ?></p>
<p><strong>تاریخ پایان گارانتی:</strong> <?php echo $assignment['warranty_end_date'] ?? '-'; ?></p>
<p><strong>شماره کارت گارانتی:</strong> <?php echo $assignment['warranty_serial'] ?? '-'; ?></p>
<p><strong>نام نصاب:</strong> <?php echo $assignment['installer_name'] ?? '-'; ?></p>
</div>
</div>
<div class="row mt-3"><div class="col-md-12">
<p><strong>آدرس نصب:</strong> <?php echo $assignment['installation_address'] ?? '-'; ?></p>
<p><strong>شرایط گارانتی:</strong> <?php echo $assignment['warranty_conditions'] ?? '-'; ?></p>
<p><strong>تعهدات پس از راه‌اندازی:</strong> <?php echo $assignment['post_installation_commitments'] ?? '-'; ?></p>
<?php if(!empty($assignment['installation_photo'])): ?>
<p><strong>عکس نصب:</strong></p>
<img src="<?php echo $assignment['installation_photo']; ?>" class="img-thumbnail" style="max-width:300px;">
<?php endif; ?>
</div></div>
</div>
<div class="modal-footer">
<?php if(!empty($assignment['warranty_serial'])): ?>
<button type="button" class="btn btn-success" onclick="printWarranty(<?php echo $assignment['id']; ?>)">
    <i class="fas fa-print"></i> چاپ کارت گارانتی
</button>
<?php endif; ?>
<a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-warning">ویرایش این انتساب</a>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
</div>
</div></div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function loadCustomerInfo() {
    const customerSelect = document.getElementById('customer_id');
    const assetSelect = document.getElementById('asset_id');
    const employerName = document.getElementById('employer_name');
    const employerPhone = document.getElementById('employer_phone');
    const assignmentDetails = document.getElementById('assignmentDetails');
    
    if (customerSelect.value && assetSelect.value) {
        assignmentDetails.style.display = 'block';
        
        // پر کردن اطلاعات کارفرما
        const selectedCustomer = customerSelect.options[customerSelect.selectedIndex];
        employerName.value = selectedCustomer.text;
        employerPhone.value = selectedCustomer.getAttribute('data-phone') || '';
    } else {
        assignmentDetails.style.display = 'none';
    }
}

function loadAssetDetails() {
    const assetSelect = document.getElementById('asset_id');
    const deviceModel = document.getElementById('device_model_display');
    const deviceSerial = document.getElementById('device_serial_display');
    
    if (assetSelect.value) {
        const selectedAsset = assetSelect.options[assetSelect.selectedIndex];
        deviceModel.value = selectedAsset.getAttribute('data-model') || '';
        deviceSerial.value = selectedAsset.getAttribute('data-serial') || '';
    }
    
    loadCustomerInfo();
}

function previewImage(input, imgId) {
    const preview = document.getElementById(imgId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function printWarranty(assignmentId = null) {
    let printUrl = 'print_warranty.php';
    if (assignmentId) {
        printUrl += '?id=' + assignmentId;
    }
    
    window.open(printUrl, '_blank');
}

// بارگذاری اولیه
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('customer_id').addEventListener('change', loadCustomerInfo);
    document.getElementById('asset_id').addEventListener('change', loadAssetDetails);
    
    // اگر از قبل مقادیری انتخاب شده‌اند
    loadAssetDetails();
    
    // دیباگ: نمایش تعداد دستگاه‌های موجود در dropdown
    console.log("تعداد دستگاه‌های موجود در dropdown: ", document.getElementById('asset_id').options.length - 1);
});
</script>
</body>
</html>