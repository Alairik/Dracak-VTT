(function(){
  "use strict";

  // ---------- visible error badge (diagnostic safety net) ----------
  // If anything below throws, surface it on the page itself instead of failing silently,
  // since this page's console is not always reachable for debugging.
  function showDebugError(msg){
    try {
      var b = document.getElementById('__dbg__');
      if (!b) {
        b = document.createElement('div');
        b.id = '__dbg__';
        b.style.cssText = 'position:fixed;bottom:8px;left:8px;right:8px;max-height:32vh;overflow:auto;background:#b3261e;color:#fff;font:11px/1.4 monospace;padding:10px 12px;border-radius:8px;z-index:99999;white-space:pre-wrap;box-shadow:0 6px 20px rgba(0,0,0,.4);';
        (document.body || document.documentElement).appendChild(b);
      }
      b.textContent += (b.textContent ? '\n' : '') + msg;
    } catch(_e) { /* nothing more we can do */ }
  }
  window.addEventListener('error', function(e){
    showDebugError('JS chyba: ' + (e && (e.message || e.error)) + (e && e.filename ? (' (' + e.filename + ':' + e.lineno + ')') : ''));
  });
  window.addEventListener('unhandledrejection', function(e){
    showDebugError('Promise chyba: ' + (e && e.reason));
  });

  try {

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var DIAC = {'á':'a','ä':'a','č':'c','ď':'d','é':'e','ě':'e','í':'i','ĺ':'l','ľ':'l','ň':'n','ó':'o','ô':'o','ř':'r','š':'s','ť':'t','ú':'u','ů':'u','ü':'u','ý':'y','ž':'z'};
    function fold(s){
      s = String(s).toLowerCase();
      var out = '';
      for (var i=0;i<s.length;i++){
        var c = s[i];
        out += DIAC[c] || c;
      }
      return out;
    }

    var HEADINGS = window.__HEADINGS__ || {};
    var HEADING_ORDER = Object.keys(HEADINGS);

    // ---------- element refs ----------
    var sidebar = document.getElementById('sidebar');
    var menuBtn = document.getElementById('menuBtn');
    var backdrop = document.getElementById('backdrop');
    var topBtn = document.getElementById('topBtn');
    var content = document.getElementById('content');

    var headInput = document.getElementById('headSearch');
    var headField = document.getElementById('headField');
    var headResults = document.getElementById('headResults');
    var headClear = document.getElementById('headClear');

    var fullInput = document.getElementById('fullSearch');
    var fullField = document.getElementById('fullField');
    var fullClear = document.getElementById('fullClear');
    var fullWrap = document.getElementById('fullResultsWrap');
    var fullList = document.getElementById('fullResults');
    var fullCloseBtn = document.getElementById('fullClose');
    var tocView = document.getElementById('tocView');
    var fullHeadLabel = document.querySelector('.full-results-head span');

    // ---------- parts (Hráč / PJ / Bestiář) ----------
    var PART_LABELS = {hrac:'Hráč', pj:'Pán jeskyně', bestiar:'Bestiář', hb:'Homebrew'};
    var currentPart = 'hrac';
    var searchScope = 'all';
    var partTabs = Array.prototype.slice.call(document.querySelectorAll('.part-tab'));
    function partOf(el){
      var sec = el && el.closest ? el.closest('section.part') : null;
      return sec ? sec.getAttribute('data-part') : null;
    }
    function setPart(key){
      if (!key || key === currentPart) return;
      currentPart = key;
      partTabs.forEach(function(t){
        var on = t.getAttribute('data-part') === key;
        t.classList.toggle('active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      var roots = document.querySelectorAll('.toc-root');
      for (var i=0;i<roots.length;i++) roots[i].hidden = roots[i].getAttribute('data-part') !== key;
      var sbody = document.querySelector('.sidebar-body'); if (sbody) sbody.scrollTop = 0;
      if (searchScope === 'current') rerunSearches();
    }
    partTabs.forEach(function(t){
      t.addEventListener('click', function(){
        var key = t.getAttribute('data-part');
        setPart(key);
        goTo('part-' + key);
      });
    });

    // ---------- mobile drawer ----------
    function openSidebar(){ sidebar.classList.add('open'); backdrop.classList.add('show'); document.body.style.overflow='hidden'; }
    function closeSidebar(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); document.body.style.overflow=''; }
    if (menuBtn) menuBtn.addEventListener('click', function(){
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // ---------- back to top ----------
    window.addEventListener('scroll', function(){
      if (window.scrollY > 500) topBtn.classList.add('show');
      else topBtn.classList.remove('show');
    }, {passive:true});
    topBtn.addEventListener('click', function(){
      try { window.scrollTo({top:0, behavior: reduceMotion ? 'auto' : 'smooth'}); }
      catch(_e) { window.scrollTo(0,0); }
    });

    // ---------- navigation: native #hash jump (robust across every layout) ----------
    // We deliberately do NOT drive the scroll with JS (scrollIntoView/scrollTo can behave
    // inconsistently across sticky/grid layouts and sandboxed viewers). Instead we let the
    // browser's own anchor navigation do the scrolling — that always works — and only use
    // JS for the extras (flash highlight, expanding the tree, marking the active link).
    function afterJump(id, elArg){
      var el = elArg || document.getElementById(id);
      if (!el) return;
      setPart(partOf(el));
      el.classList.remove('flash');
      void el.offsetWidth; // restart animation
      el.classList.add('flash');
      setTimeout(function(){ el.classList.remove('flash'); }, 2000);
      expandAncestors(id);
      setActiveTocLink(nearestHeadingId(el));
      closeSidebar();
    }

    function goTo(id){
      var el = document.getElementById(id);
      if (!el) return;
      if (location.hash === '#' + id) {
        // hash already matches this target — hashchange won't fire, so run the extras directly
        // and nudge the scroll ourselves as a fallback in case the browser already sat there.
        afterJump(id, el);
        try { location.href = '#' + id; } catch(_e) {}
      } else {
        location.hash = id;
      }
    }

    window.addEventListener('hashchange', function(){
      var id = location.hash.slice(1);
      if (id) afterJump(id);
    });

    function nearestHeadingId(el){
      if (el.tagName && /^H[1-5]$/.test(el.tagName)) return el.id;
      var sec = el.getAttribute && el.getAttribute('data-sec');
      return sec || null;
    }

    function expandAncestors(id){
      var link = document.querySelector('.toc-link[data-id="'+id+'"]');
      if (!link) {
        // maybe id belongs to a content block; use its data-sec heading instead
        var el = document.getElementById(id);
        var sec = el && el.getAttribute('data-sec');
        if (sec) link = document.querySelector('.toc-link[data-id="'+sec+'"]');
      }
      if (!link) return;
      var li = link.closest('li.toc-item');
      while (li) {
        var parentUl = li.parentElement;
        if (parentUl && parentUl.classList.contains('toc-children')) {
          parentUl.hidden = false;
          var parentLi = parentUl.closest('li.toc-item');
          var toggle = parentLi && parentLi.querySelector(':scope > .toc-row > .toc-toggle');
          if (toggle) toggle.setAttribute('data-open','true');
          li = parentLi;
        } else {
          li = null;
        }
      }
    }

    function setActiveTocLink(id){
      var prev = document.querySelector('.toc-link.active');
      if (prev) prev.classList.remove('active');
      if (!id) return;
      var link = document.querySelector('.toc-link[data-id="'+id+'"]');
      if (link) {
        link.classList.add('active');
        try { link.scrollIntoView({block:'nearest'}); } catch(_e) {}
      }
    }

    // ---------- TOC toggle ----------
    document.addEventListener('click', function(e){
      var btn = e.target.closest && e.target.closest('.toc-toggle');
      if (!btn) return;
      var open = btn.getAttribute('data-open') === 'true';
      btn.setAttribute('data-open', open ? 'false' : 'true');
      var ul = btn.closest('.toc-row').nextElementSibling;
      if (ul && ul.classList.contains('toc-children')) ul.hidden = open;
    });

    // ---------- TOC link clicks ----------
    // Real <a href="#id"> elements: let the click perform native navigation (most robust),
    // and just run the "extras" afterwards via the hashchange listener above — except when
    // the hash is unchanged, which goTo() already handles.
    document.addEventListener('click', function(e){
      var link = e.target.closest && e.target.closest('.toc-link');
      if (!link) return;
      var id = link.getAttribute('data-id');
      if (location.hash === '#' + id) {
        e.preventDefault();
        afterJump(id);
      }
      // otherwise: let the native <a href="#id"> click proceed; hashchange picks it up.
    });

    // ---------- heading typeahead ----------
    var headActiveIndex = -1;
    function renderHeadResults(query){
      var nq = fold(query.trim());
      headResults.innerHTML = '';
      headActiveIndex = -1;
      if (nq.length === 0) { headResults.classList.remove('show'); return; }
      var matches = [];
      for (var i=0;i<HEADING_ORDER.length;i++){
        var id = HEADING_ORDER[i];
        var h = HEADINGS[id];
        if (searchScope === 'current' && h.g !== currentPart) continue;
        if (searchScope === 'nohb' && h.x) continue;
        var ti = fold(h.t).indexOf(nq);
        var pi = ti === -1 ? fold(h.p).indexOf(nq) : -1;
        if (ti === -1 && pi === -1) continue;
        // rank: title starts with query < title contains < only path matches; current part first
        var rank = (ti === 0 ? 0 : (ti > 0 ? 1 : 2)) * 2 + (h.g === currentPart ? 0 : 1);
        matches.push({id:id, h:h, rank:rank, ord:i});
        if (matches.length >= 400) break;
      }
      matches.sort(function(a,b){ return a.rank - b.rank || a.ord - b.ord; });
      if (matches.length === 0) {
        var li = document.createElement('li');
        li.innerHTML = '<span style="display:block;padding:8px 9px;color:var(--ink-faint);font-size:.88rem;">Žádný nadpis neodpovídá.</span>';
        headResults.appendChild(li);
        headResults.classList.add('show');
        return;
      }
      matches.slice(0,15).forEach(function(m){
        var li = document.createElement('li');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.id = m.id;
        var text = highlightText(m.h.t, query);
        btn.innerHTML = text + (m.h.x ? '<span class="hb-mark">homebrew</span>' : '') + (m.h.p ? '<span class="path">' + escapeHtml(m.h.p) + '</span>' : '');
        li.appendChild(btn);
        headResults.appendChild(li);
      });
      headResults.classList.add('show');
    }
    function escapeHtml(s){
      return String(s).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
      });
    }
    function highlightText(text, query){
      var nText = fold(text), nq = fold(query.trim());
      if (!nq) return escapeHtml(text);
      var idx = nText.indexOf(nq);
      if (idx === -1) return escapeHtml(text);
      return escapeHtml(text.slice(0,idx)) + '<mark>' + escapeHtml(text.slice(idx, idx+nq.length)) + '</mark>' + escapeHtml(text.slice(idx+nq.length));
    }

    headInput.addEventListener('input', function(){
      headField.classList.toggle('has-value', headInput.value.length>0);
      renderHeadResults(headInput.value);
    });
    headInput.addEventListener('focus', function(){
      if (headInput.value) renderHeadResults(headInput.value);
    });
    headInput.addEventListener('keydown', function(e){
      var items = headResults.querySelectorAll('button');
      if (e.key === 'ArrowDown'){
        e.preventDefault();
        if (!items.length) return;
        headActiveIndex = Math.min(headActiveIndex+1, items.length-1);
        updateHeadActive(items);
      } else if (e.key === 'ArrowUp'){
        e.preventDefault();
        if (!items.length) return;
        headActiveIndex = Math.max(headActiveIndex-1, 0);
        updateHeadActive(items);
      } else if (e.key === 'Enter'){
        e.preventDefault();
        if (headActiveIndex >= 0 && items[headActiveIndex]) {
          items[headActiveIndex].click();
        } else if (items.length) {
          items[0].click();
        }
      } else if (e.key === 'Escape'){
        headResults.classList.remove('show');
        headInput.blur();
      }
    });
    function updateHeadActive(items){
      items.forEach(function(it,i){ it.classList.toggle('active', i===headActiveIndex); });
      if (items[headActiveIndex]) items[headActiveIndex].scrollIntoView({block:'nearest'});
    }
    headResults.addEventListener('click', function(e){
      var btn = e.target.closest('button');
      if (!btn || !btn.dataset.id) return;
      goTo(btn.dataset.id);
      headInput.value = '';
      headField.classList.remove('has-value');
      headResults.classList.remove('show');
    });
    headClear.addEventListener('click', function(){
      headInput.value = '';
      headField.classList.remove('has-value');
      headResults.classList.remove('show');
      headInput.focus();
    });
    document.addEventListener('click', function(e){
      if (!headField.contains(e.target)) headResults.classList.remove('show');
    });

    // ---------- fulltext search ----------
    var BLOCKS = null;
    function buildBlockIndex(){
      var nodes = content.querySelectorAll('[data-blk]');
      BLOCKS = [];
      nodes.forEach(function(el){
        BLOCKS.push({el:el, norm: fold(el.textContent), part: el.getAttribute('data-part'), hb: el.hasAttribute('data-hb')});
      });
    }

    function runFullSearch(query){
      if (!BLOCKS) buildBlockIndex();
      var nq = fold(query.trim());
      fullList.innerHTML = '';
      if (nq.length < 2) {
        fullWrap.classList.remove('show');
        tocView.hidden = false;
        return;
      }
      var results = [];
      var total = 0;
      for (var i=0;i<BLOCKS.length;i++){
        var b = BLOCKS[i];
        if (searchScope === 'current' && b.part !== currentPart) continue;
        if (searchScope === 'nohb' && b.hb) continue;
        var idx = b.norm.indexOf(nq);
        if (idx !== -1){
          total++;
          results.push({el:b.el, idx:idx, cur: b.part === currentPart ? 0 : 1, ord:i});
        }
      }
      results.sort(function(a,b){ return a.cur - b.cur || a.ord - b.ord; });
      var shown = Math.min(results.length, 60);
      results = results.slice(0, 60);
      if (fullHeadLabel) fullHeadLabel.textContent = 'Výsledky hledání' + (total ? ' (' + (total > shown ? shown + ' z ' + total : total) + ')' : '');
      tocView.hidden = true;
      fullWrap.classList.add('show');
      if (results.length === 0){
        var empty = document.createElement('div');
        empty.className = 'full-empty';
        empty.textContent = 'Žádná shoda v textu pravidel.';
        fullList.appendChild(empty);
        return;
      }
      results.forEach(function(r){
        var raw = r.el.textContent.replace(/\s+/g,' ').trim();
        var normRaw = fold(raw);
        var idx = normRaw.indexOf(nq);
        if (idx === -1) idx = 0;
        var start = Math.max(0, idx-45);
        var end = Math.min(raw.length, idx+nq.length+70);
        var snippet = (start>0?'…':'') + raw.slice(start,idx) + '<mark>' + raw.slice(idx, idx+nq.length) + '</mark>' + raw.slice(idx+nq.length, end) + (end<raw.length?'…':'');
        var secId = r.el.getAttribute('data-sec');
        var pathStr = '';
        if (secId && HEADINGS[secId]) {
          var h = HEADINGS[secId];
          pathStr = (h.p ? h.p + ' › ' : '') + h.t;
        }
        var li = document.createElement('li');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.target = r.el.id;
        btn.innerHTML = (pathStr ? '<span class="path">' + escapeHtml(pathStr) + '</span>' : '') + '<span class="snippet">' + snippet + '</span>';
        li.appendChild(btn);
        fullList.appendChild(li);
      });
    }

    var fullDebounce = null;
    fullInput.addEventListener('input', function(){
      fullField.classList.toggle('has-value', fullInput.value.length>0);
      clearTimeout(fullDebounce);
      var v = fullInput.value;
      fullDebounce = setTimeout(function(){ runFullSearch(v); }, 160);
    });
    fullInput.addEventListener('keydown', function(e){
      if (e.key === 'Escape'){ fullInput.value=''; fullField.classList.remove('has-value'); runFullSearch(''); fullInput.blur(); }
    });
    fullClear.addEventListener('click', function(){
      fullInput.value=''; fullField.classList.remove('has-value'); runFullSearch(''); fullInput.focus();
    });
    fullCloseBtn.addEventListener('click', function(){
      fullInput.value=''; fullField.classList.remove('has-value'); runFullSearch('');
    });
    fullList.addEventListener('click', function(e){
      var btn = e.target.closest('button');
      if (!btn) return;
      goTo(btn.dataset.target);
    });

    // ---------- search scope ----------
    function rerunSearches(){
      if (fullInput.value.trim().length >= 2) runFullSearch(fullInput.value);
      if (headInput.value.trim() && headResults.classList.contains('show')) renderHeadResults(headInput.value);
    }
    var scopeChips = Array.prototype.slice.call(document.querySelectorAll('.scope-chip'));
    scopeChips.forEach(function(c){
      c.addEventListener('click', function(){
        searchScope = c.getAttribute('data-scope');
        scopeChips.forEach(function(x){ x.classList.toggle('active', x === c); });
        rerunSearches();
      });
    });

    // ---------- keyboard shortcut ----------
    document.addEventListener('keydown', function(e){
      if (e.key === '/' && document.activeElement.tagName !== 'INPUT'){
        e.preventDefault();
        fullInput.focus();
      }
    });

    // ---------- scrollspy ----------
    var headingEls = Array.prototype.slice.call(content.querySelectorAll('h1[id],h2[id],h3[id],h4[id],h5[id]'));
    var currentActive = null;
    function onScroll(){
      var pos = window.scrollY + 90;
      var active = null;
      for (var i=0;i<headingEls.length;i++){
        if (headingEls[i].offsetTop <= pos) active = headingEls[i];
        else break;
      }
      if (active && active.id !== currentActive){
        currentActive = active.id;
        setPart(partOf(active));
        expandAncestors(active.id);
        setActiveTocLink(active.id);
      }
    }
    var scrollTicking = false;
    window.addEventListener('scroll', function(){
      if (!scrollTicking){
        window.requestAnimationFrame(function(){ onScroll(); scrollTicking = false; });
        scrollTicking = true;
      }
    }, {passive:true});

    // ---------- initial hash ----------
    if (location.hash.length > 1){
      var initId = location.hash.slice(1);
      setTimeout(function(){ afterJump(initId); }, 30);
    } else {
      onScroll();
    }

  } catch (fatalErr) {
    showDebugError('Fatální chyba při inicializaci: ' + (fatalErr && (fatalErr.stack || fatalErr.message || fatalErr)));
  }
})();
