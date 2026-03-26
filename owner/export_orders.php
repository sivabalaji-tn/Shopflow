<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['owner_id'])) { header("Location: login.php"); exit; }

$shop_id   = $_SESSION['shop_id'];
$format    = $_GET['format'] ?? 'csv';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');

$orders = $conn->query("
    SELECT o.id, o.shop_order_number, u.name as customer, u.email, u.phone,
           o.total_amount, o.status, o.payment_method, o.address, o.notes,
           o.created_at,
           GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity, ' @₹', oi.price) SEPARATOR ' | ') as items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.shop_id = $shop_id
      AND DATE(o.created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

$shop = $conn->query("SELECT name FROM shops WHERE id=$shop_id")->fetch_assoc();
$filename = 'orders_' . $shop['name'] . '_' . $date_from . '_to_' . $date_to;

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Customer','Email','Phone','Items','Total (₹)','Status','Payment','Address','Notes','Date']);
    while ($row = $orders->fetch_assoc()) {
        fputcsv($out, [
            str_pad($row['shop_order_number'] ?? $row['id'], 4, '0', STR_PAD_LEFT),
            $row['customer'], $row['email'], $row['phone'] ?? '',
            $row['items'], $row['total_amount'],
            ucfirst(str_replace('_',' ',$row['status'])),
            strtoupper($row['payment_method']),
            $row['address'], $row['notes'] ?? '',
            date('d M Y H:i', strtotime($row['created_at']))
        ]);
    }
    fclose($out);
    exit;
}

// HTML print view (acts as PDF via browser print)
$rows = [];
while ($row = $orders->fetch_assoc()) $rows[] = $row;
$total_revenue = array_sum(array_column($rows, 'total_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Report — <?= htmlspecialchars($shop['name']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;color:#1a1a1a;background:#fff;padding:32px;}
h1{font-size:22px;font-weight:800;margin-bottom:4px;}
.meta{font-size:13px;color:#666;margin-bottom:24px;}
.summary{display:flex;gap:20px;margin-bottom:24px;}
.summary-box{border:1px solid #e5e5e5;border-radius:10px;padding:14px 20px;flex:1;}
.summary-label{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:4px;}
.summary-val{font-size:22px;font-weight:800;}
table{width:100%;border-collapse:collapse;font-size:12.5px;}
th{background:#f5f5f5;padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#555;border-bottom:2px solid #e0e0e0;}
td{padding:10px 12px;border-bottom:1px solid #f0f0f0;vertical-align:top;}
tr:hover td{background:#fafafa;}
.status{padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700;}
.status-delivered{background:#d1fae5;color:#065f46;}
.status-pending{background:#fef3c7;color:#92400e;}
.status-cancelled{background:#fee2e2;color:#991b1b;}
.status-processing{background:#dbeafe;color:#1e40af;}
.status-out_for_delivery{background:#ede9fe;color:#5b21b6;}
.print-btn{position:fixed;top:20px;right:20px;background:#1a1a1a;color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;}
@media print{.print-btn{display:none;}}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>
<h1><?= htmlspecialchars($shop['name']) ?> — Sales Report</h1>
<div class="meta">Period: <?= date('d M Y', strtotime($date_from)) ?> to <?= date('d M Y', strtotime($date_to)) ?> &nbsp;·&nbsp; Generated: <?= date('d M Y H:i') ?></div>

<div class="summary">
    <div class="summary-box">
        <div class="summary-label">Total Orders</div>
        <div class="summary-val"><?= count($rows) ?></div>
    </div>
    <div class="summary-box">
        <div class="summary-label">Total Revenue</div>
        <div class="summary-val">₹<?= number_format($total_revenue, 2) ?></div>
    </div>
    <div class="summary-box">
        <div class="summary-label">Avg. Order Value</div>
        <div class="summary-val">₹<?= count($rows) > 0 ? number_format($total_revenue / count($rows), 2) : '0' ?></div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Order #</th><th>Customer</th><th>Items</th>
            <th>Total</th><th>Status</th><th>Date</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td style="font-weight:700;">#<?= str_pad($row['shop_order_number'] ?? $row['id'],4,'0',STR_PAD_LEFT) ?></td>
        <td>
            <div style="font-weight:600;"><?= htmlspecialchars($row['customer']) ?></div>
            <div style="color:#888;font-size:11.5px;"><?= htmlspecialchars($row['phone'] ?? $row['email']) ?></div>
        </td>
        <td style="max-width:220px;color:#555;"><?= htmlspecialchars($row['items']) ?></td>
        <td style="font-weight:700;">₹<?= number_format($row['total_amount'],2) ?></td>
        <td><span class="status status-<?= $row['status'] ?>"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span></td>
        <td style="color:#888;"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>