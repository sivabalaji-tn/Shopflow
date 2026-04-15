<?php
/**
 * TamizhMart - Tax Invoice PDF Generator
 * Matches the Amazon-style invoice template
 * Triggered from: owner/orders.php (print invoice button)
 * Usage: invoice_pdf.php?order_id=123
 */

require '../config/db.php';
//require_once '../auth/owner_auth.php';
//requireOwnerLogin();

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) die('Invalid order ID');

$owner_id = $_SESSION['owner_id'];

// ── Fetch order ──────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
           s.shop_name, s.address AS shop_address, s.city AS shop_city,
           s.state AS shop_state, s.pincode AS shop_pincode,
           ss.logo AS shop_logo, ss.gstin,
           o.shop_order_number
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN shop_settings ss ON s.id = ss.shop_id
    WHERE o.id = ? AND s.owner_id = ?
");
$stmt->bind_param("ii", $order_id, $owner_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) die('Order not found');

// ── Fetch order items ─────────────────────────────────────────
$stmt2 = $conn->prepare("
    SELECT oi.*, p.name AS product_name, p.sku, p.hsn_code
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Tax settings (default 9% CGST + 9% SGST = 18% GST) ──────
$tax_rate = 9; // each component (CGST/SGST)
$shipping_charge = 0; // set from order if available
// Try to get shipping from order total vs items total
$items_total = array_sum(array_column($items, 'subtotal'));

// ── Helpers ───────────────────────────────────────────────────
function fmt($n) { return '₹' . number_format($n, 2); }
function calcNetAmount($subtotal, $tax_rate) {
    // If tax inclusive, back-calculate net: net = subtotal / (1 + taxRate/100)
    // Assuming prices are tax-exclusive (net price)
    return $subtotal;
}
function calcTaxAmount($net, $rate) {
    return round($net * $rate / 100, 2);
}

$invoice_number = 'TM-' . strtoupper(substr($order['shop_name'], 0, 3)) . '-' . date('y') . '-' . str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT);
$order_date     = date('d.m.Y', strtotime($order['created_at']));
$invoice_date   = date('d.m.Y', strtotime($order['created_at']));

