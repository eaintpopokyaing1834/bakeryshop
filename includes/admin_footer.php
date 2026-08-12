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

    function toggleAdminLangMenu() {
        const menu = document.getElementById('adminLangMenu');
        const btn = document.getElementById('adminLangGlobeBtn');
        const open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', open);
    }

    function setAdminLang(code) {
        document.getElementById('adminLangInput').value = code;
        document.getElementById('adminLangForm').submit();
    }

    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('adminLangDropdownWrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('adminLangMenu').classList.remove('open');
            document.getElementById('adminLangGlobeBtn').setAttribute('aria-expanded', 'false');
        }
    });

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
                    list.innerHTML = '<p class="text-center text-gray-400 text-sm py-6"><?= __('admin_no_notifications') ?></p>';
                    return;
                }
                list.innerHTML = data.map(n => {
                    let icon;
                    if (n.type === 'new_order') {
                        icon = '<div class="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>';
                    } else if (n.type === 'customize_request') {
                        icon = '<div class="w-9 h-9 bg-amber-100 rounded-full flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></div>';
                    } else {
                        icon = '<div class="w-9 h-9 bg-green-100 rounded-full flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>';
                    }
                    const unread = n.is_seen == 0 ? 'bg-rose-50' : '';
                    return `<div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer ${unread}">${icon}<div class="flex-1 min-w-0"><p class="text-sm font-semibold text-gray-800">${n.title}</p><p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${n.message}</p><p class="text-[10px] text-gray-400 mt-1">${window.localizeJsDate(n.created_at)}</p></div></div>`;
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

    document.addEventListener('click', function (e) {
        const wrapper = document.getElementById('notifWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('notifDropdown')?.classList.add('hidden');
        }
    });

    window.localizeJsNumber = function(amount) {
        let formatted = String(amount);
        const currentLang = '<?= currentLang() ?>';
        if (currentLang === 'my') {
            const en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            const my = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
            for (let i = 0; i < 10; i++) {
                formatted = formatted.split(en[i]).join(my[i]);
            }
        }
        return formatted;
    };

    window.localizeJsDate = function(dateString) {
        let d = new Date(dateString);
        let formatted = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        const currentLang = '<?= currentLang() ?>';
        if (currentLang === 'my') {
            const en_months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const my_months = ['ဇန်နဝါရီ', 'ဖေဖော်ဝါရီ', 'မတ်', 'ဧပြီ', 'မေ', 'ဇွန်', 'ဇူလိုင်', 'ဩဂုတ်', 'စက်တင်ဘာ', 'အောက်တိုဘာ', 'နိုဝင်ဘာ', 'ဒီဇင်ဘာ'];
            for (let i = 0; i < 12; i++) {
                formatted = formatted.replace(en_months[i], my_months[i]);
            }
            formatted = formatted.replace(/,/g, '၊');
            return window.localizeJsNumber(formatted);
        }
        return formatted;
    };

    window.formatPriceJS = function(amount) {
        let formatted = Number(amount).toLocaleString();
        const currentLang = '<?= currentLang() ?>';
        if (currentLang === 'my') {
            return window.localizeJsNumber(formatted) + ' ကျပ်';
        }
        return formatted + ' MMK';
    };
</script>
</body>

</html>