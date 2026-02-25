<?php
session_start();
require '../config/db.php';

$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
if (!$slug) { header("Location: ../index.php"); exit; }

$stmt = $conn->prepare("SELECT * FROM shops WHERE slug=? AND is_active=1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
if (!$shop) { http_response_code(404); die('<h2 style="text-align:center;margin-top:60px;font-family:sans-serif;">Shop not found.</h2>'); }

$_SESSION['current_shop_slug'] = $slug;
$shop_id = $shop['id'];

// Settings
$settings_map = [];
$sr = $conn->query("SELECT setting_key,setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

// Search
$q = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);

// Categories
$categories = $conn->query("SELECT * FROM categories WHERE shop_id=$shop_id AND is_active=1 ORDER BY name");

// Featured / all products
$where = "p.shop_id=$shop_id AND p.is_active=1 AND p.stock>0";
if ($q) $where .= " AND p.name LIKE '%" . $conn->real_escape_string($q) . "%'";
if ($cat_filter) $where .= " AND p.category_id=$cat_filter";
$products = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $where ORDER BY p.created_at DESC LIMIT 12");

$page_title = $q ? "Search: $q" : null;

require 'includes/shop_head.php';
?>

<style>
/* ── Hero ── */
.hero-section {
    position: relative;
    overflow: hidden;
    padding: 0;
    margin-bottom: 56px;
}
.hero-banner {
    width: 100%;
    height: clamp(300px, 45vw, 520px);
    object-fit: cover;
    display: block;
}
.hero-no-banner {
    height: clamp(280px, 40vw, 460px);
    background: linear-gradient(135deg,
        color-mix(in srgb, var(--primary) 18%, var(--bg)),
        color-mix(in srgb, var(--secondary) 12%, var(--bg))
    );
    display: flex;
    align-items: center;
}
.hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to right,
        color-mix(in srgb, var(--text) 65%, transparent) 0%,
        color-mix(in srgb, var(--text) 20%, transparent) 50%,
        transparent 100%
    );
}
.hero-content {
    position: absolute;
    top: 50%; left: 0;
    transform: translateY(-50%);
    padding: 0 60px;
    max-width: 600px;
    color: #fff;
}
.hero-content.no-banner { position: relative; color: var(--text); padding: 0 48px; transform: none; top: auto; left: auto; }
.hero-eyebrow {
    font-size: 12px; font-weight: 600; letter-spacing: 2px;
    text-transform: uppercase;
    opacity: 0.75; margin-bottom: 12px;
}
.hero-title {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: clamp(28px, 4vw, 52px);
    line-height: 1.1;
    letter-spacing: -1.5px;
    margin-bottom: 16px;
    text-shadow: 0 2px 20px rgba(0,0,0,0.1);
}
.hero-sub { font-size: 15px; opacity: 0.8; margin-bottom: 28px; line-height: 1.6; max-width: 380px; }
.hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

/* ── Section Heading ── */
.section-head {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
}
.section-title {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: clamp(20px, 2.5vw, 26px);
    letter-spacing: -0.6px;
}
.section-sub { font-size: 13.5px; color: var(--text-muted); margin-top: 3px; }

/* ── Category chips ── */
.category-scroll {
    display: flex; gap: 10px;
    overflow-x: auto; padding-bottom: 6px;
    scrollbar-width: none;
    margin-bottom: 36px;
}
.category-scroll::-webkit-scrollbar { display: none; }
.cat-chip {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 18px;
    border-radius: 99px;
    background: var(--card-bg);
    border: 1.5px solid var(--border);
    color: var(--text-muted);
    text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    white-space: nowrap;
    transition: var(--transition);
    flex-shrink: 0;
}
.cat-chip:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
.cat-chip.active { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: 600; }
.cat-chip-img {
    width: 22px; height: 22px; border-radius: 50%;
    object-fit: cover;
}

