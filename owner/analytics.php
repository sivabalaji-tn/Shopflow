<?php
session_start();
require '../config/db.php';

$page_title    = 'Analytics';
$page_subtitle = 'Deep insights into your store performance';

require 'includes/sidebar.php';
//This script is made by Siva Balaji sm

$shop_id = $_SESSION['shop_id'];

// ── Date range filter ────────────────────────────────────────
$range  = $_GET['range'] ?? '30';
$ranges = ['7'=>'Last 7 Days','30'=>'Last 30 Days','90'=>'Last 90 Days','365'=>'This Year'];
if (!array_key_exists($range, $ranges)) $range = '30';
$date_from = date('Y-m-d', strtotime("-{$range} days"));

// ── Revenue over time ────────────────────────────────────────
$group_by = $range <= 30 ? 'DATE(created_at)' : 'DATE_FORMAT(created_at,"%Y-%m")';
$rev_data = $conn->query("
    SELECT $group_by as period, COALESCE(SUM(total_amount),0) as revenue, COUNT(*) as orders
    FROM orders WHERE shop_id=$shop_id AND status!='cancelled' AND created_at >= '$date_from'
    GROUP BY period ORDER BY period
");
$chart_labels = $chart_revenue = $chart_orders = [];
while ($r = $rev_data->fetch_assoc()) {
    $label = $range <= 30 ? date('M j', strtotime($r['period'])) : date('M Y', strtotime($r['period'].'-01'));
    $chart_labels[]  = $label;
    $chart_revenue[] = (float)$r['revenue'];
    $chart_orders[]  = (int)$r['orders'];
}

// ── Summary cards ────────────────────────────────────────────
$period_rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM orders WHERE shop_id=$shop_id AND status!='cancelled' AND created_at>='$date_from'")->fetch_assoc()['t'];
$period_orders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE shop_id=$shop_id AND created_at>='$date_from'")->fetch_assoc()['c'];
$period_customers = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM orders WHERE shop_id=$shop_id AND created_at>='$date_from'")->fetch_assoc()['c'];
$avg_order = $period_orders > 0 ? ($period_rev / $period_orders) : 0;

// Previous period for comparison
$prev_from = date('Y-m-d', strtotime("-" . ($range*2) . " days"));
$prev_rev  = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM orders WHERE shop_id=$shop_id AND status!='cancelled' AND created_at>='$prev_from' AND created_at<'$date_from'")->fetch_assoc()['t'];
$rev_change = $prev_rev > 0 ? (($period_rev - $prev_rev) / $prev_rev * 100) : 0;

// ── Top products ─────────────────────────────────────────────
$top_prods = $conn->query("
    SELECT p.name, p.image, SUM(oi.quantity) as units, SUM(oi.quantity*oi.price) as revenue
    FROM order_items oi JOIN products p ON oi.product_id=p.id
    JOIN orders o ON oi.order_id=o.id
    WHERE p.shop_id=$shop_id AND o.created_at>='$date_from'
    GROUP BY p.id ORDER BY revenue DESC LIMIT 8
");

// ── Category revenue ─────────────────────────────────────────
$cat_rev = $conn->query("
    SELECT c.name, COALESCE(SUM(oi.quantity*oi.price),0) as revenue
    FROM categories c LEFT JOIN products p ON p.category_id=c.id
    LEFT JOIN order_items oi ON oi.product_id=p.id
    LEFT JOIN orders o ON oi.order_id=o.id AND o.created_at>='$date_from'
    WHERE c.shop_id=$shop_id GROUP BY c.id ORDER BY revenue DESC
");
$cat_names = $cat_revenues = [];
while ($r = $cat_rev->fetch_assoc()) {
    $cat_names[]    = $r['name'];
    $cat_revenues[] = (float)$r['revenue'];
}

// ── Orders by hour (heatmap data) ───────────────────────────
$hourly = $conn->query("SELECT HOUR(created_at) as hr, COUNT(*) as c FROM orders WHERE shop_id=$shop_id AND created_at>='$date_from' GROUP BY hr");
$hour_data = array_fill(0, 24, 0);
while ($r = $hourly->fetch_assoc()) $hour_data[$r['hr']] = (int)$r['c'];

// ── Recent customers ─────────────────────────────────────────
$new_customers = $conn->query("
    SELECT u.name, u.email, u.created_at,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id AND o.shop_id=$shop_id) as order_count,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id=u.id AND o.shop_id=$shop_id AND o.status!='cancelled') as total_spent
    FROM users u WHERE u.shop_id=$shop_id ORDER BY u.created_at DESC LIMIT 8
");
?>

<!-- Range Selector -->
<div class="animate-in" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px;align-items:center;justify-content:space-between;">
    <div style="font-size:13.5px;color:var(--muted);">Showing data for: <strong style="color:var(--text);"><?= $ranges[$range] ?></strong></div>
    <div style="display:flex;gap:6px;">
        <?php foreach ($ranges as $val => $label): ?>
        <a href="?range=<?= $val ?>" style="padding:7px 14px;border-radius:99px;font-size:13px;text-decoration:none;border:1px solid;transition:all 0.2s;
            <?= $range == $val ? 'background:var(--accent-dim);border-color:rgba(200,169,126,0.3);color:var(--accent);font-weight:600;' : 'background:transparent;border-color:var(--card-border);color:var(--muted);' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Summary Cards ── -->
<div class="row g-3 animate-in">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(200,169,126,0.12);color:var(--accent);"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">&#8377;<?= number_format($period_rev, 0) ?></div>
            <div class="stat-label">Revenue</div>
            <span class="stat-trend <?= $rev_change >= 0 ? 'trend-up' : 'trend-down' ?>">
                <i class="bi bi-arrow-<?= $rev_change >= 0 ? 'up' : 'down' ?>-right"></i>
                <?= abs(round($rev_change, 1)) ?>% vs prev
            </span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(96,165,250,0.12);color:var(--info);"><i class="bi bi-bag-check"></i></div>
            <div class="stat-value"><?= $period_orders ?></div>
            <div class="stat-label">Orders</div>
            <span class="stat-trend trend-neutral"><i class="bi bi-receipt"></i> <?= $ranges[$range] ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(74,222,128,0.12);color:var(--success);"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= $period_customers ?></div>
            <div class="stat-label">Active Customers</div>
            <span class="stat-trend trend-up"><i class="bi bi-person-check"></i> Ordered in period</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(167,139,250,0.12);color:#a78bfa;"><i class="bi bi-basket"></i></div>
            <div class="stat-value">&#8377;<?= number_format($avg_order, 0) ?></div>
            <div class="stat-label">Avg. Order Value</div>
            <span class="stat-trend trend-neutral"><i class="bi bi-calculator"></i> Per order</span>
        </div>
    </div>
</div>

<!-- ── Revenue & Orders Chart ── -->//This script is made by Siva Balaji sm
<div class="card-glass animate-in d2" style="margin-top:16px;">
    <div class="section-head" style="margin-bottom:20px;">
        <div>
            <div class="section-title">Revenue & Orders Over Time</div>
            <div class="section-sub"><?= $ranges[$range] ?></div>
        </div>
        <div style="display:flex;gap:16px;font-size:12.5px;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--muted);">
                <span style="width:20px;height:2px;background:var(--accent);display:inline-block;border-radius:2px;"></span> Revenue
            </span>
            <span style="display:flex;align-items:center;gap:6px;color:var(--muted);">
                <span style="width:20px;height:2px;background:var(--info);display:inline-block;border-radius:2px;"></span> Orders
            </span>
        </div>
    </div>
    <?php if (empty($chart_labels)): ?>
    <div class="empty-state" style="padding:40px;"><i class="bi bi-graph-up"></i><p>No data for this period yet</p></div>
    <?php else: ?>
    <canvas id="mainChart" height="80"></canvas>
    <?php endif; ?>
</div>

<!-- ── Category Revenue + Top Products ── -->
<div class="row g-3" style="margin-top:4px;">
    <div class="col-lg-5">
        <div class="card-glass animate-in d3" style="height:100%;">
            <div class="section-title" style="margin-bottom:4px;">Revenue by Category</div>
            <div class="section-sub" style="margin-bottom:20px;"><?= $ranges[$range] ?></div>
            <?php if (empty($cat_revenues) || max($cat_revenues) == 0): ?>
            <div class="empty-state" style="padding:32px;"><i class="bi bi-tags"></i><p>No sales data yet</p></div>
            <?php else: ?>
            <canvas id="catChart" height="180"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-glass animate-in d3" style="height:100%;padding:0;overflow:hidden;">
            <div style="padding:22px 22px 0;">
                <div class="section-title">Top Performing Products</div>
                <div class="section-sub" style="margin-bottom:16px;"><?= $ranges[$range] ?></div>
            </div>
            <?php if ($top_prods->num_rows === 0): ?>
            <div class="empty-state" style="padding:40px;"><i class="bi bi-box-seam"></i><p>No sales data yet</p></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th style="padding-left:22px;">Product</th>
                            <th>Units</th>
                            <th>Revenue</th>
                            <th style="padding-right:22px;">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $top_arr = [];
                        while ($p = $top_prods->fetch_assoc()) $top_arr[] = $p;
                        $max_rev = $top_arr ? max(array_column($top_arr, 'revenue')) : 1;
                        foreach ($top_arr as $i => $p):
                        ?>
                        <tr>
                            <td style="padding-left:22px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:32px;height:32px;border-radius:8px;background:var(--card-bg);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                                        <?php if ($p['image']): ?>
                                        <img src="../assets/uploads/products/<?= htmlspecialchars($p['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?>
                                        <i class="bi bi-image" style="color:var(--muted);font-size:13px;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span style="font-size:13.5px;font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['name']) ?></span>
                                </div>
                            </td>
                            <td style="font-weight:600;"><?= $p['units'] ?></td>
                            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent);">&#8377;<?= number_format($p['revenue'],0) ?></td>
                            <td style="padding-right:22px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="flex:1;height:6px;border-radius:99px;background:rgba(255,255,255,0.06);overflow:hidden;min-width:50px;">
                                        <div style="height:100%;border-radius:99px;background:var(--accent);width:<?= round($p['revenue']/$max_rev*100) ?>%;transition:width 0.5s;"></div>
                                    </div>
                                    <span style="font-size:12px;color:var(--muted);white-space:nowrap;"><?= round($p['revenue']/$max_rev*100) ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Hourly Heatmap + New Customers ── -->
