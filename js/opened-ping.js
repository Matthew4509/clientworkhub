// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  var marker = document.querySelector('[data-opened-ping]');
  if (!marker || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') return;

  var sent = false;
  var events = ['pointerdown', 'pointermove', 'keydown', 'touchstart', 'wheel'];

  function ping() {
    if (sent || document.visibilityState === 'hidden') return;
    sent = true;
    events.forEach(function (name) { document.removeEventListener(name, ping, true); });
    var data = new FormData();
    data.append('cm_csrf', marker.getAttribute('data-csrf') || '');
    data.append('opened_ping', '1');
    fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' })
      .catch(function () {  });
  }

  events.forEach(function (name) { document.addEventListener(name, ping, true); });
}());
