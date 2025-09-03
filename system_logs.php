<?php
session_start();
include 'config.php';

// بررسی دسترسی فقط ادمین
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ادمین') {
    die('دسترسی غیرمجاز');
}

// دریافت تمام لاگ‌ها
$stmt = $pdo->query("SELECT l.*, u.username FROM system_logs l
                     LEFT JOIN users u ON l.user_id = u.id
                     ORDER BY l.created_at DESC");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لاگ‌های سیستم</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Vazirmatn, sans-serif; padding: 20px; }
table { font-size: 0.9rem; }
</style>
</head>
<body>
<div class="container">
<h2 class="mb-4">لاگ‌های سیستم</h2>
<table class="table table-bordered table-striped table-hover">
<thead class="table-dark">
<tr>
<th>#</th>
<th>کاربر</th>
<th>عمل</th>
<th>توضیحات</th>
<th>IP</th>
<th>User Agent</th>
<th>تاریخ</th>
</tr>
</thead>
<tbody>
<?php foreach ($logs as $i => $log): ?>
<tr>
<td><?= $i + 1 ?></td>
<td><?= htmlspecialchars($log['username'] ?? 'ناشناخته') ?></td>
<td><?= htmlspecialchars($log['action']) ?></td>
<td><?= htmlspecialchars($log['description']) ?></td>
<td><?= htmlspecialchars($log['ip_address']) ?></td>
<td><?= htmlspecialchars($log['user_agent']) ?></td>
<td><?= htmlspecialchars($log['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</body>
</html>
