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
                                /<?= htmlspecialchars($shop['slug']) ?>
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
                    localhost/shopflow/shop/index.php?shop=<?= htmlspecialchars($shop['slug']) ?>
                </span>
                <button onclick="navigator.clipboard.writeText('localhost/shopflow/shop/index.php?shop=<?= $shop['slug'] ?>').then(()=>this.innerHTML='<i class=\'bi bi-check-lg\'></i>')"
                    style="background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;font-size:14px;transition:color 0.2s;" title="Copy">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<?php
$extra_scripts = '
<script>
// Announcement toggle visual
document.getElementById("annToggle").addEventListener("change", function() {
    const slider = document.getElementById("annSlider");
    const thumb  = document.getElementById("annThumb");
    slider.style.background = this.checked ? "var(--accent)" : "rgba(255,255,255,0.1)";
    thumb.style.left = this.checked ? "23px" : "3px";
});
</script>';

require 'includes/footer.php';
?>
