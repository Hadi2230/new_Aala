<?php
// config.php - نسخه کامل و نهایی

// شروع session اگر شروع نشده
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات امنیتی و لاگ
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// تنظیمات دیتابیس
$host = '127.0.0.1';
$port = '3307';
$dbname = 'aala_niroo';
$username = 'root';
$password = '';

// تنظیمات timezone
date_default_timezone_set('Asia/Tehran');

// اتصال PDO
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci"
    ]);
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] DB CONNECT ERROR: " . $e->getMessage());
    die("<div style='text-align:center;padding:40px;font-family:Tahoma'>
        <h2>خطا در اتصال به سیستم</h2>
        <p>لطفاً بعداً تلاش کنید.</p>
        <p><small>{$e->getMessage()}</small></p>
    </div>");
}

// تولید CSRF token اگر وجود ندارد
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * ایجاد جداول دیتابیس (ایمن برای اجرای مکرر)
 */
function createDatabaseTables(PDO $pdo): void {
    // دایرکتوری‌های ضروری
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0755, true);
    }
    if (!is_dir(__DIR__ . '/uploads')) {
        @mkdir(__DIR__ . '/uploads', 0755, true);
        @mkdir(__DIR__ . '/uploads/installations', 0755, true);
        @mkdir(__DIR__ . '/uploads/assets', 0755, true);
        @mkdir(__DIR__ . '/uploads/filters', 0755, true);
        @file_put_contents(__DIR__ . '/uploads/.htaccess',
            "Order deny,allow\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif)$\">\nAllow from all\n</Files>");
    }

    $tables = [
        // انواع دارایی
        "CREATE TABLE IF NOT EXISTS asset_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // فیلدهای سفارشی دارایی
        "CREATE TABLE IF NOT EXISTS asset_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_id INT,
            field_name VARCHAR(100),
            field_type ENUM('text','number','date','select','file'),
            is_required BOOLEAN DEFAULT false,
            options TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // دارایی‌ها
        "CREATE TABLE IF NOT EXISTS assets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type_id INT NOT NULL,
            serial_number VARCHAR(255) UNIQUE,
            purchase_date DATE,
            status ENUM('فعال','غیرفعال','در حال تعمیر','آماده بهره‌برداری') DEFAULT 'فعال',
            brand VARCHAR(255),
            model VARCHAR(255),
            power_capacity VARCHAR(100),
            engine_type VARCHAR(100),
            consumable_type VARCHAR(100),
            engine_model VARCHAR(255),
            engine_serial VARCHAR(255),
            alternator_model VARCHAR(255),
            alternator_serial VARCHAR(255),
            device_model VARCHAR(255),
            device_serial VARCHAR(255),
            control_panel_model VARCHAR(255),
            breaker_model VARCHAR(255),
            fuel_tank_specs TEXT,
            battery VARCHAR(255),
            battery_charger VARCHAR(255),
            heater VARCHAR(255),
            oil_capacity VARCHAR(255),
            radiator_capacity VARCHAR(255),
            antifreeze VARCHAR(255),
            other_items TEXT,
            workshop_entry_date DATE,
            workshop_exit_date DATE,
            datasheet_link VARCHAR(500),
            engine_manual_link VARCHAR(500),
            alternator_manual_link VARCHAR(500),
            control_panel_manual_link VARCHAR(500),
            description TEXT,
            oil_filter_part VARCHAR(100),
            fuel_filter_part VARCHAR(100),
            water_fuel_filter_part VARCHAR(100),
            air_filter_part VARCHAR(100),
            water_filter_part VARCHAR(100),
            part_description TEXT,
            part_serial VARCHAR(255),
            part_register_date DATE,
            part_notes TEXT,
            supply_method VARCHAR(100),
            location VARCHAR(255),
            quantity INT DEFAULT 0,
            supplier_name VARCHAR(255),
            supplier_contact VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
            INDEX idx_type_id (type_id),
            INDEX idx_status (status),
            INDEX idx_serial (serial_number),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // تصاویر دارایی
        "CREATE TABLE IF NOT EXISTS asset_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_field_name (field_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // مشتریان
        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_type ENUM('حقیقی','حقوقی') NOT NULL DEFAULT 'حقیقی',
            full_name VARCHAR(255),
            phone VARCHAR(20),
            company VARCHAR(255),
            responsible_name VARCHAR(255),
            company_phone VARCHAR(20),
            responsible_phone VARCHAR(20),
            address TEXT NOT NULL,
            operator_name VARCHAR(255) NOT NULL,
            operator_phone VARCHAR(20) NOT NULL,
            notes TEXT,
            name VARCHAR(255) GENERATED ALWAYS AS (
                CASE WHEN customer_type='حقوقی' AND COALESCE(company,'')<>'' THEN company ELSE full_name END
            ) STORED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer_type (customer_type),
            INDEX idx_full_name (full_name),
            INDEX idx_company (company),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // کاربران
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            full_name VARCHAR(255),
            role ENUM('ادمین','کاربر عادی','اپراتور') DEFAULT 'کاربر عادی',
            is_active BOOLEAN DEFAULT true,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // انتساب دارایی به مشتری
        "CREATE TABLE IF NOT EXISTS asset_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            asset_id INT NOT NULL,
            customer_id INT NOT NULL,
            assignment_date DATE,
            notes TEXT,
            assignment_status ENUM('فعال','خاتمه یافته','موقت') DEFAULT 'فعال',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            INDEX idx_asset_id (asset_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_assignment_date (assignment_date),
            INDEX idx_status (assignment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // جزئیات نصب/تحویل
        "CREATE TABLE IF NOT EXISTS assignment_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assignment_id INT NOT NULL,
            installation_date DATE,
            delivery_person VARCHAR(255),
            installation_address TEXT,
            warranty_start_date DATE,
            warranty_end_date DATE,
            warranty_conditions TEXT,
            employer_name VARCHAR(255),
            employer_phone VARCHAR(20),
            recipient_name VARCHAR(255),
            recipient_phone VARCHAR(20),
            installer_name VARCHAR(255),
            installation_status ENUM('نصب شده','در حال نصب','لغو شده') NULL,
            installation_start_date DATE,
            installation_end_date DATE,
            temporary_delivery_date DATE,
            permanent_delivery_date DATE,
            first_service_date DATE,
            post_installation_commitments TEXT,
            notes TEXT,
            installation_photo VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE,
            INDEX idx_assignment_id (assignment_id),
            INDEX idx_installation_date (installation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // کارت گارانتی
        "CREATE TABLE IF NOT EXISTS guaranty_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            issue_date DATE NOT NULL,
            asset_id INT NOT NULL,
            coupler_company VARCHAR(255) NOT NULL,
            customer_id INT NOT NULL,
            guaranty_number VARCHAR(50) NOT NULL UNIQUE,
            alternator_model VARCHAR(255) NULL,
            alternator_serial VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // نظرسنجی‌ها
        "CREATE TABLE IF NOT EXISTS surveys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        "CREATE TABLE IF NOT EXISTS survey_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('yes_no','rating','descriptive') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // پاسخ‌ها
        "CREATE TABLE IF NOT EXISTS survey_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            question_id INT NOT NULL,
            customer_id INT,
            asset_id INT,
            response_text TEXT NOT NULL,
            responded_by INT NOT NULL,
            submission_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
            FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_submission_id (submission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // لاگ سیستم
        "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // یادداشت کاربران
        "CREATE TABLE IF NOT EXISTS user_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            note TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci",

        // گروه‌بندی پاسخ‌ها
        "CREATE TABLE IF NOT EXISTS survey_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            customer_id INT NULL,
            asset_id INT NULL,
            started_by INT NOT NULL,
            status ENUM('in_progress','completed') DEFAULT 'in_progress',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_survey_id (survey_id),
            INDEX idx_customer_id (customer_id),
            INDEX idx_asset_id (asset_id),
            INDEX idx_started_by (started_by),
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci"
    ];

    foreach ($tables as $sql) {
        try { 
            $pdo->exec($sql); 
        } catch (Throwable $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] CREATE TABLE ERROR: " . $e->getMessage());
        }
    }

    // داده اولیه
    try {
        $c = $pdo->query("SELECT COUNT(*) AS c FROM asset_types")->fetch();
        if ((int)$c['c'] === 0) {
            $pdo->exec("INSERT INTO asset_types (name,display_name) VALUES
                ('generator','ژنراتور'),('power_motor','موتور برق'),('consumable','اقلام مصرفی')");
        }
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] INIT DATA ERROR: " . $e->getMessage());
    }

    try {
        $c = $pdo->query("SELECT COUNT(*) AS c FROM users")->fetch();
        if ((int)$c['c'] === 0) {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username,password,full_name,role) VALUES ('admin',?,?, 'ادمین')");
            $stmt->execute([$hash, 'مدیر سیستم']);
        }
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] USER INIT ERROR: " . $e->getMessage());
    }
}

