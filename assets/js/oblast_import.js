// Přidání oblasti ze souboru (hra/svet_administrace.php): nejdřív se ukáže, co balíček obsahuje,
// a teprve po tom jde přidat do světa. Import bez téhle kontroly by tiše vyrobil nestvůry bez pravidel.
const soubor = document.getElementById('oblastSoubor');
const kontrolaBtn = document.getElementById('oblastKontrolaBtn');
const pridatBtn = document.getElementById('oblastPridatBtn');
const vysledek = document.getElementById('oblastVysledek');
if (soubor && kontrolaBtn && pridatBtn && vysledek) {
  const svetId = new URLSearchParams(location.search).get('id');
  const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let overeno = null;   // jméno souboru, u kterého proběhla kontrola

  const poslat = async (akce) => {
    const f = soubor.files && soubor.files[0];
    if (!f) { vysledek.textContent = 'Nejdřív vyber soubor s oblastí (ZIP).'; return null; }
    const telo = new FormData();
    telo.append('svet_id', svetId);
    telo.append('akce', akce);
    telo.append('soubor', f);
    vysledek.textContent = akce === 'kontrola' ? 'Čtu soubor…' : 'Přidávám do světa…';
    const r = await fetch('api/oblast_import.php', { method: 'POST', body: telo, credentials: 'same-origin' });
    let d = {};
    try { d = await r.json(); } catch (e) { d = { error: 'Server odpověděl nesrozumitelně.' }; }
    if (d.error) { vysledek.innerHTML = '<strong>Nepovedlo se:</strong> ' + esc(d.error); return null; }
    return d;
  };

  kontrolaBtn.addEventListener('click', async () => {
    pridatBtn.disabled = true;
    const d = await poslat('kontrola');
    if (!d) return;
    const k = d.kontrola || {};
    const p = k.pribude || {};
    let html = '<strong>' + esc(d.nazev || 'Oblast') + '</strong><br>Přibude: '
      + esc(p.map) + ' map, ' + esc(p.pinu) + ' míst, ' + esc(p.tokenu) + ' tokenů, '
      + esc(p.npc) + ' nestvůr, ' + esc(p.zdi) + ' zdí, ' + esc(p.poznamek) + ' poznámek.';
    if ((k.chybi_v_katalogu || []).length) {
      html += '<br><br><strong>Pozor — tohle u tebe v pravidlech není:</strong><ul style="margin:4px 0 0;padding-left:18px;">'
        + k.chybi_v_katalogu.map((x) => '<li>' + esc(x) + '</li>').join('') + '</ul>'
        + 'Co chybí, se nepřidá; zbytek ano.';
    }
    if ((k.piny_bez_cile || []).length) {
      html += '<br><br>Místa, která vedla pryč z oblasti a je potřeba je po přidání napojit ručně: '
        + k.piny_bez_cile.map(esc).join(', ') + '.';
    }
    vysledek.innerHTML = html;
    overeno = soubor.files[0].name;
    pridatBtn.disabled = false;
  });

  // Výměna souboru po kontrole shodí povolení — jinak by šlo přidat něco jiného, než co se zkontrolovalo.
  soubor.addEventListener('change', () => {
    pridatBtn.disabled = true;
    overeno = null;
    vysledek.textContent = '';
  });

  pridatBtn.addEventListener('click', async () => {
    if (!soubor.files[0] || soubor.files[0].name !== overeno) {
      vysledek.textContent = 'Nejdřív klikni na „Co je v souboru?“.';
      pridatBtn.disabled = true;
      return;
    }
    pridatBtn.disabled = true;
    const d = await poslat('zapis');
    if (!d) return;
    let html = '<strong>Hotovo.</strong> Přidáno map: ' + esc(d.map) + '.';
    if ((d.preskoceno || []).length) {
      html += '<br>Přeskočeno:<ul style="margin:4px 0 0;padding-left:18px;">'
        + d.preskoceno.map((x) => '<li>' + esc(x) + '</li>').join('') + '</ul>';
    }
    if (d.prvni_mapa) {
      html += '<br><a class="btn btn-secondary" style="margin-top:8px;" href="mapa.php?id=' + encodeURIComponent(d.prvni_mapa) + '">Otevřít přidanou mapu</a>';
    }
    vysledek.innerHTML = html;
    overeno = null;
  });
}
