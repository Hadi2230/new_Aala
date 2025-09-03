<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/config.php';

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ادمین';

$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

// لیست ثبت‌ها
$subs = [];
try {
    $st = $pdo->query("SELECT s.id, s.created_at, s.status, sv.title AS survey_title,
                              c.name AS customer_name, a.name AS asset_name, a.serial_number
                       FROM survey_submissions s
                       JOIN surveys sv ON sv.id = s.survey_id
                       LEFT JOIN customers c ON c.id = s.customer_id
                       LEFT JOIN assets a ON a.id = s.asset_id
                       ORDER BY s.id DESC LIMIT 200");
    $subs = $st ? $st->fetchAll() : [];
} catch (Throwable $e) {}

// پیش‌نمایش
$viewSubmission = null; $viewResponses = [];
if ($submission_id > 0) {
    $st = $pdo->prepare("SELECT s.*, sv.title AS survey_title, c.name AS customer_name, a.name AS asset_name, a.serial_number
                         FROM survey_submissions s
                         JOIN surveys sv ON sv.id = s.survey_id
                         LEFT JOIN customers c ON c.id = s.customer_id
                         LEFT JOIN assets a ON a.id = s.asset_id
                         WHERE s.id = ?");
    $st->execute([$submission_id]);
    $viewSubmission = $st->fetch();
    if ($viewSubmission) {
        $rt = $pdo->prepare("SELECT r.response_text, q.question_text, q.answer_type
                             FROM survey_responses r
                             JOIN survey_questions q ON q.id = r.question_id
                             WHERE r.submission_id = ?
                             ORDER BY q.id");
        $rt->execute([$submission_id]);
        $viewResponses = $rt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌های نظرسنجی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
<div class="container mt-4">
    <h4 class="mb-3">ثبت‌های نظرسنجی</h4>

    <div class="card mb-4">
        <div class="card-body">
            <?php if (empty($subs)): ?>
                <p class="text-muted m-0">ثبتی یافت نشد.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>نظرسنجی</th>
                                <th>مشتری</th>
                                <th>دستگاه</th>
                                <th>سریال</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subs as $s): ?>
                                <tr>
                                    <td><?php echo (int)$s['id']; ?></td>
                                    <td><?php echo htmlspecialchars($s['survey_title']); ?></td>
                                    <td><?php echo htmlspecialchars($s['customer_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($s['asset_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($s['serial_number'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($s['status']); ?></td>
                                    <td><?php echo htmlspecialchars($s['created_at']); ?></td>
                                    <td class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="survey_list.php?submission_id=<?php echo (int)$s['id']; ?>">مشاهده/چاپ</a>
                                        <?php if ($is_admin): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="survey_edit.php?submission_id=<?php echo (int)$s['id']; ?>">ویرایش</a>
                                            <form method="post" action="survey.php" onsubmit="return confirm('حذف این ثبت؟');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" name="delete_submission" type="submit">حذف</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($viewSubmission): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>پیش‌نمایش ثبت #<?php echo (int)$viewSubmission['id']; ?> (<?php echo htmlspecialchars($viewSubmission['survey_title']); ?>)</span>
            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">چاپ</button>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <strong>مشتری:</strong> <?php echo htmlspecialchars($viewSubmission['customer_name'] ?? '-'); ?> |
                <strong>دستگاه:</strong> <?php echo htmlspecialchars($viewSubmission['asset_name'] ?? '-'); ?> |
                <strong>سریال:</strong> <?php echo htmlspecialchars($viewSubmission['serial_number'] ?? '-'); ?> |
                <strong>وضعیت:</strong> <?php echo htmlspecialchars($viewSubmission['status']); ?> |
                <strong>تاریخ:</strong> <?php echo htmlspecialchars($viewSubmission['created_at']); ?>
            </div>
            <?php if (empty($viewResponses)): ?>
                <p class="text-muted m-0">پاسخی ثبت نشده.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>سوال</th><th>پاسخ</th></tr></thead>
                        <tbody>
                            <?php foreach ($viewResponses as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['question_text']); ?></td>
                                    <td><?php echo htmlspecialchars($r['response_text']); ?></td>
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