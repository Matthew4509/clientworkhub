/* SPDX-License-Identifier: PolyForm-Shield-1.0.0 */

(function () {
  'use strict';
  var sel = document.querySelector('[data-first-run-tz]');
  if (!sel || sel.value !== 'UTC') return;
  var zone = '';
  try { zone = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { zone = ''; }
  for (var i = 0; i < sel.options.length; i++) {
    if (sel.options[i].value === zone) { sel.selectedIndex = i; return; }
  }
})();
