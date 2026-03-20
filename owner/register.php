<?php
session_start();
require '../config/db.php';

// Check if registration is open
$reg_open = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='registration_open'")->fetch_row();
if ($reg_open && $reg_open[0] === '0') {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Registration Closed</title><link href="https://fonts.googleapis.com/css2?family=Syne:wght@800&family=DM+Sans:wght@400&display=swap" rel="stylesheet"><style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:"DM Sans",sans-serif;background:#0d0b0e;color:#f3f0f8;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;}h1{font-family:"Syne",sans-serif;font-weight:800;font-size:26px;margin:16px 0 10px;}p{opacity:.5;font-size:14px;max-width:320px;margin:0 auto 20px;}<a href="login.php" style="color:#a855f7;font-size:14px;">← Back to Login</a></style></head><body><div><div style="font-size:52px">🔒</div><h1>Registration Closed</h1><p>New shop registrations are currently not open. Please contact the administrator.</p><a href="login.php">← Back to Login</a></div></body></html>');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $shop_name  = trim($_POST['shop_name']);
    $shop_slug  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $shop_name));

    $check = $conn->prepare("SELECT id FROM owners WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "This email is already registered.";
    } else {
        $slugCheck = $conn->prepare("SELECT id FROM shops WHERE slug = ?");
        $slugCheck->bind_param("s", $shop_slug);
        $slugCheck->execute();
        $slugCheck->store_result();
        if ($slugCheck->num_rows > 0) {
            $shop_slug = $shop_slug . '-' . time();
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO owners (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed);
        $stmt->execute();
        $owner_id = $stmt->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO shops (owner_id, name, slug) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $owner_id, $shop_name, $shop_slug);
        $stmt2->execute();
        $shop_id = $stmt2->insert_id;

        $stmt3 = $conn->prepare("UPDATE owners SET shop_id = ? WHERE id = ?");
        $stmt3->bind_param("ii", $shop_id, $owner_id);
        $stmt3->execute();

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Shop &mdash; ShopFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --glass: rgba(255,255,255,0.07);
            --glass-border: rgba(255,255,255,0.15);
            --glass-hover: rgba(255,255,255,0.12);
            --accent: #c8a97e;
            --accent2: #e8d5b7;
            --text: #f0ece4;
            --muted: rgba(240,236,228,0.55);
            --input-bg: rgba(255,255,255,0.06);
            --input-border: rgba(255,255,255,0.12);
            --input-focus: rgba(200,169,126,0.4);
            --error: #f87171;
            --success: #6ee7b7;
        }

        html, body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            overflow-x: hidden;
        }

        body {
            background: #0d0b08;
            position: relative;
        }

        /* Animated gradient mesh background */
        .bg-mesh {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(180,130,70,0.22) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 90%, rgba(100,60,20,0.3) 0%, transparent 60%),
                radial-gradient(ellipse 100% 100% at 50% 50%, #1a1208 0%, #0d0b08 100%);
            animation: meshShift 12s ease-in-out infinite alternate;
        }

        @keyframes meshShift {
            0%   { background-position: 0% 0%, 100% 100%, center; }
            100% { background-position: 10% 5%, 90% 95%, center; }
        }

        /* Grain overlay */
        .grain {
            position: fixed;
            inset: 0;
            z-index: 1;
            opacity: 0.035;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-repeat: repeat;
            background-size: 180px;
        }

        /* Decorative orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 8s ease-in-out infinite;
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: rgba(180,130,60,0.12);
            top: -100px; left: -100px;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 300px; height: 300px;
            background: rgba(120,80,30,0.15);
            bottom: -80px; right: -80px;
            animation-delay: -4s;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%       { transform: translate(20px, -20px) scale(1.05); }
        }

        /* Page layout */
        .page-wrap {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        /* Left panel */
        .left-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 64px;
            border-right: 1px solid var(--glass-border);
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 80px;
        }
        .brand-icon {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, var(--accent), #8b6840);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
        }
        .brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
            color: var(--text);
        }

        .left-headline {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: clamp(36px, 4vw, 54px);
            line-height: 1.1;
            letter-spacing: -2px;
            color: var(--text);
            margin-bottom: 24px;
        }
        .left-headline span {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .left-sub {
            font-size: 16px;
            color: var(--muted);
            line-height: 1.7;
            max-width: 380px;
            margin-bottom: 48px;
        }

        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            backdrop-filter: blur(10px);
            transition: background 0.3s;
        }
        .feature-item:hover { background: var(--glass-hover); }
        .feature-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, rgba(200,169,126,0.2), rgba(200,169,126,0.05));
            border: 1px solid rgba(200,169,126,0.25);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 16px;
            flex-shrink: 0;
        }
        .feature-text {
            font-size: 14px;
            color: var(--muted);
            font-weight: 400;
        }
        .feature-text strong {
            display: block;
            color: var(--text);
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 2px;
        }

        /* Right panel — form */
        .right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 64px;
        }

        .form-card {
            width: 100%;
            max-width: 420px;
        }

        .form-title {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 28px;
            letter-spacing: -0.8px;
            margin-bottom: 6px;
        }
        .form-subtitle {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 36px;
        }

        /* Section dividers inside form */
        .form-section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
            margin-top: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--glass-border);
        }

        /* Inputs */
        .input-wrap {
            position: relative;
            margin-bottom: 16px;
        }
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s;
        }
        .form-control-custom {
            width: 100%;
            padding: 14px 16px 14px 44px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .form-control-custom::placeholder { color: var(--muted); }
        .form-control-custom:focus {
            border-color: var(--accent);
            background: rgba(200,169,126,0.06);
            box-shadow: 0 0 0 3px var(--input-focus);
        }
        .form-control-custom:focus + .input-icon,
        .input-wrap:focus-within .input-icon { color: var(--accent); }

        /* Password toggle */
        .pass-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
            transition: color 0.2s;
        }
        .pass-toggle:hover { color: var(--accent); }

        /* Password strength */
        .strength-bar {
            height: 3px;
            border-radius: 99px;
            margin-top: 8px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.4s, background 0.4s;
            width: 0%;
        }
        .strength-label {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            text-align: right;
            min-height: 16px;
        }

        /* Alerts */
        .alert-custom {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        .alert-error {
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.25);
            color: var(--error);
        }
        .alert-success {
            background: rgba(110,231,183,0.1);
            border: 1px solid rgba(110,231,183,0.25);
            color: var(--success);
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent), #8b6428);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.3px;
            cursor: pointer;
            margin-top: 28px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 24px rgba(200,169,126,0.25);
        }
        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(200,169,126,0.35); }
        .btn-submit:hover::before { opacity: 1; }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit.loading .btn-text { opacity: 0; }
        .btn-submit.loading .btn-spinner { display: block; }
        .btn-spinner {
            display: none;
            position: absolute;
            inset: 0;
            margin: auto;
            width: 20px; height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13.5px;
            color: var(--muted);
        }
        .form-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .form-footer a:hover { color: var(--accent2); }

        /* Responsive */
        @media (max-width: 900px) {
            .page-wrap { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 40px 24px; }
        }

        /* Staggered entrance animation */
        .animate-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.6s ease forwards;
        }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
    </style>
