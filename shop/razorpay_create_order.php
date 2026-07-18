<?php
/**
 * TamizhMart — Razorpay Create Order (AJAX endpoint)
 * Called from checkout.php before opening the Razorpay popup
 * POST JSON: { shop_id, amount }
 * Returns:   { razorpay_order_id, amount, currency, key_id } or { error }
 */
session_start();
require '../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$shop_id = intval($data['shop_id'] ?? 0);
$amount  = floatval($data['amount'] ?? 0);

if (!$shop_id || $amount <= 0) {
    echo json_encode(['error' => 'Invalid data']); exit;
}

// ── Fetch THIS shop's Razorpay keys ───────────────────────────
$keys = [];
$res  = $conn->query("SELECT setting_key, setting_value FROM shop_settings
                       WHERE shop_id = $shop_id
                       AND setting_key IN ('razorpay_enabled','razorpay_key_id','razorpay_key_secret')");
while ($r = $res->fetch_assoc()) $keys[$r['setting_key']] = $r['setting_value'];

if (($keys['razorpay_enabled'] ?? '0') !== '1') {
    echo json_encode(['error' => 'Online payment not enabled for this shop']); exit;
}
if (empty($keys['razorpay_key_id']) || empty($keys['razorpay_key_secret'])) {
    echo json_encode(['error' => 'Payment gateway not configured by shop owner']); exit;
}

$key_id     = $keys['razorpay_key_id'];
$key_secret = $keys['razorpay_key_secret'];

// ── Create order via Razorpay REST API ────────────────────────
// Amount in paise (₹1 = 100 paise)
$payload = json_encode([
    'amount'          => intval(round($amount * 100)),
    'currency'        => 'INR',
    'receipt'         => 'tm_' . $shop_id . '_' . time(),
    'payment_capture' => 1,
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_USERPWD        => "$key_id:$key_secret",
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Razorpay create order failed (HTTP $http_code): $response");
    echo json_encode(['error' => 'Payment gateway error. Please try Cash on Delivery.']); exit;
}

$rz = json_decode($response, true);

echo json_encode([
    'razorpay_order_id' => $rz['id'],
    'amount'            => $rz['amount'],
    'currency'          => $rz['currency'],
    'key_id'            => $key_id,
]);