<div class="row g-3" style="margin-top:4px;">
    <div class="col-lg-5">
        <div class="card-glass animate-in d4">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-clock" style="color:var(--accent);margin-right:6px;font-size:14px;"></i>Orders by Hour</div>
            <div class="section-sub" style="margin-bottom:18px;">When do your customers order most?</div>
            <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:4px;margin-bottom:8px;">
                <?php
                $max_h = max($hour_data) ?: 1;
                for ($h = 0; $h < 24; $h++):
                    $intensity = $hour_data[$h] / $max_h;
                    $alpha = 0.08 + ($intensity * 0.7);
                ?>
                <div title="<?= $h ?>:00 — <?= $hour_data[$h] ?> orders"
                    style="height:32px;border-radius:5px;background:rgba(200,169,126,<?= round($alpha,2) ?>);position:relative;cursor:default;transition:transform 0.15s;"
                    onmouseenter="this.style.transform='scaleY(1.08)'" onmouseleave="this.style.transform='scaleY(1)'">
                </div>
                <?php endfor; ?>
            </div>
            <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:4px;">
                <?php for ($h = 0; $h < 24; $h += 2): ?>
                <div style="grid-column:span 1;font-size:10px;color:var(--muted);text-align:center;"><?= $h ?>h</div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-glass animate-in d4" style="padding:0;overflow:hidden;">
            <div style="padding:22px 22px 0;">
                <div class="section-title">Recent Customers</div>
                <div class="section-sub" style="margin-bottom:16px;">Latest registered accounts</div>
            </div>
            <?php if ($new_customers->num_rows === 0): ?>
            <div class="empty-state" style="padding:40px;"><i class="bi bi-people"></i><p>No customers yet</p></div>
            <?php else: ?>
            <table class="table-glass">
                <thead>
                    <tr>
                        <th style="padding-left:22px;">Customer</th>
                        <th>Orders</th>
                        <th>Spent</th>
                        <th style="padding-right:22px;">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $new_customers->fetch_assoc()): ?>
                    <tr>
                        <td style="padding-left:22px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--accent),#7a5c2e);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;color:#fff;flex-shrink:0;">
                                    <?= strtoupper(substr($c['name'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-size:13.5px;font-weight:500;"><?= htmlspecialchars($c['name']) ?></div>
                                    <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight:600;"><?= $c['order_count'] ?></td>
                        <td style="color:var(--accent);font-weight:600;">&#8377;<?= number_format($c['total_spent'],0) ?></td>
                        <td style="font-size:12.5px;color:var(--muted);padding-right:22px;"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script>
// Main Revenue + Orders Chart
' . (!empty($chart_labels) ? '
const ctx = document.getElementById("mainChart").getContext("2d");
const grad1 = ctx.createLinearGradient(0,0,0,250);
grad1.addColorStop(0,"rgba(200,169,126,0.25)");
grad1.addColorStop(1,"rgba(200,169,126,0)");
new Chart(ctx, {
    type: "line",
    data: {
        labels: ' . json_encode($chart_labels) . ',
        datasets: [
            {
                label: "Revenue",
                data: ' . json_encode($chart_revenue) . ',
                borderColor: "#c8a97e",
                borderWidth: 2.5,
                fill: true,
                backgroundColor: grad1,
                tension: 0.45,
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: "y"
            },
            {
                label: "Orders",
                data: ' . json_encode($chart_orders) . ',
                borderColor: "#60a5fa",
                borderWidth: 2,
                fill: false,
                tension: 0.45,
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: "y1"
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: "index", intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: "#1a1408",
                borderColor: "rgba(200,169,126,0.25)",
                borderWidth: 1,
                titleColor: "#f0ece4",
                bodyColor: "rgba(240,236,228,0.7)",
                callbacks: {
                    label: ctx => ctx.dataset.label === "Revenue"
                        ? " ₹" + ctx.raw.toLocaleString()
                        : " " + ctx.raw + " orders"
                }
            }
        },
        scales: {
            x: { grid:{color:"rgba(255,255,255,0.04)"}, ticks:{color:"rgba(240,236,228,0.45)",font:{size:12}} },
            y: { position:"left", grid:{color:"rgba(255,255,255,0.04)"}, ticks:{color:"rgba(240,236,228,0.45)",font:{size:12},callback:v=>"₹"+v} },
            y1: { position:"right", grid:{drawOnChartArea:false}, ticks:{color:"rgba(96,165,250,0.7)",font:{size:12}} }
        }
    }
});
' : '') . '

// Category pie
' . (!empty($cat_revenues) && max($cat_revenues) > 0 ? '
const ctx2 = document.getElementById("catChart").getContext("2d");
new Chart(ctx2, {
    type: "doughnut",
    data: {
        labels: ' . json_encode($cat_names) . ',
        datasets: [{
            data: ' . json_encode($cat_revenues) . ',
            backgroundColor: ["#c8a97e","#60a5fa","#4ade80","#f87171","#fbbf24","#a78bfa","#34d399","#fb923c"],
            borderColor: "#111009",
            borderWidth: 3
        }]
    },
    options: {
        responsive: true,
        cutout: "65%",
        plugins: {
            legend: {
                position: "bottom",
                labels: { color:"rgba(240,236,228,0.6)", boxWidth:12, padding:14, font:{size:12} }
            },
            tooltip: {
                backgroundColor:"#1a1408",
                borderColor:"rgba(200,169,126,0.25)",
                borderWidth:1,
                callbacks: { label: ctx => " ₹" + ctx.raw.toLocaleString() }
            }
        }
    }
});
' : '') . '
</script>';

require 'includes/footer.php';
?>
