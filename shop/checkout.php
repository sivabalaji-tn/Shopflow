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


$user_id = $_SESSION['user_id'];

// Get cart
$cart_items = $conn->query("
    SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.discount_price, p.image, p.stock
    FROM cart c JOIN products p ON c.product_id=p.id
    WHERE c.user_id=$user_id AND c.shop_id=$shop_id AND p.is_active=1
");
$items = []; $subtotal = 0;
while ($row = $cart_items->fetch_assoc()) {
    $row['final_price'] = $row['discount_price'] ?: $row['price'];
    $row['line_total']  = $row['final_price'] * $row['quantity'];
    $subtotal += $row['line_total'];
    $items[]   = $row;
}
if (empty($items)) { header("Location: cart.php?shop=$slug"); exit; }

// User info for pre-filling
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

$error = '';
$order_placed = false;
$order_id     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address']);
    $notes   = trim($_POST['notes'] ?? '');

    if (empty($address)) {
        $error = 'Please provide a delivery address.';
    } else {
        $conn->begin_transaction();
        try {
            // Get next shop order number
            $next_num = $conn->query("SELECT COALESCE(MAX(shop_order_number), 0) + 1 FROM orders WHERE shop_id=$shop_id")->fetch_row()[0];

            // Insert order
            $ostmt = $conn->prepare("INSERT INTO orders (shop_id, user_id, total_amount, status, payment_method, address, notes, shop_order_number) VALUES (?,?,?,'pending','cod',?,?,?)");
            $ostmt->bind_param("iidssi", $shop_id, $user_id, $subtotal, $address, $notes, $next_num);
            $ostmt->execute();
            $order_id = $ostmt->insert_id;

            // Insert order items & update stock
            foreach ($items as $item) {
                $oistmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
                $oistmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['final_price']);
                $oistmt->execute();
                $conn->query("UPDATE products SET stock = stock - {$item['quantity']} WHERE id={$item['product_id']} AND stock >= {$item['quantity']}");
            }

            // Clear cart
            $conn->query("DELETE FROM cart WHERE user_id=$user_id AND shop_id=$shop_id");

            // Update user address
            if (!empty($user['address']) == '') {
                $conn->query("UPDATE users SET address='" . $conn->real_escape_string($address) . "' WHERE id=$user_id");
            }

            $conn->commit();
            $order_placed = true;

            // ── Send notifications ────────────────────────────
            require_once 'includes/notifications.php';
            $notif_result = sendOrderNotifications($conn, $order_id, $shop, $user, $items, $subtotal, $settings_map);
            $whatsapp_notify_url = $notif_result['whatsapp_url'] ?? false;
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Order failed. Please try again.';
        }
    }
}

$page_title = 'Checkout';
require 'includes/shop_head.php';
requireCustomerLogin($shop);
?>

