<?php
session_start();
require '../config/db.php';

$page_title    = 'Store Settings';
$page_subtitle = 'Manage your shop information and branding';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $name         = trim($_POST['name']);
        $description  = trim($_POST['description']);
        $phone        = trim($_POST['phone'] ?? '');
        $address_text = trim($_POST['address_setting'] ?? '');
        $announcement = trim($_POST['announcement'] ?? '');
        $ann_active   = isset($_POST['announcement_active']) ? 1 : 0;

        // Update shop basic info
        $stmt = $conn->prepare("UPDATE shops SET name=?, description=?, announcement=?, announcement_active=? WHERE id=? AND owner_id=?");
        $stmt->bind_param("sssiii", $name, $description, $announcement, $ann_active, $shop_id, $_SESSION['owner_id']);
        $stmt->execute();

        // Update shop_settings for phone and address
        foreach (['phone'=>$phone, 'address'=>$address_text] as $k=>$v) {
            $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value) VALUES ($shop_id, '$k', '" . $conn->real_escape_string($v) . "')
                ON DUPLICATE KEY UPDATE setting_value = '" . $conn->real_escape_string($v) . "'");
        }

        $success = "Store information updated.";
        // Refresh shop
        $s2 = $conn->prepare("SELECT * FROM shops WHERE id=?");
        $s2->bind_param("i", $shop_id);
        $s2->execute();
        $shop = $s2->get_result()->fetch_assoc();

    } elseif ($action === 'update_logo') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $f   = $_FILES['logo'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $name = uniqid('logo_').'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], "../assets/uploads/logos/$name")) {
                    $stmt = $conn->prepare("UPDATE shops SET logo=? WHERE id=?");
                    $stmt->bind_param("si", $name, $shop_id);
                    $stmt->execute();
                    $shop['logo'] = $name;
                    $success = "Logo updated.";
                }
            } else { $error = "Only JPG, PNG, WEBP allowed."; }
        }

    } elseif ($action === 'update_banner') {
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === 0) {
            $f   = $_FILES['banner'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $name = uniqid('banner_').'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], "../assets/uploads/banners/$name")) {
                    $stmt = $conn->prepare("UPDATE shops SET banner=? WHERE id=?");
                    $stmt->bind_param("si", $name, $shop_id);
                    $stmt->execute();
                    $shop['banner'] = $name;
                    $success = "Banner updated.";
                }
            } else { $error = "Only JPG, PNG, WEBP allowed."; }
        }

    } elseif ($action === 'remove_logo') {
        $conn->query("UPDATE shops SET logo=NULL WHERE id=$shop_id");
        $shop['logo'] = null;
        $success = "Logo removed.";

    } elseif ($action === 'remove_banner') {
        $conn->query("UPDATE shops SET banner=NULL WHERE id=$shop_id");
        $shop['banner'] = null;
        $success = "Banner removed.";

    } elseif ($action === 'update_razorpay') {
        $rz_enabled = isset($_POST['razorpay_enabled']) ? '1' : '0';
        $rz_key_id  = trim($_POST['razorpay_key_id'] ?? '');
        $rz_secret  = trim($_POST['razorpay_key_secret'] ?? '');

        if ($rz_enabled === '1' && (!str_starts_with($rz_key_id, 'rzp_') || empty($rz_secret))) {
            $error = "Please enter valid Razorpay Key ID (starts with rzp_) and Secret.";
        } else {
            foreach ([
                'razorpay_enabled'    => $rz_enabled,
                'razorpay_key_id'     => $rz_key_id,
                'razorpay_key_secret' => $rz_secret,
            ] as $k => $v) {
                $v_esc = $conn->real_escape_string($v);
                $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value)
                              VALUES ($shop_id, '$k', '$v_esc')
                              ON DUPLICATE KEY UPDATE setting_value='$v_esc'");
            }
            $success = "Razorpay settings saved.";
        }
    }
}

