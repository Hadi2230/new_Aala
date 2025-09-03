<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ادمین') {
    header('Location: login.php');
    exit();
}

include 'config.php';

$error = '';
$success = '';

// ایجاد نظرسنجی جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_survey'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('درخواست نامعتبر است - CSRF Token validation failed');
    }
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    if (empty($title)) {
        $error = 'عنوان نظرسنجی نمی‌تواند خالی باشد.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO surveys (title, description, created_by) VALUES (?, ?, ?)");
        if ($stmt->execute([$title, $description, $_SESSION['user_id']])) {
            $success = 'نظرسنجی با موفقیت ایجاد شد.';
        } else {
            $error = 'خطا در ایجاد نظرسنجی.';
        }
    }
}

// اضافه کردن سوال جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('درخواست نامعتبر است - CSRF Token validation failed');
    }
    $survey_id = (int)($_POST['survey_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $question_type = $_POST['question_type'] ?? 'descriptive';
    
    if ($survey_id <= 0) {
        $error = 'ابتدا یک نظرسنجی انتخاب کنید.';
    } elseif (empty($question_text)) {
        $error = 'متن سوال نمی‌تواند خالی باشد.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type) VALUES (?, ?, ?)");
        if ($stmt->execute([$survey_id, $question_text, $question_type])) {
            $success = 'سوال با موفقیت اضافه شد.';
        } else {
            $error = 'خطا در اضافه کردن سوال.';
        }
    }
}

// دریافت لیست نظرسنجی‌ها
$surveys = $pdo->query("SELECT * FROM surveys ORDER BY created_at DESC")->fetchAll();
$activeSurveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت نظرسنجی - اعلا نیرو</title>
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
        <h2 class="text-center mb-4">مدیریت نظرسنجی</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- فرم ایجاد نظرسنجی جدید -->
        <div class="card mb-4">
            <div class="card-header">ایجاد نظرسنجی جدید</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label for="title" class="form-label">عنوان نظرسنجی</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">توضیحات</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" name="create_survey" class="btn btn-primary">ایجاد نظرسنجی</button>
                </form>
            </div>
        </div>
        
        <!-- لیست نظرسنجی‌ها -->
        <div class="card">
            <div class="card-header">نظرسنجی‌های موجود</div>
            <div class="card-body">
                <?php if (count($surveys) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($surveys as $survey): ?>
                            <div class="list-group-item">
                                <h5><?php echo htmlspecialchars($survey['title']); ?></h5>
                                <p><?php echo htmlspecialchars($survey['description']); ?></p>
                                
                                <!-- فرم اضافه کردن سوال -->
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="survey_id" value="<?php echo (int)$survey['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">سوال جدید:</label>
                                        <input type="text" class="form-control" name="question_text" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">نوع سوال:</label>
                                        <select class="form-select" name="question_type" required>
                                            <option value="yes_no" <?php echo ($survey['id']===$activeSurveyId?'selected':''); ?>>بله/خیر</option>
                                            <option value="rating">امتیازی (1-5)</option>
                                            <option value="descriptive">تشریحی</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_question" class="btn btn-success">اضافه کردن سوال</button>
                                </form>
                                
                                <!-- نمایش سوالات موجود -->
                                <?php
                                $questions = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY created_at");
                                $questions->execute([$survey['id']]);
                                $questions = $questions->fetchAll();
                                ?>
                                
                                <?php if (count($questions) > 0): ?>
                                    <div class="mt-3">
                                        <h6>سوالات این نظرسنجی:</h6>
                                        <ul class="list-group">
                                            <?php foreach ($questions as $question): ?>
                                                <li class="list-group-item">
                                                    <strong><?php echo htmlspecialchars($question['question_text']); ?></strong>
                                                    <span class="badge bg-info">
                                                        <?php
                                                        if ($question['question_type'] == 'yes_no') {
                                                            echo 'بله/خیر';
                                                        } elseif ($question['question_type'] == 'rating') {
                                                            echo 'امتیازی';
                                                        } else {
                                                            echo 'تشریحی';
                                                        }
                                                        ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center">هیچ نظرسنجی ایجاد نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>