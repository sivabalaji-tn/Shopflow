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

$page_title = 'My Orders';
require 'includes/shop_head.php';
requireCustomerLogin($shop);

$user_id = $_SESSION['user_id'];
$orders = $conn->query("SELECT * FROM orders WHERE user_id=$user_id AND shop_id=$shop_id ORDER BY created_at DESC");
?>

<style>
.orders-wrap { padding: 32px 0 60px; max-width: 780px; }

.order-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 16px;
    overflow: hidden;
    transition: var(--transition);
}
.order-card:hover { border-color: var(--border-mid); box-shadow: var(--card-shadow); }

.order-card-header {
    padding: 18px 22px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.2s;
    user-select: none;
}
.order-card-header:hover { background: color-mix(in srgb, var(--primary) 3%, var(--card-bg)); }

.order-id { font-family:'Syne',sans-serif; font-weight:800; font-size:17px; color:var(--primary); }
.order-date { font-size:13px; color:var(--text-muted); }
.order-total { font-family:'Syne',sans-serif; font-weight:800; font-size:17px; }

/* Timeline */
.order-body { padding: 22px; display: none; }
.order-body.open { display: block; }

.timeline {
    display: flex;
    margin-bottom: 28px;
    position: relative;
    overflow-x: auto;
    padding-bottom: 4px;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 18px; left: 0; right: 0; height: 2px;
    background: var(--border);
    z-index: 0;
}
.timeline-step {
    flex: 1; min-width: 80px;
    display: flex; flex-direction: column; align-items: center;
    gap: 8px; position: relative; z-index: 1;
}
.timeline-dot {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 2px solid var(--border);
    background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; color: var(--text-muted);
    transition: var(--transition);
    position: relative;
}
.timeline-dot.done {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 0 0 4px var(--primary-light);
}
.timeline-dot.current {
    border-color: var(--primary);
    color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-light);
    animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{box-shadow:0 0 0 4px var(--primary-light)} 50%{box-shadow:0 0 0 8px var(--primary-light)} }
.timeline-label { font-size:11.5px; font-weight:500; color:var(--text-muted); text-align:center; }
.timeline-label.done,.timeline-label.current { color:var(--primary); font-weight:600; }

