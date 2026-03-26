<?php
session_start();
require '../config/db.php';

$page_title    = 'Customers';
$page_subtitle = 'All registered customers in your shop';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$search  = trim($_GET['q'] ?? '');

$where = "u.shop_id = $shop_id";
if ($search) $where .= " AND (u.name LIKE '%" . $conn->real_escape_string($search) . "%' OR u.email LIKE '%" . $conn->real_escape_string($search) . "%' OR u.phone LIKE '%" . $conn->real_escape_string($search) . "%')";

$customers = $conn->query("
    SELECT u.*,
           COUNT(o.id) as total_orders,
           COALESCE(SUM(o.total_amount),0) as total_spent,
           MAX(o.created_at) as last_order_at
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.shop_id = $shop_id AND o.status != 'cancelled'
    WHERE $where
    GROUP BY u.id
    ORDER BY total_spent DESC
");

$total_customers = $conn->query("SELECT COUNT(*) FROM users WHERE shop_id=$shop_id")->fetch_row()[0];
$total_revenue   = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE shop_id=$shop_id AND status!='cancelled'")->fetch_row()[0];
$avg_spend       = $total_customers > 0 ? round($total_revenue / $total_customers, 2) : 0;
?>

<!-- Stats -->
<div class="row g-3 animate-in" style="margin-bottom:24px;">
    <div class="col-6 col-lg-3">
        <div class="stat-card-small">
            <div class="stat-icon-sm" style="background:var(--info-dim);color:var(--info);"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-val-sm"><?= $total_customers ?></div>
                <div class="stat-lbl-sm">Total Customers</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-small">
            <div class="stat-icon-sm" style="background:var(--success-dim);color:var(--success);"><i class="bi bi-currency-rupee"></i></div>
            <div>
                <div class="stat-val-sm">₹<?= number_format($total_revenue,0) ?></div>
                <div class="stat-lbl-sm">Total Revenue</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-small">
            <div class="stat-icon-sm" style="background:var(--accent-dim);color:var(--accent);"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="stat-val-sm">₹<?= number_format($avg_spend,0) ?></div>
                <div class="stat-lbl-sm">Avg. Spend / Customer</div>
            </div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="card-glass animate-in d1" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="q" class="input-custom" placeholder="Search by name, email or phone..."
                value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
        </div>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Search</button>
        <?php if ($search): ?><a href="customers.php" class="btn-ghost-custom"><i class="bi bi-x"></i> Clear</a><?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <?php foreach (['Customer','Contact','Orders','Total Spent','Last Order','Joined'] as $h): ?>
                <th style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted2);padding:12px 16px;border-bottom:1px solid var(--card-border);text-align:left;white-space:nowrap;"><?= $h ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($customers->num_rows === 0): ?>
        <tr><td colspan="6" style="text-align:center;padding:48px;color:var(--muted);">No customers found.</td></tr>
        <?php endif; ?>
        <?php while ($c = $customers->fetch_assoc()): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--info-dim);color:var(--info);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:14px;flex-shrink:0;">
                        <?= strtoupper(substr($c['name'],0,1)) ?>
                    </div>
                    <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($c['name']) ?></div>
                </div>
            </td>
            <td style="padding:14px 16px;">
                <div style="font-size:13px;"><?= htmlspecialchars($c['email']) ?></div>
                <?php if ($c['phone']): ?><div style="font-size:12px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($c['phone']) ?></div><?php endif; ?>
            </td>
            <td style="padding:14px 16px;">
                <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;"><?= $c['total_orders'] ?></span>
            </td>
            <td style="padding:14px 16px;">
                <span style="font-family:'Syne',sans-serif;font-weight:700;color:var(--success);">₹<?= number_format($c['total_spent'],0) ?></span>
            </td>
            <td style="padding:14px 16px;font-size:12.5px;color:var(--muted);">
                <?= $c['last_order_at'] ? date('d M Y', strtotime($c['last_order_at'])) : '—' ?>
            </td>
            <td style="padding:14px 16px;font-size:12.5px;color:var(--muted);">
                <?= date('d M Y', strtotime($c['created_at'])) ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<style>
.stat-card-small{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:18px;display:flex;align-items:center;gap:14px;}
.stat-icon-sm{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.stat-val-sm{font-family:'Syne',sans-serif;font-weight:800;font-size:22px;line-height:1;}
.stat-lbl-sm{font-size:12px;color:var(--muted);margin-top:4px;}
</style>

<?php require 'includes/footer.php'; ?>