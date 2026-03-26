<?php
// Protect all owner pages
if (!isset($_SESSION['owner_id'])) {
    header("Location: ../login.php");
    exit;
}

// Fetch shop data
$shop_id = $_SESSION['shop_id'];
$shop_stmt = $conn->prepare("SELECT * FROM shops WHERE id = ?");
$shop_stmt->bind_param("i", $shop_id);
$shop_stmt->execute();
$shop = $shop_stmt->get_result()->fetch_assoc();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> &mdash; <?= htmlspecialchars($shop['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 260px;
            --sidebar-bg: #0e0c09;
            --sidebar-border: rgba(255,255,255,0.07);
            --body-bg: #111009;
            --card-bg: rgba(255,255,255,0.04);
            --card-border: rgba(255,255,255,0.08);
            --card-hover: rgba(255,255,255,0.07);
            --accent: #c8a97e;
            --accent2: #e8d5b7;
            --accent-dim: rgba(200,169,126,0.15);
            --accent-glow: rgba(200,169,126,0.08);
            --text: #f0ece4;
            --muted: rgba(240,236,228,0.45);
            --muted2: rgba(240,236,228,0.25);
            --success: #4ade80;
            --danger: #f87171;
            --warning: #fbbf24;
            --info: #60a5fa;
            --success-dim: rgba(74,222,128,0.12);
            --danger-dim: rgba(248,113,113,0.12);
            --warning-dim: rgba(251,191,36,0.12);
            --info-dim: rgba(96,165,250,0.12);
            --nav-active-bg: rgba(200,169,126,0.12);
            --nav-active-border: var(--accent);
            --input-bg: rgba(255,255,255,0.05);
            --input-border: rgba(255,255,255,0.1);
            --radius: 14px;
            --radius-sm: 10px;
            --transition: all 0.2s ease;
        }

        html, body { height: 100%; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--body-bg); overflow-x: hidden; }

        /* ─── Scrollbar ─── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(200,169,126,0.25); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(200,169,126,0.45); }

        /* ─── Layout ─── */
        .layout { display: flex; min-height: 100vh; }

        /* ─── Sidebar ─── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            padding: 24px 22px 20px;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 11px;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent), #7a5c2e);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(200,169,126,0.3);
        }
        .brand-texts { overflow: hidden; }
        .brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800; font-size: 15px;
            letter-spacing: -0.3px; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .brand-sub { font-size: 11px; color: var(--muted); margin-top: 1px; }

        /* Nav */
        .sidebar-nav { padding: 16px 12px; flex: 1; overflow-y: auto; }
        .nav-section-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 1.8px; text-transform: uppercase;
            color: var(--muted2);
            padding: 0 10px;
            margin: 18px 0 8px;
        }
        .nav-section-label:first-child { margin-top: 4px; }

        .nav-item {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--muted);
            text-decoration: none;
            font-size: 13.5px; font-weight: 400;
            transition: var(--transition);
            position: relative;
            margin-bottom: 2px;
            border: 1px solid transparent;
        }
        .nav-item i { font-size: 16px; flex-shrink: 0; transition: var(--transition); }
        .nav-item:hover {
            color: var(--text);
            background: rgba(255,255,255,0.04);
        }
        .nav-item.active {
            color: var(--accent);
            background: var(--nav-active-bg);
            border-color: rgba(200,169,126,0.15);
            font-weight: 500;
        }
        .nav-item.active i { color: var(--accent); }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            background: var(--accent);
            border-radius: 0 3px 3px 0;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--accent);
            color: #1a0f00;
            font-size: 10px; font-weight: 700;
            padding: 2px 7px;
            border-radius: 99px;
        }

        /* Sidebar footer */
        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid var(--sidebar-border);
        }
        .owner-card {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--card-border);
        }
        .owner-avatar {
            width: 34px; height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--accent), #7a5c2e);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: 13px; color: #fff;
            flex-shrink: 0;
        }
        .owner-info { overflow: hidden; flex: 1; }
        .owner-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .owner-role { font-size: 11px; color: var(--muted); }
        .owner-logout {
            color: var(--muted);
            font-size: 15px;
            text-decoration: none;
            transition: color 0.2s;
            padding: 4px;
        }
        .owner-logout:hover { color: var(--danger); }

        /* ─── Main content ─── */
        .main-content {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            padding: 20px 32px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(17,16,9,0.8);
            backdrop-filter: blur(16px);
            position: sticky; top: 0; z-index: 50;
        }
        .topbar-left h1 {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: 20px;
            letter-spacing: -0.5px;
        }
        .topbar-left p { font-size: 13px; color: var(--muted); margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }

        .topbar-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 500;
            border: 1px solid;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
        }
        .topbar-btn-ghost {
            background: transparent;
            border-color: var(--card-border);
            color: var(--muted);
        }
        .topbar-btn-ghost:hover { background: var(--card-bg); color: var(--text); border-color: rgba(255,255,255,0.15); }
        .topbar-btn-primary {
            background: linear-gradient(135deg, var(--accent), #8b6428);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 4px 14px rgba(200,169,126,0.25);
        }
        .topbar-btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }

        /* Mobile hamburger */
        .mobile-menu-btn {
            display: none;
            background: none; border: 1px solid var(--card-border);
            color: var(--text); border-radius: 8px;
            padding: 7px 10px; cursor: pointer;
            font-size: 16px;
        }

        /* Page body */
        .page-body { padding: 28px 32px; flex: 1; }

        /* ─── Cards ─── */
        .card-glass {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 24px;
            transition: var(--transition);
        }
        .card-glass:hover { background: var(--card-hover); border-color: rgba(255,255,255,0.12); }

        /* ─── Stat cards ─── */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 22px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-glow-color, var(--accent-glow)), transparent);
        }
        .stat-card:hover { background: var(--card-hover); transform: translateY(-2px); }
        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 16px;
        }
        .stat-card .stat-value {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: 28px;
            letter-spacing: -1px;
        }
        .stat-card .stat-label { font-size: 12.5px; color: var(--muted); margin-top: 3px; }
        .stat-card .stat-trend {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11.5px; font-weight: 500;
            margin-top: 8px; padding: 3px 8px;
            border-radius: 99px;
        }
        .trend-up { background: var(--success-dim); color: var(--success); }
        .trend-down { background: var(--danger-dim); color: var(--danger); }
        .trend-neutral { background: rgba(255,255,255,0.06); color: var(--muted); }

        /* ─── Table ─── */
        .table-glass {
            width: 100%; border-collapse: separate; border-spacing: 0;
        }
        .table-glass thead tr th {
            padding: 12px 16px;
            font-size: 11px; font-weight: 600;
            letter-spacing: 1.2px; text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--card-border);
            background: rgba(255,255,255,0.02);
            white-space: nowrap;
        }
        .table-glass tbody tr td {
            padding: 14px 16px;
            font-size: 13.5px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            vertical-align: middle;
        }
        .table-glass tbody tr:last-child td { border-bottom: none; }
        .table-glass tbody tr:hover td { background: rgba(255,255,255,0.025); }

        /* ─── Badges / Status pills ─── */
        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 99px;
            font-size: 11.5px; font-weight: 600; white-space: nowrap;
        }
        .status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .pill-pending    { background: var(--warning-dim); color: var(--warning); }
        .pill-pending::before { background: var(--warning); }
        .pill-processing { background: var(--info-dim); color: var(--info); }
        .pill-processing::before { background: var(--info); }
        .pill-out_for_delivery { background: rgba(167,139,250,0.12); color: #a78bfa; }
        .pill-out_for_delivery::before { background: #a78bfa; }
        .pill-delivered  { background: var(--success-dim); color: var(--success); }
        .pill-delivered::before { background: var(--success); }
        .pill-cancelled  { background: var(--danger-dim); color: var(--danger); }
        .pill-cancelled::before { background: var(--danger); }
        .pill-active     { background: var(--success-dim); color: var(--success); }
        .pill-active::before { background: var(--success); }
        .pill-inactive   { background: rgba(255,255,255,0.06); color: var(--muted); }
        .pill-inactive::before { background: var(--muted); }

        /* ─── Form controls ─── */
        .form-label-custom { font-size: 12.5px; font-weight: 500; color: var(--muted); margin-bottom: 7px; letter-spacing: 0.3px; }
        .input-custom {
            width: 100%;
            padding: 11px 14px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px; outline: none;
            transition: var(--transition);
        }
        .input-custom::placeholder { color: var(--muted); }
        .input-custom:focus {
            border-color: var(--accent);
            background: rgba(200,169,126,0.05);
            box-shadow: 0 0 0 3px rgba(200,169,126,0.12);
        }
        select.input-custom option { background: #1a1408; color: var(--text); }
        textarea.input-custom { resize: vertical; min-height: 90px; }

        /* ─── Buttons ─── */
        .btn-primary-custom {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent), #8b6428);
            border: none; border-radius: var(--radius-sm);
            color: #fff;
            font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13.5px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(200,169,126,0.2);
        }
        .btn-primary-custom:hover { filter: brightness(1.1); transform: translateY(-1px); color: #fff; }

        .btn-ghost-custom {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px;
            background: transparent;
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            color: var(--muted);
            font-family: 'DM Sans', sans-serif; font-size: 13.5px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
        }
        .btn-ghost-custom:hover { background: var(--card-bg); color: var(--text); border-color: rgba(255,255,255,0.15); }

        .btn-danger-custom {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px;
            background: var(--danger-dim);
            border: 1px solid rgba(248,113,113,0.2);
            border-radius: var(--radius-sm);
            color: var(--danger); font-size: 13px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .btn-danger-custom:hover { background: rgba(248,113,113,0.2); color: var(--danger); }

        .btn-success-custom {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px;
            background: var(--success-dim);
            border: 1px solid rgba(74,222,128,0.2);
            border-radius: var(--radius-sm);
            color: var(--success); font-size: 13px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .btn-success-custom:hover { background: rgba(74,222,128,0.2); color: var(--success); }

        /* ─── Alert ─── */
        .alert-flash {
            padding: 13px 18px; border-radius: var(--radius-sm);
            font-size: 13.5px; margin-bottom: 22px;
            display: flex; align-items: center; gap: 10px;
            animation: slideIn 0.3s ease;
        }
        .alert-flash-success { background: var(--success-dim); border: 1px solid rgba(74,222,128,0.2); color: var(--success); }
        .alert-flash-error   { background: var(--danger-dim);  border: 1px solid rgba(248,113,113,0.2); color: var(--danger); }
        @keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

        /* ─── Modal ─── */
        .modal-backdrop-custom {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            opacity: 0; pointer-events: none;
            transition: opacity 0.25s;
        }
        .modal-backdrop-custom.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: #161208;
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 32px;
            width: 100%; max-width: 520px;
            transform: scale(0.95) translateY(10px);
            transition: transform 0.25s;
            max-height: 90vh; overflow-y: auto;
        }
        .modal-backdrop-custom.open .modal-box { transform: scale(1) translateY(0); }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .modal-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 18px; }
        .modal-close {
            background: none; border: none;
            color: var(--muted); font-size: 20px;
            cursor: pointer; padding: 4px;
            transition: color 0.2s;
        }
        .modal-close:hover { color: var(--text); }

        /* ─── Section heading ─── */
        .section-head {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px;
        }
        .section-title {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: 16px; letter-spacing: -0.3px;
        }
        .section-sub { font-size: 12.5px; color: var(--muted); margin-top: 2px; }

        /* ─── Empty state ─── */
        .empty-state {
            text-align: center; padding: 60px 32px;
            color: var(--muted);
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.4; display: block; }
        .empty-state h4 { font-family: 'Syne', sans-serif; font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .empty-state p { font-size: 13.5px; }

        /* ─── Responsive ─── */
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .page-body { padding: 20px 16px; }
            .topbar { padding: 16px 20px; }
        }

        /* ─── Animations ─── */
        .animate-in { opacity: 0; transform: translateY(14px); animation: fadeUp 0.45s ease forwards; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .d1{animation-delay:.05s} .d2{animation-delay:.1s} .d3{animation-delay:.15s}
        .d4{animation-delay:.2s}  .d5{animation-delay:.25s} .d6{animation-delay:.3s}

        /* Grain overlay */
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            pointer-events: none;
            opacity: 0.02;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 180px;
        }
    </style>
</head>
<body>
<div class="layout">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-bag-heart-fill"></i></div>
        <div class="brand-texts">
            <div class="brand-name"><?= htmlspecialchars($shop['name']) ?></div>
            <div class="brand-sub">Owner Dashboard</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="analytics.php" class="nav-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Analytics
        </a>

        <div class="nav-section-label">Store</div>
        <a href="orders.php" class="nav-item <?= $current_page === 'orders.php' ? 'active' : '' ?>">
            <i class="bi bi-bag-check"></i> Orders
            <?php
            $pending_count_q = $conn->prepare("SELECT COUNT(*) as c FROM orders WHERE shop_id = ? AND status = 'pending'");
            $pending_count_q->bind_param("i", $shop_id);
            $pending_count_q->execute();
            $pc = $pending_count_q->get_result()->fetch_assoc()['c'];
            if ($pc > 0): ?>
            <span class="nav-badge"><?= $pc ?></span>
            <?php endif; ?>
        </a>
        <a href="customers.php" class="nav-item <?= $current_page === 'customers.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="export_orders.php?format=print" target="_blank" class="nav-item">
            <i class="bi bi-file-earmark-arrow-down"></i> Export Orders
        </a>
        <a href="products.php" class="nav-item <?= $current_page === 'products.php' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a href="sort_products.php" class="nav-item <?= $current_page === 'sort_products.php' ? 'active' : '' ?>">
            <i class="bi bi-sort-numeric-down"></i> Sort Products
        </a>
        <a href="bulk_upload.php" class="nav-item <?= $current_page === 'bulk_upload.php' ? 'active' : '' ?>">
            <i class="bi bi-cloud-upload"></i> Bulk Upload
        </a>
        <a href="categories.php" class="nav-item <?= $current_page === 'categories.php' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a href="popups.php" class="nav-item <?= $current_page === 'popups.php' ? 'active' : '' ?>">
            <i class="bi bi-megaphone"></i> Offers & Popups
        </a>

        <div class="nav-section-label">Customise</div>
        <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <i class="bi bi-sliders"></i> Store Settings
        </a>
        <a href="theme.php" class="nav-item <?= $current_page === 'theme.php' ? 'active' : '' ?>">
            <i class="bi bi-palette2"></i> Theme & Colors
        </a>
        <a href="social.php" class="nav-item <?= $current_page === 'social.php' ? 'active' : '' ?>">
            <i class="bi bi-share"></i> Social Links
        </a>

        <div class="nav-section-label">Preview</div>
        <a href="../shop/index.php?shop=<?= $shop['slug'] ?>" class="nav-item" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i> View My Shop
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="owner-card">
            <div class="owner-avatar"><?= strtoupper(substr($_SESSION['owner_name'], 0, 1)) ?></div>
            <div class="owner-info">
                <div class="owner-name"><?= htmlspecialchars($_SESSION['owner_name']) ?></div>
                <div class="owner-role">Shop Owner</div>
            </div>
            <a href="logout.php" class="owner-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</aside>

<!-- Main -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:14px;">
            <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <div class="topbar-left">
                <h1><?= $page_title ?? 'Dashboard' ?></h1>
                <p><?= $page_subtitle ?? date('l, F j, Y') ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="../shop/index.php?shop=<?= $shop['slug'] ?>" target="_blank" class="topbar-btn topbar-btn-ghost">
                <i class="bi bi-eye"></i> <span class="d-none d-md-inline">Preview Shop</span>
            </a>
            <?php if (isset($topbar_action_label)): ?>
            <button class="topbar-btn topbar-btn-primary" onclick="<?= $topbar_action_onclick ?? '' ?>">
                <i class="bi bi-<?= $topbar_action_icon ?? 'plus' ?>"></i> <?= $topbar_action_label ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <!-- Page content starts here -->
    <div class="page-body">