// Správa tokenů (jen PJ) — lišta dole uprostřed mapy, jako mívají jiné VTT.
//
// Čtyři záložky:
//   Nový token    — vyrobí dávku NPC do zásoby („10× Goblin“), volitelně „každý jedinečný“ (vlastní čísla pro boj)
//                   a s volbou místa: tady (ukáže se u téhle lokace) nebo bez místa (zásoba pro mapy, co ještě nejsou).
//   Nevyužité     — NPC, která nestojí na žádné mapě; filtr vše / z téhle lokace / bez místa.
//   Tato lokace   — co všechno v téhle lokaci existuje (i to, co tam právě stojí).
//   Naposledy     — druhy, které PJ ve světě posledně pokládal; přetažením se vyrobí a položí jeden kus.
//
// Pokládá se TAŽENÍM na mapu — prstem i myší (Pointer Events, ne HTML5 drag&drop, který na dotyku nefunguje).
// Klepnutí na chip ho „nabije“: další klepnutí do mapy ho položí (když se táhnout nedá nebo se to tak někomu líbí víc).
// Všechno jede přes hra/api/npc_*.php a token_pridat.php; server si práva a svět ověřuje sám.

export function initTokenBar(opts) {
  const bar = document.getElementById('tokenBar');
  if (!bar) return null;
  const { svetId, mapaId, postJson, getJson, naMapu, gridStred, obnovitStav, logLine, viewport } = opts;

  const el = (id) => document.getElementById(id);
  const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const zalozky = Array.from(bar.querySelectorAll('.tb-zal'));
  const panely = Array.from(bar.querySelectorAll('.tb-panel'));
  let stav = { zasoba: [], naposledy: [], lokace: [] };
  let filtr = 'vse';
  let nabito = null;      // „nabitý“ chip: klepnutí do mapy ho položí
  let tahne = null;       // probíhající tažení z chipu

  // --- záložky ---
  const prepnout = (jmeno) => {
    zalozky.forEach((z) => z.classList.toggle('aktivni', z.dataset.tbZal === jmeno));
    panely.forEach((p) => { p.hidden = p.dataset.tbPanel !== jmeno; });
  };
  zalozky.forEach((z) => z.addEventListener('click', () => prepnout(z.dataset.tbZal)));
  // Filtr zásoby: vše / jen z téhle lokace / jen bez místa.
  bar.querySelectorAll('[data-tb-filtr]').forEach((b) => b.addEventListener('click', () => {
    filtr = b.dataset.tbFiltr;
    bar.querySelectorAll('[data-tb-filtr]').forEach((x) => x.classList.toggle('aktivni', x === b));
    vykreslit();
  }));
  el('tbZavrit').addEventListener('click', () => { bar.style.display = 'none'; });

  // --- výběr druhu ---
  // Vidět je seznam s náhledy (tvor_vyber.js), který vyjede nad polem hledání; skrytý <select>
  // drží vybrané id, protože z něj čte výroba NPC. Options se klonují ze seznamu v popoveru
  // „Přidat token“, aby se bestiář nevypisoval do HTML dvakrát.
  const druhSel = el('tbDruh');
  const zdroj = document.getElementById('novaNestvuraSelect');
  const vsechnyDruhy = zdroj ? Array.from(zdroj.options).map((o) => ({ id: o.value, nazev: o.textContent })) : [];
  druhSel.innerHTML = vsechnyDruhy.map((d) => '<option value="' + esc(d.id) + '">' + esc(d.nazev) + '</option>').join('');
  druhSel.selectedIndex = -1;
  const hledatEl = el('tbHledat');
  const seznamEl = el('tbDruhSeznam');
  // Vybraný druh se napíše rovnou do pole hledání (jako u našeptávače) — lišta tím nebobtná
  // o další popisek a je na jeden pohled vidět, co se vyrobí.
  const popisVybraneho = () => {
    const d = vsechnyDruhy.find((x) => String(x.id) === String(druhSel.value));
    hledatEl.classList.toggle('ma', !!d);
    if (d) hledatEl.value = d.nazev;
  };
  const otevritSeznam = (ano) => {
    seznamEl.hidden = !ano;
    hledatEl.setAttribute('aria-expanded', ano ? 'true' : 'false');
  };
  if (opts.tvorVyber) {
    opts.tvorVyber({
      kontejner: seznamEl, select: druhSel, hledat: hledatEl,
      tvorove: (window.MAPA && window.MAPA.bestiar) || vsechnyDruhy.map((d) => ({ id: Number(d.id), nazev: d.nazev })),
      hex: !!opts.hex,
      poVybrani: () => { otevritSeznam(false); popisVybraneho(); },
    });
  }
  hledatEl.addEventListener('focus', () => { hledatEl.select(); otevritSeznam(true); });
  hledatEl.addEventListener('input', () => otevritSeznam(true));
  document.addEventListener('pointerdown', (e) => {
    if (!seznamEl.hidden && !e.target.closest('.tb-vyber')) otevritSeznam(false);
  });
  popisVybraneho();

  // --- data ---
  async function nacist() {
    const d = await getJson('api/npc_zasoba.php?svet_id=' + svetId + '&mapa_id=' + mapaId);
    if (!d || d.error) { logLine('Zásoba NPC: ' + ((d && d.error) || 'nedostupná')); return; }
    stav = { zasoba: d.zasoba || [], naposledy: d.naposledy || [], lokace: d.lokace || [] };
    vykreslit();
  }

  const chipHtml = (c) => '<button class="tb-chip' + (c.jedinecny ? ' jedinecny' : '') + (c.naMape ? ' na-mape' : '') + '" type="button"'
    + ' data-tb-chip="' + esc(c.druhChip ? 'druh' : 'npc') + '"'
    + ' data-id="' + esc(c.id) + '"'
    + ' title="' + esc(c.popis) + '">'
    + (c.jedinecny ? '<span class="tb-hvezda" aria-hidden="true">★</span>' : '')
    + '<span class="tb-chip-jmeno">' + esc(c.jmeno) + '</span>'
    + (c.vedle ? '<span class="tb-chip-vedle">' + esc(c.vedle) + '</span>' : '')
    + (c.smazatelne ? '<span class="tb-chip-x" data-tb-smazat="' + esc(c.id) + '" title="Smazat NPC ze zásoby">×</span>' : '')
    + '</button>';

  function vykreslit() {
    const vZasobe = stav.zasoba.filter((n) => filtr === 'vse'
      || (filtr === 'tady' && n.lokace_mapa_id === mapaId)
      || (filtr === 'bez' && n.lokace_mapa_id === null));
    el('tbPocetZasoba').textContent = String(stav.zasoba.length);
    el('tbPocetLokace').textContent = String(stav.lokace.length);
    el('tbZasoba').innerHTML = vZasobe.length
      ? vZasobe.map((n) => chipHtml({
          id: n.instance_id, jmeno: n.nazev, vedle: n.lokace_nazev || 'bez místa', jedinecny: n.jedinecny, smazatelne: true,
          popis: n.druh + ', životy ' + n.hp + '/' + n.max_hp + (n.jedinecny ? ', vlastní čísla' : ', čísla druhu')
            + (n.lokace_nazev ? ', patří do: ' + n.lokace_nazev : ', bez místa') + ' — přetáhni na mapu',
        })).join('')
      : '<p class="tb-note">Zásoba je prázdná. Vyrob si NPC v záložce „Nový token“.</p>';
    el('tbLokaceSeznam').innerHTML = stav.lokace.length
      ? stav.lokace.map((n) => chipHtml({
          id: n.instance_id, jmeno: n.nazev, vedle: n.na_mape ? 'na mapě' : 'v zásobě', jedinecny: n.jedinecny, naMape: n.na_mape,
          popis: n.druh + (n.na_mape ? ' — právě stojí na téhle mapě' : ' — patří sem, ale nestojí tu; přetáhni na mapu'),
        })).join('')
      : '<p class="tb-note">K téhle lokaci není navázané žádné NPC.</p>';
    el('tbNaposledy').innerHTML = stav.naposledy.length
      ? stav.naposledy.map((d) => chipHtml({
          id: d.nestvura_id, jmeno: d.druh, vedle: d.kusu + '×', druhChip: true,
          popis: 'Ve světě už je ' + d.kusu + ' kusů — přetažením na mapu vyrobíš a položíš další',
        })).join('')
      : '<p class="tb-note">Ve světě ještě žádné NPC není.</p>';
  }

  // --- pokládání ---
  async function polozit(chip, clientX, clientY) {
    const bod = naMapu(clientX, clientY);
    const stred = gridStred(bod);
    const x = Math.round(stred.x), y = Math.round(stred.y);
    const telo = chip.druh
      ? { mapa_id: mapaId, typ_entity: 'nestvura', nestvura_id: chip.id, x, y }
      : { mapa_id: mapaId, typ_entity: 'nestvura', instance_id: chip.id, x, y };
    const d = await postJson('api/token_pridat.php', telo);
    if (d && d.error) { logLine('Položení nevyšlo: ' + d.error); return; }
    await nacist();
    obnovitStav();
  }

  function chipZUdalosti(t) {
    const b = t.closest('[data-tb-chip]');
    if (!b) return null;
    return { druh: b.dataset.tbChip === 'druh', id: parseInt(b.dataset.id, 10), el: b };
  }

  // Tažení: duch sleduje prst/myš, puštění nad mapou položí. Prahová vzdálenost odlišuje tažení od klepnutí.
  bar.addEventListener('pointerdown', (e) => {
    if (e.target.closest('[data-tb-smazat]')) return;
    const chip = chipZUdalosti(e.target);
    if (!chip) return;
    tahne = { chip, x0: e.clientX, y0: e.clientY, duch: null, pointerId: e.pointerId };
    try { chip.el.setPointerCapture(e.pointerId); } catch (err) { /* nevadí */ }
  });
  bar.addEventListener('pointermove', (e) => {
    if (!tahne || e.pointerId !== tahne.pointerId) return;
    const od = Math.hypot(e.clientX - tahne.x0, e.clientY - tahne.y0);
    if (!tahne.duch && od > 6) {
      tahne.duch = document.createElement('div');
      tahne.duch.className = 'tb-duch';
      tahne.duch.textContent = tahne.chip.el.querySelector('.tb-chip-jmeno').textContent;
      document.body.appendChild(tahne.duch);
      bar.classList.add('tahne');
    }
    if (tahne.duch) {
      tahne.duch.style.left = e.clientX + 'px';
      tahne.duch.style.top = e.clientY + 'px';
    }
  });
  const dotahnout = (e) => {
    if (!tahne || e.pointerId !== tahne.pointerId) return;
    const t = tahne;
    tahne = null;
    bar.classList.remove('tahne');
    if (t.duch) t.duch.remove();
    const nadMapou = viewport.contains(document.elementFromPoint(e.clientX, e.clientY) || document.body);
    if (t.duch && nadMapou) {
      polozit(t.chip, e.clientX, e.clientY);
      return;
    }
    if (!t.duch) {
      // klepnutí — chip se nabije, položí ho klepnutí do mapy
      nabito = nabito && nabito.id === t.chip.id && nabito.druh === t.chip.druh ? null : t.chip;
      bar.querySelectorAll('.tb-chip.nabito').forEach((c) => c.classList.remove('nabito'));
      if (nabito) {
        t.chip.el.classList.add('nabito');
        logLine('Klepni do mapy, kam položit „' + t.chip.el.querySelector('.tb-chip-jmeno').textContent + '“.');
      }
    }
  };
  bar.addEventListener('pointerup', dotahnout);
  bar.addEventListener('pointercancel', (e) => {
    if (!tahne || e.pointerId !== tahne.pointerId) return;
    if (tahne.duch) tahne.duch.remove();
    tahne = null;
    bar.classList.remove('tahne');
  });

  // Smazání ze zásoby (křížek na chipu).
  bar.addEventListener('click', async (e) => {
    const x = e.target.closest('[data-tb-smazat]');
    if (!x) return;
    e.preventDefault();
    e.stopPropagation();
    const d = await postJson('api/npc_smazat.php', { instance_id: parseInt(x.dataset.tbSmazat, 10) });
    if (d && d.error) { logLine('Smazání nevyšlo: ' + d.error); return; }
    nacist();
  });

  // Výroba dávky.
  el('tbVyrobit').addEventListener('click', async () => {
    const nestvuraId = parseInt(druhSel.value, 10);
    if (!nestvuraId) { logLine('Vyber druh.'); return; }
    const telo = {
      svet_id: svetId,
      nestvura_id: nestvuraId,
      pocet: Math.max(1, Math.min(50, parseInt(el('tbPocet').value, 10) || 1)),
      jedinecny: el('tbJedinecny').checked,
    };
    if (el('tbLokace').value === 'tady') telo.lokace_mapa_id = mapaId;
    const d = await postJson('api/npc_vyrobit.php', telo);
    if (d && d.error) { logLine('Výroba nevyšla: ' + d.error); return; }
    logLine('Vyrobeno do zásoby: ' + d.vyrobeno + '×.');
    await nacist();
    prepnout('zasoba');
  });

  return {
    prepnout: () => {
      const zavreno = bar.style.display === 'none';
      bar.style.display = zavreno ? '' : 'none';
      if (zavreno) nacist();
    },
    // Klepnutí do mapy, když je chip „nabitý“ — vrací true, když se token položil (mapa pak klik dál neřeší).
    klikDoMapy: (clientX, clientY) => {
      if (!nabito) return false;
      const chip = nabito;
      nabito = null;
      bar.querySelectorAll('.tb-chip.nabito').forEach((c) => c.classList.remove('nabito'));
      polozit(chip, clientX, clientY);
      return true;
    },
    maNabito: () => !!nabito,
    obnovit: nacist,
  };
}