/* Cancelled state */
.timeline.cancelled .timeline-step:last-child .timeline-dot { background:#dc2626; border-color:#dc2626; color:#fff; }
.timeline.cancelled .timeline-step:last-child .timeline-label { color:#dc2626; }

/* Order items inside */
.order-items-list { display:flex; flex-direction:column; gap:10px; }
.order-item-row {
    display:flex; align-items:center; gap:12px;
    padding:12px; background:color-mix(in srgb,var(--text) 3%,var(--bg));
    border-radius:var(--radius-sm);
    border:1px solid var(--border);
}
.order-item-img { width:48px;height:48px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--primary-light);display:flex;align-items:center;justify-content:center; }
.order-item-img img { width:100%;height:100%;object-fit:cover; }
</style>

<div class="shop-container">
<div class="orders-wrap">
    <h1 style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;letter-spacing:-0.6px;margin-bottom:28px;" class="fade-up">My Orders</h1>

    <?php if ($orders->num_rows === 0): ?>
    <div style="text-align:center;padding:80px 20px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);" class="fade-up">
        <i class="bi bi-bag-x" style="font-size:60px;color:var(--text-faint);display:block;margin-bottom:18px;"></i>
        <h3 style="font-family:'Syne',sans-serif;font-weight:700;font-size:22px;margin-bottom:12px;">No Orders Yet</h3>
        <p style="color:var(--text-muted);font-size:14.5px;margin-bottom:24px;">Start shopping to place your first order!</p>
        <a href="index.php?shop=<?= $slug ?>" class="btn-shop-primary"><i class="bi bi-grid"></i> Browse Products</a>
    </div>

    <?php else:
    $status_order = ['pending'=>0,'processing'=>1,'out_for_delivery'=>2,'delivered'=>3,'cancelled'=>99];
    $status_icons = ['pending'=>'clock','processing'=>'gear','out_for_delivery'=>'truck','delivered'=>'bag-check-fill','cancelled'=>'x-circle'];
    $status_labels = ['pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
    $timeline_steps = ['pending','processing','out_for_delivery','delivered'];

    $oi = 0;
    while ($order = $orders->fetch_assoc()):
        $oi++;
        $current_step = $status_order[$order['status']] ?? 0;
        $order_items_q = $conn->query("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id={$order['id']}");
    ?>
    <div class="order-card fade-up" style="animation-delay:<?= ($oi * 0.05) ?>s;">
        <div class="order-card-header" onclick="toggleOrder('order<?= $order['id'] ?>')">
            <div>
                <div class="order-id"><i class="bi bi-receipt" style="font-size:14px;margin-right:6px;"></i>Order #<?= str_pad($order['shop_order_number'] ?? $order['id'], 4, '0', STR_PAD_LEFT) ?></div>
                <div class="order-date"><?= date('D, M j, Y \a\t g:i A', strtotime($order['created_at'])) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="order-status status-<?= $order['status'] ?>"><?= $status_labels[$order['status']] ?></span>
                <div class="order-total">&#8377;<?= number_format($order['total_amount'],2) ?></div>
                <i class="bi bi-chevron-down" id="chevron<?= $order['id'] ?>" style="color:var(--text-muted);font-size:14px;transition:transform 0.3s;"></i>
            </div>
        </div>

        <div class="order-body" id="order<?= $order['id'] ?>">
            <?php if ($order['status'] !== 'cancelled'): ?>
            <!-- Timeline -->
            <div class="timeline">
                <?php foreach ($timeline_steps as $si => $step):
                    $step_idx = $status_order[$step];
                    $is_done  = $current_step > $step_idx;
                    $is_curr  = $current_step === $step_idx;
                ?>
                <div class="timeline-step">
                    <div class="timeline-dot <?= $is_done ? 'done' : ($is_curr ? 'current' : '') ?>">
                        <i class="bi bi-<?= $is_done ? 'check-lg' : $status_icons[$step] ?>"></i>
                    </div>
                    <div class="timeline-label <?= $is_done ? 'done' : ($is_curr ? 'current' : '') ?>"><?= $status_labels[$step] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius-sm);padding:12px 16px;display:flex;gap:10px;align-items:center;margin-bottom:22px;font-size:13.5px;color:#dc2626;">
                <i class="bi bi-x-circle-fill"></i> This order has been cancelled.
            </div>
            <?php endif; ?>

            <!-- Address + notes -->
            <div style="display:grid;grid-template-columns:1fr<?= $order['notes'] ? ' 1fr' : '' ?>;gap:12px;margin-bottom:20px;">
                <div style="padding:14px;background:var(--primary-light);border-radius:var(--radius-sm);border:1px solid var(--primary-mid);">
                    <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:1px;color:var(--primary);font-weight:600;margin-bottom:6px;"><i class="bi bi-geo-alt-fill me-1"></i>Delivery Address</div>
                    <div style="font-size:13.5px;line-height:1.5;"><?= nl2br(htmlspecialchars($order['address'])) ?></div>
                </div>
                <?php if ($order['notes']): ?>
                <div style="padding:14px;background:color-mix(in srgb,var(--text) 4%,var(--bg));border-radius:var(--radius-sm);border:1px solid var(--border);">
                    <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);font-weight:600;margin-bottom:6px;"><i class="bi bi-chat-left-text me-1"></i>Notes</div>
                    <div style="font-size:13.5px;line-height:1.5;color:var(--text-muted);"><?= htmlspecialchars($order['notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <div style="font-size:12.5px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;">Items Ordered</div>
            <div class="order-items-list">
                <?php while ($oi_row = $order_items_q->fetch_assoc()): ?>
                <div class="order-item-row">
                    <div class="order-item-img">
                        <?php if ($oi_row['image']): ?>
                        <img src="../assets/uploads/products/<?= htmlspecialchars($oi_row['image']) ?>" alt="">
                        <?php else: ?>
                        <i class="bi bi-image" style="color:var(--primary-glow);font-size:18px;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:500;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($oi_row['name']) ?></div>
                        <div style="font-size:12.5px;color:var(--text-muted);">&#215;<?= $oi_row['quantity'] ?> &middot; &#8377;<?= number_format($oi_row['price'],2) ?> each</div>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:14px;flex-shrink:0;">
                        &#8377;<?= number_format($oi_row['price'] * $oi_row['quantity'],2) ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Total row -->
            <div style="display:flex;justify-content:flex-end;align-items:center;gap:16px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <span style="font-size:13.5px;color:var(--text-muted);">Order Total</span>
                <span style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--primary);">&#8377;<?= number_format($order['total_amount'],2) ?></span>
            </div>
        </div>
    </div>
    <?php endwhile; endif; ?>

</div>
</div>

<?php
$extra_js = '
<script>
function toggleOrder(id) {
    const body = document.getElementById(id);
    const chevron = document.getElementById("chevron" + id.replace("order",""));
    const isOpen  = body.classList.contains("open");
    // Close all
    document.querySelectorAll(".order-body").forEach(b => b.classList.remove("open"));
    document.querySelectorAll("[id^=chevron]").forEach(c => c.style.transform = "");
    // Open this if was closed
    if (!isOpen) {
        body.classList.add("open");
        chevron.style.transform = "rotate(180deg)";
    }
}
// Auto-open first order
const firstBody = document.querySelector(".order-body");
const firstChev = document.querySelector("[id^=chevron]");
if (firstBody) { firstBody.classList.add("open"); if(firstChev) firstChev.style.transform="rotate(180deg)"; }
</script>';

require 'includes/shop_foot.php';
?>