<?php
require_once 'config.php';
require_auth('کاربر عادی');

// بررسی وجود نظرسنجی فعال
$active_survey = null;
$questions = [];

try {
    $stmt = $pdo->prepare("
        SELECT s.*, COUNT(sq.id) as question_count 
        FROM surveys s 
        LEFT JOIN survey_questions sq ON s.id = sq.survey_id 
        WHERE s.id = (SELECT MAX(id) FROM surveys WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        GROUP BY s.id
    ");
    $stmt->execute();
    $active_survey = $stmt->fetch();
    
    if ($active_survey) {
        $stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id");
        $stmt->execute([$active_survey['id']]);
        $questions = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Survey load error: " . $e->getMessage());
    // اگر خطای مربوط به فیلد answer_type است، کوئری را تغییر دهید
    try {
        if ($active_survey) {
            $stmt = $pdo->prepare("SELECT id, question_text, question_type FROM survey_questions WHERE survey_id = ? ORDER BY id");
            $stmt->execute([$active_survey['id']]);
            $questions = $stmt->fetchAll();
        }
    } catch (PDOException $e2) {
        error_log("Alternative survey load error: " . $e2->getMessage());
    }
}

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    verifyCsrfToken();
    
    try {
        $pdo->beginTransaction();
        
        // ایجاد یک سابقه جدید برای پاسخ‌ها
        $stmt = $pdo->prepare("
            INSERT INTO survey_submissions (survey_id, customer_id, asset_id, started_by, status) 
            VALUES (?, ?, ?, ?, 'completed')
        ");
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $asset_id = !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
        $stmt->execute([$active_survey['id'], $customer_id, $asset_id, $_SESSION['user_id']]);
        $submission_id = $pdo->lastInsertId();
        
        // ذخیره پاسخ‌ها
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $question_id = (int)str_replace('question_', '', $key);
                
                $stmt = $pdo->prepare("
                    INSERT INTO survey_responses 
                    (survey_id, question_id, customer_id, asset_id, response_text, responded_by, submission_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $response_text = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : trim($value);
                $stmt->execute([
                    $active_survey['id'], 
                    $question_id, 
                    $customer_id, 
                    $asset_id, 
                    $response_text, 
                    $_SESSION['user_id'],
                    $submission_id
                ]);
            }
        }
        
        $pdo->commit();
        
        // ثبت در لاگ سیستم
        log_action('survey_submission', 'ارسال نظرسنجی با شناسه: ' . $submission_id);
        
        // نمایش پیام موفقیت
        $_SESSION['success_message'] = "نظرسنجی با موفقیت ثبت شد. از مشارکت شما سپاسگزاریم!";
        header("Location: survey.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Survey submission error: " . $e->getMessage());
        $_SESSION['error_message'] = "خطا در ثبت نظرسنجی. لطفاً مجدداً تلاش کنید.";
    }
}

// دریافت لیست مشتریان و دارایی‌ها برای dropdownها
$customers = [];
$assets = [];

try {
    $stmt = $pdo->query("SELECT id, name FROM customers ORDER BY name");
    $customers = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name, serial_number FROM assets WHERE status = 'فعال' ORDER BY name");
    $assets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Data load error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظرسنجی - اعلا نیرو</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }
        
        body {
            font-family: Vazirmatn, sans-serif;
            background-color: #f5f7f9;
            padding-top: 80px;
            color: #333;
        }
        
        .survey-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .survey-header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .survey-body {
            padding: 30px;
        }
        
        .question-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .question-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .question-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            text-align: center;
            line-height: 30px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .rating-stars {
            display: flex;
            justify-content: center;
            margin: 15px 0;
            direction: ltr;
        }
        
        .rating-stars input {
            display: none;
        }
        
        .rating-stars label {
            cursor: pointer;
            width: 40px;
            height: 40px;
            margin: 0 2px;
            color: #ddd;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        
        .rating-stars label:hover,
        .rating-stars input:checked ~ label {
            color: #f39c12;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            padding: 12px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(0,0,0,0.3);
        }
        
        .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .alert-survey {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .no-survey {
            text-align: center;
            padding: 40px 20px;
        }
        
        .no-survey i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .character-counter {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: left;
        }
        
        @media (max-width: 768px) {
            .survey-body {
                padding: 20px;
            }
            
            .rating-stars label {
                width: 35px;
                height: 35px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mb-5">
        <div class="survey-container">
            <div class="survey-header">
                <h2><i class="bi bi-clipboard-check"></i> سامانه نظرسنجی</h2>
                <p class="mb-0">شرکت اعلا نیرو</p>
            </div>
            
            <div class="survey-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-survey">
                        <i class="bi bi-check-circle-fill"></i> 
                        <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-survey">
                        <i class="bi bi-exclamation-circle-fill"></i> 
                        <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($active_survey): ?>
                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($active_survey['title']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($active_survey['description']); ?></p>
                        <p class="text-muted"><small>تعداد سوالات: <?php echo $active_survey['question_count']; ?> سوال</small></p>
                    </div>
                    
                    <form method="POST" id="surveyForm">
                        <?php csrf_field(); ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label">مشتری (اختیاری)</label>
                                <select class="form-select" id="customer_id" name="customer_id">
                                    <option value="">انتخاب مشتری...</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="asset_id" class="form-label">دارایی (اختیاری)</label>
                                <select class="form-select" id="asset_id" name="asset_id">
                                    <option value="">انتخاب دارایی...</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['id']; ?>">
                                            <?php echo htmlspecialchars($asset['name'] . ' - ' . $asset['serial_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="questions-container">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-card">
                                    <h5>
                                        <span class="question-number"><?php echo $index + 1; ?></span>
                                        <?php echo htmlspecialchars($question['question_text']); ?>
                                    </h5>
                                    
                                    <div class="question-body mt-3">
                                        <?php if ($question['question_type'] == 'yes_no'): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" 
                                                    name="question_<?php echo $question['id']; ?>" 
                                                    id="q<?php echo $question['id']; ?>_yes" 
                                                    value="بله" required>
                                                <label class="form-check-label" for="q<?php echo $question['id']; ?>_yes">بله</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" 
                                                    name="question_<?php echo $question['id']; ?>" 
                                                    id="q<?php echo $question['id']; ?>_no" 
                                                    value="خیر">
                                                <label class="form-check-label" for="q<?php echo $question['id']; ?>_no">خیر</label>
                                            </div>
                                            
                                        <?php elseif ($question['question_type'] == 'rating'): ?>
                                            <div class="rating-stars">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" 
                                                        name="question_<?php echo $question['id']; ?>" 
                                                        id="q<?php echo $question['id']; ?>_star<?php echo $i; ?>" 
                                                        value="<?php echo $i; ?>" 
                                                        <?php if ($i == 5) echo 'required'; ?>>
                                                    <label for="q<?php echo $question['id']; ?>_star<?php echo $i; ?>">
                                                        <i class="bi bi-star-fill"></i>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="text-center mt-2">
                                                <small class="text-muted">(1: بسیار ضعیف - 5: عالی)</small>
                                            </div>
                                            
                                        <?php else: ?>
                                            <textarea class="form-control" 
                                                name="question_<?php echo $question['id']; ?>" 
                                                rows="3" 
                                                placeholder="پاسخ خود را وارد کنید..." 
                                                oninput="countChars(this, 'charCounter<?php echo $question['id']; ?>')"
                                                required></textarea>
                                            <div class="character-counter">
                                                <span id="charCounter<?php echo $question['id']; ?>">0</span> کاراکتر
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" name="submit_survey" class="btn btn-submit">
                                <i class="bi bi-send-fill"></i> ارسال نظرسنجی
                            </button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <div class="no-survey">
                        <i class="bi bi-clipboard-x"></i>
                        <h4>نظرسنجی فعالی موجود نیست</h4>
                        <p class="text-muted">در حال حاضر هیچ نظرسنجی فعالی برای شرکت وجود ندارد.</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">
                            <i class="bi bi-house-door"></i> بازگشت به داشبورد
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function countChars(textarea, counterId) {
            const counter = document.getElementById(counterId);
            counter.textContent = textarea.value.length;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // افزودن اعتبارسنجی فرم
            const form = document.getElementById('surveyForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    
                    // بررسی پاسخ به همه سوالات
                    const requiredInputs = form.querySelectorAll('input[required], textarea[required]');
                    requiredInputs.forEach(input => {
                        if (!input.value) {
                            valid = false;
                            input.classList.add('is-invalid');
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('لطفاً به تمام سوالات ضروری پاسخ دهید.');
                    }
                });
            }
            
            // نمایش tooltip برای ستاره‌های امتیازدهی
            const stars = document.querySelectorAll('.rating-stars label');
            stars.forEach(star => {
                star.setAttribute('title', star.previousElementSibling.value + ' ستاره');
            });
        });
    </script>
</body>
</html>