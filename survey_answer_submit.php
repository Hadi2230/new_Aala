<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/config.php';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('درخواست نامعتبر است - CSRF Token validation failed');
}

$user_id = (int)$_SESSION['user_id'];
$survey_id = (int)($_POST['survey_id'] ?? 0);
$submission_id = (int)($_POST['submission_id'] ?? 0);
$answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];

if ($survey_id <= 0 || $submission_id <= 0) {
    $_SESSION['error'] = 'شناسه نامعتبر است.';
    header('Location: survey.php?tab=answer&survey_id=' . $survey_id);
    exit();
}

try {
    $qs = $pdo->prepare('SELECT id, answer_type FROM survey_questions WHERE survey_id = ? ORDER BY id');
    $qs->execute([$survey_id]);
    $questions = $qs->fetchAll();

    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO survey_responses (survey_id, question_id, customer_id, asset_id, response_text, responded_by, submission_id, created_at)
                          SELECT ?, ?, s.customer_id, s.asset_id, ?, ?, ?, NOW() FROM survey_submissions s WHERE s.id = ?");
    $saved = 0;
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        $atype = $q['answer_type'];
        $val = isset($answers[$qid]) ? $answers[$qid] : '';
        if ($atype === 'boolean') {
            $val = ($val === 'yes') ? 'yes' : (($val === 'no') ? 'no' : '');
        } elseif ($atype === 'rating') {
            $val = (string)max(1, min(5, (int)$val));
        } else {
            $val = trim((string)$val);
        }
        if ($val !== '') {
            $ins->execute([$survey_id, $qid, $val, $user_id, $submission_id, $submission_id]);
            $saved++;
        }
    }
    $pdo->prepare('UPDATE survey_submissions SET status=\"completed\" WHERE id = ?')->execute([$submission_id]);
    $pdo->commit();
    $_SESSION['success'] = $saved > 0 ? \"پاسخ‌ها ثبت شد ($saved).\" : 'پاسخی ثبت نشد.';
    header('Location: survey_list.php?submission_id=' . $submission_id);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error'] = 'خطا در ثبت: ' . $e->getMessage();
    header('Location: survey.php?tab=answer&survey_id=' . $survey_id);
}