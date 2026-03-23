<?php
session_start();
require '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../shop/index.php");
    exit;
}

// Get shop by slug (URL param or session)
$shop_slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
$shop = null;

if ($shop_slug) {
    $stmt = $conn->prepare("SELECT * FROM shops WHERE slug = ? AND is_active = 1");
    $stmt->bind_param("s", $shop_slug);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $shop) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $shop_id  = $shop['id'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ? AND shop_id = ?");
    $stmt->bind_param("si", $email, $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']            = $user['id'];
        $_SESSION['user_name']          = $user['name'];
        $_SESSION['shop_id']            = $shop_id;
        $_SESSION['current_shop_slug']  = $shop['slug'];
        header("Location: ../shop/index.php?shop=" . $shop['slug']);
        exit;
    } else {
        $error = "Incorrect email or password.";
    }
}

// Theme vars
$primary   = $shop['theme_primary']   ?? '#c8a97e';
$secondary = $shop['theme_secondary'] ?? '#6c757d';
$bg        = $shop['theme_bg']        ?? '#0d0b08';
$font      = $shop['theme_font']      ?? 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &mdash; <?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&family=<?= urlencode($font) ?>:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: <?= htmlspecialchars($primary) ?>;
            --bg: <?= htmlspecialchars($bg) ?>;
            --text: #f0ece4;
            --muted: rgba(240,236,228,0.5);
            --input-bg: rgba(255,255,255,0.06);
            --input-border: rgba(255,255,255,0.12);
            --glass: rgba(255,255,255,0.05);
            --glass-border: rgba(255,255,255,0.1);
        }

        html, body {
            min-height: 100vh;
            font-family: '<?= htmlspecialchars($font) ?>', 'DM Sans', sans-serif;
            color: var(--text);
            background: #0c0c0e;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
        }

        /* Dynamic shop-colored background */
        .bg-dynamic {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 65% 65% at 10% 15%, color-mix(in srgb, var(--primary) 20%, transparent) 0%, transparent 60%),
                radial-gradient(ellipse 55% 55% at 90% 85%, color-mix(in srgb, var(--primary) 12%, transparent) 0%, transparent 55%),
                #0c0c0e;
        }

        .grain {
            position: fixed; inset: 0; z-index: 1;
            opacity: 0.03; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 180px;
        }

        /* Decorative ring */
        .ring {
            position: fixed;
            border-radius: 50%;
            border: 1px solid;
            border-color: color-mix(in srgb, var(--primary) 12%, transparent);
            pointer-events: none;
            z-index: 0;
            animation: pulse 6s ease-in-out infinite;
        }
        .ring-1 { width: 600px; height: 600px; top: -250px; left: -200px; animation-delay: 0s; }
        .ring-2 { width: 400px; height: 400px; bottom: -180px; right: -160px; animation-delay: -3s; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50%       { transform: scale(1.04); opacity: 1; }
        }

        .login-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 440px;
        }

        /* Shop header */
        .shop-header {
            text-align: center;
            margin-bottom: 36px;
        }
        .shop-logo-wrap {
            width: 72px; height: 72px;
            border-radius: 20px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            position: relative;
            overflow: hidden;
            background: color-mix(in srgb, var(--primary) 18%, rgba(255,255,255,0.05));
            border: 1px solid color-mix(in srgb, var(--primary) 30%, transparent);
            box-shadow: 0 8px 32px color-mix(in srgb, var(--primary) 20%, transparent);
        }
        .shop-logo-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 20px;
        }
        .shop-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
        }
        .shop-welcome {
            font-size: 13.5px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* No shop found */
        .no-shop {
            text-align: center;
            padding: 48px 24px;
        }
        .no-shop-icon {
            font-size: 56px;
            color: color-mix(in srgb, var(--primary) 60%, transparent);
            margin-bottom: 16px;
        }
        .no-shop h3 {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 22px;
            margin-bottom: 10px;
        }
        .no-shop p { font-size: 14px; color: var(--muted); }

        /* Card */
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px;
            position: relative;
            box-shadow: 0 24px 60px rgba(0,0,0,0.45);
        }
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--primary) 50%, transparent), transparent);
        }

        .form-heading {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.6px;
            margin-bottom: 6px;
        }
        .form-sub {
            font-size: 13.5px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 14px;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s;
        }
        .form-control-custom {
            width: 100%;
            padding: 14px 16px 14px 43px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        .form-control-custom::placeholder { color: var(--muted); }
        .form-control-custom:focus {
            border-color: var(--primary);
            background: color-mix(in srgb, var(--primary) 6%, transparent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent);
        }
        .input-wrap:focus-within .input-icon { color: var(--primary); }

        .pass-toggle {
            position: absolute; right: 13px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer;
            font-size: 14px; padding: 4px;
            transition: color 0.2s;
        }
        .pass-toggle:hover { color: var(--primary); }

        .alert-error {
            padding: 12px 16px;
            border-radius: 11px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 9px;
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.2);
            color: #f87171;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 14.5px;
            cursor: pointer;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
            box-shadow: 0 4px 20px color-mix(in srgb, var(--primary) 30%, transparent);
        }
        .btn-submit::after {
            content: '';
            position: absolute; inset: 0;
            background: rgba(255,255,255,0.12);
            opacity: 0; transition: opacity 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
            box-shadow: 0 8px 28px color-mix(in srgb, var(--primary) 40%, transparent);
        }
        .btn-submit:hover::after { opacity: 1; }
        .btn-submit:active { transform: translateY(0); }

        .btn-spinner {
            display: none;
            width: 17px; height: 17px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading .btn-text { display: none; }
        .loading .btn-spinner { display: block; }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--muted);
        }
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .owner-link {
            text-align: center;
            margin-top: 28px;
            font-size: 12.5px;
            color: rgba(240,236,228,0.25);
        }
        .owner-link a {
            color: rgba(240,236,228,0.35);
            text-decoration: none;
        }
        .owner-link a:hover { color: var(--muted); }

        .animate-in {
            opacity: 0;
            transform: translateY(16px);
            animation: fadeUp 0.5s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .d1 { animation-delay: 0.05s; }
        .d2 { animation-delay: 0.15s; }
        .d3 { animation-delay: 0.25s; }

        /* When there's an error, skip animation delays so fields show instantly */
        <?php if ($error): ?>
        .animate-in { animation-duration: 0.01s !important; animation-delay: 0s !important; }
        <?php endif; ?>

        .shake { animation: shake 0.4s ease; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); opacity: 1; }
            20% { transform: translateX(-7px); opacity: 1; }
            40% { transform: translateX(7px); opacity: 1; }
            60% { transform: translateX(-4px); opacity: 1; }
            80% { transform: translateX(4px); opacity: 1; }
        }

        @media (max-width: 500px) {
            .glass-card { padding: 28px 20px; }
        }
    </style>
