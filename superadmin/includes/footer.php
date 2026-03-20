</div><!-- end page-body -->
</div><!-- end main-content -->
</div><!-- end layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(id)  { document.getElementById(id).style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).style.display = 'none';  document.body.style.overflow = ''; }
document.querySelectorAll('.modal-backdrop-custom').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});
</script>
</body>
</html>