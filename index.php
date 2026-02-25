<?php
/**
 * Root index.php — Customer shop entry point
 *
 * How to use:
 *   yoursite.com/?shop=mybakery         → opens shop home
 *   yoursite.com/                       → shows 404 (no shop specified)
 *
 * Deploy tip: Set your domain's document root to this folder,
 * then share the URL as:  yoursite.com/?shop=YOUR_SLUG
 */

session_start();
require 'config/db.php';

$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;

if ($slug) {
    // Validate slug exists and is active
    $stmt = $conn->prepare("SELECT id, slug FROM shops WHERE slug = ? AND is_active = 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();

    if ($shop) {
        $_SESSION['current_shop_slug'] = $shop['slug'];
        header("Location: shop/index.php?shop=" . urlencode($shop['slug']));
        exit;
    }
}

// No shop slug given or slug not found — show a clean 404
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #faf7f2;
            color: #1a1208;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .wrap { text-align: center; max-width: 400px; }
        .icon {
            font-size: 56px;
            margin-bottom: 20px;
            display: block;
            animation: fadeIn 0.5s ease;
        }
        h1 {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 28px;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }
        p {
            font-size: 15px;
            color: rgba(26,18,8,0.5);
            line-height: 1.65;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; } }
    </style>
</head>
<body>
<div class="wrap">
    <span class="icon">🔍</span>
    <h1>Shop not found</h1>
    <p>No shop matches that link. Please check the URL or ask the shop owner for the correct link.</p>
</div>
</body>
</html>