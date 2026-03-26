<?php
session_start();
require '../config/db.php';

$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
if (!$slug) { header("Location: ../index.php"); exit; }
$stmt = $conn->prepare("SELECT * FROM shops WHERE slug=? AND is_active=1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
if (!$shop) die('<h2 style="text-align:center;margin-top:60px;">The Shop has some issues Kindly Visit Back later.</h2>');
$_SESSION['current_shop_slug'] = $slug;
$shop_id = $shop['id'];

$settings_map = [];
$sr = $conn->query("SELECT setting_key,setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

$q          = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$sort       = $_GET['sort'] ?? 'newest';
$min_price  = (float)($_GET['min'] ?? 0);
$max_price  = (float)($_GET['max'] ?? 0);
$page       = max(1, (int)($_GET['p'] ?? 1));
$per_page   = 16;
$offset     = ($page - 1) * $per_page;

$sort_sql = match($sort) {
    'price_asc'  => 'COALESCE(p.discount_price,p.price) ASC',
    'price_desc' => 'COALESCE(p.discount_price,p.price) DESC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC',
};

$where = "p.shop_id=$shop_id AND p.is_active=1";
if ($q)          $where .= " AND p.name LIKE '%" . $conn->real_escape_string($q) . "%'";
if ($cat_filter) $where .= " AND p.category_id=$cat_filter";
if ($min_price)  $where .= " AND COALESCE(p.discount_price,p.price) >= $min_price";
if ($max_price)  $where .= " AND COALESCE(p.discount_price,p.price) <= $max_price";

$total = $conn->query("SELECT COUNT(*) as c FROM products p WHERE $where")->fetch_assoc()['c'];
$total_pages = ceil($total / $per_page);

$products = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $where ORDER BY $sort_sql LIMIT $per_page OFFSET $offset");

// Price range for filter
$price_range = $conn->query("SELECT MIN(COALESCE(discount_price,price)) as mn, MAX(price) as mx FROM products WHERE shop_id=$shop_id AND is_active=1")->fetch_assoc();

$categories = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.is_active=1) as pcount FROM categories c WHERE c.shop_id=$shop_id AND c.is_active=1 ORDER BY c.name");

$page_title = $cat_filter ? null : ($q ? "Search: $q" : "All Products");
require 'includes/shop_head.php';
?>

<style>
.products-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 28px;
    padding: 32px 0 60px;
}
.filter-sidebar {
    position: sticky;
    top: calc(var(--navbar-h) + 20px);
    height: fit-content;
}
.filter-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 14px;
}
.filter-title {
    font-weight: 700; font-size: 14px;
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
}
.filter-title i { color: var(--primary); }
.filter-cat-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 10px; border-radius: var(--radius-sm);
    text-decoration: none; color: var(--text-muted);
    font-size: 13.5px; font-weight: 500;
    transition: var(--transition);
    margin-bottom: 2px;
}
.filter-cat-item:hover { background: var(--primary-light); color: var(--primary); }
.filter-cat-item.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }
.filter-cat-count { font-size: 11.5px; background: var(--border); padding: 1px 7px; border-radius: 99px; }
.products-main {}
.products-topbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; gap: 12px; flex-wrap: wrap;
}
.sort-select {
    padding: 8px 14px; border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    background: var(--card-bg); color: var(--text);
    font-family: inherit; font-size: 13.5px;
    outline: none; cursor: pointer;
    transition: var(--transition);
}
.sort-select:focus { border-color: var(--primary); }
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }

/* Price range slider */
.range-track {
    position: relative; height: 4px;
    background: var(--border); border-radius: 99px;
    margin: 12px 4px 8px;
}
.range-fill {
    position: absolute; height: 100%;
    background: var(--primary); border-radius: 99px;
    pointer-events: none;
}
.range-input {
    -webkit-appearance: none;
    position: absolute; width: 100%;
    height: 4px; background: none; outline: none;
    pointer-events: none;
}
.range-input::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: var(--primary);
    border: 2px solid var(--bg);
    cursor: pointer; pointer-events: all;
    box-shadow: 0 2px 8px var(--primary-glow);
    transition: transform 0.15s;
}
.range-input::-webkit-slider-thumb:hover { transform: scale(1.2); }

