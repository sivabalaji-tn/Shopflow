<?php
session_start();
require '../config/db.php';

$page_title    = 'Orders';
$page_subtitle = 'Manage and fulfil customer orders';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

// ── Update order status ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $oid    = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $allowed = ['pending','processing','out_for_delivery','delivered','cancelled'];
        if (in_array($status, $allowed)) {
            $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=? AND shop_id=?");
            $stmt->bind_param("sii", $status, $oid, $shop_id);
            $stmt->execute();
            $num = $conn->query("SELECT shop_order_number FROM orders WHERE id=$oid AND shop_id=$shop_id")->fetch_row()[0] ?? $oid;
            $success = "Order #" . str_pad($num, 4, '0', STR_PAD_LEFT) . " status updated to " . ucfirst(str_replace('_',' ',$status));
        }
    }
}

// ── Filters ──────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

$where = "o.shop_id = $shop_id";
if ($filter_status !== 'all') $where .= " AND o.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($search) $where .= " AND (u.name LIKE '%{$conn->real_escape_string($search)}%' OR o.id LIKE '%{$conn->real_escape_string($search)}%')";

$total_rows = $conn->query("SELECT COUNT(*) as c FROM orders o JOIN users u ON o.user_id=u.id WHERE $where")->fetch_assoc()['c'];
$total_pages = ceil($total_rows / $per_page);

