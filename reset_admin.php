<?php
session_start();
require_once __DIR__ . '/config.php';

try {
  $hash = password_hash('admin', PASSWORD_DEFAULT);
  // اگر کاربر admin وجود دارد، آپدیت؛ در غیر اینصورت ایجاد
  $st = $pdo->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
  $st->execute();
  $row = $st->fetch();

  if ($row) {
    $pdo->prepare("UPDATE users SET password=?, role='ادمین', is_active=1 WHERE id=?")
        ->execute([$hash, (int)$row['id']]);
    echo "رمز ادمین به 'admin' ریست شد.";
  } else {
    $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('admin', ?, 'مدیر سیستم', 'ادمین', 1)")
        ->execute([$hash]);
    echo "کاربر ادمین ایجاد شد. رمز: admin";
  }
  echo " | <a href='login.php'>ورود</a>";
} catch (Throwable $e) {
  echo "خطا: " . $e->getMessage();
}