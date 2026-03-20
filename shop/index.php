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

$settings_map = [];
$sr = $conn->query("SELECT setting_key,setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

$q          = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);

// Categories with product count
$cats_data = [];
$cats_r = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.is_active=1 AND p.stock>0) as pcount FROM categories c WHERE c.shop_id=$shop_id AND c.is_active=1 ORDER BY c.name");
while ($c = $cats_r->fetch_assoc()) $cats_data[] = $c;

// Products
$where = "p.shop_id=$shop_id AND p.is_active=1 AND p.stock>0";
if ($q)          $where .= " AND p.name LIKE '%" . $conn->real_escape_string($q) . "%'";
if ($cat_filter) $where .= " AND p.category_id=$cat_filter";
$products = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $where ORDER BY p.created_at DESC LIMIT 12");

// Best sellers
$bestsellers = $conn->query("
    SELECT p.*, c.name as cat_name, COALESCE(SUM(oi.quantity),0) as sold
    FROM products p
    LEFT JOIN categories c ON p.category_id=c.id
    LEFT JOIN order_items oi ON oi.product_id=p.id
    WHERE p.shop_id=$shop_id AND p.is_active=1 AND p.stock>0
    GROUP BY p.id ORDER BY sold DESC LIMIT 4
");

// Stats
$total_prods = $conn->query("SELECT COUNT(*) FROM products WHERE shop_id=$shop_id AND is_active=1")->fetch_row()[0];
$total_custs = $conn->query("SELECT COUNT(*) FROM users WHERE shop_id=$shop_id")->fetch_row()[0];
$total_ords  = $conn->query("SELECT COUNT(*) FROM orders WHERE shop_id=$shop_id AND status='delivered'")->fetch_row()[0];

$page_title = $q ? "Search: $q" : null;
require 'includes/shop_head.php';
?>

<style>
/* ── Announcement ticker ── */
.ticker-bar{background:var(--primary);color:#fff;font-size:12.5px;font-weight:600;letter-spacing:.3px;padding:9px 0;overflow:hidden;white-space:nowrap;}
.ticker-inner{display:inline-flex;animation:ticker 30s linear infinite;}
.ticker-inner span{padding:0 40px;display:inline-flex;align-items:center;gap:10px;}
.ticker-inner span::before{content:'✦';font-size:10px;opacity:.6;}
@keyframes ticker{from{transform:translateX(0)}to{transform:translateX(-50%)}}

/* ── Hero ── */
.hero{position:relative;overflow:hidden;min-height:clamp(380px,50vw,580px);display:flex;flex-direction:column;}
.hero-media{position:absolute;inset:0;}
.hero-banner-img{width:100%;height:100%;object-fit:cover;}
.hero-scrim{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.65) 0%,rgba(0,0,0,.3) 55%,rgba(0,0,0,.05) 100%);}
.hero-bg-grad{position:absolute;inset:0;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,var(--bg)) 0%,var(--bg) 65%);}
.hero-body{position:relative;z-index:2;flex:1;display:flex;align-items:center;max-width:1200px;margin:0 auto;padding:64px 40px;width:100%;}
.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.2);padding:6px 14px;border-radius:99px;font-size:12px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#fff;margin-bottom:20px;animation:fadeUp .6s ease both;}
.hero-title{font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:clamp(32px,5vw,60px);line-height:1.06;letter-spacing:-2px;color:#fff;margin-bottom:18px;max-width:560px;animation:fadeUp .6s .1s ease both;text-shadow:0 2px 30px rgba(0,0,0,.12);}
.hero-title .aw{color:var(--primary);}
.hero-sub{font-size:15.5px;color:rgba(255,255,255,.75);line-height:1.7;max-width:420px;margin-bottom:32px;animation:fadeUp .6s .2s ease both;}
.hero-ctas{display:flex;gap:14px;flex-wrap:wrap;animation:fadeUp .6s .3s ease both;}
.hero-btn-p{display:inline-flex;align-items:center;gap:9px;padding:14px 28px;background:var(--primary);color:#fff;border-radius:12px;font-weight:700;font-size:14px;text-decoration:none;transition:all .25s;box-shadow:0 8px 28px rgba(0,0,0,.2);}
.hero-btn-p:hover{transform:translateY(-2px);filter:brightness(1.1);color:#fff;box-shadow:0 12px 32px rgba(0,0,0,.25);}
.hero-btn-g{display:inline-flex;align-items:center;gap:9px;padding:13px 26px;background:rgba(255,255,255,.1);backdrop-filter:blur(8px);border:1.5px solid rgba(255,255,255,.3);color:#fff;border-radius:12px;font-weight:600;font-size:14px;text-decoration:none;transition:all .25s;}
.hero-btn-g:hover{background:rgba(255,255,255,.18);color:#fff;transform:translateY(-2px);}
/* no-banner */
.hero-nobanner .hero-eyebrow{background:var(--primary-light);border-color:var(--primary-mid);color:var(--primary);}
.hero-nobanner .hero-title{color:var(--text);}
.hero-nobanner .hero-sub{color:var(--text-muted);}
.hero-nobanner .hero-btn-g{background:var(--card-bg);border-color:var(--border);color:var(--text);}
.hero-nobanner .hero-btn-g:hover{background:var(--primary-light);color:var(--primary);}

/* stats bar */
.hero-stats{position:relative;z-index:2;background:rgba(0,0,0,.35);backdrop-filter:blur(16px);border-top:1px solid rgba(255,255,255,.08);}
.hero-stats-inner{max-width:1200px;margin:0 auto;padding:18px 40px;display:flex;align-items:center;}
.hs-item{flex:1;text-align:center;padding:0 20px;border-right:1px solid rgba(255,255,255,.1);}
.hs-item:last-child{border-right:none;}
.hs-val{font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:22px;color:#fff;}
.hs-label{font-size:11.5px;color:rgba(255,255,255,.45);margin-top:2px;}

/* page wrapper */
.sfp{max-width:1200px;margin:0 auto;padding:0 28px;}

/* section */
.sfs{margin-bottom:60px;}
.sfs-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:28px;gap:16px;}
.sfs-tag{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--primary);margin-bottom:6px;display:flex;align-items:center;gap:6px;}
.sfs-tag::before{content:'';width:20px;height:2px;background:var(--primary);border-radius:99px;display:inline-block;}
.sfs-title{font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:clamp(22px,2.5vw,30px);letter-spacing:-.8px;color:var(--text);}
.sfs-all{display:inline-flex;align-items:center;gap:6px;font-size:13.5px;font-weight:600;color:var(--primary);text-decoration:none;padding:8px 16px;border:1.5px solid var(--primary-mid);border-radius:99px;transition:all .2s;white-space:nowrap;}
.sfs-all:hover{background:var(--primary);color:#fff;border-color:var(--primary);}

/* trust */
.trust-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;margin-bottom:52px;}
.trust-item{background:var(--card-bg);padding:22px 18px;display:flex;align-items:center;gap:14px;}
.trust-icon{width:44px;height:44px;border-radius:12px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--primary);flex-shrink:0;}
.trust-label{font-weight:700;font-size:13.5px;}
.trust-sub{font-size:12px;color:var(--text-muted);margin-top:2px;}

/* categories */
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(138px,1fr));gap:14px;}
.cat-tile{border-radius:16px;overflow:hidden;text-decoration:none;color:var(--text);background:var(--card-bg);border:1.5px solid var(--border);transition:all .3s cubic-bezier(.34,1.56,.64,1);position:relative;aspect-ratio:1;display:flex;flex-direction:column;}
.cat-tile:hover{transform:translateY(-6px) scale(1.02);border-color:var(--primary);box-shadow:0 16px 40px rgba(0,0,0,.1);}
.cat-tile-img{flex:1;overflow:hidden;background:var(--primary-light);display:flex;align-items:center;justify-content:center;}
.cat-tile-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease;}
.cat-tile:hover .cat-tile-img img{transform:scale(1.08);}
.cat-tile-img i{font-size:36px;color:var(--primary);opacity:.6;}
.cat-tile-foot{padding:10px 12px 12px;background:var(--card-bg);}
.cat-tile-name{font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cat-tile-count{font-size:11.5px;color:var(--text-muted);margin-top:2px;}

/* product grid */
.prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(228px,1fr));gap:20px;}
.prod-card{background:var(--card-bg);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;transition:all .3s cubic-bezier(.34,1.56,.64,1);position:relative;display:flex;flex-direction:column;}
.prod-card:hover{transform:translateY(-6px);border-color:var(--primary);box-shadow:0 20px 50px rgba(0,0,0,.1);}
.prod-img-wrap{position:relative;overflow:hidden;background:var(--primary-light);aspect-ratio:1;}
.prod-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease;}
.prod-card:hover .prod-img-wrap img{transform:scale(1.07);}
.prod-no-img{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary-light),var(--primary-mid));}
.prod-no-img i{font-size:48px;color:var(--primary);opacity:.45;}
.prod-badge-disc{position:absolute;top:12px;left:12px;background:var(--primary);color:#fff;font-size:11px;font-weight:800;padding:4px 10px;border-radius:99px;}
.prod-badge-new{position:absolute;top:12px;right:12px;background:#10b981;color:#fff;font-size:10px;font-weight:700;padding:3px 9px;border-radius:99px;}
.prod-wish{position:absolute;bottom:12px;right:12px;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.9);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;font-size:15px;color:var(--text-muted);transition:all .2s;opacity:0;transform:translateY(4px);}
.prod-card:hover .prod-wish{opacity:1;transform:translateY(0);}
.prod-wish:hover{color:#ef4444;background:#fff;transform:scale(1.12) !important;}
.prod-body{padding:16px;flex:1;display:flex;flex-direction:column;}
.prod-cat{font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--primary);margin-bottom:6px;}
.prod-name{font-weight:700;font-size:14.5px;line-height:1.4;color:var(--text);text-decoration:none;display:block;margin-bottom:10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;transition:color .2s;}
.prod-name:hover{color:var(--primary);}
.prod-price-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:auto;}
.prod-price{font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:18px;color:var(--text);}
.prod-orig{font-size:13px;color:var(--text-faint);text-decoration:line-through;}
.prod-save{font-size:11.5px;font-weight:700;color:#10b981;background:rgba(16,185,129,.1);padding:2px 8px;border-radius:99px;}
.prod-footer{padding:0 16px 16px;display:flex;gap:8px;}
.prod-add{flex:1;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 16px;font-size:13.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:all .2s;text-decoration:none;}
.prod-add:hover{filter:brightness(1.1);transform:translateY(-1px);color:#fff;}
.prod-view{width:40px;height:40px;border-radius:10px;background:var(--card-bg);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:15px;text-decoration:none;transition:all .2s;flex-shrink:0;}
.prod-view:hover{background:var(--primary-light);border-color:var(--primary);color:var(--primary);}

/* bestsellers */
.bs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
.bs-card{display:flex;gap:14px;align-items:center;background:var(--card-bg);border:1.5px solid var(--border);border-radius:16px;padding:14px;text-decoration:none;color:var(--text);transition:all .25s;position:relative;overflow:hidden;}
.bs-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--primary);border-radius:99px 0 0 99px;transform:scaleY(0);transition:transform .25s;}
.bs-card:hover{border-color:var(--primary);transform:translateX(4px);box-shadow:0 8px 24px rgba(0,0,0,.08);}
.bs-card:hover::before{transform:scaleY(1);}
.bs-img{width:72px;height:72px;border-radius:12px;overflow:hidden;flex-shrink:0;background:var(--primary-light);display:flex;align-items:center;justify-content:center;}
.bs-img img{width:100%;height:100%;object-fit:cover;}
.bs-img i{font-size:28px;color:var(--primary);opacity:.5;}
.bs-rank{position:absolute;top:8px;right:12px;font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:28px;color:var(--primary);opacity:.12;line-height:1;}
.bs-name{font-weight:700;font-size:14px;line-height:1.4;margin-bottom:6px;}
.bs-price{font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:16px;color:var(--primary);}

