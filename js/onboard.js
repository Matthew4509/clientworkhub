// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function clear(row) {
    row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    return row;
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target;
    if (!btn || !btn.hasAttribute || !btn.hasAttribute('data-prop-add')) return;
    ev.preventDefault();

    var set = btn.closest('fieldset');
    var list = set && set.querySelector('[data-prop-list]');
    if (!list) return;

    var rows = list.querySelectorAll('[data-prop-row]');

    if (rows.length >= 25) {
      btn.disabled = true;
      return;
    }

    var copy = clear(rows[rows.length - 1].cloneNode(true));
    list.appendChild(copy);


    var first = copy.querySelector('input');
    if (first) first.focus();
  });
})();
