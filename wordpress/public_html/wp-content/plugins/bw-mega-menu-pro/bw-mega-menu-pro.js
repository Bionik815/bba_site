(function () {
    'use strict';

    function initMegaMenu(nav) {
        var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-bw-mm-tab]'));
        var panel = nav.querySelector('[data-bw-mm-panel]');
        var groups = Array.prototype.slice.call(nav.querySelectorAll('[data-bw-mm-group]'));
        if (!tabs.length || !panel) {
            return;
        }

        var hoverMedia = window.matchMedia('(hover: hover) and (pointer: fine)');
        var openTimer = null;
        var closeTimer = null;
        var activeGroup = null;

        function clearTimers() {
            if (openTimer) { clearTimeout(openTimer); openTimer = null; }
            if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
        }

        function showGroup(groupId) {
            activeGroup = groupId;
            panel.hidden = false;
            nav.classList.add('is-open');

            groups.forEach(function (section) {
                section.hidden = section.getAttribute('data-bw-mm-group') !== groupId;
            });

            tabs.forEach(function (tab) {
                var isActive = tab.getAttribute('data-bw-mm-tab') === groupId;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            });
        }

        function closePanel() {
            activeGroup = null;
            panel.hidden = true;
            nav.classList.remove('is-open');
            tabs.forEach(function (tab) {
                tab.classList.remove('is-active');
                tab.setAttribute('aria-expanded', 'false');
            });
        }

        function scheduleClose() {
            clearTimers();
            closeTimer = setTimeout(closePanel, 180);
        }

        tabs.forEach(function (tab) {
            var groupId = tab.getAttribute('data-bw-mm-tab');

            tab.addEventListener('click', function () {
                clearTimers();
                if (activeGroup === groupId) {
                    closePanel();
                } else {
                    showGroup(groupId);
                }
            });

            tab.addEventListener('mouseenter', function () {
                if (!hoverMedia.matches) {
                    return;
                }
                clearTimers();
                // Small intent delay only when opening fresh; switching
                // between tabs while open is instant.
                if (activeGroup) {
                    showGroup(groupId);
                } else {
                    openTimer = setTimeout(function () { showGroup(groupId); }, 80);
                }
            });

            tab.addEventListener('mouseleave', function () {
                if (!hoverMedia.matches) {
                    return;
                }
                scheduleClose();
            });
        });

        panel.addEventListener('mouseenter', function () {
            if (hoverMedia.matches) {
                clearTimers();
            }
        });

        panel.addEventListener('mouseleave', function () {
            if (hoverMedia.matches) {
                scheduleClose();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeGroup) {
                closePanel();
            }
        });

        document.addEventListener('click', function (event) {
            if (activeGroup && !nav.contains(event.target)) {
                closePanel();
            }
        });

        // Keep the panel open while tabbing through it; close when focus
        // leaves the whole nav.
        nav.addEventListener('focusout', function () {
            setTimeout(function () {
                if (activeGroup && !nav.contains(document.activeElement)) {
                    closePanel();
                }
            }, 0);
        });
    }

    function boot() {
        Array.prototype.slice.call(document.querySelectorAll('[data-bw-mm]')).forEach(initMegaMenu);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
