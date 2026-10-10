// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function templatesFor(form) {
    try { return JSON.parse(form.getAttribute('data-job-templates') || '{}'); }
    catch (e) { return {}; }
  }


  function touched(el) { return el.dataset.touched === '1'; }

  function apply(form, id) {
    var data = templatesFor(form)[String(id)] || null;
    var note = form.querySelector('[data-job-note]');
    var anyUnset = false;

    form.querySelectorAll('[data-job-field]').forEach(function (el) {
      var key = el.getAttribute('data-job-field');
      var val = data ? (data[key] || '') : '';


      if (el.type === 'radio') {
        if (!touched(el)) {
          var spans = data ? (data.spans || '') : '';
          el.checked = (key === 'spans') === (spans !== '');
        }
        return;
      }

      if (!touched(el)) {
        if (el.tagName === 'SELECT') {
          el.value = val || 'oneoff';
        } else {
          el.value = val;
        }
      }


      var unset = data !== null && val === '' && key !== 'name' && !touched(el);
      el.classList.toggle('is-unset', unset);
      if (unset) anyUnset = true;
    });

    if (note) note.hidden = !anyUnset;
    showTasks(form, data);


    var daysBox = form.querySelector('[data-avg-days]');
    if (daysBox) average(daysBox);
  }


  function showTasks(form, data) {
    var box  = form.querySelector('[data-job-tasks]');
    if (!box) return;
    var list = box.querySelector('[data-job-tasks-list]');
    var note = box.querySelector('[data-job-tasks-note]');
    list.innerHTML = '';


    box.hidden = false;

    if (!data) {
      note.textContent = 'Pick a service above and its tasks appear here.';
      return;
    }

    var tasks = data.tasks || [];
    if (!tasks.length) {
      note.textContent = 'This service has no tasks yet, so the job starts empty. '
                       + 'Add them on the service and every job stamped from it gets them.';
      return;
    }

    tasks.forEach(function (t) {
      var li = document.createElement('li');
      var b = document.createElement('b');
      b.textContent = t.name;
      li.appendChild(b);
      var bits = [];
      if (t.freq) bits.push(t.freq);
      if (t.mins) bits.push(t.mins);
      if (bits.length) {
        var s = document.createElement('span');
        s.className = 'muted small';

        s.textContent = bits.join(' · ');
        li.appendChild(s);
      }
      list.appendChild(li);
    });
    note.textContent = tasks.length + (tasks.length === 1 ? ' task' : ' tasks')
                     + ' will be created with the job, from today.';
  }

  document.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t && t.hasAttribute && t.hasAttribute('data-job-template')) {
      var form = t.closest('.job-form');
      if (form) apply(form, t.value);
    }
  });


  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t && t.hasAttribute && t.hasAttribute('data-job-field')) {
      t.dataset.touched = '1';
      t.classList.remove('is-unset');
    }
  });


  function twin(el) {
    var id = el.getAttribute('data-exclusive-with');
    return id ? document.getElementById(id) : null;
  }

  function settle(el) {
    var other = twin(el);
    if (!other) return;
    if (el.value !== '') {
      if (other.value !== '') other.value = '';
      other.disabled = true;
    } else if (!el.disabled) {
      other.disabled = false;
    }

    average(el);
  }

  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t && t.hasAttribute && t.hasAttribute('data-exclusive-with')) settle(t);
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-exclusive-with]').forEach(function (el) {
      var other = twin(el);
      if (other && el.value !== '' && other.value === '') settle(el);
    });
    document.querySelectorAll('[data-span-end]').forEach(spanDays);
    document.querySelectorAll('[data-shows]').forEach(function (g) {
      reveal(g);
      lockDays(g);
    });
  });


  function reveal(group) {
    var key = group.getAttribute('data-shows');
    var picked = group.querySelector('input[type=radio]:checked');
    if (!key || !picked) return;

    document.querySelectorAll('[data-when="' + key + '"]').forEach(function (block) {
      var on = block.getAttribute('data-when-value') === picked.value;


      if (block.getAttribute('data-when-effect') === 'dim') {
        block.classList.toggle('is-dimmed', !on);
      } else {
        block.hidden = !on;
        block.style.display = on ? '' : 'none';
      }
      block.querySelectorAll('input, select, textarea').forEach(function (f) {
        f.disabled = !on;
      });

      if (on) block.querySelectorAll('[data-exclusive-with]').forEach(settle);
    });
  }


  function lockDays(group) {
    var picked = group.querySelector('input[type=radio]:checked');
    if (!picked) return;
    var scope = group.closest('form') || document;
    var days = scope.querySelector('[data-avg-days]');
    if (!days) return;

    if (picked.value === 'oneoff') {
      if (!days.readOnly) days.dataset.spanDays = days.value;
      days.value = '1';
      days.readOnly = true;
    } else if (days.readOnly) {
      days.readOnly = false;
      days.value = days.dataset.spanDays || '';
      delete days.dataset.spanDays;
    }
    average(days);
  }


  function spanDays(within) {
    var scope = (within && within.closest && within.closest('form')) || document;
    var start = scope.querySelector('[data-span-start]');
    var end   = scope.querySelector('[data-span-end]');
    var days  = scope.querySelector('[data-avg-days]');
    if (!start || !end || !days) return;

    var a = start.value, b = end.value;
    if (a && b) {
      var ms = Date.UTC(+b.slice(0, 4), +b.slice(5, 7) - 1, +b.slice(8, 10))
             - Date.UTC(+a.slice(0, 4), +a.slice(5, 7) - 1, +a.slice(8, 10));
      var n = Math.floor(ms / 86400000) + 1;
      if (n >= 1) {
        days.value = String(n);
        days.readOnly = true;
        days.dataset.derived = '1';
        days.classList.add('f-derived');
        average(days);
        return;
      }

    }
    if (days.dataset.derived) {
      delete days.dataset.derived;
      days.readOnly = false;
      days.classList.remove('f-derived');
      average(days);
    }
  }

  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t && t.hasAttribute
        && (t.hasAttribute('data-span-start') || t.hasAttribute('data-span-end'))) {
      spanDays(t);
    }
  });

  document.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t && t.hasAttribute
        && (t.hasAttribute('data-span-start') || t.hasAttribute('data-span-end'))) {
      spanDays(t);
      return;
    }
    if (!t || t.type !== 'radio') return;
    var group = t.closest('[data-shows]');
    if (group) { reveal(group); lockDays(group); spanDays(group); }
  });



  function average(within) {
    var scope = (within && within.closest && within.closest('form')) || document;
    var out = scope.querySelector('[data-avg-out]');
    if (!out) return;
    var d = scope.querySelector('[data-avg-days]');
    var t = scope.querySelector('[data-avg-total]');
    var days = d ? parseFloat(d.value) : 0;
    var hours = t ? parseFloat(t.value) : 0;

    if (!(days > 0) || !(hours > 0)) { out.value = ''; return; }

    var mins = Math.round((hours * 60) / days);
    out.value = Math.floor(mins / 60) + 'h ' + ('0' + (mins % 60)).slice(-2) + 'm / day';
  }

  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t && t.hasAttribute && (t.hasAttribute('data-avg-days') || t.hasAttribute('data-avg-total'))) {
      average(t);
    }
  });
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-avg-out]').forEach(average);
  });
})();
