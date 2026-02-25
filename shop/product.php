<?php
session_start();
require '../config/db.php';

$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
$pid  = (int)($_GET['id'] ?? 0);
if (!$slug || !$pid) { header("Location: index.php?shop=$slug"); exit; }

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

// Fetch product
$pstmt = $conn->prepare("SELECT p.*, c.name as cat_name, c.id as cat_id FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=? AND p.shop_id=? AND p.is_active=1");
$pstmt->bind_param("ii", $pid, $shop_id);
$pstmt->execute();
$product = $pstmt->get_result()->fetch_assoc();
if (!$product) { header("Location: products.php?shop=$slug"); exit; }

// Related products
$related = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.category_id={$product['cat_id']} AND p.id != $pid AND p.shop_id=$shop_id AND p.is_active=1 LIMIT 4");

$disc     = $product['discount_price'];
$orig     = $product['price'];
$save_pct = $disc ? round((($orig - $disc) / $orig) * 100) : 0;
$page_title = htmlspecialchars($product['name']);

require 'includes/shop_head.php';
?>

<style>
.product-detail-wrap {
    padding: 40px 0 60px;
}
.product-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: start;
}
.product-img-wrap {
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: 1px solid var(--border);
    aspect-ratio: 1;
    background: var(--primary-light);
    display: flex; align-items: center; justify-content: center;
    position: sticky;
    top: calc(var(--navbar-h) + 20px);
}
.product-img-wrap img { width:100%; height:100%; object-fit:cover; }
.product-img-wrap i { font-size:80px; color: var(--primary-glow); }

.product-cat-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--primary-light);
    color: var(--primary);
    padding: 5px 14px;
    border-radius: 99px;
    font-size: 12px; font-weight: 600;
    margin-bottom: 16px;
    text-decoration: none;
    transition: var(--transition);
}
.product-cat-badge:hover { background: var(--primary-mid); }

.product-name {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: clamp(22px, 3vw, 32px);
    line-height: 1.2;
    letter-spacing: -0.8px;
    margin-bottom: 16px;
}

.product-price-block {
    display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap;
    margin-bottom: 20px;
}
.product-price-main {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 32px;
    color: var(--primary);
}
.product-price-orig {
    font-size: 18px; color: var(--text-muted);
    text-decoration: line-through;
}
.product-save-badge {
    background: rgba(34,197,94,0.12);
    color: #16a34a;
    font-size: 13px; font-weight: 700;
    padding: 4px 10px; border-radius: 99px;
}

.product-desc {
    font-size: 15px; color: var(--text-muted);
    line-height: 1.7; margin-bottom: 28px;
    border-top: 1px solid var(--border);
    padding-top: 20px;
}

/* Qty selector */
.qty-wrap {
    display: flex; align-items: center; gap: 0;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    overflow: hidden;
    width: fit-content;
}
.qty-btn {
    width: 42px; height: 42px;
    background: var(--card-bg);
    border: none; cursor: pointer;
    color: var(--text); font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    transition: var(--transition);
}
.qty-btn:hover { background: var(--primary-light); color: var(--primary); }
.qty-input {
    width: 56px; height: 42px;
    border: none; border-left: 1.5px solid var(--border); border-right: 1.5px solid var(--border);
    background: var(--bg); color: var(--text);
    font-family: 'Syne',sans-serif; font-weight: 700; font-size: 15px;
    text-align: center; outline: none;
}
/* Remove spinner */
.qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.qty-input[type=number] { -moz-appearance: textfield; }