</head>
<body>

<div class="bg-mesh"></div>
<div class="grain"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="page-wrap">

    <!-- Left Panel -->
    <div class="left-panel">
        <div class="brand-mark animate-in">
            <div class="brand-icon"><i class="bi bi-bag-heart-fill"></i></div>
            <span class="brand-name">ShopFlow</span>
        </div>

        <h1 class="left-headline animate-in delay-1">
            Launch your<br><span>dream store</span><br>in minutes.
        </h1>
        <p class="left-sub animate-in delay-2">
            One platform, infinite possibilities. Create a bakery, a grocery store, a fashion boutique — fully branded, fully yours.
        </p>

        <div class="feature-list">
            <div class="feature-item animate-in delay-3">
                <div class="feature-icon"><i class="bi bi-palette2"></i></div>
                <div class="feature-text">
                    <strong>Dynamic Theme Customizer</strong>
                    Change colors, fonts & branding in real time
                </div>
            </div>
            <div class="feature-item animate-in delay-4">
                <div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="feature-text">
                    <strong>Rich Analytics Dashboard</strong>
                    Track revenue, orders & top products live
                </div>
            </div>
            <div class="feature-item animate-in delay-5">
                <div class="feature-icon"><i class="bi bi-megaphone"></i></div>
                <div class="feature-text">
                    <strong>Smart Offer Popups</strong>
                    Schedule promotions that show up automatically
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
        <div class="form-card">

            <h2 class="form-title animate-in">Create your shop</h2>
            <p class="form-subtitle animate-in delay-1">Fill in the details below to get started for free.</p>

            <?php if ($error): ?>
            <div class="alert-custom alert-error animate-in">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert-custom alert-success animate-in">
                <i class="bi bi-check-circle-fill"></i>
                Shop created successfully! <a href="login.php" style="color:var(--success);font-weight:600;margin-left:4px;">Login now &rarr;</a>
            </div>
            <?php else: ?>

            <form method="POST" id="registerForm" novalidate>

                <div class="form-section-label animate-in delay-2">Your Account</div>

                <div class="input-wrap animate-in delay-2">
                    <input type="text" name="name" class="form-control-custom" placeholder="Full name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <i class="bi bi-person input-icon"></i>
                </div>

                <div class="input-wrap animate-in delay-3">
                    <input type="email" name="email" class="form-control-custom" placeholder="Email address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <i class="bi bi-envelope input-icon"></i>
                </div>

                <div class="input-wrap animate-in delay-4" style="margin-bottom:4px;">
                    <input type="password" name="password" class="form-control-custom" placeholder="Password" id="passwordInput" required>
                    <i class="bi bi-lock input-icon"></i>
                    <button type="button" class="pass-toggle" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
                <div class="strength-bar animate-in delay-4">
                    <div class="strength-fill" id="strengthFill"></div>
                </div>
                <div class="strength-label animate-in delay-4" id="strengthLabel"></div>

                <div class="form-section-label animate-in delay-5">Your Shop</div>

                <div class="input-wrap animate-in delay-5">
                    <input type="text" name="shop_name" class="form-control-custom" placeholder="Shop name (e.g. Sugar Bloom Bakery)" required value="<?= htmlspecialchars($_POST['shop_name'] ?? '') ?>">
                    <i class="bi bi-shop input-icon"></i>
                </div>

                <button type="submit" class="btn-submit animate-in delay-5" id="submitBtn">
                    <span class="btn-text">
                        <i class="bi bi-rocket-takeoff me-2"></i>Launch My Shop
                    </span>
                    <div class="btn-spinner"></div>
                </button>

            </form>

            <?php endif; ?>

            <div class="form-footer animate-in">
                Already have a shop? <a href="login.php">Sign in <i class="bi bi-arrow-right"></i></a>
            </div>

        </div>
    </div>

</div>

<script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    document.getElementById('passwordInput').addEventListener('input', function () {
        const val = this.value;
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const configs = [
            { w: '0%',   color: '',              text: '' },
            { w: '25%',  color: '#f87171',        text: 'Weak' },
            { w: '50%',  color: '#fb923c',        text: 'Fair' },
            { w: '75%',  color: '#facc15',        text: 'Good' },
            { w: '100%', color: '#6ee7b7',        text: 'Strong' },
        ];
        const c = configs[score];
        fill.style.width    = c.w;
        fill.style.background = c.color;
        label.textContent   = c.text;
        label.style.color   = c.color || 'var(--muted)';
    });

    document.getElementById('registerForm')?.addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
    });
</script>

</body>
</html>
