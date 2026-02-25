<?php // shop/includes/shop_foot.php ?>
</main>

<!-- Footer -->
<footer class="shop-footer">
    <div class="footer-inner">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="footer-brand-name"><?= htmlspecialchars($shop['name']) ?></div>
                <?php if ($shop['description']): ?>
                <p class="footer-desc"><?= htmlspecialchars($shop['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-2 col-6">
                <div style="font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;color:var(--text-muted);">Shop</div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="index.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Home</a>
                    <a href="products.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">All Products</a>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div style="font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;color:var(--text-muted);">Account</div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="orders.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">My Orders</a>
                    <a href="profile.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Profile</a>
                    <?php else: ?>
                    <a href="../auth/login.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Sign In</a>
                    <a href="../auth/register.php?shop=<?= $slug ?>" style="font-size:14px;color:var(--text-muted);text-decoration:none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Register</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $phone   = $settings_map['phone']   ?? null;
            $address = $settings_map['address'] ?? null;
            if ($phone || $address):
            ?>
            <div class="col-md-4">
                <div style="font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;color:var(--text-muted);">Contact</div>
                <?php if ($phone): ?>
                <div style="font-size:14px;color:var(--text-muted);display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <i class="bi bi-telephone" style="color:var(--primary);font-size:14px;"></i>
                    <?= htmlspecialchars($phone) ?>
                </div>
                <?php endif; ?>
                <?php if ($address): ?>
                <div style="font-size:14px;color:var(--text-muted);display:flex;align-items:flex-start;gap:8px;line-height:1.5;">
                    <i class="bi bi-geo-alt" style="color:var(--primary);font-size:14px;margin-top:1px;flex-shrink:0;"></i>
                    <?= htmlspecialchars($address) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <hr class="footer-divider">
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($shop['name']) ?>. All rights reserved.</span>
            <span class="footer-powered">Powered by <strong>SM Tech</strong></span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Navbar scroll effect
window.addEventListener('scroll', () => {
    document.getElementById('shopNavbar').classList.toggle('scrolled', window.scrollY > 10);
});

// Mobile menu
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
    document.body.style.overflow = document.getElementById('mobileMenu').classList.contains('open') ? 'hidden' : '';
}

// Popup
function closePopup() {
    const p = document.getElementById('shopPopup');
    if (p) { p.style.opacity='0'; p.style.transition='opacity 0.3s'; setTimeout(()=>p.remove(),300); }
    sessionStorage.setItem('popup_dismissed_<?= $shop['id'] ?>', '1');
}
// Don't show popup twice in same session
if (sessionStorage.getItem('popup_dismissed_<?= $shop['id'] ?>')) {
    const p = document.getElementById('shopPopup');
    if (p) p.remove();
}

// Toast helper
function showToast(msg, icon='check-circle-fill') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast-item';
    t.innerHTML = `<i class="bi bi-${icon}"></i>${msg}`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3100);
}

// Add to cart via AJAX
function addToCart(productId, qty=1) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=add&product_id=${productId}&quantity=${qty}&shop=<?= $slug ?>`
    })
    .then(r=>r.json())
    .then(d=>{
        if(d.success) {
            showToast(d.message || 'Added to cart!');
            // Update cart count
            document.querySelectorAll('.cart-badge,.mobile-cart-badge').forEach(el => {
                el.textContent = d.cart_count;
                el.style.display = d.cart_count > 0 ? 'flex' : 'none';
            });
        } else {
            showToast(d.message || 'Failed to add', 'exclamation-circle-fill');
        }
    });
}

// Popup close on overlay click
document.getElementById('shopPopup')?.addEventListener('click', function(e) {
    if (e.target === this) closePopup();
});
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
