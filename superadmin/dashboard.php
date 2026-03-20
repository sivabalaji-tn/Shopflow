<?php
session_start();
require '../config/db.php';

$page_title    = 'Dashboard';
$page_subtitle = 'Platform overview at a glance';

require __DIR__ . '/includes/sidebar.php';

// ── Platform Stats ────────────────────────────────────────────
$total_shops     = $conn->query("SELECT COUNT(*) FROM shops")->fetch_row()[0];
$active_shops    = $conn->query("SELECT COUNT(*) FROM shops WHERE is_active=1 AND (is_suspended IS NULL OR is_suspended=0)")->fetch_row()[0];
$total_owners    = $conn->query("SELECT COUNT(*) FROM owners")->fetch_row()[0];
$total_customers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_orders    = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$total_revenue   = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'")->fetch_row()[0];
$total_products  = $conn->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetch_row()[0];
$pending_orders  = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0];

// ── Revenue last 14 days ──────────────────────────────────────
$chart_labels = $chart_data = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d M', strtotime("-$i days"));
    $rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)='$date' AND status!='cancelled'")->fetch_row()[0];
    $chart_labels[] = $label;
    $chart_data[]   = (float)$rev;
}

// ── Today stats ───────────────────────────────────────────────
$today = date('Y-m-d');
$today_orders  = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetch_row()[0];
$today_revenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled'")->fetch_row()[0];
$today_signups = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)='$today'")->fetch_row()[0];

