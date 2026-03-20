<?php
session_start();
require '../config/db.php';

if (isset($_SESSION['owner_id'])) {
    header("Location: dashboard.php");
    exit;
    //This script is made by Siva Balaji sm
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, shop_id FROM owners WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner  = $result->fetch_assoc();

    if ($owner && password_verify($password, $owner['password'])) {
        $_SESSION['owner_id']   = $owner['id'];
        $_SESSION['owner_name'] = $owner['name'];
        $_SESSION['shop_id']    = $owner['shop_id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Login &mdash; ShopFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --glass: rgba(255,255,255,0.07);
            --glass-border: rgba(255,255,255,0.12);
            --accent: #c8a97e;
            --accent2: #e8d5b7;
            --text: #f0ece4;
            --muted: rgba(240,236,228,0.5);
            --input-bg: rgba(255,255,255,0.06);
            --input-border: rgba(255,255,255,0.12);
            --error: #f87171;
        }

        html, body {
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
        }

        body {
            background: #0d0b08;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        /* Background */
        .bg-mesh {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 70% 70% at 15% 20%, rgba(180,130,70,0.18) 0%, transparent 55%),
                radial-gradient(ellipse 60% 60% at 85% 80%, rgba(100,60,20,0.22) 0%, transparent 55%),
                #0d0b08;
        }
        .grain {
            position: fixed; inset: 0; z-index: 1;
            opacity: 0.03; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 180px;
        }

        /* Decorative lines */
        .deco-lines {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .deco-lines::before, .deco-lines::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(200,169,126,0.07);
        }
        .deco-lines::before {
            width: 700px; height: 700px;
            top: -200px; left: -200px;
        }
        .deco-lines::after {
            width: 500px; height: 500px;
            bottom: -150px; right: -150px;
        }

        /* Card */
        .login-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 460px;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 40px;
        }
        .brand-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--accent), #8b6840);
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            box-shadow: 0 8px 24px rgba(200,169,126,0.3);
        }
        .brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -0.5px;
        }

        .glass-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 44px 40px;
            box-shadow:
                0 32px 80px rgba(0,0,0,0.5),
                0 0 0 0.5px rgba(255,255,255,0.05) inset,
                0 1px 0 rgba(255,255,255,0.1) inset;
        }

        /* Shine effect on card */
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(200,169,126,0.4), transparent);
            border-radius: 24px 24px 0 0;
        }

        .form-heading {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 26px;
            letter-spacing: -0.8px;
            margin-bottom: 6px;
            text-align: center;
        }
        .form-sub {
            font-size: 14px;
            color: var(--muted);
            text-align: center;
            margin-bottom: 32px;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 14px;
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
            z-index: 1;
        }
        .form-control-custom {
            width: 100%;
            padding: 15px 16px 15px 44px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 13px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 14.5px;
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .form-control-custom::placeholder { color: var(--muted); }
        .form-control-custom:focus {
            border-color: var(--accent);
            background: rgba(200,169,126,0.05);
            box-shadow: 0 0 0 3px rgba(200,169,126,0.15);
        }
        .input-wrap:focus-within .input-icon { color: var(--accent); }

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
            z-index: 1;
        }
        .pass-toggle:hover { color: var(--accent); }

        .alert-error {
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.2);
            color: var(--error);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Shake animation for errors */
        .shake {
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-8px); }
            40%       { transform: translateX(8px); }
            60%       { transform: translateX(-5px); }
            80%       { transform: translateX(5px); }
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent), #8b6428);
            border: none;
            border-radius: 13px;
            color: #fff;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            margin-top: 24px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 24px rgba(200,169,126,0.25);
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(200,169,126,0.4); }
        .btn-submit:hover::after { opacity: 1; }
        .btn-submit:active { transform: translateY(0); }

        .btn-spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading .btn-text { display: none; }
        .loading .btn-spinner { display: block; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0 20px;
            color: var(--muted);
            font-size: 12px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }

        .customer-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 13px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13.5px;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .customer-link:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
            color: var(--text);
        }

        .form-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: var(--muted);
        }
        .form-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .form-footer a:hover { color: var(--accent2); }

        /* Entrance animations */
        .animate-in {
            opacity: 0;
            transform: translateY(18px);
            animation: fadeUp 0.55s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .d1 { animation-delay: 0.05s; }
        .d2 { animation-delay: 0.15s; }
        .d3 { animation-delay: 0.25s; }
        .d4 { animation-delay: 0.35s; }

        @media (max-width: 500px) {
            .glass-card { padding: 32px 24px; }
        }
    </style>
</head>
<body>

<div class="bg-mesh"></div>
<div class="grain"></div>
<div class="deco-lines"></div>

<div class="login-wrap">

    <div class="brand-mark animate-in">
        <div class="brand-icon"><i class="bi bi-bag-heart-fill"></i></div>
        <span class="brand-name">ShopFlow</span>
    </div>

    <div class="glass-card animate-in d1" style="position:relative;" id="loginCard">

        <h2 class="form-heading">Welcome back</h2>
        <p class="form-sub">Sign in to manage your shop</p>

        <?php if ($error): ?>
        <div class="alert-error" id="errorAlert">
            <i class="bi bi-shield-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" novalidate>

            <div class="input-wrap animate-in d2">
                <input
                    type="email"
                    name="email"
                    class="form-control-custom"
                    placeholder="Email address"
                    required
                    autofocus
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
                <i class="bi bi-envelope input-icon"></i>
            </div>

            <div class="input-wrap animate-in d3">
                <input
                    type="password"
                    name="password"
                    class="form-control-custom"
                    placeholder="Password"
                    id="passwordInput"
                    required
                >
                <i class="bi bi-lock input-icon"></i>
                <button type="button" class="pass-toggle" onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>

            <button type="submit" class="btn-submit animate-in d4" id="submitBtn">
                <span class="btn-text"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Dashboard</span>
                <div class="btn-spinner"></div>
            </button>

        </form>

        <div class="divider animate-in d4">or</div>

        <a href="../auth/login.php" class="customer-link animate-in d4">
            <i class="bi bi-person-circle"></i>
            Sign in as a customer instead
        </a>

    </div>

    <div class="form-footer animate-in d4">
        Don't have a shop yet? <a href="register.php">Create one free <i class="bi bi-arrow-right"></i></a>
    </div>

</div>

<script>
    function togglePass() {
        const input = document.getElementById('passwordInput');
        const icon  = document.getElementById('eyeIcon');
        input.type  = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }

    document.getElementById('loginForm').addEventListener('submit', function () {
        document.getElementById('submitBtn').classList.add('loading');
    });

    // Shake card on error
    <?php if ($error): ?>
    document.getElementById('loginCard').classList.add('shake');
    <?php endif; ?>
</script>

</body>
</html>
