<?php
/**
 * Shared Footer Layout Template
 * Completes layout shell and handles interactive collapsible sidebar logic.
 */
?>
        </main>
    </div> <!-- Close mainContentArea -->
</div> <!-- Close Authenticated App Shell -->

<!-- Sidebar Backdrop for Mobile overlay -->
<div id="sidebarBackdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-30 hidden md:hidden transition-opacity"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const mainContentArea = document.getElementById('mainContentArea');
    const mobileOpenBtn = document.getElementById('mobileOpenSidebar');
    const mobileCloseBtn = document.getElementById('mobileCloseSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const desktopCollapseBtn = document.getElementById('desktopCollapseSidebar');

    // Mobile sidebar toggle
    function openMobileSidebar() {
        if (sidebar && backdrop) {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
        }
    }

    function closeMobileSidebar() {
        if (sidebar && backdrop) {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }
    }

    if (mobileOpenBtn) mobileOpenBtn.addEventListener('click', openMobileSidebar);
    if (mobileCloseBtn) mobileCloseBtn.addEventListener('click', closeMobileSidebar);
    if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);

    // Desktop mini sidebar toggle (persisted in localStorage)
    const savedState = localStorage.getItem('apex_sidebar_collapsed');
    if (savedState === 'true' && sidebar && mainContentArea && window.innerWidth >= 768) {
        sidebar.classList.add('sidebar-collapsed');
        mainContentArea.classList.remove('md:ml-64');
        mainContentArea.classList.add('md:ml-20');
    }

    if (desktopCollapseBtn && sidebar && mainContentArea) {
        desktopCollapseBtn.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
            if (isCollapsed) {
                mainContentArea.classList.remove('md:ml-64');
                mainContentArea.classList.add('md:ml-20');
                localStorage.setItem('apex_sidebar_collapsed', 'true');
            } else {
                mainContentArea.classList.remove('md:ml-20');
                mainContentArea.classList.add('md:ml-64');
                localStorage.setItem('apex_sidebar_collapsed', 'false');
            }
        });
    }

    // Collapsible navigation groups/accordions
    document.querySelectorAll('.group-toggle').forEach(function(btn) {
        const targetId = btn.getAttribute('data-target');
        const targetEl = document.getElementById(targetId);
        const icon = btn.querySelector('.chevron-icon');

        // Check if state stored
        const storedGroupState = localStorage.getItem('group_' + targetId);
        if (storedGroupState === 'closed' && targetEl) {
            targetEl.classList.add('hidden');
            if (icon) icon.classList.add('-rotate-90');
        }

        btn.addEventListener('click', function() {
            if (!targetEl) return;
            const isHidden = targetEl.classList.toggle('hidden');
            if (icon) {
                if (isHidden) {
                    icon.classList.add('-rotate-90');
                    localStorage.setItem('group_' + targetId, 'closed');
                } else {
                    icon.classList.remove('-rotate-90');
                    localStorage.setItem('group_' + targetId, 'open');
                }
            }
        });
    });
});
</script>

<footer class="border-t border-slate-900 bg-slate-950/90 py-4 text-center text-xs text-slate-500">
    <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div>&copy; <?= date('Y') ?> <?= APP_NAME ?>. High-performance direct selling architecture.</div>
        <div class="flex items-center space-x-4">
            <span class="text-slate-600">PDO Prepared Queries</span>
            <span class="text-slate-600">&bull;</span>
            <span class="text-slate-600">Zero-Dependency Plain PHP</span>
        </div>
    </div>
</footer>

</body>
</html>
