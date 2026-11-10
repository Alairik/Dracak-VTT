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
// Připomínky uživatele k návrhu okna (plátno ShFfKGCh8AUcW3jK3ncVnQ, 2026-11-10) — všechny zapracované tady:
//   * vysvětlivky nejsou text v okně, ale bublina za otazníčkem u nadpisu sekce (a platí to pro celou aplikaci);
//   * zranitelnost ukazuje PÍSMENO A–P (+ ikonu), celé slovo vyjede na nájezd myší;
//   * životy: jen − a + hned u pruhu, a číslo se dá přepsat rukou (PJ komukoli, hráč u své postavy);
//   * velikost nepatří mezi čísla obrany (je v hlavičce jako druh tvora);
//   * dovednosti se nesmí zapomenout — sekce se jmenuje podle toho, co v ní opravdu je;
//   * odkaz na deník je v hlavičce, spodní tlačítko bylo jeho dvojník.
//
// Čísla pro boj, výbavu a kořist spravuje dál deník nestvůry v postranním panelu; odtud na něj vede odkaz Deník.

// Ikona k písmenu zranitelnosti — jen napovídá, písmeno zůstává hlavní (kody_zranitelnosti.kod:
// „Zažité písmeno A-P, MUSÍ zůstat viditelné v UI“). Kódy bez ikony se ukážou jen jako písmeno.
const ZR_IKONY = {
  A: 'M8 11V5.5a1.5 1.5 0 0 1 3 0V11m0-1.5a1.5 1.5 0 0 1 3 0V11m0-1a1.5 1.5 0 0 1 3 0v4a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5v-2',
  B: 'M14.5 17.5 3 6V3h3l11.5 11.5M13 19l6-6M16 16l4 4',
  C: 'M12 3l1.9 4.1L18 9l-4.1 1.9L12 15l-1.9-4.1L6 9l4.1-1.9L12 3zM19 17l.8 1.7 1.7.8-1.7.8-.8 1.7-.8-1.7-1.7-.8 1.7-.8L19 17z',
  D: 'M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 4v8',
  E: 'M12 2.7 6.7 8a7.5 7.5 0 1 0 10.6 0L12 2.7z',
  F: 'M4 12c2-5 5-7 8-7s6 2 8 7c-2 5-5 7-8 7s-6-2-8-7zm8-3v6',
  G: 'M12 2c2 4 5 5 5 9a5 5 0 0 1-10 0c0-2 1-3 2-4 .5 2 2 2 2 0 0-2-1-3 1-5z',
  H: 'M9 3h6v4l4 10a2 2 0 0 1-1.8 3H6.8A2 2 0 0 1 5 17L9 7V3zM9 3h6',
  O: 'M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z',
  P: 'M12 3a5.5 5.5 0 0 0-5 5.5c0 1.7.7 2.9 1.6 3.8V15h6.8v-2.7A5.5 5.5 0 0 0 12 3zM9.6 18h4.8',
};

