<?php
session_start();
require '../config/db.php';

$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? null;
if (!$slug) { header("Location: ../index.php"); exit; }
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

$page_title = 'Your Cart';
require 'includes/shop_head.php';

requireCustomerLogin($shop);

$user_id = $_SESSION['user_id'];

// Fetch cart items
$cart_items = $conn->query("
    SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.discount_price, p.image, p.stock
    FROM cart c JOIN products p ON c.product_id=p.id
    WHERE c.user_id=$user_id AND c.shop_id=$shop_id AND p.is_active=1
    ORDER BY c.created_at ASC
");
$items = [];
$subtotal = 0;
while ($row = $cart_items->fetch_assoc()) {
    $row['final_price'] = $row['discount_price'] ?: $row['price'];
    $row['line_total']  = $row['final_price'] * $row['quantity'];
    $subtotal += $row['line_total'];
    $items[]   = $row;
}
?>

<style>
.cart-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    padding: 32px 0 60px;
    align-items: start;
}
.cart-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 0;
}
.cart-item {
    display: flex; align-items: center; gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}
.cart-item:last-child { border-bottom: none; }
.cart-item:hover { background: color-mix(in srgb, var(--primary) 3%, var(--card-bg)); }
.cart-item-img {
    width: 80px; height: 80px; flex-shrink: 0;
    border-radius: var(--radius-sm);
    overflow: hidden; background: var(--primary-light);
    display: flex; align-items: center; justify-content: center;
}
.cart-item-img img { width:100%; height:100%; object-fit:cover; }
.cart-item-img i { font-size:28px; color:var(--primary-glow); }
.cart-item-name { font-weight:600; font-size:14.5px; margin-bottom:4px; }
.cart-item-price { font-size:13px; color:var(--text-muted); }
.cart-item-total { font-family:'Syne',sans-serif; font-weight:700; font-size:16px; color:var(--primary); margin-left:auto; flex-shrink:0; text-align:right; }

.mini-qty-wrap { display:flex; align-items:center; gap:0; border:1.5px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; width:fit-content; margin-top:8px; }
.mini-qty-btn { width:32px; height:32px; background:var(--card-bg); border:none; cursor:pointer; color:var(--text); font-size:16px; display:flex; align-items:center; justify-content:center; transition:var(--transition); }
.mini-qty-btn:hover { background:var(--primary-light); color:var(--primary); }
.mini-qty-val { width:44px; height:32px; border:none; border-left:1.5px solid var(--border); border-right:1.5px solid var(--border); background:var(--bg); color:var(--text); font-family:'Syne',sans-serif; font-weight:700; font-size:13px; text-align:center; outline:none; }
.mini-qty-val::-webkit-outer-spin-button, .mini-qty-val::-webkit-inner-spin-button { -webkit-appearance:none; }

.order-summary {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    position: sticky;
    top: calc(var(--navbar-h) + 20px);
}
.summary-title { font-family:'Syne',sans-serif; font-weight:800; font-size:18px; margin-bottom:20px; }
.summary-row { display:flex; justify-content:space-between; align-items:center; font-size:14px; margin-bottom:12px; }
.summary-row.total { font-family:'Syne',sans-serif; font-weight:800; font-size:20px; margin-top:16px; padding-top:16px; border-top:1px solid var(--border); }
.summary-row.total span:last-child { color:var(--primary); }

@media (max-width: 900px) {
    .cart-layout { grid-template-columns: 1fr; }
    .order-summary { position: relative; top: 0; }
}
@media (max-width: 480px) {
    .cart-item { gap: 12px; padding: 16px; }
    .cart-item-img { width: 64px; height: 64px; }
}
</style>

