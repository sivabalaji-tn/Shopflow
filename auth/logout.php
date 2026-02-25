<?php
session_start();
$shop_slug = $_SESSION['current_shop_slug'] ?? null;
session_destroy();
if ($shop_slug) {
    header("Location: ../auth/login.php?shop=" . $shop_slug);
} else {
    header("Location: ../auth/login.php");
}
exit;
