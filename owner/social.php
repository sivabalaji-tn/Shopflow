<?php
session_start();
require '../config/db.php';

$page_title    = 'Social Links';
$page_subtitle = 'Connect your social media and contact channels';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

// Load existing settings
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'whatsapp'  => $_POST['whatsapp']  ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'facebook'  => $_POST['facebook']  ?? '',
        'twitter'   => $_POST['twitter']   ?? '',
        'youtube'   => $_POST['youtube']   ?? '',
        'website'   => $_POST['website']   ?? '',
        'email_contact' => $_POST['email_contact'] ?? '',
        'phone'     => $_POST['phone']     ?? '',
        'address'   => $_POST['address']   ?? '',
    ];

    foreach ($fields as $key => $value) {
        $k = $conn->real_escape_string($key);
        $v = $conn->real_escape_string(trim($value));
        $conn->query("INSERT INTO shop_settings (shop_id, setting_key, setting_value) VALUES ($shop_id, '$k', '$v')
            ON DUPLICATE KEY UPDATE setting_value='$v'");
    }
    $settings = array_merge($settings, $fields);
    $success  = 'Social links saved successfully.';
}

$socials = [
    ['key'=>'whatsapp',     'icon'=>'whatsapp',     'label'=>'WhatsApp Number',    'placeholder'=>'+91 98765 43210',                 'prefix'=>'',          'color'=>'#25d366'],
    ['key'=>'instagram',    'icon'=>'instagram',    'label'=>'Instagram Handle',   'placeholder'=>'@yourshop',                      'prefix'=>'instagram.com/', 'color'=>'#e1306c'],
    ['key'=>'facebook',     'icon'=>'facebook',     'label'=>'Facebook Page',      'placeholder'=>'facebook.com/yourshop',           'prefix'=>'',          'color'=>'#1877f2'],
    ['key'=>'twitter',      'icon'=>'twitter-x',    'label'=>'X (Twitter) Handle', 'placeholder'=>'@yourshop',                      'prefix'=>'x.com/',    'color'=>'#000'],
    ['key'=>'youtube',      'icon'=>'youtube',      'label'=>'YouTube Channel',    'placeholder'=>'youtube.com/@yourshop',           'prefix'=>'',          'color'=>'#ff0000'],
    ['key'=>'website',      'icon'=>'globe2',       'label'=>'Website URL',        'placeholder'=>'https://yourwebsite.com',         'prefix'=>'',          'color'=>'#6366f1'],
    ['key'=>'email_contact','icon'=>'envelope',     'label'=>'Contact Email',      'placeholder'=>'hello@yourshop.com',             'prefix'=>'',          'color'=>'#f59e0b'],
    ['key'=>'phone',        'icon'=>'telephone',    'label'=>'Phone Number',       'placeholder'=>'+91 98765 43210',                'prefix'=>'',          'color'=>'#10b981'],
    ['key'=>'address',      'icon'=>'geo-alt',      'label'=>'Shop Address',       'placeholder'=>'123 Main St, Chennai, Tamil Nadu','prefix'=>'',          'color'=>'#ef4444'],
];
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-glass animate-in">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-share" style="color:var(--accent);margin-right:8px;"></i>Social Media & Contact</div>
            <div class="section-sub" style="margin-bottom:26px;">These links appear in your shop footer and help customers connect with you.</div>
            <form method="POST">
                <div style="display:grid;gap:16px;">
                    <?php foreach ($socials as $s): ?>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:<?= $s['color'] ?>1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-<?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:18px;"></i>
                        </div>
                        <div style="flex:1;">
                            <div class="form-label-custom"><?= $s['label'] ?></div>
                            <input type="text" name="<?= $s['key'] ?>"
                                class="input-custom"
                                placeholder="<?= $s['placeholder'] ?>"
                                value="<?= htmlspecialchars($settings[$s['key']] ?? '') ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn-primary-custom" style="margin-top:24px;">
                    <i class="bi bi-check-lg"></i> Save All Links
                </button>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-glass animate-in d1" style="border-color:rgba(200,169,126,0.15);">
            <div style="font-size:13.5px;font-weight:600;margin-bottom:14px;"><i class="bi bi-eye" style="color:var(--accent);margin-right:6px;"></i>Preview in Footer</div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($socials as $s):
                    if (empty($settings[$s['key']])) continue;
                ?>
                <div style="display:flex;align-items:center;gap:7px;padding:8px 12px;background:rgba(255,255,255,0.04);border:1px solid var(--card-border);border-radius:8px;font-size:13px;">
                    <i class="bi bi-<?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;"></i>
                    <span style="color:var(--muted);"><?= $s['label'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty(array_filter(array_map(fn($s) => $settings[$s['key']] ?? '', $socials)))): ?>
                <div style="color:var(--muted2);font-size:13px;padding:8px 0;">Fill in any field to see it here.</div>
                <?php endif; ?>
            </div>
            <div style="margin-top:20px;padding:14px;background:var(--accent-dim);border:1px solid rgba(200,169,126,0.15);border-radius:10px;font-size:13px;color:var(--muted);">
                <i class="bi bi-info-circle" style="color:var(--accent);margin-right:6px;"></i>
                Social links automatically appear in your shop's footer once saved.
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
