// Hlásí chyby JavaScriptu do logu aplikace (hra/api/klient_chyba.php → admin_log.php). Hráč většinou nepozná, že se v prohlížeči
// něco rozbilo — jen "nic se nestalo po kliknutí". Posílá se jen pár záznamů na načtení stránky, bez opakování a bez šumu
// (rozšíření prohlížeče, cizí skripty, nenačtené obrázky). Samo nic nezobrazuje a nikdy nesmí způsobit další chybu.
(function () {
  'use strict';
  var skript = document.currentScript;
  if (!skript || !window.fetch || !window.URL) return;
  var adresa;
  try { adresa = new URL('../../hra/api/klient_chyba.php', skript.src).href; } catch (e) { return; }
  var odeslano = 0, videno = {}, MAX = 5;

  function posli(zprava, zdroj, radek, sloupec, stopa) {
    try {
      zprava = String(zprava || '').slice(0, 400);
      if (!zprava || odeslano >= MAX) return;
      zdroj = (zdroj || '').replace(location.origin, '');   // do logu jen cesta, ne celá adresa
      var klic = zprava + '|' + zdroj + '|' + radek;
      if (videno[klic]) return;
      videno[klic] = 1;
      odeslano++;
      fetch(adresa, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          zprava: zprava, zdroj: zdroj, radek: radek || 0, sloupec: sloupec || 0,
          stopa: stopa ? String(stopa).slice(0, 1200) : '', stranka: location.pathname + location.search
        })
      }).catch(function () {});
    } catch (e) { /* hlášení chyb nesmí chybovat */ }
  }

  // Cizí kód (rozšíření, skripty z jiné domény) a prázdné "Script error." nejsou naše chyby.
  function nase(zdroj) {
    return !zdroj || zdroj.indexOf(location.origin) === 0;
  }

  window.addEventListener('error', function (ev) {
    if (ev.target && ev.target !== window) {
      // Nenačtený skript nebo styl je vážný problém (nasazení neproběhlo celé); nenačtený obrázek ne.
      var t = ev.target, url = t.src || t.href || '';
      if ((t.tagName === 'SCRIPT' || (t.tagName === 'LINK' && t.rel === 'stylesheet')) && nase(url)) {
        posli('Nepodařilo se načíst ' + (t.tagName === 'SCRIPT' ? 'skript' : 'styl'), url, 0, 0, '');
      }
      return;
    }
    if (!ev.message || ev.message === 'Script error.' || /ResizeObserver loop/.test(ev.message) || !nase(ev.filename)) return;
    posli(ev.message, ev.filename, ev.lineno, ev.colno, ev.error && ev.error.stack);
  }, true);

  window.addEventListener('unhandledrejection', function (ev) {
    var r = ev.reason, zprava = r && r.message ? r.message : String(r);
    if (r && /^(AbortError|NetworkError)/.test(r.name || '')) return;
    posli('Nezachycený slib: ' + zprava, '', 0, 0, r && r.stack);
  });
})();
