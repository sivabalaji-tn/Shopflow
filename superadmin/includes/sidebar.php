<?php
// Protect all super admin pages
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../superadmin/login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

// Platform stats for sidebar
$total_shops    = $conn->query("SELECT COUNT(*) FROM shops")->fetch_row()[0];
$total_orders   = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$pending_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Super Admin' ?> &mdash; TamizhMart Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --sidebar-w:    260px;
            --sidebar-bg:   #080608;
            --body-bg:      #0d0b0e;
            --card-bg:      rgba(255,255,255,0.04);
            --card-border:  rgba(255,255,255,0.07);
            --accent:       #a855f7;
            --accent2:      #c084fc;
            --accent-dim:   rgba(168,85,247,0.15);
            --accent-glow:  rgba(168,85,247,0.08);
            --text:         #f3f0f8;
            --muted:        rgba(243,240,248,0.45);
            --muted2:       rgba(243,240,248,0.25);
            --success:      #4ade80;
            --danger:       #f87171;
            --warning:      #fbbf24;
            --info:         #60a5fa;
            --success-dim:  rgba(74,222,128,0.12);
            --danger-dim:   rgba(248,113,113,0.12);
            --warning-dim:  rgba(251,191,36,0.12);
            --info-dim:     rgba(96,165,250,0.12);
            --radius:       14px;
            --radius-sm:    10px;
            --transition:   all 0.2s ease;
        }

        html, body { height:100%; font-family:'DM Sans',sans-serif; color:var(--text); background:var(--body-bg); overflow-x:hidden; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-thumb { background:rgba(168,85,247,0.25); border-radius:99px; }

        /* ── Layout ── */
        .layout { display:flex; min-height:100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width:var(--sidebar-w); min-height:100vh;
            background:var(--sidebar-bg);
            border-right:1px solid rgba(255,255,255,0.06);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; z-index:100;
            transition:transform 0.3s ease;
        }
        .sidebar-brand {
            padding:22px 20px 18px;
            border-bottom:1px solid rgba(255,255,255,0.06);
            display:flex; align-items:center; gap:12px;
        }
        .brand-icon {
            width:38px; height:38px; border-radius:10px;
            background:linear-gradient(135deg,var(--accent),#7c3aed);
            display:flex; align-items:center; justify-content:center;
            font-size:18px; flex-shrink:0;
            box-shadow:0 4px 16px rgba(168,85,247,0.3);
        }
        .brand-text { font-family:'Syne',sans-serif; font-weight:800; font-size:15px; line-height:1.2; }
        .brand-sub  { font-size:10.5px; color:var(--accent); font-weight:600; letter-spacing:1px; text-transform:uppercase; }

        /* Super Admin badge */
        .sa-badge {
            margin:12px 16px;
            padding:8px 14px;
            background:linear-gradient(135deg,rgba(168,85,247,0.15),rgba(124,58,237,0.1));
            border:1px solid rgba(168,85,247,0.25);
            border-radius:10px;
            display:flex; align-items:center; gap:8px;
            font-size:12px; font-weight:600; color:var(--accent2);
        }
        .sa-badge i { font-size:14px; color:var(--accent); }

        .nav-section-label {
            font-size:10px; font-weight:700; letter-spacing:1.5px;
            text-transform:uppercase; color:var(--muted2);
            padding:16px 20px 6px;
        }
        .nav-item {
            display:flex; align-items:center; gap:10px;
            padding:10px 16px; margin:2px 8px;
            border-radius:var(--radius-sm);
            text-decoration:none; color:var(--muted);
            font-size:13.5px; font-weight:500;
            transition:var(--transition);
            border:1px solid transparent; position:relative;
        }
        .nav-item i { font-size:16px; width:20px; text-align:center; flex-shrink:0; }
        .nav-item:hover { background:var(--accent-glow); color:var(--text); }
        .nav-item.active {
            background:var(--accent-dim); color:var(--accent2);
            border-color:rgba(168,85,247,0.2);
        }
        .nav-item.active i { color:var(--accent); }
        .nav-badge {
            margin-left:auto; background:var(--danger);
            color:#fff; font-size:10px; font-weight:700;
            padding:2px 7px; border-radius:99px; min-width:20px; text-align:center;
        }

        .sidebar-footer {
            margin-top:auto;
            padding:16px;
            border-top:1px solid rgba(255,255,255,0.06);
        }
        .admin-card {
            display:flex; align-items:center; gap:10px;
            padding:12px; background:var(--card-bg);
            border:1px solid var(--card-border); border-radius:var(--radius-sm);
        }
        .admin-avatar {
            width:36px; height:36px; border-radius:10px;
            background:linear-gradient(135deg,var(--accent),#7c3aed);
            display:flex; align-items:center; justify-content:center;
            font-family:'Syne',sans-serif; font-weight:800; font-size:14px;
            flex-shrink:0;
        }
        .admin-name  { font-size:13px; font-weight:600; }
        .admin-role  { font-size:11px; color:var(--accent); font-weight:600; }
        .admin-logout {
            margin-left:auto; color:var(--muted);
            text-decoration:none; font-size:16px;
            transition:color 0.2s;
        }
        .admin-logout:hover { color:var(--danger); }

        /* ── Main content ── */
        .main-content {
            margin-left:var(--sidebar-w);
            flex:1; display:flex; flex-direction:column; min-height:100vh;
        }
        .topbar {
            padding:18px 28px;
            border-bottom:1px solid var(--card-border);
            display:flex; align-items:center; justify-content:space-between;
            background:rgba(8,6,8,0.8); backdrop-filter:blur(12px);
            position:sticky; top:0; z-index:50;
        }
        .topbar-title { font-family:'Syne',sans-serif; font-weight:700; font-size:18px; }
        .topbar-sub   { font-size:12.5px; color:var(--muted); margin-top:1px; }

        .page-body { padding:24px 28px; flex:1; }

        /* ── Cards ── */
        .card-glass {
            background:var(--card-bg); border:1px solid var(--card-border);
            border-radius:var(--radius); padding:22px;
            transition:var(--transition);
        }
        .card-glass:hover { border-color:rgba(168,85,247,0.15); }

        /* ── Stat cards ── */
        .stat-card {
            background:var(--card-bg); border:1px solid var(--card-border);
            border-radius:var(--radius); padding:20px 22px;
            display:flex; align-items:center; gap:16px;
            transition:var(--transition); position:relative; overflow:hidden;
        }
        .stat-card::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(135deg,var(--glow-color,rgba(168,85,247,0.05)),transparent);
            pointer-events:none;
        }
        .stat-card:hover { border-color:rgba(168,85,247,0.2); transform:translateY(-2px); }
        .stat-icon {
            width:48px; height:48px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:22px; flex-shrink:0;
        }
        .stat-val  { font-family:'Syne',sans-serif; font-weight:800; font-size:26px; line-height:1; }
        .stat-label{ font-size:12.5px; color:var(--muted); margin-top:4px; }
        .stat-change { font-size:12px; margin-top:6px; font-weight:600; }

        /* ── Table ── */
        .table-custom { width:100%; border-collapse:collapse; }
        .table-custom th {
            font-size:11px; font-weight:700; text-transform:uppercase;
            letter-spacing:0.8px; color:var(--muted2);
            padding:10px 16px; border-bottom:1px solid var(--card-border);
            text-align:left; white-space:nowrap;
        }
        .table-custom td {
            padding:13px 16px; border-bottom:1px solid rgba(255,255,255,0.04);
            font-size:13.5px; vertical-align:middle;
        }
        .table-custom tr:last-child td { border-bottom:none; }
        .table-custom tr:hover td { background:var(--accent-glow); }

        /* ── Badges ── */
        .badge-custom {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 10px; border-radius:99px;
            font-size:11.5px; font-weight:600; white-space:nowrap;
        }
        .badge-success { background:var(--success-dim); color:var(--success); }
        .badge-danger  { background:var(--danger-dim);  color:var(--danger);  }
        .badge-warning { background:var(--warning-dim); color:var(--warning); }
        .badge-info    { background:var(--info-dim);    color:var(--info);    }
        .badge-purple  { background:var(--accent-dim);  color:var(--accent2); }

        /* ── Buttons ── */
        .btn-primary-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; background:var(--accent);
            border:none; border-radius:var(--radius-sm);
            color:#fff; font-family:'Syne',sans-serif; font-weight:700; font-size:13px;
            cursor:pointer; transition:var(--transition);
            box-shadow:0 4px 16px rgba(168,85,247,0.25); text-decoration:none;
        }
        .btn-primary-custom:hover { filter:brightness(1.1); transform:translateY(-1px); color:#fff; }
        .btn-ghost-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; background:transparent;
            border:1px solid var(--card-border); border-radius:var(--radius-sm);
            color:var(--muted); font-size:13px; cursor:pointer;
            transition:var(--transition); text-decoration:none;
        }
        .btn-ghost-custom:hover { background:var(--card-bg); color:var(--text); border-color:rgba(255,255,255,0.15); }
        .btn-danger-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; background:var(--danger-dim);
            border:1px solid rgba(248,113,113,0.2); border-radius:var(--radius-sm);
            color:var(--danger); font-size:13px; cursor:pointer;
            transition:var(--transition); text-decoration:none;
        }
        .btn-danger-custom:hover { background:rgba(248,113,113,0.2); }
        .btn-success-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; background:var(--success-dim);
            border:1px solid rgba(74,222,128,0.2); border-radius:var(--radius-sm);
            color:var(--success); font-size:13px; cursor:pointer;
            transition:var(--transition); text-decoration:none;
        }
        .btn-success-custom:hover { background:rgba(74,222,128,0.2); }

        /* ── Form inputs ── */
        .input-custom {
            width:100%; padding:10px 14px;
            background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:var(--radius-sm); color:var(--text);
            font-family:'DM Sans',sans-serif; font-size:13.5px; outline:none;
            transition:var(--transition);
        }
        .input-custom:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(168,85,247,0.12); }
        .input-custom::placeholder { color:var(--muted2); }
        .form-label-custom { font-size:12px; font-weight:600; color:var(--muted); margin-bottom:6px; letter-spacing:0.3px; }

        /* ── Section titles ── */
        .section-title { font-family:'Syne',sans-serif; font-weight:700; font-size:15px; }
        .section-sub   { font-size:13px; color:var(--muted); margin-top:3px; }

        /* ── Modal ── */
        .modal-backdrop-custom {
            position:fixed; inset:0; background:rgba(0,0,0,0.6);
            backdrop-filter:blur(4px); z-index:1000;
            display:none; align-items:center; justify-content:center; padding:20px;
        }
        .modal-box {
            background:#130f18; border:1px solid rgba(168,85,247,0.15);
            border-radius:18px; padding:28px; width:100%; max-width:480px;
            max-height:90vh; overflow-y:auto;
            animation:modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes modalIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
        .modal-title { font-family:'Syne',sans-serif; font-weight:800; font-size:18px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }

        /* ── Alert flash ── */
        .alert-flash {
            display:flex; align-items:center; gap:10px;
            padding:13px 18px; border-radius:var(--radius-sm);
            font-size:13.5px; font-weight:500; margin-bottom:20px;
            animation:slideDown 0.3s ease;
        }
        @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .alert-flash-success { background:var(--success-dim); border:1px solid rgba(74,222,128,0.2); color:var(--success); }
        .alert-flash-danger  { background:var(--danger-dim);  border:1px solid rgba(248,113,113,0.2); color:var(--danger);  }

        /* ── Maintenance banner ── */
        .maintenance-bar {
            background:linear-gradient(90deg,rgba(251,191,36,0.15),rgba(251,191,36,0.08));
            border-bottom:1px solid rgba(251,191,36,0.2);
            padding:10px 28px; font-size:13px; color:var(--warning);
            display:flex; align-items:center; gap:10px;
        }

        /* ── Animate ── */
        .animate-in { animation:fadeUp 0.4s ease both; }
        .d1 { animation-delay:0.05s; } .d2 { animation-delay:0.1s; } .d3 { animation-delay:0.15s; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

        /* ── Responsive ── */
        .mobile-menu-btn { display:none; }
        @media(max-width:900px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .main-content { margin-left:0; }
            .mobile-menu-btn { display:flex; }
            .page-body { padding:16px; }
        }
    </style>
</head>
<body>
<div class="layout">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">👑</div>
        <div>
            <div class="brand-text">TamizhMart</div>
            <div class="brand-sub">Control Panel</div>
        </div>
    </div>

    <div class="sa-badge">
        <i class="bi bi-shield-fill-check"></i>
        Super Administrator
    </div>

    <nav style="flex:1;overflow-y:auto;padding-bottom:12px;">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="nav-item <?= $current_page==='dashboard.php'?'active':'' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <div class="nav-section-label">Platform</div>
        <a href="shops.php" class="nav-item <?= $current_page==='shops.php'?'active':'' ?>">
            <i class="bi bi-shop"></i> All Shops
            <span class="nav-badge" style="background:var(--accent);color:#fff;"><?= $total_shops ?></span>
        </a>
        <a href="owners.php" class="nav-item <?= $current_page==='owners.php'?'active':'' ?>">
            <i class="bi bi-person-badge"></i> Shop Owners
        </a>
        <a href="customers.php" class="nav-item <?= $current_page==='customers.php'?'active':'' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="orders.php" class="nav-item <?= $current_page==='orders.php'?'active':'' ?>">
            <i class="bi bi-bag"></i> All Orders
            <?php if ($pending_orders > 0): ?>
            <span class="nav-badge"><?= $pending_orders ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section-label">System</div>
        <a href="settings.php" class="nav-item <?= $current_page==='settings.php'?'active':'' ?>">
            <i class="bi bi-sliders"></i> Platform Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-card">
            <div class="admin-avatar"><?= strtoupper(substr($_SESSION['superadmin_name'],0,1)) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['superadmin_name']) ?></div>
                <div class="admin-role">Super Admin</div>
            </div>
            <a href="logout.php" class="admin-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</aside>

<!-- ── Main ── -->
<div class="main-content">

<?php
// Maintenance mode banner
$maint = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='maintenance_mode'")->fetch_row()[0] ?? '0';
if ($maint === '1'):
?>
<div class="maintenance-bar">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Maintenance Mode is ON</strong> — All shops are currently showing an offline message to customers.
    <a href="settings.php" style="color:var(--warning);margin-left:auto;font-weight:600;text-decoration:none;">Manage →</a>
</div>
<?php endif; ?>

<div class="topbar">
    <div>
        <div class="topbar-title"><?= $page_title ?? 'Dashboard' ?></div>
        <div class="topbar-sub"><?= $page_subtitle ?? 'TamizhMart Super Admin Panel' ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <button class="mobile-menu-btn btn-ghost-custom" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="bi bi-list"></i>
        </button>
        <a href="../owner/login.php" class="btn-ghost-custom" style="font-size:12px;">
            <i class="bi bi-box-arrow-up-right"></i> Owner Panel
        </a>
    </div>
</div>

<div class="page-body">