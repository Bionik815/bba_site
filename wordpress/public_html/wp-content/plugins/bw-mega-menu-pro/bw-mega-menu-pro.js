(function () {
  function initBWMM() {
    document.querySelectorAll('.bw-mega').forEach(function (wrap) {
      const toggle = wrap.querySelector('.bw-mega__toggle');
      const panel  = wrap.querySelector('.bw-mega__panel'); // avoid ID collisions
      if (!toggle || !panel) return;

      const inner  = panel.querySelector('.bw-mega__inner');
      const closeBtn = panel.querySelector('.bw-close');

      // helpers
      const open = () => {
        panel.removeAttribute('hidden');
        document.body.classList.add('bw-mega--open');      // lock scroll via CSS
        toggle.setAttribute('aria-expanded','true');
        (closeBtn || panel).focus?.();
      };
      const close = () => {
        panel.setAttribute('hidden','');
        document.body.classList.remove('bw-mega--open');
        toggle.setAttribute('aria-expanded','false');
        toggle.focus?.();
      };

      // open
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        open();
      });

      // close on X
      if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
          e.preventDefault();
          close();
        });
      }

      // close when clicking backdrop (outside inner panel)
      panel.addEventListener('click', (e) => {
        if (!inner) return;
        if (!inner.contains(e.target)) close();
      });

      // close on Esc
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
