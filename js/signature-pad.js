// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';
  var canvas = document.getElementById('sig');
  var store = document.getElementById('sig-data');
  var clearBtn = document.getElementById('sig-clear');
  if (!canvas || !store || !canvas.getContext) return;

  var drawing = false, lastX = 0, lastY = 0, dirty = store.value !== '';

  function size() {
    var ratio = window.devicePixelRatio || 1;
    var w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = Math.max(1, Math.floor(w * ratio));
    canvas.height = Math.max(1, Math.floor(h * ratio));
    var ctx = canvas.getContext('2d');
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1c1917';
    if (store.value) {
      var img = new Image();
      img.onload = function () { ctx.drawImage(img, 0, 0, w, h); };
      img.src = store.value;
    }
  }
  size();
  window.addEventListener('resize', size);

  function ctx() { return canvas.getContext('2d'); }
  function pos(e) {
    var rect = canvas.getBoundingClientRect();
    var t = (e.touches && e.touches[0]) ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  }
  function start(e) { e.preventDefault(); drawing = true; var p = pos(e); lastX = p.x; lastY = p.y; }
  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = pos(e), c = ctx();
    c.beginPath(); c.moveTo(lastX, lastY); c.lineTo(p.x, p.y); c.stroke();
    lastX = p.x; lastY = p.y; dirty = true;
  }
  function end() {
    if (!drawing) return;
    drawing = false;
    if (dirty) store.value = canvas.toDataURL('image/png');
  }
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      ctx().clearRect(0, 0, canvas.width, canvas.height);
      store.value = '';
      dirty = false;
    });
  }
}());
