<?php
session_start();
require '../config/db.php';

$page_title    = 'Shop Owners';
$page_subtitle = 'Manage all registered shop owners';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $owner_id = (int)($_POST['owner_id'] ?? 0);

    if ($action === 'suspend') {
        $conn->query("UPDATE owners SET is_suspended=1 WHERE id=$owner_id");
        $conn->query("UPDATE shops SET is_suspended=1, is_active=0 WHERE owner_id=$owner_id");
        $success = "Owner and their shop suspended.";
    } elseif ($action === 'activate') {
        $conn->query("UPDATE owners SET is_suspended=0 WHERE id=$owner_id");
        $conn->query("UPDATE shops SET is_suspended=0, is_active=1 WHERE owner_id=$owner_id");
        $success = "Owner reactivated.";
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM owners WHERE id=$owner_id");
        $success = "Owner and all their data deleted.";
    }
}

require __DIR__ . '/includes/sidebar.php';

$search = trim($_GET['search'] ?? '');
$where  = "1=1";
if ($search) $where .= " AND (o.name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR o.email LIKE '%".mysqli_real_escape_string($conn,$search)."%')";

$owners = $conn->query("
    SELECT o.*,
           s.id as shop_id, s.name as shop_name, s.slug as shop_slug,
           s.is_active as shop_active, s.is_suspended as shop_suspended,
           COUNT(DISTINCT p.id) as product_count,
           COUNT(DISTINCT ord.id) as order_count,
           COALESCE(SUM(ord.total_amount),0) as revenue
    FROM owners o
    LEFT JOIN shops s ON s.owner_id = o.id
    LEFT JOIN products p ON p.shop_id = s.id
    LEFT JOIN orders ord ON ord.shop_id = s.id AND ord.status != 'cancelled'
    WHERE $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
?>

<?php if (isset($success)): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Search -->
<div class="card-glass animate-in" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="search" class="input-custom" placeholder="Search by name or email..."
                value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
        </div>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Search</button>
        <?php if ($search): ?><a href="owners.php" class="btn-ghost-custom"><i class="bi bi-x"></i> Clear</a><?php endif; ?>
    </form>
</div>

<!-- Owners Table -->
<div class="card-glass animate-in d1" style="padding:0;overflow:hidden;">
    <table class="table-custom">
        <thead>
            <tr>
                <th>Owner</th>
                <th>Shop</th>
                <th>Stats</th>
                <th>Revenue</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($owners->num_rows === 0): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No owners found.</td></tr>
        <?php endif; ?>
        <?php while ($o = $owners->fetch_assoc()): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#7c3aed);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:15px;flex-shrink:0;">
                        <?= strtoupper(substr($o['name'],0,1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($o['name']) ?></div>
                        <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($o['email']) ?></div>
                        <?php if ($o['phone']): ?>
                        <div style="font-size:11.5px;color:var(--muted2);"><?= htmlspecialchars($o['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td>
                <?php if ($o['shop_id']): ?>
                <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($o['shop_name']) ?></div>
                <div style="font-size:12px;color:var(--muted);">/?shop=<?= htmlspecialchars($o['shop_slug']) ?></div>
                <?php else: ?>
                <span style="color:var(--muted);font-size:13px;">No shop yet</span>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-size:12.5px;display:flex;flex-direction:column;gap:3px;">
                    <span><i class="bi bi-box-seam" style="color:var(--accent);"></i> <?= $o['product_count'] ?> products</span>
                    <span><i class="bi bi-bag" style="color:var(--warning);"></i> <?= $o['order_count'] ?> orders</span>
                </div>
            </td>
            <td>
                <div style="font-family:'Syne',sans-serif;font-weight:700;color:var(--success);">
                    ₹<?= number_format($o['revenue'], 0) ?>
                </div>
            </td>
            <td>
                <?php if ($o['is_suspended'] ?? 0): ?>
                <span class="badge-custom badge-danger"><i class="bi bi-slash-circle"></i> Suspended</span>
                <?php else: ?>
                <span class="badge-custom badge-success"><i class="bi bi-check-circle"></i> Active</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12.5px;color:var(--muted);"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <?php if ($o['shop_slug']): ?>
                    <a href="../shop/index.php?shop=<?= $o['shop_slug'] ?>" target="_blank"
                       class="btn-ghost-custom" style="padding:5px 10px;font-size:12px;" title="View Shop">
                        <i class="bi bi-eye"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($o['is_suspended'] ?? 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="owner_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn-success-custom" style="padding:5px 10px;font-size:12px;">
                            <i class="bi bi-check-circle"></i> Restore
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="owner_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="action" value="suspend">
                        <button type="submit" class="btn-danger-custom" style="padding:5px 10px;font-size:12px;"
                            onclick="return confirm('Suspend this owner? Their shop will go offline.')">
                            <i class="bi bi-slash-circle"></i> Suspend
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="owner_id" value="<?= $o['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn-danger-custom" style="padding:5px 10px;font-size:12px;"
                            onclick="return confirm('Delete this owner and ALL their shop data permanently?')">
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