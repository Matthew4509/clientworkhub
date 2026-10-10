// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function sync(select) {
    var form = select.form;
    if (!form) return;
    var off = form.querySelector('[name="ap_offwork"]');
    if (!off) return;

    var opt = select.options[select.selectedIndex];
    var work = opt ? opt.getAttribute('data-work') : null;


    if (work === null) return;
    off.checked = work === '0';
  }

  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el && el.id === 'ap_kind') sync(el);
  });

  function init() {
    var sel = document.getElementById('ap_kind');
    if (sel) sync(sel);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }


  document.addEventListener('click', function (ev) {
    var o = ev.target instanceof Element ? ev.target.closest('[data-modal-open]') : null;
    if (!o || o.getAttribute('data-modal-open') !== 'dlg-appt') return;
    window.setTimeout(init, 0);
  });
}());


(function () {
  'use strict';

  var SETS = {
    none:     [],
    daily:    [1, 2, 3, 4, 5, 6, 7],
    weekdays: [1, 2, 3, 4, 5],
    weekend:  [6, 7]
  };

  function apply(form, key) {
    var want = SETS[key] || [];
    form.querySelectorAll('[name="ap_days[]"]').forEach(function (box) {
      box.checked = want.indexOf(parseInt(box.value, 10)) !== -1;
    });
    highlight(form);
  }


  function highlight(form) {
    var on = [];
    form.querySelectorAll('[name="ap_days[]"]').forEach(function (b) {
      if (b.checked) on.push(parseInt(b.value, 10));
    });
    on.sort(function (a, b) { return a - b; });

    form.querySelectorAll('[data-days-preset]').forEach(function (btn) {
      var want = (SETS[btn.getAttribute('data-days-preset')] || []).slice().sort(function (a, b) { return a - b; });
      btn.classList.toggle('is-on', want.join(',') === on.join(','));
    });
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-days-preset]') : null;
    if (!btn || !btn.form) return;
    ev.preventDefault();
    apply(btn.form, btn.getAttribute('data-days-preset'));
  });

  document.addEventListener('change', function (ev) {
    if (ev.target && ev.target.name === 'ap_days[]' && ev.target.form) highlight(ev.target.form);
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (f) {
      if (f.querySelector('[data-days-preset]')) highlight(f);
    });
  });
}());
