<?php
session_start();
require '../config/db.php';

$page_title    = 'All Orders';
$page_subtitle = 'Platform-wide order feed across all shops';

require __DIR__ . '/includes/sidebar.php';

$search      = trim($_GET['search'] ?? '');
$shop_filter = (int)($_GET['shop_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$date_filter   = $_GET['date'] ?? '';

$where = "1=1";
if ($search)       $where .= " AND (u.name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR o.id LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
if ($shop_filter)  $where .= " AND o.shop_id = $shop_filter";
if ($status_filter) $where .= " AND o.status = '".mysqli_real_escape_string($conn,$status_filter)."'";
if ($date_filter)  $where .= " AND DATE(o.created_at) = '".mysqli_real_escape_string($conn,$date_filter)."'";

$orders = $conn->query("
    SELECT o.*, u.name as customer_name, u.email as customer_email,
           s.name as shop_name, s.slug as shop_slug,
           COUNT(oi.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 200
");

$all_shops = $conn->query("SELECT id, name FROM shops ORDER BY name ASC");

$status_colors = ['pending'=>'warning','processing'=>'info','out_for_delivery'=>'purple','delivered'=>'success','cancelled'=>'danger'];
$status_labels = ['pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];

// Total filtered revenue
$rev_query = $conn->query("SELECT COALESCE(SUM(o.total_amount),0) FROM orders o JOIN users u ON o.user_id=u.id WHERE $where AND o.status!='cancelled'");
$filtered_revenue = $rev_query->fetch_row()[0];
?>

<!-- Filters -->
<div class="card-glass animate-in" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:160px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="search" class="input-custom" placeholder="Order ID or customer..."
                value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
        </div>
        <select name="shop_id" class="input-custom" style="width:160px;" onchange="this.form.submit()">
            <option value="0">All Shops</option>
            <?php while ($sh = $all_shops->fetch_assoc()): ?>
            <option value="<?= $sh['id'] ?>" <?= $shop_filter==$sh['id']?'selected':'' ?>><?= htmlspecialchars($sh['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <select name="status" class="input-custom" style="width:160px;" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($status_labels as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $status_filter===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" class="input-custom" style="width:150px;" value="<?= htmlspecialchars($date_filter) ?>" onchange="this.form.submit()">
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i></button>
        <?php if ($search || $shop_filter || $status_filter || $date_filter): ?>
        <a href="orders.php" class="btn-ghost-custom"><i class="bi bi-x"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Summary bar -->
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;" class="animate-in d1">
    <div class="card-glass" style="padding:14px 20px;display:flex;align-items:center;gap:12px;">
        <i class="bi bi-bag-fill" style="color:var(--accent);font-size:20px;"></i>
        <div>
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;"><?= $orders->num_rows ?></div>
            <div style="font-size:12px;color:var(--muted);">Orders shown</div>
        </div>
    </div>
    <div class="card-glass" style="padding:14px 20px;display:flex;align-items:center;gap:12px;">
        <i class="bi bi-currency-rupee" style="color:var(--success);font-size:20px;"></i>
        <div>
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--success);">₹<?= number_format($filtered_revenue, 0) ?></div>
            <div style="font-size:12px;color:var(--muted);">Total revenue</div>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <table class="table-custom">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Shop</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($orders->num_rows === 0): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No orders found.</td></tr>
        <?php endif; ?>
        <?php $orders->data_seek(0); while ($o = $orders->fetch_assoc()): ?>
        <tr>
            <td>
                <span style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent2);">#<?= str_pad($o['shop_order_number'] ?? $o['id'], 4, '0', STR_PAD_LEFT) ?></span>
            </td>
            <td>
                <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($o['customer_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($o['customer_email']) ?></div>
            </td>
            <td>
                <a href="../shop/index.php?shop=<?= $o['shop_slug'] ?>" target="_blank"
                   style="color:var(--accent2);text-decoration:none;font-size:13px;font-weight:500;">
                    <?= htmlspecialchars($o['shop_name']) ?> <i class="bi bi-arrow-up-right" style="font-size:11px;"></i>
                </a>
            </td>
            <td style="font-size:13px;"><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
            <td>
                <span style="font-family:'Syne',sans-serif;font-weight:700;color:var(--success);">
                    ₹<?= number_format($o['total_amount'], 2) ?>
                </span>
            </td>
            <td>
                <span class="badge-custom badge-<?= $status_colors[$o['status']] ?>">
                    <?= $status_labels[$o['status']] ?>
                </span>
            </td>
            <td style="font-size:12.5px;color:var(--muted);">
                <?= date('d M Y', strtotime($o['created_at'])) ?>
                <div style="font-size:11.5px;"><?= date('h:i A', strtotime($o['created_at'])) ?></div>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>