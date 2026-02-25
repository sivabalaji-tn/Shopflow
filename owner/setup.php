<?php
session_start();
require '../config/db.php';

// Must be logged in as owner
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$shop_id  = $_SESSION['shop_id'];
$owner_id = $_SESSION['owner_id'];

// Check if already completed setup
$setup_done = $conn->query("SELECT setting_value FROM shop_settings WHERE shop_id=$shop_id AND setting_key='setup_complete'")->fetch_assoc();
if ($setup_done && $setup_done['setting_value'] === '1') {
    header("Location: dashboard.php");
    exit;
}

$shop = $conn->query("SELECT * FROM shops WHERE id=$shop_id")->fetch_assoc();
$step = (int)($_GET['step'] ?? 1);
$step = max(1, min(5, $step));
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1') { // Basic info
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $stmt = $conn->prepare("UPDATE shops SET name=?, description=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $desc, $shop_id);
        $stmt->execute();
        header("Location: setup.php?step=2");
        exit;

    } elseif ($action === 'step2') { // Logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $f   = $_FILES['logo'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $name = uniqid('logo_').'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], "../assets/uploads/logos/$name")) {
                    $stmt = $conn->prepare("UPDATE shops SET logo=? WHERE id=?");
                    $stmt->bind_param("si", $name, $shop_id);
                    $stmt->execute();
                }
            }
        }
        header("Location: setup.php?step=3");
        exit;

    } elseif ($action === 'step3') { // Theme
        $primary   = $_POST['primary'] ?? '#c8a97e';
        $secondary = $_POST['secondary'] ?? '#8b6428';
        $bg        = $_POST['bg'] ?? '#faf7f2';
        $font      = $_POST['font'] ?? 'Poppins';
        $stmt = $conn->prepare("UPDATE shops SET theme_primary=?, theme_secondary=?, theme_bg=?, theme_font=? WHERE id=?");
        $stmt->bind_param("ssssi", $primary, $secondary, $bg, $font, $shop_id);
        $stmt->execute();
        header("Location: setup.php?step=4");
        exit;

    } elseif ($action === 'step4') { // First category + product
        $cat_name = trim($_POST['category_name'] ?? '');
        if ($cat_name) {
            $stmt = $conn->prepare("INSERT INTO categories (shop_id, name, is_active) VALUES (?,?,1)");
            $stmt->bind_param("is", $shop_id, $cat_name);
            $stmt->execute();
            $cat_id = $stmt->insert_id;

            $prod_name  = trim($_POST['product_name'] ?? '');
            $prod_price = (float)($_POST['product_price'] ?? 0);
            $prod_stock = (int)($_POST['product_stock'] ?? 10);
            if ($prod_name && $prod_price > 0) {
                $stmt2 = $conn->prepare("INSERT INTO products (shop_id, category_id, name, price, stock, is_active) VALUES (?,?,?,?,?,1)");
                $stmt2->bind_param("iisdi", $shop_id, $cat_id, $prod_name, $prod_price, $prod_stock);
                $stmt2->execute();
            }
        }
        header("Location: setup.php?step=5");
        exit;

    } elseif ($action === 'complete') { // Finish
        $announcement = trim($_POST['announcement'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        if ($announcement) {
            $conn->query("UPDATE shops SET announcement='".addslashes($announcement)."', announcement_active=1 WHERE id=$shop_id");
        }
        if ($phone) {
            $conn->query("INSERT INTO shop_settings (shop_id,setting_key,setting_value) VALUES ($shop_id,'phone','".addslashes($phone)."') ON DUPLICATE KEY UPDATE setting_value='".addslashes($phone)."'");
        }
        $conn->query("INSERT INTO shop_settings (shop_id,setting_key,setting_value) VALUES ($shop_id,'setup_complete','1') ON DUPLICATE KEY UPDATE setting_value='1'");
        header("Location: dashboard.php?welcome=1");
        exit;
    }
}

// Refresh shop data
$shop = $conn->query("SELECT * FROM shops WHERE id=$shop_id")->fetch_assoc();
$steps_info = [
    1 => ['icon'=>'shop',        'title'=>'Shop Info',    'desc'=>'Name & description'],
    2 => ['icon'=>'image',       'title'=>'Logo',         'desc'=>'Upload your brand logo'],
    3 => ['icon'=>'palette2',    'title'=>'Theme',        'desc'=>'Pick your colors'],
    4 => ['icon'=>'box-seam',    'title'=>'First Product','desc'=>'Add a category & product'],
    5 => ['icon'=>'check-circle','title'=>'Finish',       'desc'=>'Final touches'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Your Shop &mdash; ShopFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --accent: #c8a97e;
            --accent-dim: rgba(200,169,126,0.12);
            --bg: #0d0b08;
            --card: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --text: #f0ece4;
            --muted: rgba(240,236,228,0.45);
        }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
        .bg-mesh {
            position:fixed; inset:0; z-index:0;
            background:
                radial-gradient(ellipse 60% 60% at 10% 10%, rgba(200,169,126,0.1) 0%, transparent 55%),
                radial-gradient(ellipse 60% 60% at 90% 90%, rgba(100,60,20,0.08) 0%, transparent 55%),
                var(--bg);
        }
        .setup-wrap { position:relative; z-index:1; max-width:640px; margin:0 auto; padding:40px 24px 60px; }

        /* Progress */
        .progress-bar-wrap {
            display:flex; gap:0; margin-bottom:44px;
            background:var(--card); border:1px solid var(--border);
            border-radius:16px; padding:6px;
        }
        .progress-step {
            flex:1; display:flex; flex-direction:column; align-items:center; gap:5px;
            padding:12px 8px; border-radius:12px; cursor:default;
            transition:all 0.25s;
        }
        .progress-step.done  { background:var(--accent-dim); }
        .progress-step.active { background:linear-gradient(135deg,rgba(200,169,126,0.2),rgba(200,169,126,0.08)); }
        .step-dot {
            width:32px; height:32px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:14px; transition:all 0.25s;
            border:1.5px solid var(--border);
            color:var(--muted);
        }
        .progress-step.done  .step-dot { background:var(--accent); border-color:var(--accent); color:#fff; }
        .progress-step.active .step-dot { border-color:var(--accent); color:var(--accent); box-shadow:0 0 0 4px rgba(200,169,126,0.15); }
        .step-label { font-size:11px; color:var(--muted); text-align:center; font-weight:500; }
        .progress-step.active .step-label, .progress-step.done .step-label { color:var(--accent); }

        /* Card */
        .setup-card {
            background:var(--card); border:1px solid var(--border);
            border-radius:20px; padding:36px;
            animation: fadeUp 0.4s ease;
        }
        @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
        .setup-title { font-family:'Syne',sans-serif; font-weight:800; font-size:26px; letter-spacing:-0.6px; margin-bottom:8px; }
        .setup-sub   { font-size:14.5px; color:var(--muted); margin-bottom:28px; line-height:1.6; }

        /* Form */
        .form-label { font-size:12.5px; font-weight:500; color:var(--muted); margin-bottom:7px; letter-spacing:0.3px; }
        .input {
            width:100%; padding:12px 16px;
            background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
            border-radius:10px; color:var(--text); font-family:'DM Sans',sans-serif;
            font-size:14px; outline:none; transition:all 0.2s;
        }
        .input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(200,169,126,0.12); background:rgba(200,169,126,0.05); }
        .input::placeholder { color:var(--muted); }
        textarea.input { resize:vertical; min-height:90px; }

        .btn-next {
            display:inline-flex; align-items:center; gap:8px;
            padding:13px 28px;
            background:linear-gradient(135deg,var(--accent),#8b6428);
            border:none; border-radius:12px;
            color:#fff; font-family:'Syne',sans-serif; font-weight:700; font-size:14px;
            cursor:pointer; transition:all 0.2s;
            box-shadow:0 4px 20px rgba(200,169,126,0.25);
        }
        .btn-next:hover { filter:brightness(1.1); transform:translateY(-1px); }
        .btn-skip {
            display:inline-flex; align-items:center; gap:6px;
            padding:12px 22px;
            background:transparent; border:1px solid var(--border);
            border-radius:12px; color:var(--muted);
            font-family:'DM Sans',sans-serif; font-size:14px;
            cursor:pointer; text-decoration:none; transition:all 0.2s;
        }
        .btn-skip:hover { background:rgba(255,255,255,0.05); color:var(--text); }

        /* Color pickers */
        .color-row { display:flex; gap:12px; flex-wrap:wrap; }
        .color-pick { display:flex; flex-direction:column; align-items:center; gap:8px; }
        .color-pick input[type=color] {
            width:52px; height:52px; border-radius:12px;
            border:1px solid var(--border); padding:2px;
            background:none; cursor:pointer;
        }
        .color-pick span { font-size:12px; color:var(--muted); }

        /* Font grid */
        .font-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .font-opt {
            padding:12px 14px; border-radius:10px;
            border:1.5px solid var(--border);
            cursor:pointer; transition:all 0.2s;
            display:flex; align-items:center; gap:10px;
        }
        .font-opt input[type=radio] { accent-color:var(--accent); }
        .font-opt:has(input:checked) { border-color:var(--accent); background:var(--accent-dim); }

        /* Upload zone */
        .upload-zone {
            border:2px dashed var(--border); border-radius:14px;
            padding:36px 20px; text-align:center; cursor:pointer;
            transition:all 0.2s; position:relative;
        }
        .upload-zone:hover { border-color:var(--accent); background:var(--accent-dim); }
        .upload-zone input[type=file] {
            position:absolute; inset:0; opacity:0; cursor:pointer;
        }
        .upload-zone i { font-size:36px; color:var(--accent); margin-bottom:10px; display:block; }
        .upload-zone p { font-size:13.5px; color:var(--muted); }

        /* Success animation */
        .confetti-wrap { text-align:center; padding:20px 0; }
        .confetti-icon { font-size:64px; animation:bounce 0.6s cubic-bezier(0.34,1.56,0.64,1); display:inline-block; }
        @keyframes bounce { from{transform:scale(0)} to{transform:scale(1)} }
    </style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="setup-wrap">

    <!-- Logo -->
    <div style="text-align:center;margin-bottom:28px;">
        <div style="display:inline-flex;align-items:center;gap:10px;">
            <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#8b6428);display:flex;align-items:center;justify-content:center;font-size:18px;">🛍</div>
            <span style="font-family:'Syne',sans-serif;font-weight:800;font-size:18px;">ShopFlow Setup</span>
        </div>
    </div>

    <!-- Progress Steps -->
    <div class="progress-bar-wrap">
        <?php foreach ($steps_info as $s => $info): ?>
        <div class="progress-step <?= $s < $step ? 'done' : ($s === $step ? 'active' : '') ?>">
            <div class="step-dot">
                <?php if ($s < $step): ?>
                <i class="bi bi-check-lg"></i>
                <?php else: ?>
                <i class="bi bi-<?= $info['icon'] ?>"></i>
                <?php endif; ?>
            </div>
            <div class="step-label"><?= $info['title'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Step Content -->
    <div class="setup-card">

    <?php if ($step === 1): ?>
        <div class="setup-title">Let's set up your shop 🚀</div>
        <p class="setup-sub">Start with the basics — what's your shop called and what do you sell?</p>
        <form method="POST" style="display:grid;gap:16px;">
            <input type="hidden" name="action" value="step1">
            <div>
                <div class="form-label">Shop Name *</div>
                <input type="text" name="name" class="input" value="<?= htmlspecialchars($shop['name']) ?>" placeholder="e.g. Priya's Bakery, Fresh Mart..." required>
            </div>
            <div>
                <div class="form-label">Shop Description</div>
                <textarea name="description" class="input" placeholder="Tell your customers what you offer..."><?= htmlspecialchars($shop['description'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="submit" class="btn-next">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>

    <?php elseif ($step === 2): ?>
        <div class="setup-title">Upload your logo</div>
        <p class="setup-sub">A great logo builds trust. You can always change this later in Settings.</p>
        <form method="POST" enctype="multipart/form-data" style="display:grid;gap:20px;">
            <input type="hidden" name="action" value="step2">
            <div class="upload-zone" id="uploadZone">
                <input type="file" name="logo" accept="image/*" onchange="previewLogo(this)">
                <i class="bi bi-cloud-upload" id="uploadIcon"></i>
                <p id="uploadText">Click to upload or drag &amp; drop<br><span style="font-size:12px;">PNG, JPG or WEBP &middot; max 5MB</span></p>
                <img id="logoPreview" style="display:none;max-height:100px;border-radius:10px;margin-top:12px;">
            </div>
            <div style="display:flex;gap:10px;justify-content:space-between;">
                <a href="setup.php?step=3" class="btn-skip">Skip for now</a>
                <button type="submit" class="btn-next">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>

    <?php elseif ($step === 3): ?>
        <div class="setup-title">Choose your theme 🎨</div>
        <p class="setup-sub">Pick colors and a font that match your brand. Your shop will update instantly.</p>
        <form method="POST" style="display:grid;gap:20px;">
            <input type="hidden" name="action" value="step3">
            <div>
                <div class="form-label" style="margin-bottom:12px;">Brand Colors</div>
                <div class="color-row">
                    <div class="color-pick">
                        <input type="color" name="primary"   value="<?= $shop['theme_primary'] ?? '#c8a97e' ?>">
                        <span>Primary</span>
                    </div>
                    <div class="color-pick">
                        <input type="color" name="secondary" value="<?= $shop['theme_secondary'] ?? '#8b6428' ?>">
                        <span>Secondary</span>
                    </div>
                    <div class="color-pick">
                        <input type="color" name="bg"        value="<?= $shop['theme_bg'] ?? '#faf7f2' ?>">
                        <span>Background</span>
                    </div>
                </div>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:12px;">Font Style</div>
                <div class="font-grid">
                    <?php
                    $fonts = ['Poppins'=>'Poppins','DM Sans'=>'DM Sans','Syne'=>'Syne','Nunito'=>'Nunito'];
                    foreach ($fonts as $val => $label):
                    ?>
                    <label class="font-opt">
                        <input type="radio" name="font" value="<?= $val ?>" <?= ($shop['theme_font'] ?? 'Poppins') === $val ? 'checked' : '' ?>>
                        <span style="font-family:'<?= $val ?>',sans-serif;font-size:14px;font-weight:600;"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:space-between;">
                <a href="setup.php?step=4" class="btn-skip">Skip</a>
                <button type="submit" class="btn-next">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>

    <?php elseif ($step === 4): ?>
        <div class="setup-title">Add your first product 📦</div>
        <p class="setup-sub">Create a category and add your first product to start selling right away.</p>
        <form method="POST" style="display:grid;gap:16px;">
            <input type="hidden" name="action" value="step4">
            <div style="padding:16px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;">
                <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--accent);margin-bottom:12px;">Category</div>
                <div class="form-label">Category Name</div>
                <input type="text" name="category_name" class="input" placeholder="e.g. Cakes, Beverages, Snacks...">
            </div>
            <div style="padding:16px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;">
                <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--accent);margin-bottom:12px;">First Product</div>
                <div style="display:grid;gap:12px;">
                    <div>
                        <div class="form-label">Product Name</div>
                        <input type="text" name="product_name" class="input" placeholder="e.g. Chocolate Truffle Cake">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <div class="form-label">Price (₹)</div>
                            <input type="number" name="product_price" class="input" placeholder="0.00" step="0.01">
                        </div>
                        <div>
                            <div class="form-label">Stock</div>
                            <input type="number" name="product_stock" class="input" placeholder="10" value="10">
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:space-between;">
                <a href="setup.php?step=5" class="btn-skip">Skip</a>
                <button type="submit" class="btn-next">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>

    <?php elseif ($step === 5): ?>
        <div class="confetti-wrap">
            <div class="confetti-icon">🎉</div>
        </div>
        <div class="setup-title" style="text-align:center;">Almost there!</div>
        <p class="setup-sub" style="text-align:center;">Just a couple final touches before we launch your shop.</p>
        <form method="POST" style="display:grid;gap:16px;">
            <input type="hidden" name="action" value="complete">
            <div>
                <div class="form-label">Announcement Bar Message</div>
                <input type="text" name="announcement" class="input" placeholder="e.g. 🎉 Free delivery on orders above ₹500!">
                <div style="font-size:12px;color:var(--muted);margin-top:5px;">Shown as a banner on top of your shop. Leave empty to skip.</div>
            </div>
            <div>
                <div class="form-label">Shop Phone Number</div>
                <input type="text" name="phone" class="input" placeholder="+91 00000 00000">
            </div>
            <div style="background:var(--accent-dim);border:1px solid rgba(200,169,126,0.2);border-radius:12px;padding:16px;margin-top:4px;">
                <div style="font-size:13.5px;font-weight:600;margin-bottom:6px;">Your shop URL 🔗</div>
                <code style="font-size:13px;color:var(--accent);">localhost/shopflow/shop/index.php?shop=<?= htmlspecialchars($shop['slug']) ?></code>
            </div>
            <button type="submit" class="btn-next" style="width:100%;justify-content:center;padding:15px;font-size:15px;margin-top:8px;">
                <i class="bi bi-rocket-takeoff"></i> Launch My Shop!
            </button>
        </form>
    <?php endif; ?>

    </div>

    <!-- Step indicator -->
    <div style="text-align:center;margin-top:20px;font-size:12.5px;color:var(--muted);">
        Step <?= $step ?> of 5
    </div>
</div>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('logoPreview').src = e.target.result;
            document.getElementById('logoPreview').style.display = 'block';
            document.getElementById('uploadIcon').style.display = 'none';
            document.getElementById('uploadText').style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
