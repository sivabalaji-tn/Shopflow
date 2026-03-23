<?php
/**
 * order_email.php — Order confirmation email template
 * 
 * Usage (from checkout.php or anywhere after order is placed):
 *   require '../email/order_email.php';
 *   $html = buildOrderEmail($conn, $order_id, $shop);
 *   // Then use PHP mail() or any SMTP library
 * 
 * For XAMPP / local: use a library like PHPMailer with SMTP
 * Install: download PHPMailer from https://github.com/PHPMailer/PHPMailer
 */

function buildOrderEmail($conn, $order_id, $shop) {
    $order = $conn->query("SELECT o.*, u.name as cname, u.email as cemail, u.phone as cphone FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=$order_id")->fetch_assoc();
    if (!$order) return '';

    $items = $conn->query("SELECT oi.*, p.name as pname FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$order_id");

    $primary = htmlspecialchars($shop['theme_primary'] ?? '#c8a97e');
    $items_html = '';
    while ($item = $items->fetch_assoc()) {
        $items_html .= '
        <tr>
            <td style="padding:12px 0;border-bottom:1px solid #f0ece4;font-size:14px;color:#1a1208;">'. htmlspecialchars($item['pname']) .'</td>
            <td style="padding:12px 0;border-bottom:1px solid #f0ece4;text-align:center;font-size:14px;color:#666;">'. $item['quantity'] .'</td>
            <td style="padding:12px 0;border-bottom:1px solid #f0ece4;text-align:right;font-size:14px;font-weight:600;color:#1a1208;">₹'. number_format($item['price'] * $item['quantity'], 2) .'</td>
        </tr>';
    }

    $status_colors = ['pending'=>'#d97706','processing'=>'#2563eb','out_for_delivery'=>'#7c3aed','delivered'=>'#16a34a','cancelled'=>'#dc2626'];
    $status_color  = $status_colors[$order['status']] ?? '#666';

    return '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmation #'. $order_id .'</title>
</head>
<body style="margin:0;padding:0;background:#f5f3ef;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ef;padding:32px 0;">
<tr><td>
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background:'. $primary .';padding:28px 36px;text-align:center;">
      <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:-0.5px;font-family:Georgia,serif;">'. htmlspecialchars($shop['name']) .'</div>
      <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;">Order Confirmation</div>
    </td>
  </tr>

  <!-- Hero message -->
  <tr>
    <td style="padding:32px 36px 20px;text-align:center;border-bottom:1px solid #f0ece4;">
      <div style="font-size:36px;margin-bottom:12px;">🛍️</div>
      <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#1a1208;">Your order is confirmed!</h1>
      <p style="margin:0;color:#666;font-size:14.5px;line-height:1.6;">Thank you, <strong>'. htmlspecialchars($order['cname']) .'</strong>! We\'ve received your order and it\'s being prepared.</p>
    </td>
  </tr>

  <!-- Order info -->
  <tr>
    <td style="padding:24px 36px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="width:50%;padding:0 6px 0 0;">
            <div style="background:#faf7f2;border-radius:10px;padding:16px;">
              <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:4px;">Order ID</div>
              <div style="font-size:18px;font-weight:800;color:'. $primary .';">#'. $order_id .'</div>
            </div>
          </td>
          <td style="width:50%;padding:0 0 0 6px;">
            <div style="background:#faf7f2;border-radius:10px;padding:16px;">
              <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:4px;">Status</div>
              <div style="font-size:15px;font-weight:700;color:'. $status_color .';">'. ucfirst(str_replace('_',' ',$order['status'])) .'</div>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Items -->
  <tr>
    <td style="padding:0 36px 24px;">
      <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:12px;">Items Ordered</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <th style="text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:#999;padding-bottom:8px;">Product</th>
          <th style="text-align:center;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:#999;padding-bottom:8px;">Qty</th>
          <th style="text-align:right;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:#999;padding-bottom:8px;">Total</th>
        </tr>
        '. $items_html .'
        <tr>
          <td colspan="2" style="padding:16px 0 0;font-size:16px;font-weight:800;color:#1a1208;">Total</td>
          <td style="padding:16px 0 0;text-align:right;font-size:20px;font-weight:900;color:'. $primary .';">₹'. number_format($order['total_amount'],2) .'</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Delivery -->
  <tr>
    <td style="padding:0 36px 24px;">
      <div style="background:#faf7f2;border-radius:10px;padding:16px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:8px;">📍 Delivery Address</div>
        <div style="font-size:14px;color:#1a1208;line-height:1.6;">'. nl2br(htmlspecialchars($order['address'])) .'</div>
      </div>
    </td>
  </tr>

  <!-- Payment -->
  <tr>
    <td style="padding:0 36px 28px;">
      <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;display:flex;gap:10px;">
        <span style="font-size:18px;">💰</span>
        <div>
          <div style="font-size:13.5px;font-weight:600;color:#92400e;">Payment: Cash on Delivery</div>
          <div style="font-size:12.5px;color:#b45309;margin-top:2px;">Please keep ₹'. number_format($order['total_amount'],2) .' ready at the time of delivery.</div>
        </div>
      </div>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#1a1208;padding:24px 36px;text-align:center;">
      <div style="color:rgba(240,236,228,0.6);font-size:12.5px;line-height:1.8;">
        Need help? Contact us anytime.<br>
        © '. date('Y') .' '. htmlspecialchars($shop['name']) .'. All rights reserved.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}


// ── Simple send wrapper ─────────────────────────────────────
// To actually send emails, replace mail() with PHPMailer:s
// https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail.phps
function sendOrderEmail($to_email, $to_name, $subject, $html_body) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ShopFlow <noreply@shopflow.local>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    return mail($to_email, $subject, $html_body, $headers);
}
?>
