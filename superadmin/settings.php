<?php
session_start();
require '../config/db.php';

$page_title    = 'Platform Settings';
$page_subtitle = 'Control global platform behaviour';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $settings = [
            'site_name'           => trim($_POST['site_name'] ?? 'TamizhMart'),
            'contact_email'       => trim($_POST['contact_email'] ?? ''),
            'maintenance_mode'    => isset($_POST['maintenance_mode']) ? '1' : '0',
            'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
            'registration_open'   => isset($_POST['registration_open']) ? '1' : '0',
        ];
        foreach ($settings as $key => $val) {
            $k = mysqli_real_escape_string($conn, $key);
            $v = mysqli_real_escape_string($conn, $val);
            $conn->query("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('$k','$v') ON DUPLICATE KEY UPDATE setting_value='$v'");
        }
        $success = "Platform settings saved.";

    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $admin = $conn->query("SELECT password FROM super_admins WHERE id=".(int)$_SESSION['superadmin_id'])->fetch_assoc();
        if (!password_verify($current, $admin['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_pass) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($new_pass !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $conn->query("UPDATE super_admins SET password='$hash' WHERE id=".(int)$_SESSION['superadmin_id']);
            $success = "Password changed successfully.";
        }
    }
}

require __DIR__ . '/includes/sidebar.php';

// Load settings
$settings_map = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM platform_settings");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

