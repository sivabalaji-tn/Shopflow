<?php
session_start();
require '../config/db.php';

$page_title    = 'Theme & Colors';
$page_subtitle = 'Customise your shop\'s look and feel in real time';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_theme') {
    $primary   = $_POST['theme_primary'];
    $secondary = $_POST['theme_secondary'];
    $bg        = $_POST['theme_bg'];
    $text_col  = $_POST['theme_text'];
    $font      = $_POST['theme_font'];

    // Basic color validation (hex)
    $hex_pattern = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
    if (preg_match($hex_pattern, $primary) && preg_match($hex_pattern, $secondary) && preg_match($hex_pattern, $bg)) {
        $stmt = $conn->prepare("UPDATE shops SET theme_primary=?, theme_secondary=?, theme_bg=?, theme_text=?, theme_font=? WHERE id=? AND owner_id=?");
        $stmt->bind_param("sssssii", $primary, $secondary, $bg, $text_col, $font, $shop_id, $_SESSION['owner_id']);
        $stmt->execute();
        $success = "Theme saved! Your shop now reflects the new colors.";
        // Refresh shop
        $s2 = $conn->prepare("SELECT * FROM shops WHERE id=?");
        $s2->bind_param("i", $shop_id);
        $s2->execute();
        $shop = $s2->get_result()->fetch_assoc();
    }
}

$fonts = [
    'Poppins'   => 'Poppins — Modern & Clean',
    'DM Sans'   => 'DM Sans — Friendly & Readable',
    'Syne'      => 'Syne — Bold & Editorial',
    'Lato'      => 'Lato — Professional & Neutral',
    'Playfair Display' => 'Playfair Display — Elegant & Luxury',
    'Nunito'    => 'Nunito — Soft & Rounded',
    'Raleway'   => 'Raleway — Stylish & Minimal',
    'Josefin Sans' => 'Josefin Sans — Geometric & Trendy',
];