.stock-info {
    font-size: 13px;
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    display: inline-flex; align-items: center; gap: 6px;
    margin-bottom: 20px;
}
.in-stock  { background: rgba(34,197,94,0.1); color: #16a34a; }
.low-stock { background: rgba(251,191,36,0.1); color: #d97706; }
.no-stock  { background: rgba(239,68,68,0.1);  color: #dc2626; }

.add-cart-wrap { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }

/* Related */
.related-section { margin-top: 60px; }
.related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }

@media (max-width: 768px) {
    .product-detail-grid { grid-template-columns: 1fr; gap: 24px; }
    .product-img-wrap { position: relative; top: 0; max-height: 300px; }
}
</style>

<div class="shop-container">
    <!-- Breadcrumb -->
    <nav style="padding:20px 0 28px;font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:6px;flex-wrap:wrap;" class="fade-up">
        <a href="index.php?shop=<?= $slug ?>" style="color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Home</a>
        <i class="bi bi-chevron-right" style="font-size:11px;"></i>
        <a href="products.php?shop=<?= $slug ?>" style="color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Products</a>
        <?php if ($product['cat_name']): ?>
        <i class="bi bi-chevron-right" style="font-size:11px;"></i>
        <a href="products.php?shop=<?= $slug ?>&cat=<?= $product['cat_id'] ?>" style="color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'"><?= htmlspecialchars($product['cat_name']) ?></a>
        <?php endif; ?>
        <i class="bi bi-chevron-right" style="font-size:11px;"></i>
        <span style="color:var(--text);"><?= htmlspecialchars($product['name']) ?></span>
    </nav>

    <div class="product-detail-wrap">
        <div class="product-detail-grid">

            <!-- Image -->
            <div class="fade-up">
                <div class="product-img-wrap">
                    <?php if ($product['image']): ?>
                    <img src="<?= strpos($product['image'],'http')===0 ? htmlspecialchars($product['image']) : '../assets/uploads/products/'.htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                    <i class="bi bi-image"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info -->
            <div class="fade-up d2">
                <a href="products.php?shop=<?= $slug ?>&cat=<?= $product['cat_id'] ?>" class="product-cat-badge">
                    <i class="bi bi-tag"></i> <?= htmlspecialchars($product['cat_name'] ?? 'Uncategorised') ?>
                </a>

                <h1 class="product-name"><?= htmlspecialchars($product['name']) ?></h1>

                <div class="product-price-block">
                    <span class="product-price-main">&#8377;<?= number_format($disc ?: $orig, 2) ?></span>
                    <?php if ($disc): ?>
                    <span class="product-price-orig">&#8377;<?= number_format($orig, 2) ?></span>
                    <span class="product-save-badge"><i class="bi bi-tag-fill"></i> Save <?= $save_pct ?>%</span>
                    <?php endif; ?>
                </div>

                <!-- Stock info -->
                <?php if ($product['stock'] <= 0): ?>
                <div class="stock-info no-stock"><i class="bi bi-x-circle-fill"></i> Out of Stock</div>
                <?php elseif ($product['stock'] <= 5): ?>
                <div class="stock-info low-stock"><i class="bi bi-exclamation-triangle-fill"></i> Only <?= $product['stock'] ?> left!</div>
                <?php else: ?>
                <div class="stock-info in-stock"><i class="bi bi-check-circle-fill"></i> In Stock (<?= $product['stock'] ?> available)</div>
                <?php endif; ?>

                <?php if ($product['description']): ?>
                <div class="product-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
                <?php endif; ?>

                <?php if ($product['stock'] > 0): ?>
                <!-- Qty + Add to cart -->
                <div style="margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-muted);">Quantity</div>
                <div class="qty-wrap" style="margin-bottom:0;">
                    <button type="button" class="qty-btn" onclick="changeQty(-1)"><i class="bi bi-dash"></i></button>
                    <input type="number" class="qty-input" id="qtyInput" value="1" min="1" max="<?= $product['stock'] ?>">
                    <button type="button" class="qty-btn" onclick="changeQty(1)"><i class="bi bi-plus"></i></button>
                </div>

                <div class="add-cart-wrap">
                    <button onclick="addToCartWithQty()" class="btn-shop-primary" style="flex:1;justify-content:center;padding:14px 24px;font-size:15px;border-radius:var(--radius);" id="addCartBtn">
                        <i class="bi bi-bag-plus"></i> Add to Cart
                    </button>
                    <a href="cart.php?shop=<?= $slug ?>" class="btn-shop-outline" style="padding:14px 20px;border-radius:var(--radius);">
                        <i class="bi bi-bag"></i>
                    </a>
                </div>

                <!-- Quick buy -->
                <?php if (!isset($_SESSION['user_id'])): ?>
                <p style="font-size:12.5px;color:var(--text-muted);margin-top:12px;text-align:center;">
                    <a href="../auth/login.php?shop=<?= $slug ?>" style="color:var(--primary);text-decoration:none;font-weight:600;">Sign in</a> to save your cart across devices.
                </p>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Meta info -->
                <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;gap:10px;font-size:13.5px;color:var(--text-muted);">
                        <i class="bi bi-truck" style="color:var(--primary);font-size:16px;flex-shrink:0;margin-top:1px;"></i>
                        Cash on Delivery available
                    </div>
                    <div style="display:flex;gap:10px;font-size:13.5px;color:var(--text-muted);">
                        <i class="bi bi-shield-check" style="color:var(--primary);font-size:16px;flex-shrink:0;margin-top:1px;"></i>
                        Secure checkout
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if ($related->num_rows > 0): ?>
        <div class="related-section fade-up d3">
            <div class="section-head">
                <div>
                    <div class="section-title">You Might Also Like</div>
                    <div class="section-sub">More from <?= htmlspecialchars($product['cat_name'] ?? 'this shop') ?></div>
                </div>
                <a href="products.php?shop=<?= $slug ?>&cat=<?= $product['cat_id'] ?>" class="btn-shop-ghost">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="related-grid">
                <?php $i = 0; while ($p = $related->fetch_assoc()): $i++; ?>
                <?php include 'includes/product_card.php'; ?>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$product_id  = (int)$product['id'];
$shop_slug   = htmlspecialchars($slug, ENT_QUOTES);
?>
<script>
function changeQty(delta) {
    const input = document.getElementById("qtyInput");
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

function addToCartWithQty() {
    const qty = parseInt(document.getElementById("qtyInput").value);
    const btn = document.getElementById("addCartBtn");
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass"></i> Adding...';
    fetch("cart_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=add&product_id=<?= $product_id ?>&quantity=" + qty + "&shop=<?= $shop_slug ?>"
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="bi bi-bag-check"></i> Added!';
            btn.style.background = "#16a34a";
            showToast(d.message || "Added to cart!");
            document.querySelectorAll(".cart-badge,.mobile-cart-badge").forEach(el => {
                el.textContent = d.cart_count;
                el.style.display = d.cart_count > 0 ? "flex" : "none";
            });
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-bag-plus"></i> Add to Cart';
                btn.style.background = "";
            }, 2500);
        } else {
            showToast(d.message || "Failed", "exclamation-circle-fill");
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-bag-plus"></i> Add to Cart';
        }
    });
}
</script>
<?php
require 'includes/shop_foot.php';
?>