<?php
session_start();
require '../config/db.php';

$page_title    = 'All Shops';
$page_subtitle = 'Manage every shop on the platform';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $shop_id = (int)($_POST['shop_id'] ?? 0);

    if ($action === 'suspend') {
        $conn->query("UPDATE shops SET is_suspended=1, is_active=0 WHERE id=$shop_id");
        $success = "Shop suspended.";
    } elseif ($action === 'activate') {
        $conn->query("UPDATE shops SET is_suspended=0, is_active=1 WHERE id=$shop_id");
        $success = "Shop reactivated.";
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM shops WHERE id=$shop_id");
        $success = "Shop deleted permanently.";
    }
}

require __DIR__ . '/includes/sidebar.php';

// Filters
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = "1=1";
if ($search) $where .= " AND (s.name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR s.slug LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR o.name LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
if ($filter === 'active')    $where .= " AND s.is_active=1 AND (s.is_suspended IS NULL OR s.is_suspended=0)";
if ($filter === 'suspended') $where .= " AND s.is_suspended=1";
if ($filter === 'inactive')  $where .= " AND s.is_active=0 AND (s.is_suspended IS NULL OR s.is_suspended=0)";

$shops = $conn->query("
    SELECT s.*, o.name as owner_name, o.email as owner_email, o.phone as owner_phone,
           COUNT(DISTINCT p.id) as product_count,
           COUNT(DISTINCT u.id) as customer_count,
           COUNT(DISTINCT ord.id) as order_count,
           COALESCE(SUM(ord.total_amount),0) as total_revenue
    FROM shops s
    JOIN owners o ON s.owner_id = o.id
    LEFT JOIN products p ON p.shop_id = s.id
    LEFT JOIN users u ON u.shop_id = s.id
    LEFT JOIN orders ord ON ord.shop_id = s.id AND ord.status != 'cancelled'
    WHERE $where
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
?>

<?php if (isset($success)): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Filters & Search -->
<div class="card-glass animate-in" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <form method="GET" style="display:flex;align-items:center;gap:10px;flex:1;min-width:200px;">
            <div style="position:relative;flex:1;">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
                <input type="text" name="search" class="input-custom" placeholder="Search shops, owners..."
                    value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
            </div>
            <select name="filter" class="input-custom" style="width:140px;" onchange="this.form.submit()">
                <option value="all"       <?= $filter==='all'?'selected':'' ?>>All Shops</option>
                <option value="active"    <?= $filter==='active'?'selected':'' ?>>Active</option>
                <option value="suspended" <?= $filter==='suspended'?'selected':'' ?>>Suspended</option>
                <option value="inactive"  <?= $filter==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Search</button>
        </form>
        <?php if ($search || $filter !== 'all'): ?>
        <a href="shops.php" class="btn-ghost-custom"><i class="bi bi-x"></i> Clear</a>
        <?php endif; ?>
    </div>
</div>

<!-- Shops Table -->
<div class="card-glass animate-in d1" style="padding:0;overflow:hidden;">
    <table class="table-custom">
        <thead>
            <tr>
                <th>Shop</th>
                <th>Owner</th>
                <th>Stats</th>
                <th>Revenue</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($shops->num_rows === 0): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No shops found.</td></tr>
        <?php endif; ?>
        <?php while ($s = $shops->fetch_assoc()): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:38px;height:38px;border-radius:10px;overflow:hidden;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <?php if ($s['logo']): ?>
                        <img src="../assets/uploads/logos/<?= htmlspecialchars($s['logo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <i class="bi bi-shop" style="color:var(--accent);font-size:16px;"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:12px;color:var(--muted);">/?shop=<?= htmlspecialchars($s['slug']) ?></div>
                    </div>
                </div>
            </td>
            <td>
                <div style="font-size:13.5px;font-weight:500;"><?= htmlspecialchars($s['owner_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($s['owner_email']) ?></div>
            </td>
            <td>
                <div style="font-size:12.5px;display:flex;flex-direction:column;gap:3px;">
                    <span><i class="bi bi-box-seam" style="color:var(--accent);"></i> <?= $s['product_count'] ?> products</span>
                    <span><i class="bi bi-people" style="color:var(--info);"></i> <?= $s['customer_count'] ?> customers</span>
                    <span><i class="bi bi-bag" style="color:var(--warning);"></i> <?= $s['order_count'] ?> orders</span>
                </div>
            </td>
            <td>
                <div style="font-family:'Syne',sans-serif;font-weight:700;color:var(--success);">
                    ₹<?= number_format($s['total_revenue'], 0) ?>
                </div>
            </td>
            <td>
                <?php if ($s['is_suspended'] ?? 0): ?>
                <span class="badge-custom badge-danger"><i class="bi bi-slash-circle"></i> Suspended</span>
                <?php elseif ($s['is_active']): ?>
                <span class="badge-custom badge-success"><i class="bi bi-check-circle"></i> Active</span>
                <?php else: ?>
                <span class="badge-custom badge-warning"><i class="bi bi-pause-circle"></i> Inactive</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12.5px;color:var(--muted);"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <a href="../shop/index.php?shop=<?= $s['slug'] ?>" target="_blank"
                       class="btn-ghost-custom" style="padding:5px 10px;font-size:12px;" title="Preview">
                        <i class="bi bi-eye"></i>
                    </a>
                    <?php if ($s['is_suspended'] ?? 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn-success-custom" style="padding:5px 10px;font-size:12px;" title="Reactivate">
                            <i class="bi bi-check-circle"></i> Restore
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="action" value="suspend">
                        <button type="submit" class="btn-danger-custom" style="padding:5px 10px;font-size:12px;"
                            onclick="return confirm('Suspend <?= htmlspecialchars(addslashes($s['name'])) ?>? Their storefront will go offline.')" title="Suspend">
                            <i class="bi bi-slash-circle"></i> Suspend
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="shop_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn-danger-custom" style="padding:5px 10px;font-size:12px;"
                            onclick="return confirm('PERMANENTLY delete <?= htmlspecialchars(addslashes($s['name'])) ?> and ALL its data? This cannot be undone.')" title="Delete">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>