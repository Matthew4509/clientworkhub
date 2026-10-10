// SPDX-License-Identifier: PolyForm-Shield-1.0.0
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.admin-nav-toggle').forEach(function (toggle) {
    var navId = toggle.getAttribute('aria-controls');
    var nav = navId ? document.getElementById(navId) : null;
    if (!nav) return;

    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      nav.classList.toggle('is-open', !open);
    });

    nav.addEventListener('click', function (event) {
      if (!event.target.closest('a')) return;
      toggle.setAttribute('aria-expanded', 'false');
      nav.classList.remove('is-open');
    });
  });
});
