// Malá karta postavy u tokenu (rozhodnutí uživatele 2026-11-12): jeden klik na token postavy neotevírá plný deník,
// ale jen tuhle kartu — životy, magenergie, útočné číslo se zbraní v ruce a obranné číslo. Plný (rychlý) deník
// otevře dvojklik na token nebo tlačítko „Deník“ tady. Karta se zavře křížkem, klikem mimo ni nebo klávesou Esc.
// Čísla dodává hra/api/postava_mini.php (stejný zdroj, ze kterého počítá souboj); karta je jen ke čtení.

export function initPostavaMini(opts) {
  const { mapaId, getJson, otevritDenik } = opts;
  let box = null;
  let tokenId = null;

  const el = (tag, trida, text) => {
    const e = document.createElement(tag);
    if (trida) e.className = trida;
    if (text !== undefined) e.textContent = text;
    return e;
  };

  function zavrit() {
    if (box) { box.remove(); box = null; }
    tokenId = null;
  }

  // Karta se drží u tokenu, ale nesmí vylézt z obrazovky (na telefonu ji CSS přilepí dolů přes šířku).
  function umistit(bod) {
    if (!box || !bod) return;
    const r = box.getBoundingClientRect();
    const x = Math.min(Math.max(8, bod.x + 24), window.innerWidth - r.width - 8);
    const y = Math.min(Math.max(8, bod.y - 30), window.innerHeight - r.height - 8);
    box.style.left = Math.round(x) + 'px';
    box.style.top = Math.round(y) + 'px';
  }

  function pruh(nazev, aktualni, max, trida) {
    const r = el('div', 'pmini-radek');
    r.appendChild(el('span', 'pmini-popis', nazev));
    const p = el('div', 'pmini-pruh' + (trida ? ' ' + trida : ''));
    const i = document.createElement('i');
    i.style.width = (max > 0 ? Math.max(0, Math.min(100, Math.round(aktualni / max * 100))) : 0) + '%';
    p.appendChild(i);
    p.setAttribute('role', 'img');
    p.setAttribute('aria-label', nazev + ' ' + aktualni + ' z ' + max);
    r.appendChild(p);
    r.appendChild(el('span', 'pmini-cislo', aktualni + ' / ' + max));
    return r;
  }

  function cislo(zkratka, hodnota, popis) {
    const c = el('div', 'pmini-stat');
    c.appendChild(el('span', 'pmini-zkr', zkratka));
    c.appendChild(el('strong', null, hodnota === null || hodnota === undefined ? '–' : String(hodnota)));
    if (popis) c.appendChild(el('span', 'pmini-pozn', popis));
    return c;
  }

  function vykreslit(t, d) {
    if (!box) return;
    box.textContent = '';
    const hlava = el('div', 'pmini-hlava');
    const jmeno = el('div', 'pmini-jmeno');
    jmeno.appendChild(el('strong', null, (d && d.nazev) || t.label || 'Postava'));
    if (d && d.podtitul) jmeno.appendChild(el('span', 'pmini-druh', d.podtitul));
    hlava.appendChild(jmeno);
    const denik = el('button', 'pmini-denik', 'Deník');
    denik.type = 'button';
    denik.title = 'Otevřít deník postavy (také dvojklikem na token)';
    denik.addEventListener('click', () => { const tt = t; zavrit(); otevritDenik(tt); });
    hlava.appendChild(denik);
    const zav = el('button', 'pmini-zavrit', '✕');
    zav.type = 'button';
    zav.setAttribute('aria-label', 'Zavřít');
    zav.addEventListener('click', zavrit);
    hlava.appendChild(zav);
    box.appendChild(hlava);

    if (!d) { box.appendChild(el('p', 'pmini-ceka', 'Načítám…')); return; }
    const telo = el('div', 'pmini-telo');
    let tridaHp = '';
    if (d.stav && d.stav.vyrazena) tridaHp = 'vyrazena';
    else if (d.stav && d.stav.pod_tretinou) tridaHp = 'zranena';
    telo.appendChild(pruh('Životy', d.hp, d.max_hp, tridaHp));
    if (d.max_mag !== null && d.max_mag > 0) telo.appendChild(pruh('Magenergie', d.mag || 0, d.max_mag, 'mag'));
    const staty = el('div', 'pmini-staty');
    staty.appendChild(cislo('ÚČ', d.zbran ? d.zbran.uc : (d.pest !== null && d.pest !== undefined ? d.pest : null), d.zbran ? d.zbran.nazev : 'beze zbraně (pěst)'));
    staty.appendChild(cislo('OČ', d.oc, null));
    telo.appendChild(staty);
    box.appendChild(telo);
  }

  // Klik mimo kartu ji zavře. Klik na token nechává být — ten si kartu sám přepne (nebo dvojklikem otevře deník).
  document.addEventListener('pointerdown', (e) => {
    if (!box || box.contains(e.target)) return;
    if (e.target.closest && e.target.closest('.vtt-token')) return;
    zavrit();
  }, true);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && box) zavrit(); });

  return {
    async otevrit(t, bod) {
      if (!box) {
        box = el('div', 'pmini');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', 'Postava');
        document.body.appendChild(box);
      }
      tokenId = t.id;
      vykreslit(t, null);
      umistit(bod);
      const d = await getJson('api/postava_mini.php?mapa_id=' + mapaId + '&token_id=' + t.id);
      if (!box || tokenId !== t.id) return;          // mezitím se kliklo jinam
      if (!d || d.error) { zavrit(); return; }
      vykreslit(t, d);
      umistit(bod);
    },
    // Po obnově stavu (zásah, léčení) ať čísla sedí; zmizel-li token, karta se zavře.
    obnov(t) {
      if (!box) return;
      if (!t || t.id !== tokenId) { if (!t) zavrit(); return; }
      getJson('api/postava_mini.php?mapa_id=' + mapaId + '&token_id=' + t.id).then((d) => {
        if (box && tokenId === t.id && d && !d.error) vykreslit(t, d);
      });
    },
    zavrit,
    jeOtevrene: () => !!box,
    tokenId: () => tokenId,
  };
}
