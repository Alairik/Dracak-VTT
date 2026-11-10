// Okno tvora (jen PJ): plovoucí karta u tokenu nestvůry s tím, co PJ potřebuje při hře.
//
// Proč vzniklo: postranní panel míchal dohromady tři různé věci — vlastnosti tvora, nastavení značky na mapě
// a nástroje PJ — a to podstatné (čím nestvůru vůbec jde zranit) v něm vůbec nebylo. Tohle okno ukazuje jen
// tvora a drží se u něj na mapě. Rozhodnutí uživatele 2026-11-10:
//   * odolnost a pohyblivost sem NEPATŘÍ (jsou v deníku nestvůry, ne v okně při hře);
//   * efekty se vyhodnocují samy a tady jsou jen VIDĚT — nasazují se kouzlem, lektvarem nebo akcí jiného tvora,
//     stejně jako u postav hráčů;
//   * vzhled tokenu (barva, obrázek, otočení, zámek) sem nepatří vůbec — je to značka, ne tvor.
//
// Čísla pro boj, výbavu a kořist spravuje dál deník nestvůry v postranním panelu; odtud na něj vede tlačítko.

export function initTvorOkno(opts) {
  const { mapaId, postJson, getJson, logLine, obnovitStav, otevritPanel } = opts;
  let box = null;
  let tokenId = null;
  let posun = null;

  const esc = (t) => String(t == null ? '' : t);
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

  function vytvorBox() {
    const b = el('div', 'tvor-okno');
    b.setAttribute('role', 'dialog');
    b.setAttribute('aria-label', 'Tvor');
    document.body.appendChild(b);
    return b;
  }

  // Okno se drží u tokenu, ale nesmí vylézt z obrazovky.
  function umistit(klientskyBod) {
    if (!box || !klientskyBod) return;
    const r = box.getBoundingClientRect();
    const x = Math.min(Math.max(8, klientskyBod.x + 24), window.innerWidth - r.width - 8);
    const y = Math.min(Math.max(8, klientskyBod.y - 40), window.innerHeight - r.height - 8);
    box.style.left = Math.round(x) + 'px';
    box.style.top = Math.round(y) + 'px';
  }

  function radekCipu(nadpis, polozky) {
    if (!polozky.length) return null;
    const wrap = el('div', 'tvor-sekce');
    if (nadpis) wrap.appendChild(el('h4', 'tvor-h', nadpis));
    const cipy = el('div', 'tvor-cipy');
    polozky.forEach((p) => {
      const c = el('span', 'tvor-cip' + (p.zvyraznit ? ' silny' : ''), p.text);
      if (p.popis) c.title = p.popis;
      cipy.appendChild(c);
    });
    wrap.appendChild(cipy);
    return wrap;
  }

  function vykreslit(t, d) {
    if (!box) return;
    box.textContent = '';

    // --- hlavička: jméno, druh, zavřít ---
    const hlava = el('div', 'tvor-hlava');
    const jmeno = el('div', 'tvor-jmeno');
    jmeno.appendChild(el('strong', null, t.label || (d && d.nazev) || 'Tvor'));
    if (d && d.nazev && d.nazev !== t.label) jmeno.appendChild(el('span', 'tvor-druh', d.nazev));
    hlava.appendChild(jmeno);
    const zav = el('button', 'tvor-zavrit', '✕');
    zav.type = 'button';
    zav.title = 'Zavřít';
    zav.addEventListener('click', zavrit);
    hlava.appendChild(zav);
    box.appendChild(hlava);

    // --- životy a bojeschopnost ---
    const hp = el('div', 'tvor-hp');
    const cislo = el('div', 'tvor-hp-cislo', (t.hp == null ? '?' : t.hp) + ' / ' + (t.max_hp == null ? '?' : t.max_hp));
    hp.appendChild(cislo);
    const tlacitka = el('div', 'tvor-hp-tl');
    [[-5, '−5'], [-1, '−1'], [1, '+1'], [5, '+5']].forEach(([delta, popis]) => {
      const b = el('button', 'btn btn-ghost', popis);
      b.type = 'button';
      b.addEventListener('click', async () => {
        const r = await postJson('api/hp_uprava.php', {
          mapa_id: mapaId, typ_entity: t.typ_entity, entita_id: t.entita_id, delta,
        });
        if (r && r.error) { logLine('Chyba: ' + r.error); return; }
        obnovitStav();
      });
      tlacitka.appendChild(b);
    });
    hp.appendChild(tlacitka);
    box.appendChild(hp);
    // Stav počítá server (dracak_vtt_bojeschopnost) — tady se jen pojmenuje, ať se pravidlo nepočítá dvakrát.
    const bs = d && d.bojeschopnost;
    if (bs) {
      let stav;
      let trida = '';
      if (bs.vyrazena) { stav = 'Vyřazená z boje'; trida = ' vyrazena'; }
      else if (bs.pod_tretinou) { stav = 'Pod třetinou životů · postih ' + bs.postih_aktualni + ' · vyřadí ji ' + bs.mez_vyrazeni + ' a méně'; trida = ' zranena'; }
      else { stav = 'Bojeschopná · vyřadí ji ' + bs.mez_vyrazeni + ' a méně'; }
      box.appendChild(el('div', 'tvor-stav' + trida, stav));
    }

    if (!d) {
      box.appendChild(el('p', 'tvor-pozn', 'Čísla tvora se nepodařilo načíst.'));
      return;
    }

    // --- útoky: to, čím hází ---
    const utoky = (d.staty && d.staty.utoky) || [];
    if (utoky.length) {
      const s = el('div', 'tvor-sekce');
      s.appendChild(el('h4', 'tvor-h', 'Útoky'));
      utoky.forEach((u) => {
        const r = el('div', 'tvor-utok');
        r.appendChild(el('span', 'tvor-utok-jmeno', u.nazev || 'útok'));
        r.appendChild(el('span', 'tvor-utok-cisla',
          'ÚČ ' + (u.uc == null ? '?' : u.uc) + (u.utocnost == null ? '' : ' · útočnost ' + u.utocnost)));
        s.appendChild(r);
      });
      box.appendChild(s);
    }

    // --- obrana a velikost (odolnost a pohyblivost sem vědomě nepatří — jsou v deníku) ---
    const st = d.staty || {};
    const obrana = [];
    if (st.oc !== null && st.oc !== undefined) obrana.push({ text: 'OČ ' + st.oc });
    if (st.oz !== null && st.oz !== undefined) obrana.push({ text: 'OZ ' + st.oz });
    if (st.velikost) obrana.push({ text: 'velikost ' + st.velikost });
    const obranaEl = radekCipu('Obrana', obrana);
    if (obranaEl) box.appendChild(obranaEl);

    // --- zranitelnosti: v boji první otázka, co na ni platí ---
    const zr = d.zranitelnosti || [];
    if (zr.length) {
      const s = el('div', 'tvor-sekce');
      s.appendChild(el('h4', 'tvor-h', 'Zraní ji'));
      const cipy = el('div', 'tvor-cipy');
      zr.forEach((z) => {
        const c = el('span', 'tvor-cip zranitelnost' + (z.nasobek ? ' nasobek' : ''),
          z.nazev + (z.nasobek ? ' ×' + z.nasobek : ''));
        c.title = (z.popis || z.nazev) + (z.nasobek ? '\nZranění se násobí ' + z.nasobek + ' (zaokrouhluj nahoru).' : '');
        cipy.appendChild(c);
      });
      s.appendChild(cipy);
      if (d.zranitelnosti_pozn) s.appendChild(el('p', 'tvor-pozn', d.zranitelnosti_pozn));
      box.appendChild(s);
    }

    // --- schopnosti (od migrace 0116 i s hodnotou: „Infravidění 30 sáhů“) ---
    const schopnostiEl = radekCipu('Schopnosti', (d.schopnosti || []).map((x) => ({ text: x })));
    if (schopnostiEl) box.appendChild(schopnostiEl);

    // --- efekty: jen k vidění, nasazují se kouzlem/lektvarem/akcí (rozhodnutí uživatele 2026-11-10) ---
    const efekty = t.efekty_detail || [];
    if (efekty.length) {
      const s = el('div', 'tvor-sekce');
      s.appendChild(el('h4', 'tvor-h', 'Působí na ni'));
      efekty.forEach((e) => {
        const r = el('div', 'tvor-efekt');
        r.appendChild(el('span', null, e.nazev + (e.zbyva_kol != null ? ' · ' + e.zbyva_kol + ' kol' : '')));
        if (e.text) r.title = e.text;
        const x = el('button', 'tvor-efekt-x', '✕');
        x.type = 'button';
        x.title = 'Sundat efekt';
        x.addEventListener('click', async () => {
          const v = await postJson('api/efekt_odebrat.php', { mapa_id: mapaId, aktivni_efekt_id: e.id });
          if (v && v.error) { logLine('Chyba: ' + v.error); return; }
          obnovitStav();
        });
        r.appendChild(x);
        s.appendChild(r);
      });
      box.appendChild(s);
    }

    // --- po boji: zkušenost a poklady (PJ je jinak hledá v knize) ---
    if (d.zkusenost || d.poklady) {
      const det = el('details', 'tvor-det');
      det.appendChild(el('summary', null, 'Po boji'));
      if (d.zkusenost) det.appendChild(el('div', 'tvor-radek', 'Zkušenost: ' + d.zkusenost));
      if (d.poklady) det.appendChild(el('div', 'tvor-radek', 'Poklady: ' + d.poklady));
      box.appendChild(det);
    }
    if (d.popis) {
      const det = el('details', 'tvor-det');
      det.appendChild(el('summary', null, 'Popis z bestiáře'));
      det.appendChild(el('p', 'tvor-popis', d.popis));
      box.appendChild(det);
    }

    // --- co zůstává v postranním panelu: čísla pro boj, výbava, kořist, vzhled tokenu ---
    const paticka = el('div', 'tvor-paticka');
    const vice = el('button', 'btn btn-ghost', 'Deník, výbava a kořist…');
    vice.type = 'button';
    vice.addEventListener('click', () => { if (otevritPanel) otevritPanel(t); });
    paticka.appendChild(vice);
    box.appendChild(paticka);
  }

  // Tažení za hlavičku — PJ si okno odsune, když mu stojí v cestě.
  document.addEventListener('pointerdown', (e) => {
    if (!box || !e.target.closest('.tvor-hlava') || e.target.closest('.tvor-zavrit')) return;
    const r = box.getBoundingClientRect();
    posun = { dx: e.clientX - r.left, dy: e.clientY - r.top };
    box.classList.add('tahne');
  });
  document.addEventListener('pointermove', (e) => {
    if (!posun || !box) return;
    box.style.left = Math.round(Math.min(Math.max(0, e.clientX - posun.dx), window.innerWidth - box.offsetWidth)) + 'px';
    box.style.top = Math.round(Math.min(Math.max(0, e.clientY - posun.dy), window.innerHeight - box.offsetHeight)) + 'px';
  });
  document.addEventListener('pointerup', () => {
    if (posun && box) box.classList.remove('tahne');
    posun = null;
  });

  return {
    async otevrit(t, klientskyBod) {
      if (!box) box = vytvorBox();
      tokenId = t.id;
      vykreslit(t, null);
      umistit(klientskyBod);
      const d = await getJson('api/nestvura_denik.php?mapa_id=' + mapaId + '&token_id=' + t.id);
      if (!box || tokenId !== t.id) return;          // mezitím se kliklo jinam
      vykreslit(t, d && !d.error ? d : null);
      umistit(klientskyBod);
    },
    // Po obnově stavu (zásah, efekt) se okno překreslí, aby čísla seděla.
    obnov(t) {
      if (!box || !t || t.id !== tokenId) return;
      getJson('api/nestvura_denik.php?mapa_id=' + mapaId + '&token_id=' + t.id).then((d) => {
        if (box && tokenId === t.id) vykreslit(t, d && !d.error ? d : null);
      });
    },
    zavrit,
    jeOtevrene: () => !!box,
    tokenId: () => tokenId,
  };
}
