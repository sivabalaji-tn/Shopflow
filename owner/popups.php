<?php
session_start();
require '../config/db.php';

$page_title    = 'Offers & Popups';
$page_subtitle = 'Create promotional popups shown to customers';
$topbar_action_label   = 'New Popup';
$topbar_action_icon    = 'plus-lg';
$topbar_action_onclick = "openModal('addPopupModal')";

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    function uploadPopupImg($key) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== 0) return null;
        $f   = $_FILES[$key];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp','gif'])) return null;
        $name = uniqid('popup_').'.'.$ext;
        if (move_uploaded_file($f['tmp_name'], "../assets/uploads/popups/$name")) return $name;
        return null;
    }

    if ($action === 'add') {
        $title       = trim($_POST['title']);
        $message     = trim($_POST['message']);
        $btn_text    = trim($_POST['button_text']);
        $btn_link    = trim($_POST['button_link']);
        $start_date  = $_POST['start_date'] ?: null;
        $end_date    = $_POST['end_date']   ?: null;
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $image       = uploadPopupImg('image');
        $stmt = $conn->prepare("INSERT INTO popups (shop_id,title,message,image,button_text,button_link,is_active,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssss", $shop_id,$title,$message,$image,$btn_text,$btn_link,$is_active,$start_date,$end_date);
        if ($stmt->execute()) $success = "Popup created successfully.";
        else $error = "Failed to create popup.";

    } elseif ($action === 'edit') {
        $pid       = (int)$_POST['popup_id'];
        $title     = trim($_POST['title']);
        $message   = trim($_POST['message']);
        $btn_text  = trim($_POST['button_text']);
        $btn_link  = trim($_POST['button_link']);
        $start     = $_POST['start_date'] ?: null;
        $end       = $_POST['end_date']   ?: null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $old_img   = $_POST['old_image'];
        $image     = uploadPopupImg('image') ?? $old_img;
        $stmt = $conn->prepare("UPDATE popups SET title=?,message=?,image=?,button_text=?,button_link=?,is_active=?,start_date=?,end_date=? WHERE id=? AND shop_id=?");
        $stmt->bind_param("sssssssiii", $title,$message,$image,$btn_text,$btn_link,$is_active,$start,$end,$pid,$shop_id);
        if ($stmt->execute()) $success = "Popup updated.";
        else $error = "Failed to update.";

    } elseif ($action === 'delete') {
        $pid = (int)$_POST['popup_id'];
        $stmt = $conn->prepare("DELETE FROM popups WHERE id=? AND shop_id=?");
        $stmt->bind_param("ii", $pid, $shop_id);
        $stmt->execute();
        $success = "Popup deleted.";

    } elseif ($action === 'toggle') {
        $pid = (int)$_POST['popup_id'];
        $conn->query("UPDATE popups SET is_active = NOT is_active WHERE id=$pid AND shop_id=$shop_id");
        $success = "Popup status updated.";
    }
}

$popups = $conn->query("SELECT * FROM popups WHERE shop_id=$shop_id ORDER BY created_at DESC");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php elseif ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Info Banner -->
<div class="animate-in" style="background:rgba(96,165,250,0.07);border:1px solid rgba(96,165,250,0.18);border-radius:var(--radius);padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <i class="bi bi-info-circle-fill" style="color:var(--info);font-size:18px;flex-shrink:0;"></i>
    <div style="font-size:13.5px;color:rgba(240,236,228,0.7);">
        Only <strong style="color:var(--text);">one active popup</strong> is shown per customer visit. The most recently activated popup takes priority. Schedule popups using start/end dates for timed campaigns.
    </div>
</div>

<?php if ($popups->num_rows === 0): ?>
<div class="card-glass animate-in d1">
    <div class="empty-state">
        <i class="bi bi-megaphone"></i>
        <h4>No Popups Yet</h4>
        <p>Create your first promotional popup to engage customers.</p>
        <button class="btn-primary-custom" style="margin-top:16px;" onclick="openModal('addPopupModal')">
            <i class="bi bi-plus-lg"></i> Create First Popup
        </button>
    </div>
</div>
<?php else: ?>

