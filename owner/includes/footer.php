    </div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /layout -->

<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay" onclick="toggleSidebar()"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:99;backdrop-filter:blur(3px);"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    sb.classList.toggle('open');
    ov.style.display = sb.classList.contains('open') ? 'block' : 'none';
}

// Modal helpers
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// Auto-dismiss flash alerts
setTimeout(() => {
    document.querySelectorAll('.alert-flash').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>
<?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
