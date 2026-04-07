(function () {
    var THEME_KEY = 'xdj_oa_admin_theme';
    var SIDEBAR_KEY = 'xdj_oa_admin_sidebar_collapsed';

    function getStoredTheme() {
        try {
            return localStorage.getItem(THEME_KEY);
        } catch (e) {
            return null;
        }
    }

    /**
     * 未手动选主题时：本地时间 6–18 点浅色，其余深色。
     */
    function getTimeBasedTheme() {
        var h = new Date().getHours();
        return (h >= 6 && h <= 18) ? 'light' : 'dark';
    }

    function applyTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            theme = 'dark';
        }
        document.documentElement.setAttribute('data-theme', theme);
    }

    function persistTheme(theme) {
        try {
            localStorage.setItem(THEME_KEY, theme);
        } catch (e) {
            /* ignore */
        }
    }

    function isSidebarCollapsed() {
        return document.documentElement.classList.contains('admin-sidebar-collapsed');
    }

    function setSidebarCollapsed(collapsed) {
        var root = document.documentElement;
        if (collapsed) {
            root.classList.add('admin-sidebar-collapsed');
        } else {
            root.classList.remove('admin-sidebar-collapsed');
        }
        try {
            localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0');
        } catch (e) {
            /* ignore */
        }
        syncSidebarToggleUi();
    }

    function syncSidebarToggleUi() {
        var btn = document.getElementById('admin-sidebar-toggle');
        if (!btn) {
            return;
        }
        var collapsed = isSidebarCollapsed();
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.setAttribute('title', collapsed ? '展开侧栏' : '收起侧栏');
    }

    function applyAutoThemeIfNeeded() {
        var s = getStoredTheme();
        if (s !== 'light' && s !== 'dark') {
            applyTheme(getTimeBasedTheme());
        }
    }

    function initTheme() {
        applyAutoThemeIfNeeded();

        var themeBtn = document.getElementById('admin-theme-toggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
                var next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                persistTheme(next);
            });
        }

        setInterval(applyAutoThemeIfNeeded, 60000);
    }

    function initClock() {
        var el = document.getElementById('admin-header-time');
        if (!el) {
            return;
        }
        function pad2(n) {
            return n < 10 ? '0' + n : String(n);
        }
        function tick() {
            var now = new Date();
            el.setAttribute('datetime', now.toISOString());
            var y = now.getFullYear();
            var m = pad2(now.getMonth() + 1);
            var d = pad2(now.getDate());
            var h = pad2(now.getHours());
            var min = pad2(now.getMinutes());
            var s = pad2(now.getSeconds());
            el.textContent = y + '年' + m + '月' + d + '日 ' + h + ':' + min + ':' + s;
            applyAutoThemeIfNeeded();
        }
        tick();
        setInterval(tick, 1000);
    }

    function initSidebar() {
        var toggle = document.getElementById('admin-sidebar-toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                if (window.matchMedia && !window.matchMedia('(min-width: 769px)').matches) {
                    return;
                }
                setSidebarCollapsed(!isSidebarCollapsed());
            });
            syncSidebarToggleUi();
        }

        if (window.matchMedia) {
            var mq = window.matchMedia('(max-width: 768px)');
            function onNarrow() {
                if (mq.matches) {
                    document.documentElement.classList.remove('admin-sidebar-collapsed');
                    syncSidebarToggleUi();
                } else {
                    try {
                        if (localStorage.getItem(SIDEBAR_KEY) === '1') {
                            document.documentElement.classList.add('admin-sidebar-collapsed');
                        }
                    } catch (e) { /* ignore */ }
                    syncSidebarToggleUi();
                }
            }
            mq.addEventListener('change', onNarrow);
        }
    }

    var MENU_ACCORDION_KEY = 'xdj_oa_sidebar_open_parent';

    function initMenuAccordion() {
        var groups = document.querySelectorAll('.admin-sidebar__group[data-menu-id]');
        if (!groups.length) {
            return;
        }

        function setOpenGroupId(id) {
            groups.forEach(function (group) {
                var sid = group.getAttribute('data-menu-id');
                var sub = group.querySelector('.admin-sidebar__sub');
                if (!sub) {
                    return;
                }
                if (String(sid) === String(id)) {
                    group.classList.add('is-open');
                    sub.classList.remove('admin-sidebar__sub--collapsed');
                } else {
                    group.classList.remove('is-open');
                    sub.classList.add('admin-sidebar__sub--collapsed');
                }
            });
        }

        function findActiveGroupId() {
            for (var i = 0; i < groups.length; i++) {
                if (groups[i].querySelector('.admin-sidebar__link.is-active')) {
                    return groups[i].getAttribute('data-menu-id');
                }
            }
            return null;
        }

        var stored = null;
        try {
            stored = sessionStorage.getItem(MENU_ACCORDION_KEY);
        } catch (e) { /* ignore */ }

        var openId = stored || findActiveGroupId();
        if (openId) {
            setOpenGroupId(openId);
        } else {
            groups.forEach(function (group) {
                var sub = group.querySelector('.admin-sidebar__sub');
                if (sub) {
                    group.classList.remove('is-open');
                    sub.classList.add('admin-sidebar__sub--collapsed');
                }
            });
        }

        groups.forEach(function (group) {
            var parentLink = group.querySelector('.admin-sidebar__link--parent');
            if (!parentLink) {
                return;
            }
            parentLink.addEventListener('click', function () {
                var id = group.getAttribute('data-menu-id');
                try {
                    sessionStorage.setItem(MENU_ACCORDION_KEY, id);
                } catch (e) { /* ignore */ }
                setOpenGroupId(id);
            });
        });
    }

    function init() {
        initTheme();
        initClock();
        initSidebar();
        initMenuAccordion();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
