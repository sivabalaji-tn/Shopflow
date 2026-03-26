<?php
session_start();
require '../config/db.php';

$page_title    = 'Bulk Upload Products';
$page_subtitle = 'Import multiple products at once via CSV';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$success = $error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== 0 || $file['size'] === 0) {
        $error = 'Please upload a valid CSV file.';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Only CSV files are allowed.';
    } else {
        $handle  = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle); // skip header row
        $added = $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) { $skipped++; continue; }

            $name        = trim($row[0] ?? '');
            $price       = (float)($row[1] ?? 0);
            $stock       = (int)($row[2] ?? 0);
            $description = trim($row[3] ?? '');
            $category    = trim($row[4] ?? '');
            $disc_price  = isset($row[5]) && $row[5] !== '' ? (float)$row[5] : null;
            $image_url   = trim($row[6] ?? '');

            if (!$name || $price <= 0) { $skipped++; continue; }

            // Find or skip category
            $cat_id = null;
            if ($category) {
                $cr = $conn->query("SELECT id FROM categories WHERE shop_id=$shop_id AND name='" . $conn->real_escape_string($category) . "' LIMIT 1");
                if ($cr->num_rows > 0) $cat_id = $cr->fetch_assoc()['id'];
            }

            $img = !empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL) ? $image_url : null;

            $stmt = $conn->prepare("INSERT INTO products (shop_id, category_id, name, description, price, discount_price, image, stock, is_active) VALUES (?,?,?,?,?,?,?,?,1)");
            $stmt->bind_param("iissddsi", $shop_id, $cat_id, $name, $description, $price, $disc_price, $img, $stock);
            if ($stmt->execute()) {
                $added++;
                $results[] = ['status'=>'ok','name'=>$name,'msg'=>'Added successfully'];
            } else {
                $skipped++;
                $results[] = ['status'=>'err','name'=>$name,'msg'=>'Failed to insert'];
            }
        }
        fclose($handle);
        $success = "$added product(s) imported successfully." . ($skipped ? " $skipped row(s) skipped." : '');
    }
}

