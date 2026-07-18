<?php
/**
 * TamizhMart — Payment Receipt Page
 * URL: receipt.php?shop=sm-store&order_id=42
 * Only accessible by the customer who placed the order
 */
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

$order_id = intval($_GET['order_id'] ?? 0);
$user_id  = $_SESSION['user_id'] ?? 0;
if (!$order_id || !$user_id) { header("Location: index.php?shop=$slug"); exit; }

// Fetch order — must belong to this user & shop
$stmt2 = $conn->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id=? AND o.shop_id=? AND o.user_id=?
");
$stmt2->bind_param("iii", $order_id, $shop_id, $user_id);
$stmt2->execute();
$order = $stmt2->get_result()->fetch_assoc();
if (!$order) { header("Location: orders.php?shop=$slug"); exit; }

// Fetch order items
$items_q = $conn->query("
    SELECT oi.*, p.name AS product_name, p.image
    FROM order_items oi JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = $order_id
");
$items = $items_q->fetch_all(MYSQLI_ASSOC);

// Shop settings for logo
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

$is_online  = $order['payment_method'] === 'online';
$is_paid    = $order['payment_status'] === 'paid';
$order_date = date('d M Y, h:i A', strtotime($order['created_at']));

$page_title = 'Payment Receipt';
require 'includes/shop_head.php';
requireCustomerLogin($shop);
?>

<style>
.receipt-wrap {
    max-width: 600px;
    margin: 40px auto 80px;
}
.receipt-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.receipt-header {
    background: linear-gradient(135deg,
        color-mix(in srgb, var(--primary) 20%, var(--bg)),
        var(--bg));
    padding: 36px 32px 28px;
    text-align: center;
    border-bottom: 1px solid var(--border);
}
.receipt-status-icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px;
    margin: 0 auto 16px;
    animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes popIn { from { transform:scale(0); opacity:0; } to { transform:scale(1); opacity:1; } }
.icon-success { background:rgba(34,197,94,0.12); border:2px solid rgba(34,197,94,0.3); color:#16a34a; }
.icon-cod     { background:rgba(var(--primary-rgb,99,102,241),0.12); border:2px solid rgba(var(--primary-rgb,99,102,241),0.3); color:var(--primary); }
.receipt-body { padding: 28px 32px; }
.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13.5px;
}
.receipt-row:last-child { border-bottom: none; }
.receipt-row .label { color: var(--text-muted); }
.receipt-row .value { font-weight: 600; text-align: right; }
.receipt-items { margin: 20px 0; }
.receipt-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13.5px;
}
.receipt-item:last-child { border-bottom: none; }
.receipt-item-img {
    width: 46px; height: 46px; border-radius: var(--radius-sm);
    overflow: hidden; flex-shrink: 0;
    background: var(--primary-light);
    display: flex; align-items: center; justify-content: center;
}
.receipt-item-img img { width:100%; height:100%; object-fit:cover; }
.receipt-total {
    background: var(--primary-light);
    border-radius: var(--radius-sm);
    padding: 16px 20px;
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 16px;
}
.payment-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 99px;
    font-size: 12px; font-weight: 700;
}
.badge-paid { background:rgba(34,197,94,0.12); color:#16a34a; border:1px solid rgba(34,197,94,0.25); }
.badge-cod  { background:rgba(99,102,241,0.1); color:var(--primary); border:1px solid rgba(99,102,241,0.2); }
@media print {
    .no-print { display:none !important; }
    body { background:#fff; }
    .receipt-wrap { max-width:100%; margin:0; }
}
</style>

<div class="shop-container">
<div class="receipt-wrap fade-up">

    <!-- ── Header ── -->
    <div class="receipt-card">
        <div class="receipt-header">
            <?php if ($is_online && $is_paid): ?>
            <div class="receipt-status-icon icon-success">
                <i class="bi bi-patch-check-fill"></i>
            </div>
            <h2 style="font-family:'Syne',sans-serif;font-weight:800;font-size:24px;margin-bottom:6px;">Payment Successful!</h2>
            <p style="color:var(--text-muted);font-size:14px;">Your payment has been confirmed and order is being processed.</p>
            <?php else: ?>
            <div class="receipt-status-icon icon-cod">
                <i class="bi bi-bag-check-fill"></i>
            </div>
            <h2 style="font-family:'Syne',sans-serif;font-weight:800;font-size:24px;margin-bottom:6px;">Order Placed!</h2>
            <p style="color:var(--text-muted);font-size:14px;">Pay when your order arrives at your door.</p>
            <?php endif; ?>

            <!-- Order number pill -->
            <div style="display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary);padding:8px 20px;border-radius:99px;font-family:'Syne',sans-serif;font-weight:700;font-size:17px;margin-top:14px;">
                <i class="bi bi-receipt"></i>
                Order #<?= str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT) ?>
            </div>
        </div>

        <!-- ── Order Details ── -->
        <div class="receipt-body">

            <!-- Payment info -->
            <div style="margin-bottom:20px;">
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:12px;">Payment Details</div>

                <div class="receipt-row">
                    <span class="label">Payment Method</span>
                    <span class="value">
                        <?php if ($is_online): ?>
                        <span class="payment-badge badge-paid"><i class="bi bi-shield-check"></i> Online (Razorpay)</span>
                        <?php else: ?>
                        <span class="payment-badge badge-cod"><i class="bi bi-truck"></i> Cash on Delivery</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="receipt-row">
                    <span class="label">Payment Status</span>
                    <span class="value">
                        <?php if ($is_paid): ?>
                        <span style="color:#16a34a;font-weight:700;">✓ Paid</span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">Pending (Pay on Delivery)</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($order['razorpay_payment_id'])): ?>
                <div class="receipt-row">
                    <span class="label">Transaction ID</span>
                    <span class="value" style="font-family:monospace;font-size:12px;color:var(--text-muted);">
                        <?= htmlspecialchars($order['razorpay_payment_id']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="receipt-row">
                    <span class="label">Order Date</span>
                    <span class="value"><?= $order_date ?></span>
                </div>
                <div class="receipt-row">
                    <span class="label">Order Status</span>
                    <span class="value" style="text-transform:capitalize;"><?= htmlspecialchars($order['status']) ?></span>
                </div>
            </div>

            <!-- Delivery address -->
            <div style="margin-bottom:20px;">
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:12px;">Delivery To</div>
                <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;font-size:13.5px;">
                    <div style="font-weight:600;margin-bottom:4px;"><?= htmlspecialchars($order['customer_name']) ?></div>
                    <div style="color:var(--text-muted);"><?= nl2br(htmlspecialchars($order['address'])) ?></div>
                    <?php if ($order['customer_phone']): ?>
                    <div style="color:var(--text-muted);margin-top:4px;"><i class="bi bi-telephone" style="margin-right:4px;"></i><?= htmlspecialchars($order['customer_phone']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items -->
            <div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:12px;">Items Ordered</div>
                <div class="receipt-items">
                    <?php foreach ($items as $item): ?>
                    <div class="receipt-item">
                        <div class="receipt-item-img">
                            <?php if ($item['image']): ?>
                            <img src="../assets/uploads/products/<?= htmlspecialchars($item['image']) ?>" alt="">
                            <?php else: ?>
                            <i class="bi bi-image" style="color:var(--primary-glow);font-size:18px;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($item['product_name']) ?>
                            </div>
                            <div style="font-size:12.5px;color:var(--text-muted);">Qty: <?= $item['quantity'] ?></div>
                        </div>
                        <div style="font-family:'Syne',sans-serif;font-weight:700;flex-shrink:0;">
                            &#8377;<?= number_format($item['price'] * $item['quantity'], 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Total -->
                <div class="receipt-total">
                    <div>
                        <div style="font-size:13px;color:var(--text-muted);">Total Amount</div>
                        <?php if (!$is_paid): ?>
                        <div style="font-size:11.5px;color:var(--text-muted);">Payable on delivery</div>
                        <?php endif; ?>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:var(--primary);">
                        &#8377;<?= number_format($order['total_amount'], 2) ?>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:24px;" class="no-print">
                <a href="orders.php?shop=<?= $slug ?>" class="btn-shop-primary" style="width:100%;justify-content:center;padding:13px;">
                    <i class="bi bi-list-ul"></i> View My Orders
                </a>
                <button onclick="window.print()" class="btn-shop-outline" style="width:100%;justify-content:center;padding:12px;cursor:pointer;border:none;">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
                <a href="index.php?shop=<?= $slug ?>" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;font-size:13px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">
                    <i class="bi bi-arrow-left"></i> Continue Shopping
                </a>
            </div>

        </div><!-- /.receipt-body -->
    </div><!-- /.receipt-card -->

</div>
</div>

<?php require 'includes/shop_foot.php'; ?>