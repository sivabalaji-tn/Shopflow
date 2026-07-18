<?php
/**
 * TamizhMart — Razorpay Verify & Place Order
 * POST JSON: { razorpay_order_id, razorpay_payment_id, razorpay_signature,
 *              shop_id, address, notes, amount }
 * Returns JSON: { success, order_number } or { success:false, error }
 */
session_start();
require '../config/db.php';
header('Content-Type: application/json');

function fail($msg) {
    echo json_encode(['success' => false, 'error' => $msg]); exit;
}

if (empty($_SESSION['user_id'])) fail('Not logged in.');

$d = json_decode(file_get_contents('php://input'), true);
if (!$d) fail('Bad request.');

$rz_oid = trim($d['razorpay_order_id']   ?? '');
$rz_pid = trim($d['razorpay_payment_id'] ?? '');
$rz_sig = trim($d['razorpay_signature']  ?? '');
$shop_id = (int)($d['shop_id'] ?? 0);
$address = trim($d['address']  ?? '');
$notes   = trim($d['notes']    ?? '');
$amount  = floatval($d['amount'] ?? 0);

if (!$rz_oid || !$rz_pid || !$rz_sig)  fail('Missing Razorpay payment data.');
if (!$shop_id)                           fail('Missing shop.');
if (!$address)                           fail('Missing delivery address.');

// ── Get this shop's secret ────────────────────────────────────
$row = $conn->query("SELECT setting_value FROM shop_settings
                     WHERE shop_id=$shop_id AND setting_key='razorpay_key_secret' LIMIT 1")->fetch_row();
if (!$row || empty($row[0])) fail('Payment gateway not configured for this shop.');
$secret = $row[0];

// ── Verify Razorpay HMAC signature ───────────────────────────
$expected = hash_hmac('sha256', $rz_oid . '|' . $rz_pid, $secret);
if (!hash_equals($expected, $rz_sig)) {
    error_log("Razorpay sig mismatch shop=$shop_id rz_oid=$rz_oid rz_pid=$rz_pid");
    fail('Payment verification failed. Please contact support.');
}

// ── Signature OK — place order ────────────────────────────────
$user_id = (int)$_SESSION['user_id'];

$shop = $conn->query("SELECT * FROM shops WHERE id=$shop_id LIMIT 1")->fetch_assoc();
$user = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();

$settings_map = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

// Fetch cart
$cq = $conn->query("
    SELECT c.quantity, p.id AS pid, p.name, p.price, p.discount_price
    FROM cart c JOIN products p ON c.product_id = p.id
    WHERE c.user_id=$user_id AND c.shop_id=$shop_id AND p.is_active=1
");
$items = []; $subtotal = 0;
while ($row = $cq->fetch_assoc()) {
    $row['fp']   = floatval($row['discount_price'] ?: $row['price']);
    $row['line'] = $row['fp'] * $row['quantity'];
    $subtotal   += $row['line'];
    $items[]     = $row;
}
if (empty($items)) fail('Cart is empty.');

$conn->begin_transaction();
try {
    // Next order number for this shop
    $nxt = (int)$conn->query("SELECT COALESCE(MAX(shop_order_number),0)+1 FROM orders WHERE shop_id=$shop_id")->fetch_row()[0];

    // Insert order
    // Columns:  shop_id  user_id  total   status      payment  pay_status  rz_oid  rz_pid  address  notes  num
    // Types:      i        i       d                              s           s       s       s        s      i
    $ins = $conn->prepare("
        INSERT INTO orders
            (shop_id, user_id, total_amount, status, payment_method, payment_status,
             razorpay_order_id, razorpay_payment_id, address, notes, shop_order_number)
        VALUES
            (?,       ?,       ?,            'confirmed', 'online', 'paid',
             ?,                  ?,                   ?,       ?,     ?)
    ");
    // Types: i i d s s s s i  = 8 params
    $ins->bind_param('iidssssi',
        $shop_id, $user_id, $subtotal,
        $rz_oid, $rz_pid, $address, $notes,
        $nxt
    );
    $ins->execute();
    $order_id = (int)$conn->insert_id;
    if (!$order_id) throw new Exception('Order insert returned no ID.');

    // Insert items + reduce stock
    foreach ($items as $it) {
        $oi = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
        $oi->bind_param('iiid', $order_id, $it['pid'], $it['quantity'], $it['fp']);
        $oi->execute();
        $conn->query("UPDATE products SET stock=stock-{$it['quantity']} WHERE id={$it['pid']} AND stock>={$it['quantity']}");
    }

    // Clear cart
    $conn->query("DELETE FROM cart WHERE user_id=$user_id AND shop_id=$shop_id");

    $conn->commit();

    // Notifications (non-fatal)
    try {
        require_once 'includes/notifications.php';
        sendOrderNotifications($conn, $order_id, $shop, $user, $items, $subtotal, $settings_map);
    } catch (Throwable $e) {
        error_log('Notification error: ' . $e->getMessage());
    }

    echo json_encode([
        'success'      => true,
        'order_id'     => $order_id,
        'order_number' => str_pad($nxt, 4, '0', STR_PAD_LEFT),
        'payment_id'   => $rz_pid,
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log("Order placement failed after Razorpay payment=$rz_pid : " . $e->getMessage());
    fail('Payment received but order could not be saved. Contact support with Payment ID: ' . $rz_pid);
}