// Logo path
$logo_src = '';
if (!empty($order['shop_logo'])) {
    $logo_path = '../uploads/logos/' . $order['shop_logo'];
    if (file_exists($logo_path)) {
        $logo_src = $logo_path;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice #<?= htmlspecialchars($invoice_number) ?></title>
<style>
  /* ── Reset & Base ───────────────────────── */
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #000;
    background: #fff;
  }

  /* ── A5 Page ────────────────────────────── */
  .page {
    width: 148mm;
    min-height: 210mm;
    margin: 0 auto;
    padding: 8mm 8mm 6mm 8mm;
    background: #fff;
  }

  /* ── Print styles ───────────────────────── */
  @media print {
    body { background: #fff; }
    .page { margin: 0; width: 100%; }
    .no-print { display: none !important; }
    @page { size: A5 portrait; margin: 0; }
  }

  /* ── Header section ─────────────────────── */
  .header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 4px;
  }
  .shop-info { flex: 1; }
  .shop-logo {
    width: 24mm;
    height: 24mm;
    object-fit: contain;
    border: 1px solid #ddd;
  }
  .logo-placeholder {
    width: 24mm;
    height: 24mm;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 7pt;
    color: #999;
    border: 1px solid #ddd;
    text-align: center;
  }
  .sold-by-label { font-weight: bold; font-size: 8pt; }
  .shop-name-text { font-size: 9pt; margin-top: 1px; }
  .shop-addr-text { font-size: 7.5pt; color: #333; margin-top: 1px; }

  /* ── Invoice title ──────────────────────── */
  .invoice-title {
    text-align: center;
    font-size: 11pt;
    font-weight: bold;
    border-top: 1.5px solid #000;
    border-bottom: 1.5px solid #000;
    padding: 3px 0;
    margin: 5px 0 2px 0;
  }
  .original-label {
    text-align: right;
    font-size: 8pt;
    color: #444;
    margin-bottom: 4px;
  }

  /* ── Address block ──────────────────────── */
  .address-row {
    display: flex;
    gap: 6px;
    margin-bottom: 4px;
  }
  .address-box {
    flex: 1;
    border: 1px solid #ccc;
    padding: 4px 5px;
    font-size: 7.5pt;
    min-height: 28mm;
  }
  .address-box .addr-label {
    font-weight: bold;
    font-size: 8pt;
    margin-bottom: 2px;
  }

  /* ── Order meta table ───────────────────── */
  .meta-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2px;
    font-size: 8pt;
  }
  .meta-table td {
    padding: 2px 4px;
    border: 1px solid #ccc;
  }
  .meta-table .label { font-weight: bold; white-space: nowrap; }

  /* ── Items table ────────────────────────── */
  .items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    font-size: 7.5pt;
  }
  .items-table th, .items-table td {
    border: 1px solid #000;
    padding: 2px 3px;
    vertical-align: top;
  }
  .items-table thead th {
    background: #000;
    color: #fff;
    font-weight: bold;
    text-align: center;
    font-size: 7pt;
  }
  .items-table thead tr:first-child th { border-bottom: none; }
  .items-table thead tr:last-child th { border-top: 1px solid #555; }
  .items-table .desc-col { text-align: left; width: 30%; }
  .items-table .num-col { text-align: right; white-space: nowrap; }
  .items-table .center-col { text-align: center; }
  .items-table tbody tr:nth-child(even) { background: #fafafa; }

  /* Tax sub-cols */
  .tax-header { text-align: center; }
  .tax-sub { font-size: 6.5pt; text-align: center; }

  /* ── Total row ──────────────────────────── */
  .total-row td {
    font-weight: bold;
    background: #f0f0f0;
    border-top: 2px solid #000;
  }
  .grand-total-row td {
    font-weight: bold;
    background: #000;
    color: #fff;
    border-top: 2px solid #000;
    font-size: 8.5pt;
  }

  /* ── Footer ─────────────────────────────── */
  .invoice-footer {
    text-align: right;
    font-size: 7pt;
    color: #888;
    margin-top: 6px;
    border-top: 1px solid #ddd;
    padding-top: 3px;
  }

  /* ── Print button ───────────────────────── */
  .print-bar {
    text-align: center;
    padding: 12px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
  }
  .btn-print {
    background: #198754;
    color: #fff;
    border: none;
    padding: 8px 24px;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
    margin-right: 8px;
  }
  .btn-back {
    background: #6c757d;
    color: #fff;
    border: none;
    padding: 8px 20px;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
  }
</style>
</head>
<body>

<!-- Print Bar (hidden when printing) -->
<div class="print-bar no-print">
  <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
  <button class="btn-back" onclick="window.history.back()">← Back</button>
</div>

<!-- ═══ A5 Invoice Page ═══ -->
<div class="page">

  <!-- ── Header ── -->
  <div class="header-row">
    <div class="shop-info">
      <div class="sold-by-label">Sold By :</div>
      <div class="shop-name-text"><?= htmlspecialchars($order['shop_name']) ?></div>
      <div class="shop-addr-text">
        <?= htmlspecialchars($order['shop_address'] ?? '') ?>
        <?php if ($order['shop_city']): ?>, <?= htmlspecialchars($order['shop_city']) ?><?php endif; ?><br>
        <?= htmlspecialchars($order['shop_state'] ?? '') ?>
        <?php if ($order['shop_pincode']): ?> - <?= htmlspecialchars($order['shop_pincode']) ?><?php endif; ?>
        <?php if (!empty($order['gstin'])): ?><br>GSTIN: <?= htmlspecialchars($order['gstin']) ?><?php endif; ?>
      </div>
    </div>
    <div>
      <?php if ($logo_src): ?>
        <img src="<?= $logo_src ?>" alt="Logo" class="shop-logo">
      <?php else: ?>
        <div class="logo-placeholder">SHOP<br>LOGO</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Invoice Title ── -->
  <div class="invoice-title">Tax Invoice / Bill of Supply / Cash Memo</div>
  <div class="original-label">(Original for Recipient)</div>

  <!-- ── Address Row ── -->
  <div class="address-row">
    <div class="address-box">
      <div class="addr-label">Billing Address :</div>
      <?= htmlspecialchars($order['customer_name']) ?><br>
      <?= nl2br(htmlspecialchars($order['address'] ?? '')) ?><br>
      <?= htmlspecialchars($order['city'] ?? '') ?>
      <?php if ($order['state'] ?? ''): ?>, <?= htmlspecialchars($order['state']) ?><?php endif; ?>
      <?php if ($order['pincode'] ?? ''): ?> - <?= htmlspecialchars($order['pincode']) ?><?php endif; ?><br>
      IN
      <?php if (!empty($order['customer_phone'])): ?><br>Ph: <?= htmlspecialchars($order['customer_phone']) ?><?php endif; ?>
    </div>
    <div class="address-box">
      <div class="addr-label">Shipping Address :</div>
      <?= htmlspecialchars($order['customer_name']) ?><br>
      <?= nl2br(htmlspecialchars($order['address'] ?? '')) ?><br>
      <?= htmlspecialchars($order['city'] ?? '') ?>
      <?php if ($order['state'] ?? ''): ?>, <?= htmlspecialchars($order['state']) ?><?php endif; ?>
      <?php if ($order['pincode'] ?? ''): ?> - <?= htmlspecialchars($order['pincode']) ?><?php endif; ?><br>
      IN
    </div>
  </div>

  <!-- ── Order Meta ── -->
  <table class="meta-table">
    <tr>
      <td class="label">Order Number:</td>
      <td>#<?= str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT) ?> &nbsp;|&nbsp; Ref: <?= htmlspecialchars($order['id']) ?></td>
      <td class="label">Invoice No:</td>
      <td><?= htmlspecialchars($invoice_number) ?></td>
    </tr>
    <tr>
      <td class="label">Order Date:</td>
      <td><?= $order_date ?></td>
      <td class="label">Invoice Date:</td>
      <td><?= $invoice_date ?></td>
    </tr>
    <tr>
      <td class="label">Payment:</td>
      <td><?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'COD')) ?></td>
      <td class="label">Status:</td>
      <td><?= htmlspecialchars(ucfirst($order['status'] ?? 'pending')) ?></td>
    </tr>
  </table>

  <!-- ── Items Table ── -->
  <table class="items-table">
    <thead>
      <tr>
        <th rowspan="2" style="width:4%">Sl.<br>No</th>
        <th rowspan="2" class="desc-col">Description</th>
        <th rowspan="2" style="width:10%">Unit<br>Price</th>
        <th rowspan="2" style="width:5%">Qty</th>
        <th rowspan="2" style="width:10%">Net<br>Amount</th>
        <th colspan="3" class="tax-header" style="width:24%">Tax</th>
        <th rowspan="2" style="width:11%">Total<br>Amount</th>
      </tr>
      <tr>
        <th class="tax-sub">Rate<br>&amp; Type</th>
        <th class="tax-sub">CGST<br>Amt</th>
        <th class="tax-sub">SGST<br>Amt</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sl = 1;
      $grand_net   = 0;
      $grand_cgst  = 0;
      $grand_sgst  = 0;
      $grand_total = 0;

      foreach ($items as $item):
        $unit_price = floatval($item['price']);
        $qty        = intval($item['quantity']);
        $net_amount = $unit_price * $qty;
        $cgst       = calcTaxAmount($net_amount, $tax_rate);
        $sgst       = calcTaxAmount($net_amount, $tax_rate);
        $total      = $net_amount + $cgst + $sgst;

        $grand_net   += $net_amount;
        $grand_cgst  += $cgst;
        $grand_sgst  += $sgst;
        $grand_total += $total;
      ?>
      <tr>
        <td class="center-col"><?= $sl++ ?></td>
        <td class="desc-col">
          <?= htmlspecialchars($item['product_name']) ?>
          <?php if (!empty($item['sku'])): ?><br><small style="color:#666">SKU: <?= htmlspecialchars($item['sku']) ?></small><?php endif; ?>
          <?php if (!empty($item['hsn_code'])): ?><br><small style="color:#666">HSN: <?= htmlspecialchars($item['hsn_code']) ?></small><?php endif; ?>
        </td>
        <td class="num-col"><?= fmt($unit_price) ?></td>
        <td class="center-col"><?= $qty ?></td>
        <td class="num-col"><?= fmt($net_amount) ?></td>
        <td class="center-col"><?= $tax_rate ?>%<br><small>CGST+SGST</small></td>
        <td class="num-col"><?= fmt($cgst) ?></td>
        <td class="num-col"><?= fmt($sgst) ?></td>
        <td class="num-col"><?= fmt($total) ?></td>
      </tr>
      <?php endforeach; ?>

      <?php
      // Shipping charges row
      $ship_amount = floatval($order['total_amount']) - $grand_total;
      // Fallback: check if there's a separate shipping field
      $shipping_fee = floatval($order['shipping_fee'] ?? 0);
      if ($shipping_fee <= 0 && isset($order['delivery_charge'])) {
          $shipping_fee = floatval($order['delivery_charge']);
      }
      if ($shipping_fee > 0):
        $ship_cgst  = calcTaxAmount($shipping_fee, $tax_rate);
        $ship_sgst  = calcTaxAmount($shipping_fee, $tax_rate);
        $ship_total = $shipping_fee + $ship_cgst + $ship_sgst;
        $grand_net   += $shipping_fee;
        $grand_cgst  += $ship_cgst;
        $grand_sgst  += $ship_sgst;
        $grand_total += $ship_total;
      ?>
      <tr>
        <td class="center-col"><?= $sl++ ?></td>
        <td class="desc-col">Shipping Charges</td>
        <td class="num-col"><?= fmt($shipping_fee) ?></td>
        <td class="center-col">1</td>
        <td class="num-col"><?= fmt($shipping_fee) ?></td>
        <td class="center-col"><?= $tax_rate ?>%</td>
        <td class="num-col"><?= fmt($ship_cgst) ?></td>
        <td class="num-col"><?= fmt($ship_sgst) ?></td>
        <td class="num-col"><?= fmt($ship_total) ?></td>
      </tr>
      <?php endif; ?>

      <!-- Subtotal row -->
      <tr class="total-row">
        <td colspan="4" style="text-align:right">Sub Total</td>
        <td class="num-col"><?= fmt($grand_net) ?></td>
        <td class="center-col">—</td>
        <td class="num-col"><?= fmt($grand_cgst) ?></td>
        <td class="num-col"><?= fmt($grand_sgst) ?></td>
        <td class="num-col"><?= fmt($grand_total) ?></td>
      </tr>

      <!-- Grand Total row -->
      <tr class="grand-total-row">
        <td colspan="8" style="text-align:right;padding-right:8px">GRAND TOTAL</td>
        <td class="num-col" style="text-align:right"><?= fmt(floatval($order['total_amount'])) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- ── Tax Summary ── -->
  <table style="width:60%;margin-top:5px;margin-left:auto;font-size:7.5pt;border-collapse:collapse;">
    <tr style="background:#f0f0f0;">
      <th style="border:1px solid #ccc;padding:2px 4px;text-align:left">Tax Type</th>
      <th style="border:1px solid #ccc;padding:2px 4px;text-align:right">Rate</th>
      <th style="border:1px solid #ccc;padding:2px 4px;text-align:right">Amount</th>
    </tr>
    <tr>
      <td style="border:1px solid #ccc;padding:2px 4px">CGST</td>
      <td style="border:1px solid #ccc;padding:2px 4px;text-align:right"><?= $tax_rate ?>%</td>
      <td style="border:1px solid #ccc;padding:2px 4px;text-align:right"><?= fmt($grand_cgst) ?></td>
    </tr>
    <tr>
      <td style="border:1px solid #ccc;padding:2px 4px">SGST</td>
      <td style="border:1px solid #ccc;padding:2px 4px;text-align:right"><?= $tax_rate ?>%</td>
      <td style="border:1px solid #ccc;padding:2px 4px;text-align:right"><?= fmt($grand_sgst) ?></td>
    </tr>
    <tr style="font-weight:bold;background:#f8f8f8;">
      <td style="border:1px solid #ccc;padding:2px 4px">Total Tax</td>
      <td style="border:1px solid #ccc;padding:2px 4px;text-align:right"><?= $tax_rate * 2 ?>%</td>
      <td style="border:1px solid #ccc;padding:2px 4px;text-align:right"><?= fmt($grand_cgst + $grand_sgst) ?></td>
    </tr>
  </table>

  <!-- ── Footer ── -->
  <div class="invoice-footer">Page 1 of 1 &nbsp;|&nbsp; Generated by TamizhMart</div>

</div><!-- /.page -->

<script>
// Auto-print if ?print=1 in URL
const params = new URLSearchParams(window.location.search);
if (params.get('print') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>