<div class="shop-container">
    <div style="padding:28px 0 0;">
        <h1 style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;letter-spacing:-0.6px;margin-bottom:4px;" class="fade-up">Your Cart</h1>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:28px;" class="fade-up d1"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($items)): ?>
    <div style="text-align:center;padding:80px 20px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:60px;" class="fade-up">
        <i class="bi bi-bag-x" style="font-size:64px;color:var(--text-faint);display:block;margin-bottom:20px;"></i>
        <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:22px;margin-bottom:12px;">Your cart is empty</h3>
        <p style="color:var(--text-muted);font-size:14.5px;margin-bottom:24px;">Add some products to get started.</p>
        <a href="products.php?shop=<?= $slug ?>" class="btn-shop-primary">
            <i class="bi bi-grid"></i> Browse Products
        </a>
    </div>

    <?php else: ?>
    <div class="cart-layout">

        <!-- Cart Items -->
        <div>
            <div class="cart-card fade-up d1">
                <?php foreach ($items as $item): ?>
                <div class="cart-item" id="cartRow<?= $item['cart_id'] ?>">
                    <a href="product.php?shop=<?= $slug ?>&id=<?= $item['product_id'] ?>" style="text-decoration:none;flex-shrink:0;">
                        <div class="cart-item-img">
                            <?php if ($item['image']): ?>
                            <img src="<?= strpos($item['image'],'http')===0 ? htmlspecialchars($item['image']) : '../assets/uploads/products/'.htmlspecialchars($item['image']) ?>" alt="">
                            <?php else: ?>
                            <i class="bi bi-image"></i>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div style="flex:1;min-width:0;">
                        <a href="product.php?shop=<?= $slug ?>&id=<?= $item['product_id'] ?>" style="text-decoration:none;color:inherit;">
                            <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                        </a>
                        <div class="cart-item-price">
                            &#8377;<?= number_format($item['final_price'], 2) ?> each
                            <?php if ($item['discount_price']): ?>
                            &middot; <span style="text-decoration:line-through;font-size:12px;">&#8377;<?= number_format($item['price'],2) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <div class="mini-qty-wrap">
                                <button class="mini-qty-btn" onclick="changeCartQty(<?= $item['cart_id'] ?>, -1)"><i class="bi bi-dash"></i></button>
                                <input type="number" class="mini-qty-val" id="qty<?= $item['cart_id'] ?>" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>" onchange="updateCartQty(<?= $item['cart_id'] ?>, this.value)">
                                <button class="mini-qty-btn" onclick="changeCartQty(<?= $item['cart_id'] ?>, 1)"><i class="bi bi-plus"></i></button>
                            </div>
                            <button onclick="removeCartItem(<?= $item['cart_id'] ?>)" class="btn-shop-ghost" style="font-size:12.5px;padding:5px 10px;color:var(--text-muted);">
                                <i class="bi bi-trash3"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="cart-item-total">
                        <div>&#8377;<span id="lineTotal<?= $item['cart_id'] ?>"><?= number_format($item['line_total'], 2) ?></span></div>
                        <div style="font-size:11.5px;font-weight:400;color:var(--text-muted);margin-top:2px;" id="qtyDisplay<?= $item['cart_id'] ?>"><?= $item['quantity'] ?> &times; &#8377;<?= number_format($item['final_price'],2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Continue shopping -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;" class="fade-up d2">
                <a href="products.php?shop=<?= $slug ?>" class="btn-shop-ghost">
                    <i class="bi bi-arrow-left"></i> Continue Shopping
                </a>
                <button onclick="clearCart()" class="btn-shop-ghost" style="color:var(--text-muted);">
                    <i class="bi bi-trash3"></i> Clear Cart
                </button>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="order-summary fade-up d2">
            <div class="summary-title">Order Summary</div>

            <div class="summary-row">
                <span style="color:var(--text-muted);">Subtotal (<?= count($items) ?> items)</span>
                <span id="summarySubtotal">&#8377;<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="summary-row">
                <span style="color:var(--text-muted);">Delivery</span>
                <span style="color:#16a34a;font-weight:600;">Free</span>
            </div>
            <div class="summary-row">
                <span style="color:var(--text-muted);">Payment</span>
                <span style="font-size:13px;">Cash on Delivery</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="summaryTotal">&#8377;<?= number_format($subtotal, 2) ?></span>
            </div>

            <a href="checkout.php?shop=<?= $slug ?>" class="btn-shop-primary" style="width:100%;justify-content:center;margin-top:20px;padding:14px;font-size:15px;border-radius:var(--radius);">
                <i class="bi bi-bag-check"></i> Proceed to Checkout
            </a>

            <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;font-size:12.5px;color:var(--text-muted);">
                <i class="bi bi-shield-check" style="color:var(--primary);"></i>
                Secure &amp; Safe Checkout
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php $shop_slug = htmlspecialchars($slug, ENT_QUOTES); ?>
<script>
const cartPrices = {};
<?php foreach ($items as $item): ?>
cartPrices[<?= (int)$item['cart_id'] ?>] = <?= (float)$item['final_price'] ?>;
<?php endforeach; ?>

function changeCartQty(cartId, delta) {
    const input = document.getElementById("qty" + cartId);
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) { removeCartItem(cartId); return; }
    if (val > max) val = max;
    input.value = val;
    updateCartQty(cartId, val);
}

function updateCartQty(cartId, qty) {
    qty = parseInt(qty);
    if (isNaN(qty) || qty < 1) { removeCartItem(cartId); return; }

    fetch("cart_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=update&cart_id=" + cartId + "&quantity=" + qty + "&shop=<?= $shop_slug ?>"
    }).then(r => r.json()).then(d => {
        if (d.success) {
            const price = cartPrices[cartId];
            const total = (price * qty).toFixed(2);
            document.getElementById("lineTotal" + cartId).textContent = parseFloat(total).toLocaleString("en-IN", {minimumFractionDigits: 2});
            document.getElementById("qtyDisplay" + cartId).innerHTML = qty + " × ₹" + price.toFixed(2);
            document.getElementById("qty" + cartId).value = qty;
            recalcSummary();
            document.querySelectorAll(".cart-badge,.mobile-cart-badge").forEach(el => {
                el.textContent = d.cart_count;
                el.style.display = d.cart_count > 0 ? "flex" : "none";
            });
        }
    });
}

function removeCartItem(cartId) {
    const row = document.getElementById("cartRow" + cartId);
    if (row) { row.style.opacity = "0"; row.style.transition = "opacity 0.3s"; }
    fetch("cart_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=remove&cart_id=" + cartId + "&shop=<?= $shop_slug ?>"
    }).then(r => r.json()).then(d => {
        if (d.success && row) {
            delete cartPrices[cartId];
            setTimeout(() => { row.remove(); recalcSummary(); }, 300);
            document.querySelectorAll(".cart-badge,.mobile-cart-badge").forEach(el => {
                el.textContent = d.cart_count;
                el.style.display = d.cart_count > 0 ? "flex" : "none";
            });
            showToast("Item removed from cart");
        }
    });
}

function clearCart() {
    if (!confirm("Remove all items from your cart?")) return;
    fetch("cart_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=clear&shop=<?= $shop_slug ?>"
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function recalcSummary() {
    let total = 0;
    document.querySelectorAll(".cart-item").forEach(row => {
        const cartId = parseInt(row.id.replace("cartRow", ""));
        const qty    = parseInt(document.getElementById("qty" + cartId)?.value || 0);
        const price  = cartPrices[cartId] || 0;
        total += qty * price;
    });
    const fmt = total.toLocaleString("en-IN", {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById("summarySubtotal").textContent = "₹" + fmt;
    document.getElementById("summaryTotal").textContent    = "₹" + fmt;
}
</script>

<?php require 'includes/shop_foot.php'; ?>