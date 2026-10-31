// PagesDoc: theme, navigation drawer, copy buttons, table of contents highlight and search.
(function () {
  'use strict';

  var root = document.documentElement;
  var body = document.body;
  var $$ = function (selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); };

  // ---------------------------------------------------------------- theme
  function isDark() {
    var chosen = root.getAttribute('data-theme');
    return chosen ? chosen === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
  $$('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var theme = isDark() ? 'light' : 'dark';
      root.setAttribute('data-theme', theme);
      try { localStorage.setItem('pagesdoc-theme', theme); } catch (e) { /* private mode: the choice lasts for this page only */ }
    });
  });

  // ---------------------------------------------------------------- navigation drawer (small screens)
  $$('[data-open-nav]').forEach(function (b) { b.addEventListener('click', function () { body.classList.add('nav-open'); }); });
  $$('[data-close-nav]').forEach(function (b) { b.addEventListener('click', function () { body.classList.remove('nav-open'); }); });
  $$('.sidenav a').forEach(function (a) { a.addEventListener('click', function () { body.classList.remove('nav-open'); }); });

  // ---------------------------------------------------------------- print
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  // ---------------------------------------------------------------- copy code
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      body.appendChild(area);
      area.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
      body.removeChild(area);
    });
  }
  $$('[data-copy]').forEach(function (button) {
    var label = button.querySelector('span');
    button.addEventListener('click', function () {
      var code = button.closest('.code').querySelector('pre code');
      copyText(code.textContent.replace(/\n$/, '')).then(function () {
        button.classList.add('done');
        label.textContent = 'تم النسخ';
        clearTimeout(button._t);
        button._t = setTimeout(function () { button.classList.remove('done'); label.textContent = 'نسخ'; }, 1800);
      }, function () { label.textContent = 'تعذّر النسخ'; });
    });
  });

  // ---------------------------------------------------------------- table of contents: the section being read
  var tocLinks = $$('.toc .toc-list a, .toc-mobile .toc-list a');
  var headings = [];
  $$('.toc .toc-list a').forEach(function (a) {
    var el = document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));
    if (el) headings.push(el);
  });
  var activeId = null;
  function markActive() {
    var line = (parseInt(getComputedStyle(root).getPropertyValue('--top-h'), 10) || 66) + 40;
    var current = null;
    for (var i = 0; i < headings.length; i++) {
      if (headings[i].getBoundingClientRect().top <= line) current = headings[i]; else break;
    }
    // At the very bottom the last sections can never reach the line: take the last one.
    if (headings.length && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
      current = headings[headings.length - 1];
    }
    var id = current ? current.id : null;
    if (id === activeId) return;
    activeId = id;
    tocLinks.forEach(function (a) {
      var on = id !== null && decodeURIComponent(a.getAttribute('href').slice(1)) === id;
      a.classList.toggle('active', on);
      if (on && a.closest('.toc')) {
        var box = a.closest('.toc');
        var top = a.offsetTop - box.clientHeight / 2;
        if (a.offsetTop < box.scrollTop || a.offsetTop > box.scrollTop + box.clientHeight - 40) box.scrollTo({ top: top });
      }
    });
  }
  if (headings.length) {
    var ticking = false;
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(function () { ticking = false; markActive(); }); }
    }, { passive: true });
    markActive();
  }
  $$('.toc-mobile .toc-list a').forEach(function (a) {
    a.addEventListener('click', function () { a.closest('details').removeAttribute('open'); });
  });

  // ---------------------------------------------------------------- search
  var modal = document.querySelector('[data-search]');
  var input = document.querySelector('[data-search-input]');
  var results = document.querySelector('[data-search-results]');
  var index = (window.PAGESDOC_INDEX || []).map(function (e) {
    var h = fold(e.h), p = fold(e.p), t = fold(e.t);
    return { e: e, h: h.s, p: p.s, t: t.s, tmap: t.map };
  });
  var selected = 0;
  var lastFocus = null;
  var suggestions = ['النسخ الاحتياطي', 'فاتورة', 'تحديث', 'Webhooks', 'كلمة المرور', 'SSL'];

  // Arabic-aware folding (hamza forms, taa marbuta, alef maqsura, diacritics, tatweel) with a map back to the original text.
  function fold(text) {
    var out = '', map = [];
    for (var i = 0; i < text.length; i++) {
      var ch = text[i];
      if (/[ً-ٰٟـ]/.test(ch)) continue;
      if (/[أإآٱ]/.test(ch)) ch = 'ا';
      else if (ch === 'ة') ch = 'ه';
      else if (ch === 'ى') ch = 'ي';
      else if (ch === 'ؤ') ch = 'و';
      else if (ch === 'ئ') ch = 'ي';
      else ch = ch.toLowerCase();
      out += ch;
      map.push(i);
    }
    return { s: out, map: map };
  }
  function esc(s) {
    return s.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  // Highlights each term in a short text (heading / page title).
  function markAll(original, terms) {
    var folded = fold(original);
    var ranges = [];
    terms.forEach(function (term) {
      var from = 0, at;
      while (term && (at = folded.s.indexOf(term, from)) !== -1) {
        ranges.push([folded.map[at], folded.map[at + term.length - 1] + 1]);
        from = at + term.length;
      }
    });
    return wrap(original, ranges);
  }
  function wrap(original, ranges) {
    ranges.sort(function (a, b) { return a[0] - b[0]; });
    var html = '', pos = 0;
    ranges.forEach(function (r) {
      if (r[0] < pos) return;
      html += esc(original.slice(pos, r[0])) + '<mark>' + esc(original.slice(r[0], r[1])) + '</mark>';
      pos = r[1];
    });
    return html + esc(original.slice(pos));
  }
  function snippet(item, terms) {
    var text = item.e.t;
    var first = -1;
    terms.forEach(function (term) {
      var at = item.t.indexOf(term);
      if (at !== -1 && (first === -1 || at < first)) first = at;
    });
    if (first === -1) return esc(text.slice(0, 150)) + (text.length > 150 ? '…' : '');
    var center = item.tmap[first];
    var start = Math.max(0, center - 60);
    var end = Math.min(text.length, start + 170);
    var piece = text.slice(start, end);
    return (start > 0 ? '…' : '') + markAll(piece, terms) + (end < text.length ? '…' : '');
  }
  function search(query) {
    var terms = fold(query.trim()).s.split(/\s+/).filter(Boolean);
    if (!terms.length) return { terms: terms, hits: [] };
    var hits = [];
    index.forEach(function (item) {
      var score = 0;
      for (var i = 0; i < terms.length; i++) {
        var t = terms[i];
        var inH = item.h.indexOf(t) !== -1, inP = item.p.indexOf(t) !== -1, inT = item.t.indexOf(t) !== -1;
        if (!inH && !inP && !inT) return;
        score += (inH ? 6 : 0) + (inP ? 2 : 0) + (inT ? 1 : 0);
        if (item.h.indexOf(t) === 0) score += 2;
      }
      hits.push({ item: item, score: score });
    });
    hits.sort(function (a, b) { return b.score - a.score; });
    return { terms: terms, hits: hits.slice(0, 30) };
  }
  function render() {
    var q = input.value;
    if (!q.trim()) {
      results.innerHTML = '<div class="search-hint"><b>ابحث في كل الأدلة دفعة واحدة.</b><br>جرّب:<div class="pills">'
        + suggestions.map(function (s) { return '<button type="button" data-suggest="' + esc(s) + '">' + esc(s) + '</button>'; }).join('') + '</div></div>';
      return;
    }
    var found = search(q);
    if (!found.hits.length) {
      results.innerHTML = '<div class="search-empty">لا نتائج لـ «' + esc(q.trim()) + '». جرّب كلمة أقصر أو مرادفاً.</div>';
      return;
    }
    selected = 0;
    results.innerHTML = found.hits.map(function (hit, i) {
      var e = hit.item.e;
      return '<a class="sr' + (i === 0 ? ' sel' : '') + '" href="' + esc(e.u) + '">'
        + '<span class="sr-page">' + esc(e.g) + ' · ' + esc(e.p) + '</span>'
        + '<span class="sr-h">' + markAll(e.h, found.terms) + '</span>'
        + (e.t ? '<span class="sr-t">' + snippet(hit.item, found.terms) + '</span>' : '')
        + '</a>';
    }).join('');
  }
  function select(n) {
    var items = $$('.sr', results);
    if (!items.length) return;
    selected = (n + items.length) % items.length;
    items.forEach(function (el, i) { el.classList.toggle('sel', i === selected); });
    items[selected].scrollIntoView({ block: 'nearest' });
  }
  function open(initial) {
    if (!modal) return;
    lastFocus = document.activeElement;
    modal.hidden = false;
    body.classList.remove('nav-open');
    body.style.overflow = 'hidden';
    if (typeof initial === 'string') input.value = initial;
    render();
    setTimeout(function () { input.focus(); input.select(); }, 0);
  }
  function close() {
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  if (modal) {
    $$('[data-open-search]').forEach(function (b) { b.addEventListener('click', function () { open(); }); });
    $$('[data-close-search]').forEach(function (b) { b.addEventListener('click', close); });
    input.addEventListener('input', render);
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown') { ev.preventDefault(); select(selected + 1); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); select(selected - 1); }
      else if (ev.key === 'Enter') {
        var target = $$('.sr', results)[selected];
        if (target) { ev.preventDefault(); target.click(); }
      }
    });
    results.addEventListener('click', function (ev) {
      var suggestion = ev.target.closest('[data-suggest]');
      if (suggestion) { input.value = suggestion.getAttribute('data-suggest'); render(); input.focus(); return; }
      var link = ev.target.closest('.sr');
      // Same page: the browser only scrolls, so the dialog must close itself.
      if (link) setTimeout(close, 0);
    });
    document.addEventListener('keydown', function (ev) {
      var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) || document.activeElement.isContentEditable;
      if ((ev.ctrlKey || ev.metaKey) && (ev.key === 'k' || ev.key === 'K' || ev.key === 'ن')) { ev.preventDefault(); modal.hidden ? open() : close(); }
      else if (ev.key === '/' && !typing && modal.hidden) { ev.preventDefault(); open(); }
      else if (ev.key === 'Escape') { close(); body.classList.remove('nav-open'); }
    });
  }
})();
