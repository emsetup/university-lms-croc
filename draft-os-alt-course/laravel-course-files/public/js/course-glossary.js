(function () {
  var tip = null;
  var active = null;

  function ensureTip() {
    if (tip) return tip;
    tip = document.createElement('div');
    tip.className = 'course-glossary-tip';
    tip.setAttribute('role', 'tooltip');
    document.body.appendChild(tip);
    return tip;
  }

  function placeTip(el) {
    var box = ensureTip();
    var rect = el.getBoundingClientRect();
    var margin = 10;
    var tipW = box.offsetWidth || 240;
    var tipH = box.offsetHeight || 48;
    var left = rect.left + rect.width / 2 - tipW / 2;
    left = Math.max(margin, Math.min(left, window.innerWidth - tipW - margin));
    var top = rect.top - tipH - 8;
    if (top < margin) {
      top = rect.bottom + 8;
    }
    box.style.left = left + 'px';
    box.style.top = top + 'px';
  }

  function show(el) {
    var text = el.getAttribute('data-glossary-tip') || '';
    if (!text) return;
    active = el;
    var box = ensureTip();
    box.textContent = text;
    box.classList.add('is-visible');
    placeTip(el);
  }

  function hide() {
    active = null;
    if (!tip) return;
    tip.classList.remove('is-visible');
  }

  document.addEventListener('mouseover', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.course-glossary-term') : null;
    if (el) show(el);
  });
  document.addEventListener('mouseout', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.course-glossary-term') : null;
    if (!el) return;
    var related = e.relatedTarget;
    if (related && el.contains(related)) return;
    hide();
  });
  document.addEventListener('focusin', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.course-glossary-term') : null;
    if (el) show(el);
  });
  document.addEventListener('focusout', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.course-glossary-term') : null;
    if (el) hide();
  });
  window.addEventListener('scroll', function () {
    if (active) placeTip(active);
  }, true);
  window.addEventListener('resize', function () {
    if (active) placeTip(active);
  });
})();
