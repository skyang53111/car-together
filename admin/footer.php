    </main>
</div>
<script>
document.addEventListener('click', function(e) {
    var dd = e.target.closest('.action-dropdown');
    document.querySelectorAll('.action-dropdown.open').forEach(function(el) {
        if (el !== dd) el.classList.remove('open');
    });
    if (dd && e.target.closest('.action-btn')) {
        e.stopPropagation();
        dd.classList.toggle('open');
    }
});
</script>
</body>
</html>