// Platform stats for info cards
$total_shops   = $conn->query("SELECT COUNT(*) FROM shops")->fetch_row()[0];
$total_users   = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_owners  = $conn->query("SELECT COUNT(*) FROM owners")->fetch_row()[0];
$db_size       = $conn->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema='tamizhmart_db'")->fetch_row()[0];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-flash alert-flash-danger animate-in"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- General Settings -->
    <div class="col-lg-7">
        <div class="card-glass animate-in" style="margin-bottom:20px;">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-sliders" style="color:var(--accent);margin-right:8px;"></i>General Settings</div>
            <div class="section-sub" style="margin-bottom:22px;">Basic platform configuration</div>

            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                <div style="display:grid;gap:16px;">
                    <div>
                        <div class="form-label-custom">Platform Name</div>
                        <input type="text" name="site_name" class="input-custom"
                            value="<?= htmlspecialchars($settings_map['site_name'] ?? 'TamizhMart') ?>" required>
                    </div>
                    <div>
                        <div class="form-label-custom">Contact / Support Email</div>
                        <input type="email" name="contact_email" class="input-custom"
                            value="<?= htmlspecialchars($settings_map['contact_email'] ?? '') ?>"
                            placeholder="admin@tamizhmart.com">
                    </div>

                    <!-- Maintenance Mode -->
                    <div style="background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.15);border-radius:var(--radius-sm);padding:16px;">
                        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-bottom:12px;">
                            <input type="checkbox" name="maintenance_mode" value="1"
                                <?= ($settings_map['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>
                                style="width:18px;height:18px;accent-color:var(--warning);">
                            <div>
                                <div style="font-weight:700;font-size:14px;color:var(--warning);">
                                    <i class="bi bi-cone-striped"></i> Maintenance Mode
                                </div>
                                <div style="font-size:12.5px;color:var(--muted);margin-top:2px;">
                                    All customer storefronts will show a maintenance message
                                </div>
                            </div>
                        </label>
                        <div>
                            <div class="form-label-custom">Maintenance Message</div>
                            <input type="text" name="maintenance_message" class="input-custom"
                                value="<?= htmlspecialchars($settings_map['maintenance_message'] ?? 'We are under maintenance. Back soon!') ?>"
                                placeholder="Message shown to customers...">
                        </div>
                    </div>

                    <!-- Registration -->
                    <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:14px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                        <input type="checkbox" name="registration_open" value="1"
                            <?= ($settings_map['registration_open'] ?? '1') === '1' ? 'checked' : '' ?>
                            style="width:18px;height:18px;accent-color:var(--accent);">
                        <div>
                            <div style="font-weight:600;font-size:13.5px;">Open Registration</div>
                            <div style="font-size:12.5px;color:var(--muted);margin-top:2px;">
                                Allow new shop owners to register. Uncheck to make it invite-only.
                            </div>
                        </div>
                    </label>

                    <button type="submit" class="btn-primary-custom" style="justify-content:center;padding:12px;">
                        <i class="bi bi-check-circle"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card-glass animate-in d1">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-key" style="color:var(--accent);margin-right:8px;"></i>Change Admin Password</div>
            <div class="section-sub" style="margin-bottom:22px;">Keep your control panel secure</div>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div style="display:grid;gap:14px;">
                    <div>
                        <div class="form-label-custom">Current Password</div>
                        <input type="password" name="current_password" class="input-custom" required>
                    </div>
                    <div>
                        <div class="form-label-custom">New Password (min 8 characters)</div>
                        <input type="password" name="new_password" class="input-custom" required minlength="8">
                    </div>
                    <div>
                        <div class="form-label-custom">Confirm New Password</div>
                        <input type="password" name="confirm_password" class="input-custom" required>
                    </div>
                    <button type="submit" class="btn-primary-custom" style="justify-content:center;padding:12px;">
                        <i class="bi bi-shield-lock"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="col-lg-5">
        <!-- Platform Info -->
        <div class="card-glass animate-in d1" style="margin-bottom:16px;">
            <div class="section-title" style="margin-bottom:16px;"><i class="bi bi-info-circle" style="color:var(--accent);margin-right:8px;"></i>Platform Info</div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php
                $info_rows = [
                    ['icon'=>'shop','label'=>'Total Shops','val'=>$total_shops,'color'=>'var(--accent)'],
                    ['icon'=>'person-badge','label'=>'Shop Owners','val'=>$total_owners,'color'=>'var(--accent2)'],
                    ['icon'=>'people','label'=>'Total Customers','val'=>$total_users,'color'=>'var(--info)'],
                    ['icon'=>'database','label'=>'Database Size','val'=>($db_size ?? '0').' MB','color'=>'var(--warning)'],
                ];
                foreach ($info_rows as $row):
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:rgba(255,255,255,0.02);border-radius:10px;border:1px solid var(--card-border);">
                    <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--muted);">
                        <i class="bi bi-<?= $row['icon'] ?>" style="color:<?= $row['color'] ?>;font-size:16px;"></i>
                        <?= $row['label'] ?>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:16px;color:<?= $row['color'] ?>;"><?= $row['val'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Current Status -->
        <div class="card-glass animate-in d2" style="margin-bottom:16px;">
            <div class="section-title" style="margin-bottom:16px;"><i class="bi bi-activity" style="color:var(--accent);margin-right:8px;"></i>Current Status</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:rgba(255,255,255,0.02);border-radius:10px;border:1px solid var(--card-border);">
                    <span style="font-size:13.5px;color:var(--muted);">Maintenance Mode</span>
                    <?php if (($settings_map['maintenance_mode'] ?? '0') === '1'): ?>
                    <span class="badge-custom badge-warning"><i class="bi bi-cone-striped"></i> ON</span>
                    <?php else: ?>
                    <span class="badge-custom badge-success"><i class="bi bi-check-circle"></i> OFF</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:rgba(255,255,255,0.02);border-radius:10px;border:1px solid var(--card-border);">
                    <span style="font-size:13.5px;color:var(--muted);">New Registrations</span>
                    <?php if (($settings_map['registration_open'] ?? '1') === '1'): ?>
                    <span class="badge-custom badge-success"><i class="bi bi-door-open"></i> Open</span>
                    <?php else: ?>
                    <span class="badge-custom badge-danger"><i class="bi bi-door-closed"></i> Closed</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:rgba(255,255,255,0.02);border-radius:10px;border:1px solid var(--card-border);">
                    <span style="font-size:13.5px;color:var(--muted);">Admin Account</span>
                    <span style="font-size:13px;font-weight:600;color:var(--accent2);"><?= htmlspecialchars($_SESSION['superadmin_email']) ?></span>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="card-glass animate-in d3" style="border-color:rgba(248,113,113,0.15);">
            <div class="section-title" style="margin-bottom:4px;color:var(--danger);"><i class="bi bi-exclamation-triangle-fill" style="margin-right:8px;"></i>Danger Zone</div>
            <div class="section-sub" style="margin-bottom:16px;">Irreversible actions — proceed with caution</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="padding:14px;background:var(--danger-dim);border:1px solid rgba(248,113,113,0.15);border-radius:var(--radius-sm);">
                    <div style="font-weight:600;font-size:13.5px;margin-bottom:4px;">Clear All Cart Data</div>
                    <div style="font-size:12.5px;color:var(--muted);margin-bottom:12px;">Remove all abandoned cart items across the platform</div>
                    <form method="POST" onsubmit="return confirm('Clear all cart data? This cannot be undone.')">
                        <input type="hidden" name="action" value="clear_carts">
                        <button type="submit" class="btn-danger-custom" style="font-size:13px;">
                            <i class="bi bi-trash3"></i> Clear All Carts
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Handle danger zone actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_carts') {
    $conn->query("DELETE FROM cart");
}
require __DIR__ . '/includes/footer.php';
?>