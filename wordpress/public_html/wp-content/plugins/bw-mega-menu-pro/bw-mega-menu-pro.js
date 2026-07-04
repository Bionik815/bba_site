(function () {
    'use strict';

    function initMegaMenu(nav) {
        var trigger = nav.querySelector('[data-bw-mm-trigger]');
        var panel = nav.querySelector('[data-bw-mm-panel]');
        var cats = Array.prototype.slice.call(nav.querySelectorAll('[data-bw-mm-tab]'));
        var groups = Array.prototype.slice.call(nav.querySelectorAll('[data-bw-mm-group]'));
        if (!trigger || !panel || !cats.length) {
            return;
        }

        var hoverMedia = window.matchMedia('(hover: hover) and (pointer: fine)');
        var openTimer = null;
        var closeTimer = null;
        var isOpen = false;
        var activeGroup = cats[0].getAttribute('data-bw-mm-tab');

        function clearTimers() {
            if (openTimer) { clearTimeout(openTimer); openTimer = null; }
            if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
        }

        function showGroup(groupId) {
            activeGroup = groupId;
            groups.forEach(function (section) {
                section.hidden = section.getAttribute('data-bw-mm-group') !== groupId;
            });
            cats.forEach(function (cat) {
                var isActive = cat.getAttribute('data-bw-mm-tab') === groupId;
                cat.classList.toggle('is-active', isActive);
                cat.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            });
        }

        function openPanel() {
            isOpen = true;
            panel.hidden = false;
            nav.classList.add('is-open');
            trigger.classList.add('is-active');
            trigger.setAttribute('aria-expanded', 'true');
            showGroup(activeGroup);
        }

        function closePanel() {
            isOpen = false;
            panel.hidden = true;
            nav.classList.remove('is-open');
            trigger.classList.remove('is-active');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function scheduleClose() {
            clearTimers();
            closeTimer = setTimeout(closePanel, 180);
        }

        trigger.addEventListener('click', function () {
            clearTimers();
            if (isOpen) {
                closePanel();
            } else {
                openPanel();
            }
        });

        trigger.addEventListener('mouseenter', function () {
            if (!hoverMedia.matches) {
                return;
            }
            clearTimers();
            if (!isOpen) {
                openTimer = setTimeout(openPanel, 80);
            }
        });

        trigger.addEventListener('mouseleave', function () {
            if (hoverMedia.matches) {
                scheduleClose();
            }
        });

        cats.forEach(function (cat) {
            var groupId = cat.getAttribute('data-bw-mm-tab');

            cat.addEventListener('click', function () {
                clearTimers();
                showGroup(groupId);
            });

            cat.addEventListener('mouseenter', function () {
                if (hoverMedia.matches) {
                    clearTimers();
                    showGroup(groupId);
                }
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
            if (event.key === 'Escape' && isOpen) {
                closePanel();
                trigger.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (isOpen && !nav.contains(event.target)) {
                closePanel();
            }
        });

        // Keep the panel open while tabbing through it; close when focus
        // leaves the whole nav.
        nav.addEventListener('focusout', function () {
            setTimeout(function () {
                if (isOpen && !nav.contains(document.activeElement)) {
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