<div class="row g-3 animate-in d1">
    <?php while ($popup = $popups->fetch_assoc()):
        $now = date('Y-m-d');
        $is_scheduled = $popup['start_date'] && $popup['end_date'];
        $is_live = $popup['is_active'] && (!$is_scheduled || ($now >= $popup['start_date'] && $now <= $popup['end_date']));
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card-glass" style="position:relative;overflow:hidden;">
            <!-- Status glow bar -->
            <div style="position:absolute;top:0;left:0;right:0;height:2px;background:<?= $is_live ? 'var(--success)' : ($popup['is_active'] ? 'var(--warning)' : 'rgba(255,255,255,0.08)') ?>;"></div>

            <!-- Preview image -->
            <?php if ($popup['image']): ?>
            <div style="height:110px;margin:-24px -24px 16px;overflow:hidden;position:relative;">
                <img src="../assets/uploads/popups/<?= htmlspecialchars($popup['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,rgba(14,12,9,0.85));"></div>
            </div>
            <?php endif; ?>

            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;">
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;"><?= htmlspecialchars($popup['title']) ?></div>
                <span class="status-pill <?= $is_live ? 'pill-active' : ($popup['is_active'] ? 'pill-pending' : 'pill-inactive') ?>" style="flex-shrink:0;">
                    <?= $is_live ? 'Live' : ($popup['is_active'] ? 'Scheduled' : 'Inactive') ?>
                </span>
            </div>

            <?php if ($popup['message']): ?>
            <p style="font-size:13px;color:var(--muted);margin-bottom:12px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                <?= htmlspecialchars($popup['message']) ?>
            </p>
            <?php endif; ?>

            <?php if ($popup['button_text']): ?>
            <div style="display:inline-flex;align-items:center;gap:6px;background:var(--accent-dim);color:var(--accent);padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600;margin-bottom:12px;">
                <i class="bi bi-cursor"></i> <?= htmlspecialchars($popup['button_text']) ?>
            </div>
            <?php endif; ?>

            <?php if ($is_scheduled): ?>
            <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
                <i class="bi bi-calendar3 me-1"></i>
                <?= date('M j', strtotime($popup['start_date'])) ?> &ndash; <?= date('M j, Y', strtotime($popup['end_date'])) ?>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:8px;margin-top:auto;">
                <button class="btn-ghost-custom" style="flex:1;justify-content:center;font-size:12.5px;"
                    onclick="openEditPopup(<?= htmlspecialchars(json_encode($popup)) ?>)">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="popup_id" value="<?= $popup['id'] ?>">
                    <button type="submit" class="btn-ghost-custom" style="padding:8px 12px;" title="Toggle">
                        <i class="bi bi-<?= $popup['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                    </button>
                </form>
                <button class="btn-danger-custom" style="padding:8px 12px;"
                    onclick="confirmDeletePopup(<?= $popup['id'] ?>,'<?= htmlspecialchars(addslashes($popup['title'])) ?>')">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php endif; ?>