// Get categories for reference
$cats = $conn->query("SELECT name FROM categories WHERE shop_id=$shop_id AND is_active=1 ORDER BY name");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-flash alert-flash-danger animate-in"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Upload Form -->
    <div class="col-lg-6">
        <div class="card-glass animate-in">
            <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:16px;margin-bottom:4px;">
                <i class="bi bi-cloud-upload" style="color:var(--accent);margin-right:8px;"></i>Upload CSV File
            </div>
            <div style="font-size:13px;color:var(--muted);margin-bottom:22px;">Upload a CSV file with your product data</div>

            <form method="POST" enctype="multipart/form-data">
                <div style="border:2px dashed var(--card-border);border-radius:var(--radius);padding:32px;text-align:center;margin-bottom:20px;transition:all .2s;"
                     id="dropZone"
                     ondragover="event.preventDefault();this.style.borderColor='var(--accent)'"
                     ondragleave="this.style.borderColor='var(--card-border)'"
                     ondrop="event.preventDefault();this.style.borderColor='var(--card-border)';document.getElementById('csvInput').files=event.dataTransfer.files;updateFileName(event.dataTransfer.files[0].name)">
                    <i class="bi bi-file-earmark-spreadsheet" style="font-size:40px;color:var(--accent);display:block;margin-bottom:12px;"></i>
                    <div style="font-weight:600;font-size:14px;margin-bottom:6px;">Drag & drop your CSV here</div>
                    <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">or click to browse</div>
                    <input type="file" name="csv_file" id="csvInput" accept=".csv" style="display:none;"
                        onchange="updateFileName(this.files[0]?.name)">
                    <button type="button" onclick="document.getElementById('csvInput').click()" class="btn-ghost-custom" style="font-size:13px;">
                        <i class="bi bi-folder2-open"></i> Choose File
                    </button>
                    <div id="fileName" style="margin-top:12px;font-size:13px;color:var(--accent);font-weight:600;"></div>
                </div>
                <button type="submit" class="btn-primary-custom" style="width:100%;justify-content:center;padding:13px;">
                    <i class="bi bi-cloud-upload"></i> Import Products
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if (!empty($results)): ?>
        <div class="card-glass animate-in d1" style="margin-top:16px;max-height:300px;overflow-y:auto;">
            <div style="font-weight:700;font-size:14px;margin-bottom:14px;">Import Results</div>
            <?php foreach ($results as $r): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px;">
                <i class="bi bi-<?= $r['status']==='ok' ? 'check-circle-fill' : 'x-circle-fill' ?>"
                   style="color:var(--<?= $r['status']==='ok' ? 'success' : 'danger' ?>);flex-shrink:0;"></i>
                <span style="flex:1;"><?= htmlspecialchars($r['name']) ?></span>
                <span style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($r['msg']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Instructions & Template -->
    <div class="col-lg-6">
        <div class="card-glass animate-in d1" style="margin-bottom:16px;">
            <div style="font-weight:700;font-size:15px;margin-bottom:16px;"><i class="bi bi-info-circle" style="color:var(--accent);margin-right:8px;"></i>CSV Format</div>
            <div style="font-size:13px;color:var(--muted);margin-bottom:14px;">Your CSV must have these columns in this exact order:</div>
            <div style="background:rgba(255,255,255,0.03);border-radius:10px;overflow:hidden;border:1px solid var(--card-border);">
                <?php
                $cols = [
                    ['name','Product name','Required'],
                    ['price','Selling price in ₹','Required'],
                    ['stock','Stock quantity','Required'],
                    ['description','Product description','Optional'],
                    ['category','Must match existing category name','Optional'],
                    ['discount_price','Discounted price in ₹','Optional'],
                    ['image_url','Full URL of product image','Optional'],
                ];
                foreach ($cols as $i => $col):
                ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;<?= $i > 0 ? 'border-top:1px solid rgba(255,255,255,0.04)' : '' ?>">
                    <div style="width:24px;height:24px;border-radius:6px;background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $i+1 ?></div>
                    <div style="flex:1;">
                        <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;"><?= $col[0] ?></span>
                        <span style="font-size:12px;color:var(--muted);margin-left:6px;">— <?= $col[1] ?></span>
                    </div>
                    <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;<?= $col[2]==='Required' ? 'background:var(--danger-dim);color:var(--danger)' : 'background:rgba(255,255,255,0.05);color:var(--muted)' ?>"><?= $col[2] ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Download template -->
            <a href="data:text/csv;charset=utf-8,name,price,stock,description,category,discount_price,image_url%0AExample Product,299,50,This is a sample product description,<?= urlencode($cats->num_rows > 0 ? $cats->fetch_assoc()['name'] : 'CategoryName') ?>,,https://example.com/image.jpg"
               download="tamizhmart_product_template.csv"
               class="btn-ghost-custom" style="margin-top:16px;width:100%;justify-content:center;font-size:13px;">
                <i class="bi bi-download"></i> Download Sample Template
            </a>
        </div>

        <!-- Your categories -->
        <div class="card-glass animate-in d2">
            <div style="font-weight:700;font-size:14px;margin-bottom:12px;"><i class="bi bi-tags" style="color:var(--accent);margin-right:8px;"></i>Your Categories</div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:12px;">Use these exact names in the category column:</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php
                $cats->data_seek(0);
                while ($cat = $cats->fetch_assoc()):
                ?>
                <span style="background:var(--card-bg);border:1px solid var(--card-border);padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:500;">
                    <?= htmlspecialchars($cat['name']) ?>
                </span>
                <?php endwhile; ?>
                <?php if ($cats->num_rows === 0): ?>
                <span style="color:var(--muted);font-size:13px;">No categories yet. <a href="categories.php" style="color:var(--accent);">Add one first</a></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

<script>
function updateFileName(name) {
    if (name) document.getElementById('fileName').textContent = '📄 ' + name;
}
</script>