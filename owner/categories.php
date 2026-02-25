<?php
session_start();
require '../config/db.php';

$page_title    = 'Categories';
$page_subtitle = 'Organise your products into categories';
$topbar_action_label   = 'Add Category';
$topbar_action_icon    = 'plus-lg';
$topbar_action_onclick = "openModal('addCatModal')";

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    function uploadCatImage($file_key) {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) return null;
        $f   = $_FILES[$file_key];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return null;
        $name = uniqid('cat_') . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], "../assets/uploads/products/$name")) return $name;
        return null;
    }

    if ($action === 'add') {
        $name      = trim($_POST['name']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image     = uploadCatImage('image');
        $stmt = $conn->prepare("INSERT INTO categories (shop_id, name, image, is_active) VALUES (?,?,?,?)");
        $stmt->bind_param("issi", $shop_id, $name, $image, $is_active);
        if ($stmt->execute()) $success = "Category \"$name\" added.";
        else $error = "Failed to add category.";

    } elseif ($action === 'edit') {
        $cid       = (int)$_POST['cat_id'];
        $name      = trim($_POST['name']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $old_img   = $_POST['old_image'];
        $image     = uploadCatImage('image') ?? $old_img;
        $stmt = $conn->prepare("UPDATE categories SET name=?, image=?, is_active=? WHERE id=? AND shop_id=?");
        $stmt->bind_param("ssiii", $name, $image, $is_active, $cid, $shop_id);
        if ($stmt->execute()) $success = "Category updated.";
        else $error = "Failed to update.";

    } elseif ($action === 'delete') {
        $cid = (int)$_POST['cat_id'];
        $check = $conn->query("SELECT COUNT(*) as c FROM products WHERE category_id=$cid")->fetch_assoc()['c'];
        if ($check > 0) {
            $error = "Cannot delete — $check product(s) are in this category. Move them first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id=? AND shop_id=?");
            $stmt->bind_param("ii", $cid, $shop_id);
            $stmt->execute();
            $success = "Category deleted.";
        }

    } elseif ($action === 'toggle') {
        $cid = (int)$_POST['cat_id'];
        $conn->query("UPDATE categories SET is_active = NOT is_active WHERE id=$cid AND shop_id=$shop_id");
        $success = "Category visibility updated.";
    }
}

