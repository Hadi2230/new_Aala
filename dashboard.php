<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

// ثبت لاگ مشاهده داشبورد
try { logAction($pdo, 'VIEW_DASHBOARD', 'مشاهده داشبورد'); } catch (Throwable $e) {}

// مقادیر پیش‌فرض
$total_assets = 0;
$total_customers = 0;
$total_users = 0;
$total_assignments = 0;
$assigned_assets = 0;
$total_guaranties = 0;
$active_guaranties = 0;
$last_login = null;

// دریافت آمار ایمن با try/catch (برای جلوگیری از HTTP 500 در صورت نبود جدول/اتصال)
try { $total_assets = (int)$pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total']; } catch (Throwable $e) {}
try { $total_customers = (int)$pdo->query("SELECT COUNT(*) as total FROM customers")->fetch()['total']; } catch (Throwable $e) {}
try { $total_users = (int)$pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total']; } catch (Throwable $e) {}
try { $total_assignments = (int)$pdo->query("SELECT COUNT(*) as total FROM asset_assignments")->fetch()['total']; } catch (Throwable $e) {}
try { $assigned_assets = (int)$pdo->query("SELECT COUNT(DISTINCT asset_id) as total FROM asset_assignments")->fetch()['total']; } catch (Throwable $e) {}

// دریافت آمار گارانتی (اگر جدول موجود نبود، نادیده می‌گیریم)
try {
    $total_guaranties = (int)$pdo->query("SELECT COUNT(*) as total FROM guaranty_cards")->fetch()['total'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM guaranty_cards WHERE DATE_ADD(issue_date, INTERVAL 18 MONTH) >= CURDATE()");
    $stmt->execute();
    $active_guaranties = (int)$stmt->fetch()['total'];
} catch (Throwable $e) {}

// آخرین ورود کاربر
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $last_login = $stmt->fetch()['last_login'] ?? null;
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سامانه مدیریت اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1a1a1a;
            --dark-text: #ffffff;
        }

        body {
            font-family: Vazirmatn, sans-serif;
            background-color: #f8f9fa;
            padding-top: 80px;
        }

        .dark-mode { 
            background-color: var(--dark-bg) !important; 
            color: var(--dark-text) !important; 
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .dark-mode .stat-card {
            background: #2d3748;
        }

        .stat-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .assets-count { color: var(--secondary-color); }
        .customers-count { color: var(--success-color); }
        .users-count { color: var(--accent-color); }
        .assignments-count { color: var(--warning-color); }
        .guaranty-count { color: var(--info-color); }

        .stat-title {
            font-weight: 500;
            color: #555;
            margin-bottom: 15px;
        }

        .dark-mode .stat-title {
            color: #ccc;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .dark-mode .card {
            background-color: #2d3748;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            border: none;
            border-radius: 8px;
            padding: 8px 20px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.4);
        }

        .list-group-item {
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px !important;
            margin-bottom: 8px;
        }

        .dark-mode .list-group-item {
            background-color: #374151;
            border-color: #4b5563;
            color: var(--dark-text);
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme']==='dark' ? 'dark-mode' : ''; ?>">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="text-center mb-4">داشبورد مدیریت</h1>
        <p class="text-center">به سامانه مدیریت شرکت <strong>اعلا نیرو</strong> خوش آمدید.</p>
        <?php if ($last_login): ?>
            <p class="text-center text-muted">آخرین ورود شما: 
                <?php 
                try {
                    echo htmlspecialchars(jalaliDate($last_login));
                } catch (Throwable $e) {
                    echo htmlspecialchars($last_login);
                }
                ?>
            </p>
        <?php endif; ?>

        <div class="row mt-5">
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-number assets-count"><?php echo $total_assets; ?></div>
                    <div class="stat-title">تعداد دارایی‌ها</div>
                    <a href="assets.php" class="btn btn-primary">مشاهده دارایی‌ها</a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-number customers-count"><?php echo $total_customers; ?></div>
                    <div class="stat-title">تعداد مشتریان</div>
                    <a href="customers.php" class="btn btn-primary">مشاهده مشتریان</a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-number users-count"><?php echo $total_users; ?></div>
                    <div class="stat-title">تعداد کاربران</div>
                    <a href="users.php" class="btn btn-primary">مشاهده کاربران</a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-number assignments-count"><?php echo $assigned_assets; ?>/<?php echo $total_assets; ?></div>
                    <div class="stat-title">انتساب‌های فعال</div>
                    <a href="assignments.php" class="btn btn-primary">مدیریت انتساب‌ها</a>
                </div>
            </div>
        </div>

        <!-- ردیف دوم برای آمار گارانتی -->
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-number guaranty-count"><?php echo $total_guaranties; ?></div>
                    <div class="stat-title">کارت‌های گارانتی</div>
                    <a href="create_guaranty.php" class="btn btn-primary">مدیریت گارانتی</a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card">
                    <div class="stat-number guaranty-count"><?php echo $active_guaranties; ?></div>
                    <div class="stat-title">گارانتی‌های فعال</div>
                    <a href="create_guaranty.php" class="btn btn-primary">مشاهده جزئیات</a>
                </div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">آمار سریع</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center p-2">
                                    <span>دارایی‌های منتسب شده:</span>
                                    <span class="badge bg-success"><?php echo $assigned_assets; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center p-2">
                                    <span>دارایی‌های منتسب نشده:</span>
                                    <span class="badge bg-warning"><?php echo max(0, $total_assets - $assigned_assets); ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center p-2">
                                    <span>کل انتساب‌ها:</span>
                                    <span class="badge bg-info"><?php echo $total_assignments; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex justify-content-between align-items-center p-2">
                                    <span>گارانتی‌های فعال:</span>
                                    <span class="badge bg-primary"><?php echo $active_guaranties; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- یادداشت‌های من -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">یادداشت‌های من</div>
                    <div class="card-body">
                        <form method="post" action="save_note.php">
                            <?php csrf_field(); ?>
                            <div class="mb-3">
                                <textarea class="form-control" name="note" rows="3" placeholder="یادداشت خود را بنویسید..."></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit">ذخیره یادداشت</button>
                        </form>
                        <hr>
                        <div>
                            <?php
                            try {
                                if (isset($_SESSION['user_id'])) {
                                    $stmt = $pdo->prepare("SELECT note, updated_at FROM user_notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 10");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $notes = $stmt->fetchAll();
                                    if ($notes) {
                                        echo '<ul class="list-group">';
                                        foreach ($notes as $n) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'.htmlspecialchars($n['note']).'<span class="badge bg-secondary">'.htmlspecialchars($n['updated_at']).'</span></li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<p class="text-muted">یادداشتی موجود نیست.</p>';
                                    }
                                }
                            } catch (Throwable $e) {}
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.cookie.includes('theme=dark')) {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
</body>
</html>