<!-- ── Add Popup Modal ── -->
<div class="modal-backdrop-custom" id="addPopupModal">
    <div class="modal-box" style="max-width:540px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-megaphone" style="color:var(--accent);margin-right:8px;"></i>Create Popup</div>
            <button class="modal-close" onclick="closeModal('addPopupModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div style="display:grid;gap:14px;">
                <div>
                    <div class="form-label-custom">Popup Title *</div>
                    <input type="text" name="title" class="input-custom" placeholder="e.g. Weekend Special Offer!" required>
                </div>
                <div>
                    <div class="form-label-custom">Message</div>
                    <textarea name="message" class="input-custom" placeholder="Tell customers about your offer..." style="min-height:80px;"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Button Text</div>
                        <input type="text" name="button_text" class="input-custom" placeholder="e.g. Shop Now">
                    </div>
                    <div>
                        <div class="form-label-custom">Button Link</div>
                        <input type="text" name="button_link" class="input-custom" placeholder="/shop/products">
                    </div>
                </div>
                <div>
                    <div class="form-label-custom">Popup Image (optional)</div>
                    <input type="file" name="image" class="input-custom" accept="image/*" onchange="previewPopupImg(this,'addPopPreview')">
                    <img id="addPopPreview" style="margin-top:10px;max-height:80px;border-radius:8px;display:none;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Start Date</div>
                        <input type="date" name="start_date" class="input-custom">
                    </div>
                    <div>
                        <div class="form-label-custom">End Date</div>
                        <input type="date" name="end_date" class="input-custom">
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--accent);width:16px;height:16px;">
                    <span style="font-size:13.5px;">Activate this popup now</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;">
                    <i class="bi bi-megaphone"></i> Create Popup
                </button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('addPopupModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Popup Modal ── -->
<div class="modal-backdrop-custom" id="editPopupModal">
    <div class="modal-box" style="max-width:540px;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:8px;"></i>Edit Popup</div>
            <button class="modal-close" onclick="closeModal('editPopupModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="popup_id" id="ep_id">
            <input type="hidden" name="old_image" id="ep_old_img">
            <div style="display:grid;gap:14px;">
                <div>
                    <div class="form-label-custom">Popup Title *</div>
                    <input type="text" name="title" id="ep_title" class="input-custom" required>
                </div>
                <div>
                    <div class="form-label-custom">Message</div>
                    <textarea name="message" id="ep_msg" class="input-custom" style="min-height:80px;"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Button Text</div>
                        <input type="text" name="button_text" id="ep_btxt" class="input-custom">
                    </div>
                    <div>
                        <div class="form-label-custom">Button Link</div>
                        <input type="text" name="button_link" id="ep_blink" class="input-custom">
                    </div>
                </div>
                <div>
                    <div class="form-label-custom">Replace Image</div>
                    <input type="file" name="image" class="input-custom" accept="image/*" onchange="previewPopupImg(this,'editPopPreview')">
                    <img id="editPopPreview" style="margin-top:10px;max-height:80px;border-radius:8px;display:none;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div class="form-label-custom">Start Date</div>
                        <input type="date" name="start_date" id="ep_start" class="input-custom">
                    </div>
                    <div>
                        <div class="form-label-custom">End Date</div>
                        <input type="date" name="end_date" id="ep_end" class="input-custom">
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="is_active" value="1" id="ep_active" style="accent-color:var(--accent);width:16px;height:16px;">
                    <span style="font-size:13.5px;">Active</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;"><i class="bi bi-check-lg"></i> Save</button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('editPopupModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Modal ── -->
<div class="modal-backdrop-custom" id="deletePopupModal">
    <div class="modal-box" style="max-width:380px;">
        <div style="text-align:center;padding:8px 0 20px;">
            <div style="width:54px;height:54px;background:var(--danger-dim);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--danger);margin:0 auto 14px;"><i class="bi bi-trash3"></i></div>
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:17px;margin-bottom:8px;">Delete Popup?</div>
            <div id="deletePopupName" style="font-size:13.5px;color:var(--muted);"></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="popup_id" id="deletePopupId">
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-danger-custom" style="flex:1;justify-content:center;padding:12px;">Delete</button>
                <button type="button" class="btn-ghost-custom" onclick="closeModal('deletePopupModal')" style="flex:1;justify-content:center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function openEditPopup(p) {
    document.getElementById("ep_id").value    = p.id;
    document.getElementById("ep_title").value = p.title;
    document.getElementById("ep_msg").value   = p.message || "";
    document.getElementById("ep_btxt").value  = p.button_text || "";
    document.getElementById("ep_blink").value = p.button_link || "";
    document.getElementById("ep_start").value = p.start_date || "";
    document.getElementById("ep_end").value   = p.end_date || "";
    document.getElementById("ep_active").checked = p.is_active == 1;
    document.getElementById("ep_old_img").value = p.image || "";
    const prev = document.getElementById("editPopPreview");
    if (p.image) { prev.src="../assets/uploads/popups/"+p.image; prev.style.display="block"; }
    else prev.style.display="none";
    openModal("editPopupModal");
}
function confirmDeletePopup(id, title) {
    document.getElementById("deletePopupId").value = id;
    document.getElementById("deletePopupName").textContent = `"${title}" will be permanently deleted.`;
    openModal("deletePopupModal");
}
function previewPopupImg(input, id) {
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
