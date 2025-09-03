<?php
include 'config.php';
echo "اتصال به دیتابیس موفق بود!";
echo "<br>Session: " . (isset($_SESSION['csrf_token']) ? 'فعال' : 'غیرفعال');