<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

require_once __DIR__ . '/config.php';

// CSRF فقط برای درخواست POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('درخواست نامعتبر است - CSRF Token validation failed');
    }
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ادمین';

$survey_id = (int)($_POST['survey_id'] ?? 0);
$manage = isset($_POST['manage']);

// هدایت به مدیریت سوالات در صورت درخواست ادمین
if ($manage && $is_admin) {
    header('Location: survey.php?tab=manage&survey_id=' . $survey_id);
    exit();
}

$customer_phone = trim($_POST['customer_phone'] ?? '');
$device_code    = trim($_POST['device_code'] ?? '');

if ($survey_id <= 0) {
    $_SESSION['error'] = 'لطفاً یک نظرسنجی معتبر انتخاب کنید.';
    header('Location: survey.php?tab=answer');
    exit();
}

if ($customer_phone === '' && $device_code === '') {
    $_SESSION['error'] = 'لطفاً شماره مشتری یا شماره دستگاه را وارد کنید.';
    header('Location: survey.php?survey_id=' . $survey_id . '&tab=answer');
    exit();
}

// یافتن مشتری بر اساس phone
$customer = null;
if ($customer_phone !== '') {
    $st = $pdo->prepare('SELECT id, name, phone, address FROM customers WHERE phone = ? LIMIT 1');
    $st->execute([$customer_phone]);
    $customer = $st->fetch();
}

