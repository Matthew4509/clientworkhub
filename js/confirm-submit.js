// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  var dlg = null, msgEl = null, noBtn = null;
  var pendingForm = null, pendingSubmitter = null;


  function carrySubmitter(form, submitter) {
    if (!submitter || !submitter.name) return;
    var carry = document.createElement('input');
    carry.type = 'hidden';
    carry.name = submitter.name;
    carry.value = submitter.value;
    form.appendChild(carry);
  }


  function go(form) {
    form.dataset.cmConfirmed = '1';
    var taken = false;
    try {
      taken = !form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    } catch (err) {
      taken = false;
    }
    if (!taken) {
      delete form.dataset.cmConfirmed;
      HTMLFormElement.prototype.submit.call(form);
    }
  }


  function build() {
    dlg = document.createElement('dialog');
    dlg.className = 'modal';
    dlg.id = 'dlg-confirm';

    var box = document.createElement('div');
    box.className = 'modal-box';

    var head = document.createElement('div');
    head.className = 'modal-head';
    var h2 = document.createElement('h2');
    h2.textContent = 'Are you sure?';
    var x = document.createElement('button');
    x.type = 'button';
    x.className = 'modal-x';
    x.setAttribute('data-modal-close', '');
    x.setAttribute('aria-label', 'Close');
    x.innerHTML = '&times;';
    head.appendChild(h2);
    head.appendChild(x);

    msgEl = document.createElement('p');
    msgEl.className = 'prewrap';

    var actions = document.createElement('div');
    actions.className = 'actions';

    var yes = document.createElement('button');
    yes.type = 'button';
    yes.className = 'btn btn-danger';
    yes.textContent = 'Yes';

    noBtn = document.createElement('button');
    noBtn.type = 'button';
    noBtn.className = 'btn btn-quiet';
    noBtn.setAttribute('data-modal-close', '');
    noBtn.textContent = 'No';


    actions.appendChild(yes);
    actions.appendChild(noBtn);

    box.appendChild(head);
    box.appendChild(msgEl);
    box.appendChild(actions);
    dlg.appendChild(box);
    document.body.appendChild(dlg);

    yes.addEventListener('click', function () {
      var form = pendingForm, submitter = pendingSubmitter;
      pendingForm = null;
      pendingSubmitter = null;
      if (dlg.open) dlg.close();
      if (!form) return;
      carrySubmitter(form, submitter);
      go(form);
    });


    dlg.addEventListener('close', function () {
      pendingForm = null;
      pendingSubmitter = null;
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.nodeName !== 'FORM') return;
    if (form.dataset.cmConfirmed === '1') return;

    var msg = (e.submitter && e.submitter.getAttribute)
        ? e.submitter.getAttribute('data-confirm') : null;
    if (!msg) msg = form.getAttribute('data-confirm');


    if (!msg) {
      var selects = form.querySelectorAll('select');
      for (var i = 0; i < selects.length && !msg; i++) {
        var chosen = selects[i].options[selects[i].selectedIndex];
        if (chosen) msg = chosen.getAttribute('data-confirm');
      }
    }
    if (!msg) return;

    e.preventDefault();
    e.stopPropagation();

    if (!dlg) build();


    if (!dlg || typeof dlg.showModal !== 'function') {
      if (window.confirm(msg)) {
        carrySubmitter(form, e.submitter || null);
        form.submit();
      }
      return;
    }

    pendingForm = form;
    pendingSubmitter = e.submitter || null;
    msgEl.textContent = msg;
    if (!dlg.open) dlg.showModal();
    if (noBtn) {
      try { noBtn.focus(); } catch (err) {  }
    }
  }, true);
}());