/* promo */
.sf-promo{border-radius:22px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);padding:48px 52px;display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap;overflow:hidden;position:relative;margin-bottom:60px;}
.promo-o1{position:absolute;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.07);top:-80px;right:-40px;pointer-events:none;}
.promo-o2{position:absolute;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.05);bottom:-60px;right:180px;pointer-events:none;}
.promo-o3{position:absolute;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.06);top:20px;left:42%;pointer-events:none;}
.promo-text{position:relative;z-index:1;}
.promo-lbl{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.6);margin-bottom:8px;display:flex;align-items:center;gap:8px;}
.promo-lbl::before{content:'';width:16px;height:2px;background:rgba(255,255,255,.5);display:inline-block;border-radius:99px;}
.promo-title{font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:clamp(22px,3vw,34px);letter-spacing:-1px;color:#fff;margin-bottom:10px;line-height:1.1;}
.promo-sub{font-size:15px;color:rgba(255,255,255,.75);line-height:1.6;max-width:380px;}
.promo-btn{display:inline-flex;align-items:center;gap:10px;background:#fff;color:var(--primary);padding:15px 32px;border-radius:14px;font-weight:800;font-size:15px;text-decoration:none;transition:all .25s;white-space:nowrap;position:relative;z-index:1;box-shadow:0 8px 28px rgba(0,0,0,.15);flex-shrink:0;}
.promo-btn:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 16px 36px rgba(0,0,0,.2);color:var(--primary);}

/* search */
.search-info{display:flex;align-items:center;gap:12px;padding:16px 20px;background:var(--primary-light);border:1.5px solid var(--primary-mid);border-radius:14px;margin-bottom:24px;}
.cat-chips{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;margin-bottom:28px;scrollbar-width:none;}
.cat-chips::-webkit-scrollbar{display:none;}
.cat-chip{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:99px;background:var(--card-bg);border:1.5px solid var(--border);color:var(--text-muted);font-size:13px;font-weight:500;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .2s;}
.cat-chip:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light);}
.cat-chip.active{background:var(--primary);border-color:var(--primary);color:#fff;font-weight:700;}
.cat-chip img{width:20px;height:20px;border-radius:50%;object-fit:cover;}

/* animate */
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.sf-in{animation:fadeUp .5s ease both;}
.d1{animation-delay:.07s;}.d2{animation-delay:.14s;}.d3{animation-delay:.21s;}.d4{animation-delay:.28s;}

/* responsive */
@media(max-width:900px){.hero-body{padding:48px 24px;}.sfp{padding:0 16px;}.trust-bar{grid-template-columns:repeat(2,1fr);}.sf-promo{padding:32px 28px;}.hero-stats-inner{padding:14px 20px;}}
@media(max-width:640px){.hero-title{font-size:28px;letter-spacing:-1px;}.prod-grid{grid-template-columns:repeat(2,1fr);gap:12px;}.cat-grid{grid-template-columns:repeat(3,1fr);}.trust-bar{grid-template-columns:repeat(2,1fr);}.bs-grid{grid-template-columns:1fr;}.hero-stats{display:none;}}
@media(max-width:400px){.cat-grid{grid-template-columns:repeat(2,1fr);}.prod-grid{grid-template-columns:repeat(2,1fr);gap:8px;}}
</style>

<?php if ($q || $cat_filter): ?>
<!-- SEARCH/FILTER VIEW -->
<div class="sfp" style="padding-top:36px;padding-bottom:80px;">
    <?php if ($q): ?>
    <div class="search-info sf-in">
        <i class="bi bi-search" style="color:var(--primary);font-size:20px;flex-shrink:0;"></i>
        <div>
            <span style="font-weight:700;">Results for "<span style="color:var(--primary);"><?= htmlspecialchars($q) ?></span>"</span>
            <span style="color:var(--text-muted);font-size:13px;margin-left:10px;"><?= $products->num_rows ?> product<?= $products->num_rows != 1 ? 's' : '' ?> found</span>
        </div>
        <a href="index.php?shop=<?= $slug ?>" class="btn-shop-ghost" style="margin-left:auto;font-size:13px;padding:7px 14px;"><i class="bi bi-x"></i> Clear</a>
    </div>
    <?php endif; ?>

    <div class="cat-chips sf-in d1">
        <a href="index.php?shop=<?= $slug ?>" class="cat-chip <?= !$cat_filter?'active':'' ?>">All</a>
        <?php foreach ($cats_data as $c): ?>
        <a href="index.php?shop=<?= $slug ?>&cat=<?= $c['id'] ?><?= $q?'&q='.urlencode($q):'' ?>" class="cat-chip <?= $cat_filter==$c['id']?'active':'' ?>">
            <?php if ($c['image']): ?><img src="<?= strpos($c['image'],'http')===0?htmlspecialchars($c['image']):'../assets/uploads/products/'.htmlspecialchars($c['image']) ?>" alt=""><?php endif; ?>
            <?= htmlspecialchars($c['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($products->num_rows === 0): ?>
    <div style="text-align:center;padding:100px 20px;" class="sf-in d2">
        <div style="font-size:64px;margin-bottom:16px;">🔍</div>
        <h3 style="font-family:var(--font-display,'Syne',sans-serif);font-weight:800;font-size:24px;margin-bottom:10px;">No products found</h3>
        <p style="color:var(--text-muted);font-size:15px;margin-bottom:24px;">Try different keywords or browse all categories.</p>
        <a href="index.php?shop=<?= $slug ?>" class="hero-btn-p" style="display:inline-flex;">Browse All Products</a>
    </div>
    <?php else: ?>
    <div class="prod-grid">
        <?php $i=0; while ($p=$products->fetch_assoc()): $i++;
            $disc=$p['discount_price'];$orig=$p['price'];$save_pct=$disc?round((($orig-$disc)/$orig)*100):0;
            $delay=min($i*0.05,0.35);
        ?>
        <?php include 'includes/product_card.php'; ?>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- HOMEPAGE -->

<?php if ($shop['announcement_active'] && $shop['announcement']): ?>
<div class="ticker-bar">
    <div class="ticker-inner">
        <?php for ($t=0;$t<8;$t++): ?><span><?= htmlspecialchars($shop['announcement']) ?></span><?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<section class="hero <?= !$shop['banner']?'hero-nobanner':'' ?>">
    <div class="hero-media">
        <?php if ($shop['banner']): ?>
        <img src="<?= strpos($shop['banner'],'http')===0?htmlspecialchars($shop['banner']):'../assets/uploads/banners/'.htmlspecialchars($shop['banner']) ?>" class="hero-banner-img" alt="">
        <div class="hero-scrim"></div>
        <?php else: ?>
        <div class="hero-bg-grad"></div>
        <?php endif; ?>
    </div>
    <div class="hero-body">
        <div>
            <div class="hero-eyebrow"><i class="bi bi-stars"></i> <?= htmlspecialchars($shop['name']) ?></div>
            <h1 class="hero-title">
                <?php $words=explode(' ',$shop['name']); $last=array_pop($words);
                echo htmlspecialchars(implode(' ',$words)).' <span class="aw">'.htmlspecialchars($last).'</span>'; ?>
            </h1>
            <p class="hero-sub"><?= $shop['description']?htmlspecialchars($shop['description']):"Discover our curated selection of premium products — quality you can trust, prices you'll love." ?></p>
            <div class="hero-ctas">
                <a href="products.php?shop=<?= $slug ?>" class="hero-btn-p"><i class="bi bi-grid-fill"></i> Shop All Products</a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="../auth/register.php?shop=<?= $slug ?>" class="hero-btn-g"><i class="bi bi-person-plus"></i> Create Account</a>
                <?php else: ?>
                <a href="orders.php?shop=<?= $slug ?>" class="hero-btn-g"><i class="bi bi-bag-check"></i> My Orders</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($shop['banner']): ?>
    <div class="hero-stats">
        <div class="hero-stats-inner">
            <div class="hs-item"><div class="hs-val"><?= $total_prods ?>+</div><div class="hs-label">Products</div></div>
            <div class="hs-item"><div class="hs-val"><?= $total_custs ?>+</div><div class="hs-label">Customers</div></div>
            <div class="hs-item"><div class="hs-val"><?= $total_ords ?>+</div><div class="hs-label">Orders Delivered</div></div>
            <div class="hs-item"><div class="hs-val"><?= count($cats_data) ?></div><div class="hs-label">Categories</div></div>
        </div>
    </div>
    <?php endif; ?>
</section>

<div class="sfp" style="padding-top:52px;padding-bottom:80px;">

    <!-- Trust bar -->
    <div class="trust-bar sf-in">
        <div class="trust-item"><div class="trust-icon"><i class="bi bi-truck"></i></div><div><div class="trust-label">Fast Delivery</div><div class="trust-sub">Quick & reliable</div></div></div>
        <div class="trust-item"><div class="trust-icon"><i class="bi bi-shield-check"></i></div><div><div class="trust-label">Secure Orders</div><div class="trust-sub">100% safe checkout</div></div></div>
        <div class="trust-item"><div class="trust-icon"><i class="bi bi-arrow-counterclockwise"></i></div><div><div class="trust-label">Easy Returns</div><div class="trust-sub">Hassle-free policy</div></div></div>
        <div class="trust-item"><div class="trust-icon"><i class="bi bi-headset"></i></div><div><div class="trust-label">24/7 Support</div><div class="trust-sub">Always here to help</div></div></div>
    </div>

    <!-- Categories -->
    <?php if (!empty($cats_data)): ?>
    <section class="sfs sf-in d1">
        <div class="sfs-head">
            <div><div class="sfs-tag">Explore</div><div class="sfs-title">Shop by Category</div></div>
            <a href="products.php?shop=<?= $slug ?>" class="sfs-all">All Products <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="cat-grid">
            <?php foreach ($cats_data as $ci=>$c): ?>
            <a href="products.php?shop=<?= $slug ?>&cat=<?= $c['id'] ?>" class="cat-tile sf-in" style="animation-delay:<?= $ci*0.06 ?>s;">
                <div class="cat-tile-img">
                    <?php if ($c['image']): ?><img src="<?= strpos($c['image'],'http')===0?htmlspecialchars($c['image']):'../assets/uploads/products/'.htmlspecialchars($c['image']) ?>" alt=""><?php else: ?><i class="bi bi-tags"></i><?php endif; ?>
                </div>
                <div class="cat-tile-foot">
                    <div class="cat-tile-name"><?= htmlspecialchars($c['name']) ?></div>
                    <div class="cat-tile-count"><?= $c['pcount'] ?> item<?= $c['pcount']!=1?'s':'' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Products -->
    <?php if ($products->num_rows > 0): ?>
    <section class="sfs sf-in d2">
        <div class="sfs-head">
            <div><div class="sfs-tag">New Arrivals</div><div class="sfs-title">Featured Products</div></div>
            <a href="products.php?shop=<?= $slug ?>" class="sfs-all">View all <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="prod-grid">
            <?php $i=0; while ($p=$products->fetch_assoc()): $i++;
                $disc=$p['discount_price'];$orig=$p['price'];$save_pct=$disc?round((($orig-$disc)/$orig)*100):0;
                $delay=min($i*0.05,0.35);
            ?>
            <div class="prod-card sf-in" style="animation-delay:<?= $delay ?>s;">
                <a href="product.php?shop=<?= $slug ?>&id=<?= $p['id'] ?>" style="display:block;text-decoration:none;">
                    <div class="prod-img-wrap">
                        <?php if ($p['image']): ?>
                        <img src="<?= strpos($p['image'],'http')===0?htmlspecialchars($p['image']):'../assets/uploads/products/'.htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        <?php else: ?><div class="prod-no-img"><i class="bi bi-image"></i></div><?php endif; ?>
                        <?php if ($disc && $save_pct>0): ?><div class="prod-badge-disc">-<?= $save_pct ?>%</div><?php endif; ?>
                        <?php if ($i<=3): ?><div class="prod-badge-new">NEW</div><?php endif; ?>
                        <button class="prod-wish" onclick="event.preventDefault();this.style.color='#ef4444';" title="Wishlist"><i class="bi bi-heart"></i></button>
                    </div>
                </a>
                <div class="prod-body">
                    <?php if ($p['cat_name']): ?><div class="prod-cat"><?= htmlspecialchars($p['cat_name']) ?></div><?php endif; ?>
                    <a href="product.php?shop=<?= $slug ?>&id=<?= $p['id'] ?>" class="prod-name"><?= htmlspecialchars($p['name']) ?></a>
                    <div class="prod-price-row">
                        <span class="prod-price">₹<?= number_format($disc?:$orig,0) ?></span>
                        <?php if ($disc): ?><span class="prod-orig">₹<?= number_format($orig,0) ?></span><span class="prod-save">Save <?= $save_pct ?>%</span><?php endif; ?>
                    </div>
                </div>
                <?php if ($p['stock']>0): ?>
                <div class="prod-footer">
                    <button onclick="addToCart(<?= $p['id'] ?>)" class="prod-add"><i class="bi bi-bag-plus"></i> Add to Cart</button>
                    <a href="product.php?shop=<?= $slug ?>&id=<?= $p['id'] ?>" class="prod-view" title="View"><i class="bi bi-eye"></i></a>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Best Sellers -->
    <?php if ($bestsellers->num_rows > 0): ?>
    <section class="sfs sf-in d3">
        <div class="sfs-head">
            <div><div class="sfs-tag">Top Picks</div><div class="sfs-title">Best Sellers</div></div>
            <a href="products.php?shop=<?= $slug ?>" class="sfs-all">View all <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="bs-grid">
            <?php $rank=1; while ($b=$bestsellers->fetch_assoc()): ?>
            <a href="product.php?shop=<?= $slug ?>&id=<?= $b['id'] ?>" class="bs-card sf-in" style="animation-delay:<?= ($rank-1)*0.07 ?>s;">
                <div class="bs-rank">#<?= $rank ?></div>
                <div class="bs-img">
                    <?php if ($b['image']): ?><img src="<?= strpos($b['image'],'http')===0?htmlspecialchars($b['image']):'../assets/uploads/products/'.htmlspecialchars($b['image']) ?>" alt=""><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="bs-name"><?= htmlspecialchars($b['name']) ?></div>
                    <?php if ($b['cat_name']): ?><div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;"><?= htmlspecialchars($b['cat_name']) ?></div><?php endif; ?>
                    <div class="bs-price">₹<?= number_format($b['discount_price']?:$b['price'],0) ?></div>
                </div>
                <i class="bi bi-chevron-right" style="color:var(--primary);font-size:14px;flex-shrink:0;"></i>
            </a>
            <?php $rank++; endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Promo Banner -->
    <div class="sf-promo sf-in d4">
        <div class="promo-o1"></div><div class="promo-o2"></div><div class="promo-o3"></div>
        <div class="promo-text">
            <div class="promo-lbl">Exclusive Deals</div>
            <div class="promo-title"><?= $shop['announcement_active']&&$shop['announcement']?htmlspecialchars($shop['announcement']):'Shop More, Save More' ?></div>
            <p class="promo-sub">Explore our full collection and discover amazing deals on every product.</p>
        </div>
        <a href="products.php?shop=<?= $slug ?>" class="promo-btn">Shop Now <i class="bi bi-arrow-right"></i></a>
    </div>

</div>
<?php endif; ?>

<?php require 'includes/shop_foot.php'; ?>