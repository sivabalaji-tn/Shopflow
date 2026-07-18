<?php
/**
 * TamizhMart — Order Notifications
 * Uses PHPMailer + Gmail SMTP (free, no cost)
 *
 * SETUP REQUIRED (one time only):
 *   1. composer require phpmailer/phpmailer  (run in project root)
 *   2. Set your Gmail + App Password below
 */

// ══════════════════════════════════════════════════════════════
//  YOUR GMAIL SETTINGS — fill these in
// ══════════════════════════════════════════════════════════════
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'sivathetechie24@gmail.com');   // ← your Gmail address
define('MAIL_PASSWORD', 'yjqz ofcg htvl qxfu');    // ← 16-char App Password from Step 2
define('MAIL_FROM',     'sivathetechie24@gmail.com');   // ← same Gmail address
define('MAIL_FROMNAME', 'TamizhMart');
// ══════════════════════════════════════════════════════════════

// PHPMailer classes — must be declared outside functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendOrderEmail($to_email, $to_name, $shop_name, $order_num, $items, $total, $address) {
    // Load PHPMailer (manual install — no Composer needed)
    $src = dirname(__DIR__, 2) . '/vendor/phpmailer/src/';
    if (!file_exists($src . 'PHPMailer.php')) {
        error_log('PHPMailer not found. Copy Exception.php, PHPMailer.php, SMTP.php to vendor/phpmailer/src/');
        return false;
    }
    require_once $src . 'Exception.php';
    require_once $src . 'PHPMailer.php';
    require_once $src . 'SMTP.php';

    // Build items HTML
    $items_html = '';
    foreach ($items as $item) {
        $fp    = floatval($item['fp'] ?? $item['price'] ?? 0);
        $qty   = intval($item['quantity']);
        $name  = htmlspecialchars($item['name']);
        $items_html .= "
        <tr>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;'>$name</td>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;text-align:center;'>$qty</td>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;text-align:right;font-weight:700;'>₹" . number_format($fp * $qty, 2) . "</td>
        </tr>";
    }

    $body = "
    <!DOCTYPE html>
    <html><head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>
            <div style='background:#1a1208;padding:28px 32px;text-align:center;'>
                <div style='font-size:22px;font-weight:800;color:#c8a97e;'>$shop_name</div>
                <div style='font-size:13px;color:rgba(255,255,255,0.5);margin-top:4px;'>Order Confirmation</div>
            </div>
            <div style='padding:28px 32px;'>
                <h2 style='font-size:20px;font-weight:800;margin:0 0 6px;'>Thank you, " . htmlspecialchars($to_name) . "! 🎉</h2>
                <p style='color:#666;font-size:14px;margin:0 0 24px;'>Your order has been placed. We'll get it ready soon.</p>
                <div style='background:#faf7f2;border:1px solid #e8ddd0;border-radius:10px;padding:16px;margin-bottom:24px;text-align:center;'>
                    <div style='font-size:13px;color:#888;'>Order Number</div>
                    <div style='font-size:22px;font-weight:800;color:#c8a97e;'>#$order_num</div>
                </div>
                <table style='width:100%;border-collapse:collapse;margin-bottom:16px;'>
                    <thead>
                        <tr style='background:#f9f9f9;'>
                            <th style='padding:10px 16px;text-align:left;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;'>Product</th>
                            <th style='padding:10px 16px;text-align:center;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;'>Qty</th>
                            <th style='padding:10px 16px;text-align:right;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;'>Price</th>
                        </tr>
                    </thead>
                    <tbody>$items_html</tbody>
                </table>
                <div style='display:flex;justify-content:space-between;padding:14px 16px;background:#1a1208;border-radius:10px;margin-bottom:24px;'>
                    <div style='color:rgba(255,255,255,0.6);font-size:14px;font-weight:600;'>Total</div>
                    <div style='color:#c8a97e;font-size:18px;font-weight:800;'>₹" . number_format($total, 2) . "</div>
                </div>
                <div style='background:#f9f9f9;border-radius:10px;padding:16px;margin-bottom:20px;'>
                    <div style='font-size:12px;color:#888;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;'>Delivery Address</div>
                    <div style='font-size:14px;color:#333;line-height:1.6;'>" . nl2br(htmlspecialchars($address)) . "</div>
                </div>
            </div>
            <div style='background:#f5f5f5;padding:16px 32px;text-align:center;border-top:1px solid #eee;'>
                <p style='font-size:12px;color:#aaa;margin:0;'>Automated confirmation from <strong>$shop_name</strong> via TamizhMart</p>
            </div>
        </div>
    </body></html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROMNAME . ' — ' . $shop_name);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(MAIL_FROM, $shop_name);

        $mail->isHTML(true);
        $mail->Subject = "Your order #$order_num is confirmed — $shop_name";
        $mail->Body    = $body;
        $mail->AltBody = "Hi $to_name, your order #$order_num has been placed. Total: ₹" . number_format($total, 2);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed for order #$order_num : " . $e->getMessage());
        return false;
    }
}

function sendWhatsAppNotification($shop_phone, $message) {
    if (empty($shop_phone)) return false;
    $log = date('Y-m-d H:i:s') . " | WhatsApp to $shop_phone: $message\n";
    file_put_contents(__DIR__ . '/../../logs/whatsapp.log', $log, FILE_APPEND | LOCK_EX);
    return true;
}

function sendOrderNotifications($conn, $order_id, $shop, $user, $items, $total, $shop_settings) {
    $row       = $conn->query("SELECT shop_order_number, payment_method FROM orders WHERE id=$order_id")->fetch_assoc();
    $order_num = str_pad($row['shop_order_number'] ?? $order_id, 4, '0', STR_PAD_LEFT);

    // Email to customer
    sendOrderEmail(
        $user['email'],
        $user['name'],
        $shop['name'],
        $order_num,
        $items,
        $total,
        $user['address'] ?? ''
    );

    // WhatsApp log to owner
    $owner_phone = $shop_settings['phone'] ?? '';
    if ($owner_phone) {
        $item_list = implode(', ', array_map(fn($i) => $i['name'] . ' x' . $i['quantity'], $items));
        $payment   = strtoupper($row['payment_method'] ?? 'COD');
        sendWhatsAppNotification($owner_phone,
            "🛍️ New Order #$order_num on {$shop['name']}!\n"
          . "Customer: {$user['name']}\n"
          . "Items: $item_list\n"
          . "Total: ₹" . number_format($total, 2) . "\n"
          . "Payment: $payment"
        );
    }
}