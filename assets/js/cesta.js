// Kalkulačka cesty (hra/cesta.php) — formulář, volání hra/api/cesta_vypocet.php
// a vykreslení výsledku. Všechno se počítá na serveru.
(function () {
  const wrap = document.querySelector('.cs-wrap');
  if (!wrap) return;
  const SVET = Number(wrap.dataset.svetId);
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const num = (v, d) => { const n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? n : d; };
  const fmt = (n, d) => Number(n).toLocaleString('cs-CZ', { maximumFractionDigits: d === undefined ? 1 : d });

  function hodiny(h) {
    const min = Math.round(h * 60);
    const hh = Math.floor(min / 60), mm = min % 60;
    return ((hh ? hh + ' h ' : '') + (mm || !hh ? (hh ? String(mm).padStart(2, '0') : mm) + ' min' : '')).trim();
  }

  function narocnost() {
    const n = num($('cs-povrch').value, 0) * num($('cs-teren').value, 1) * num($('cs-vliv').value, 1);
    $('cs-narocnost').textContent = fmt(n, 2);
  }
  ['cs-povrch', 'cs-teren', 'cs-vliv'].forEach((id) => $(id).addEventListener('input', narocnost));
  document.querySelectorAll('[data-vyber]').forEach((sel) => {
    sel.addEventListener('change', () => { $('cs-' + sel.dataset.vyber).value = sel.value; narocnost(); });
  });
  narocnost();

  // Doprovodná zvířata.
  const sablona = $('cs-zvire-sablona');
  if (sablona) {
    $('cs-pridat-zvire').addEventListener('click', () => {
      $('cs-nosice').appendChild(sablona.content.cloneNode(true));
    });
    $('cs-nosice').addEventListener('click', (ev) => {
      const t = ev.target.closest('.cs-zvire-smazat');
      if (t) t.closest('.cs-zvire').remove();
    });
  }

  // Povoz: zapnutí, předvyplnění váhy a násobku podle typu, cestující.
  const povozZap = $('cs-povoz-zapnout');
  function povozPredvypln() {
    const o = $('cs-povoz-typ').selectedOptions[0];
    if (!o) return;
    $('cs-povoz-vaha').value = o.dataset.vaha;
    $('cs-povoz-nasobek').value = o.dataset.nasobek;
  }
  if (povozZap) {
    povozPredvypln();
    povozZap.addEventListener('change', () => {
      $('cs-povoz-pole').hidden = !povozZap.checked;
      wrap.querySelectorAll('.cs-napovoze').forEach((cb) => {
        const tr = cb.closest('tr');
        cb.disabled = !povozZap.checked || tr.classList.contains('cs-nelze');
        if (cb.disabled) cb.checked = false;
      });
    });
    $('cs-povoz-typ').addEventListener('change', povozPredvypln);
  }

  function telo(zapsat) {
    const ucastnici = [];
    wrap.querySelectorAll('tr[data-postava]').forEach((tr) => {
      const cb = tr.querySelector('.cs-ucastnik');
      if (!cb || !cb.checked) return;
      const vaha = tr.querySelector('.cs-vaha');
      const povoz = tr.querySelector('.cs-napovoze');
      ucastnici.push({ postava_id: Number(tr.dataset.postava), vaha_jezdce: vaha ? num(vaha.value, 0) : 0, na_povoze: !!(povoz && povoz.checked) });
    });
    const zvirata = [];
    wrap.querySelectorAll('.cs-zvire').forEach((r) => {
      zvirata.push({ zvire_id: Number(r.querySelector('.cs-zvire-typ').value), naklad: num(r.querySelector('.cs-zvire-naklad').value, 0) });
    });
    return {
      svet_id: SVET, vzdalenost: num($('cs-vzdalenost').value, 0), povrch: num($('cs-povrch').value, 0),
      teren: num($('cs-teren').value, 1), vliv: num($('cs-vliv').value, 1),
      tempo: wrap.querySelector('input[name="cs-tempo"]:checked').value, zastav: Number($('cs-zastav').value),
      ucastnici, zvirata, zapsat: !!zapsat,
      povoz: povozZap && povozZap.checked ? {
        povoz_id: Number($('cs-povoz-typ').value), vaha_vozu: num($('cs-povoz-vaha').value, 0), nasobek: num($('cs-povoz-nasobek').value, 0),
        zvire_id: Number($('cs-povoz-zvire').value), pocet: Number($('cs-povoz-pocet').value), naklad: num($('cs-povoz-naklad').value, 0),
      } : null,
    };
  }

  async function volej(zapsat) {
    const stav = $('cs-stav');
    stav.textContent = 'Počítám…';
    try {
      const r = await fetch('api/cesta_vypocet.php', {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(telo(zapsat)),
      });
      const d = await r.json().catch(() => null);
      stav.textContent = '';
      if (!r.ok || !d) { stav.textContent = (d && d.error) || 'Výpočet selhal.'; return null; }
      return d;
    } catch (e) {
      stav.textContent = 'Výpočet selhal (síť).';
      return null;
    }
  }

  function vykresli(d) {
    const box = $('cs-vysledek');
    let h = '';
    if (d.chyby && d.chyby.length) {
      h += '<h3 class="dn-h3">Nejde spočítat</h3><ul class="cs-chyby">' + d.chyby.map((c) => '<li>' + esc(c) + '</li>').join('') + '</ul>';
    }
    const pv = d.povoz;
    if (pv) {
      const nal = { zadne: 'žádné', mirne: 'mírné', stredni: 'střední', velke: 'velké', prilis_velke: 'nad 4×' };
      h += '<h3 class="dn-h3">Povoz — ' + esc(pv.nazev) + '</h3><div class="dn-tab-wrap"><table class="dn-tab"><tbody>' +
        '<tr><td>Váha vozu + provozní náklad (posádka, náklad)</td><td class="c">' + fmt(pv.vaha_vozu, 0) + ' + ' + fmt(pv.provozni_naklad, 0) + ' = <strong>' + fmt(pv.celkem_pro_sprezeni, 0) + ' mn</strong></td></tr>' +
        '<tr><td>Bezpečný provozní náklad při této náročnosti' + (pv.procento !== null ? ' (' + pv.procento + ' % konstrukční nosnosti)' : '') + '</td><td class="c">' + fmt(pv.max_provozni_naklad, 0) + ' mn</td></tr>' +
        '<tr><td>Spřežení ' + esc(pv.sprezeni) + ': nosnost v zápřahu / nejvíc uveze</td><td class="c">' + fmt(pv.nosnost_v_zaprahu_mn, 0) + ' / ' + fmt(pv.max_uveze_mn, 0) + ' mn</td></tr>' +
        '<tr><td>Naložení zvířat / jejich pohyblivost</td><td class="c">' + esc(nal[pv.nalozeni] || pv.nalozeni) + ' / ' + (pv.pohyblivost !== null ? pv.pohyblivost : '—') + (pv.bonus !== null ? ' (bonus ' + (pv.bonus > 0 ? '+' : '') + pv.bonus + ')' : '') + '</td></tr>' +
        '<tr><td>Cestující dostane 1 bod únavy za</td><td class="c">' + pv.unava_cestujiciho_za_body_zvirete + ' bodů únavy zvířat</td></tr>' +
        (pv.rizeni_procent !== null ? '<tr><td>Řízení klusem: úspěch kočího (hází se za každou započatou směnu)</td><td class="c">' + fmt(pv.rizeni_procent, 1) + ' %</td></tr>' : '') +
        '</tbody></table></div>';
      if (pv.poznamky && pv.poznamky.length) h += '<ul class="cs-chyby">' + pv.poznamky.map((p) => '<li>' + esc(p) + '</li>').join('') + '</ul>';
    }
    const v = d.vysledek;
    if (v) {
      let titul;
      if (v.duvod === 'cil') titul = 'Cíl dosažen za <strong>' + hodiny(v.hodin) + '</strong> (' + fmt(v.mil, 2) + ' mil)';
      else if (v.duvod === 'unava') titul = 'Úsek končí po <strong>' + hodiny(v.hodin) + '</strong> — ušli ' + fmt(v.mil, 2) + ' mil, zbývá ' + fmt(v.zbyva_mil, 2) + '. Další krok by <strong>' + esc(v.kdo_nazev || '') + '</strong> přivedl na vybraný stupeň únavy.';
      else if (v.duvod === 'hotovo_na_zacatku') titul = 'Nelze vyrazit: <strong>' + esc(v.kdo_nazev || '') + '</strong> je na hranici zvoleného stupně únavy už na začátku. Vyber vyšší stupeň nebo nejdřív odpočiň.';
      else titul = 'Cestu nejde spočítat' + (v.kdo_nazev ? ' (' + esc(v.kdo_nazev) + ')' : '') + '.';
      h += '<h3 class="dn-h3">Výsledek — ' + esc(d.tempo) + ', náročnost ' + fmt(d.narocnost, 2) + ' b/míli</h3><p class="cs-titul">' + titul + '</p>';
      if (v.segmenty.length) {
        h += '<div class="dn-tab-wrap"><table class="dn-tab"><thead><tr><th>Od</th><th>Do</th><th class="c">Rychlost družiny</th><th class="c">Mil za hodinu</th><th>Zpomaluje</th></tr></thead><tbody>' +
          v.segmenty.map((s) => '<tr><td>' + hodiny(s.od_h) + '</td><td>' + hodiny(s.do_h) + '</td><td class="c">' + s.body_za_hodinu + ' b/h</td><td class="c">' + fmt(s.mil_za_hodinu, 2) + '</td><td>' + esc(s.omezuje) + '</td></tr>').join('') +
          '</tbody></table></div>';
      }
      h += '<div class="dn-tab-wrap"><table class="dn-tab"><thead><tr><th>Kdo</th><th class="c">Únava před</th><th class="c">Únava po</th><th class="c">Vytrvalost</th><th>Stav po cestě</th></tr></thead><tbody>' +
        v.konec.map((k) => '<tr><td>' + esc(k.nazev) + '</td><td class="c">' + fmt(k.body_zacatek, 2) + '</td><td class="c">' + fmt(k.body_konec, 2) + '</td><td class="c">' + k.vytrvalost + '</td><td>' + esc(k.stupen_nazev) + '</td></tr>').join('') +
        '</tbody></table></div>';
      const poznamky = [];
      (d.subjekty || []).forEach((s) => (s.poznamky || []).forEach((p) => poznamky.push(s.nazev + ': ' + p)));
      if (poznamky.length) h += '<ul class="cs-chyby">' + poznamky.map((p) => '<li>' + esc(p) + '</li>').join('') + '</ul>';
      h += '<p class="note">Únava zvířat se nikam neukládá (zvíře není evidovaná postava); jejich únava se jen hlídá kvůli zastavení. Jezdec dostane třetinu bodů únavy zvířete. Únava postav zpomaluje, protože ubírá bonus za sílu a obratnost (Tabulka únavy); jak únava ovlivňuje samotná zvířata, kniha neříká, jejich rychlost se proto s únavou nemění.</p>';
      if (d.zapsano && d.zapsano.length) {
        h += '<p class="cs-ulozeno">Únava zapsána: ' + d.zapsano.map((z) => esc(z.nazev) + ' → ' + z.unava).join(', ') + '.</p>';
      } else if (v.duvod !== 'nelze' && v.duvod !== 'hotovo_na_zacatku' && v.konec.some((k) => k.klic.startsWith('p:'))) {
        h += '<button type="button" class="btn btn-secondary" id="cs-zapsat">Zapsat únavu postavám (celé body)</button>';
      }
    }
    box.innerHTML = h;
    box.hidden = false;
    const zap = $('cs-zapsat');
    if (zap) zap.addEventListener('click', async () => {
      if (!window.confirm('Zapsat postavám body únavy po téhle cestě? Přepíše to jejich současnou únavu.')) return;
      const d2 = await volej(true);
      if (d2) vykresli(d2);
    });
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  $('cs-spocitat').addEventListener('click', async () => {
    const d = await volej(false);
    if (d) vykresli(d);
  });
})();
