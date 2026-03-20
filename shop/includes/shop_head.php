<?php
// shop/includes/shop_head.php
// Requires: $shop array, $conn, session started

// ── Maintenance mode check ────────────────────────────────────
$maint_row = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='maintenance_mode'")->fetch_row();
if ($maint_row && $maint_row[0] === '1') {
    $maint_msg_row = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='maintenance_message'")->fetch_row();
    $maint_msg = $maint_msg_row ? $maint_msg_row[0] : 'We are under maintenance. Back soon!';
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Under Maintenance — <?= htmlspecialchars($shop['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:<?= htmlspecialchars($shop['theme_bg']??'#faf7f2') ?>;color:<?= htmlspecialchars($shop['theme_text']??'#1a1208') ?>;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;}
    .icon{font-size:56px;margin-bottom:20px;animation:spin 4s linear infinite;display:inline-block;}
    @keyframes spin{0%,100%{transform:rotate(-8deg)}50%{transform:rotate(8deg)}}
    h1{font-family:'Syne',sans-serif;font-weight:800;font-size:28px;margin-bottom:10px;}
    p{font-size:15px;opacity:.55;line-height:1.65;max-width:360px;margin:0 auto;}
    </style>
    </head>
    <body>
    <div>
        <div class="icon">🔧</div>
        <h1><?= htmlspecialchars($shop['name']) ?></h1>
        <p><?= htmlspecialchars($maint_msg) ?></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ── Shop suspended check ──────────────────────────────────────
if (!empty($shop['is_suspended'])) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Shop Unavailable</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'DM Sans',sans-serif;background:#faf7f2;color:#1a1208;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;}h1{font-family:'Syne',sans-serif;font-weight:800;font-size:26px;margin:16px 0 10px;}p{font-size:14px;opacity:.5;max-width:320px;margin:0 auto;}</style>
    </head>
    <body>
    <div>
        <div style="font-size:52px;margin-bottom:4px;">🚫</div>
        <h1>Shop Unavailable</h1>
        <p>This shop is currently unavailable. Please check back later.</p>
    </div>
    </body>
    </html>
    <?php
    exit;
}


// Auth guard helper - soft (redirects to login if not logged in)
function requireCustomerLogin($shop) {
    if (!isset($_SESSION['user_id'])) {
        $login_url = "../auth/login.php?shop=" . urlencode($shop['slug']);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($login_url) . '">';
        echo '</head><body></body></html>';
        exit;
    }
}

// Cart count
$cart_count = 0;
if (isset($_SESSION['user_id'], $_SESSION['shop_id'])) {
    $uid = $_SESSION['user_id'];
    $sid = $_SESSION['shop_id'];
    $cc  = $conn->query("SELECT COALESCE(SUM(quantity),0) as c FROM cart WHERE user_id=$uid AND shop_id=$sid");
    $cart_count = (int)$cc->fetch_assoc()['c'];
}

// Active popup
$popup = null;
if (isset($shop['id'])) {
    $today = date('Y-m-d');
    $ps = $conn->prepare("SELECT * FROM popups WHERE shop_id=? AND is_active=1 AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?) ORDER BY id DESC LIMIT 1");
    $ps->bind_param("iss", $shop['id'], $today, $today);
    $ps->execute();
    $popup = $ps->get_result()->fetch_assoc();
}

$primary   = $shop['theme_primary']   ?? '#c8a97e';
$secondary = $shop['theme_secondary'] ?? '#8b6428';
$bg        = $shop['theme_bg']        ?? '#faf7f2';
$text_col  = $shop['theme_text']      ?? '#1a1208';
$font      = $shop['theme_font']      ?? 'Poppins';
$slug      = $shop['slug'] ?? '';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title).' — ' : '' ?><?= htmlspecialchars($shop['name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($shop['description'] ?? 'Shop online at '.$shop['name']) ?>">
    <!-- PWA -->
    <meta name="theme-color" content="<?= htmlspecialchars($primary) ?>">
    <link rel="manifest" href="../manifest.php?shop=<?= $slug ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($font) ?>:wght@300;400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:   <?= htmlspecialchars($primary) ?>;
            --secondary: <?= htmlspecialchars($secondary) ?>;
            --bg:        <?= htmlspecialchars($bg) ?>;
            --text:      <?= htmlspecialchars($text_col) ?>;
            --primary-light: color-mix(in srgb, var(--primary) 12%, transparent);
            --primary-mid:   color-mix(in srgb, var(--primary) 20%, transparent);
            --primary-glow:  color-mix(in srgb, var(--primary) 30%, transparent);
            --text-muted:    color-mix(in srgb, var(--text) 50%, transparent);
            --text-faint:    color-mix(in srgb, var(--text) 25%, transparent);
            --border:        color-mix(in srgb, var(--text) 10%, transparent);
            --border-mid:    color-mix(in srgb, var(--text) 16%, transparent);
            --card-bg:       color-mix(in srgb, var(--bg) 96%, var(--text));
            --card-shadow:   0 4px 24px color-mix(in srgb, var(--text) 8%, transparent);
            --card-shadow-hover: 0 12px 40px color-mix(in srgb, var(--text) 14%, transparent);
            --navbar-h: 68px;
            --radius: 16px;
            --radius-sm: 10px;
            --radius-lg: 22px;
            --transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
        }

        html { scroll-behavior: smooth; }
        body {
            font-family: '<?= htmlspecialchars($font) ?>', 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-top: var(--navbar-h);
        }

        /* ─── Scrollbar ─── */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--primary-mid); border-radius: 99px; }

        /* ─── Announcement Bar ─── */
        .announcement-bar {
            background: var(--primary);
            color: #fff;
            text-align: center;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 201;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        body.has-announcement { padding-top: calc(var(--navbar-h) + 36px); }
        body.has-announcement .shop-navbar { top: 36px; }

        /* ─── Navbar ─── */
        .shop-navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--navbar-h);
            z-index: 200;
            background: color-mix(in srgb, var(--bg) 90%, transparent);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            transition: box-shadow 0.3s;
        }
        .shop-navbar.scrolled {
            box-shadow: 0 4px 24px color-mix(in srgb, var(--text) 8%, transparent);
        }

        .navbar-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Logo / Brand */
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .nav-brand-logo {
            width: 38px; height: 38px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--primary-light);
            display: flex; align-items: center; justify-content: center;
        }
        .nav-brand-logo img { width: 100%; height: 100%; object-fit: cover; }
        .nav-brand-logo i { color: var(--primary); font-size: 20px; }
        .nav-brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 18px;
            color: var(--text);
            letter-spacing: -0.4px;
        }

        /* Desktop nav links */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            flex: 1;
        }
        .nav-link-item {
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }
        .nav-link-item:hover { color: var(--text); background: var(--primary-light); }
        .nav-link-item.active { color: var(--primary); background: var(--primary-light); font-weight: 600; }

        /* Search bar */
        .nav-search {
            flex: 1;
            max-width: 340px;
            position: relative;
        }
        .nav-search input {
            width: 100%;
            padding: 9px 16px 9px 40px;
            border-radius: 99px;
            border: 1.5px solid var(--border);
            background: var(--card-bg);
            color: var(--text);
            font-family: inherit;
            font-size: 13.5px;
            outline: none;
            transition: var(--transition);
        }
        .nav-search input::placeholder { color: var(--text-muted); }
        .nav-search input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .nav-search i {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        /* Nav right actions */
        .nav-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

        .nav-btn {
            display: flex; align-items: center; justify-content: center;
            width: 40px; height: 40px;
            border-radius: 10px;
            background: transparent;
            border: 1.5px solid var(--border);
            color: var(--text-muted);
            font-size: 17px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
        }
        .nav-btn:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }

        .cart-badge {
            position: absolute;
            top: -5px; right: -5px;
            min-width: 18px; height: 18px;
            background: var(--primary);
            color: #fff;
            font-size: 10px; font-weight: 700;
            border-radius: 99px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--bg);
        }

        .nav-user-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 6px 14px 6px 8px;
            border-radius: 99px;
            background: var(--primary-light);
            border: 1.5px solid var(--border-mid);
            color: var(--text);
            font-size: 13.5px; font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
        }
        .nav-user-btn:hover { background: var(--primary-mid); border-color: var(--primary); color: var(--text); }
        .nav-user-avatar {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 11px;
            display: flex; align-items: center; justify-content: center;
        }

        /* User dropdown */
        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 200px;
            background: var(--bg);
            border: 1px solid var(--border-mid);
            border-radius: var(--radius);
            box-shadow: var(--card-shadow-hover);
            padding: 8px;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-8px);
            transition: all 0.2s;
            z-index: 300;
        }
        .nav-user-btn:hover .user-dropdown,
        .nav-user-btn:focus-within .user-dropdown { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-item-custom {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13.5px;
            transition: var(--transition);
        }
        .dropdown-item-custom:hover { background: var(--primary-light); color: var(--primary); }
        .dropdown-item-custom i { font-size: 15px; }
        .dropdown-divider { height: 1px; background: var(--border); margin: 6px 0; }

        /* Mobile hamburger */
        .mobile-nav-btn {
            display: none;
            background: none; border: 1.5px solid var(--border);
            color: var(--text); border-radius: 10px;
            padding: 8px 10px; cursor: pointer; font-size: 18px;
            transition: var(--transition);
        }
        .mobile-nav-btn:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }

        /* Mobile menu */
        .mobile-menu {
            position: fixed;
            inset: 0;
            z-index: 250;
            display: none;
        }
        .mobile-menu.open { display: block; }
        .mobile-menu-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
        }
        .mobile-menu-panel {
            position: absolute;
            top: 0; right: 0; bottom: 0;
            width: 280px;
            background: var(--bg);
            border-left: 1px solid var(--border-mid);
            padding: 24px 20px;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .mobile-menu.open .mobile-menu-panel { transform: translateX(0); }
        .mobile-menu-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 28px;
        }
        .mobile-menu-close {
            background: none; border: none;
            color: var(--text-muted); font-size: 20px; cursor: pointer;
            padding: 4px; transition: color 0.2s;
        }
        .mobile-menu-close:hover { color: var(--text); }
        .mobile-nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            text-decoration: none;
            font-size: 15px; font-weight: 500;
            transition: var(--transition);
            margin-bottom: 4px;
        }
        .mobile-nav-link:hover, .mobile-nav-link.active {
            background: var(--primary-light);
            color: var(--primary);
        }
        .mobile-nav-link i { font-size: 18px; flex-shrink: 0; }

        /* ─── Page container ─── */
        .shop-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ─── Buttons ─── */
        .btn-shop-primary {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px;
            background: var(--primary);
            border: none;
            border-radius: 99px;
            color: #fff;
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 16px var(--primary-glow);
            white-space: nowrap;
        }
        .btn-shop-primary:hover { filter: brightness(1.1); transform: translateY(-2px); color: #fff; box-shadow: 0 8px 24px var(--primary-glow); }
        .btn-shop-primary:active { transform: translateY(0); }

        .btn-shop-outline {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px;
            background: transparent;
            border: 1.5px solid var(--border-mid);
            border-radius: 99px;
            color: var(--text);
            font-family: inherit; font-size: 14px; font-weight: 500;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
        }
        .btn-shop-outline:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }

        .btn-shop-ghost {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: transparent;
            border: none;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            font-family: inherit; font-size: 13.5px; font-weight: 500;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
        }
        .btn-shop-ghost:hover { color: var(--primary); background: var(--primary-light); }

        /* ─── Cards ─── */
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--border-mid);
        }
        .product-card-img {
            width: 100%; aspect-ratio: 1;
            object-fit: cover;
            background: var(--primary-light);
            display: flex; align-items: center; justify-content: center;
        }
        .product-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .product-card-img i { font-size: 42px; color: var(--primary-glow); }
        .product-card-body { padding: 16px; }
        .product-card-name {
            font-weight: 600; font-size: 14.5px;
            line-height: 1.3;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .product-card-cat {
            font-size: 12px; color: var(--primary);
            font-weight: 500; margin-bottom: 10px;
        }
        .product-card-price {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: 17px;
            color: var(--text);
        }
        .product-card-price-orig {
            font-size: 12.5px; color: var(--text-muted);
            text-decoration: line-through; font-weight: 400;
            font-family: inherit;
        }
        .product-card-discount {
            background: color-mix(in srgb, #22c55e 15%, transparent);
            color: #16a34a;
            font-size: 11px; font-weight: 700;
            padding: 2px 7px; border-radius: 99px;
        }
        .product-card-footer {
            padding: 0 16px 16px;
            display: flex; gap: 8px;
        }
        .out-of-stock-overlay {
            position: absolute; inset: 0;
            background: color-mix(in srgb, var(--bg) 75%, transparent);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            backdrop-filter: blur(2px);
        }

        /* ─── Inputs ─── */
        .input-shop {
            width: 100%;
            padding: 12px 16px;
            background: var(--card-bg);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: var(--transition);
        }
        .input-shop::placeholder { color: var(--text-muted); }
        .input-shop:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        textarea.input-shop { resize: vertical; min-height: 90px; }
        select.input-shop option { background: var(--bg); color: var(--text); }

        /* ─── Status badges ─── */
        .order-status {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 11px; border-radius: 99px;
            font-size: 12px; font-weight: 600;
        }
        .order-status::before { content:''; width:6px; height:6px; border-radius:50%; }
        .status-pending    { background:rgba(251,191,36,0.12); color:#d97706; }
        .status-pending::before { background:#d97706; }
        .status-processing { background:rgba(96,165,250,0.12); color:#2563eb; }
        .status-processing::before { background:#2563eb; }
        .status-out_for_delivery { background:rgba(139,92,246,0.12); color:#7c3aed; }
        .status-out_for_delivery::before { background:#7c3aed; }
        .status-delivered  { background:rgba(34,197,94,0.12); color:#16a34a; }
        .status-delivered::before { background:#16a34a; }
        .status-cancelled  { background:rgba(239,68,68,0.12); color:#dc2626; }
        .status-cancelled::before { background:#dc2626; }

        /* ─── Toast ─── */
        .toast-container {
            position: fixed;
            bottom: 24px; right: 24px;
            z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
        }
        .toast-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px;
            background: var(--text);
            color: var(--bg);
            border-radius: var(--radius);
            font-size: 14px; font-weight: 500;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            animation: toastIn 0.3s ease, toastOut 0.3s ease 2.7s forwards;
            min-width: 260px;
            max-width: 340px;
        }
        .toast-item i { font-size: 18px; flex-shrink: 0; }
        @keyframes toastIn  { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
        @keyframes toastOut { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(20px); } }

        /* ─── Footer ─── */
        .shop-footer {
            background: color-mix(in srgb, var(--text) 5%, var(--bg));
            border-top: 1px solid var(--border);
            margin-top: 80px;
            padding: 40px 0 24px;
        }
        .footer-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 24px;
        }
        .footer-brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800; font-size: 20px; margin-bottom: 8px;
        }
        .footer-desc { font-size: 13.5px; color: var(--text-muted); max-width: 280px; line-height: 1.6; }
        .footer-divider { border-color: var(--border); margin: 28px 0 20px; }
        .footer-bottom {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
            font-size: 12.5px; color: var(--text-muted);
        }
        .footer-powered {
            font-size: 12px;
            color: var(--text-faint);
        }

        /* ─── Mobile bottom nav ─── */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: color-mix(in srgb, var(--bg) 92%, transparent);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            z-index: 150;
            padding: 8px 0 max(8px, env(safe-area-inset-bottom));
        }
        .mobile-bottom-nav-inner {
            display: flex;
            justify-content: space-around;
        }
        .mobile-nav-tab {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            padding: 6px 12px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 10px; font-weight: 500;
            transition: color 0.2s;
            position: relative;
            border-radius: 10px;
            min-width: 56px;
        }
        .mobile-nav-tab i { font-size: 20px; }
        .mobile-nav-tab.active { color: var(--primary); }
        .mobile-nav-tab.active i { transform: scale(1.1); }
        .mobile-cart-badge {
            position: absolute; top: 4px; right: 8px;
            min-width: 16px; height: 16px;
            background: var(--primary); color: #fff;
            font-size: 9px; font-weight: 700;
            border-radius: 99px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 3px;
        }
        body.has-mobile-nav { padding-bottom: 70px; }

        /* ─── Animations ─── */
        .fade-up {
            opacity: 0; transform: translateY(20px);
            animation: fadeUp 0.5s ease forwards;
        }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
        .d1{animation-delay:.05s} .d2{animation-delay:.1s} .d3{animation-delay:.15s}
        .d4{animation-delay:.2s}  .d5{animation-delay:.25s} .d6{animation-delay:.3s}
        .d7{animation-delay:.35s} .d8{animation-delay:.4s}

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .nav-links, .nav-search { display: none; }
            .mobile-nav-btn { display: flex; }
            .mobile-bottom-nav { display: block; }
            body { padding-bottom: 70px; }
            .nav-user-btn .user-name { display: none; }
            .shop-container { padding: 0 16px; }
        }
        @media (max-width: 480px) {
            :root { --navbar-h: 60px; }
        }
    </style>
</head>
<body class="<?= ($shop['announcement_active'] && $shop['announcement']) ? 'has-announcement' : '' ?>">

<?php if ($shop['announcement_active'] && $shop['announcement']): ?>
<div class="announcement-bar">
    <i class="bi bi-megaphone-fill"></i>
    <?= htmlspecialchars($shop['announcement']) ?>
    <button onclick="this.parentElement.style.display='none';document.body.classList.remove('has-announcement');"
        style="background:none;border:none;color:rgba(255,255,255,0.7);margin-left:8px;cursor:pointer;font-size:14px;padding:0 4px;">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<!-- Navbar -->
<nav class="shop-navbar" id="shopNavbar">
    <div class="navbar-inner">
        <!-- Brand -->
        <a href="index.php?shop=<?= $slug ?>" class="nav-brand">
            <div class="nav-brand-logo">
                <?php if ($shop['logo']): ?>
                <img src="../assets/uploads/logos/<?= htmlspecialchars($shop['logo']) ?>" alt="logo">
                <?php else: ?>
                <i class="bi bi-bag-heart-fill"></i>
                <?php endif; ?>
            </div>
            <span class="nav-brand-name"><?= htmlspecialchars($shop['name']) ?></span>
        </a>

        <!-- Desktop Search -->
        <div class="nav-search">
            <form action="index.php" method="GET">
                <input type="hidden" name="shop" value="<?= $slug ?>">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </form>
        </div>

        <!-- Desktop Nav Links -->
        <div class="nav-links">
            <a href="index.php?shop=<?= $slug ?>" class="nav-link-item <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="products.php?shop=<?= $slug ?>" class="nav-link-item <?= $current_page === 'products.php' ? 'active' : '' ?>">Products</a>
            <a href="orders.php?shop=<?= $slug ?>" class="nav-link-item <?= $current_page === 'orders.php' ? 'active' : '' ?>">My Orders</a>
        </div>

        <!-- Actions -->
        <div class="nav-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="cart.php?shop=<?= $slug ?>" class="nav-btn" title="Cart">
                <i class="bi bi-bag"></i>
                <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <div class="nav-user-btn" tabindex="0">
                <div class="nav-user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
                <span class="user-name"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?></span>
                <i class="bi bi-chevron-down" style="font-size:11px;color:var(--text-muted);"></i>
                <div class="user-dropdown">
                    <a href="profile.php?shop=<?= $slug ?>" class="dropdown-item-custom"><i class="bi bi-person"></i> My Profile</a>
                    <a href="orders.php?shop=<?= $slug ?>" class="dropdown-item-custom"><i class="bi bi-bag-check"></i> My Orders</a>
                    <div class="dropdown-divider"></div>
                    <a href="../auth/logout.php" class="dropdown-item-custom" style="color:#dc2626;"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
                </div>
            </div>
            <?php else: ?>
            <a href="../auth/login.php?shop=<?= $slug ?>" class="btn-shop-outline" style="font-size:13.5px;padding:8px 18px;">
                <i class="bi bi-person"></i> Sign In
            </a>
            <?php endif; ?>
            <button class="mobile-nav-btn" onclick="toggleMobileMenu()"><i class="bi bi-list"></i></button>
        </div>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
    <div class="mobile-menu-panel">
        <div class="mobile-menu-header">
            <span style="font-family:'Syne',sans-serif;font-weight:800;font-size:16px;"><?= htmlspecialchars($shop['name']) ?></span>
            <button class="mobile-menu-close" onclick="toggleMobileMenu()"><i class="bi bi-x-lg"></i></button>
        </div>
        <!-- Mobile Search -->
        <form action="index.php" method="GET" style="margin-bottom:20px;">
            <input type="hidden" name="shop" value="<?= $slug ?>">
            <div style="position:relative;">
                <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;"></i>
                <input type="text" name="q" placeholder="Search products..." class="input-shop" style="padding-left:40px;border-radius:99px;">
            </div>
        </form>
        <a href="index.php?shop=<?= $slug ?>" class="mobile-nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>"><i class="bi bi-house"></i> Home</a>
        <a href="products.php?shop=<?= $slug ?>" class="mobile-nav-link <?= $current_page === 'products.php' ? 'active' : '' ?>"><i class="bi bi-grid"></i> All Products</a>
        <a href="cart.php?shop=<?= $slug ?>" class="mobile-nav-link <?= $current_page === 'cart.php' ? 'active' : '' ?>">
            <i class="bi bi-bag"></i> Cart
            <?php if ($cart_count > 0): ?><span style="margin-left:auto;background:var(--primary);color:#fff;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;"><?= $cart_count ?></span><?php endif; ?>
        </a>
        <a href="orders.php?shop=<?= $slug ?>" class="mobile-nav-link <?= $current_page === 'orders.php' ? 'active' : '' ?>"><i class="bi bi-bag-check"></i> My Orders</a>
        <a href="profile.php?shop=<?= $slug ?>" class="mobile-nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>"><i class="bi bi-person"></i> Profile</a>
        <div style="height:1px;background:var(--border);margin:12px 0;"></div>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="../auth/logout.php" class="mobile-nav-link" style="color:#dc2626;"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
        <?php else: ?>
        <a href="../auth/login.php?shop=<?= $slug ?>" class="mobile-nav-link"><i class="bi bi-person-circle"></i> Sign In</a>
        <a href="../auth/register.php?shop=<?= $slug ?>" class="mobile-nav-link"><i class="bi bi-person-plus"></i> Create Account</a>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Bottom Nav -->
<div class="mobile-bottom-nav">
    <div class="mobile-bottom-nav-inner">
        <a href="index.php?shop=<?= $slug ?>" class="mobile-nav-tab <?= $current_page === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-house<?= $current_page === 'index.php' ? '-fill' : '' ?>"></i> Home
        </a>
        <a href="products.php?shop=<?= $slug ?>" class="mobile-nav-tab <?= $current_page === 'products.php' ? 'active' : '' ?>">
            <i class="bi bi-grid<?= $current_page === 'products.php' ? '-fill' : '' ?>"></i> Products
        </a>
        <a href="cart.php?shop=<?= $slug ?>" class="mobile-nav-tab <?= $current_page === 'cart.php' ? 'active' : '' ?>">
            <i class="bi bi-bag<?= $current_page === 'cart.php' ? '-fill' : '' ?>"></i> Cart
            <?php if ($cart_count > 0): ?><span class="mobile-cart-badge"><?= $cart_count ?></span><?php endif; ?>
        </a>
        <a href="<?= isset($_SESSION['user_id']) ? 'orders.php?shop='.$slug : '../auth/login.php?shop='.$slug ?>" class="mobile-nav-tab <?= $current_page === 'orders.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt<?= $current_page === 'orders.php' ? '' : '' ?>"></i> Orders
        </a>
        <a href="<?= isset($_SESSION['user_id']) ? 'profile.php?shop='.$slug : '../auth/login.php?shop='.$slug ?>" class="mobile-nav-tab <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person<?= $current_page === 'profile.php' ? '-fill' : '' ?>"></i> Profile
        </a>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<?php if ($popup): ?>
<!-- Popup Offer -->
<div id="shopPopup" style="position:fixed;inset:0;z-index:500;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);animation:fadeIn 0.3s ease;">
    <div style="background:var(--bg);border:1px solid var(--border-mid);border-radius:var(--radius-lg);max-width:440px;width:100%;position:relative;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,0.25);animation:scaleIn 0.3s cubic-bezier(0.34,1.56,0.64,1);">
        <?php if ($popup['image']): ?>
        <img src="../assets/uploads/popups/<?= htmlspecialchars($popup['image']) ?>" style="width:100%;height:200px;object-fit:cover;">
        <?php endif; ?>
        <div style="padding:28px;">
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:22px;margin-bottom:10px;letter-spacing:-0.5px;"><?= htmlspecialchars($popup['title']) ?></div>
            <?php if ($popup['message']): ?>
            <p style="font-size:14.5px;color:var(--text-muted);line-height:1.6;margin-bottom:20px;"><?= htmlspecialchars($popup['message']) ?></p>
            <?php endif; ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ($popup['button_text']): ?>
                <a href="<?= htmlspecialchars($popup['button_link'] ?? '#') ?>" class="btn-shop-primary" onclick="closePopup()">
                    <?= htmlspecialchars($popup['button_text']) ?> <i class="bi bi-arrow-right"></i>
                </a>
                <?php endif; ?>
                <button onclick="closePopup()" class="btn-shop-outline">Maybe later</button>
            </div>
        </div>
        <button onclick="closePopup()" style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.3);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.5)'" onmouseout="this.style.background='rgba(0,0,0,0.3)'">
            <i class="bi bi-x"></i>
        </button>
    </div>
</div>
<?php endif; ?>

<main>