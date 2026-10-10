// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';


  function note(anchor, text) {
    var id = anchor.getAttribute('data-pdf-note-id');
    var el = id ? document.getElementById(id) : null;
    if (!text) { if (el) el.parentNode.removeChild(el); anchor.removeAttribute('data-pdf-note-id'); return; }
    if (!el) {
      el = document.createElement('p');
      el.className = 'pdf-note';
      el.setAttribute('role', 'status');
      el.id = 'pdf-note-' + Math.random().toString(36).slice(2);
      anchor.setAttribute('data-pdf-note-id', el.id);
      anchor.insertAdjacentElement('afterend', el);
    }
    el.textContent = text;
  }
  var LOGO_NOTE = 'Your logo could not be drawn in the PDF, so it was left out. Upload it again in Settings.';
  var DATA_NOTE = 'The PDF could not be made: this page\'s data did not read correctly. Reload the page and try again.';

  var btn = document.querySelector('[data-summary-pdf]');
  var dataEl = document.getElementById('summary-pdf-data');
  if (!btn || !dataEl) return;

  var SMART = { '—': '-', '–': '-', '−': '-', '‘': "'", '’': "'",
                '“': '"', '”': '"', '…': '...', '•': '-', ' ': ' ',
                ' ': ' ', ' ': ' ', '‑': '-', '′': "'", '″': '"' };
  function safe(s) {
    return String(s == null ? '' : s)
      .replace(/[—–−‘’“”…•   ‑′″]/g, function (c) { return SMART[c]; })
      .replace(/[^\x09\x0a\x0d\x20-\xff]/g, '');
  }

  function build(clicked) {
    if (typeof window.jspdf === 'undefined') {
      window.alert('The PDF library is still loading — try again in a second.');
      return;
    }
    var data;
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { note(clicked, DATA_NOTE); return; }
    var logoLost = false;
    var biz = data.business || {}, doc = data.doc || {};

    var jsPDF = window.jspdf.jsPDF;
    var pdf = new jsPDF({ unit: 'mm', format: 'a4' });
    var _text = pdf.text.bind(pdf);
    pdf.text = function (txt) {
      var rest = Array.prototype.slice.call(arguments, 1);
      return _text.apply(null, [Array.isArray(txt) ? txt.map(safe) : safe(txt)].concat(rest));
    };
    var _split = pdf.splitTextToSize.bind(pdf);
    pdf.splitTextToSize = function (txt) {
      var rest = Array.prototype.slice.call(arguments, 1);
      return _split.apply(null, [safe(txt)].concat(rest));
    };

    var INK = [20, 20, 20], MUTED = [110, 110, 110], LINE = [205, 205, 205], SHADE = [240, 240, 240];
    var PH = pdf.internal.pageSize.getHeight(), PW = pdf.internal.pageSize.getWidth();
    var ML = 15, MR = PW - 15, W = MR - ML, BOTTOM = PH - 18;
    var y = 18;
    var setInk = function () { pdf.setTextColor(INK[0], INK[1], INK[2]); };
    var setMuted = function () { pdf.setTextColor(MUTED[0], MUTED[1], MUTED[2]); };
    var font = function (style, size) { pdf.setFont('helvetica', style); pdf.setFontSize(size); };

    if (biz.logo) {
      try {
        var lw = 40, lh = 16;
        if (biz.logoW && biz.logoH) {
          var r = biz.logoW / biz.logoH;
          if (r >= lw / lh) lh = lw / r; else lw = lh * r;
        }
        pdf.addImage(biz.logo, biz.logoType || 'PNG', ML, y, lw, lh, undefined, 'FAST');
        y += lh + 4;
      } catch (e) { logoLost = true;  }
    }

    setInk(); font('bold', 18);
    pdf.text(biz.name || 'Your business', ML, y + 6);
    font('bold', 14);
    pdf.text(doc.title || 'Work summary', MR, y + 6, { align: 'right' });
    y += 12;
    font('normal', 9); setMuted();
    if (doc.date) pdf.text(doc.date, MR, y + 4, { align: 'right' });
    var addrLines = (biz.lines || []).filter(Boolean);
    addrLines.forEach(function (l) { pdf.text(l, ML, y + 4); y += 4.6; });
    if (!addrLines.length) y += 4.6;
    y += 8;
    pdf.setDrawColor(LINE[0], LINE[1], LINE[2]); pdf.setLineWidth(0.3);
    pdf.line(ML, y, MR, y);
    y += 8;

    var top = y;
    setInk(); font('bold', 9);
    pdf.text('Prepared for', ML, y);
    var ly = y + 5.5;
    font('normal', 10);
    (doc.forLines || []).forEach(function (l) {
      pdf.splitTextToSize(l, 90).forEach(function (w) { pdf.text(w, ML, ly); ly += 4.8; });
    });

    var my = top;
    font('normal', 9);
    var widest = 0;
    (doc.facts || []).forEach(function (f) { widest = Math.max(widest, pdf.getTextWidth(safe(String(f[1])))); });
    var labelX = MR - widest - 6;
    (doc.facts || []).forEach(function (f) {
      font('bold', 9); setInk();
      pdf.text(f[0], labelX, my + 4, { align: 'right' });
      font('normal', 9); setMuted();
      pdf.text(String(f[1]), MR, my + 4, { align: 'right' });
      my += 5.6;
    });
    y = Math.max(ly, my) + 8;

    if (doc.description) {
      setInk(); font('bold', 9);
      pdf.text('The job', ML, y); y += 5;
      font('normal', 9.5); setMuted();
      pdf.splitTextToSize(doc.description, W).forEach(function (l) {
        if (y > BOTTOM) { pdf.addPage(); y = 20; }
        pdf.text(l, ML, y); y += 4.8;
      });
      y += 6;
    }

    var wDate = 24, wTask = 42, wNote = W - wDate - wTask;
    function cellBox(x, yy, w, h, fill) {
      if (fill) { pdf.setFillColor(SHADE[0], SHADE[1], SHADE[2]); pdf.rect(x, yy, w, h, 'F'); }
      pdf.setDrawColor(LINE[0], LINE[1], LINE[2]); pdf.setLineWidth(0.2);
      pdf.rect(x, yy, w, h, 'S');
    }
    function thead() {
      font('bold', 8.5); setInk();
      cellBox(ML, y, wDate, 8, true);          pdf.text('DATE', ML + 2, y + 5.2);
      cellBox(ML + wDate, y, wTask, 8, true);  pdf.text('TASK', ML + wDate + 2, y + 5.2);
      cellBox(ML + wDate + wTask, y, wNote, 8, true); pdf.text('WHAT WAS DONE', ML + wDate + wTask + 2, y + 5.2);
      y += 8;
    }
    setInk(); font('bold', 9);
    pdf.text('Work completed' + (doc.rows && doc.rows.length ? ' (' + doc.rows.length + ')' : ''), ML, y);
    y += 5;
    thead();
    font('normal', 9);
    (doc.rows || []).forEach(function (row) {
      var taskLines = pdf.splitTextToSize(row.task || '', wTask - 4);
      var noteText = (row.note || '') + (row.url ? (row.note ? '\n' : '') + row.url : '');
      var noteLines = noteText ? pdf.splitTextToSize(noteText, wNote - 4) : ['-'];
      var lines = Math.max(1, taskLines.length, noteLines.length);
      var h = lines * 4.6 + 3.4;
      if (y + h > BOTTOM) { pdf.addPage(); y = 20; thead(); font('normal', 9); }
      setInk();
      cellBox(ML, y, wDate, h, false);
      cellBox(ML + wDate, y, wTask, h, false);
      cellBox(ML + wDate + wTask, y, wNote, h, false);
      pdf.text(row.date || '', ML + 2, y + 5);
      pdf.text(taskLines, ML + wDate + 2, y + 5);
      pdf.text(noteLines, ML + wDate + wTask + 2, y + 5);
      y += h;
    });
    if (!(doc.rows && doc.rows.length)) {
      setMuted(); font('normal', 9);
      cellBox(ML, y, W, 8, false);
      pdf.text('Nothing completed yet.', ML + 2, y + 5.2);
      y += 8;
    }
    y += 8;

    if (doc.timeSpent) {
      if (y > BOTTOM - 10) { pdf.addPage(); y = 20; }
      font('normal', 9);
      var tsX = MR - pdf.getTextWidth(safe(doc.timeSpent)) - 6;
      setInk(); font('bold', 9);
      pdf.text('Time spent', tsX, y, { align: 'right' });
      font('normal', 9); setMuted();
      pdf.text(doc.timeSpent, MR, y, { align: 'right' });
      y += 8;
    }

    if (y > BOTTOM - 14) { pdf.addPage(); y = 20; }
    setInk(); font('normal', 10);
    pdf.text('- ' + (biz.name || ''), ML, y + 4);

    var pages = pdf.internal.getNumberOfPages();
    for (var i = 1; i <= pages; i++) {
      pdf.setPage(i);
      font('normal', 8); setMuted();
      var foot = [biz.name, biz.phone, biz.email, biz.website].filter(Boolean).join('  ·  ');
      pdf.text(foot, ML, PH - 10);
      pdf.text('Page ' + i + ' of ' + pages, MR, PH - 10, { align: 'right' });
    }

    pdf.save(doc.filename || 'work-summary.pdf');
    note(clicked, logoLost ? LOGO_NOTE : '');
  }

  btn.addEventListener('click', function (ev) { ev.preventDefault(); build(btn); });
}());