$presets = [
    ['name'=>'Golden Hour',  'primary'=>'#c8a97e', 'secondary'=>'#8b6428', 'bg'=>'#faf7f2', 'text'=>'#1a1208'],
    ['name'=>'Midnight Blue','primary'=>'#3b82f6', 'secondary'=>'#1d4ed8', 'bg'=>'#f0f4ff', 'text'=>'#0f172a'],
    ['name'=>'Forest Green', 'primary'=>'#22c55e', 'secondary'=>'#15803d', 'bg'=>'#f0fdf4', 'text'=>'#052e16'],
    ['name'=>'Cherry Blossom','primary'=>'#ec4899','secondary'=>'#be185d','bg'=>'#fdf2f8','text'=>'#500724'],
    ['name'=>'Coral Sunset', 'primary'=>'#f97316', 'secondary'=>'#ea580c', 'bg'=>'#fff7ed', 'text'=>'#431407'],
    ['name'=>'Deep Purple',  'primary'=>'#8b5cf6', 'secondary'=>'#6d28d9', 'bg'=>'#f5f3ff', 'text'=>'#1e1b4b'],
    ['name'=>'Slate Dark',   'primary'=>'#64748b', 'secondary'=>'#334155', 'bg'=>'#f8fafc', 'text'=>'#0f172a'],
    ['name'=>'Rose Gold',    'primary'=>'#d97706', 'secondary'=>'#b45309', 'bg'=>'#fffbeb', 'text'=>'#1c1917'],
];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" id="themeForm">
    <input type="hidden" name="action" value="save_theme">
    <input type="hidden" name="theme_primary"   id="inp_primary"   value="<?= htmlspecialchars($shop['theme_primary'] ?? '#c8a97e') ?>">
    <input type="hidden" name="theme_secondary" id="inp_secondary" value="<?= htmlspecialchars($shop['theme_secondary'] ?? '#8b6428') ?>">
    <input type="hidden" name="theme_bg"        id="inp_bg"        value="<?= htmlspecialchars($shop['theme_bg'] ?? '#faf7f2') ?>">
    <input type="hidden" name="theme_text"      id="inp_text"      value="<?= htmlspecialchars($shop['theme_text'] ?? '#1a1208') ?>">
    <input type="hidden" name="theme_font"      id="inp_font"      value="<?= htmlspecialchars($shop['theme_font'] ?? 'Poppins') ?>">

    <div class="row g-3">

        <!-- Controls -->
        <div class="col-lg-5">

            <!-- Color Presets -->
            <div class="card-glass animate-in" style="margin-bottom:16px;">
                <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-stars" style="color:var(--accent);margin-right:8px;"></i>Quick Presets</div>
                <div class="section-sub" style="margin-bottom:18px;">Click to apply a ready-made theme</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <?php foreach ($presets as $preset): ?>
                    <button type="button" class="preset-btn" onclick="applyPreset('<?= $preset['primary'] ?>','<?= $preset['secondary'] ?>','<?= $preset['bg'] ?>','<?= $preset['text'] ?>')"
                        style="padding:12px 14px;border-radius:10px;border:1px solid var(--card-border);background:var(--card-bg);cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:10px;text-align:left;">
                        <div style="display:flex;gap:4px;flex-shrink:0;">
                            <div style="width:14px;height:14px;border-radius:50%;background:<?= $preset['primary'] ?>;"></div>
                            <div style="width:14px;height:14px;border-radius:50%;background:<?= $preset['secondary'] ?>;"></div>
                            <div style="width:14px;height:14px;border-radius:50%;background:<?= $preset['bg'] ?>;border:1px solid rgba(255,255,255,0.15);"></div>
                        </div>
                        <span style="font-size:12.5px;color:var(--text);font-weight:500;"><?= $preset['name'] ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Custom Colors -->
            <div class="card-glass animate-in d1" style="margin-bottom:16px;">
                <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-palette2" style="color:var(--accent);margin-right:8px;"></i>Custom Colors</div>
                <div class="section-sub" style="margin-bottom:18px;">Fine-tune each color individually</div>

                <div style="display:grid;gap:14px;">
                    <?php
                    $color_fields = [
                        ['id'=>'pick_primary',   'inp'=>'inp_primary',   'label'=>'Primary Color',   'hint'=>'Buttons, accents, links'],
                        ['id'=>'pick_secondary', 'inp'=>'inp_secondary', 'label'=>'Secondary Color', 'hint'=>'Hover states, gradients'],
                        ['id'=>'pick_bg',        'inp'=>'inp_bg',        'label'=>'Background Color','hint'=>'Main page background'],
                        ['id'=>'pick_text',      'inp'=>'inp_text',      'label'=>'Text Color',       'hint'=>'Body text color'],
                    ];
                    foreach ($color_fields as $cf):
                    ?>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <input type="color" id="<?= $cf['id'] ?>"
                            value="<?= htmlspecialchars($shop[$cf['inp'] === 'inp_primary' ? 'theme_primary' : ($cf['inp'] === 'inp_secondary' ? 'theme_secondary' : ($cf['inp'] === 'inp_bg' ? 'theme_bg' : 'theme_text'))] ?? '#000000') ?>"
                            oninput="syncColor(this, '<?= $cf['inp'] ?>')"
                            style="width:44px;height:44px;border-radius:10px;border:1px solid var(--card-border);cursor:pointer;padding:2px;background:none;">
                        <div>
                            <div style="font-size:13.5px;font-weight:500;"><?= $cf['label'] ?></div>
                            <div style="font-size:12px;color:var(--muted);"><?= $cf['hint'] ?></div>
                        </div>
                        <div style="margin-left:auto;font-family:monospace;font-size:13px;color:var(--muted);" id="<?= $cf['id'] ?>_hex">
                            <?= htmlspecialchars($shop[$cf['inp'] === 'inp_primary' ? 'theme_primary' : ($cf['inp'] === 'inp_secondary' ? 'theme_secondary' : ($cf['inp'] === 'inp_bg' ? 'theme_bg' : 'theme_text'))] ?? '#000000') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Font Selection -->
            <div class="card-glass animate-in d2">
                <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-type" style="color:var(--accent);margin-right:8px;"></i>Typography</div>
                <div class="section-sub" style="margin-bottom:16px;">Choose a font family for your shop</div>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($fonts as $font_val => $font_label): ?>
                    <label style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;border:1px solid;cursor:pointer;transition:all 0.2s;border-color:var(--card-border);"
                        class="font-option" data-font="<?= $font_val ?>">
                        <input type="radio" name="_font_display" value="<?= $font_val ?>"
                            <?= ($shop['theme_font'] ?? 'Poppins') === $font_val ? 'checked' : '' ?>
                            onchange="selectFont('<?= $font_val ?>')"
                            style="accent-color:var(--accent);">
                        <div>
                            <div style="font-family:'<?= $font_val ?>',sans-serif;font-size:14px;font-weight:600;"><?= $font_val ?></div>
                            <div style="font-size:11.5px;color:var(--muted);"><?= explode(' — ', $font_label)[1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Live Preview -->
        <div class="col-lg-7">
            <div class="card-glass animate-in d1" style="position:sticky;top:88px;">
                <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-eye" style="color:var(--accent);margin-right:8px;"></i>Live Preview</div>
                <div class="section-sub" style="margin-bottom:18px;">See how your shop will look in real time</div>

                <!-- Preview Frame -->
                <div id="previewFrame" style="border-radius:14px;overflow:hidden;border:1px solid var(--card-border);">

                    <!-- Preview Navbar -->
                    <div id="prev_navbar" style="padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(0,0,0,0.08);">
                        <div id="prev_brand" style="font-weight:800;font-size:16px;"><?= htmlspecialchars($shop['name']) ?></div>
                        <div style="display:flex;gap:8px;">
                            <div id="prev_btn" style="padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;">Shop Now</div>
                        </div>
                    </div>

                    <!-- Preview Hero -->
                    <div id="prev_hero" style="padding:36px 20px;text-align:center;border-bottom:1px solid rgba(0,0,0,0.06);">
                        <div id="prev_hero_title" style="font-size:22px;font-weight:800;margin-bottom:8px;"><?= htmlspecialchars($shop['name']) ?></div>
                        <div id="prev_hero_sub" style="font-size:14px;margin-bottom:20px;">Discover our amazing products</div>
                        <div id="prev_hero_btn" style="display:inline-block;padding:10px 24px;border-radius:10px;font-size:14px;font-weight:600;color:#fff;">Browse Products</div>
                    </div>

                    <!-- Preview Products -->
                    <div id="prev_products" style="padding:20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="prev-card" style="border-radius:10px;overflow:hidden;border:1px solid rgba(0,0,0,0.08);">
                            <div style="height:70px;background:rgba(0,0,0,0.06);"></div>
                            <div style="padding:10px;">
                                <div style="height:10px;border-radius:4px;background:rgba(0,0,0,0.1);margin-bottom:6px;width:80%;"></div>
                                <div style="height:8px;border-radius:4px;background:rgba(0,0,0,0.06);width:50%;"></div>
                                <div class="prev-card-btn" style="margin-top:10px;height:28px;border-radius:7px;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;">Add to Cart</div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Save Button -->
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;padding:13px;">
                        <i class="bi bi-check-circle"></i> Save Theme to Shop
                    </button>
                    <a href="../shop/index.php?shop=<?= $shop['slug'] ?>" target="_blank" class="btn-ghost-custom" style="padding:13px 18px;">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$extra_scripts = '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=DM+Sans:wght@400;500;600&family=Syne:wght@700;800&family=Lato:wght@400;700&family=Playfair+Display:wght@400;700&family=Nunito:wght@400;600;700&family=Raleway:wght@400;600;700&family=Josefin+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script>
let previewColors = {
    primary:   document.getElementById("inp_primary").value,
    secondary: document.getElementById("inp_secondary").value,
    bg:        document.getElementById("inp_bg").value,
    text:      document.getElementById("inp_text").value,
    font:      document.getElementById("inp_font").value
};

function updatePreview() {
    const p = previewColors;
    const frame = document.getElementById("previewFrame");
    frame.style.background  = p.bg;
    frame.style.color       = p.text;
    frame.style.fontFamily  = `"${p.font}", sans-serif`;

    document.getElementById("prev_navbar").style.background = p.bg;
    document.getElementById("prev_brand").style.color = p.text;
    document.getElementById("prev_btn").style.background = p.primary;

    document.getElementById("prev_hero").style.background = p.bg;
    document.getElementById("prev_hero_title").style.color = p.text;
    document.getElementById("prev_hero_sub").style.color = p.text + "99";
    document.getElementById("prev_hero_btn").style.background = p.primary;

    document.getElementById("prev_products").style.background = p.bg;
    document.querySelectorAll(".prev-card").forEach(c => { c.style.background = p.bg; });
    document.querySelectorAll(".prev-card-btn").forEach(b => { b.style.background = p.primary; });
}

function syncColor(picker, inputId) {
    const val = picker.value;
    document.getElementById(inputId).value = val;
    document.getElementById(picker.id + "_hex").textContent = val;

    if (inputId === "inp_primary")   previewColors.primary   = val;
    if (inputId === "inp_secondary") previewColors.secondary = val;
    if (inputId === "inp_bg")        previewColors.bg        = val;
    if (inputId === "inp_text")      previewColors.text      = val;
    updatePreview();
}

function applyPreset(p, s, bg, t) {
    document.getElementById("inp_primary").value   = p;
    document.getElementById("inp_secondary").value = s;
    document.getElementById("inp_bg").value        = bg;
    document.getElementById("inp_text").value      = t;
    document.getElementById("pick_primary").value   = p;
    document.getElementById("pick_secondary").value = s;
    document.getElementById("pick_bg").value        = bg;
    document.getElementById("pick_text").value      = t;
    ["pick_primary","pick_secondary","pick_bg","pick_text"].forEach(id => {
        const inp  = document.getElementById(id);
        const map  = {"pick_primary":"inp_primary","pick_secondary":"inp_secondary","pick_bg":"inp_bg","pick_text":"inp_text"};
        document.getElementById(id+"_hex").textContent = inp.value;
    });
    previewColors = { primary:p, secondary:s, bg:bg, text:t, font:previewColors.font };
    updatePreview();

    // Highlight preset button briefly
    document.querySelectorAll(".preset-btn").forEach(b => b.style.borderColor="var(--card-border)");
    event.currentTarget.style.borderColor = "var(--accent)";
}

function selectFont(font) {
    document.getElementById("inp_font").value = font;
    previewColors.font = font;
    updatePreview();
}

// Init preview
updatePreview();

// Preset button hover
document.querySelectorAll(".preset-btn").forEach(b => {
    b.addEventListener("mouseenter", () => b.style.borderColor="rgba(200,169,126,0.3)");
    b.addEventListener("mouseleave", () => {
        if (b.style.borderColor !== "var(--accent)") b.style.borderColor="var(--card-border)";
    });
});
</script>';

require 'includes/footer.php';
?>
