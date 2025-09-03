<?php
include 'config.php';

echo "<h3>ساختار جدول users:</h3>";
$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . $column['Field'] . "</td>";
    echo "<td>" . $column['Type'] . "</td>";
    echo "<td>" . $column['Null'] . "</td>";
    echo "<td>" . $column['Key'] . "</td>";
    echo "<td>" . $column['Default'] . "</td>";
    echo "<td>" . $column['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// بررسی وجود ستون role
$has_role_column = false;
foreach ($columns as $column) {
    if ($column['Field'] === 'role') {
        $has_role_column = true;
        break;
    }
}

if ($has_role_column) {
    echo "<p style='color: green;'>✓ ستون role وجود دارد</p>";
} else {
    echo "<p style='color: red;'>✗ ستون role وجود ندارد!</p>";
}
?>