/* Mobile filter toggle */
.filter-toggle-btn { display: none; }
@media (max-width: 900px) {
    .products-layout { grid-template-columns: 1fr; }
    .filter-sidebar { position: fixed; top:0; left:0; bottom:0; z-index:300; width:280px; background:var(--bg); padding:24px; overflow-y:auto; transform:translateX(-100%); transition:transform 0.3s; }
    .filter-sidebar.open { transform:translateX(0); box-shadow:8px 0 40px rgba(0,0,0,0.15); }
    .filter-toggle-btn { display:inline-flex; }
}
@media (max-width: 480px) {
    .products-grid { grid-template-columns: repeat(2,1fr); gap:10px; }
}
</style>

<div class="shop-container">
    <div class="products-layout">

        <!-- Sidebar Filters -->
        <aside class="filter-sidebar" id="filterSidebar">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:16px;">Filters</div>
                <button onclick="toggleFilters()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;padding:4px;" class="d-block d-lg-none"><i class="bi bi-x-lg"></i></button>
            </div>

            <!-- Categories -->
            <div class="filter-card">
                <div class="filter-title"><i class="bi bi-tags"></i> Categories</div>
                <a href="products.php?shop=<?= $slug ?>" class="filter-cat-item <?= !$cat_filter ? 'active' : '' ?>">
                    All Products <span class="filter-cat-count"><?= $total ?></span>
                </a>
                <?php
                $cats = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.is_active=1) as pcount FROM categories c WHERE c.shop_id=$shop_id AND c.is_active=1 ORDER BY c.name");
                while ($c = $cats->fetch_assoc()):
                ?>
                <a href="products.php?shop=<?= $slug ?>&cat=<?= $c['id'] ?><?= $q ? '&q='.urlencode($q) : '' ?>&sort=<?= $sort ?>" class="filter-cat-item <?= $cat_filter == $c['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($c['name']) ?>
                    <span class="filter-cat-count"><?= $c['pcount'] ?></span>
                </a>
                <?php endwhile; ?>
            </div>

            <!-- Price Range -->
            <?php if ($price_range['mx'] > 0): ?>
            <div class="filter-card">
                <div class="filter-title"><i class="bi bi-currency-rupee"></i> Price Range</div>
                <form method="GET" id="priceForm">
                    <input type="hidden" name="shop" value="<?= $slug ?>">
                    <?php if ($cat_filter): ?><input type="hidden" name="cat" value="<?= $cat_filter ?>"><?php endif; ?>
                    <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                    <input type="hidden" name="sort" value="<?= $sort ?>">
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-muted);margin-bottom:8px;">
                        <span id="minLabel">&#8377;<?= number_format($min_price ?: $price_range['mn']) ?></span>
                        <span id="maxLabel">&#8377;<?= number_format($max_price ?: $price_range['mx']) ?></span>
                    </div>
                    <div class="range-track">
                        <div class="range-fill" id="rangeFill"></div>
                        <input type="range" class="range-input" id="minRange" name="min"
                            min="<?= floor($price_range['mn']) ?>" max="<?= ceil($price_range['mx']) ?>"
                            value="<?= $min_price ?: floor($price_range['mn']) ?>" oninput="updateRange()">
                        <input type="range" class="range-input" id="maxRange" name="max"
                            min="<?= floor($price_range['mn']) ?>" max="<?= ceil($price_range['mx']) ?>"
                            value="<?= $max_price ?: ceil($price_range['mx']) ?>" oninput="updateRange()">
                    </div>
                    <button type="submit" class="btn-shop-primary" style="width:100%;justify-content:center;margin-top:14px;font-size:13px;padding:9px;">
                        Apply Price Filter
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($q || $cat_filter || $min_price || $max_price): ?>
            <a href="products.php?shop=<?= $slug ?>" class="btn-shop-ghost" style="width:100%;justify-content:center;padding:10px;">
                <i class="bi bi-x-circle"></i> Clear All Filters
            </a>
            <?php endif; ?>
        </aside>

        <!-- Products Main -->
        <div class="products-main">
            <!-- Topbar -->
            <div class="products-topbar fade-up">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button onclick="toggleFilters()" class="btn-shop-outline filter-toggle-btn" style="padding:8px 16px;font-size:13.5px;">
                        <i class="bi bi-sliders"></i> Filters
                    </button>
                    <span style="font-size:13.5px;color:var(--text-muted);">
                        <strong style="color:var(--text);"><?= $total ?></strong> product<?= $total != 1 ? 's' : '' ?>
                        <?= $cat_filter || $q ? ' found' : '' ?>
                    </span>
                </div>
                <form method="GET" id="sortForm">
                    <input type="hidden" name="shop" value="<?= $slug ?>">
                    <?php if ($cat_filter): ?><input type="hidden" name="cat" value="<?= $cat_filter ?>"><?php endif; ?>
                    <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                    <?php if ($min_price): ?><input type="hidden" name="min" value="<?= $min_price ?>"><?php endif; ?>
                    <?php if ($max_price): ?><input type="hidden" name="max" value="<?= $max_price ?>"><?php endif; ?>
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
                    </select>
                </form>
            </div>

            <!-- Grid -->
            <?php if ($products->num_rows === 0): ?>
            <div style="text-align:center;padding:80px 20px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);">
                <i class="bi bi-box-seam" style="font-size:52px;color:var(--text-faint);display:block;margin-bottom:16px;"></i>
                <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:20px;margin-bottom:10px;">No Products Found</h3>
                <p style="color:var(--text-muted);font-size:14px;">Try adjusting your filters or search terms.</p>
            </div>
            <?php else: ?>
            <div class="products-grid">
                <?php $i = 0; while ($p = $products->fetch_assoc()): $i++; ?>
                <?php include 'includes/product_card.php'; ?>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:36px;flex-wrap:wrap;" class="fade-up">
                <?php for ($pg = 1; $pg <= $total_pages; $pg++): ?>
                <a href="?shop=<?= $slug ?>&cat=<?= $cat_filter ?>&q=<?= urlencode($q) ?>&sort=<?= $sort ?>&min=<?= $min_price ?>&max=<?= $max_price ?>&p=<?= $pg ?>"
                    style="width:40px;height:40px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px;font-weight:600;border:1.5px solid;transition:var(--transition);
                    <?= $pg == $page ? 'background:var(--primary);border-color:var(--primary);color:#fff;' : 'background:var(--card-bg);border-color:var(--border);color:var(--text-muted);' ?>">
                    <?= $pg ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter sidebar overlay -->
