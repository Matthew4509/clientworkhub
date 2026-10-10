// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  var timer = null;

  function fieldsFor(out) {
    var form = out.closest('form');
    if (!form) return null;
    return {
      start:    form.querySelector('[data-eta-start]'),
      days:     form.querySelector('[data-eta-days]'),
      end:      form.querySelector('[data-eta-end]'),
      perday:   form.querySelector('[data-eta-perday]'),
      template: form.querySelector('[data-eta-template]')
    };
  }

  function show(out, html) {
    out.innerHTML = html;
    out.hidden = html === '';
  }


  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
    });
  }

  function refresh(out) {
    var f = fieldsFor(out);
    if (!f || !f.start) return;


    if (f.end && f.end.value !== '') {
      show(out, 'Ends on the date you fixed: <b>' + esc(f.end.value) + '</b>.');
      return;
    }

    var days = f.days ? f.days.value.trim() : '';
    var tpl  = f.template ? f.template.value : '0';
    if (days === '' && (tpl === '0' || tpl === '')) { show(out, ''); return; }

    var url = out.getAttribute('data-eta-url')
            + '?start=' + encodeURIComponent(f.start.value)
            + '&days=' + encodeURIComponent(days)
            + '&perday=' + encodeURIComponent(f.perday ? f.perday.value.trim() : '')
            + '&template=' + encodeURIComponent(tpl);

    window.fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : { ok: false }; })
      .then(function (d) {
        if (!d || !d.ok) { show(out, ''); return; }
        if (d.unplannable) {
          show(out, 'Target completion: <b>' + esc(d.plain_label) + '</b> by the calendar &mdash; '
                  + esc(d.note));
          return;
        }
        var line = 'Target completion: <b>' + esc(d.plain_label) + '</b> by the calendar'
                 + ' &middot; <b>' + esc(d.nominal_label) + '</b> across your working days';
        if (d.pushed) {
          line += ' &middot; with what is already booked, more like <b>'
                + esc(d.loaded_label) + '</b>';
          if (d.note !== '') line += ' <span class="muted">&mdash; ' + esc(d.note) + '</span>';
        }
        show(out, line);
      })
      .catch(function () { show(out, '');  });
  }

  function schedule(out) {
    window.clearTimeout(timer);
    timer = window.setTimeout(function () { refresh(out); }, 250);
  }

  function outFor(el) {
    var form = el.closest ? el.closest('form') : null;
    return form ? form.querySelector('[data-eta-out]') : null;
  }

  ['input', 'change'].forEach(function (kind) {
    document.addEventListener(kind, function (ev) {
      var t = ev.target;
      if (!(t instanceof Element)) return;
      if (t.hasAttribute('data-eta-start') || t.hasAttribute('data-eta-days')
       || t.hasAttribute('data-eta-end') || t.hasAttribute('data-eta-perday')
       || t.hasAttribute('data-eta-template')) {
        var out = outFor(t);
        if (out) schedule(out);
      }
    });
  });


  document.addEventListener('click', function (ev) {
    var o = ev.target instanceof Element ? ev.target.closest('[data-modal-open]') : null;
    if (!o) return;
    var dlg = document.getElementById(o.getAttribute('data-modal-open'));
    if (!dlg) return;
    var out = dlg.querySelector('[data-eta-out]');
    if (out) window.setTimeout(function () { refresh(out); }, 0);
  });
}());
