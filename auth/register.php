<?php
session_start();
require '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../shop/index.php");
    exit;
}

$shop_slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
$shop = null;

if ($shop_slug) {
    $stmt = $conn->prepare("SELECT * FROM shops WHERE slug = ? AND is_active = 1");
    $stmt->bind_param("s", $shop_slug);
    $stmt->execute();
    $shop = $stmt->get_result()->fetch_assoc();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $shop) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $phone    = trim($_POST['phone']);
    $address  = trim($_POST['address']);
    $shop_id  = $shop['id'];

    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND shop_id = ?");
    $check->bind_param("si", $email, $shop_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "This email is already registered at this shop.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (shop_id, name, email, password, phone, address) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("isssss", $shop_id, $name, $email, $hashed, $phone, $address);
        $stmt->execute();
        $success = true;
    }
}

$primary = $shop['theme_primary'] ?? '#c8a97e';
$font    = $shop['theme_font']    ?? 'Inter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account &mdash; <?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&family=<?= urlencode($font) ?>:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: <?= htmlspecialchars($primary) ?>;
            --text: #f0ece4;
            --muted: rgba(240,236,228,0.5);
            --input-bg: rgba(255,255,255,0.06);
            --input-border: rgba(255,255,255,0.11);
            --glass: rgba(255,255,255,0.05);
            --glass-border: rgba(255,255,255,0.1);
        }

        html, body {
            min-height: 100vh;
            font-family: '<?= htmlspecialchars($font) ?>', 'DM Sans', sans-serif;
            color: var(--text);
            background: #0c0c0e;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 24px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-dynamic {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 70% 70% at 10% 10%, color-mix(in srgb, var(--primary) 18%, transparent) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 90% 90%, color-mix(in srgb, var(--primary) 10%, transparent) 0%, transparent 55%),
                #0c0c0e;
        }
        .grain {
            position: fixed; inset: 0; z-index: 1;
            opacity: 0.03; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 180px;
        }

        .wrap {
            position: relative; z-index: 2;
            width: 100%; max-width: 480px;
        }

        .shop-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .shop-logo-wrap {
            width: 64px; height: 64px;
            border-radius: 18px;
            margin: 0 auto 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px;
            background: color-mix(in srgb, var(--primary) 15%, rgba(255,255,255,0.05));
            border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
            box-shadow: 0 6px 24px color-mix(in srgb, var(--primary) 18%, transparent);
        }
        .shop-logo-wrap img { width:100%; height:100%; object-fit:cover; border-radius:18px; }
        .shop-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800; font-size: 20px; letter-spacing: -0.4px;
        }
        .shop-tagline { font-size: 13px; color: var(--muted); margin-top: 4px; }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 36px 36px 32px;
            position: relative;
            box-shadow: 0 24px 60px rgba(0,0,0,0.4);
        }
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%; height: 1px;
            background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--primary) 45%, transparent), transparent);
        }

        .section-label {
            font-size: 10.5px; font-weight: 600;
            letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 12px; margin-top: 22px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-label::after {
            content: ''; flex: 1; height: 1px;
            background: var(--glass-border);
        }

        .input-wrap {
            position: relative; margin-bottom: 12px;
        }
        .input-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted); font-size: 14px;
            pointer-events: none; transition: color 0.2s;
        }
        .form-control-custom {
            width: 100%;
            padding: 13px 14px 13px 42px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 11px;
            color: var(--text);
            font-family: inherit; font-size: 14px;
            outline: none; transition: all 0.2s;
        }
        .form-control-custom::placeholder { color: var(--muted); }
        .form-control-custom:focus {
            border-color: var(--primary);
            background: color-mix(in srgb, var(--primary) 5%, transparent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 15%, transparent);
        }
        .input-wrap:focus-within .input-icon { color: var(--primary); }

        textarea.form-control-custom {
            padding-top: 13px; resize: none; min-height: 80px;
        }

        .pass-toggle {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer; font-size: 14px; padding: 4px;
            transition: color 0.2s;
        }
        .pass-toggle:hover { color: var(--primary); }

        .strength-bar { height: 3px; border-radius: 99px; margin-top: 7px; background: rgba(255,255,255,0.07); overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 99px; transition: width 0.4s, background 0.4s; width: 0%; }
        .strength-label { font-size: 10.5px; color: var(--muted); margin-top: 3px; text-align: right; min-height: 15px; }

        .alert-custom {
            padding: 12px 16px; border-radius: 11px;
            font-size: 13px; margin-bottom: 18px;
            display: flex; align-items: center; gap: 9px;
            animation: slideIn 0.3s ease;
        }
        .alert-error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: #f87171; }
        .alert-success { background: rgba(110,231,183,0.1); border: 1px solid rgba(110,231,183,0.2); color: #6ee7b7; }
        @keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

        .btn-submit {
            width: 100%; padding: 14px;
            background: var(--primary);
            border: none; border-radius: 12px;
            color: #fff;
            font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14.5px;
            cursor: pointer; margin-top: 22px;
            position: relative; overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
            box-shadow: 0 4px 20px color-mix(in srgb, var(--primary) 30%, transparent);
        }
        .btn-submit::after { content:''; position:absolute; inset:0; background:rgba(255,255,255,0.1); opacity:0; transition:opacity 0.2s; }
        .btn-submit:hover { transform:translateY(-2px); filter:brightness(1.1); }
        .btn-submit:hover::after { opacity:1; }
        .btn-submit:active { transform:translateY(0); }

        .btn-spinner {
            display:none; width:17px; height:17px;
            border:2px solid rgba(255,255,255,0.3); border-top-color:#fff;
            border-radius:50%; animation:spin 0.7s linear infinite; margin:0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading .btn-text { display:none; }
        .loading .btn-spinner { display:block; }

        .form-footer { text-align:center; margin-top:22px; font-size:13px; color:var(--muted); }
        .form-footer a { color:var(--primary); text-decoration:none; font-weight:500; }

        .animate-in { opacity:0; transform:translateY(16px); animation:fadeUp 0.5s ease forwards; }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
        .d1 { animation-delay:0.05s; } .d2 { animation-delay:0.12s; }
        .d3 { animation-delay:0.2s; }  .d4 { animation-delay:0.28s; }

        @media (max-width: 520px) { .glass-card { padding: 28px 20px; } }
    </style>
</head>
<body>

<div class="bg-dynamic"></div>
<div class="grain"></div>

<div class="wrap">

    <?php if (!$shop): ?>
    <div class="glass-card animate-in" style="text-align:center;padding:56px 32px;">
        <i class="bi bi-shop-window" style="font-size:52px;color:var(--primary);display:block;margin-bottom:16px;"></i>
        <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:21px;margin-bottom:10px;">Shop Not Found</h3>
        <p style="font-size:14px;color:var(--muted);">This shop doesn't exist or is currently inactive.</p>
    </div>

    <?php else: ?>

    <div class="shop-header animate-in">
        <div class="shop-logo-wrap">
            <?php if ($shop['logo']): ?>
                <img src="../assets/uploads/logos/<?= htmlspecialchars($shop['logo']) ?>" alt="logo">
            <?php else: ?>
                <i class="bi bi-shop" style="color:var(--primary)"></i>
            <?php endif; ?>
        </div>
        <div class="shop-name"><?= htmlspecialchars($shop['name']) ?></div>
        <div class="shop-tagline">Create your account to start shopping</div>
    </div>

    <div class="glass-card animate-in d1">

        <?php if ($error): ?>
        <div class="alert-custom alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert-custom alert-success">
            <i class="bi bi-check-circle-fill"></i>
            Account created! <a href="login.php?shop=<?= htmlspecialchars($shop['slug']) ?>" style="color:#6ee7b7;font-weight:600;margin-left:4px;">Sign in now &rarr;</a>
        </div>
        <?php else: ?>

        <form method="POST" action="register.php?shop=<?= htmlspecialchars($shop_slug) ?>" id="regForm" novalidate>

            <div class="section-label animate-in d2">Personal Info</div>

            <div class="input-wrap animate-in d2">
                <input type="text" name="name" class="form-control-custom" placeholder="Full name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                <i class="bi bi-person input-icon"></i>
            </div>

            <div class="input-wrap animate-in d2">
                <input type="tel" name="phone" class="form-control-custom" placeholder="Phone number (optional)" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                <i class="bi bi-telephone input-icon"></i>
            </div>

            <div class="section-label animate-in d3">Account Details</div>

            <div class="input-wrap animate-in d3">
                <input type="email" name="email" class="form-control-custom" placeholder="Email address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <i class="bi bi-envelope input-icon"></i>
            </div>

            <div class="input-wrap animate-in d3" style="margin-bottom:4px;">
                <input type="password" name="password" class="form-control-custom" id="passInput" placeholder="Password" required>
                <i class="bi bi-lock input-icon"></i>
                <button type="button" class="pass-toggle" onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
            <div class="strength-bar animate-in d3"><div class="strength-fill" id="sFill"></div></div>
            <div class="strength-label animate-in d3" id="sLabel"></div>

            <div class="section-label animate-in d4">Delivery Address</div>

            <div class="input-wrap animate-in d4" style="margin-bottom:0;">
                <textarea name="address" class="form-control-custom" placeholder="Your delivery address (optional)"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                <i class="bi bi-geo-alt input-icon" style="top:18px;transform:none;"></i>
            </div>

            <button type="submit" class="btn-submit animate-in d4" id="submitBtn">
                <span class="btn-text"><i class="bi bi-bag-plus me-2"></i>Create Account</span>
                <div class="btn-spinner"></div>
            </button>

        </form>

        <?php endif; ?>

        <div class="form-footer animate-in d4">
            Already have an account? <a href="login.php?shop=<?= htmlspecialchars($shop['slug'] ?? '') ?>">Sign in <i class="bi bi-arrow-right"></i></a>
        </div>

    </div>

    <?php endif; ?>

</div>

<script>
    function togglePass() {
        const input = document.getElementById('passInput');
        const icon  = document.getElementById('eyeIcon');
        input.type  = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }

    document.getElementById('passInput')?.addEventListener('input', function () {
        const val = this.value;
        const fill = document.getElementById('sFill');
        const label = document.getElementById('sLabel');
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const c = [
            { w:'0%', color:'', text:'' },
            { w:'25%', color:'#f87171', text:'Weak' },
            { w:'50%', color:'#fb923c', text:'Fair' },
            { w:'75%', color:'#facc15', text:'Good' },
            { w:'100%', color:'#6ee7b7', text:'Strong' },
        ][score];
        fill.style.width = c.w;
        fill.style.background = c.color;
        label.textContent = c.text;
        label.style.color = c.color || 'var(--muted)';
    });

    document.getElementById('regForm')?.addEventListener('submit', function () {
        document.getElementById('submitBtn').classList.add('loading');
    });
</script>

</body>
</html>