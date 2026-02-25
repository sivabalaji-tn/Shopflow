<?php
// includes/product_card.php
// Requires: $p (product row with cat_name), $slug, $i (index for animation delay)
$disc = $p['discount_price'];
$orig = $p['price'];
$save_pct = $disc ? round((($orig - $disc) / $orig) * 100) : 0;
$delay = min($i * 0.05, 0.4);
?>
<div class="product-card fade-up" style="animation-delay:<?= $delay ?>s;">
    <!-- Image -->
    <a href="product.php?shop=<?= $slug ?>&id=<?= $p['id'] ?>" style="display:block;text-decoration:none;">
        <div class="product-card-img" style="height:200px;">
            <?php if ($p['image']): ?>
            <img src="<?= strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : '../assets/uploads/products/'.htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
            <div style="width:100%;height:100%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-image" style="font-size:40px;color:var(--primary-glow);"></i>
            </div>
            <?php endif; ?>
        </div>
    </a>

    <!-- Out of stock overlay -->
    <?php if ($p['stock'] <= 0): ?>
    <div class="out-of-stock-overlay">Out of Stock</div>
    <?php endif; ?>

    <!-- Discount badge -->
    <?php if ($disc && $save_pct > 0): ?>
    <div style="position:absolute;top:12px;left:12px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;padding:3px 9px;border-radius:99px;">
        -<?= $save_pct ?>%
    </div>
    <?php endif; ?>

    <!-- Body -->
    <div class="product-card-body">
        <div class="product-card-cat"><?= htmlspecialchars($p['cat_name'] ?? '') ?></div>
        <a href="product.php?shop=<?= $slug ?>&id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit;">
            <div class="product-card-name"><?= htmlspecialchars($p['name']) ?></div>
        </a>
        <div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;">
            <span class="product-card-price">&#8377;<?= number_format($disc ?: $orig, 2) ?></span>
            <?php if ($disc): ?>
            <span class="product-card-price-orig">&#8377;<?= number_format($orig, 2) ?></span>
            <span class="product-card-discount">Save <?= $save_pct ?>%</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer CTA -->
    <?php if ($p['stock'] > 0): ?>
    <div class="product-card-footer">
        <button onclick="addToCart(<?= $p['id'] ?>)" class="btn-shop-primary" style="flex:1;justify-content:center;border-radius:var(--radius-sm);padding:9px 14px;font-size:13px;">
            <i class="bi bi-bag-plus"></i> Add to Cart
        </button>
        <a href="product.php?shop=<?= $slug ?>&id=<?= $p['id'] ?>" class="btn-shop-outline" style="padding:8px 12px;border-radius:var(--radius-sm);" title="View Details">
            <i class="bi bi-eye"></i>
        </a>
    </div>
    <?php endif; ?>
</div>