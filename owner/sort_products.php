<?php
session_start();
require '../config/db.php';

$page_title    = 'Sort Products';
$page_subtitle = 'Drag to reorder how products appear in your shop';

$shop_id = $_SESSION['shop_id'] ?? 0;

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
    $ids = array_map('intval', explode(',', $_POST['order']));
    foreach ($ids as $pos => $pid) {
        $conn->query("UPDATE products SET sort_order=" . ($pos + 1) . " WHERE id=$pid AND shop_id=$shop_id");
    }
    echo json_encode(['success' => true]);
    exit;
}

require 'includes/sidebar.php';

$products = $conn->query("
    SELECT p.*, c.name as cat_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.shop_id = $shop_id
    ORDER BY p.sort_order ASC, p.created_at DESC
");
?>

<div class="card-glass animate-in" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <div style="font-size:13.5px;color:var(--muted);">Drag the <i class="bi bi-grip-vertical" style="color:var(--accent);"></i> handle to reorder products. Changes save automatically.</div>
    </div>
    <div id="saveStatus" style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;">
        <i class="bi bi-check-circle" style="color:var(--success);"></i> All changes saved
    </div>
</div>

<div class="card-glass animate-in d1" style="padding:0;overflow:hidden;">
    <div id="sortList">
        <?php while ($p = $products->fetch_assoc()): ?>
        <div class="sort-row" data-id="<?= $p['id'] ?>"
            style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.04);cursor:default;transition:background .15s;user-select:none;">

            <!-- Drag handle -->
            <div class="drag-handle" style="color:var(--muted);font-size:18px;cursor:grab;flex-shrink:0;padding:4px;">
                <i class="bi bi-grip-vertical"></i>
            </div>

            <!-- Image -->
            <div style="width:48px;height:48px;border-radius:10px;overflow:hidden;background:var(--primary-light);flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                <?php if ($p['image']): ?>
                <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : '../assets/uploads/products/'.htmlspecialchars($p['image']) ?>"
                     style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <i class="bi bi-image" style="color:var(--muted);font-size:18px;"></i>
                <?php endif; ?>
            </div>

            <!-- Name & Category -->
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                <?php if ($p['cat_name']): ?>
                <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($p['cat_name']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Price -->
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:14px;color:var(--accent);">
                    ₹<?= number_format($p['discount_price'] ?: $p['price'], 0) ?>
                </div>
                <div style="font-size:12px;color:var(--muted);margin-top:2px;">Stock: <?= $p['stock'] ?></div>
            </div>

            <!-- Active badge -->
            <div style="flex-shrink:0;">
                <?php if ($p['is_active']): ?>
                <span style="background:var(--success-dim);color:var(--success);font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;">Active</span>
                <?php else: ?>
                <span style="background:var(--danger-dim);color:var(--danger);font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;">Hidden</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const list      = document.getElementById('sortList');
const saveStatus = document.getElementById('saveStatus');
let saveTimer;

const sortable = Sortable.create(list, {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'sort-ghost',
    onEnd: function() {
        clearTimeout(saveTimer);
        saveStatus.innerHTML = '<i class="bi bi-hourglass-split" style="color:var(--warning);"></i> Saving...';
        saveTimer = setTimeout(saveOrder, 600);
    }
});

function saveOrder() {
    const ids = [...list.querySelectorAll('.sort-row')].map(r => r.dataset.id).join(',');
    fetch('sort_products.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'order=' + ids
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            saveStatus.innerHTML = '<i class="bi bi-check-circle" style="color:var(--success);"></i> All changes saved';
        }
    })
    .catch(() => {
        saveStatus.innerHTML = '<i class="bi bi-exclamation-circle" style="color:var(--danger);"></i> Save failed';
    });
}
</script>

<style>
.sort-ghost { opacity: 0.4; background: var(--accent-dim) !important; border-radius: 8px; }
.sort-row:hover { background: rgba(255,255,255,0.02); }
.drag-handle:hover { color: var(--accent) !important; }
</style>