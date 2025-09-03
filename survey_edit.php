<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/config.php';

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ادمین';
if (!$is_admin) { http_response_code(403); echo 'دسترسی غیرمجاز'; exit(); }

$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
if ($submission_id <= 0) { echo 'شناسه نامعتبر'; exit(); }

// دریافت اطلاعات ثبت و پاسخ‌ها
$submission = null; $responses = []; $questions = [];
$st = $pdo->prepare("SELECT s.*, sv.title AS survey_title FROM survey_submissions s JOIN surveys sv ON sv.id = s.survey_id WHERE s.id = ?");
$st->execute([$submission_id]);
$submission = $st->fetch();
if (!$submission) { echo 'ثبت یافت نشد'; exit(); }

$rt = $pdo->prepare("SELECT r.question_id, r.response_text, q.question_text, q.answer_type
                     FROM survey_responses r JOIN survey_questions q ON q.id = r.question_id
                     WHERE r.submission_id = ? ORDER BY q.id");
$rt->execute([$submission_id]);
$responses = $rt->fetchAll();

// اگر پاسخ وجود نداشت، سوالات نظرسنجی را برای درج اولیه نمایش بده
if (empty($responses)) {
    $qt = $pdo->prepare('SELECT id AS question_id, question_text, answer_type FROM survey_questions WHERE survey_id = ? ORDER BY id');
    $qt->execute([(int)$submission['survey_id']]);
    $responses = $qt->fetchAll();
    foreach ($responses as &$r) { $r['response_text'] = ''; }
}

// ذخیره ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edit'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('درخواست نامعتبر است - CSRF');
    }
    $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
    try {
        $pdo->beginTransaction();
        // حذف پاسخ‌های قبلی ثبت
        $pdo->prepare('DELETE FROM survey_responses WHERE submission_id = ?')->execute([$submission_id]);
        // درج مجدد
        $ins = $pdo->prepare("INSERT INTO survey_responses (survey_id, question_id, customer_id, asset_id, response_text, responded_by, submission_id, created_at)
                              SELECT ?, ?, s.customer_id, s.asset_id, ?, ?, ?, NOW() FROM survey_submissions s WHERE s.id = ?");
        $saved = 0;
        foreach ($answers as $qid => $val) {
            $val = trim((string)$val);
            if ($val === '') continue;
            $ins->execute([(int)$submission['survey_id'], (int)$qid, $val, (int)$_SESSION['user_id'], $submission_id, $submission_id]);
            $saved++;
        }
        $pdo->commit();
        $_SESSION['success'] = "ویرایش ثبت شد ($saved).";
        header('Location: survey_list.php?submission_id=' . $submission_id);
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش ثبت #<?php echo (int)$submission_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
<div class="container mt-4">
    <h4 class="mb-3">ویرایش ثبت #<?php echo (int)$submission_id; ?> (<?php echo htmlspecialchars($submission['survey_title']); ?>)</h4>
    <?php if (!empty($err)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php foreach ($responses as $r): ?>
            <div class="mb-3">
                <label class="form-label d-block"><?php echo htmlspecialchars($r['question_text']); ?></label>
                <?php if (($r['answer_type'] ?? 'text') === 'boolean'): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="answers[<?php echo (int)$r['question_id']; ?>]" value="yes" id="q<?php echo (int)$r['question_id']; ?>_yes" <?php echo ($r['response_text']==='yes')?'checked':''; ?>>
                        <label class="form-check-label" for="q<?php echo (int)$r['question_id']; ?>_yes">بله</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="answers[<?php echo (int)$r['question_id']; ?>]" value="no" id="q<?php echo (int)$r['question_id']; ?>_no" <?php echo ($r['response_text']==='no')?'checked':''; ?>>
                        <label class="form-check-label" for="q<?php echo (int)$r['question_id']; ?>_no">خیر</label>
                    </div>
                <?php elseif (($r['answer_type'] ?? 'text') === 'rating'): ?>
                    <select class="form-select w-auto" name="answers[<?php echo (int)$r['question_id']; ?>]">
                        <option value="">امتیاز</option>
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($r['response_text']==(string)$i)?'selected':''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                <?php else: ?>
                    <textarea class="form-control" name="answers[<?php echo (int)$r['question_id']; ?>]" rows="2"><?php echo htmlspecialchars($r['response_text'] ?? ''); ?></textarea>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="د-flex gap-2">
            <button class="btn btn-primary" name="save_edit" type="submit">ذخیره ویرایش</button>
            <a class="btn btn-outline-secondary" href="survey_list.php?submission_id=<?php echo (int)$submission_id; ?>">انصراف</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">چاپ</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>