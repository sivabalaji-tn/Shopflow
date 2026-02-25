<?php
session_start();
require '../config/db.php';

$page_title    = 'Products';
$page_subtitle = 'Add, edit and manage your product catalogue';
$topbar_action_label   = 'Add Product';
$topbar_action_icon    = 'plus-lg';
$topbar_action_onclick = "openModal('addProductModal')";

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle image upload helper
    function uploadImage($file_key, $folder) {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) return null;
        $f    = $_FILES[$file_key];
        $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) return null;
        if ($f['size'] > 5 * 1024 * 1024) return null;
        $name = uniqid('img_') . '.' . $ext;
        $dest = "../../assets/uploads/$folder/$name";
        // We'll store relative to assets folder
        $dest = "../assets/uploads/$folder/$name";
        if (move_uploaded_file($f['tmp_name'], $dest)) return $name;
        return null;
    }

    if ($action === 'add') {
        $name         = trim($_POST['name']);
        $desc         = trim($_POST['description']);
        $price        = (float)$_POST['price'];
        $disc_price   = $_POST['discount_price'] !== '' ? (float)$_POST['discount_price'] : null;
        $stock        = (int)$_POST['stock'];
        $cat_id       = (int)$_POST['category_id'];
        $is_active    = isset($_POST['is_active']) ? 1 : 0;
        $image        = uploadImage('image', 'products');

        $stmt = $conn->prepare("INSERT INTO products (shop_id, category_id, name, description, price, discount_price, image, stock, is_active) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissddsii", $shop_id, $cat_id, $name, $desc, $price, $disc_price, $image, $stock, $is_active);
        if ($stmt->execute()) $success = "Product \"$name\" added successfully.";
        else $error = "Failed to add product.";

    } elseif ($action === 'edit') {
        $pid          = (int)$_POST['product_id'];
        $name         = trim($_POST['name']);
        $desc         = trim($_POST['description']);
        $price        = (float)$_POST['price'];
        $disc_price   = $_POST['discount_price'] !== '' ? (float)$_POST['discount_price'] : null;
        $stock        = (int)$_POST['stock'];
        $cat_id       = (int)$_POST['category_id'];
        $is_active    = isset($_POST['is_active']) ? 1 : 0;
        $old_image    = $_POST['old_image'];
        $image        = uploadImage('image', 'products') ?? $old_image;

        $stmt = $conn->prepare("UPDATE products SET category_id=?, name=?, description=?, price=?, discount_price=?, image=?, stock=?, is_active=? WHERE id=? AND shop_id=?");
        $stmt->bind_param("issddsiiii", $cat_id, $name, $desc, $price, $disc_price, $image, $stock, $is_active, $pid, $shop_id);
        if ($stmt->execute()) $success = "Product updated.";
        else $error = "Failed to update product.";

    } elseif ($action === 'delete') {
        $pid = (int)$_POST['product_id'];
        $stmt = $conn->prepare("DELETE FROM products WHERE id=? AND shop_id=?");
        $stmt->bind_param("ii", $pid, $shop_id);
        $stmt->execute();
        $success = "Product deleted.";

    } elseif ($action === 'toggle') {
        $pid = (int)$_POST['product_id'];
        $conn->query("UPDATE products SET is_active = NOT is_active WHERE id=$pid AND shop_id=$shop_id");
        $success = "Product visibility updated.";
    }
}

// ── Fetch categories for dropdown ────────────────────────────
$cats = $conn->query("SELECT id, name FROM categories WHERE shop_id=$shop_id AND is_active=1 ORDER BY name");
$categories = [];
while ($c = $cats->fetch_assoc()) $categories[] = $c;

// ── Fetch products ────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter_cat = (int)($_GET['cat'] ?? 0);
$filter_stock = $_GET['stock'] ?? 'all';

$where = "p.shop_id = $shop_id";
if ($search) $where .= " AND p.name LIKE '%" . $conn->real_escape_string($search) . "%'";
if ($filter_cat) $where .= " AND p.category_id = $filter_cat";
if ($filter_stock === 'low') $where .= " AND p.stock > 0 AND p.stock <= 5";
if ($filter_stock === 'out') $where .= " AND p.stock = 0";

