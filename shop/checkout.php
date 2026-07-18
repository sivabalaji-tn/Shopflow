<?php
/**
 * TamizhMart — Checkout
 * ALL PHP logic runs first, then HTML output begins.
 * This prevents "headers already sent" errors.
 */
session_start();
require '../config/db.php';

// ── Shop ─────────────────────────────────────────────────────
$slug = $_GET['shop'] ?? $_SESSION['current_shop_slug'] ?? '';
if (!$slug) { header('Location: ../index.php'); exit; }

$st = $conn->prepare("SELECT * FROM shops WHERE slug=? AND is_active=1 LIMIT 1");
$st->bind_param('s', $slug);
$st->execute();
$shop = $st->get_result()->fetch_assoc();
if (!$shop) die('Shop not found.');
$_SESSION['current_shop_slug'] = $slug;
$shop_id = (int)$shop['id'];

// ── Settings ─────────────────────────────────────────────────
$settings_map = [];
$sr = $conn->query("SELECT setting_key,setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

// ── Auth — redirect before any output ────────────────────────
if (empty($_SESSION['user_id'])) {
    header("Location: login.php?shop=$slug&redirect=checkout");
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$user    = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();

// ── Cart — redirect before any output ────────────────────────
$cq = $conn->query("
    SELECT c.quantity, p.id AS pid, p.name, p.price, p.discount_price, p.image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id=$user_id AND c.shop_id=$shop_id AND p.is_active=1
");
$items = []; $subtotal = 0;
while ($row = $cq->fetch_assoc()) {
    $row['fp']   = floatval($row['discount_price'] ?: $row['price']);
    $row['line'] = $row['fp'] * $row['quantity'];
    $subtotal   += $row['line'];
    $items[]     = $row;
}
if (empty($items)) {
    header("Location: cart.php?shop=$slug");
    exit;
}

// ── Razorpay config ───────────────────────────────────────────
$rz_on  = ($settings_map['razorpay_enabled'] ?? '0') === '1'
          && !empty($settings_map['razorpay_key_id'])
          && !empty($settings_map['razorpay_key_secret']);
$rz_key = $settings_map['razorpay_key_id'] ?? '';

// ── COD POST — runs before any HTML output ────────────────────
$cod_done = false; $cod_order_num = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cod') {
    $address = trim($_POST['address'] ?? '');
    $notes   = trim($_POST['notes']   ?? '');
    if (!$address) {
        $err = 'Please enter your delivery address.';
    } else {
        $conn->begin_transaction();
        try {
            $nxt = (int)$conn->query("SELECT COALESCE(MAX(shop_order_number),0)+1 FROM orders WHERE shop_id=$shop_id")->fetch_row()[0];
            $ins = $conn->prepare("INSERT INTO orders (shop_id,user_id,total_amount,status,payment_method,payment_status,address,notes,shop_order_number) VALUES (?,?,?,'pending','cod','pending',?,?,?)");
            $ins->bind_param('iidssi', $shop_id, $user_id, $subtotal, $address, $notes, $nxt);
            $ins->execute();
            $oid = (int)$conn->insert_id;
            foreach ($items as $it) {
                $oi = $conn->prepare("INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)");
                $oi->bind_param('iiid', $oid, $it['pid'], $it['quantity'], $it['fp']);
                $oi->execute();
                $conn->query("UPDATE products SET stock=stock-{$it['quantity']} WHERE id={$it['pid']} AND stock>={$it['quantity']}");
            }
            $conn->query("DELETE FROM cart WHERE user_id=$user_id AND shop_id=$shop_id");
            $conn->commit();
            $cod_done      = true;
            $cod_order_num = str_pad($nxt, 4, '0', STR_PAD_LEFT);
            try {
                require_once 'includes/notifications.php';
                sendOrderNotifications($conn, $oid, $shop, $user, $items, $subtotal, $settings_map);
            } catch (Throwable $e) {}
        } catch (Throwable $e) {
            $conn->rollback();
            $err = 'Order failed. Please try again.';
        }
    }
}

// ════════════════════════════════════════════════════════════
// ALL PHP DONE — HTML output starts here
// ════════════════════════════════════════════════════════════
$page_title = 'Checkout';
require 'includes/shop_head.php';
requireCustomerLogin($shop);
?>
<style>
/* ── Layout ─────────────────────────── */
.co-wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 32px 0 80px;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
/* ── Cards ──────────────────────────── */
.co-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 16px;
}
.co-card-title {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 18px;
    color: var(--text);
}
.co-card-title i { color: var(--primary); }

/* ── Payment options ────────────────── */
.pay-opt {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    cursor: pointer;
    margin-bottom: 10px;
    transition: border-color .15s, background .15s;
    user-select: none;
}
.pay-opt.sel {
    border-color: var(--primary);
    background: var(--primary-light);
}
.pay-dot {
    width: 18px; height: 18px;
    border-radius: 50%;
    border: 2px solid var(--border-mid);
    flex-shrink: 0;
    position: relative;
    transition: all .15s;
}
.pay-opt.sel .pay-dot {
    background: var(--primary);
    border-color: var(--primary);
}
.pay-opt.sel .pay-dot::after {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #fff;
}

/* ── Summary items ──────────────────── */
.sum-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13.5px;
}
.sum-item:last-of-type { border-bottom: none; }
.sum-thumb {
    width: 46px; height: 46px;
    border-radius: var(--radius-sm);
    background: var(--primary-light);
    overflow: hidden; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.sum-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* ── Main button ────────────────────── */
.co-btn {
    width: 100%;
    padding: 14px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: filter .2s, transform .2s;
}
.co-btn:hover:not(:disabled) { filter: brightness(1.1); transform: translateY(-1px); }
.co-btn:disabled { opacity: .65; cursor: not-allowed; transform: none; filter: none; }

/* ── Success screen ─────────────────── */
.success-box {
    max-width: 520px;
    margin: 40px auto 80px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    text-align: center;
}
.success-top {
    padding: 40px 32px 28px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(160deg, var(--primary-light), var(--bg));
}
.success-icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 30px;
    margin: 0 auto 18px;
    animation: pop .5s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.success-body { padding: 28px 32px; }

/* ── Responsive ─────────────────────── */
@media (max-width: 820px) {
    .co-wrap {
        grid-template-columns: 1fr;
        padding: 20px 0 60px;
    }
}
</style>

<div class="shop-container">

<?php if ($cod_done): ?>
<!-- ══ COD SUCCESS ══════════════════════════════════════════ -->
<div class="success-box fade-up">
    <div class="success-top">
        <div class="success-icon" style="background:rgba(34,197,94,.12);border:2px solid rgba(34,197,94,.3);color:#16a34a;">
            <i class="bi bi-bag-check-fill"></i>
        </div>
        <h2 style="font-family:'Syne',sans-serif;font-weight:800;font-size:24px;margin-bottom:6px;">Order Placed!</h2>
        <p style="color:var(--text-muted);font-size:14px;">We have received your order. Pay when it arrives.</p>
        <div style="display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary);padding:8px 20px;border-radius:99px;font-family:'Syne',sans-serif;font-weight:700;font-size:18px;margin-top:14px;">
            <i class="bi bi-receipt"></i> Order #<?= $cod_order_num ?>
        </div>
    </div>
    <div class="success-body">
        <div style="display:flex;align-items:center;gap:10px;background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.2);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;font-size:13.5px;color:var(--text-muted);text-align:left;">
            <i class="bi bi-truck" style="color:#16a34a;font-size:20px;flex-shrink:0;"></i>
            Payment: <strong style="color:var(--text);margin-left:4px;">Cash on Delivery</strong>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="orders.php?shop=<?= $slug ?>" class="btn-shop-primary" style="justify-content:center;padding:13px;">
                <i class="bi bi-list-ul"></i> Track My Order
            </a>
            <a href="index.php?shop=<?= $slug ?>" class="btn-shop-outline" style="justify-content:center;padding:12px;">
                <i class="bi bi-arrow-left"></i> Continue Shopping
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══ ONLINE PAYMENT SUCCESS (shown by JS) ════════════════ -->
<div id="rzSuccess" style="display:none;" class="success-box">
    <div class="success-top">
        <div class="success-icon" style="background:rgba(59,130,246,.12);border:2px solid rgba(59,130,246,.3);color:#2563eb;">
            <i class="bi bi-patch-check-fill"></i>
        </div>
        <h2 style="font-family:'Syne',sans-serif;font-weight:800;font-size:24px;margin-bottom:6px;">Payment Successful!</h2>
        <p style="color:var(--text-muted);font-size:14px;">Your order is confirmed and being processed.</p>
        <div id="rzOrderNum" style="display:inline-flex;align-items:center;gap:8px;background:var(--primary-light);color:var(--primary);padding:8px 20px;border-radius:99px;font-family:'Syne',sans-serif;font-weight:700;font-size:18px;margin-top:14px;">
            <i class="bi bi-receipt"></i> Order #----
        </div>
    </div>
    <div class="success-body">
        <div style="display:flex;align-items:center;gap:10px;background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;font-size:13.5px;color:var(--text-muted);text-align:left;">
            <i class="bi bi-shield-check" style="color:#2563eb;font-size:20px;flex-shrink:0;"></i>
            Payment confirmed via <strong style="color:var(--text);margin-left:4px;">Razorpay</strong>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="orders.php?shop=<?= $slug ?>" class="btn-shop-primary" style="justify-content:center;padding:13px;">
                <i class="bi bi-list-ul"></i> Track My Order
            </a>
            <a href="index.php?shop=<?= $slug ?>" class="btn-shop-outline" style="justify-content:center;padding:12px;">
                <i class="bi bi-arrow-left"></i> Continue Shopping
            </a>
        </div>
    </div>
</div>

<!-- ══ CHECKOUT FORM ═══════════════════════════════════════ -->
<div id="coForm">
    <h1 style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;letter-spacing:-0.5px;padding:28px 0 20px;">
        Checkout
    </h1>

    <?php if ($err): ?>
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#dc2626;border-radius:var(--radius-sm);padding:13px 16px;display:flex;gap:10px;align-items:center;margin-bottom:16px;font-size:13.5px;">
        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($err) ?>
    </div>
    <?php endif; ?>

    <div class="co-wrap">

        <!-- ── LEFT COLUMN ── -->
        <div>
            <!-- Address -->
            <div class="co-card fade-up">
                <div class="co-card-title"><i class="bi bi-geo-alt-fill"></i> Delivery Address</div>
                <div style="display:grid;gap:14px;">
                    <div>
                        <label style="font-size:12.5px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;">Name</label>
                        <input class="input-shop" type="text" value="<?= htmlspecialchars($user['name']) ?>" readonly style="background:color-mix(in srgb,var(--text) 5%,var(--bg));cursor:default;">
                    </div>
                    <?php if (!empty($user['phone'])): ?>
                    <div>
                        <label style="font-size:12.5px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;">Phone</label>
                        <input class="input-shop" type="text" value="<?= htmlspecialchars($user['phone']) ?>" readonly style="background:color-mix(in srgb,var(--text) 5%,var(--bg));cursor:default;">
                    </div>
                    <?php endif; ?>
                    <div>
                        <label style="font-size:12.5px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;">Delivery Address <span style="color:#ef4444;">*</span></label>
                        <textarea id="addrField" class="input-shop" placeholder="House no, Street, Area, City, Pincode..." style="min-height:95px;"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="co-card fade-up" style="animation-delay:.05s;">
                <div class="co-card-title"><i class="bi bi-chat-left-text"></i> Order Notes <span style="font-weight:400;font-size:13px;color:var(--text-muted);">(Optional)</span></div>
                <textarea id="notesField" class="input-shop" placeholder="Special instructions, landmark, leave at gate..." style="min-height:75px;"></textarea>
            </div>

            <!-- Payment method -->
            <div class="co-card fade-up" style="animation-delay:.1s;">
                <div class="co-card-title"><i class="bi bi-credit-card"></i> Payment Method</div>

                <?php if ($rz_on): ?>
                <div class="pay-opt sel" id="optOnline" onclick="pickPay('online')">
                    <div class="pay-dot"></div>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Pay Online</div>
                        <div style="font-size:12px;color:var(--text-muted);">UPI · Cards · Net Banking · Wallets</div>
                    </div>
                    <span style="font-size:10px;font-weight:800;letter-spacing:.5px;background:#072654;color:#fff;padding:3px 8px;border-radius:4px;">RAZORPAY</span>
                </div>
                <?php endif; ?>

                <div class="pay-opt <?= !$rz_on ? 'sel' : '' ?>" id="optCod" onclick="pickPay('cod')">
                    <div class="pay-dot"></div>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:14px;">Cash on Delivery</div>
                        <div style="font-size:12px;color:var(--text-muted);">Pay when your order arrives</div>
                    </div>
                    <i class="bi bi-truck" style="font-size:22px;color:var(--primary);"></i>
                </div>
            </div>
        </div>

        <!-- ── RIGHT COLUMN: Summary ── -->
        <div>
            <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;position:sticky;top:calc(var(--navbar-h,68px) + 16px);" class="fade-up">
                <div style="padding:18px 20px;border-bottom:1px solid var(--border);">
                    <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:16px;">Order Summary</div>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:2px;"><?= count($items) ?> item<?= count($items)!=1?'s':'' ?></div>
                </div>
                <div style="padding:4px 20px;">
                    <?php foreach ($items as $it): ?>
                    <div class="sum-item">
                        <div class="sum-thumb">
                            <?php if ($it['image']): ?>
                            <img src="../assets/uploads/products/<?= htmlspecialchars($it['image']) ?>" alt="">
                            <?php else: ?>
                            <i class="bi bi-image" style="color:var(--primary-glow);"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($it['name']) ?></div>
                            <div style="font-size:12px;color:var(--text-muted);">Qty: <?= $it['quantity'] ?></div>
                        </div>
                        <div style="font-family:'Syne',sans-serif;font-weight:700;flex-shrink:0;">
                            &#8377;<?= number_format($it['line'], 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:14px 20px;border-top:1px solid var(--border);">
                    <div style="display:flex;justify-content:space-between;font-size:13.5px;color:var(--text-muted);margin-bottom:7px;">
                        <span>Subtotal</span><span>&#8377;<?= number_format($subtotal,2) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13.5px;color:var(--text-muted);margin-bottom:7px;">
                        <span>Delivery</span><span style="color:#16a34a;font-weight:600;">Free</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-family:'Syne',sans-serif;font-weight:800;font-size:22px;padding-top:12px;border-top:1px solid var(--border);margin-top:4px;">
                        <span>Total</span>
                        <span style="color:var(--primary);">&#8377;<?= number_format($subtotal,2) ?></span>
                    </div>
                </div>
                <div style="padding:0 20px 20px;">
                    <button class="co-btn" id="mainBtn" onclick="handlePlace()">
                        <i class="bi bi-bag-check" id="btnIco"></i>
                        <span id="btnTxt">Place Order</span>
                    </button>
                    <div style="text-align:center;font-size:12px;color:var(--text-muted);margin-top:10px;">
                        <i class="bi bi-shield-check" style="color:var(--primary);margin-right:3px;"></i>Safe &amp; Secure
                    </div>
                </div>
            </div>
            <a href="cart.php?shop=<?= $slug ?>" style="display:flex;align-items:center;justify-content:center;gap:5px;margin-top:12px;font-size:13px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">
                <i class="bi bi-arrow-left"></i> Back to Cart
            </a>
        </div>

    </div><!-- /.co-wrap -->
</div><!-- /#coForm -->
<?php endif; ?>

</div><!-- /.shop-container -->

<?php
$extra_js = ($rz_on ? '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>' : '') . '
<script>
(function() {
    const RZ_ON    = ' . ($rz_on ? 'true' : 'false') . ';
    const RZ_KEY   = ' . json_encode($rz_key) . ';
    const TOTAL    = ' . json_encode($subtotal) . ';
    const SHOP_ID  = ' . json_encode($shop_id) . ';
    const SLUG     = ' . json_encode($slug) . ';
    const U_NAME   = ' . json_encode($user['name'] ?? '') . ';
    const U_EMAIL  = ' . json_encode($user['email'] ?? '') . ';
    const U_PHONE  = ' . json_encode($user['phone'] ?? '') . ';

    let curPay = RZ_ON ? "online" : "cod";

    window.pickPay = function(m) {
        curPay = m;
        document.getElementById("optCod")?.classList.remove("sel");
        document.getElementById("optOnline")?.classList.remove("sel");
        document.getElementById("opt" + (m === "cod" ? "Cod" : "Online"))?.classList.add("sel");
        const t = document.getElementById("btnTxt");
        const i = document.getElementById("btnIco");
        if (m === "online") {
            t.textContent = "Pay ₹" + TOTAL.toFixed(2);
            i.className   = "bi bi-credit-card";
        } else {
            t.textContent = "Place Order";
            i.className   = "bi bi-bag-check";
        }
    };
    if (RZ_ON) pickPay("online");

    function lock(txt) {
        document.getElementById("mainBtn").disabled = true;
        document.getElementById("btnTxt").textContent = txt;
        document.getElementById("btnIco").className = "bi bi-hourglass-split";
    }
    function unlock() {
        document.getElementById("mainBtn").disabled = false;
        pickPay(curPay);
    }

    window.handlePlace = function() {
        const addr = document.getElementById("addrField").value.trim();
        if (!addr) {
            const f = document.getElementById("addrField");
            f.style.borderColor = "#ef4444";
            f.focus();
            f.scrollIntoView({ behavior: "smooth", block: "center" });
            return;
        }
        document.getElementById("addrField").style.borderColor = "";

        if (curPay === "cod") {
            lock("Placing Order...");
            /* Submit as plain form POST */
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "";
            [
                ["action",  "cod"],
                ["address", addr],
                ["notes",   document.getElementById("notesField").value],
                ["shop",    SLUG]
            ].forEach(([n,v]) => {
                const inp = document.createElement("input");
                inp.type = "hidden"; inp.name = n; inp.value = v;
                form.appendChild(inp);
            });
            document.body.appendChild(form);
            form.submit();
        } else {
            doRazorpay(addr);
        }
    };

    async function doRazorpay(addr) {
        lock("Opening Payment...");
        let rzOrderData;
        try {
            const r = await fetch("razorpay_create_order.php", {
                method:  "POST",
                headers: { "Content-Type": "application/json" },
                body:    JSON.stringify({ shop_id: SHOP_ID, amount: TOTAL })
            });
            rzOrderData = await r.json();
        } catch(e) {
            alert("Network error. Please check your connection and try again.");
            unlock(); return;
        }

        if (rzOrderData.error) {
            alert("Payment Error: " + rzOrderData.error);
            unlock(); return;
        }

        unlock(); /* unlock before opening popup so user can cancel */

        const rzp = new Razorpay({
            key:         RZ_KEY,
            amount:      rzOrderData.amount,
            currency:    "INR",
            order_id:    rzOrderData.razorpay_order_id,
            name:        ' . json_encode($shop['name']) . ',
            description: "Order Payment",
            prefill:     { name: U_NAME, email: U_EMAIL, contact: U_PHONE },
            theme:       { color: getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() || "#6366f1" },

            handler: async function(resp) {
                lock("Confirming Order...");
                let vd;
                try {
                    const vr = await fetch("razorpay_verify.php", {
                        method:  "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            razorpay_order_id:   resp.razorpay_order_id,
                            razorpay_payment_id: resp.razorpay_payment_id,
                            razorpay_signature:  resp.razorpay_signature,
                            shop_id: SHOP_ID,
                            address: addr,
                            notes:   document.getElementById("notesField").value,
                            amount:  TOTAL
                        })
                    });
                    vd = await vr.json();
                } catch(e) {
                    alert("Network error after payment. Your payment may have gone through — please check My Orders before trying again. Payment ID: " + resp.razorpay_payment_id);
                    unlock(); return;
                }

                if (vd.success) {
                    /* Show success panel on the same page — no redirect needed */
                    document.getElementById("coForm").style.display = "none";
                    document.getElementById("rzOrderNum").innerHTML = \'<i class="bi bi-receipt"></i> Order #\' + vd.order_number;
                    const s = document.getElementById("rzSuccess");
                    s.style.display = "block";
                    window.scrollTo({ top: 0, behavior: "smooth" });
                } else {
                    alert("Error: " + vd.error);
                    unlock();
                }
            },

            modal: {
                ondismiss: unlock
            }
        });

        rzp.on("payment.failed", function(r) {
            alert("Payment failed: " + r.error.description + "\nYou can try again or choose Cash on Delivery.");
            unlock();
        });

        rzp.open();
    }
})();
</script>';

require 'includes/shop_foot.php';
?>