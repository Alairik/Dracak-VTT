// Raid panel (jen PJ; hra/mapa.php, #raidPanel): plovoucí okno s boxy všech tvorů na mapě — avatar, jméno, životy, magenergie, efekty, iniciativa.
// Řazení: bez souboje nejdřív nestvůry a postavy PJ, potom postavy hráčů; v souboji podle iniciativy (pořadí z mapa_stav.php), kdo ještě
// nemá výsledek, jde za nimi (nestvůry, pak hráči), vyřazení na konec skupiny. Boxy jdou přetahovat (ruční pořadí se pamatuje; po novém kole
// iniciativy nebo při jiné skupině tvorů se zahodí), okno jde přesunout za hlavičku, sbalit a přepnout na kompaktní řádky.
// Myš nad boxem zvýrazní token na mapě a naopak (zvyraznit / zvyraznenyToken). Veškerý text z databáze jde do DOM přes textContent.

const KLIC = 'dracak_raid_panel';
const SIRKA = {plne: 340, kompakt: 296};
const HLAVA_VIDITELNA = 120;
const OKRAJ = 8;

function nacti() {
  try { return JSON.parse(localStorage.getItem(KLIC) || '{}') || {}; } catch (e) { return {}; }
}
function uloz(stav) {
  try { localStorage.setItem(KLIC, JSON.stringify(stav)); } catch (e) { /* soukromé okno, zablokované úložiště */ }
}

function el(tag, trida, text) {
  const e = document.createElement(tag);
  if (trida) e.className = trida;
  if (text !== undefined && text !== null) e.textContent = text;
  return e;
}

// Barvy avataru podle tokenu: stálý odstín z id, ať jsou dva goblini rozeznatelní i bez obrázku.
function odstin(id) { return (Number(id) * 47) % 360; }