/* ── Category card grid ── */
.cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; margin-bottom: 56px; }
.cat-card {
    border-radius: var(--radius);
    overflow: hidden;
    text-decoration: none;
    color: var(--text);
    border: 1px solid var(--border);
    transition: var(--transition);
    background: var(--card-bg);
    display: flex; flex-direction: column;
}
.cat-card:hover { transform: translateY(-4px); box-shadow: var(--card-shadow-hover); border-color: var(--primary); }
.cat-card-img { width: 100%; aspect-ratio: 4/3; object-fit: cover; background: var(--primary-light); display: flex; align-items: center; justify-content: center; }
.cat-card-img img { width: 100%; height: 100%; object-fit: cover; }
.cat-card-img i { font-size: 32px; color: var(--primary-glow); }
.cat-card-body { padding: 12px 14px; }
.cat-card-name { font-weight: 600; font-size: 14px; }
.cat-card-count { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

/* ── Product grid ── */
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }

/* ── Search result bar ── */
.search-bar-result {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px;
    background: var(--primary-light);
    border-radius: var(--radius);
    margin-bottom: 20px;
    border: 1px solid var(--primary-mid);
}

/* ── Promo strip ── */
.promo-strip {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: var(--radius-lg);
    padding: 36px 44px;
    color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    gap: 24px; flex-wrap: wrap;
    margin-bottom: 56px;
    overflow: hidden;
    position: relative;
}
.promo-strip::before {
    content: '';
    position: absolute;
    right: -40px; top: -40px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.promo-strip::after {
    content: '';
    position: absolute;
    right: 60px; bottom: -60px;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}
.promo-text h3 { font-family:'Syne',sans-serif; font-weight:800; font-size:22px; margin-bottom:6px; }
.promo-text p { font-size:14px; opacity:0.85; }

@media (max-width: 768px) {
    .hero-content { padding: 0 24px; max-width: 100%; }
    .hero-title { font-size: 26px; }
    .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .cat-grid { grid-template-columns: repeat(3, 1fr); }
    .promo-strip { padding: 24px; }
}
@media (max-width: 480px) {
    .products-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .cat-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php if ($q || $cat_filter): ?>
<!-- ── SEARCH / FILTER RESULTS ── -->
<div class="shop-container" style="padding-top:32px;padding-bottom:60px;">
    <?php if ($q): ?>
    <div class="search-bar-result fade-up">
        <i class="bi bi-search" style="color:var(--primary);font-size:18px;"></i>
        <div>
            <span style="font-weight:600;">Results for "<?= htmlspecialchars($q) ?>"</span>
            <span style="color:var(--text-muted);font-size:13px;margin-left:8px;"><?= $products->num_rows ?> product<?= $products->num_rows != 1 ? 's' : '' ?></span>
        </div>
        <a href="index.php?shop=<?= $slug ?>" class="btn-shop-ghost" style="margin-left:auto;"><i class="bi bi-x"></i> Clear</a>
    </div>
    <?php endif; ?>

    <!-- Category filter chips -->
    <div class="category-scroll fade-up d1">
        <a href="index.php?shop=<?= $slug ?>" class="cat-chip <?= !$cat_filter ? 'active' : '' ?>">All</a>
        <?php
        $cats_all = $conn->query("SELECT * FROM categories WHERE shop_id=$shop_id AND is_active=1 ORDER BY name");
        while ($c = $cats_all->fetch_assoc()):
        ?>
        <a href="index.php?shop=<?= $slug ?>&cat=<?= $c['id'] ?><?= $q ? '&q='.urlencode($q) : '' ?>" class="cat-chip <?= $cat_filter == $c['id'] ? 'active' : '' ?>">
            <?php if ($c['image']): ?>
            <img src="../assets/uploads/products/<?= htmlspecialchars($c['image']) ?>" class="cat-chip-img" alt="">
            <?php endif; ?>
            <?= htmlspecialchars($c['name']) ?>
        </a>
        <?php endwhile; ?>
    </div>

    <?php if ($products->num_rows === 0): ?>
    <div style="text-align:center;padding:80px 20px;">
        <i class="bi bi-search" style="font-size:56px;color:var(--text-faint);display:block;margin-bottom:16px;"></i>
        <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:22px;margin-bottom:10px;">No products found</h3>
        <p style="color:var(--text-muted);font-size:14.5px;">Try different keywords or browse all products.</p>
        <a href="index.php?shop=<?= $slug ?>" class="btn-shop-primary" style="margin-top:20px;">Browse All</a>
    </div>
    <?php else: ?>
    <div class="products-grid">
        <?php $i = 0; while ($p = $products->fetch_assoc()): $i++; ?>
        <?php include 'includes/product_card.php'; ?>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ── HOMEPAGE ── -->

<!-- Hero -->
<section class="hero-section fade-up">
    <?php if ($shop['banner']): ?>
    <img src="../assets/uploads/banners/<?= htmlspecialchars($shop['banner']) ?>" class="hero-banner" alt="<?= htmlspecialchars($shop['name']) ?>">
    <div class="hero-overlay"></div>
    <div class="hero-content">
    <?php else: ?>
    <div class="hero-no-banner">
    <div class="hero-content no-banner">
    <?php endif; ?>
        <div class="hero-eyebrow">Welcome to <?= htmlspecialchars($shop['name']) ?></div>
        <h1 class="hero-title"><?= htmlspecialchars($shop['name']) ?></h1>
        <?php if ($shop['description']): ?>
        <p class="hero-sub"><?= htmlspecialchars($shop['description']) ?></p>
        <?php else: ?>
        <p class="hero-sub">Discover our amazing selection of products, crafted just for you.</p>
        <?php endif; ?>
        <div class="hero-actions">
            <a href="products.php?shop=<?= $slug ?>" class="btn-shop-primary" <?= $shop['banner'] ? 'style="background:#fff;color:var(--primary);"' : '' ?>>
                <i class="bi bi-grid"></i> Shop All Products
            </a>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="../auth/register.php?shop=<?= $slug ?>" class="btn-shop-outline" <?= $shop['banner'] ? 'style="border-color:rgba(255,255,255,0.5);color:#fff;"' : '' ?>>
                <i class="bi bi-person-plus"></i> Join Us
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$shop['banner']): ?></div><?php endif; ?>
</section>

<div class="shop-container">

    <!-- Categories -->
    <?php
    $cats_data = [];
    $cats_r = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.is_active=1) as pcount FROM categories c WHERE c.shop_id=$shop_id AND c.is_active=1 ORDER BY c.name");
    while ($c = $cats_r->fetch_assoc()) $cats_data[] = $c;
    if (!empty($cats_data)):
    ?>
    <section class="fade-up d1">
        <div class="section-head">
            <div>
                <div class="section-title">Browse Categories</div>
                <div class="section-sub">Find exactly what you're looking for</div>
            </div>
        </div>
        <div class="cat-grid">
            <?php foreach ($cats_data as $ci => $c): ?>
            <a href="products.php?shop=<?= $slug ?>&cat=<?= $c['id'] ?>" class="cat-card fade-up" style="animation-delay:<?= ($ci * 0.05) ?>s;">
                <div class="cat-card-img">
                    <?php if ($c['image']): ?>
                    <img src="../assets/uploads/products/<?= htmlspecialchars($c['image']) ?>" alt="<?= htmlspecialchars($c['name']) ?>">
                    <?php else: ?>
                    <i class="bi bi-tags"></i>
                    <?php endif; ?>
                </div>
                <div class="cat-card-body">
                    <div class="cat-card-name"><?= htmlspecialchars($c['name']) ?></div>
                    <div class="cat-card-count"><?= $c['pcount'] ?> item<?= $c['pcount'] != 1 ? 's' : '' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Products -->
    <?php if ($products->num_rows > 0): ?>
    <section class="fade-up d2">
        <div class="section-head">
            <div>
                <div class="section-title">Featured Products</div>
                <div class="section-sub">Our latest arrivals</div>
            </div>
            <a href="products.php?shop=<?= $slug ?>" class="btn-shop-ghost">
                View all <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="products-grid">
            <?php $i = 0; while ($p = $products->fetch_assoc()): $i++; ?>
            <?php include 'includes/product_card.php'; ?>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Promo Strip (if announcement exists) -->
    <?php if ($shop['announcement_active'] && $shop['announcement']): ?>
    <section class="promo-strip fade-up d3" style="margin-top:48px;">
        <div class="promo-text" style="position:relative;z-index:1;">
            <h3><i class="bi bi-megaphone-fill"></i> Special Offer</h3>
            <p><?= htmlspecialchars($shop['announcement']) ?></p>
        </div>
        <a href="products.php?shop=<?= $slug ?>" class="btn-shop-primary" style="background:#fff;color:var(--primary);position:relative;z-index:1;flex-shrink:0;">
            Shop Now <i class="bi bi-arrow-right"></i>
        </a>
    </section>
    <?php endif; ?>

</div>
<?php endif; ?>

<?php require 'includes/shop_foot.php'; ?>