</head>
<body>

<div class="bg-dynamic"></div>
<div class="grain"></div>
<div class="ring ring-1"></div>
<div class="ring ring-2"></div>

<div class="login-wrap">

    <?php if (!$shop): ?>
    <!-- No shop found state -->
    <div class="glass-card animate-in">
        <div class="no-shop">
            <div class="no-shop-icon"><i class="bi bi-shop-window"></i></div>
            <h3>Shop Not Found</h3>
            <p>The shop you're looking for doesn't exist or is currently inactive.</p>
        </div>
    </div>

    <?php else: ?>

    <!-- Shop Header -->
    <div class="shop-header animate-in">
        <div class="shop-logo-wrap">
            <?php if ($shop['logo']): ?>
                <img src="../assets/uploads/logos/<?= htmlspecialchars($shop['logo']) ?>" alt="logo">
            <?php else: ?>
                <i class="bi bi-shop" style="color:var(--primary)"></i>
            <?php endif; ?>
        </div>
        <div class="shop-name"><?= htmlspecialchars($shop['name']) ?></div>
        <div class="shop-welcome">Sign in to continue shopping</div>
    </div>

    <!-- Card -->
    <div class="glass-card animate-in d1" id="loginCard">

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php?shop=<?= htmlspecialchars($shop_slug) ?>" id="loginForm" novalidate>

            <div class="input-wrap animate-in d2">
                <input type="email" name="email" class="form-control-custom"
                    placeholder="Email address" required autofocus
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <i class="bi bi-envelope input-icon"></i>
            </div>

            <div class="input-wrap animate-in d3">
                <input type="password" name="password" class="form-control-custom"
                    id="passwordInput" placeholder="Password" required>
                <i class="bi bi-lock input-icon"></i>
                <button type="button" class="pass-toggle" onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>

            <button type="submit" class="btn-submit animate-in d3" id="submitBtn">
                <span class="btn-text"><i class="bi bi-bag-check me-2"></i>Sign In & Shop</span>
                <div class="btn-spinner"></div>
            </button>

        </form>

        <div class="form-footer animate-in d3">
            New here? <a href="register.php?shop=<?= htmlspecialchars($shop['slug']) ?>">Create an account <i class="bi bi-arrow-right"></i></a>
        </div>

    </div>

    <div class="owner-link animate-in d3">
        <a href="../owner/login.php"><i class="bi bi-gear me-1"></i>Owner / Admin login</a>
    </div>

    <?php endif; ?>

</div>

<script>
    function togglePass() {
        const input = document.getElementById('passwordInput');
        const icon  = document.getElementById('eyeIcon');
        input.type  = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }

    document.getElementById('loginForm')?.addEventListener('submit', function () {
        document.getElementById('submitBtn').classList.add('loading');
    });

    <?php if ($error): ?>
    document.getElementById('loginCard')?.classList.add('shake');
    <?php endif; ?>
</script>

</body>
</html>