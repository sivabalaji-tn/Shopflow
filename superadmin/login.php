<?php
session_start();
require '../config/db.php';

if (isset($_SESSION['superadmin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM super_admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['superadmin_id']   = $admin['id'];
            $_SESSION['superadmin_name'] = $admin['name'];
            $_SESSION['superadmin_email']= $admin['email'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Login — ShopFlow</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family:'DM Sans',sans-serif;
    background:#080608;
    color:#f3f0f8;
    min-height:100vh;
    display:flex; align-items:center; justify-content:center;
    padding:24px;
    position:relative; overflow:hidden;
}
/* Background glow */
.bg-glow {
    position:fixed; inset:0; z-index:0; pointer-events:none;
    background:
        radial-gradient(ellipse 60% 50% at 20% 30%, rgba(168,85,247,0.12) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 80% 70%, rgba(124,58,237,0.1) 0%, transparent 60%),
        #080608;
}
.card {
    position:relative; z-index:1;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(168,85,247,0.15);
    border-radius:22px; padding:40px 36px;
    width:100%; max-width:420px;
    box-shadow:0 24px 64px rgba(0,0,0,0.4), 0 0 0 1px rgba(168,85,247,0.08);
    animation:fadeUp .4s ease;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.logo {
    width:56px; height:56px; border-radius:16px;
    background:linear-gradient(135deg,#a855f7,#7c3aed);
    display:flex; align-items:center; justify-content:center;
    font-size:26px; margin:0 auto 18px;
    box-shadow:0 8px 24px rgba(168,85,247,0.3);
}
h1 {
    font-family:'Syne',sans-serif; font-weight:800; font-size:24px;
    text-align:center; margin-bottom:4px; letter-spacing:-0.5px;
}
.sub {
    text-align:center; font-size:13px; margin-bottom:28px;
    color:rgba(243,240,248,0.45);
}
.restricted {
    display:flex; align-items:center; justify-content:center; gap:7px;
    background:rgba(168,85,247,0.08); border:1px solid rgba(168,85,247,0.2);
    border-radius:10px; padding:9px 14px; margin-bottom:24px;
    font-size:12.5px; color:#c084fc; font-weight:600;
}
label { font-size:12px; font-weight:600; color:rgba(243,240,248,0.45); display:block; margin-bottom:7px; }
.inp {
    width:100%; padding:12px 16px;
    background:rgba(255,255,255,0.05);
    border:1.5px solid rgba(255,255,255,0.08);
    border-radius:10px; color:#f3f0f8;
    font-family:'DM Sans',sans-serif; font-size:14px; outline:none;
    transition:all .2s;
}
.inp:focus { border-color:#a855f7; box-shadow:0 0 0 3px rgba(168,85,247,0.12); }
.inp::placeholder { color:rgba(243,240,248,0.2); }
.pw { position:relative; }
.pw .inp { padding-right:44px; }
.pw-btn {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:rgba(243,240,248,0.35);
    cursor:pointer; font-size:15px; padding:4px; transition:color .2s;
}
.pw-btn:hover { color:#a855f7; }
.err {
    background:rgba(248,113,113,0.08); border:1px solid rgba(248,113,113,0.2);
    border-radius:10px; padding:11px 14px;
    display:flex; align-items:center; gap:8px;
    font-size:13px; color:#f87171; margin-bottom:18px;
}
.btn {
    width:100%; padding:13px;
    background:linear-gradient(135deg,#a855f7,#7c3aed);
    border:none; border-radius:10px; color:#fff;
    font-family:'Syne',sans-serif; font-weight:700; font-size:15px;
    cursor:pointer; transition:all .2s; margin-top:8px;
    box-shadow:0 4px 20px rgba(168,85,247,0.3);
}
.btn:hover { filter:brightness(1.1); transform:translateY(-1px); }
.fg { margin-bottom:16px; }
.back {
    display:flex; align-items:center; justify-content:center; gap:6px;
    margin-top:22px; font-size:12.5px; color:rgba(243,240,248,0.35);
    text-decoration:none; transition:color .2s;
}
.back:hover { color:#a855f7; }
</style>
</head>
<body>
<div class="bg-glow"></div>
<div class="card">
    <div class="logo">👑</div>
    <h1>Control Panel</h1>
    <p class="sub">ShopFlow Super Administrator</p>
    <div class="restricted">
        <i class="bi bi-shield-lock-fill"></i>
        Restricted Access — Authorised Personnel Only
    </div>

    <?php if ($error): ?>
    <div class="err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="fg">
            <label>Admin Email</label>
            <input type="email" name="email" class="inp" placeholder="admin@shopflow.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="fg">
            <label>Password</label>
            <div class="pw">
                <input type="password" name="password" class="inp" placeholder="••••••••" required id="pw">
                <button type="button" class="pw-btn" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password';this.querySelector('i').className=i.type==='password'?'bi bi-eye':'bi bi-eye-slash'">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn"><i class="bi bi-shield-check"></i> Access Control Panel</button>
    </form>
    <a href="../owner/login.php" class="back"><i class="bi bi-arrow-left"></i> Back to Owner Login</a>
</div>
</body>
</html>
