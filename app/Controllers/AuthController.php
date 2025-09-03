<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use function pdo;

final class AuthController extends Controller
{
    public function showLogin(?string $error = null): void
    {
        $this->view('auth/login', ['error' => $error]);
    }

    public function doLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showLogin();
            return;
        }
        if (function_exists('verifyCsrfToken')) {
            verifyCsrfToken();
        }
        $username = clean($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $this->showLogin('نام کاربری یا رمز عبور وارد نشده است');
            return;
        }
        $stmt = pdo()->prepare('SELECT id, username, password, role, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !(bool)$user['is_active'] || !password_verify($password, $user['password'])) {
            if (function_exists('log_action')) {
                log_action('LOGIN_FAILED', 'ورود ناموفق برای کاربر: ' . $username);
            }
            $this->showLogin('نام کاربری یا رمز عبور نادرست است');
            return;
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        pdo()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);
        if (function_exists('log_action')) {
            log_action('LOGIN_SUCCESS', 'ورود موفق');
        }
        header('Location: /index.php');
        exit();
    }
}