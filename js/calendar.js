// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  var WEEK_START = 1;
  var DOW = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                'August', 'September', 'October', 'November', 'December'];
  var LONG_DOW = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday',
                  'Friday', 'Saturday'];


  function iso(d) {
    var m = d.getMonth() + 1, day = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' + m : m) + '-' + (day < 10 ? '0' + day : day);
  }

  function parseMonth(s) {
    var p = String(s).split('-');
    return new Date(+p[0], +p[1] - 1, 1);
  }


  function render(cal, monthDate) {
    var input = cal.querySelector('[data-cal-input]');
    var selected = input ? input.value : '';
    var today = iso(new Date());
    var year = monthDate.getFullYear(), month = monthDate.getMonth();
    var first = new Date(year, month, 1);
    var daysIn = new Date(year, month + 1, 0).getDate();

    var dowN = first.getDay() === 0 ? 7 : first.getDay();
    var lead = (dowN - WEEK_START + 7) % 7;

    var head = document.createElement('div');
    head.className = 'cal-head';
    head.innerHTML =
      '<button class="cal-nav" type="button" data-cal-prev aria-label="Previous month">‹</button>'
      + '<span class="cal-title" data-cal-title></span>'
      + '<button class="cal-nav" type="button" data-cal-next aria-label="Next month">›</button>';
    head.querySelector('[data-cal-title]').textContent = MONTHS[month] + ' ' + year;

    var grid = document.createElement('div');
    grid.className = 'cal-grid';

    DOW.forEach(function (d) {
      var s = document.createElement('span');
      s.className = 'cal-dow';
      s.setAttribute('aria-hidden', 'true');
      s.textContent = d;
      grid.appendChild(s);
    });
    for (var i = 0; i < lead; i++) {
      var pad = document.createElement('span');
      pad.className = 'cal-pad';
      grid.appendChild(pad);
    }
    for (var day = 1; day <= daysIn; day++) {
      var date = new Date(year, month, day);
      var val = iso(date);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'cal-day'
                  + (val === selected ? ' is-selected' : '')
                  + (val === today ? ' is-today' : '');
      b.setAttribute('data-cal-day', val);
      b.setAttribute('aria-label',
        LONG_DOW[date.getDay()] + ' ' + day + ' ' + MONTHS[month] + ' ' + year);
      b.setAttribute('aria-pressed', val === selected ? 'true' : 'false');
      b.textContent = String(day);
      grid.appendChild(b);
    }


    var oldHead = cal.querySelector('.cal-head');
    var oldGrid = cal.querySelector('.cal-grid');
    if (oldHead) cal.replaceChild(head, oldHead); else cal.appendChild(head);
    if (oldGrid) cal.replaceChild(grid, oldGrid); else cal.appendChild(grid);
  }


  function shownMonth(cal) {
    var anyDay = cal.querySelector('[data-cal-day]');
    if (anyDay) return parseMonth(anyDay.getAttribute('data-cal-day').slice(0, 7));
    var input = cal.querySelector('[data-cal-input]');
    return parseMonth((input && input.value ? input.value : iso(new Date())).slice(0, 7));
  }

  function step(cal, by) {
    var m = shownMonth(cal);
    render(cal, new Date(m.getFullYear(), m.getMonth() + by, 1));
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target instanceof Element ? ev.target : null;
    if (!el) return;

    var day = el.closest('[data-cal-day]');
    if (day) {
      var cal = day.closest('[data-cal]');
      if (!cal) return;
      ev.preventDefault();
      var input = cal.querySelector('[data-cal-input]');
      if (input) input.value = day.getAttribute('data-cal-day');
      cal.querySelectorAll('.cal-day.is-selected').forEach(function (b) {
        b.classList.remove('is-selected');
        b.setAttribute('aria-pressed', 'false');
      });
      day.classList.add('is-selected');
      day.setAttribute('aria-pressed', 'true');
      return;
    }

    var prev = el.closest('[data-cal-prev]');
    if (prev) { ev.preventDefault(); step(prev.closest('[data-cal]'), -1); return; }

    var next = el.closest('[data-cal-next]');
    if (next) { ev.preventDefault(); step(next.closest('[data-cal]'), 1); }
  });
}());
