<?php
// includes/notifications.php
// Call sendOrderNotifications($conn, $order_id, $shop, $user, $items, $total) after order is placed

/**
 * Builds a wa.me click-to-chat URL for the given phone + message.
 * Returns the URL string, or false if phone is empty.
 * No API key needed — opens WhatsApp on the owner's device.
 */
function buildWhatsAppURL($phone, $message) {
    if (empty(trim($phone))) return false;

    // Strip everything except digits and leading +
    $clean = preg_replace('/[^0-9]/', '', $phone);

    // If number starts with 0 (local Indian format), replace with 91
    if (strlen($clean) === 10) {
        $clean = '91' . $clean;
    }

    return 'https://wa.me/' . $clean . '?text=' . rawurlencode($message);
}

function sendOrderEmail($to_email, $to_name, $shop_name, $order_num, $items, $total, $address) {
    $subject = "Your order #$order_num has been placed — $shop_name";

    $items_html = '';
    foreach ($items as $item) {
        $items_html .= "
        <tr>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;'>{$item['name']}</td>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;text-align:center;'>{$item['quantity']}</td>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;text-align:right;font-weight:700;'>₹" . number_format($item['price'] * $item['quantity'], 2) . "</td>
        </tr>";
    }

    $body = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>

            <!-- Header -->
            <div style='background:#1a1208;padding:28px 32px;text-align:center;'>
                <div style='font-size:22px;font-weight:800;color:#c8a97e;letter-spacing:-0.5px;'>{$shop_name}</div>
                <div style='font-size:13px;color:rgba(255,255,255,0.5);margin-top:4px;'>Order Confirmation</div>
            </div>

            <!-- Body -->
            <div style='padding:28px 32px;'>
                <h2 style='font-size:20px;font-weight:800;margin:0 0 6px;'>Thank you, {$to_name}! 🎉</h2>
                <p style='color:#666;font-size:14px;margin:0 0 24px;'>Your order has been placed successfully. We'll get it ready for you soon.</p>

                <!-- Order Number -->
                <div style='background:#faf7f2;border:1px solid #e8ddd0;border-radius:10px;padding:16px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;'>
                    <div style='font-size:13px;color:#888;'>Order Number</div>
                    <div style='font-size:18px;font-weight:800;color:#c8a97e;'>#{$order_num}</div>
                </div>

                <!-- Items Table -->
                <table style='width:100%;border-collapse:collapse;margin-bottom:16px;'>
                    <thead>
                        <tr style='background:#f9f9f9;'>
                            <th style='padding:10px 16px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:2px solid #f0f0f0;'>Product</th>
                            <th style='padding:10px 16px;text-align:center;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:2px solid #f0f0f0;'>Qty</th>
                            <th style='padding:10px 16px;text-align:right;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:2px solid #f0f0f0;'>Price</th>
                        </tr>
                    </thead>
                    <tbody>{$items_html}</tbody>
                </table>

                <!-- Total -->
                <div style='display:flex;justify-content:space-between;padding:14px 16px;background:#1a1208;border-radius:10px;margin-bottom:24px;'>
                    <div style='color:rgba(255,255,255,0.6);font-size:14px;font-weight:600;'>Total Amount</div>
                    <div style='color:#c8a97e;font-size:18px;font-weight:800;'>₹" . number_format($total, 2) . "</div>
                </div>

                <!-- Address -->
                <div style='background:#f9f9f9;border-radius:10px;padding:16px;margin-bottom:24px;'>
                    <div style='font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:8px;'>Delivery Address</div>
                    <div style='font-size:14px;color:#333;line-height:1.6;'>{$address}</div>
                </div>

                <!-- Payment -->
                <div style='display:flex;align-items:center;gap:8px;font-size:13.5px;color:#666;'>
                    <span style='background:#e8f5e9;color:#2e7d32;padding:4px 12px;border-radius:99px;font-weight:700;font-size:12px;'>COD</span>
                    Payment will be collected on delivery
                </div>
            </div>

            <!-- Footer -->
            <div style='background:#f5f5f5;padding:18px 32px;text-align:center;border-top:1px solid #eee;'>
                <p style='font-size:12px;color:#aaa;margin:0;'>This is an automated confirmation from <strong>{$shop_name}</strong> via TamizhMart.</p>
            </div>
        </div>
    </body>
    </html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$shop_name} <sivathetechie24@gmail.com>\r\n";

    return mail($to_email, $subject, $body, $headers);
}

/**
 * Sends all post-order notifications.
 * Returns ['whatsapp_url' => string|false] so checkout.php
 * can open the link client-side.
 */
function sendOrderNotifications($conn, $order_id, $shop, $user, $items, $total, $shop_settings) {
    $order_num = $conn->query("SELECT shop_order_number FROM orders WHERE id=$order_id")->fetch_row()[0] ?? $order_id;
    $order_num = str_pad($order_num, 4, '0', STR_PAD_LEFT);

    // ── Email to customer ─────────────────────────────────────
    sendOrderEmail(
        $user['email'],
        $user['name'],
        $shop['name'],
        $order_num,
        $items,
        $total,
        $user['address'] ?? ''
    );

    // ── WhatsApp to shop owner ────────────────────────────────
    // Uses whatsapp_notify_number if set, otherwise falls back to phone.
    // Only fires if whatsapp_notify_enabled = 1.
    $wa_url = false;
    $wa_enabled = ($shop_settings['whatsapp_notify_enabled'] ?? '0') === '1';
    $wa_phone   = trim($shop_settings['whatsapp_notify_number'] ?? $shop_settings['phone'] ?? '');

    if ($wa_enabled && $wa_phone) {
        $item_lines = implode("\n", array_map(
            fn($i) => "  • {$i['name']} × {$i['quantity']}  ₹" . number_format($i['final_price'] * $i['quantity'], 2),
            $items
        ));
        $message = "🛍️ *New Order #{$order_num}* — {$shop['name']}\n\n"
                 . "👤 Customer: {$user['name']}\n"
                 . "📱 Phone: " . ($user['phone'] ?? 'N/A') . "\n\n"
                 . "🧾 Items:\n{$item_lines}\n\n"
                 . "💰 Total: ₹" . number_format($total, 2) . "\n"
                 . "💵 Payment: Cash on Delivery\n\n"
                 . "📍 Deliver to:\n{$user['address']}";

        $wa_url = buildWhatsAppURL($wa_phone, $message);
    }

    return ['whatsapp_url' => $wa_url];
}