/**
 * مهاجرت سبک برای هم‌ترازی اسکیما موجود با کد
 */
function migrateDatabaseSchema(PDO $pdo): void {
    try {
        $columnExists = function(string $table, string $column) use ($pdo): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            $row = $stmt->fetch();
            return !empty($row) && (int)$row['cnt'] > 0;
        };

        $addColumn = function(string $table, string $ddl) use ($pdo, $columnExists): void {
            if (preg_match('/ADD\\s+(?:COLUMN\\s+)?`?([a-zA-Z0-9_]+)`?/i', $ddl, $m)) {
                $col = $m[1];
                if (!$columnExists($table, $col)) {
                    $pdo->exec("ALTER TABLE {$table} {$ddl}");
                }
            }
        };

        // ستون‌های مورد نیاز
        $addColumn('survey_questions', "ADD COLUMN answer_type ENUM('boolean','rating','text') NULL");
        $addColumn('survey_questions', "ADD COLUMN created_by_admin INT NULL");
        $addColumn('survey_responses', "ADD COLUMN submission_id INT NULL");
        
        try { 
            $pdo->exec("ALTER TABLE survey_responses ADD INDEX idx_submission_id (submission_id)"); 
        } catch (Throwable $e) {}
        
        try { 
            $pdo->exec("ALTER TABLE survey_responses
                ADD CONSTRAINT fk_survey_responses_submission
                FOREIGN KEY (submission_id) REFERENCES survey_submissions(id) ON DELETE CASCADE"); 
        } catch (Throwable $e) {}

        // نرم‌کردن محدودیت NULL برای survey_questions.survey_id
        try { 
            $pdo->exec("ALTER TABLE survey_questions MODIFY survey_id INT NULL"); 
        } catch (Throwable $e) {}

        // ستون‌های assets
        $assetsColumns = [
            "ADD COLUMN device_model VARCHAR(255) NULL",
            "ADD COLUMN device_serial VARCHAR(255) NULL",
            "ADD COLUMN part_description TEXT NULL",
            "ADD COLUMN part_serial VARCHAR(255) NULL",
            "ADD COLUMN part_register_date DATE NULL",
            "ADD COLUMN part_notes TEXT NULL",
            "ADD COLUMN supply_method VARCHAR(100) NULL",
            "ADD COLUMN location VARCHAR(255) NULL",
            "ADD COLUMN quantity INT DEFAULT 0",
            "ADD COLUMN supplier_name VARCHAR(255) NULL",
            "ADD COLUMN supplier_contact VARCHAR(255) NULL"
        ];
        
        foreach ($assetsColumns as $column) {
            $addColumn('assets', $column);
        }

        // users: ایمیل
        $addColumn('users', "ADD COLUMN email VARCHAR(255) NULL");

        // assignment_details: وضعیت نصب
        $addColumn('assignment_details', "ADD COLUMN installation_status ENUM('نصب شده','در حال نصب','لغو شده') NULL");

        // guaranty_cards: فیلدهای جدید
        $addColumn('guaranty_cards', "ADD COLUMN alternator_model VARCHAR(255) NULL");
        $addColumn('guaranty_cards', "ADD COLUMN alternator_serial VARCHAR(255) NULL");
        $addColumn('guaranty_cards', "ADD COLUMN updated_at TIMESTAMP NULL");

        // جدول submissions اگر نبود
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS survey_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                survey_id INT NOT NULL,
                customer_id INT NULL,
                asset_id INT NULL,
                started_by INT NOT NULL,
                status ENUM('in_progress','completed') DEFAULT 'in_progress',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_survey_id (survey_id),
                INDEX idx_customer_id (customer_id),
                INDEX idx_asset_id (asset_id),
                INDEX idx_started_by (started_by),
                FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        } catch (Throwable $e) {}

        // انواع دارایی در صورت نبود
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS asset_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                display_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
            
            $c = $pdo->query("SELECT COUNT(*) AS c FROM asset_types")->fetch();
            if ((int)$c['c'] === 0) {
                $pdo->exec("INSERT INTO asset_types (name,display_name) VALUES
                    ('generator','ژنراتور'),('power_motor','موتور برق'),('consumable','اقلام مصرفی')");
            }
        } catch (Throwable $e) {}
        
    } catch (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] SCHEMA MIGRATION ERROR: " . $e->getMessage());
    }
}