// ctx: {mapaId, getTokeny, getKolo, getIniciativa, getUcastnici, zvyraznit(tokenId, zap), vybrat(tokenId), obrazekUrl(id) | null}
export function initRaidPanel(ctx) {
  const okno = document.getElementById('raidPanel');
  if (!okno) return null;
  const hlava = document.getElementById('raidHlava');
  const titul = document.getElementById('raidTitul');
  const podtitul = document.getElementById('raidPodtitul');
  const seznam = document.getElementById('raidSeznam');
  const btnRezim = document.getElementById('raidRezim');
  const btnSbalit = document.getElementById('raidSbalit');
  const btnZavrit = document.getElementById('raidZavrit');
  const btnReset = document.getElementById('raidReset');
  const tlacitko = document.getElementById('tb-raid');
  const stav = Object.assign({otevreno: false, x: null, y: null, kompakt: false, sbaleno: false}, nacti());
  const rucni = (stav.poradi && typeof stav.poradi === 'object') ? stav.poradi : {};   // {mapaId: {klic, ids}}
  stav.poradi = rucni;
  let zvyraznenyId = null;       // token zvýrazněný z mapy (myš nad tokenem) nebo z boxu
  let tazeneId = null;
  let casovac = null;

  // ----- poloha okna -----
  function jeCislo(v) { return typeof v === 'number' && Number.isFinite(v); }
  function ohranic() {
    const sw = window.innerWidth, sh = window.innerHeight;
    const w = stav.kompakt ? SIRKA.kompakt : SIRKA.plne;
    // Poprvé vlevo za lištou s nástroji — vpravo se otevírá detail tokenu a panel pořadí tahů, ať si nepřekážejí; dál si okno PJ posune sám.
    if (!jeCislo(stav.x)) {
      const lista = document.querySelector('.toolbar');
      stav.x = lista ? Math.round(lista.getBoundingClientRect().right) + 10 : 88;
    }
    if (!jeCislo(stav.y)) stav.y = 70;
    stav.x = Math.max(HLAVA_VIDITELNA - w, Math.min(sw - HLAVA_VIDITELNA, stav.x));
    stav.y = Math.max(0, Math.min(sh - 60, stav.y));
  }
  function aplikuj() {
    ohranic();
    okno.style.display = stav.otevreno ? '' : 'none';
    okno.classList.toggle('kompakt', !!stav.kompakt);
    okno.classList.toggle('sbaleno', !!stav.sbaleno);
    okno.style.left = stav.x + 'px';
    okno.style.top = stav.y + 'px';
    okno.style.width = (stav.kompakt ? SIRKA.kompakt : SIRKA.plne) + 'px';
    okno.style.maxHeight = Math.max(160, window.innerHeight - stav.y - OKRAJ) + 'px';
    if (tlacitko) tlacitko.classList.toggle('aktivni', !!stav.otevreno);
    btnRezim.textContent = stav.kompakt ? 'Plné' : 'Kompakt';
    btnRezim.title = stav.kompakt ? 'Přepnout na plné boxy' : 'Přepnout na kompaktní řádky';
    btnSbalit.setAttribute('aria-pressed', stav.sbaleno ? 'true' : 'false');
    btnSbalit.title = stav.sbaleno ? 'Rozbalit' : 'Sbalit na hlavičku';
  }

  hlava.addEventListener('pointerdown', (e) => {
    if (e.button !== 0 || e.target.closest('button, a, select')) return;
    e.preventDefault();
    hlava.setPointerCapture(e.pointerId);
    const x0 = e.clientX - stav.x, y0 = e.clientY - stav.y;
    okno.classList.add('tahne');
    const krok = (ev) => { stav.x = ev.clientX - x0; stav.y = ev.clientY - y0; aplikuj(); };
    const konec = () => {
      okno.classList.remove('tahne');
      hlava.removeEventListener('pointermove', krok);
      hlava.removeEventListener('pointerup', konec);
      hlava.removeEventListener('pointercancel', konec);
      uloz(stav);
    };
    hlava.addEventListener('pointermove', krok);
    hlava.addEventListener('pointerup', konec);
    hlava.addEventListener('pointercancel', konec);
  });
  window.addEventListener('resize', aplikuj);

  // ----- pořadí -----
  function jeTvor(t) { return t.typ_entity === 'postava' || t.typ_entity === 'nestvura_instance'; }
  function vyrazen(t) { return t.hp !== null && t.hp !== undefined && t.hp <= 0; }

  // Pořadí podle pravidel výše; vrací {radky: [token…], souboj: bool, klic: řetězec popisující „skupinu“ pro ruční pořadí}.
  function seradit() {
    const tvorove = ctx.getTokeny().filter(jeTvor);
    const kolo = ctx.getKolo();
    const ini = ctx.getIniciativa() || [];
    const poId = new Map(tvorove.map((t) => [t.id, t]));
    const strana = (t) => (t.strana === 'hrac' ? 1 : 0);
    const posledni = (a, b) => (vyrazen(a) ? 1 : 0) - (vyrazen(b) ? 1 : 0);
    const bezIni = (arr) => arr.slice().sort((a, b) => strana(a) - strana(b) || posledni(a, b));
    let radky, souboj = false, klic;
    if (kolo && ini.length) {
      souboj = true;
      const podleIni = ini.map((r) => poId.get(r.token_id)).filter(Boolean);
      const mamIni = new Set(podleIni.map((t) => t.id));
      // Vyřazení se v pořadí neposouvají na konec: jejich výsledek iniciativy pořád platí a PJ je vidí tam, kde stojí; šedé jsou i tak.
      radky = podleIni.concat(bezIni(tvorove.filter((t) => !mamIni.has(t.id))));
      klic = 'souboj:' + kolo.cislo_kola + ':' + radky.map((t) => t.id).join(',');
    } else {
      // Nestvůry a postavy PJ nahoře, pak postavy hráčů; vyřazení vždy na konec své skupiny.
      radky = tvorove.slice().sort((a, b) => strana(a) - strana(b) || posledni(a, b));
      klic = 'priprava';
    }
    // Ruční pořadí: platí, jen když sedí skupina (souboj: kolo + tvorové; příprava: vždy, nové tokeny se přidají na konec své skupiny).
    const r = rucni[ctx.mapaId];
    if (r && r.klic === klic) {
      const idx = new Map(r.ids.map((id, i) => [id, i]));
      if (klic === 'priprava') {
        const maRucne = radky.filter((t) => idx.has(t.id)).sort((a, b) => idx.get(a.id) - idx.get(b.id));
        const nove = radky.filter((t) => !idx.has(t.id));
        radky = maRucne.concat(nove);
      } else {
        radky = radky.slice().sort((a, b) => idx.get(a.id) - idx.get(b.id));
      }
    }
    return {radky, souboj, klic, iniPoId: new Map(ini.map((x) => [x.token_id, x.vysledek])), rucne: !!(r && r.klic === klic)};
  }

  // ----- kreslení -----
  function procento(t) {
    if (t.hp === null || t.hp === undefined || !t.max_hp) return 0;
    return Math.max(0, Math.min(100, Math.round(t.hp / t.max_hp * 100)));
  }
  function barvaHp(p) { return p > 60 ? '#5cae6a' : (p > 30 ? '#e0a73a' : '#d2503f'); }

  function avatar(t, velikost) {
    const a = el('div', 'raid-avatar' + (t.strana === 'hrac' ? ' hrac' : ' pj'));
    a.style.width = a.style.height = velikost + 'px';
    const obr = ctx.obrazekUrl ? ctx.obrazekUrl(t.obrazek_id) : null;
    if (obr) {
      a.style.backgroundImage = "url('" + obr + "')";
      a.classList.add('s-obrazkem');
    } else {
      const h = odstin(t.id);
      a.style.background = 'radial-gradient(circle at 35% 30%, hsl(' + h + ' 35% 52%), hsl(' + h + ' 40% 28%))';
      a.textContent = (t.label || '?').trim().charAt(0).toUpperCase();
      a.style.fontSize = Math.round(velikost * 0.42) + 'px';
    }
    return a;
  }

  function stitky(t) {
    const out = [];
    (t.efekty_detail || []).forEach((e) => {
      const kola = e.zbyva_kol === null || e.zbyva_kol === undefined ? '' : ' · ' + e.zbyva_kol + (e.zbyva_kol === 1 ? ' kolo' : (e.zbyva_kol < 5 ? ' kola' : ' kol'));
      out.push({text: e.nazev + kola, druh: 'fx'});
    });
    if (!(t.efekty_detail || []).length && (t.efekty || []).length) (t.efekty || []).forEach((n) => out.push({text: n, druh: 'fx'}));
    if (t.unava && t.unava > 0) out.push({text: 'Únava ' + t.unava, druh: 'warn'});
    return out;
  }

  function box(t, info, aktivniId) {
    const kompakt = !!stav.kompakt;
    const naTahu = aktivniId === t.id;
    const b = el('div', 'raid-box' + (kompakt ? ' kompakt' : '') + (naTahu ? ' na-tahu' : '') + (vyrazen(t) ? ' vyrazen' : '')
      + (zvyraznenyId === t.id ? ' zvyrazneny' : '') + (ctx.jeVybrany(t.id) ? ' vybrany' : '') + (tazeneId === t.id ? ' tazeny' : ''));
    b.dataset.tokenId = String(t.id);
    b.draggable = true;
    b.append(Object.assign(el('span', 'raid-grip'), {textContent: '⠿'}));
    b.lastChild.setAttribute('aria-hidden', 'true');

    const av = el('div', 'raid-avatar-obal');
    av.append(avatar(t, kompakt ? 30 : 50));
    if (!kompakt) {
      const odznaky = el('div', 'raid-odznaky');
      if (!t.viditelny) odznaky.append(Object.assign(el('span', 'raid-odznak', '◌'), {title: 'Skrytý před hráči'}));
      if (t.svetlo && t.svetlo_typ !== 'tma' && t.svetlo_typ !== 'temnota') odznaky.append(Object.assign(el('span', 'raid-odznak', '✦'), {title: 'Svítí'}));
      if (vyrazen(t)) odznaky.append(Object.assign(el('span', 'raid-odznak', '✕'), {title: 'Vyřazen'}));
      if (odznaky.childNodes.length) av.append(odznaky);
    }
    b.append(av);

    const telo = el('div', 'raid-telo');
    const hlavicka = el('div', 'raid-jmeno-radek');
    hlavicka.append(el('div', 'raid-jmeno', t.label || '?'));
    if (!kompakt) hlavicka.append(el('div', 'raid-podtitul', t.podtitul || ''));
    if (kompakt) {
      telo.append(hlavicka.firstChild);
    } else {
      telo.append(hlavicka);
    }
    const p = procento(t);
    const hp = el('div', 'raid-hp');
    const vypln = el('div', 'raid-hp-vypln');
    vypln.style.width = p + '%';
    vypln.style.background = barvaHp(p);
    hp.append(vypln, el('div', 'raid-hp-text', t.hp === null || t.hp === undefined ? '—' : (t.hp + (t.max_hp ? ' / ' + t.max_hp : ''))));
    if (kompakt) {
      b.append(telo, hp);
    } else {
      telo.append(hp);
      if (t.max_magenergie) {
        const mag = el('div', 'raid-mag');
        const dr = el('div', 'raid-mag-draha');
        const mv = el('div', 'raid-mag-vypln');
        mv.style.width = Math.max(0, Math.min(100, Math.round((t.magenergie || 0) / t.max_magenergie * 100))) + '%';
        dr.append(mv);
        mag.append(dr, el('div', 'raid-mag-text', (t.magenergie || 0) + ' / ' + t.max_magenergie));
        telo.append(mag);
      }
      const st = stitky(t);
      if (st.length) {
        const radek = el('div', 'raid-stitky');
        st.slice(0, 3).forEach((s) => radek.append(Object.assign(el('span', 'raid-stitek ' + s.druh, s.text), {title: s.text})));
        if (st.length > 3) radek.append(Object.assign(el('span', 'raid-stitek fx', '+' + (st.length - 3)), {title: st.slice(3).map((s) => s.text).join('\n')}));
        telo.append(radek);
      }
      b.append(telo);
    }
    if (info.souboj) {
      const vysledek = info.iniPoId.get(t.id);
      const ini = el('div', 'raid-ini');
      ini.append(el('div', 'raid-ini-popisek', 'INI'), el('div', 'raid-ini-cislo', vysledek === undefined || vysledek === null ? '—' : String(vysledek)));
      b.append(ini);
    }
    return b;
  }

  function prekresli() {
    if (!stav.otevreno || tazeneId !== null) return;
    const info = seradit();
    const kolo = ctx.getKolo();
    const aktivniId = kolo ? kolo.aktivni_token_id : null;
    titul.textContent = info.souboj ? 'Souboj · kolo ' + kolo.cislo_kola : 'Na mapě';
    podtitul.textContent = info.souboj ? 'Podle iniciativy' : 'Nestvůry a NPC nahoře, pak družina';
    btnReset.style.display = info.rucne ? '' : 'none';
    seznam.replaceChildren();
    if (!info.radky.length) {
      seznam.append(el('div', 'raid-prazdno', 'Na mapě zatím nikdo není.'));
      return;
    }
    // Bez souboje oddělíme nestvůry od družiny nadpisem; ruční pořadí může skupiny promíchat — pak nadpisy nedávají smysl.
    let predchoziStrana = null;
    info.radky.forEach((t) => {
      if (!info.souboj && !info.rucne) {
        const s = t.strana === 'hrac' ? 'hrac' : 'pj';
        if (s !== predchoziStrana) {
          const pocet = info.radky.filter((x) => (x.strana === 'hrac' ? 'hrac' : 'pj') === s).length;
          seznam.append(el('div', 'raid-skupina', (s === 'pj' ? 'Nestvůry a NPC · ' : 'Družina · ') + pocet));
          predchoziStrana = s;
        }
      }
      seznam.append(box(t, info, aktivniId));
    });
  }
  function planuj() {
    if (casovac) return;
    casovac = setTimeout(() => { casovac = null; prekresli(); }, 90);
  }

  // ----- zvýraznění a klik -----
  function nastavZvyrazneni(id) {
    if (zvyraznenyId === id) return;
    const stary = zvyraznenyId;
    zvyraznenyId = id;
    seznam.querySelectorAll('.raid-box').forEach((b) => {
      const bid = parseInt(b.dataset.tokenId, 10);
      if (bid === id) b.classList.add('zvyrazneny'); else if (bid === stary) b.classList.remove('zvyrazneny');
    });
  }
  seznam.addEventListener('mouseover', (e) => {
    const b = e.target.closest('.raid-box');
    if (!b) return;
    const id = parseInt(b.dataset.tokenId, 10);
    if (zvyraznenyId === id) return;
    nastavZvyrazneni(id);
    ctx.zvyraznit(id, true);
  });
  seznam.addEventListener('mouseout', (e) => {
    const b = e.target.closest('.raid-box');
    if (!b || b.contains(e.relatedTarget)) return;
    const id = parseInt(b.dataset.tokenId, 10);
    nastavZvyrazneni(null);
    ctx.zvyraznit(id, false);
  });
  seznam.addEventListener('click', (e) => {
    const b = e.target.closest('.raid-box');
    if (b) ctx.vybrat(parseInt(b.dataset.tokenId, 10));
  });

  // ----- přetahování boxů (HTML5 drag & drop) -----
  function znacka() {
    let z = seznam.querySelector('.raid-znacka');
    if (!z) z = el('div', 'raid-znacka');
    return z;
  }
  seznam.addEventListener('dragstart', (e) => {
    const b = e.target.closest('.raid-box');
    if (!b) return;
    tazeneId = parseInt(b.dataset.tokenId, 10);
    b.classList.add('tazeny');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', String(tazeneId)); } catch (err) { /* starší prohlížeč */ }
  });
  seznam.addEventListener('dragover', (e) => {
    if (tazeneId === null) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const cil = e.target.closest('.raid-box');
    const z = znacka();
    if (!cil) {
      if (e.target === seznam) seznam.append(z);
      return;
    }
    const r = cil.getBoundingClientRect();
    const dolu = (e.clientY - r.top) > r.height / 2;
    if (dolu) cil.after(z); else cil.before(z);
  });
  seznam.addEventListener('drop', (e) => {
    if (tazeneId === null) return;
    e.preventDefault();
    const z = seznam.querySelector('.raid-znacka');
    const id = tazeneId;
    const info = seradit();
    const ids = info.radky.map((t) => t.id).filter((x) => x !== id);
    let pozice = ids.length;
    if (z) {
      pozice = 0;
      for (let n = z.previousSibling; n; n = n.previousSibling) {
        if (n.classList && n.classList.contains('raid-box') && parseInt(n.dataset.tokenId, 10) !== id) pozice++;
      }
    }
    ids.splice(pozice, 0, id);
    rucni[ctx.mapaId] = {klic: info.klic, ids};
    uloz(stav);
    konecTazeni();
  });
  function konecTazeni() {
    tazeneId = null;
    seznam.querySelectorAll('.raid-znacka').forEach((z) => z.remove());
    prekresli();
  }
  seznam.addEventListener('dragend', konecTazeni);

  // ----- tlačítka -----
  btnRezim.addEventListener('click', () => { stav.kompakt = !stav.kompakt; uloz(stav); aplikuj(); prekresli(); });
  btnSbalit.addEventListener('click', () => { stav.sbaleno = !stav.sbaleno; uloz(stav); aplikuj(); });
  btnZavrit.addEventListener('click', () => { stav.otevreno = false; uloz(stav); aplikuj(); });
  btnReset.addEventListener('click', () => { delete rucni[ctx.mapaId]; uloz(stav); prekresli(); });
  if (tlacitko) tlacitko.addEventListener('click', () => { stav.otevreno = !stav.otevreno; if (stav.otevreno) stav.sbaleno = false; uloz(stav); aplikuj(); prekresli(); });

  aplikuj();
  prekresli();
  return {
    prekresli: planuj,
    zvyraznenyToken: (id) => nastavZvyrazneni(id),
  };
}
