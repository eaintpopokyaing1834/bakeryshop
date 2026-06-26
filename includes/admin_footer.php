<?php
// includes/admin_footer.php — Closes admin layout
?>
        </main>
    </div><!-- /.main-content-wrapper -->

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }

    function toggleNotifDropdown() {
        const dropdown = document.getElementById('notifDropdown');
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            loadNotifications();
        }
    }

    function loadNotifications() {
        fetch('/sweetheaven/api/notifications.php?action=list')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('notifList');
                if (data.length === 0) {
                    list.innerHTML = '<p class="text-center text-gray-400 text-sm py-6">No notifications</p>';
                    return;
                }
                list.innerHTML = data.map(n => {
                    const icon = n.type === 'new_order'
                        ? '<div class="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>'
                        : '<div class="w-9 h-9 bg-green-100 rounded-full flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>';
                    const unread = n.is_seen == 0 ? 'bg-rose-50' : '';
                    return `<div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer ${unread}">${icon}<div class="flex-1 min-w-0"><p class="text-sm font-semibold text-gray-800">${n.title}</p><p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${n.message}</p><p class="text-[10px] text-gray-400 mt-1">${n.created_at}</p></div></div>`;
                }).join('');
            });
    }

    function markAllSeen() {
        fetch('/sweetheaven/api/notifications.php?action=mark_seen', { method: 'POST' })
            .then(r => r.json())
            .then(() => {
                const badge = document.getElementById('notifBadge');
                if (badge) badge.remove();
                loadNotifications();
            });
    }

    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notifWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('notifDropdown')?.classList.add('hidden');
        }
    });
    </script>
</body>
</html>