// ── Recently registered shops ─────────────────────────────────
$recent_shops = $conn->query("
    SELECT s.*, o.name as owner_name, o.email as owner_email,
           COUNT(DISTINCT p.id) as product_count,
           COUNT(DISTINCT ord.id) as order_count
    FROM shops s
    JOIN owners o ON s.owner_id = o.id
    LEFT JOIN products p ON p.shop_id = s.id
    LEFT JOIN orders ord ON ord.shop_id = s.id
    GROUP BY s.id
    ORDER BY s.created_at DESC LIMIT 6
");

// ── Top shops by revenue ──────────────────────────────────────
$top_shops = $conn->query("
    SELECT s.name, s.slug, s.theme_primary,
           COALESCE(SUM(o.total_amount),0) as revenue,
           COUNT(o.id) as orders
    FROM shops s
    LEFT JOIN orders o ON o.shop_id = s.id AND o.status != 'cancelled'
    GROUP BY s.id
    ORDER BY revenue DESC LIMIT 5
");

// ── Orders by status ──────────────────────────────────────────
$status_counts = [];
$statuses = ['pending','processing','out_for_delivery','delivered','cancelled'];
foreach ($statuses as $st) {
    $status_counts[$st] = $conn->query("SELECT COUNT(*) FROM orders WHERE status='$st'")->fetch_row()[0];
}
?>

<!-- ── Today Banner ── -->
<div class="animate-in" style="background:linear-gradient(135deg,rgba(168,85,247,0.12),rgba(124,58,237,0.08));border:1px solid rgba(168,85,247,0.2);border-radius:var(--radius);padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
    <div>
        <div style="font-size:12px;color:var(--accent2);font-weight:600;letter-spacing:0.5px;text-transform:uppercase;">Today — <?= date('l, d F Y') ?></div>
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:22px;margin-top:2px;">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars($_SESSION['superadmin_name']) ?> 👑</div>
    </div>
    <div style="margin-left:auto;display:flex;gap:20px;flex-wrap:wrap;">
        <div style="text-align:center;">
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:22px;color:var(--accent);"><?= $today_orders ?></div>
            <div style="font-size:11.5px;color:var(--muted);">Orders Today</div>
        </div>
        <div style="text-align:center;">
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:22px;color:var(--success);">₹<?= number_format($today_revenue, 0) ?></div>
            <div style="font-size:11.5px;color:var(--muted);">Revenue Today</div>
        </div>
        <div style="text-align:center;">
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:22px;color:var(--info);"><?= $today_signups ?></div>
            <div style="font-size:11.5px;color:var(--muted);">New Customers</div>
        </div>
    </div>
</div>

<!-- ── Stat Cards ── -->
<div class="row g-3 animate-in d1" style="margin-bottom:24px;">
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(168,85,247,0.08);">
            <div class="stat-icon" style="background:var(--accent-dim);color:var(--accent);"><i class="bi bi-shop"></i></div>
            <div>
                <div class="stat-val"><?= $total_shops ?></div>
                <div class="stat-label">Total Shops</div>
                <div class="stat-change" style="color:var(--success);"><?= $active_shops ?> active</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(96,165,250,0.08);">
            <div class="stat-icon" style="background:var(--info-dim);color:var(--info);"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-val"><?= $total_customers ?></div>
                <div class="stat-label">Total Customers</div>
                <div class="stat-change" style="color:var(--muted);"><?= $total_owners ?> owners</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(251,191,36,0.08);">
            <div class="stat-icon" style="background:var(--warning-dim);color:var(--warning);"><i class="bi bi-bag-fill"></i></div>
            <div>
                <div class="stat-val"><?= $total_orders ?></div>
                <div class="stat-label">Total Orders</div>
                <div class="stat-change" style="color:var(--warning);"><?= $pending_orders ?> pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(74,222,128,0.08);">
            <div class="stat-icon" style="background:var(--success-dim);color:var(--success);"><i class="bi bi-currency-rupee"></i></div>
            <div>
                <div class="stat-val">₹<?= number_format($total_revenue, 0) ?></div>
                <div class="stat-label">Platform Revenue</div>
                <div class="stat-change" style="color:var(--muted);"><?= $total_products ?> products</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card-glass animate-in d2" style="height:100%;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <div>
                    <div class="section-title">Platform Revenue</div>
                    <div class="section-sub">Last 14 days across all shops</div>
                </div>
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--accent);">
                    ₹<?= number_format(array_sum($chart_data), 0) ?>
                </div>
            </div>
            <canvas id="revenueChart" height="80"></canvas>
        </div>
    </div>

    <!-- Order Status Donut -->
    <div class="col-lg-4">
        <div class="card-glass animate-in d2" style="height:100%;">
            <div class="section-title" style="margin-bottom:4px;">Order Status</div>
            <div class="section-sub" style="margin-bottom:18px;">All time breakdown</div>
            <canvas id="statusChart" height="160"></canvas>
            <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
                <?php
                $status_colors = ['pending'=>'#fbbf24','processing'=>'#60a5fa','out_for_delivery'=>'#a855f7','delivered'=>'#4ade80','cancelled'=>'#f87171'];
                $status_labels = ['pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                foreach ($status_counts as $st => $count):
                ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $status_colors[$st] ?>;flex-shrink:0;"></div>
                    <span style="color:var(--muted);flex:1;"><?= $status_labels[$st] ?></span>
                    <span style="font-weight:700;"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Top Shops -->
    <div class="col-lg-6">
        <div class="card-glass animate-in d3">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    <div class="section-title">Top Shops by Revenue</div>
                    <div class="section-sub">All time performance</div>
                </div>
                <a href="shops.php" class="btn-ghost-custom" style="font-size:12px;padding:6px 12px;">View All</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php $rank = 1; while ($s = $top_shops->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,0.02);border-radius:10px;border:1px solid rgba(255,255,255,0.04);">
                    <div style="width:28px;height:28px;border-radius:8px;background:var(--accent-dim);color:var(--accent);font-family:'Syne',sans-serif;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">#<?= $rank++ ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:12px;color:var(--muted);"><?= $s['orders'] ?> orders</div>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;color:var(--success);font-size:14px;">₹<?= number_format($s['revenue'], 0) ?></div>
                    <a href="../shop/index.php?shop=<?= $s['slug'] ?>" target="_blank" class="btn-ghost-custom" style="padding:5px 10px;font-size:12px;">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Recently Registered Shops -->
    <div class="col-lg-6">
        <div class="card-glass animate-in d3">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    <div class="section-title">New Shops</div>
                    <div class="section-sub">Recently registered</div>
                </div>
                <a href="shops.php" class="btn-ghost-custom" style="font-size:12px;padding:6px 12px;">View All</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php while ($s = $recent_shops->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,0.02);border-radius:10px;border:1px solid rgba(255,255,255,0.04);">
                    <div style="width:36px;height:36px;border-radius:10px;overflow:hidden;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <?php if ($s['logo']): ?>
                        <img src="../assets/uploads/logos/<?= htmlspecialchars($s['logo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <i class="bi bi-shop" style="color:var(--accent);font-size:16px;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:11.5px;color:var(--muted);"><?= htmlspecialchars($s['owner_name']) ?> · <?= $s['product_count'] ?> products</div>
                    </div>
                    <div>
                        <?php if ($s['is_suspended'] ?? 0): ?>
                        <span class="badge-custom badge-danger"><i class="bi bi-slash-circle"></i> Suspended</span>
                        <?php elseif ($s['is_active']): ?>
                        <span class="badge-custom badge-success"><i class="bi bi-check-circle"></i> Active</span>
                        <?php else: ?>
                        <span class="badge-custom badge-warning"><i class="bi bi-pause-circle"></i> Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
const chartLabels  = <?= json_encode($chart_labels) ?>;
const chartRevenue = <?= json_encode($chart_data) ?>;
const statusLabels = <?= json_encode(array_values(array_map(fn($s) => ucfirst(str_replace('_',' ',$s)), $statuses))) ?>;
const statusValues = <?= json_encode(array_values($status_counts)) ?>;
const statusColors = <?= json_encode(array_values($status_colors)) ?>;

// Revenue chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Revenue (₹)',
            data: chartRevenue,
            borderColor: '#a855f7',
            backgroundColor: 'rgba(168,85,247,0.1)',
            borderWidth: 2.5,
            pointRadius: 3,
            pointBackgroundColor: '#a855f7',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#888', maxTicksLimit: 7 }, grid: { color: 'rgba(255,255,255,0.04)' } },
            y: { ticks: { color: '#888', callback: v => '₹' + v.toLocaleString('en-IN') }, grid: { color: 'rgba(255,255,255,0.04)' } }
        }
    }
});

// Status donut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusValues, backgroundColor: statusColors, borderWidth: 2, borderColor: '#0d0b0e' }]
    },
    options: {
        responsive: true,
        cutout: '72%',
        plugins: { legend: { display: false } }
    }
});
</script>