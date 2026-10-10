// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';


  var STAGE_MS = 900;
  var DONE_MS = 700;

  var SAVE_MS = 600;

  function sleep(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
  }


  function rawSubmit(form) {
    HTMLFormElement.prototype.submit.call(form);
  }

  function markSaving(form) {
    var btn = form.querySelector('button[type="submit"], button:not([type])');
    if (!btn) return null;
    var original = btn.textContent;
    btn.textContent = btn.getAttribute('data-saving-label') || 'Saving…';

    btn.setAttribute('aria-busy', 'true');
    btn.classList.add('is-busy');
    return function restore() {
      btn.textContent = original;
      btn.removeAttribute('aria-busy');
      btn.classList.remove('is-busy');
    };
  }

  function buildOverlay(stages, title) {
    var wrap = document.createElement('div');
    wrap.className = 'pd-progress';

    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-live', 'polite');

    var card = document.createElement('div');
    card.className = 'pd-progress-card';

    var h = document.createElement('h2');
    h.textContent = title || 'Working…';
    card.appendChild(h);

    var list = document.createElement('ol');
    list.className = 'pd-progress-steps';
    var items = stages.map(function (label) {
      var li = document.createElement('li');
      li.textContent = label;
      list.appendChild(li);
      return li;
    });
    card.appendChild(list);

    var note = document.createElement('p');
    note.className = 'pd-progress-note';
    note.textContent = 'Do not close this window.';
    card.appendChild(note);

    wrap.appendChild(card);
    document.body.appendChild(wrap);
    return { wrap: wrap, items: items };
  }


  function showRefusal(ui, status, type, body) {
    var failedAt = 0;
    for (var n = 0; n < ui.items.length; n++) {
      if (ui.items[n].className === 'is-now') failedAt = n;
    }
    for (var m = 0; m < ui.items.length; m++) {
      ui.items[m].className = m < failedAt ? 'is-done' : (m === failedAt ? 'is-failed' : '');
    }
    var last = ui.items[ui.items.length - 1];
    if (last && last.parentNode) last.parentNode.removeChild(last);
    var card = ui.wrap.firstChild;
    var h = card.querySelector('h2');
    if (h) h.textContent = 'That did not complete';
    var said = /^text\/plain/i.test(type) ? String(body || '').replace(/\s+/g, ' ').trim().slice(0, 300) : '';
    var note = card.querySelector('.pd-progress-note');
    note.className = 'pd-progress-note pd-progress-error';
    note.textContent = (said !== '' ? said : 'The server refused the request (HTTP ' + status + ').')
      + ' Nothing was reported as done. Close this and try again; if it happens twice, check Update & backup.';
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn';
    close.textContent = 'Close';
    close.addEventListener('click', function () {
      window.location.reload();
    });
    card.appendChild(close);
    close.focus();
  }

  function advance(items, i) {
    for (var n = 0; n < items.length; n++) {
      items[n].className = n < i ? 'is-done' : (n === i ? 'is-now' : '');
    }
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.nodeName !== 'FORM') return;

    if (form.hasAttribute('data-saving') && !form.hasAttribute('data-progress')) {
      if (form.dataset.pdGo === '1') return;
      e.preventDefault();
      markSaving(form);
      setTimeout(function () {
        form.dataset.pdGo = '1';
        rawSubmit(form);
      }, SAVE_MS);
      return;
    }

    var spec = form.getAttribute('data-progress');
    if (!spec) return;
    if (form.dataset.pdGo === '1') return;


    if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') return;

    e.preventDefault();

    var stages = spec.split('|');
    var doneLabel = form.getAttribute('data-progress-done') || 'Completed successfully';
    var ui = buildOverlay(stages.concat([doneLabel]), form.getAttribute('data-progress-title'));


    var perStage = STAGE_MS;
    var totalSec = parseFloat(form.getAttribute('data-progress-seconds') || '');
    if (totalSec > 0) {
      perStage = Math.max(STAGE_MS,
        Math.round((totalSec * 1000 - DONE_MS) / Math.max(1, stages.length)));
    }


    var data = new FormData(form);
    var submitter = e.submitter;
    if (submitter && submitter.name) data.append(submitter.name, submitter.value || '');

    var started = Date.now();
    advance(ui.items, 0);


    var action = form.getAttribute('action');
    var method = form.getAttribute('method');
    var target = action && action !== '' ? action : window.location.href;


    var request = fetch(target, {
      method: (method || 'POST').toUpperCase(),
      body: data,
      credentials: 'same-origin',
      redirect: 'manual'
    });


    sleep(perStage).then(function () {
      if (stages.length > 1) advance(ui.items, 1);
    });

    request.then(function (res) {

      var refused = res && res.type !== 'opaqueredirect' && (res.status < 200 || res.status >= 400);
      if (refused) {
        return res.text().catch(function () { return ''; }).then(function (body) {
          showRefusal(ui, res.status, res.headers.get('Content-Type') || '', body);
        });
      }

      var elapsed = Date.now() - started;
      var minimum = perStage * Math.max(1, stages.length - 1);
      return sleep(Math.max(0, minimum - elapsed)).then(function () {
        if (stages.length > 2) advance(ui.items, stages.length - 1);
        return sleep(perStage);
      }).then(function () {
        advance(ui.items, stages.length);
        return sleep(DONE_MS);
      }).then(function () {

        window.location.href = (res && res.url) ? res.url : target;
      });
    }).catch(function () {

      if (ui.wrap.parentNode) ui.wrap.parentNode.removeChild(ui.wrap);
      form.dataset.pdGo = '1';
      rawSubmit(form);
    });
  }, false);
}());
