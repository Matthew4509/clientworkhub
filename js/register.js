// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';

  function symbolFor(form) {
    return (form && form.getAttribute('data-currency')) || '$';
  }


  function money(form, amount) {
    var neg = amount < 0;
    var v = Math.abs(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (neg ? '-' : '') + symbolFor(form) + v;
  }

  function num(el) {
    if (!el) return 0;

    var raw = String(el.value || '').trim().replace(/^[^\d.-]+/, '').replace(/,/g, '');
    var n = parseFloat(raw);
    return isFinite(n) ? n : 0;
  }

  function text(el, s) { if (el) el.textContent = s; }


  function customerLabel(form) {
    var id = form.querySelector('[name=client_id]');
    var picker = form.querySelector('[data-picker-input]');
    if (id && String(id.value || '').trim() !== '' && picker && picker.value.trim() !== '') {
      return picker.value.trim();
    }
    return 'Walk-in';
  }

  function update(form) {
    var method = form.querySelector('[name=payment_method]');
    if (!method) return;

    var isCash = method.value === 'cash';
    var cash   = form.querySelector('[data-cash-fields]');
    var total  = num(form.querySelector('[name=total]'));
    var got    = num(form.querySelector('[name=amount_received]'));
    var change = Math.max(0, got - total);

    if (cash) cash.hidden = !isCash;

    text(form.querySelector('[data-change]'), money(form, change));

    var methodLabel = method.options[method.selectedIndex]
        ? method.options[method.selectedIndex].text : method.value;
    var who = customerLabel(form);
    text(form.querySelector('[data-review-total]'),  money(form, total));
    text(form.querySelector('[data-review-method]'), methodLabel);
    text(form.querySelector('[data-review-client]'), who);
    text(form.querySelector('[data-review-change]'), money(form, change));

    var changeWrap = form.querySelector('[data-review-change-wrap]');
    if (changeWrap) changeWrap.hidden = !isCash;


    if (total > 0) {
      form.setAttribute('data-confirm',
        'Complete this sale?\n\n'
        + money(form, total) + ' by ' + methodLabel + ', ' + who + '.'
        + (isCash && change > 0 ? '\nChange due: ' + money(form, change) + '.' : '')
        + '\n\nThis creates a receipt straight away. A mistake can only be '
        + 'corrected with a refund, and both stay on the record.');
    } else {
      form.removeAttribute('data-confirm');
    }
  }

  document.addEventListener('input',  function (e) {
    var f = e.target.closest('[data-register-form]'); if (f) update(f);
  });
  document.addEventListener('change', function (e) {
    var f = e.target.closest('[data-register-form]'); if (f) update(f);
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-print]')) { window.print(); return; }
    var f = e.target.closest('[data-register-form]');
    if (f) window.setTimeout(function () { update(f); }, 0);
  });

  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('[data-register-form]');
    for (var i = 0; i < forms.length; i++) update(forms[i]);
  });
}());
