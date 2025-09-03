<?php
// چاپ/نمایش کارت گارانتی از جدول guaranty_cards به صورت HTML قابل چاپ
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die('شناسه نامعتبر'); }

$st = $pdo->prepare("SELECT gc.*, 
                            a.name AS asset_name, a.serial_number, a.model AS asset_model,
                            CASE WHEN c.customer_type='حقوقی' AND COALESCE(c.company,'')<>'' THEN c.company ELSE c.full_name END AS customer_name,
                            c.address AS customer_address
                     FROM guaranty_cards gc
                     JOIN assets a ON a.id = gc.asset_id
                     JOIN customers c ON c.id = gc.customer_id
                     WHERE gc.id = ?");
$st->execute([$id]);
$card = $st->fetch(PDO::FETCH_ASSOC);
if (!$card) { die('کارت یافت نشد'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>کارت گارانتی #<?php echo (int)$card['id']; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
@page { size: A4; margin: 15mm; }
.print-card { border:2px solid #0d6efd; border-radius:10px; padding:20px; }
.header { text-align:center; border-bottom:2px solid #0d6efd; padding-bottom:10px; margin-bottom:15px; }
.small { font-size: 12px; color:#555; }
</style>
</head>
<body>
<div class="container my-3">
  <div class="d-print-none mb-3">
    <a href="javascript:window.print()" class="btn btn-primary">چاپ</a>
    <a href="create_guaranty.php" class="btn btn-secondary">بازگشت</a>
  </div>

  <div class="print-card">
    <div class="header">
      <h3 class="m-0">اعلا نیرو (سهامی خاص)</h3>
      <div class="small">کارت گارانتی</div>
    </div>

    <div class="row mb-2">
      <div class="col-md-6"><strong>تاریخ صدور:</strong> <?php echo h($card['issue_date']); ?></div>
      <div class="col-md-6"><strong>شماره کارت:</strong> <?php echo h($card['guaranty_number']); ?></div>
    </div>

    <div class="row mb-2">
      <div class="col-md-6"><strong>خریدار:</strong> <?php echo h($card['customer_name']); ?></div>
      <div class="col-md-6"><strong>محل نصب:</strong> <?php echo h($card['customer_address']); ?></div>
    </div>

    <div class="row mb-2">
      <div class="col-md-6"><strong>دستگاه:</strong> <?php echo h($card['asset_name']); ?></div>
      <div class="col-md-6"><strong>سریال دستگاه:</strong> <?php echo h($card['serial_number']); ?></div>
    </div>

    <div class="row mb-2">
      <div class="col-md-6"><strong>مدل آلترناتور:</strong> <?php echo h($card['alternator_model']); ?></div>
      <div class="col-md-6"><strong>سریال آلترناتور:</strong> <?php echo h($card['alternator_serial']); ?></div>
    </div>

    <hr>
    <p class="small">
      تاریخ اتمام گارانتی: 18 ماه از زمان تحویل فیزیکی، 12 ماه از زمان نصب و راه اندازی یا 1200 ساعت کارکرد (هرکدام زودتر به پایان برسد).
    </p>
    <div class="small text-center">
      021-88837242 | info@aalaniroo.com | www.aalaniroo.com
    </div>
  </div>
</div>
</body>
</html>