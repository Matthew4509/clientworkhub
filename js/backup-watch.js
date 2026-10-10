// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  window.addEventListener('DOMContentLoaded', function () {
    var forms = Array.prototype.filter.call(document.querySelectorAll('form'),
      function (f) {
        var a = f.getAttribute('action') || '';
        return a.indexOf('backup.php') !== -1;
      });
    if (!forms.length) return;


    var here = window.location.pathname + window.location.search;
    var ping = here + (window.location.search ? '&' : '?') + 'ping=1';

    function read() {
      return fetch(ping, { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (j) { return j && j.backup_at !== undefined ? j.backup_at : null; });
    }


    var before = null;
    read().then(function (t) { before = t; }).catch(function () {});

    var watching = false, busy = [];

    function restore() {
      busy.forEach(function (b) {
        b.classList.remove('is-busy');
        b.textContent = b.getAttribute('data-was') || b.textContent;
      });
      busy = [];
    }

    function arrived() {

      window.location.href = here + '#update';
      window.location.reload();
    }

    function watch() {
      if (watching) return;
      watching = true;
      var tries = 0;
      (function poll() {
        read().then(function (t) {
          if (t !== null && t !== before) { arrived(); return; }

          if (++tries < 240) setTimeout(poll, 1500);
          else { watching = false; restore(); }
        }).catch(function () {
          if (++tries < 240) setTimeout(poll, 1500);
          else { watching = false; restore(); }
        });
      })();
    }

    forms.forEach(function (f) {
      f.addEventListener('submit', function () {
        var b = f.querySelector('button');
        if (b && b.getAttribute('data-was') === null) {
          b.setAttribute('data-was', b.textContent);
          b.classList.add('is-busy');
          b.textContent = 'Backing up…';
          busy.push(b);
        }

        setTimeout(watch, 800);
      });
    });
  });
})();
