<?php
session_start();
include 'config.php';

echo "<h3>اطلاعات Session:</h3>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "username: " . ($_SESSION['username'] ?? 'Not set') . "<br>";
echo "role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";

echo "<h3>اطلاعات کاربر از دیتابیس:</h3>";
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Role: " . $user['role'] . "<br>";
        echo "Full Name: " . $user['full_name'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        
        // بررسی تطابق
        echo "<h3>تطابق Session و دیتابیس:</h3>";
        $session_role = $_SESSION['role'] ?? '';
        $db_role = $user['role'] ?? '';
        
        echo "Session Role: '$session_role'<br>";
        echo "Database Role: '$db_role'<br>";
        
        if ($session_role === $db_role) {
            echo "<span style='color: green;'>✓ تطابق دارد</span><br>";
        } else {
            echo "<span style='color: red;'>✗ تطابق ندارد!</span><br>";
        }
        
        // بررسی شرط دسترسی
        if ($db_role === 'ادمین') {
            echo "<span style='color: green;'>✓ کاربر ادمین است</span><br>";
        } else {
            echo "<span style='color: red;'>✗ کاربر ادمین نیست!</span><br>";
        }
    } else {
        echo "کاربر در دیتابیس یافت نشد!";
    }
}

echo "<h3>همه کاربران سیستم:</h3>";
$stmt = $pdo->query("SELECT id, username, role, full_name FROM users");
$all_users = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Full Name</th></tr>";
foreach ($all_users as $user) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . $user['full_name'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>