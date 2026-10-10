// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function closeAll(except) {
    document.querySelectorAll('details.newmenu[open]').forEach(function (d) {
      if (d !== except) d.open = false;
    });
  }

  document.addEventListener('click', function (ev) {
    var inside = ev.target instanceof Element ? ev.target.closest('details.newmenu') : null;
    closeAll(inside);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    var open = document.querySelector('details.newmenu[open]');
    if (!open) return;
    open.open = false;
    var summary = open.querySelector('summary');
    if (summary) summary.focus();
  });
})();
