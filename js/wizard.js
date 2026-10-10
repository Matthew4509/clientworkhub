// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function panels(wiz) {
    return Array.prototype.slice.call(wiz.querySelectorAll('[data-step]'));
  }


  function steady(wiz) {
    if (!wiz.classList.contains('wiz-steady') || wiz.dataset.steadied === '1') return;

    var tallest = 0;
    panels(wiz).forEach(function (p) {
      var was = { hidden: p.hidden, display: p.style.display,
                  vis: p.style.visibility, pos: p.style.position };
      p.hidden = false;
      p.style.display = '';
      p.style.visibility = 'hidden';
      p.style.position = 'absolute';
      tallest = Math.max(tallest, p.getBoundingClientRect().height);
      p.hidden = was.hidden;
      p.style.display = was.display;
      p.style.visibility = was.vis;
      p.style.position = was.pos;
    });


    if (tallest > 0) {
      wiz.dataset.steadied = '1';
      panels(wiz).forEach(function (p) { p.style.minHeight = Math.ceil(tallest) + 'px'; });
    }
  }

  function show(wiz, index) {
    var steps = panels(wiz);
    if (!steps.length) return;
    index = Math.max(0, Math.min(index, steps.length - 1));
    steady(wiz);

    steps.forEach(function (p, i) {

      p.hidden = i !== index;
      p.style.display = i === index ? '' : 'none';
    });
    wiz.setAttribute('data-wizard-at', String(index));

    var title = steps[index].getAttribute('data-step-title') || '';
    var count = wiz.querySelector('[data-wizard-count]');
    if (count) {

      count.textContent = steps.length < 2 ? ''
        : 'Step ' + (index + 1) + ' of ' + steps.length + (title ? ' — ' + title : '');
    }

    var back = wiz.querySelector('[data-wizard-back]');
    var next = wiz.querySelector('[data-wizard-next]');
    var done = wiz.querySelector('[data-wizard-submit]');
    var last = index === steps.length - 1;
    if (back) back.style.display = index === 0 ? 'none' : '';
    if (next) next.style.display = last ? 'none' : '';
    if (done) done.style.display = last ? '' : 'none';

    var first = steps[index].querySelector(
      'input:not([type=hidden]):not([disabled]), select, textarea');
    if (first) { try { first.focus(); } catch (e) {} }


    var box = wiz.closest('.modal-box');
    if (box) box.scrollTop = 0;
  }

  function valid(wiz, index) {
    var step = panels(wiz)[index];
    if (!step) return true;
    var fields = step.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) {
      if (fields[i].willValidate && !fields[i].checkValidity()) {
        fields[i].reportValidity();
        return false;
      }
    }
    return true;
  }

  function at(wiz) {
    return parseInt(wiz.getAttribute('data-wizard-at') || '0', 10) || 0;
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target instanceof Element ? ev.target : null;
    if (!el) return;
    var btn = el.closest('[data-wizard-next], [data-wizard-back]');
    if (!btn) return;
    var wiz = btn.closest('[data-wizard]');
    if (!wiz) return;

    ev.preventDefault();
    if (btn.hasAttribute('data-wizard-back')) {
      show(wiz, at(wiz) - 1);
    } else if (valid(wiz, at(wiz))) {
      show(wiz, at(wiz) + 1);
    }
  });


  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    var el = ev.target instanceof Element ? ev.target : null;
    if (!el || el.tagName === 'TEXTAREA') return;
    var wiz = el.closest('[data-wizard]');
    if (!wiz) return;
    var steps = panels(wiz);
    if (at(wiz) >= steps.length - 1) return;
    ev.preventDefault();
    if (valid(wiz, at(wiz))) show(wiz, at(wiz) + 1);
  });

  function init() {
    document.querySelectorAll('[data-wizard]').forEach(function (wiz) {
      show(wiz, 0);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }


  document.addEventListener('click', function (ev) {
    var opener = ev.target instanceof Element
      ? ev.target.closest('[data-modal-open]') : null;
    if (!opener) return;
    var dlg = document.getElementById(opener.getAttribute('data-modal-open'));
    if (!dlg) return;
    window.setTimeout(function () {
      dlg.querySelectorAll('[data-wizard]').forEach(function (f) { show(f, 0); });
    }, 0);
  });
}());