export function initTvorOkno(opts) {
  const { mapaId, postJson, getJson, logLine, obnovitStav, otevritPanel } = opts;
  let box = null;
  let tokenId = null;
  let posun = null;

  const el = (tag, trida, text) => {
    const e = document.createElement(tag);
    if (trida) e.className = trida;
    if (text !== undefined) e.textContent = text;
    return e;
  };

  // Ikona 12 px ze statické cesty (žádná data z DB, takže innerHTML je tu bezpečné; viz CSP v includes/csp.php).
  function ikona(cesta) {
    const w = document.createElement('span');
    w.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
      + ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'
      + cesta + '"/></svg>';
    return w.firstChild;
  }

  // Otazníček s bublinou: nájezd myší ho ukáže sám (CSS), klepnutí na telefonu přepne třídu.
  function napoveda(text) {
    const b = el('button', 'tvor-hlp', '?');
    b.type = 'button';
    b.setAttribute('aria-label', 'Nápověda: ' + text);
    b.appendChild(el('span', 'tvor-tip', text));
    b.addEventListener('click', (e) => {
      e.stopPropagation();
      const bylo = b.classList.contains('otevreno');
      if (box) box.querySelectorAll('.tvor-hlp.otevreno').forEach((x) => x.classList.remove('otevreno'));
      if (!bylo) b.classList.add('otevreno');
    });
    return b;
  }

  function nadpis(text, napovedaText) {
    const h = el('h4', 'tvor-h');
    h.appendChild(document.createTextNode(text));
    if (napovedaText) h.appendChild(napoveda(napovedaText));
    return h;
  }

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

  function radekCipu(hlavicka, polozky) {
    if (!polozky.length) return null;
    const wrap = el('div', 'tvor-sekce');
    if (hlavicka) wrap.appendChild(hlavicka);
    const cipy = el('div', 'tvor-cipy');
    polozky.forEach((p) => {
      const c = el('span', 'tvor-cip' + (p.zvyraznit ? ' silny' : ''), p.text);
      if (p.popis) c.title = p.popis;
      cipy.appendChild(c);
    });
    wrap.appendChild(cipy);
    return wrap;
  }

  async function poslatHp(t, telo) {
    const r = await postJson('api/hp_uprava.php', Object.assign(
      { mapa_id: mapaId, typ_entity: t.typ_entity, entita_id: t.entita_id }, telo));
    if (r && r.error) { logLine('Chyba: ' + r.error); return false; }
    obnovitStav();
    return true;
  }

  function vykreslit(t, d) {
    if (!box) return;
    box.textContent = '';

    // --- hlavička: jméno, druh, odkaz na deník, zavřít ---
    const hlava = el('div', 'tvor-hlava');
    const jmeno = el('div', 'tvor-jmeno');
    jmeno.appendChild(el('strong', null, t.label || (d && d.nazev) || 'Tvor'));
    const druhRadek = [];
    if (d && d.nazev && d.nazev !== t.label) druhRadek.push(d.nazev);
    if (d && d.staty && d.staty.velikost) druhRadek.push('velikost ' + d.staty.velikost);
    if (druhRadek.length) jmeno.appendChild(el('span', 'tvor-druh', druhRadek.join(' · ')));
    hlava.appendChild(jmeno);
    const denik = el('button', 'tvor-denik', 'Deník ↗');
    denik.type = 'button';
    denik.title = 'Deník nestvůry, výbava a kořist v postranním panelu';
    denik.addEventListener('click', () => { if (otevritPanel) otevritPanel(t); });
    hlava.appendChild(denik);
    const zav = el('button', 'tvor-zavrit', '✕');
    zav.type = 'button';
    zav.title = 'Zavřít';
    zav.addEventListener('click', zavrit);
    hlava.appendChild(zav);
    box.appendChild(hlava);

    // --- životy: číslo jde přepsat, − a + jsou u pruhu, stav je štítek v nadpisu ---
    // Stav počítá server (dracak_vtt_bojeschopnost) — tady se jen pojmenuje, ať se pravidlo nepočítá dvakrát.
    const bs = d && d.bojeschopnost;
    const mez = bs ? bs.mez_vyrazeni : null;
    const hp = el('div', 'tvor-hp');
    const napHp = 'Značka na pruhu je mez vyřazení'
      + (mez != null ? ' — tady ' + mez + ' a méně' : '')
      + '. Číslo jde kliknutím přepsat: PJ komukoli, hráč u své vlastní postavy.'
      + (bs && bs.pod_tretinou ? '\nPostih za zranění pod třetinou životů hra počítá sama.' : '');
    const hlavickaHp = nadpis('Životy', napHp);
    if (bs) {
      let stav = 'Bojeschopná';
      let trida = '';
      if (bs.vyrazena) { stav = 'Vyřazená z boje'; trida = ' vyrazena'; }
      else if (bs.pod_tretinou) { stav = 'Pod třetinou · postih ' + bs.postih_aktualni; trida = ' zranena'; }
      hlavickaHp.appendChild(el('span', 'tvor-stav' + trida, stav));
    }
    hp.appendChild(hlavickaHp);

    const cislo = el('div', 'tvor-hp-cislo');
    const vstup = el('input', 'tvor-hp-vstup');
    vstup.type = 'text';
    vstup.inputMode = 'numeric';
    vstup.value = t.hp == null ? '?' : String(t.hp);
    vstup.title = 'Klikni a přepiš';
    vstup.setAttribute('aria-label', 'Aktuální životy');
    const ulozitVstup = async () => {
      const v = vstup.value.trim();
      if (!/^\d{1,5}$/.test(v) || Number(v) === t.hp) { vstup.value = t.hp == null ? '?' : String(t.hp); return; }
      if (!await poslatHp(t, { nastavit: Number(v) })) vstup.value = t.hp == null ? '?' : String(t.hp);
    };
    vstup.addEventListener('blur', ulozitVstup);
    vstup.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); vstup.blur(); }
      if (e.key === 'Escape') { vstup.value = t.hp == null ? '?' : String(t.hp); vstup.blur(); }
    });
    cislo.appendChild(vstup);
    cislo.appendChild(el('span', 'tvor-hp-max', '/ ' + (t.max_hp == null ? '?' : t.max_hp) + ' životů'));
    hp.appendChild(cislo);

    const tl = el('div', 'tvor-hp-tl');
    const tlacitko = (delta, popis, nazev) => {
      const b = el('button', 'tvor-hpb', popis);
      b.type = 'button';
      b.setAttribute('aria-label', nazev);
      b.addEventListener('click', () => poslatHp(t, { delta }));
      return b;
    };
    tl.appendChild(tlacitko(-1, '−', 'O jeden život méně'));
    const max = Number(t.max_hp) > 0 ? Number(t.max_hp) : 0;
    if (max > 0 && t.hp != null) {
      const trida = bs && bs.vyrazena ? ' vyrazena' : (bs && bs.pod_tretinou ? ' zranena' : '');
      const pruh = el('div', 'tvor-pruh' + trida);
      const vypln = el('i');
      vypln.style.width = Math.max(0, Math.min(100, (Number(t.hp) / max) * 100)) + '%';
      pruh.appendChild(vypln);
      if (mez != null && mez > 0) {
        const znacka = el('b');
        znacka.style.left = Math.max(0, Math.min(100, (mez / max) * 100)) + '%';
        znacka.title = 'Mez vyřazení: ' + mez + ' a méně';
        pruh.appendChild(znacka);
      }
      tl.appendChild(pruh);
    } else {
      tl.appendChild(el('span', 'tvor-hp-max', max > 0 ? '' : 'maximum životů kniha neuvádí'));
    }
    tl.appendChild(tlacitko(1, '+', 'O jeden život víc'));
    hp.appendChild(tl);
    box.appendChild(hp);

    if (!d) {
      box.appendChild(el('p', 'tvor-pozn', 'Čísla tvora se nepodařilo načíst.'));
      return;
    }

    // --- útoky: to, čím hází ---
    const utoky = (d.staty && d.staty.utoky) || [];
    if (utoky.length) {
      const s = el('div', 'tvor-sekce');
      s.appendChild(nadpis('Útoky', 'Hází se v panelu souboje, kde je i obrana a pořadí tahů.'));
      utoky.forEach((u) => {
        const r = el('div', 'tvor-utok');
        r.appendChild(el('span', 'tvor-utok-jmeno', u.nazev || 'útok'));
        r.appendChild(el('span', 'tvor-utok-cisla',
          'ÚČ ' + (u.uc == null ? '?' : u.uc) + (u.utocnost == null ? '' : ' · útočnost ' + u.utocnost)));
        s.appendChild(r);
      });
      box.appendChild(s);
    }

    // --- obrana (odolnost a pohyblivost sem vědomě nepatří — jsou v deníku; velikost je v hlavičce) ---
    const st = d.staty || {};
    const obrana = [];
    if (st.oc !== null && st.oc !== undefined) obrana.push({ text: 'OČ ' + st.oc });
    if (st.oz !== null && st.oz !== undefined) obrana.push({ text: 'OZ ' + st.oz });
    const obranaEl = radekCipu(nadpis('Obrana'), obrana);
    if (obranaEl) box.appendChild(obranaEl);

    // --- zranitelnosti: v boji první otázka, co na ni platí ---
    const zr = d.zranitelnosti || [];
    if (zr.length) {
      const s = el('div', 'tvor-sekce');
      s.appendChild(nadpis('Zraní ji',
        'Co v seznamu není, tvorovi podle pravidel neublíží. Písmeno je kód z bestiáře, slovo k němu vyjede na nájezd.'));
      const cipy = el('div', 'tvor-cipy');
      zr.forEach((z) => {
        const kod = (z.kod || '').toUpperCase();
        const c = el('span', 'tvor-zr' + (z.nasobek ? ' nasobek' : ''));
        if (ZR_IKONY[kod]) c.appendChild(ikona(ZR_IKONY[kod]));
        c.appendChild(document.createTextNode(kod || z.nazev));
        if (z.nasobek) c.appendChild(el('sup', null, '×' + z.nasobek));
        const slovo = (kod ? kod + ' — ' : '') + z.nazev + (z.nasobek ? ' ×' + z.nasobek : '');
        c.title = slovo + (z.popis ? '\n' + z.popis : '')
          + (z.nasobek ? '\nZranění se násobí ' + z.nasobek + ' (zaokrouhluj nahoru).' : '');
        c.setAttribute('aria-label', slovo);
        cipy.appendChild(c);
      });
      s.appendChild(cipy);
      box.appendChild(s);
    }

    // --- schopnosti a dovednosti (od migrace 0116 i s hodnotou: „Infravidění 30 sáhů“).
    //     Nadpis se jmenuje podle toho, co v sekci opravdu je — dovednosti se nesmí ztratit pod slovem „schopnosti“. ---
    const detail = d.schopnosti_detail && d.schopnosti_detail.length
      ? d.schopnosti_detail
      : (d.schopnosti || []).map((x) => ({ text: x, druh: 'schopnost' }));
    if (detail.length) {
      const maSchopnost = detail.some((x) => x.druh !== 'dovednost');
      const maDovednost = detail.some((x) => x.druh === 'dovednost');
      const jmeno2 = maSchopnost && maDovednost ? 'Schopnosti a dovednosti'
        : (maDovednost ? 'Dovednosti' : 'Schopnosti');
      box.appendChild(radekCipu(nadpis(jmeno2), detail.map((x) => ({
        text: x.text,
        popis: x.druh === 'dovednost' ? 'Dovednost' : 'Zvláštní schopnost',
      }))));
    }

    // --- efekty: jen k vidění, nasazují se kouzlem/lektvarem/akcí (rozhodnutí uživatele 2026-11-10) ---
    const efekty = t.efekty_detail || [];
    if (efekty.length) {
      const s = el('div', 'tvor-sekce');
      s.appendChild(nadpis('Působí na ni',
        'Efekty nasazuje kouzlo, lektvar nebo akce jiného tvora a hra je vyhodnocuje sama. Tady se jen vidí a dají sundat.'));
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

    // --- zkušenost a poklady: PJ je jinak hledá v knize, proto rovnou vidět ---
    if (d.zkusenost || d.poklady) {
      const s = el('div', 'tvor-sekce');
      const r = el('div', 'tvor-utok');
      if (d.zkusenost) r.appendChild(el('span', null, 'Zkušenost ' + d.zkusenost));
      if (d.poklady) r.appendChild(el('span', 'tvor-utok-cisla', 'Poklady ' + d.poklady));
      s.appendChild(r);
      box.appendChild(s);
    }
    if (d.popis) {
      const det = el('details', 'tvor-det');
      det.appendChild(el('summary', null, 'Popis z bestiáře'));
      det.appendChild(el('p', 'tvor-popis', d.popis));
      box.appendChild(det);
    }
  }

  // Tažení za hlavičku — PJ si okno odsune, když mu stojí v cestě.
  document.addEventListener('pointerdown', (e) => {
    if (!box) return;
    if (!e.target.closest('.tvor-hlp')) box.querySelectorAll('.tvor-hlp.otevreno').forEach((x) => x.classList.remove('otevreno'));
    if (!e.target.closest('.tvor-hlava') || e.target.closest('.tvor-zavrit') || e.target.closest('.tvor-denik')) return;
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
