<?php
session_start();
require '../config/db.php';

$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
if (!$slug) { header("Location: ../index.php"); exit; }
$stmt = $conn->prepare("SELECT * FROM shops WHERE slug=? AND is_active=1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
if (!$shop) die('Shop not found.');
$_SESSION['current_shop_slug'] = $slug;
$shop_id = $shop['id'];

$settings_map = [];
$sr = $conn->query("SELECT setting_key,setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];


$user_id = $_SESSION['user_id'];
$user    = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim($_POST['name']);
        $phone   = trim($_POST['phone']);
        $address = trim($_POST['address']);
        if (empty($name)) { $error = 'Name cannot be empty.'; }
        else {
            $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=? AND shop_id=?");
            $stmt->bind_param("sssii", $name, $phone, $address, $user_id, $shop_id);
            $stmt->execute();
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully.';
            $user['name'] = $name; $user['phone'] = $phone; $user['address'] = $address;
        }

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new_pw  = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new_pw !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashed, $user_id);
            $stmt->execute();
            $success = 'Password changed successfully.';
        }
    }
}

// Stats
$total_orders   = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id AND shop_id=$shop_id")->fetch_assoc()['c'];
$total_spent    = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM orders WHERE user_id=$user_id AND shop_id=$shop_id AND status!='cancelled'")->fetch_assoc()['t'];
$pending_orders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id AND shop_id=$shop_id AND status='pending'")->fetch_assoc()['c'];

$page_title = 'My Profile';
require 'includes/shop_head.php';
requireCustomerLogin($shop);
?>

<style>
.profile-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
    padding: 32px 0 60px;
    align-items: start;
}
.profile-sidebar-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 32px 24px;
    text-align: center;
    position: sticky;
    top: calc(var(--navbar-h) + 20px);
}
.profile-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 32px; color: #fff;
    margin: 0 auto 16px;
    box-shadow: 0 8px 24px var(--primary-glow);
}
.profile-user-name {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 20px; letter-spacing: -0.4px;
    margin-bottom: 4px;
}
.profile-email { font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px; }

.profile-stat {
    padding: 12px 16px;
    background: color-mix(in srgb, var(--text) 4%, var(--bg));
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    margin-bottom: 10px;
    display: flex; align-items: center; justify-content: space-between;
}
.profile-stat-label { font-size: 13px; color: var(--text-muted); }
.profile-stat-val { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 15px; color: var(--primary); }

.profile-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px;
    margin-bottom: 16px;
}
.profile-section-title {
    font-family: 'Syne', sans-serif;
    font-weight: 700; font-size: 16px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.profile-section-title i { color: var(--primary); font-size: 18px; }

.form-label-prof { font-size: 12.5px; font-weight: 500; color: var(--text-muted); margin-bottom: 7px; }

@media (max-width: 768px) {
    .profile-layout { grid-template-columns: 1fr; }
    .profile-sidebar-card { position: relative; top: 0; }
}
</style>

<div class="shop-container">
<div class="profile-layout">

    <!-- Sidebar -->
    <div>
        <div class="profile-sidebar-card fade-up">
            <div class="profile-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="profile-user-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>

            <div style="margin-bottom:16px;">
                <div class="profile-stat">
                    <span class="profile-stat-label"><i class="bi bi-bag me-1"></i>Total Orders</span>
                    <span class="profile-stat-val"><?= $total_orders ?></span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label"><i class="bi bi-currency-rupee me-1"></i>Total Spent</span>
                    <span class="profile-stat-val">&#8377;<?= number_format($total_spent, 0) ?></span>
                </div>
                <?php if ($pending_orders > 0): ?>
                <div class="profile-stat" style="border-color:color-mix(in srgb,var(--primary) 25%,transparent);background:var(--primary-light);">
                    <span class="profile-stat-label"><i class="bi bi-clock me-1"></i>Pending</span>
                    <span class="profile-stat-val"><?= $pending_orders ?></span>
                </div>
                <?php endif; ?>
            </div>

            <a href="orders.php?shop=<?= $slug ?>" class="btn-shop-outline" style="width:100%;justify-content:center;margin-bottom:8px;">
                <i class="bi bi-bag-check"></i> View Orders
            </a>
            <a href="../auth/logout.php" class="btn-shop-ghost" style="width:100%;justify-content:center;color:#dc2626;">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </div>
    </div>

    <!-- Main -->
    <div>
        <?php if ($success): ?>
        <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:var(--radius-sm);padding:14px 18px;display:flex;gap:10px;align-items:center;margin-bottom:16px;color:#16a34a;font-size:13.5px;" class="fade-up">
            <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
        </div>
        <?php elseif ($error): ?>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius-sm);padding:14px 18px;display:flex;gap:10px;align-items:center;margin-bottom:16px;color:#dc2626;font-size:13.5px;" class="fade-up">
            <i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Profile Info -->
        <div class="profile-card fade-up d1">
            <div class="profile-section-title"><i class="bi bi-person-circle"></i> Personal Information</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div style="display:grid;gap:14px;">
                    <div>
                        <div class="form-label-prof">Full Name *</div>
                        <input type="text" name="name" class="input-shop" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div>
                        <div class="form-label-prof">Email Address</div>
                        <input type="email" class="input-shop" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:color-mix(in srgb,var(--text) 4%,var(--bg));cursor:not-allowed;" title="Email cannot be changed">
                    </div>
                    <div>
                        <div class="form-label-prof">Phone Number</div>
                        <input type="tel" name="phone" class="input-shop" placeholder="+91 00000 00000" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <div class="form-label-prof">Default Delivery Address</div>
                        <textarea name="address" class="input-shop" placeholder="Your delivery address..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-shop-primary" style="margin-top:20px;">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="profile-card fade-up d2">
            <div class="profile-section-title"><i class="bi bi-shield-lock"></i> Change Password</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div style="display:grid;gap:14px;">
                    <div>
                        <div class="form-label-prof">Current Password</div>
                        <div style="position:relative;">
                            <input type="password" name="current_password" class="input-shop" placeholder="Enter current password" required id="cp1">
                            <button type="button" onclick="togglePw('cp1','e1')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:15px;padding:4px;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'"><i class="bi bi-eye" id="e1"></i></button>
                        </div>
                    </div>
                    <div>
                        <div class="form-label-prof">New Password</div>
                        <div style="position:relative;">
                            <input type="password" name="new_password" class="input-shop" placeholder="At least 6 characters" required id="cp2">
                            <button type="button" onclick="togglePw('cp2','e2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:15px;padding:4px;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'"><i class="bi bi-eye" id="e2"></i></button>
                        </div>
                    </div>
                    <div>
                        <div class="form-label-prof">Confirm New Password</div>
                        <input type="password" name="confirm_password" class="input-shop" placeholder="Repeat new password" required>
                    </div>
                </div>
                <button type="submit" class="btn-shop-outline" style="margin-top:20px;">
                    <i class="bi bi-lock"></i> Change Password
                </button>
            </form>
        </div>

    </div>
</div>
</div>

<?php
$extra_js = '
<script>
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === "password" ? "text" : "password";
    icon.className = input.type === "password" ? "bi bi-eye" : "bi bi-eye-slash";
}
</script>';

require 'includes/shop_foot.php';
?>