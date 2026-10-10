// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  var OPEN = null;

  function els(root) {
    return {
      hidden: root.querySelector('[data-picker-value]'),
      input:  root.querySelector('[data-picker-input]'),
      list:   root.querySelector('[data-picker-list]')
    };
  }

  function close(root) {
    var p = els(root);
    if (p.list) { p.list.hidden = true; p.list.innerHTML = ''; }
    if (p.input) p.input.setAttribute('aria-expanded', 'false');
    if (OPEN === root) OPEN = null;
  }


  function mark(root) {
    var p = els(root);
    if (!p.input || !p.hidden) return;
    var need = p.input.hasAttribute('data-picker-required');
    var has  = p.hidden.value !== '' && p.hidden.value !== '0';
    p.input.setCustomValidity(
      (need && !has) ? 'Choose a client from the list.' : '');
    root.classList.toggle('is-chosen', has);
  }

  function choose(root, id, name) {
    var p = els(root);
    p.hidden.value = String(id);
    p.input.value = name;
    close(root);
    mark(root);
    p.hidden.dispatchEvent(new Event('change', { bubbles: true }));
  }


  var MAX_ROWS = 10;

  function render(root, rows) {
    var p = els(root);
    if (!p.list) return;
    p.list.innerHTML = '';

    if (rows.length > MAX_ROWS) rows = rows.slice(0, MAX_ROWS);

    if (!rows.length) {
      var none = document.createElement('li');
      none.className = 'picker-none';
      none.textContent = p.input.value.trim() === ''
        ? 'Start typing to find somebody.'
        : 'Nobody matches that.';
      p.list.appendChild(none);
    }

    rows.forEach(function (r, i) {
      var li = document.createElement('li');
      li.className = 'picker-row';
      li.setAttribute('role', 'option');
      li.setAttribute('data-id', String(r.id));
      li.setAttribute('data-name', r.name);
      li.tabIndex = -1;

      var b = document.createElement('b');
      b.textContent = r.name;
      li.appendChild(b);
      if (r.meta) {
        var s = document.createElement('span');
        s.textContent = r.meta;
        li.appendChild(s);
      }
      if (i === 0) li.classList.add('is-on');
      p.list.appendChild(li);
    });


    if (!p.input.hasAttribute('data-picker-required')) {
      var clear = document.createElement('li');
      clear.className = 'picker-row picker-clear';
      clear.setAttribute('data-id', '0');
      clear.setAttribute('data-name', '');
      clear.textContent = '— nobody in particular —';
      p.list.appendChild(clear);
    }

    p.list.hidden = false;
    p.input.setAttribute('aria-expanded', 'true');
    OPEN = root;
  }

  var timer = null;
  function search(root) {
    var p = els(root);
    window.clearTimeout(timer);


    if (p.input.value.trim() === '') {

      render(root, []);
      return;
    }

    timer = window.setTimeout(function () {
      var url = root.getAttribute('data-picker-url')
              + '?q=' + encodeURIComponent(p.input.value);
      window.fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { rows: [] }; })
        .then(function (d) { render(root, (d && d.rows) || []); })
        .catch(function () {  });
    }, 160);
  }

  document.addEventListener('input', function (ev) {
    var root = ev.target.closest ? ev.target.closest('[data-picker]') : null;
    if (!root || !ev.target.hasAttribute('data-picker-input')) return;

    els(root).hidden.value = '';
    mark(root);
    search(root);
  });

  document.addEventListener('focusin', function (ev) {
    var root = ev.target.closest ? ev.target.closest('[data-picker]') : null;
    if (root && ev.target.hasAttribute('data-picker-input')) search(root);
    else if (OPEN && !ev.target.closest('[data-picker]')) close(OPEN);
  });

  document.addEventListener('click', function (ev) {
    var row = ev.target.closest ? ev.target.closest('.picker-row') : null;
    if (row) {
      var root = row.closest('[data-picker]');
      if (root) { ev.preventDefault();
                  choose(root, row.getAttribute('data-id'), row.getAttribute('data-name')); }
      return;
    }
    if (OPEN && !(ev.target.closest && ev.target.closest('[data-picker]'))) close(OPEN);
  });

  document.addEventListener('keydown', function (ev) {
    var root = ev.target.closest ? ev.target.closest('[data-picker]') : null;
    if (!root || !ev.target.hasAttribute('data-picker-input')) return;
    var p = els(root);
    if (!p.list || p.list.hidden) return;

    var rows = Array.prototype.slice.call(p.list.querySelectorAll('.picker-row'));
    if (!rows.length) return;
    var at = rows.findIndex(function (r) { return r.classList.contains('is-on'); });

    if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
      ev.preventDefault();
      if (at >= 0) rows[at].classList.remove('is-on');
      var next = ev.key === 'ArrowDown' ? at + 1 : at - 1;
      if (next < 0) next = rows.length - 1;
      if (next >= rows.length) next = 0;
      rows[next].classList.add('is-on');
      rows[next].scrollIntoView({ block: 'nearest' });
    } else if (ev.key === 'Enter') {

      if (at >= 0) {
        ev.preventDefault();
        choose(root, rows[at].getAttribute('data-id'), rows[at].getAttribute('data-name'));
      }
    } else if (ev.key === 'Escape') {
      close(root);
    }
  });

  function init() {
    document.querySelectorAll('[data-picker]').forEach(mark);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
}());
