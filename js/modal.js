// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function open(id) {
    var dlg = document.getElementById(id);
    if (!dlg || typeof dlg.showModal !== 'function') return false;
    if (dlg.open) return true;
    dlg.showModal();


    var first = dlg.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
    if (first) {
      try { first.focus(); } catch (e) {  }
    }


    var picker = dlg.querySelector('[data-autopicker]');
    if (picker && typeof picker.showPicker === 'function') {
      try { picker.showPicker(); } catch (e) {  }
    }
    return true;
  }


  var pressedOn = null;
  document.addEventListener('pointerdown', function (ev) {
    pressedOn = ev.target instanceof Element ? ev.target : null;
  }, true);

  document.addEventListener('click', function (ev) {
    var el = ev.target instanceof Element ? ev.target : null;
    if (!el) return;

    var opener = el.closest('[data-modal-open]');
    if (opener) {

      if (open(opener.getAttribute('data-modal-open'))) ev.preventDefault();
      return;
    }

    var closer = el.closest('[data-modal-close]');
    if (closer) {
      var dlg = closer.closest('dialog');
      if (dlg && dlg.open) { ev.preventDefault(); dlg.close(); }
      return;
    }


    if (el.tagName === 'DIALOG' && el.open) {
      var box = el.querySelector('.modal-box');
      if (box && !box.contains(ev.target) && !box.contains(pressedOn)) el.close();
    }
  });


  document.addEventListener('change', function (ev) {
    var picker = ev.target instanceof Element ? ev.target.closest('[data-property-kind-picker]') : null;
    if (!picker) return;
    var form = picker.closest('form');
    if (!form) return;
    var kind = picker.value;
    form.querySelectorAll('[data-property-fields]').forEach(function (fields) {
      var forKind = fields.getAttribute('data-property-fields');
      fields.hidden = forKind === 'shared' ? kind === 'web' : forKind !== kind;
    });
  });


  document.addEventListener('DOMContentLoaded', function () {
    var auto = document.querySelector('dialog[data-modal-autoopen]');
    if (auto) open(auto.id);
  });
}());