$products = $conn->query("
    SELECT p.*, c.name as cat_name
    FROM products p LEFT JOIN categories c ON p.category_id=c.id
    WHERE $where ORDER BY p.created_at DESC
");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php elseif ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Filters ── -->
<div class="card-glass animate-in" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <div style="position:relative;flex:1;min-width:180px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..."
                class="input-custom" style="padding-left:38px;">
        </div>
        <select name="cat" class="input-custom" style="width:auto;min-width:150px;">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filter_cat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="stock" class="input-custom" style="width:auto;">
            <option value="all" <?= $filter_stock === 'all' ? 'selected' : '' ?>>All Stock</option>
            <option value="low" <?= $filter_stock === 'low' ? 'selected' : '' ?>>Low Stock (&le;5)</option>
            <option value="out" <?= $filter_stock === 'out' ? 'selected' : '' ?>>Out of Stock</option>
        </select>
        <button type="submit" class="btn-primary-custom">Filter</button>
        <?php if ($search || $filter_cat || $filter_stock !== 'all'): ?>
        <a href="products.php" class="btn-ghost-custom">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- ── Products Grid/Table ── -->
<?php if ($products->num_rows === 0): ?>
<div class="card-glass animate-in d2">
    <div class="empty-state">
        <i class="bi bi-box-seam"></i>
        <h4>No Products Yet</h4>
        <p>Click "Add Product" to create your first product.</p>
    </div>
</div>
<?php else: ?>
<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="table-glass">
            <thead>
                <tr>
                    <th style="padding-left:20px;">Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th style="text-align:right;padding-right:20px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $products->fetch_assoc()): ?>
                <tr>
                    <td style="padding-left:20px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:44px;height:44px;border-radius:10px;overflow:hidden;background:var(--card-bg);flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                                <?php if ($p['image']): ?>
                                <img src="../assets/uploads/products/<?= htmlspecialchars($p['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                <i class="bi bi-image" style="color:var(--muted);font-size:18px;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight:500;font-size:14px;"><?= htmlspecialchars($p['name']) ?></div>
                                <div style="font-size:12px;color:var(--muted);margin-top:2px;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 50)) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="background:var(--accent-dim);color:var(--accent);padding:3px 10px;border-radius:99px;font-size:12px;font-weight:500;">
                            <?= htmlspecialchars($p['cat_name'] ?? 'Uncategorised') ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-family:'Syne',sans-serif;font-weight:700;">&#8377;<?= number_format($p['price'], 2) ?></div>
                        <?php if ($p['discount_price']): ?>
                        <div style="font-size:12px;color:var(--success);">&#8377;<?= number_format($p['discount_price'], 2) ?> offer</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['stock'] == 0): ?>
                        <span class="status-pill pill-cancelled">Out of Stock</span>
                        <?php elseif ($p['stock'] <= 5): ?>
                        <span class="status-pill pill-pending"><?= $p['stock'] ?> left</span>
                        <?php else: ?>
                        <span style="font-weight:600;color:var(--success);"><?= $p['stock'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-pill <?= $p['is_active'] ? 'pill-active' : 'pill-inactive' ?>">
                            <?= $p['is_active'] ? 'Active' : 'Hidden' ?>
                        </span>
                    </td>
                    <td style="text-align:right;padding-right:20px;">
                        <div style="display:flex;justify-content:flex-end;gap:6px;">
                            <!-- Toggle visibility -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-ghost-custom" style="padding:6px 10px;font-size:12px;" title="<?= $p['is_active'] ? 'Hide' : 'Show' ?>">
                                    <i class="bi bi-<?= $p['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                            </form>
                            <!-- Edit -->
                            <button class="btn-ghost-custom" style="padding:6px 10px;font-size:12px;"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- Delete -->
                            <button class="btn-danger-custom" style="padding:6px 10px;"
                                onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Add Product Modal ── -->
<div class="modal-backdrop-custom" id="addProductModal">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-plus-circle" style="color:var(--accent);margin-right:8px;"></i>Add New Product</div>
            <button class="modal-close" onclick="closeModal('addProductModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <?php if (empty($categories)): ?>
            <div class="alert-flash alert-flash-error" style="margin-bottom:20px;"><i class="bi bi-exclamation-circle"></i>Please <a href="categories.php" style="color:var(--danger);font-weight:600;">add a category</a> first.</div>
            <?php endif; ?>
            <div style="display:grid;gap:14px;">
                <div>
                    <div class="form-label-custom">Product Name *</div>
                    <input type="text" name="name" class="input-custom" placeholder="e.g. Chocolate Truffle Cake" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Category *</div>
                        <select name="category_id" class="input-custom" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div class="form-label-custom">Stock Quantity *</div>
                        <input type="number" name="stock" class="input-custom" placeholder="0" min="0" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Price (&#8377;) *</div>
                        <input type="number" name="price" class="input-custom" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                    <div>
                        <div class="form-label-custom">Discount Price (&#8377;)</div>
                        <input type="number" name="discount_price" class="input-custom" placeholder="Optional" step="0.01" min="0">
                    </div>
                </div>
                <div>
                    <div class="form-label-custom">Description</div>
                    <textarea name="description" class="input-custom" placeholder="Product description..."></textarea>
                </div>
                <div>
                    <div class="form-label-custom">Product Image</div>
                    <input type="file" name="image" class="input-custom" accept="image/*" onchange="previewImg(this,'addPreview')">
                    <img id="addPreview" src="" style="margin-top:10px;max-height:100px;border-radius:8px;display:none;">
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--accent);width:16px;height:16px;">
                    <span style="font-size:13.5px;">Make product visible in shop</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-plus-lg"></i> Add Product
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('addProductModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Product Modal ── -->
<div class="modal-backdrop-custom" id="editProductModal">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:8px;"></i>Edit Product</div>
            <button class="modal-close" onclick="closeModal('editProductModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="editProductForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="product_id" id="edit_pid">
            <input type="hidden" name="old_image" id="edit_old_image">
            <div style="display:grid;gap:14px;">
                <div>
                    <div class="form-label-custom">Product Name *</div>
                    <input type="text" name="name" id="edit_name" class="input-custom" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Category *</div>
                        <select name="category_id" id="edit_cat" class="input-custom" required>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div class="form-label-custom">Stock *</div>
                        <input type="number" name="stock" id="edit_stock" class="input-custom" min="0" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Price (&#8377;) *</div>
                        <input type="number" name="price" id="edit_price" class="input-custom" step="0.01" min="0" required>
                    </div>
                    <div>
                        <div class="form-label-custom">Discount Price</div>
                        <input type="number" name="discount_price" id="edit_disc" class="input-custom" step="0.01" min="0">
                    </div>
                </div>
                <div>
                    <div class="form-label-custom">Description</div>
                    <textarea name="description" id="edit_desc" class="input-custom"></textarea>
                </div>
                <div>
                    <div class="form-label-custom">Replace Image</div>
                    <input type="file" name="image" class="input-custom" accept="image/*" onchange="previewImg(this,'editPreview')">
                    <img id="editPreview" src="" style="margin-top:10px;max-height:100px;border-radius:8px;display:none;">
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="is_active" value="1" id="edit_active" style="accent-color:var(--accent);width:16px;height:16px;">
                    <span style="font-size:13.5px;">Product is visible in shop</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('editProductModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div class="modal-backdrop-custom" id="deleteModal">
    <div class="modal-box" style="max-width:400px;">
        <div style="text-align:center;padding:8px 0 20px;">
            <div style="width:56px;height:56px;background:var(--danger-dim);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--danger);margin:0 auto 16px;">
                <i class="bi bi-trash3"></i>
            </div>
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;margin-bottom:8px;">Delete Product?</div>
            <div style="font-size:13.5px;color:var(--muted);" id="deleteProductName"></div>
            <div style="font-size:12.5px;color:var(--muted);margin-top:6px;">This action cannot be undone.</div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="product_id" id="deleteProductId">
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-danger-custom" style="flex:1;justify-content:center;padding:12px;">
                    <i class="bi bi-trash3"></i> Yes, Delete
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('deleteModal')" style="flex:1;justify-content:center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function openEditModal(p) {
    document.getElementById("edit_pid").value   = p.id;
    document.getElementById("edit_name").value  = p.name;
    document.getElementById("edit_desc").value  = p.description || "";
    document.getElementById("edit_price").value = p.price;
    document.getElementById("edit_disc").value  = p.discount_price || "";
    document.getElementById("edit_stock").value = p.stock;
    document.getElementById("edit_cat").value   = p.category_id;
    document.getElementById("edit_active").checked = p.is_active == 1;
    document.getElementById("edit_old_image").value = p.image || "";
    const prev = document.getElementById("editPreview");
    if (p.image) {
        prev.src = "../assets/uploads/products/" + p.image;
        prev.style.display = "block";
    } else { prev.style.display = "none"; }
    openModal("editProductModal");
}

function confirmDelete(id, name) {
    document.getElementById("deleteProductId").value = id;
    document.getElementById("deleteProductName").textContent = "\"" + name + "\" will be permanently removed.";
    openModal("deleteModal");
}

function previewImg(input, previewId) {
    const prev = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { prev.src = e.target.result; prev.style.display = "block"; };
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-open add modal if ?action=add
if (new URLSearchParams(window.location.search).get("action") === "add") {
    openModal("addProductModal");
}
</script>';

require 'includes/footer.php';
?>