<div id="filterOverlay" onclick="toggleFilters()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:299;backdrop-filter:blur(3px);"></div>

<?php
$extra_js = '
<script>
function toggleFilters() {
    const sb = document.getElementById("filterSidebar");
    const ov = document.getElementById("filterOverlay");
    sb.classList.toggle("open");
    ov.style.display = sb.classList.contains("open") ? "block" : "none";
    document.body.style.overflow = sb.classList.contains("open") ? "hidden" : "";
}

function updateRange() {
    const min = parseInt(document.getElementById("minRange").value);
    const max = parseInt(document.getElementById("maxRange").value);
    const absMin = parseInt(document.getElementById("minRange").min);
    const absMax = parseInt(document.getElementById("maxRange").max);
    if (min > max) return;
    const fillLeft  = ((min - absMin) / (absMax - absMin)) * 100;
    const fillRight = ((max - absMin) / (absMax - absMin)) * 100;
    document.getElementById("rangeFill").style.left  = fillLeft + "%";
    document.getElementById("rangeFill").style.right = (100 - fillRight) + "%";
    document.getElementById("minLabel").textContent = "₹" + min.toLocaleString();
    document.getElementById("maxLabel").textContent = "₹" + max.toLocaleString();
}
updateRange();
</script>';

require 'includes/shop_foot.php';
?>
