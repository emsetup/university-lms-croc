(function () {
  function scrollRoot() {
    var admin = document.querySelector('.admin-content');
    if (admin && admin.scrollHeight > admin.clientHeight + 8) {
      return admin;
    }
    return null;
  }

  function scrollY(root) {
    return root ? root.scrollTop : (window.scrollY || document.documentElement.scrollTop || 0);
  }

  function init() {
    var btn = document.getElementById('portal-scroll-top');
    if (!btn) {
      return;
    }

    var root = scrollRoot();
    var threshold = 320;

    var onScroll = function () {
      if (scrollY(root) > threshold) {
        btn.classList.add('is-visible');
      } else {
        btn.classList.remove('is-visible');
      }
    };

    var target = root || window;
    target.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    btn.addEventListener('click', function () {
      if (root) {
        root.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
