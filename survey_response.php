<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'config.php';

$customer_id = $_GET['customer_id'] ?? null;
$asset_id = $_GET['asset_id'] ?? null;
$customer = null;
$asset = null;
$surveys = [];

// دریافت اطلاعات مشتری یا دستگاه
if ($customer_id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
} elseif ($asset_id) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
    
    // دریافت اطلاعات مشتری مرتبط با دستگاه
    if ($asset) {
        $stmt = $pdo->prepare("SELECT c.* FROM customers c INNER JOIN asset_assignments aa ON c.id = aa.customer_id WHERE aa.asset_id = ?");
        $stmt->execute([$asset_id]);
        $customer = $stmt->fetch();
    }
}

// اگر مشتری یا دستگاه یافت نشد
if (!$customer && !$asset) {
    die("مشتری یا دستگاه یافت نشد.");
}

// دریافت نظرسنجی‌های موجود
$surveys = $pdo->query("SELECT * FROM surveys ORDER BY created_at DESC")->fetchAll();

// ثبت پاسخ‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    $survey_id = $_POST['survey_id'];
    
    // دریافت سوالات این نظرسنجی
    $stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ?");
    $stmt->execute([$survey_id]);
    $questions = $stmt->fetchAll();
    
    // ثبت پاسخ هر سوال
    foreach ($questions as $question) {
        $response = $_POST['question_' . $question['id']] ?? '';
        
        if (!empty($response)) {
            $stmt = $pdo->prepare("INSERT INTO survey_responses (survey_id, question_id, customer_id, asset_id, response_text, responded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $survey_id,
                $question['id'],
                $customer ? $customer['id'] : null,
                $asset ? $asset['id'] : null,
                $response,
                $_SESSION['user_id']
            ]);
        }
    }
    
    $success = "نظرسنجی با موفقیت ثبت شد.";
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پاسخ به نظرسنجی - اعلا نیرو</title>
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
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-start;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
            margin: 0 2px;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2 class="text-center mb-4">پاسخ به نظرسنجی</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- اطلاعات مشتری/دستگاه -->
        <div class="card mb-4">
            <div class="card-header">اطلاعات مشتری/دستگاه</div>
            <div class="card-body">
                <?php if ($customer): ?>
                    <p><strong>مشتری:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
                    <p><strong>تلفن:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
                    <p><strong>آدرس:</strong> <?php echo htmlspecialchars($customer['address']); ?></p>
                <?php endif; ?>
                
                <?php if ($asset): ?>
                    <p><strong>دستگاه:</strong> <?php echo htmlspecialchars($asset['name']); ?></p>
                    <p><strong>شماره سریال:</strong> <?php echo htmlspecialchars($asset['serial_number']); ?></p>
                    <p><strong>مدل:</strong> <?php echo htmlspecialchars($asset['model']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- انتخاب نظرسنجی -->
        <div class="card mb-4">
            <div class="card-header">انتخاب نظرسنجی</div>
            <div class="card-body">
                <form method="POST" id="surveyForm">
                    <div class="mb-3">
                        <label for="survey_id" class="form-label">نظرسنجی</label>
                        <select class="form-select" id="survey_id" name="survey_id" required>
                            <option value="">-- انتخاب نظرسنجی --</option>
                            <?php foreach ($surveys as $survey): ?>
                                <option value="<?php echo $survey['id']; ?>"><?php echo htmlspecialchars($survey['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="questionsContainer"></div>
                    
                    <button type="submit" name="submit_survey" class="btn btn-primary mt-3">ثبت نظرسنجی</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('survey_id').addEventListener('change', function() {
            const surveyId = this.value;
            const questionsContainer = document.getElementById('questionsContainer');
            
            if (!surveyId) {
                questionsContainer.innerHTML = '';
                return;
            }
            
            // دریافت سوالات نظرسنجی انتخاب شده
            fetch('get_survey_questions.php?survey_id=' + surveyId)
                .then(response => response.json())
                .then(questions => {
                    let html = '';
                    
                    questions.forEach(question => {
                        html += `<div class="card mb-3">`;
                        html += `<div class="card-header">${question.question_text}</div>`;
                        html += `<div class="card-body">`;
                        
                        if (question.question_type === 'yes_no') {
                            html += `
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="question_${question.id}" id="question_${question.id}_yes" value="بله" required>
                                    <label class="form-check-label" for="question_${question.id}_yes">بله</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="question_${question.id}" id="question_${question.id}_no" value="خیر">
                                    <label class="form-check-label" for="question_${question.id}_no">خیر</label>
                                </div>
                            `;
                        } else if (question.question_type === 'rating') {
                            html += `
                                <div class="star-rating">
                                    <input type="radio" id="star${question.id}_5" name="question_${question.id}" value="5" required>
                                    <label for="star${question.id}_5"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star${question.id}_4" name="question_${question.id}" value="4">
                                    <label for="star${question.id}_4"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star${question.id}_3" name="question_${question.id}" value="3">
                                    <label for="star${question.id}_3"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star${question.id}_2" name="question_${question.id}" value="2">
                                    <label for="star${question.id}_2"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star${question.id}_1" name="question_${question.id}" value="1">
                                    <label for="star${question.id}_1"><i class="fas fa-star"></i></label>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">1 = بسیار ضعیف, 5 = عالی</small>
                                </div>
                            `;
                        } else {
                            html += `<textarea class="form-control" name="question_${question.id}" rows="3" required></textarea>`;
                        }
                        
                        html += `</div></div>`;
                    });
                    
                    questionsContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    questionsContainer.innerHTML = '<div class="alert alert-danger">خطا در دریافت سوالات</div>';
                });
        });
    </script>
</body>
</html>