<style>
.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
    padding: 32px 0 60px;
    align-items: start;
}
.checkout-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px;
    margin-bottom: 16px;
}
.checkout-section-title {
    font-family: 'Syne', sans-serif;
    font-weight: 700; font-size: 16px;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.checkout-section-title i { color: var(--primary); font-size: 18px; }

.order-mini-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.order-mini-item:last-child { border-bottom: none; }
.order-mini-img {
    width: 52px; height: 52px; border-radius: var(--radius-sm);
    overflow: hidden; flex-shrink: 0;
    background: var(--primary-light);
    display: flex; align-items: center; justify-content: center;
}
.order-mini-img img { width:100%; height:100%; object-fit:cover; }

/* Success screen */
.order-success {
    text-align: center;
    padding: 60px 32px;
}
.success-icon {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(34,197,94,0.12);
    border: 2px solid rgba(34,197,94,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 36px; color: #16a34a;
    margin: 0 auto 24px;
    animation: successPop 0.5s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes successPop { from { transform: scale(0); } to { transform: scale(1); } }

@media (max-width: 900px) {
    .checkout-layout { grid-template-columns: 1fr; }
}
</style>

<div class="shop-container">

<?php if ($order_placed): ?>
<!-- ── Order Success ── -->
<div style="max-width:520px;margin:40px auto 80px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-lg);padding:0;overflow:hidden;" class="fade-up">
    <div style="background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 18%,var(--bg)),var(--bg));padding:40px 32px 32px;text-align:center;border-bottom:1px solid var(--border);">
        <div class="success-icon"><i class="bi bi-bag-check-fill"></i></div>
        <h2 style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;margin-bottom:8px;letter-spacing:-0.5px;">Order Placed!</h2>
        <p style="color:var(--text-muted);font-size:15px;">Thank you for shopping with us.</p>
        <div style="display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary);padding:8px 20px;border-radius:99px;font-family:'Syne',sans-serif;font-weight:700;font-size:18px;margin-top:12px;">
            <i class="bi bi-receipt"></i> Order #<?= str_pad($next_num, 4, '0', STR_PAD_LEFT) ?>
        </div>
    </div>
    <div style="padding:28px 32px;">
        <div style="background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.18);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:20px;display:flex;gap:10px;align-items:center;">
            <i class="bi bi-truck" style="color:#16a34a;font-size:20px;flex-shrink:0;"></i>
            <div style="font-size:13.5px;color:var(--text-muted);">
                Your order will be delivered soon. Payment via <strong style="color:var(--text);">Cash on Delivery</strong>.
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="orders.php?shop=<?= $slug ?>" class="btn-shop-primary" style="width:100%;justify-content:center;padding:13px;">
                <i class="bi bi-list-ul"></i> Track My Order
            </a>
            <?php if (!empty($whatsapp_notify_url)): ?>
            <a href="<?= htmlspecialchars($whatsapp_notify_url) ?>" target="_blank" id="waNotifyBtn"
               style="display:flex;align-items:center;justify-content:center;gap:9px;padding:12px;border-radius:var(--radius);border:1.5px solid #25D366;color:#25D366;font-weight:600;font-size:14px;text-decoration:none;transition:background 0.2s;"
               onmouseover="this.style.background='rgba(37,211,102,0.08)'" onmouseout="this.style.background='transparent'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                Notify Owner via WhatsApp
            </a>
            <?php endif; ?>
            <a href="index.php?shop=<?= $slug ?>" class="btn-shop-outline" style="width:100%;justify-content:center;padding:12px;">
                <i class="bi bi-arrow-left"></i> Continue Shopping
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Checkout Form ── -->
<div style="padding:28px 0 0;" class="fade-up">
    <h1 style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;letter-spacing:-0.6px;margin-bottom:28px;">Checkout</h1>
</div>

<?php if ($error): ?>
<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius-sm);padding:14px 18px;display:flex;gap:10px;align-items:center;margin-bottom:20px;color:#dc2626;font-size:13.5px;" class="fade-up">
    <i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST">
<div class="checkout-layout">

    <!-- Left: Address + Notes -->
    <div>
        <div class="checkout-card fade-up d1">
            <div class="checkout-section-title"><i class="bi bi-geo-alt-fill"></i> Delivery Address</div>
            <div style="display:grid;gap:14px;">
                <div>
                    <div style="font-size:12.5px;font-weight:500;color:var(--text-muted);margin-bottom:7px;">Full Name</div>
                    <input type="text" class="input-shop" value="<?= htmlspecialchars($user['name']) ?>" readonly style="background:color-mix(in srgb,var(--text) 4%,var(--bg));cursor:not-allowed;">
                </div>
                <div>
                    <div style="font-size:12.5px;font-weight:500;color:var(--text-muted);margin-bottom:7px;">Delivery Address *</div>
                    <textarea name="address" class="input-shop" placeholder="Enter your full delivery address..." required style="min-height:100px;"><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                </div>
                <?php if ($user['phone']): ?>
                <div>
                    <div style="font-size:12.5px;font-weight:500;color:var(--text-muted);margin-bottom:7px;">Phone</div>
                    <input type="text" class="input-shop" value="<?= htmlspecialchars($user['phone']) ?>" readonly style="background:color-mix(in srgb,var(--text) 4%,var(--bg));cursor:not-allowed;">
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="checkout-card fade-up d2">
            <div class="checkout-section-title"><i class="bi bi-chat-left-text"></i> Order Notes <span style="font-weight:400;font-size:13px;color:var(--text-muted);">(Optional)</span></div>
            <textarea name="notes" class="input-shop" placeholder="Any special instructions? (e.g. ring doorbell, leave at door)" style="min-height:80px;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>

        <div class="checkout-card fade-up d2" style="background:color-mix(in srgb,var(--primary) 5%,var(--card-bg));border-color:color-mix(in srgb,var(--primary) 20%,var(--border));">
            <div class="checkout-section-title"><i class="bi bi-cash-stack"></i> Payment Method</div>
            <label style="display:flex;align-items:center;gap:14px;padding:14px 16px;border:1.5px solid var(--primary);border-radius:var(--radius-sm);background:var(--primary-light);cursor:pointer;">
                <div style="width:18px;height:18px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-check" style="color:#fff;font-size:11px;"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:14px;">Cash on Delivery</div>
                    <div style="font-size:12.5px;color:var(--text-muted);">Pay when your order arrives</div>
                </div>
                <i class="bi bi-truck" style="margin-left:auto;font-size:20px;color:var(--primary);"></i>
            </label>
        </div>
    </div>

    <!-- Right: Order Summary -->
    <div style="position:sticky;top:calc(var(--navbar-h) + 20px);" class="fade-up d2">
        <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
            <div style="padding:20px 22px;border-bottom:1px solid var(--border);">
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:17px;">Order Summary</div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:2px;"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></div>
            </div>
            <div style="padding:0 22px;">
                <?php foreach ($items as $item): ?>
                <div class="order-mini-item">
                    <div class="order-mini-img">
                        <?php if ($item['image']): ?>
                        <img src="../assets/uploads/products/<?= htmlspecialchars($item['image']) ?>" alt="">
                        <?php else: ?>
                        <i class="bi bi-image" style="color:var(--primary-glow);font-size:20px;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($item['name']) ?></div>
                        <div style="font-size:12.5px;color:var(--text-muted);">&#215;<?= $item['quantity'] ?></div>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:14px;flex-shrink:0;">
                        &#8377;<?= number_format($item['line_total'], 2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="padding:16px 22px;border-top:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;font-size:13.5px;color:var(--text-muted);margin-bottom:8px;">
                    <span>Subtotal</span><span>&#8377;<?= number_format($subtotal,2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13.5px;color:var(--text-muted);margin-bottom:8px;">
                    <span>Delivery</span><span style="color:#16a34a;font-weight:600;">Free</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-family:'Syne',sans-serif;font-weight:800;font-size:20px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
                    <span>Total</span>
                    <span style="color:var(--primary);">&#8377;<?= number_format($subtotal,2) ?></span>
                </div>
            </div>
            <div style="padding:0 22px 22px;">
                <button type="submit" class="btn-shop-primary" style="width:100%;justify-content:center;padding:14px;font-size:15px;border-radius:var(--radius);" id="placeOrderBtn">
                    <i class="bi bi-bag-check"></i> Place Order
                </button>
                <p style="text-align:center;font-size:12px;color:var(--text-muted);margin-top:12px;">
                    <i class="bi bi-shield-check" style="color:var(--primary);margin-right:4px;"></i>
                    Your order is safe &amp; secure
                </p>
            </div>
        </div>

        <a href="cart.php?shop=<?= $slug ?>" style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;font-size:13px;color:var(--text-muted);text-decoration:none;padding:8px;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">
            <i class="bi bi-arrow-left"></i> Back to Cart
        </a>
    </div>

</div>
</form>
<?php endif; ?>
</div>

<?php
$extra_js = '
<script>
document.querySelector("form")?.addEventListener("submit", function() {
    const btn = document.getElementById("placeOrderBtn");
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = \'<i class="bi bi-hourglass-split"></i> Placing Order...\';
    }
});

// Auto-open WhatsApp notify link after success animation settles
const waBtn = document.getElementById("waNotifyBtn");
if (waBtn) {
    setTimeout(() => { window.open(waBtn.href, "_blank"); }, 1500);
}
</script>';

require 'includes/shop_foot.php';
?>