// Fetch extra settings
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php elseif ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- Left Column -->
    <div class="col-lg-7">

        <!-- Basic Info -->
        <div class="card-glass animate-in" style="margin-bottom:16px;">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-shop" style="color:var(--accent);margin-right:8px;"></i>Shop Information</div>
            <div class="section-sub" style="margin-bottom:22px;">Basic details visible to your customers</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_info">
                <div style="display:grid;gap:14px;">
                    <div>
                        <div class="form-label-custom">Shop Name *</div>
                        <input type="text" name="name" class="input-custom" value="<?= htmlspecialchars($shop['name']) ?>" required>
                    </div>
                    <div>
                        <div class="form-label-custom">Description</div>
                        <textarea name="description" class="input-custom" placeholder="Tell customers about your shop..."><?= htmlspecialchars($shop['description'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <div class="form-label-custom">Phone Number</div>
                            <input type="text" name="phone" class="input-custom" placeholder="+91 00000 00000" value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                        </div>
                        <div>
                            <div class="form-label-custom">Shop Slug (URL)</div>
                            <div style="padding:11px 14px;background:rgba(255,255,255,0.03);border:1px solid var(--card-border);border-radius:var(--radius-sm);font-size:13.5px;color:var(--muted);">
                                /<?= htmlspecialchars($shop['slug']) ?> &nbsp;<small style="color:#aaa">(clean URL)</small>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="form-label-custom">Shop Address</div>
                        <textarea name="address_setting" class="input-custom" placeholder="Physical address (shown in footer)" style="min-height:70px;"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                    </div>

                    <!-- Announcement Bar -->
                    <div style="padding:16px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <div>
                                <div style="font-size:13.5px;font-weight:500;">Announcement Bar</div>
                                <div style="font-size:12px;color:var(--muted);">Shown at the top of your shop page</div>
                            </div>
                            <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                                <input type="checkbox" name="announcement_active" value="1" <?= $shop['announcement_active'] ? 'checked' : '' ?> style="opacity:0;width:0;height:0;" id="annToggle">
                                <span id="annSlider" style="position:absolute;inset:0;border-radius:99px;background:<?= $shop['announcement_active'] ? 'var(--accent)' : 'rgba(255,255,255,0.1)' ?>;transition:0.3s;">
                                    <span style="position:absolute;height:18px;width:18px;border-radius:50%;background:#fff;top:3px;left:<?= $shop['announcement_active'] ? '23px' : '3px' ?>;transition:0.3s;" id="annThumb"></span>
                                </span>
                            </label>
                        </div>
                        <input type="text" name="announcement" class="input-custom" placeholder="e.g. Free delivery on orders above ₹500!" value="<?= htmlspecialchars($shop['announcement'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary-custom" style="margin-top:20px;">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
            </form>
        </div>

    </div>

    <!-- Right Column -->
    <div class="col-lg-5">

        <!-- Logo Upload -->
        <div class="card-glass animate-in d1" style="margin-bottom:16px;">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-image" style="color:var(--accent);margin-right:8px;"></i>Shop Logo</div>
            <div class="section-sub" style="margin-bottom:20px;">Shown in your shop's navbar and header</div>

            <div style="display:flex;flex-direction:column;align-items:center;gap:16px;text-align:center;">
                <div style="width:100px;height:100px;border-radius:20px;background:var(--card-bg);border:1px solid var(--card-border);overflow:hidden;display:flex;align-items:center;justify-content:center;">
                    <?php if ($shop['logo']): ?>
                    <img src="../assets/uploads/logos/<?= htmlspecialchars($shop['logo']) ?>" style="width:100%;height:100%;object-fit:cover;" id="logoPreviewImg">
                    <?php else: ?>
                    <i class="bi bi-shop" style="font-size:40px;color:rgba(200,169,126,0.3);" id="logoPlaceholder"></i>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" style="width:100%;">
                    <input type="hidden" name="action" value="update_logo">
                    <input type="file" name="logo" id="logoInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <button type="button" class="btn-primary-custom" onclick="document.getElementById('logoInput').click()">
                            <i class="bi bi-upload"></i> Upload Logo
                        </button>
                        <?php if ($shop['logo']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove_logo">
                            <button type="submit" class="btn-danger-custom"><i class="bi bi-trash3"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </form>
                <div style="font-size:12px;color:var(--muted);">PNG, JPG or WEBP &middot; max 5MB &middot; recommended 200&times;200px</div>
            </div>
        </div>

        <!-- Banner Upload -->
        <div class="card-glass animate-in d2">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-panorama" style="color:var(--accent);margin-right:8px;"></i>Shop Banner</div>
            <div class="section-sub" style="margin-bottom:20px;">Hero image on your shop's home page</div>

            <div style="display:flex;flex-direction:column;align-items:center;gap:16px;text-align:center;">
                <div style="width:100%;height:120px;border-radius:12px;background:var(--card-bg);border:1px solid var(--card-border);overflow:hidden;display:flex;align-items:center;justify-content:center;">
                    <?php if ($shop['banner']): ?>
                    <img src="../assets/uploads/banners/<?= htmlspecialchars($shop['banner']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <i class="bi bi-image" style="font-size:36px;color:rgba(200,169,126,0.2);"></i>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" style="width:100%;">
                    <input type="hidden" name="action" value="update_banner">
                    <input type="file" name="banner" id="bannerInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <button type="button" class="btn-primary-custom" onclick="document.getElementById('bannerInput').click()">
                            <i class="bi bi-upload"></i> Upload Banner
                        </button>
                        <?php if ($shop['banner']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove_banner">
                            <button type="submit" class="btn-danger-custom"><i class="bi bi-trash3"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </form>
                <div style="font-size:12px;color:var(--muted);">Recommended 1280&times;400px &middot; max 5MB</div>
            </div>
        </div>

        <!-- Shop URL Info -->
        <div class="card-glass animate-in d3" style="margin-top:16px;border-color:rgba(200,169,126,0.15);">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:var(--accent);font-weight:600;margin-bottom:10px;">Your Shop URL</div>
            <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                <i class="bi bi-link-45deg" style="color:var(--accent);flex-shrink:0;"></i>
                <span style="font-size:13px;color:var(--muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    shop.tamizhmart.optikl.ink/<?= htmlspecialchars($shop['slug']) ?>
                </span>
                <button onclick="navigator.clipboard.writeText('shop.tamizhmart.optikl.ink/<?= $shop['slug'] ?>').then(()=>this.innerHTML='<i class=\'bi bi-check-lg\'></i>')"
                    style="background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;font-size:14px;transition:color 0.2s;" title="Copy">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     RAZORPAY PAYMENT SETTINGS CARD
     ═══════════════════════════════════════════════════ -->
<div style="margin-top:24px;" class="animate-in d3">
    <div class="card-glass" style="border-color:rgba(0,123,255,0.15);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(0,123,255,0.1);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-credit-card-2-front" style="color:#0d6efd;font-size:18px;"></i>
            </div>
            <div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;">Online Payment — Razorpay</div>
                <div style="font-size:12.5px;color:var(--muted);">Accept UPI, Cards, Net Banking &amp; Wallets from your customers</div>
            </div>
            <span style="margin-left:auto;font-size:11px;font-weight:700;background:#072654;color:#fff;padding:4px 10px;border-radius:5px;letter-spacing:0.5px;">RAZORPAY</span>
        </div>
        <hr style="border-color:var(--card-border);margin:16px 0;">

        <?php
        $rz = [
            'enabled' => $settings['razorpay_enabled'] ?? '0',
            'key_id'  => $settings['razorpay_key_id'] ?? '',
            'secret'  => $settings['razorpay_key_secret'] ?? '',
        ];
        ?>

        <!-- Status badge -->
        <?php if ($rz['enabled'] === '1' && !empty($rz['key_id'])): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:#16a34a;padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:600;margin-bottom:16px;">
            <span style="width:7px;height:7px;background:#16a34a;border-radius:50%;display:inline-block;"></span>
            Online payments active
        </div>
        <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(107,114,128,0.1);border:1px solid rgba(107,114,128,0.2);color:var(--muted);padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:600;margin-bottom:16px;">
            <span style="width:7px;height:7px;background:var(--muted);border-radius:50%;display:inline-block;"></span>
            Not enabled — only COD available
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="update_razorpay">
            <div style="display:flex;flex-direction:column;gap:16px;">

                <!-- Enable toggle -->
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:14px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="razorpay_enabled" value="1" id="rzToggle"
                        <?= $rz['enabled'] === '1' ? 'checked' : '' ?>
                        onchange="document.getElementById('rzFields').style.display=this.checked?'flex':'none'"
                        style="width:18px;height:18px;accent-color:#0d6efd;flex-shrink:0;">
                    <div>
                        <div style="font-weight:600;font-size:14px;">Enable Razorpay for my shop</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:1px;">Customers will see "Pay Online" option at checkout</div>
                    </div>
                </label>

                <!-- Key fields (hidden when disabled) -->
                <div id="rzFields" style="display:<?= $rz['enabled'] === '1' ? 'flex' : 'none' ?>;flex-direction:column;gap:14px;">

                    <div style="background:rgba(255,193,7,0.08);border:1px solid rgba(255,193,7,0.25);border-radius:var(--radius-sm);padding:12px 14px;font-size:12.5px;color:var(--text);">
                        <i class="bi bi-info-circle" style="color:#d97706;margin-right:6px;"></i>
                        Get your keys from <a href="https://dashboard.razorpay.com/app/keys" target="_blank" style="color:#0d6efd;font-weight:600;">Razorpay Dashboard → Settings → API Keys</a>.
                        Use <strong>Test keys</strong> first, switch to <strong>Live keys</strong> when ready.
                    </div>

                    <div>
                        <div style="font-size:12.5px;font-weight:500;color:var(--muted);margin-bottom:7px;">Key ID <span style="color:#888;">(starts with rzp_test_ or rzp_live_)</span></div>
                        <input type="text" name="razorpay_key_id" class="input-custom"
                            placeholder="rzp_live_xxxxxxxxxxxx"
                            value="<?= htmlspecialchars($rz['key_id']) ?>"
                            autocomplete="off">
                    </div>

                    <div>
                        <div style="font-size:12.5px;font-weight:500;color:var(--muted);margin-bottom:7px;">Key Secret <span style="color:#e74c3c;">⚠ Never share this</span></div>
                        <div style="position:relative;">
                            <input type="password" name="razorpay_key_secret" id="rzSecret" class="input-custom"
                                placeholder="Your Razorpay secret key"
                                value="<?= htmlspecialchars($rz['secret']) ?>"
                                autocomplete="new-password"
                                style="padding-right:44px;">
                            <button type="button" onclick="toggleSecret()"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;" title="Show/hide">
                                <i class="bi bi-eye" id="rzEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($rz['key_id'])): ?>
                    <div style="background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.2);border-radius:var(--radius-sm);padding:10px 14px;font-size:12.5px;">
                        <i class="bi bi-check-circle" style="color:#16a34a;margin-right:5px;"></i>
                        Keys saved. Mode: <strong><?= str_contains($rz['key_id'], '_test_') ? '🧪 Test Mode' : '🟢 Live Mode' ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-primary-custom" style="justify-content:center;padding:12px;">
                    <i class="bi bi-save"></i> Save Payment Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSecret() {
    const f = document.getElementById('rzSecret');
    const i = document.getElementById('rzEyeIcon');
    if (f.type === 'password') { f.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                        { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>

<?php require 'includes/footer.php'; ?>