// یافتن دستگاه: اگر device_code پر بود
$asset = null;
if ($device_code !== '') {
    // مدل ژنراتور یا سریال موتور برق بر اساس نوع
    $st = $pdo->prepare("SELECT a.id, a.name, a.serial_number, a.model, at.name AS type_name
                         FROM assets a
                         JOIN asset_types at ON at.id = a.type_id
                         WHERE (at.name = 'generator' AND a.model = ?)
                            OR (at.name = 'power_motor' AND a.serial_number = ?)");
    $st->execute([$device_code, $device_code]);
    $asset = $st->fetch();
}

if (!$customer && !$asset) {
    $msg = 'این شماره موجود نیست.';
    $_SESSION['error'] = $msg;
    header('Location: survey.php?survey_id=' . $survey_id . '&tab=answer');
    exit();
}

// ایجاد submission
try {
    $ins = $pdo->prepare("INSERT INTO survey_submissions (survey_id, customer_id, asset_id, started_by, status) VALUES (?,?,?,?, 'in_progress')");
    $ins->execute([$survey_id, $customer ? (int)$customer['id'] : null, $asset ? (int)$asset['id'] : null, $user_id]);
    $submission_id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    $_SESSION['error'] = 'خطای شروع: ' . $e->getMessage();
    header('Location: survey.php?survey_id=' . $survey_id . '&tab=answer');
    exit();
}

// دریافت سوالات
$questions = [];
$st = $pdo->prepare('SELECT id, question_text, 
                            COALESCE(answer_type,
                                     CASE question_type 
                                          WHEN "yes_no" THEN "boolean" 
                                          WHEN "rating" THEN "rating" 
                                          ELSE "text" END) AS answer_type
                     FROM survey_questions WHERE survey_id = ? ORDER BY id');
$st->execute([$survey_id]);
$questions = $st->fetchAll();

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پاسخ به نظرسنجی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
<div class="container mt-4">
    <h4 class="mb-3">پاسخ به نظرسنجی</h4>

    <div class="card mb-4">
        <div class="card-body">
            <div class="mb-2">
                <strong>مشتری:</strong> <?php echo htmlspecialchars($customer['name'] ?? '-'); ?>
                <?php if ($customer): ?> (<?php echo htmlspecialchars($customer['phone']); ?>)<?php endif; ?>
                | <strong>دستگاه:</strong> <?php echo htmlspecialchars($asset['name'] ?? '-'); ?>
                <?php if ($asset): ?> (<?php echo htmlspecialchars(($asset['model'] ?? '') . ' / ' . ($asset['serial_number'] ?? '')) ?>)<?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($questions)): ?>
        <div class="alert alert-info">برای این نظرسنجی سوالی ثبت نشده است.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>فرم پاسخ</span>
                <div class="d-none d-print-block"><small>پیش‌نمایش چاپ</small></div>
            </div>
            <div class="card-body">
                <form method="post" id="answerForm" action="survey_answer_submit.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="survey_id" value="<?php echo (int)$survey_id; ?>">
                    <input type="hidden" name="submission_id" value="<?php echo (int)$submission_id; ?>">

                    <?php foreach ($questions as $q): ?>
                        <div class="mb-3">
                            <label class="form-label d-block"><?php echo htmlspecialchars($q['question_text']); ?></label>
                            <?php if ($q['answer_type'] === 'boolean'): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" value="yes" id="q<?php echo (int)$q['id']; ?>_yes">
                                    <label class="form-check-label" for="q<?php echo (int)$q['id']; ?>_yes">بله</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" value="no" id="q<?php echo (int)$q['id']; ?>_no">
                                    <label class="form-check-label" for="q<?php echo (int)$q['id']; ?>_no">خیر</label>
                                </div>
                            <?php elseif ($q['answer_type'] === 'rating'): ?>
                                <select class="form-select w-auto" name="answers[<?php echo (int)$q['id']; ?>]">
                                    <option value="">امتیاز</option>
                                    <?php for ($i=1;$i<=5;$i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php else: ?>
                                <textarea class="form-control" name="answers[<?php echo (int)$q['id']; ?>]" rows="2" placeholder="پاسخ تشریحی"></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-warning" id="previewBtn">پیش‌نمایش و اتمام</button>
                        <button type="submit" name="submit_responses" class="btn btn-success">ثبت پاسخ‌ها</button>
                        <button type="button" class="btn btn-outline-primary d-none d-print-inline" onclick="window.print()">چاپ</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Preview -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">پایان نظرسنجی</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p class="mb-3">آیا از اتمام نظرسنجی مطمئن هستید؟ پیش‌نمایش پاسخ‌ها:</p>
                <div id="answersPreview" class="table-responsive">
                  <table class="table table-sm table-striped">
                    <thead><tr><th>سوال</th><th>پاسخ</th></tr></thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ادامه ویرایش</button>
                <button type="button" class="btn btn-primary" id="confirmSubmit">تایید و ثبت</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">چاپ</button>
              </div>
            </div>
          </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const previewBtn = document.getElementById('previewBtn');
  const confirmBtn = document.getElementById('confirmSubmit');
  const form = document.getElementById('answerForm');
  const modalEl = document.getElementById('previewModal');
  if (!previewBtn || !confirmBtn || !form || !modalEl) return;
  const bsModal = new bootstrap.Modal(modalEl);

  previewBtn.addEventListener('click', function(){
    const tbody = modalEl.querySelector('#answersPreview tbody');
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    const rows = [];
    form.querySelectorAll('[name^=\"answers[\"]').forEach(function(input){
      const name = input.getAttribute('name');
      const m = name && name.match(/answers\\[(\\d+)\\]/);
      if (!m) return;
      const qid = m[1];
      let qText = '';
      const container = input.closest('.mb-3');
      if (container) {
        const label = container.querySelector('label.form-label');
        if (label) qText = label.textContent.trim();
      }
      let val = '';
      if (input.type === 'radio') {
        const checked = form.querySelector('input[name=\"answers['+qid+']\"]:checked');
        if (checked) val = checked.value;
      } else if (input.tagName === 'SELECT') {
        val = input.value;
      } else if (input.tagName === 'TEXTAREA') {
        val = input.value.trim();
      }
      if (!rows.some(r => r.qid === qid)) {
        rows.push({ qid, qText, val });
      }
    });
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const tdQ = document.createElement('td'); tdQ.textContent = r.qText || ('سوال #' + r.qid);
      const tdA = document.createElement('td'); tdA.textContent = r.val || '—';
      tr.appendChild(tdQ); tr.appendChild(tdA);
      tbody.appendChild(tr);
    });
    bsModal.show();
  });

  confirmBtn.addEventListener('click', function(){
    const hiddenSubmit = document.createElement('input');
    hiddenSubmit.type = 'hidden';
    hiddenSubmit.name = 'submit_responses';
    hiddenSubmit.value = '1';
    form.appendChild(hiddenSubmit);
    form.submit();
  });
});
</script>
</body>
</html>