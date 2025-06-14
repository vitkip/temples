
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuButton = document.getElementById('toggleSidebar');
            const sidebar = document.querySelector('aside');
            
            if (menuButton) {
                menuButton.addEventListener('click', function() {
                    sidebar.classList.toggle('hidden');
                });
            }
            
            // Initialize any charts if they exist on the page
            if (window.initCharts && typeof window.initCharts === 'function') {
                window.initCharts();
            }
        });
    </script>
</body>
</html>