$orders = $conn->query("
    SELECT o.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email
    FROM orders o JOIN users u ON o.user_id=u.id
    WHERE $where ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset
");

// Status counts for tabs
$status_tabs = ['all','pending','processing','out_for_delivery','delivered','cancelled'];
$status_counts = [];
foreach ($status_tabs as $s) {
    $w = $s === 'all' ? "shop_id=$shop_id" : "shop_id=$shop_id AND status='$s'";
    $status_counts[$s] = $conn->query("SELECT COUNT(*) as c FROM orders WHERE $w")->fetch_assoc()['c'];
}
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- ── Status Tabs ── -->
<div class="animate-in" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px;">
    <?php
    $tab_labels = ['all'=>'All','pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
    foreach ($status_tabs as $s):
        $active = $filter_status === $s;
    ?>
    <a href="?status=<?= $s ?>&q=<?= urlencode($search) ?>"
        style="display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:99px;font-size:13px;text-decoration:none;border:1px solid;transition:all 0.2s;
        <?= $active ? 'background:var(--accent-dim);border-color:rgba(200,169,126,0.3);color:var(--accent);font-weight:600;' : 'background:transparent;border-color:var(--card-border);color:var(--muted);' ?>">
        <?= $tab_labels[$s] ?>
        <span style="background:<?= $active ? 'rgba(200,169,126,0.3)' : 'rgba(255,255,255,0.08)' ?>;padding:1px 7px;border-radius:99px;font-size:11px;font-weight:700;">
            <?= $status_counts[$s] ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Search & Export ── -->
<div class="card-glass animate-in d2" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <div style="position:relative;flex:1;min-width:200px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by customer name or order ID..."
                class="input-custom" style="padding-left:38px;">
        </div>
        <button type="submit" class="btn-primary-custom" style="padding:10px 18px;">Search</button>
        <?php if ($search): ?><a href="?status=<?= $filter_status ?>" class="btn-ghost-custom">Clear</a><?php endif; ?>
    </form>
</div>

<!-- ── Orders Table ── -->
<div class="card-glass animate-in d3" style="padding:0;overflow:hidden;">
    <?php if ($orders->num_rows === 0): ?>
    <div class="empty-state">
        <i class="bi bi-bag-x"></i>
        <h4>No Orders Found</h4>
        <p><?= $search ? 'Try a different search term' : 'No ' . ($filter_status !== 'all' ? $filter_status . ' ' : '') . 'orders yet' ?></p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table-glass">
            <thead>
                <tr>
                    <th style="padding-left:24px;">Order</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th style="text-align:right;padding-right:24px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = $orders->fetch_assoc()): ?>
                <tr>
                    <td style="padding-left:24px;">
                        <div style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent);">#<?= str_pad($order['shop_order_number'] ?? $order['id'], 4, '0', STR_PAD_LEFT) ?></div>
                        <div style="font-size:11.5px;color:var(--muted);"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:500;"><?= htmlspecialchars($order['customer_name']) ?></div>
                        <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($order['customer_phone'] ?? $order['customer_email'] ?? '') ?></div>
                    </td>
                    <td style="font-family:'Syne',sans-serif;font-weight:700;">&#8377;<?= number_format($order['total_amount'], 2) ?></td>
                    <td><span class="status-pill pill-<?= $order['status'] ?>"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span></td>
                    <td><span style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;"><?= strtoupper($order['payment_method']) ?></span></td>
                    <td style="color:var(--muted);font-size:12.5px;"><?= date('g:i A', strtotime($order['created_at'])) ?></td>
                    <td style="text-align:right;padding-right:24px;">
                        <div style="display:flex;justify-content:flex-end;gap:6px;">
                            <button class="btn-ghost-custom" style="padding:6px 10px;font-size:12px;"
                                onclick="viewOrder(<?= $order['id'] ?>, <?= htmlspecialchars(json_encode($order)) ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-primary-custom" style="padding:6px 12px;font-size:12px;"
                                onclick="openStatusModal(<?= $order['id'] ?>, '<?= $order['status'] ?>')">
                                <i class="bi bi-pencil"></i> Update
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="padding:16px 24px;border-top:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:13px;color:var(--muted);">Showing <?= $offset+1 ?>–<?= min($offset+$per_page, $total_rows) ?> of <?= $total_rows ?></span>
        <div style="display:flex;gap:6px;">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <a href="?status=<?= $filter_status ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"
                style="width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;text-decoration:none;border:1px solid;transition:all 0.2s;
                <?= $p == $page ? 'background:var(--accent-dim);border-color:rgba(200,169,126,0.3);color:var(--accent);font-weight:700;' : 'background:transparent;border-color:var(--card-border);color:var(--muted);' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ── Update Status Modal ── -->
<div class="modal-backdrop-custom" id="statusModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Update Order Status</div>
            <button class="modal-close" onclick="closeModal('statusModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="orders.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="modal_order_id">
            <div style="margin-bottom:18px;">
                <div class="form-label-custom">Order ID</div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;color:var(--accent);" id="modal_order_display"></div>
            </div>
            <div style="margin-bottom:24px;">
                <div class="form-label-custom">New Status</div>
                <select name="status" id="modal_status" class="input-custom">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-check-lg"></i> Save Status
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('statusModal')" style="padding:10px 18px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── View Order Modal ── -->
<div class="modal-backdrop-custom" id="viewModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title">Order Details</div>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="orderDetailContent"></div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

<script>
function openStatusModal(orderId, currentStatus) {
    document.getElementById('modal_order_id').value = orderId;
    document.getElementById('modal_order_display').textContent = '#' + String(orderId).padStart(4, '0');
    document.getElementById('modal_status').value = currentStatus;
    openModal('statusModal');
}

function viewOrder(orderId, order) {
    const statusColors = {
        pending:'#fbbf24',processing:'#60a5fa',
        out_for_delivery:'#a855f7',delivered:'#4ade80',cancelled:'#f87171'
    };
    const statusLabel = {
        pending:'Pending',processing:'Processing',
        out_for_delivery:'Out for Delivery',delivered:'Delivered',cancelled:'Cancelled'
    };
    const color = statusColors[order.status] || '#888';
    const label = statusLabel[order.status] || order.status;
    const orderNum = '#' + String(order.shop_order_number || orderId).padStart(4,'0');

    document.getElementById('orderDetailContent').innerHTML = `
        <div style="display:flex;flex-direction:column;gap:14px;padding-top:4px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--accent);">${orderNum}</div>
                <span style="background:${color}22;color:${color};border:1px solid ${color}44;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:700;">${label}</span>
            </div>
            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Customer</div>
                    <div style="font-weight:600;font-size:13.5px;">${order.customer_name || '—'}</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px;">${order.customer_phone || order.customer_email || ''}</div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Date & Payment</div>
                    <div style="font-weight:600;font-size:13px;">${new Date(order.created_at).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'})}</div>
                    <div style="font-size:12px;color:var(--muted);text-transform:uppercase;margin-top:2px;">${order.payment_method || 'COD'}</div>
                </div>
            </div>
            ${order.address ? `
            <div>
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Delivery Address</div>
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:12px;font-size:13.5px;line-height:1.6;">${order.address}</div>
            </div>` : ''}
            ${order.notes ? `
            <div>
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Order Notes</div>
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:12px;font-size:13.5px;color:var(--muted);">${order.notes}</div>
            </div>` : ''}

            <!-- Picklist -->
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="bi bi-boxes" style="margin-right:5px;color:var(--accent);"></i>Items to Pack
                    </div>
                    <div id="pickProgress" style="font-size:12px;color:var(--muted);font-weight:600;"></div>
                </div>
                <div id="picklistItems" style="display:flex;flex-direction:column;gap:8px;">
                    <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px;">
                        <i class="bi bi-hourglass-split" style="font-size:20px;display:block;margin-bottom:8px;"></i>Loading items...
                    </div>
                </div>
            </div>

            <!-- Total -->
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--accent-dim);border:1px solid rgba(200,169,126,0.2);border-radius:10px;">
                <div style="font-size:13.5px;font-weight:600;">Order Total</div>
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--accent);">₹${parseFloat(order.total_amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:10px;">
                <button onclick="openStatusModal(${orderId},'${order.status}');closeModal('viewModal');"
                    class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-pencil"></i> Update Status
                </button>
                <button onclick="closeModal('viewModal')" class="btn-ghost-custom" style="padding:10px 18px;">Close</button>
            </div>
        </div>
    `;

    openModal('viewModal');

    // Fetch order items via AJAX
    fetch(`order_items_ajax.php?order_id=${orderId}`)
        .then(r => r.json())
        .then(items => {
            const container = document.getElementById('picklistItems');
            const progress  = document.getElementById('pickProgress');

            if (!items.length) {
                container.innerHTML = '<div style="text-align:center;padding:16px;color:var(--muted);font-size:13px;">No items found.</div>';
                return;
            }

            const updateProgress = () => {
                const checked = container.querySelectorAll('input[type=checkbox]:checked').length;
                progress.textContent = checked + ' / ' + items.length + ' picked';
                progress.style.color = checked === items.length ? 'var(--success)' : 'var(--muted)';
            };

            container.innerHTML = items.map((item, idx) => {
                const imgSrc = item.image
                    ? (item.image.startsWith('http') ? item.image : `../assets/uploads/products/${item.image}`)
                    : null;
                return `
                <label id="pickrow_${idx}" style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,0.02);border:1.5px solid rgba(255,255,255,0.06);border-radius:12px;cursor:pointer;transition:all 0.2s;">
                    <input type="checkbox" id="pick_${idx}" onchange="handlePick(this,${idx})"
                        style="width:20px;height:20px;accent-color:var(--success);flex-shrink:0;cursor:pointer;">
                    <div style="width:48px;height:48px;border-radius:10px;overflow:hidden;background:var(--card-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        ${imgSrc ? `<img src="${imgSrc}" style="width:100%;height:100%;object-fit:cover;">` : `<i class="bi bi-image" style="color:var(--muted);font-size:18px;"></i>`}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.name}</div>
                        ${item.cat_name ? `<div style="font-size:11.5px;color:var(--muted);margin-top:2px;">${item.cat_name}</div>` : ''}
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:14px;color:var(--accent);">₹${parseFloat(item.price).toLocaleString('en-IN',{minimumFractionDigits:2})}</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px;">Qty: <strong style="color:var(--text);">${item.quantity}</strong></div>
                    </div>
                    <div id="pickbadge_${idx}" style="display:none;font-size:11px;font-weight:700;color:var(--success);background:var(--success-dim);padding:3px 9px;border-radius:99px;flex-shrink:0;align-items:center;gap:4px;">
                        <i class="bi bi-check2"></i> Picked
                    </div>
                </label>`;
            }).join('');

            updateProgress();
            items.forEach((_, idx) => {
                document.getElementById(`pick_${idx}`)?.addEventListener('change', updateProgress);
            });
        })
        .catch(() => {
            document.getElementById('picklistItems').innerHTML =
                '<div style="text-align:center;padding:16px;color:var(--danger);font-size:13px;"><i class="bi bi-exclamation-circle"></i> Failed to load items.</div>';
        });
}

function handlePick(checkbox, idx) {
    const row   = document.getElementById('pickrow_' + idx);
    const badge = document.getElementById('pickbadge_' + idx);
    if (checkbox.checked) {
        row.style.borderColor = 'rgba(74,222,128,0.35)';
        row.style.background  = 'rgba(74,222,128,0.05)';
        badge.style.display   = 'inline-flex';
    } else {
        row.style.borderColor = 'rgba(255,255,255,0.06)';
        row.style.background  = 'rgba(255,255,255,0.02)';
        badge.style.display   = 'none';
    }
}
</script>