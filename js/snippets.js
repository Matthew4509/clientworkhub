// SPDX-License-Identifier: PolyForm-Shield-1.0.0

(function () {
  'use strict';


  function append(existing, phrase) {
    var body = existing.replace(/\s+$/, '');
    if (body === '') return phrase;
    if (!/[.!?]$/.test(body)) body += '.';
    return body + ' ' + phrase;
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target;
    if (!(btn instanceof Element)) return;
    var phrase = btn.getAttribute('data-snip');
    if (phrase === null) return;

    ev.preventDefault();
    var group = btn.closest('[data-snip-target]');
    if (!group) return;
    var box = document.getElementById(group.getAttribute('data-snip-target'));
    if (!box) return;

    box.value = append(box.value, phrase);


    box.focus();
    box.setSelectionRange(box.value.length, box.value.length);
    box.scrollTop = box.scrollHeight;


    btn.classList.add('is-used');
  });


  function addTag(existing, tag) {
    var parts = [];
    var raw = existing.split(',');
    for (var i = 0; i < raw.length; i++) {
      var t = raw[i].replace(/^\s+|\s+$/g, '');
      if (t !== '') parts.push(t);
    }
    for (var j = 0; j < parts.length; j++) {
      if (parts[j].toLowerCase() === tag.toLowerCase()) return parts.join(', ');
    }
    parts.push(tag);
    return parts.join(', ');
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target;
    if (!(btn instanceof Element)) return;
    var tag = btn.getAttribute('data-tag');
    if (tag === null) return;

    ev.preventDefault();
    var group = btn.closest('[data-tag-target]');
    if (!group) return;
    var box = document.getElementById(group.getAttribute('data-tag-target'));
    if (!box) return;

    box.value = addTag(box.value, tag);
    box.focus();
    box.setSelectionRange(box.value.length, box.value.length);
    btn.classList.add('is-used');
  });
})();
