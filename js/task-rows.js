// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';


  function addRow(grid) {
    var rows = grid.querySelectorAll('[data-task-row]');
    var last = rows[rows.length - 1];
    if (!last) return;

    var copy = last.cloneNode(true);
    var n = rows.length;

    copy.querySelectorAll('input, select').forEach(function (el) {
      var name = el.getAttribute('name');
      if (name) el.setAttribute('name', name.replace(/\[\d+\]/, '[' + n + ']'));
      if (el.tagName === 'SELECT') {

        el.value = last.querySelector('select') ? last.querySelector('select').value : el.value;
      } else {
        el.value = '';
      }

      var id = el.getAttribute('id');
      if (id) {
        var fresh = id.replace(/-r\d+$/, '-r' + n);
        var label = copy.querySelector('label[for="' + id + '"]');
        el.setAttribute('id', fresh);
        if (label) label.setAttribute('for', fresh);
      }
    });

    grid.appendChild(copy);
    var first = copy.querySelector('input[type="text"]');
    if (first) first.focus();
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-task-add]');
    if (!btn) return;
    var grid = document.getElementById(btn.getAttribute('data-task-add'));
    if (!grid) return;
    ev.preventDefault();
    addRow(grid);
  });
})();
