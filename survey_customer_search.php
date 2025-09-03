<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$customers = [];
$assets = [];
$search_term = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search_term = trim($_POST['search_term']);
    
    if (!empty($search_term)) {
        // جستجوی مشتریان
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE phone LIKE ? OR name LIKE ?");
        $stmt->execute(["%$search_term%", "%$search_term%"]);
        $customers = $stmt->fetchAll();
        
        // جستجوی دستگاه‌ها
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE serial_number LIKE ? OR name LIKE ?");
        $stmt->execute(["%$search_term%", "%$search_term%"]);
        $assets = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جستجو برای نظرسنجی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: Vazirmatn, sans-serif;
            background-color: #f8f9fa;
            padding-top: 80px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2 class="text-center mb-4">جستجو برای شروع نظرسنجی</h2>
        
        <div class="card mb-4">
            <div class="card-header">جستجوی مشتری یا دستگاه</div>
            <div class="card-body">
                <form method="POST">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="شماره تماس مشتری یا شماره سریال دستگاه" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-primary" type="submit" name="search">جستجو</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($search_term)): ?>
            <!-- نتایج جستجوی مشتریان -->
            <?php if (count($customers) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">نتایج جستجوی مشتریان</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>نام</th>
                                        <th>تلفن</th>
                                        <th>آدرس</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['address']); ?></td>
                                            <td>
                                                <a href="survey_response.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-primary btn-sm">شروع نظرسنجی</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- نتایج جستجوی دستگاه‌ها -->
            <?php if (count($assets) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">نتایج جستجوی دستگاه‌ها</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>نام دستگاه</th>
                                        <th>شماره سریال</th>
                                        <th>مدل</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assets as $asset): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['model']); ?></td>
                                            <td>
                                                <a href="survey_response.php?asset_id=<?php echo $asset['id']; ?>" class="btn btn-primary btn-sm">شروع نظرسنجی</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (count($customers) === 0 && count($assets) === 0): ?>
                <div class="alert alert-warning">هیچ نتیجه‌ای یافت نشد.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>