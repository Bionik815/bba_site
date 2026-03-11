(function () {
  function initTabs(root) {
    const tabs = Array.from(root.querySelectorAll('.bw-tab[role="tab"]'));
    if (!tabs.length) return;

    const activateTab = (tab, moveFocus) => {
      tabs.forEach((btn) => {
        const isActive = btn === tab;
        const panelId = btn.getAttribute('aria-controls');
        const panel = panelId ? root.querySelector('#' + CSS.escape(panelId)) : null;

        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        btn.setAttribute('tabindex', isActive ? '0' : '-1');

        if (panel) {
          panel.classList.toggle('is-active', isActive);
          if (isActive) panel.removeAttribute('hidden');
          else panel.setAttribute('hidden', '');
        }
      });

      if (moveFocus) tab.focus();
    };

    tabs.forEach((tab, i) => {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        activateTab(tab, false);
      });

      tab.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' && e.key !== 'Home' && e.key !== 'End') {
          return;
        }

        e.preventDefault();
        let nextIndex = i;
        if (e.key === 'ArrowRight') nextIndex = (i + 1) % tabs.length;
        if (e.key === 'ArrowLeft') nextIndex = (i - 1 + tabs.length) % tabs.length;
        if (e.key === 'Home') nextIndex = 0;
        if (e.key === 'End') nextIndex = tabs.length - 1;
        activateTab(tabs[nextIndex], true);
      });
    });

    const current = tabs.find((t) => t.getAttribute('aria-selected') === 'true') || tabs[0];
    activateTab(current, false);
  }

  function initBWMM() {
    document.querySelectorAll('.bw-mega').forEach(function (wrap) {
      const toggle = wrap.querySelector('.bw-mega__toggle');
      const panel = wrap.querySelector('.bw-mega__panel');
      if (!toggle || !panel) return;

      const inner = panel.querySelector('.bw-mega__inner');
      const closeBtn = panel.querySelector('.bw-close');

      initTabs(panel);

      const open = () => {
        panel.removeAttribute('hidden');
        document.body.classList.add('bw-mega--open');
        toggle.setAttribute('aria-expanded', 'true');
        (closeBtn || panel).focus?.();
      };

      const close = () => {
        panel.setAttribute('hidden', '');
        document.body.classList.remove('bw-mega--open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus?.();
      };

      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        open();
      });

      if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
          e.preventDefault();
          close();
        });
      }

      panel.addEventListener('click', (e) => {
        if (!inner) return;
        if (!inner.contains(e.target)) close();
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hasAttribute('hidden')) close();
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBWMM);
  } else {
    initBWMM();
  }
})();
