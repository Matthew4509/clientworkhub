// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function clamp(n) { return Math.max(0, Math.min(100, n)); }

  function draw() {
    document.querySelectorAll('.prog-fill[data-pct]').forEach(function (el) {
      el.style.width = clamp(parseFloat(el.getAttribute('data-pct')) || 0) + '%';
    });
    document.querySelectorAll('.sp-bar[data-h]').forEach(function (el) {
      el.style.height = clamp(parseFloat(el.getAttribute('data-h')) || 0) + '%';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', draw);
  } else {
    draw();
  }
})();
