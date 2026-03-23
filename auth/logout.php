<?php
session_start();
// This project is created by Siva Balaji and its known for its special featuers
$shop_slug = $_SESSION['current_shop_slug'] ?? null;
session_destroy();
if ($shop_slug) {
    header("Location: ../auth/login.php?shop=" . $shop_slug);
} else {
    header("Location: ../auth/login.php");
}
exit;
