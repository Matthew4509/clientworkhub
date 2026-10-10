// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';
  var sel = document.querySelector('[data-compose-pick]');
  var dataEl = document.getElementById('compose-data');
  if (!sel || !dataEl) return;
  var map;
  try { map = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
  var subj = document.querySelector('[data-compose-subject]');
  var body = document.querySelector('[data-compose-body]');
  sel.addEventListener('change', function () {
    var m = map[sel.value];
    if (!m || !subj || !body) return;
    subj.value = m.subject || '';
    body.value = m.body || '';
  });
}());
