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

  var btns = document.querySelectorAll('[data-invoice-pdf]');
  var dataEl = document.getElementById('invoice-pdf-data');
  if (!btns.length || !dataEl) return;

  var SMART = { '—': '-', '–': '-', '−': '-', '‘': "'", '’': "'",
                '“': '"', '”': '"', '…': '...', '•': '-', '·': '-', ' ': ' ',
                ' ': ' ', ' ': ' ', '‑': '-', '′': "'", '″': '"' };
  function safe(s) {
    return String(s == null ? '' : s)
      .replace(/[—–−‘’“”…•·   ‑′″]/g, function (c) { return SMART[c]; })
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

    var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4' });
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

    var INK = [28, 28, 28], MUTED = [110, 110, 110], LINE = [205, 205, 205], SHADE = [247, 247, 245];
    var ACCENT = [30, 64, 175], GREEN = [34, 139, 82], RED = [200, 60, 60];
    var PW = pdf.internal.pageSize.getWidth(), PH = pdf.internal.pageSize.getHeight();
    var SB = 62;
    var ML = SB + 10, MR = PW - 14, W = MR - ML, BOTTOM = PH - 18;
    var sx = 10, sw = SB - 18;
    var setInk = function () { pdf.setTextColor(INK[0], INK[1], INK[2]); };
    var setMuted = function () { pdf.setTextColor(MUTED[0], MUTED[1], MUTED[2]); };
    var setRGB = function (c) { pdf.setTextColor(c[0], c[1], c[2]); };
    var font = function (style, size) { pdf.setFont('helvetica', style); pdf.setFontSize(size); };

    function sidebar() {
      pdf.setFillColor(SHADE[0], SHADE[1], SHADE[2]);
      pdf.rect(0, 0, SB, PH, 'F');
      var sy = 14;
      if (biz.logo) {
        try {
          var lw = sw, lh = 16;
          if (biz.logoW && biz.logoH) { var r = biz.logoW / biz.logoH; if (r >= lw / lh) lh = lw / r; else lw = lh * r; }
          pdf.addImage(biz.logo, biz.logoType || 'PNG', sx, sy, lw, lh, undefined, 'FAST');
          sy += lh + 6;
        } catch (e) { logoLost = true;  }
      }
      setInk(); font('bold', 13);
      pdf.splitTextToSize(biz.name || 'Your business', sw).forEach(function (l) { pdf.text(l, sx, sy); sy += 5.6; });
      sy += 1;
      font('normal', 8); setMuted();
      [biz.address, biz.contact, biz.reg].forEach(function (block) {
        if (!block) return;
        String(block).split(/\n|, /).forEach(function (l) {
          l = l.trim(); if (!l) return;
          pdf.splitTextToSize(l, sw).forEach(function (w) { pdf.text(w, sx, sy); sy += 4.2; });
        });
      });
      sy += 4;
      function block(label, value, color, big) {
        pdf.setDrawColor(224, 224, 220); pdf.setLineWidth(0.2);
        pdf.line(sx, sy, SB - 8, sy);
        sy += 5;
        font('bold', 7); setRGB([150, 150, 150]);
        pdf.text(String(label).toUpperCase(), sx, sy); sy += 4.5;
        font('bold', big ? 14 : 10); setRGB(color || INK);
        pdf.splitTextToSize(String(value), sw).forEach(function (w) { pdf.text(w, sx, sy); sy += big ? 6.2 : 5; });
        sy += 3;
      }
      (doc.facts || []).forEach(function (f) { block(f[0], f[1], /no\./i.test(f[0]) ? ACCENT : INK, false); });
      if (doc.headline && doc.headline[1]) {
        var hl = doc.headline[0];
        block(hl, doc.headline[1], /paid/i.test(hl) ? GREEN : (doc.status === 'OVERDUE' ? RED : ACCENT), true);
      }
      if (doc.payDetails) {
        pdf.setDrawColor(224, 224, 220); pdf.line(sx, sy, SB - 8, sy); sy += 5;
        font('bold', 7); setRGB([150, 150, 150]); pdf.text('HOW TO PAY', sx, sy); sy += 4.5;
        font('normal', 8); setInk();
        String(doc.payDetails).split('\n').forEach(function (l) {
          l = l.trim(); if (!l) return;
          pdf.splitTextToSize(l, sw).forEach(function (w) { pdf.text(w, sx, sy); sy += 4.2; });
        });
        sy += 2;
      }
      (doc.payLinks || []).forEach(function (pl) {
        if (!pl || !pl.url) return;
        font('bold', 8); setRGB(ACCENT);
        pdf.textWithLink(String(pl.label || 'Pay online'), sx, sy, { url: String(pl.url) });
        sy += 4.8;
      });
    }

    function newPage() { pdf.addPage(); sidebar(); return 18; }
    sidebar();
    var y = 18;
    setInk(); font('bold', 22);
    pdf.text(doc.title || 'INVOICE', ML, y + 6);
    var st = doc.status || '';
    if (st) {
      var stc = st === 'PAID' ? GREEN : (st === 'OVERDUE' ? RED : (st === 'VOID' ? MUTED : ACCENT));
      font('bold', 11); setRGB(stc);
      pdf.text(st, MR, y + 6, { align: 'right' });
    }
    y += 14;

    if (doc.billTo && doc.billTo.length) {
      font('bold', 7.5); setRGB([150, 150, 150]);
      pdf.text('BILLED TO', ML, y); y += 4.8;
      setInk(); font('normal', 10);
      doc.billTo.forEach(function (l, i) {
        if (i === 0) font('bold', 10); else font('normal', 9.5);
        pdf.splitTextToSize(String(l), W).forEach(function (w) { pdf.text(w, ML, y); y += 4.8; });
      });
      y += 6;
    }

    var wQty = 16, wUnit = 28, wAmt = 30, wDesc = W - wQty - wUnit - wAmt;
    function cellBox(x, yy, w, h, fill) {
      if (fill) { pdf.setFillColor(240, 240, 240); pdf.rect(x, yy, w, h, 'F'); }
      pdf.setDrawColor(LINE[0], LINE[1], LINE[2]); pdf.setLineWidth(0.2); pdf.rect(x, yy, w, h, 'S');
    }
    function thead() {
      font('bold', 8); setInk();
      cellBox(ML, y, wDesc, 8, true); pdf.text('DESCRIPTION', ML + 2, y + 5.3);
      cellBox(ML + wDesc, y, wQty, 8, true); pdf.text('QTY', ML + wDesc + wQty / 2, y + 5.3, { align: 'center' });
      cellBox(ML + wDesc + wQty, y, wUnit, 8, true); pdf.text('UNIT', ML + wDesc + wQty + wUnit - 2, y + 5.3, { align: 'right' });
      cellBox(ML + wDesc + wQty + wUnit, y, wAmt, 8, true); pdf.text('AMOUNT', MR - 2, y + 5.3, { align: 'right' });
      y += 8;
    }
    thead();
    font('normal', 9);
    (doc.items || []).forEach(function (it) {
      var lines = pdf.splitTextToSize(it.description || '', wDesc - 4);
      var h = Math.max(1, lines.length) * 4.6 + 3.4;
      if (y + h > BOTTOM) { y = newPage(); thead(); font('normal', 9); }
      setInk();
      cellBox(ML, y, wDesc, h, false); cellBox(ML + wDesc, y, wQty, h, false);
      cellBox(ML + wDesc + wQty, y, wUnit, h, false); cellBox(ML + wDesc + wQty + wUnit, y, wAmt, h, false);
      pdf.text(lines, ML + 2, y + 5);
      pdf.text(String(it.qty || ''), ML + wDesc + wQty / 2, y + 5, { align: 'center' });
      pdf.text(String(it.unit || ''), ML + wDesc + wQty + wUnit - 2, y + 5, { align: 'right' });
      pdf.text(String(it.amount || ''), MR - 2, y + 5, { align: 'right' });
      y += h;
    });
    if (!(doc.items && doc.items.length)) {
      setMuted(); font('normal', 9);
      cellBox(ML, y, W, 8, false); pdf.text('Nothing on this invoice yet.', ML + 2, y + 5.3); y += 8;
    }
    y += 6;

    var tx = ML + wDesc, tw = wQty + wUnit + wAmt;
    (doc.totals || []).forEach(function (row) {
      if (y + 7 > BOTTOM) { y = newPage(); }
      var kind = row[2] || '';
      var big = kind === 'total' || kind === 'paid' || kind === 'due';
      if (big) { pdf.setDrawColor(LINE[0], LINE[1], LINE[2]); pdf.setLineWidth(0.3); pdf.line(tx, y - 1, MR, y - 1); y += 2; }
      font(big ? 'bold' : 'normal', big ? 10.5 : 9);
      if (kind === 'paid') setRGB(GREEN); else if (kind === 'due') setRGB(doc.status === 'OVERDUE' ? RED : ACCENT); else if (big) setInk(); else setMuted();
      pdf.text(String(row[0]), tx + 2, y + 4);
      pdf.text(String(row[1]), MR - 2, y + 4, { align: 'right' });
      y += big ? 7.5 : 6;
    });
    y += 6;

    if (doc.notes) {
      if (y > BOTTOM - 20) y = newPage();
      font('bold', 7.5); setRGB([150, 150, 150]); pdf.text('NOTES', ML, y); y += 4.8;
      font('normal', 9); setInk();
      pdf.splitTextToSize(String(doc.notes), W).forEach(function (l) {
        if (y > BOTTOM) y = newPage();
        pdf.text(l, ML, y); y += 4.6;
      });
      y += 4;
    }

    var pages = pdf.internal.getNumberOfPages();
    for (var i = 1; i <= pages; i++) {
      pdf.setPage(i);
      font('normal', 8); setMuted();
      if (doc.footer) {
        pdf.splitTextToSize(String(doc.footer), W).slice(0, 2).forEach(function (l, k) { pdf.text(l, ML, PH - 14 + k * 4); });
      }
      pdf.text('Page ' + i + ' of ' + pages, MR, PH - 10, { align: 'right' });
    }

    pdf.save(doc.filename || 'invoice.pdf');
    note(clicked, logoLost ? LOGO_NOTE : '');
  }

  Array.prototype.forEach.call(btns, function (b) {
    b.addEventListener('click', function (ev) { ev.preventDefault(); build(b); });
  });
}());
