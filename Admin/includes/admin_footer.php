    </main>

    <script>
        lucide.createIcons();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('open');
            if (overlay) {
                overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
            }
        }

        // Show mobile toggle button below 1024px
        const toggleBtn = document.querySelector('.sidebar-toggle-btn');
        const websiteBtn = document.querySelector('.website-btn');
        function handleResize() {
            if (window.innerWidth < 1024) {
                if (toggleBtn) toggleBtn.style.display = 'inline-flex';
                if (websiteBtn) websiteBtn.style.display = 'none';
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                if (sidebar) sidebar.classList.remove('open');
                if (overlay) overlay.style.display = 'none';
            } else {
                if (toggleBtn) toggleBtn.style.display = 'none';
                if (websiteBtn) websiteBtn.style.display = 'inline-flex';
            }
        }
        window.addEventListener('resize', handleResize);
        handleResize();

        // Close sidebar when clicking a nav link on mobile
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    setTimeout(toggleSidebar, 100);
                }
            });
        });
    </script>
</body>
</html>
