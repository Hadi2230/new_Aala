<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// فقط ادمین یا مدیر اجازه ویرایش داشته باشه
if (!in_array($_SESSION['role'], ['ادمین','مدیر'])) {
    $_SESSION['error'] = "شما اجازه دسترسی به این بخش را ندارید!";
    header("Location: customers.php");
    exit();
}

include 'config.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    $_SESSION['error'] = "شناسه مشتری مشخص نشده است!";
    header('Location: customers.php');
    exit();
}

// دریافت مشتری
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error'] = "مشتری یافت نشد!";
    header('Location: customers.php');
    exit();
}

// ویرایش مشتری
$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_customer'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "خطای امنیتی: توکن CSRF نامعتبر است!";
        header('Location: customers.php');
        exit();
    }

    // داده‌ها
    $type       = $_POST['type'] ?? '';
    $full_name  = strip_tags(trim($_POST['full_name'] ?? ''));
    $phone      = strip_tags(trim($_POST['phone'] ?? ''));
    $company    = strip_tags(trim($_POST['company'] ?? ''));
    $manager    = strip_tags(trim($_POST['manager'] ?? ''));
    $company_phone = strip_tags(trim($_POST['company_phone'] ?? ''));
    $manager_phone = strip_tags(trim($_POST['manager_phone'] ?? ''));
    $operator   = strip_tags(trim($_POST['operator'] ?? ''));
    $operator_phone = strip_tags(trim($_POST['operator_phone'] ?? ''));
    $address    = strip_tags(trim($_POST['address'] ?? ''));
    $description = strip_tags(trim($_POST['description'] ?? ''));

    // اعتبارسنجی
    if ($type === 'حقوقی') {
        if (empty($company)) $errors[] = "نام شرکت اجباری است!";
        if (empty($manager)) $errors[] = "نام مسئول شرکت اجباری است!";
        if (empty($company_phone) || !preg_match('/^[0-9]{10,11}$/', $company_phone)) $errors[] = "شماره تماس شرکت معتبر نیست!";
        if (empty($manager_phone) || !preg_match('/^[0-9]{10,11}$/', $manager_phone)) $errors[] = "شماره تماس مسئول شرکت معتبر نیست!";
    } elseif ($type === 'حقیقی') {
        if (empty($full_name)) $errors[] = "نام و نام خانوادگی اجباری است!";
        if (empty($phone) || !preg_match('/^[0-9]{10,11}$/', $phone)) $errors[] = "شماره تلفن معتبر نیست!";
    } else {
        $errors[] = "نوع مشتری انتخاب نشده است!";
    }

    if (!empty($operator) && !preg_match('/^[0-9آ-ی\s]+$/u', $operator)) {
        $errors[] = "نام اپراتور فقط باید شامل حروف و فاصله باشد!";
    }
    if (!empty($operator_phone) && !preg_match('/^[0-9]{10,11}$/', $operator_phone)) {
        $errors[] = "شماره تلفن اپراتور معتبر نیست!";
    }

    // اگر بدون خطا
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE customers SET 
                    type = ?, full_name = ?, phone = ?, company = ?, manager = ?, 
                    company_phone = ?, manager_phone = ?, operator = ?, operator_phone = ?, 
                    address = ?, description = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([
                $type, $full_name, $phone, $company, $manager,
                $company_phone, $manager_phone, $operator, $operator_phone,
                $address, $description, $customer_id
            ]);

            $_SESSION['success'] = "مشتری با موفقیت ویرایش شد!";
            header('Location: customers.php');
            exit();
        } catch (PDOException $e) {
            $errors[] = "خطا در ذخیره تغییرات: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ویرایش مشتری</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h2 class="mb-4 text-center">ویرایش مشتری</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <!-- انتخاب نوع مشتری -->
        <div class="mb-3">
            <label class="form-label">نوع مشتری *</label>
            <select class="form-select" name="type" id="typeSelect" required>
                <option value="">انتخاب کنید</option>
                <option value="حقوقی" <?php if($customer['type']==='حقوقی') echo 'selected'; ?>>حقوقی</option>
                <option value="حقیقی" <?php if($customer['type']==='حقیقی') echo 'selected'; ?>>حقیقی</option>
            </select>
        </div>

        <!-- فرم حقوقی -->
        <div id="hoghooghiFields" style="display:none;">
            <div class="mb-3"><label>نام شرکت</label>
                <input type="text" name="company" class="form-control" value="<?php echo htmlspecialchars($customer['company']); ?>">
            </div>
            <div class="mb-3"><label>نام مسئول شرکت</label>
                <input type="text" name="manager" class="form-control" value="<?php echo htmlspecialchars($customer['manager']); ?>">
            </div>
            <div class="mb-3"><label>تلفن شرکت</label>
                <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($customer['company_phone']); ?>">
            </div>
            <div class="mb-3"><label>تلفن مسئول</label>
                <input type="text" name="manager_phone" class="form-control" value="<?php echo htmlspecialchars($customer['manager_phone']); ?>">
            </div>
        </div>

        <!-- فرم حقیقی -->
        <div id="haghighiFields" style="display:none;">
            <div class="mb-3"><label>نام و نام خانوادگی</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($customer['full_name']); ?>">
            </div>
            <div class="mb-3"><label>تلفن</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone']); ?>">
            </div>
        </div>

        <!-- مشترک -->
        <div class="mb-3"><label>آدرس</label>
            <textarea name="address" class="form-control"><?php echo htmlspecialchars($customer['address']); ?></textarea>
        </div>
        <div class="mb-3"><label>اپراتور تجهیزات</label>
            <input type="text" name="operator" class="form-control" value="<?php echo htmlspecialchars($customer['operator']); ?>">
        </div>
        <div class="mb-3"><label>تلفن اپراتور</label>
            <input type="text" name="operator_phone" class="form-control" value="<?php echo htmlspecialchars($customer['operator_phone']); ?>">
        </div>
        <div class="mb-3"><label>توضیحات</label>
            <textarea name="description" class="form-control"><?php echo htmlspecialchars($customer['description']); ?></textarea>
        </div>

        <button type="submit" name="edit_customer" class="btn btn-primary">ذخیره تغییرات</button>
        <a href="customers.php" class="btn btn-secondary">بازگشت</a>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const typeSelect = document.getElementById('typeSelect');
    const hoghooghi = document.getElementById('hoghooghiFields');
    const haghighi = document.getElementById('haghighiFields');

    function toggleFields() {
        if(typeSelect.value === 'حقوقی'){
            hoghooghi.style.display = 'block';
            haghighi.style.display = 'none';
        } else if(typeSelect.value === 'حقیقی'){
            haghighi.style.display = 'block';
            hoghooghi.style.display = 'none';
        } else {
            hoghooghi.style.display = 'none';
            haghighi.style.display = 'none';
        }
    }
    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
</body>
</html>