$categories = $conn->query("
    SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count
    FROM categories c WHERE c.shop_id=$shop_id ORDER BY c.created_at DESC
");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php elseif ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($categories->num_rows === 0): ?>
<div class="card-glass animate-in">
    <div class="empty-state">
        <i class="bi bi-tags"></i>
        <h4>No Categories Yet</h4>
        <p>Create your first category to start organising your products.</p>
        <button class="btn-primary-custom" style="margin-top:16px;" onclick="openModal('addCatModal')">
            <i class="bi bi-plus-lg"></i> Add First Category
        </button>
    </div>
</div>
<?php else: ?>

<!-- Category Cards Grid -->
<div class="row g-3 animate-in d1">
    <?php while ($cat = $categories->fetch_assoc()): ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card-glass" style="position:relative;overflow:hidden;">
            <!-- Banner image -->
            <div style="height:90px;margin:-24px -24px 16px;background:var(--card-bg);overflow:hidden;display:flex;align-items:center;justify-content:center;position:relative;">
                <?php if ($cat['image']): ?>
                <img src="../assets/uploads/products/<?= htmlspecialchars($cat['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent,rgba(14,12,9,0.7));"></div>
                <?php else: ?>
                <i class="bi bi-image" style="font-size:32px;color:rgba(200,169,126,0.25);"></i>
                <?php endif; ?>
                <span class="status-pill <?= $cat['is_active'] ? 'pill-active' : 'pill-inactive' ?>" style="position:absolute;bottom:8px;right:10px;">
                    <?= $cat['is_active'] ? 'Active' : 'Hidden' ?>
                </span>
            </div>

            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;margin-bottom:4px;"><?= htmlspecialchars($cat['name']) ?></div>
            <div style="font-size:13px;color:var(--muted);">
                <i class="bi bi-box-seam" style="margin-right:4px;"></i><?= $cat['product_count'] ?> product<?= $cat['product_count'] != 1 ? 's' : '' ?>
            </div>

            <div style="display:flex;gap:8px;margin-top:16px;">
                <button class="btn-ghost-custom" style="flex:1;justify-content:center;font-size:12.5px;"
                    onclick="openEditCat(<?= htmlspecialchars(json_encode($cat)) ?>)">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                    <button type="submit" class="btn-ghost-custom" style="padding:8px 12px;" title="Toggle">
                        <i class="bi bi-<?= $cat['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                    </button>
                </form>
                <button class="btn-danger-custom" style="padding:8px 12px;"
                    onclick="confirmDeleteCat(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', <?= $cat['product_count'] ?>)">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php endif; ?>

<!-- ── Add Category Modal ── -->
<div class="modal-backdrop-custom" id="addCatModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-tags" style="color:var(--accent);margin-right:8px;"></i>Add Category</div>
            <button class="modal-close" onclick="closeModal('addCatModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div style="display:grid;gap:14px;">
                <div>
                    <div class="form-label-custom">Category Name *</div>
                    <input type="text" name="name" class="input-custom" placeholder="e.g. Cakes, Breads, Pastries" required>
                </div>
                <div>
                    <div class="form-label-custom">Banner Image (optional)</div>
                    <input type="file" name="image" class="input-custom" accept="image/*" onchange="previewCatImg(this,'addCatPreview')">
                    <img id="addCatPreview" style="margin-top:10px;max-height:80px;border-radius:8px;display:none;">
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--accent);width:16px;height:16px;">
                    <span style="font-size:13.5px;">Show this category in shop</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-plus-lg"></i> Add Category
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('addCatModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Category Modal ── -->
<div class="modal-backdrop-custom" id="editCatModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:8px;"></i>Edit Category</div>
            <button class="modal-close" onclick="closeModal('editCatModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            <input type="hidden" name="old_image" id="edit_cat_old_img">
            <div style="display:grid;gap:14px;">
                <div>
                    <div class="form-label-custom">Category Name *</div>
                    <input type="text" name="name" id="edit_cat_name" class="input-custom" required>
                </div>
                <div>
                    <div class="form-label-custom">Replace Banner Image</div>
                    <input type="file" name="image" class="input-custom" accept="image/*" onchange="previewCatImg(this,'editCatPreview')">
                    <img id="editCatPreview" style="margin-top:10px;max-height:80px;border-radius:8px;display:none;">
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="is_active" value="1" id="edit_cat_active" style="accent-color:var(--accent);width:16px;height:16px;">
                    <span style="font-size:13.5px;">Show in shop</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;"><i class="bi bi-check-lg"></i> Save</button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('editCatModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Modal ── -->
<div class="modal-backdrop-custom" id="deleteCatModal">
    <div class="modal-box" style="max-width:380px;">
        <div style="text-align:center;padding:8px 0 20px;">
            <div style="width:54px;height:54px;background:var(--danger-dim);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--danger);margin:0 auto 14px;">
                <i class="bi bi-trash3"></i>
            </div>
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:17px;margin-bottom:8px;">Delete Category?</div>
            <div id="deleteCatName" style="font-size:13.5px;color:var(--muted);"></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="cat_id" id="deleteCatId">
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-danger-custom" style="flex:1;justify-content:center;padding:12px;">Delete</button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('deleteCatModal')" style="flex:1;justify-content:center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function openEditCat(c) {
    document.getElementById("edit_cat_id").value     = c.id;
    document.getElementById("edit_cat_name").value   = c.name;
    document.getElementById("edit_cat_active").checked = c.is_active == 1;
    document.getElementById("edit_cat_old_img").value = c.image || "";
    const prev = document.getElementById("editCatPreview");
    if (c.image) { prev.src="../assets/uploads/products/"+c.image; prev.style.display="block"; }
    else prev.style.display="none";
    openModal("editCatModal");
}
function confirmDeleteCat(id, name, count) {
    document.getElementById("deleteCatId").value = id;
    document.getElementById("deleteCatName").textContent = count > 0
        ? `"${name}" has ${count} product(s). Please move them first.`
        : `"${name}" will be permanently deleted.`;
    openModal("deleteCatModal");
}
function previewCatImg(input, id) {
    const p = document.getElementById(id);
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => { p.src=e.target.result; p.style.display="block"; };
        r.readAsDataURL(input.files[0]);
    }
}
</script>';

require 'includes/footer.php';
?>
