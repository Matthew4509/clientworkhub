// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function apply(form) {
    var toggle = form.querySelector('[data-auth-toggle]');
    if (!toggle) return;
    var on    = toggle.checked;
    var group = form.querySelector('[data-credential-fields]');
    var boxes = form.querySelectorAll('[data-needs-signin]');

    if (group) group.hidden = !on;

    for (var i = 0; i < boxes.length; i++) {
      var el = boxes[i];
      if (on) {
        el.setAttribute('required', 'required');
        var min = el.getAttribute('data-minlength');
        if (min) el.setAttribute('minlength', min);
      } else {

        el.removeAttribute('required');
        el.removeAttribute('minlength');
      }
    }
  }


  function preview(form) {
    var box = document.querySelector('[data-workscreen-preview]');
    if (!box) return;

    var ticks = form.querySelectorAll('[data-preview]');
    for (var i = 0; i < ticks.length; i++) {
      var key  = ticks[i].getAttribute('data-preview');
      var line = box.querySelector('[data-preview-for="' + key + '"]');
      if (line) line.hidden = !ticks[i].checked;
      if (key === 'vip') box.classList.toggle('is-vip', ticks[i].checked);
    }

    var heading = form.querySelector('[name=public_heading]');
    var view    = form.querySelector('[name=screen_view]');
    var head    = box.querySelector('[data-preview-head]');
    if (head) {
      var typed = heading && heading.value.trim() !== '' ? heading.value.trim() : '';
      head.textContent = typed || (view && view.options[view.selectedIndex]
          ? view.options[view.selectedIndex].text : 'Today');
    }
  }

  function update(form) { apply(form); preview(form); }

  function forForm(e) {
    var f = e.target.closest ? e.target.closest('[data-workscreen-form]') : null;
    if (f) update(f);
  }

  document.addEventListener('change', forForm);
  document.addEventListener('input',  forForm);
  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('[data-workscreen-form]');
    for (var i = 0; i < forms.length; i++) update(forms[i]);
  });
}());
