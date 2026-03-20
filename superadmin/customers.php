<?php
session_start();
require '../config/db.php';

$page_title    = 'Customers';
$page_subtitle = 'All registered customers across every shop';

require __DIR__ . '/includes/sidebar.php';

$search   = trim($_GET['search'] ?? '');
$shop_filter = (int)($_GET['shop_id'] ?? 0);

$where = "1=1";
if ($search) $where .= " AND (u.name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR u.email LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
if ($shop_filter) $where .= " AND u.shop_id = $shop_filter";

$customers = $conn->query("
    SELECT u.*, s.name as shop_name, s.slug as shop_slug,
           COUNT(o.id) as order_count,
           COALESCE(SUM(o.total_amount),0) as total_spent,
           MAX(o.created_at) as last_order
    FROM users u
    JOIN shops s ON u.shop_id = s.id
    LEFT JOIN orders o ON o.user_id = u.id AND o.status != 'cancelled'
    WHERE $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

// All shops for filter dropdown
$all_shops = $conn->query("SELECT id, name FROM shops ORDER BY name ASC");
?>

<!-- Filters -->
<div class="card-glass animate-in" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:180px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="search" class="input-custom" placeholder="Search customers..."
                value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
        </div>
        <select name="shop_id" class="input-custom" style="width:180px;" onchange="this.form.submit()">
            <option value="0">All Shops</option>
            <?php $all_shops->data_seek(0); while ($sh = $all_shops->fetch_assoc()): ?>
            <option value="<?= $sh['id'] ?>" <?= $shop_filter==$sh['id']?'selected':'' ?>><?= htmlspecialchars($sh['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Search</button>
        <?php if ($search || $shop_filter): ?><a href="customers.php" class="btn-ghost-custom"><i class="bi bi-x"></i> Clear</a><?php endif; ?>
    </form>
</div>

<!-- Customers Table -->
<div class="card-glass animate-in d1" style="padding:0;overflow:hidden;">
    <table class="table-custom">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Shop</th>
                <th>Orders</th>
                <th>Total Spent</th>
                <th>Last Order</th>
                <th>Status</th>
                <th>Joined</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($customers->num_rows === 0): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No customers found.</td></tr>
        <?php endif; ?>
        <?php while ($c = $customers->fetch_assoc()): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--info-dim);color:var(--info);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:14px;flex-shrink:0;">
                        <?= strtoupper(substr($c['name'],0,1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($c['name']) ?></div>
                        <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['email']) ?></div>
                        <?php if ($c['phone']): ?>
                        <div style="font-size:11.5px;color:var(--muted2);"><?= htmlspecialchars($c['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td>
                <a href="../shop/index.php?shop=<?= $c['shop_slug'] ?>" target="_blank"
                   style="color:var(--accent2);text-decoration:none;font-size:13.5px;font-weight:500;">
                    <?= htmlspecialchars($c['shop_name']) ?> <i class="bi bi-arrow-up-right" style="font-size:11px;"></i>
                </a>
            </td>
            <td>
                <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;"><?= $c['order_count'] ?></span>
            </td>
            <td>
                <span style="font-family:'Syne',sans-serif;font-weight:700;color:var(--success);">
                    ₹<?= number_format($c['total_spent'], 0) ?>
                </span>
            </td>
            <td style="font-size:12.5px;color:var(--muted);">
                <?= $c['last_order'] ? date('d M Y', strtotime($c['last_order'])) : '—' ?>
            </td>
            <td>
                <?php if (($c['is_active'] ?? 1)): ?>
                <span class="badge-custom badge-success"><i class="bi bi-check-circle"></i> Active</span>
                <?php else: ?>
                <span class="badge-custom badge-danger"><i class="bi bi-x-circle"></i> Inactive</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12.5px;color:var(--muted);"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>