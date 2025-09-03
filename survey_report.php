<?php
// گزارش پاسخ‌های نظرسنجی با فیلترها و خروجی CSV (ادمین)

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/config.php';

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ادمین';
if (!$is_admin) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit();
}

// دریافت لیست نظرسنجی‌ها
$surveys = [];
try {
    $q = $pdo->query('SELECT id, title FROM surveys ORDER BY id DESC');
    $surveys = $q ? $q->fetchAll() : [];
} catch (Throwable $e) {
    $surveys = [];
}

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$customer_id = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int)$_GET['customer_id'] : null;
$asset_id = isset($_GET['asset_id']) && $_GET['asset_id'] !== '' ? (int)$_GET['asset_id'] : null;
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// کوئری اصلی
$where = ['sr.survey_id = ?'];
$params = [$survey_id];

if ($customer_id !== null) { $where[] = 'sr.customer_id = ?'; $params[] = $customer_id; }
if ($asset_id !== null) { $where[] = 'sr.asset_id = ?'; $params[] = $asset_id; }
if ($date_from !== '') { $where[] = 'DATE(sr.created_at) >= ?'; $params[] = $date_from; }
if ($date_to !== '') { $where[] = 'DATE(sr.created_at) <= ?'; $params[] = $date_to; }

$whereSql = implode(' AND ', $where);

$sql = "SELECT sr.id, sr.created_at, sr.response_text,
               sq.question_text, sq.answer_type,
               u.username AS responded_by_username,
               c.name AS customer_name, a.name AS asset_name, a.serial_number AS asset_serial
        FROM survey_responses sr
        JOIN survey_questions sq ON sq.id = sr.question_id
        JOIN users u ON u.id = sr.responded_by
        LEFT JOIN customers c ON c.id = sr.customer_id
        LEFT JOIN assets a ON a.id = sr.asset_id
        WHERE $whereSql
        ORDER BY sr.created_at DESC, sr.id DESC";

$rows = [];
if ($survey_id > 0) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

if ($export && $survey_id > 0) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="survey_report_' . $survey_id . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, ['تاریخ', 'سوال', 'نوع پاسخ', 'پاسخ', 'کاربر', 'مشتری', 'دستگاه', 'سریال']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['question_text'],
            $r['answer_type'],
            $r['response_text'],
            $r['responded_by_username'],
            $r['customer_name'],
            $r['asset_name'],
            $r['asset_serial']
        ]);
    }
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش نظرسنجی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
<div class="container mt-4">
    <h4 class="mb-3">گزارش نظرسنجی</h4>

    <form class="card card-body mb-3" method="get">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">نظرسنجی</label>
                <select name="survey_id" class="form-select" required>
                    <option value="">انتخاب کنید</option>
                    <?php foreach ($surveys as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo $survey_id===(int)$s['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($s['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">شناسه مشتری</label>
                <input type="number" name="customer_id" value="<?php echo htmlspecialchars((string)($customer_id ?? '')); ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">شناسه دستگاه</label>
                <input type="number" name="asset_id" value="<?php echo htmlspecialchars((string)($asset_id ?? '')); ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">از تاریخ</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">تا تاریخ</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">جستجو</button>
            <?php if ($survey_id > 0): ?>
                <a class="btn btn-outline-secondary" href="?<?php echo http_build_query(array_merge($_GET,['export'=>'csv'])); ?>">خروجی CSV</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($survey_id === 0): ?>
        <div class="alert alert-info">ابتدا یک نظرسنجی انتخاب کنید.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <?php if (empty($rows)): ?>
                    <p class="text-muted m-0">رکوردی یافت نشد.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>سوال</th>
                                    <th>نوع پاسخ</th>
                                    <th>پاسخ</th>
                                    <th>کاربر</th>
                                    <th>مشتری</th>
                                    <th>دستگاه</th>
                                    <th>سریال</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($r['question_text']); ?></td>
                                        <td><?php echo htmlspecialchars($r['answer_type']); ?></td>
                                        <td><?php echo htmlspecialchars($r['response_text']); ?></td>
                                        <td><?php echo htmlspecialchars($r['responded_by_username']); ?></td>
                                        <td><?php echo htmlspecialchars($r['customer_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['asset_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['asset_serial'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>