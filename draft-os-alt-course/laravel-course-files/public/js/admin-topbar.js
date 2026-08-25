(function () {
  function initTopbar(root) {
    var toggle = root.querySelector('[data-admin-topbar-toggle]');
    if (!toggle) {
      return;
    }

    var iconOpen = root.querySelector('[data-admin-topbar-icon-open]');
    var iconClose = root.querySelector('[data-admin-topbar-icon-close]');

    function setOpen(open) {
      root.classList.toggle('is-menu-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
      if (iconOpen) {
        iconOpen.hidden = open;
      }
      if (iconClose) {
        iconClose.hidden = !open;
      }
    }

    toggle.addEventListener('click', function () {
      setOpen(!root.classList.contains('is-menu-open'));
    });

    root.querySelectorAll('.admin-topbar__nav a, .admin-topbar__actions a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 720px)').matches) {
          setOpen(false);
        }
      });
    });

    window.addEventListener('resize', function () {
      if (!window.matchMedia('(max-width: 720px)').matches) {
        setOpen(false);
      }
    });
  }

  document.querySelectorAll('[data-admin-topbar]').forEach(initTopbar);
})();