/**
 * توابع کمکی
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function clean($value) {
    return sanitizeInput($value);
}

function validatePhone($phone) {
    return (bool)preg_match('/^[0-9]{10,11}$/', $phone);
}

function validateEmail($email) {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function logAction(PDO $pdo, $action, $description = '') {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, user_agent)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (Throwable $e) {
        error_log("LOG ERROR: " . $e->getMessage());
    }
}

function log_action($action, $description = '') {
    logAction($GLOBALS['pdo'], $action, $description);
}

function uploadFile($file, $target_dir, $allowed_types = ['jpg','jpeg','png','gif','pdf']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('خطا در آپلود فایل');
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types, true)) {
        throw new Exception('نوع فایل مجاز نیست');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('حجم فایل بیش از حد مجاز است');
    }
    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
    $target_file = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . $file_name;
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception('خطا در ذخیره فایل');
    }
    return $target_file;
}

function verifyCsrfToken() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            die('درخواست نامعتبر است - CSRF Token validation failed');
        }
    }
}

function checkPermission($required_role = 'کاربر عادی') {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    $roles = ['کاربر عادی' => 1, 'اپراتور' => 2, 'ادمین' => 3];
    $current = $_SESSION['role'] ?? 'کاربر عادی';
    if (isset($roles[$current], $roles[$required_role]) && $roles[$current] < $roles[$required_role]) {
        die('دسترسی غیرمجاز - شما مجوز دسترسی به این بخش را ندارید');
    }
}

function require_auth($required_role = 'کاربر عادی') {
    checkPermission($required_role);
}

function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field(): void {
    $token = csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function jalaliDate($date = null) {
    if (!$date) { $date = time(); }
    $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
    $y = (int)date('Y', $ts);
    $m = (int)date('m', $ts);
    $d = (int)date('d', $ts);
    [$jy, $jm, $jd] = gregorian_to_jalali($y, $m, $d);
    return $jy . '/' . $jm . '/' . $jd;
}

function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
        + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

function pdo(): PDO {
    return $GLOBALS['pdo'];
}

// اجرای توابع ایجاد جداول و مهاجرت
createDatabaseTables($pdo);
migrateDatabaseSchema($pdo);