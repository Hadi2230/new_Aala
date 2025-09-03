<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'ادمین';

include 'config.php';
echo "اتصال موفق!";