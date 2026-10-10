// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  var form = document.getElementById('smart-send-form');
  var btn = document.getElementById('smart-send-btn');
  if (form && btn) {
    var update = function () {
      var boxes = form.querySelectorAll('input[name="ids[]"]');
      var n = 0;
      for (var i = 0; i < boxes.length; i++) {
        if (boxes[i].checked) n++;
      }
      btn.textContent = 'Send ' + n;
      btn.disabled = n === 0;
      form.setAttribute('data-progress-seconds', String(2 + 0.5 * n));
    };
    form.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'ids[]') update();
    });
    update();
    form.addEventListener('submit', function () {
      var dlg = form.closest('dialog');
      if (dlg && dlg.open) dlg.close();
    }, true);
  }

  var vip = document.querySelector('input[name="no_push_vip"]');
  if (vip) {
    var swap = function () {
      var word = (vip.checked ? 'excluding' : 'including') + ' VIPs';
      var all = document.getElementById('pause-all-vips');
      var lng = document.getElementById('pause-long-vips');
      if (all) all.textContent = word;
      if (lng) lng.textContent = word;
    };
    vip.addEventListener('change', swap);
  }
}());
