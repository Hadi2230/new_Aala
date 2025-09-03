<?php
// Lightweight bootstrap to initialize autoloading, error handling, sessions, and configuration

declare(strict_types=1);

// Composer autoload if available; fallback to simple PSR-4 autoloader
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/app/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// Timezone
date_default_timezone_set('Asia/Tehran');

// Sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Config and initialize shared services
use App\Core\Config;
use App\Core\Database;
use App\Core\Dotenv;

// Load .env if present
Dotenv::load(__DIR__ . '/.env');

Config::init([
    'DB_HOST' => getenv('DB_HOST') ?: 'localhost:3307',
    'DB_NAME' => getenv('DB_NAME') ?: 'aala_niroo',
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASS' => getenv('DB_PASS') ?: ''
]);

Database::init([
    'host' => Config::get('DB_HOST'),
    'dbname' => Config::get('DB_NAME'),
    'username' => Config::get('DB_USER'),
    'password' => Config::get('DB_PASS')
]);

// Include legacy config to run migrations and keep helper compatibility
require_once __DIR__ . '/config.php';

// Ensure baseline folders exist
if (!is_dir(__DIR__ . '/uploads')) {
    @mkdir(__DIR__ . '/uploads', 0755, true);
}
if (!is_dir(__DIR__ . '/uploads/assets')) {
    @mkdir(__DIR__ . '/uploads/assets', 0755, true);
}

// Provide a global function to access PDO quickly
if (!function_exists('pdo')) {
    function pdo(): PDO {
        return App\Core\Database::pdo();
    }
}

// CSRF token bootstrap
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simple helper for CSRF field
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $token = $_SESSION['csrf_token'] ?? '';
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

// Basic sanitizer
if (!function_exists('clean')) {
    function clean($value) {
        if (is_array($value)) {
            return array_map('clean', $value);
        }
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    }
}

// Auth helpers
if (!function_exists('require_auth')) {
    function require_auth(): void {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit();
        }
    }
}

// Logger bridge to existing table
if (!function_exists('log_action')) {
    function log_action(string $action, string $description = ''): void {
        try {
            $stmt = pdo()->prepare('INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

// Done
return true;
<?php