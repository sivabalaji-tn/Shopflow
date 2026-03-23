<?php
require '../config/db.php';

$hash = password_hash('admin123', PASSWORD_DEFAULT);

$conn->query("UPDATE super_admins SET password='$hash' WHERE email='admin@tamizhmart.com'");

echo "Done! Password is now: admin123";
echo "<br>Hash used: " . $hash;

// Also verify it works
$row = $conn->query("SELECT password FROM super_admins WHERE email='admin@tamizhmart.com'")->fetch_assoc();
echo "<br>Verify: " . (password_verify('admin123', $row['password']) ? 'PASSWORD WORKS' : 'STILL BROKEN');
?>