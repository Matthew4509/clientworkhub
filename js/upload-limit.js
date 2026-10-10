// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function tooBig(input) {
    var max = parseInt(input.getAttribute('data-max-bytes') || '', 10);
    if (!(max > 0) || !input.files) return '';
    for (var i = 0; i < input.files.length; i++) {
      if (input.files[i].size > max) {
        return input.files[i].name + ' is larger than this server accepts in one upload ('
          + (input.getAttribute('data-max-label') || max + ' bytes')
          + '). Choose a smaller file, or ask your host to raise the upload limit.';
      }
    }
    return '';
  }

  function check(input) {
    var why = tooBig(input);
    input.setCustomValidity(why);
    return why === '';
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.nodeName !== 'INPUT' || input.type !== 'file' || !input.hasAttribute('data-max-bytes')) return;
    if (!check(input) && typeof input.reportValidity === 'function') input.reportValidity();
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.nodeName !== 'FORM') return;
    var boxes = form.querySelectorAll('input[type=file][data-max-bytes]');
    for (var i = 0; i < boxes.length; i++) {
      if (!check(boxes[i])) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (typeof boxes[i].reportValidity === 'function') boxes[i].reportValidity();
        return;
      }
    }
  }, true);
}());
