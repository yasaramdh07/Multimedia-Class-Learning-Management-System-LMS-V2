<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Footer Template
 * ============================================================
 */
?>
        </main> <!-- /.page-content -->
    </div> <!-- /.main-content -->
</div> <!-- /.dashboard-container -->
<!-- Sidebar Overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<script src="<?= e(BASE_URL) ?>/assets/js/main.js?v=<?= time() ?>"></script>
<script>
    const toggle  = document.getElementById('sidebar-toggle');
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    function closeSidebar() {
        sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
    }
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active', isOpen);
        });
        if (overlay) overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });
    }
</script>
</body>
</html>
