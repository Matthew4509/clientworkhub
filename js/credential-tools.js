// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';


  function strength(pw) {
    if (!pw) return -1;
    var lower = pw.toLowerCase();
    var bad = ['password', 'qwerty', 'letmein', '123456', 'abc123', 'iloveyou', 'admin', 'welcome'];
    for (var i = 0; i < bad.length; i++) {
      if (lower.indexOf(bad[i]) !== -1) return pw.length >= 20 ? 1 : 0;
    }
    if (/^\d+$/.test(pw)) return pw.length >= 16 ? 1 : 0;
    var classes = (/[a-z]/.test(pw) ? 1 : 0) + (/[A-Z]/.test(pw) ? 1 : 0)
                + (/\d/.test(pw) ? 1 : 0) + (/[^a-zA-Z\d]/.test(pw) ? 1 : 0);
    if (pw.length >= 16 && classes >= 3) return 3;
    if (pw.length >= 20) return 3;
    if (pw.length >= 12 && classes >= 3) return 2;
    if (pw.length >= 14) return 2;
    if (pw.length >= 10 && classes >= 2) return 1;
    return 0;
  }

  var LABELS = ['Weak', 'Fair', 'Good', 'Strong'];
  var COLOURS = ['#B91C1C', '#B45309', '#15803D', '#15803D'];

  function meterFor(input) {
    var meter = document.querySelector('[data-strength-for="' + input.id + '"]');
    if (!meter) return;
    var s = strength(input.value);
    if (s < 0) { meter.textContent = ''; return; }
    meter.textContent = LABELS[s];
    meter.style.color = COLOURS[s];
    meter.style.fontWeight = '600';
  }


  function generate() {
    var alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#%+=?';
    var out = '';
    var buf = new Uint32Array(20);
    (window.crypto || window.msCrypto).getRandomValues(buf);
    for (var i = 0; i < buf.length; i++) {
      out += alphabet[buf[i] % alphabet.length];
    }
    return out;
  }

  function copyText(text, button) {

    var done = function () {
      if (button.firstElementChild) {
        button.classList.add('is-copied');
        setTimeout(function () { button.classList.remove('is-copied'); }, 1500);
        return;
      }
      var was = button.textContent;
      button.textContent = 'Copied';
      setTimeout(function () { button.textContent = was; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
    } else {

      fallbackCopy(text, done);
    }
  }

  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {  }
    document.body.removeChild(ta);
  }


  function control(ev, attr) {
    var t = ev.target;
    if (!(t instanceof Element)) return null;
    return t.closest('[' + attr + ']');
  }


  function toggleLabel(btn, shown) {
    var word = shown ? 'Hide' : 'Show';
    btn.setAttribute('aria-label', word);
    btn.setAttribute('title', word);
    btn.setAttribute('aria-pressed', shown ? 'true' : 'false');
    if (btn.firstElementChild) btn.classList.toggle('is-shown', shown);
    else btn.textContent = word;
  }

  document.addEventListener('click', function (ev) {

    var gen = control(ev, 'data-generate-for');
    if (gen) {
      ev.preventDefault();
      var genFor = gen.getAttribute('data-generate-for');
      var input = document.getElementById(genFor);
      if (input) {
        input.value = generate();
        input.type = 'text';
        meterFor(input);
        var toggle = document.querySelector('[data-toggle-for="' + genFor + '"]');
        if (toggle) toggleLabel(toggle, true);
      }
      return;
    }

    var tog = control(ev, 'data-toggle-for');
    if (tog) {
      ev.preventDefault();
      var field = document.getElementById(tog.getAttribute('data-toggle-for'));
      if (field) {
        var hidden = field.type === 'password';
        field.type = hidden ? 'text' : 'password';
        toggleLabel(tog, hidden);
      }
      return;
    }

    var cp = control(ev, 'data-copy-for');
    if (cp) {
      ev.preventDefault();
      var src = document.getElementById(cp.getAttribute('data-copy-for'));
      if (src) copyText(src.value, cp);
    }
  });

  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t instanceof HTMLInputElement && t.hasAttribute('data-strength-meter')) {
      meterFor(t);
    }
  });


  function credConfig(el) {
    var host = el.closest('[data-cred-endpoint]');
    return host ? {url: host.getAttribute('data-cred-endpoint'),
                   csrf: host.getAttribute('data-cred-csrf')} : null;
  }


  function fetchCredential(assetId, el, onOk) {
    var cfg = credConfig(el);
    if (!cfg) return;
    var textual = !el.firstElementChild;
    var was = el.textContent;
    if (textual) el.textContent = '…'; else el.classList.add('is-busy');
    el.disabled = true;
    var body = new URLSearchParams();
    body.set('asset_id', assetId);
    fetch(cfg.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CM-CSRF': cfg.csrf},
      body: body.toString()
    }).then(function (r) { return r.json().catch(function () { return {ok: false, error: 'The server did not answer properly.'}; }); })
      .then(function (data) {
        el.disabled = false;
        el.classList.remove('is-busy');
        if (data && data.ok) { if (textual) el.textContent = was; onOk(data); }
        else {
          if (textual) el.textContent = 'Failed';
          showCredError(el, (data && data.error) || 'Could not read that password.');
          if (textual) setTimeout(function () { el.textContent = was; }, 2000);
        }
      })
      .catch(function () {
        el.disabled = false;
        el.classList.remove('is-busy');
        if (textual) el.textContent = was;
        showCredError(el, 'No answer from the server — are you still signed in?');
      });
  }


  function showCredError(el, message) {
    var slot = el.closest('.work-asset, .cred-line');
    if (!slot) { window.alert(message); return; }
    var box = slot.querySelector('.cred-msg');
    if (!box) {
      box = document.createElement('div');
      box.className = 'cred-msg small txt-err mt-4';
      slot.appendChild(box);
    }
    box.textContent = message;
  }

  document.addEventListener('click', function (ev) {
    var cc = control(ev, 'data-cred-copy');
    if (cc) {
      ev.preventDefault();
      fetchCredential(cc.getAttribute('data-cred-copy'), cc,
                      function (data) { copyText(data.password, cc); });
      return;
    }

    var t = control(ev, 'data-cred-show');
    if (t) {
      var showId = t.getAttribute('data-cred-show');
      ev.preventDefault();
      var field = document.getElementById('cred' + showId);
      if (!field) return;
      if (field.dataset.shown === '1') {
        field.value = field.dataset.mask || '••••••••••••';
        field.dataset.shown = '';
        toggleLabel(t, false);
        return;
      }
      fetchCredential(showId, t, function (data) {
        field.dataset.mask = field.value;
        field.value = data.password;
        field.dataset.shown = '1';
        toggleLabel(t, true);
        var notes = document.getElementById('crednotes' + showId);
        if (notes && data.notes) { notes.textContent = data.notes; notes.hidden = false; }
      });
    }
  });
})();
