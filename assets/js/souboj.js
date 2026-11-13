// Souboj na mapě (hra/mapa.php): panel s fázemi kola, zahájení souboje, hod na
// iniciativu (3D kostka nebo ruční zápis) a tahy ve dvou půlkách.
// Pravidla a rozhodnutí: docs/navrh-souboje-v1.md. Serverová logika:
// includes/vtt_souboj.php, hra/api/souboj_*.php, umysl_nastavit.php, tah_*.php.
//
// Skript dostane od mapa.js vše potřebné přes `ctx` (data tokenů, postJson,
// kamera, 3D kostky…) a stará se jen o souboj; mapa.js mu předává stav ze
// serveru. Žádné vlastní importy: mapa.js načítá moduly s číslem verze
// (cache prohlížeče) a závislosti mu předává sám.

const UMYSLY = {
  kouzleni: 'Kouzlení', strelba: 'Střelba', utok: 'Útok', pohyb: 'Pohyb',
  jina: 'Jiná činnost', cekani: 'Čekání', utek: 'Útěk',
};
const UMYSLY_S_CILEM = ['kouzleni', 'strelba', 'utok'];
const ZAVAZEK_NAZVY = {kouzleni: 'kouzlení', utok: 'útok', strelba: 'střelbu'};
const AKCE_DRUHY = {
  utok: 'Útok', obrana: 'Obrana', krok: 'Krok', beh: 'Běh', sprint: 'Sprint', otocka: 'Otočka', krok_utok: 'Krok a útok',
  beh_utok: 'Běh a útok', sprint_utok: 'Sprint a útok',
  otocka_utok: 'Otočka a útok', obrana_krok: 'Obrana a krok', kouzleni: 'Kouzlení', jina: 'Jiná činnost', cekani: 'Čekání',
  vytasit: 'Vytasení zbraně',
};
const SMER_SIPKY = ['→', '↘', '↙', '←', '↖', '↗'];
// Pohyb a tvary tvorů: data posílá server (tabulky souboj_pohyb, tvary_tvoru; jinak výchozí hodnoty),
// tohle jsou jen záložní hodnoty, kdyby je stránka nedodala.
const POHYB_ZALOHA = {krok: {nazev: 'Krok', hexu: 1, jen_pri_umyslu: null, bonus_dovednost: 0, otoceni_max: 270}};
const TVAR_JEDEN = {typ_otaceni: 'smer', utok: 'predni3', bunky: [[0, 0]], predni: [[0, 0]]};
// Jak se akce jmenuje v pravidlech pohybu (krok / běh / sprint) a efekt dovednosti, která k běhu přidává hex.
const REZIM = {krok: 'krok', krok_utok: 'krok', obrana_krok: 'krok', beh: 'beh', sprint: 'sprint'};
const EFEKT_BEH_NA_KRATKO = 'Běh na krátké vzdálenosti';
const SVGNS = 'http://www.w3.org/2000/svg';
const FAZE = [['umysly', 'Úmysly'], ['iniciativa', 'Iniciativa'], ['kolo', 'Tahy']];
const PREKVAPENI_TEXT = {
  druzina: 'družina je překvapena', nepratele: 'nepřátelé jsou překvapeni', obe: 'obě strany jsou překvapeny',
};

// Čeština: 1 akce, 2–4 akce, 0 a 5+ akcí.
function akci(n) { return n === 1 || (n >= 2 && n <= 4) ? 'akce' : 'akcí'; }

function el(tag, trida, text) {
  const e = document.createElement(tag);
  if (trida) e.className = trida;
  if (text !== undefined) e.textContent = text;
  return e;
}

export function initSouboj(ctx) {
  const {mapaId, jePj, postJson, logLine, getTokeny, getVybrane, vystredit, obnovitStav, pohybAuto, hodit3D, nactiKostky, kostky3DPripraveny} = ctx;
  // Hexy (viz assets/js/hexy.js; mapa.js je načítá s číslem verze a předává sem)
  const {hexy, jeHexMapa, hexBunka, hexStred, gridPx, getZdi, soubojSvg, pravidla, tvarTokenu} = ctx;
  const POHYB = (pravidla && pravidla.pohyb) || POHYB_ZALOHA;
  // Souboj žije v plovoucím okně raid panelu (assets/js/raid_panel.js): nadpis a fáze nahoře, pod boxy tvorů ovládání akcí, dole tlačítka PJ.
  // Ovládání jednoho tvora (úmysl, hod, akce…) se vyrobí tady a raid panel ho vloží do jeho boxu (radekExtra).
  const panel = document.getElementById('raidPanel');
  if (!panel) return {aplikuj() {}, logUdalost() {}, planuje: () => false, klik() {}, zrusitPlan() {}, prekreslitOverlay() {}, radekExtra: () => null};
  const titul = document.getElementById('raidTitul');
  const fazeEl = document.getElementById('raidFaze');
  const seznam = document.getElementById('raidInfo');
  const akceEl = document.getElementById('raidAkce');
  const pjEl = document.getElementById('souborPj');
  const tbSouboj = document.getElementById('tb-souboj');
  const tbKolo = document.getElementById('tb-kolo');
  const extra = new Map();   // token_id → prvek s ovládáním tvora vložený do jeho boxu v raid panelu
  const prekresliRaid = () => { if (ctx.raidPrekresli) ctx.raidPrekresli(); };

  let kolo = null;
  let iniciativa = [];
  let ucastnici = [];
  const rozbaleno = new Set(); // PJ: řádky s otevřenou úpravou akcí

  // --- Pomocné ---
  const tokenPodleId = (id) => getTokeny().find((t) => t.id === id) || null;
  const zakladJmena = (label) => String(label || '').replace(/\s+\d+$/, '');

  // Skupiny pro PJ: stejné nestvůry ve stejném stavu se ovládají společně
  // (jeden úmysl, jeden hod — pravidla: nestvůry stejného druhu házejí jednou).
  function seskupit(radky, klic) {
    if (!jePj) return radky.map((u) => ({klic: String(u.token_id), radky: [u]}));
    const mapa = new Map();
    radky.forEach((u) => {
      const k = u.skupina.startsWith('n') ? u.skupina + '|' + klic(u) : 't' + u.token_id;
      if (!mapa.has(k)) mapa.set(k, {klic: k, radky: []});
      mapa.get(k).radky.push(u);
    });
    return Array.from(mapa.values());
  }
  const nazevSkupiny = (s) => (s.radky.length > 1 ? zakladJmena(s.radky[0].label) + ' ×' + s.radky.length : s.radky[0].label);
  const idSkupiny = (s) => s.radky.map((u) => u.token_id);

  // Ovládání skupiny tvorů (PJ ovládá stejné nestvůry společně) patří do boxu prvního z nich; ostatní členové skupiny ukážou odkaz.
  function nastavExtra(s, widget) {
    const ids = idSkupiny(s);
    const obal = el('div', 'raid-extra-obsah');
    if (jePj && s.radky.every((r) => r.skryta)) {
      const t = el('span', 'ini-skryto', 'skryto');
      t.title = 'Hráči nevidí iniciativu ani úmysly tohoto tvora.';
      obal.append(t);
    }
    obal.append(widget);
    extra.set(ids[0], obal);
    ids.slice(1).forEach((id) => extra.set(id, el('span', 'ini-ticho', '↑ společně s ' + s.radky[0].label)));
  }
  // Slib úkonu, za který tvor dostal bonus k iniciativě (h1622): první akce kola a aspoň polovina akcí (kdo polovinu nesplní, ztratí v příštím kole iniciativu: −3).
  function textZavazku(r, dlouhy) {
    const nazev = {kouzleni: 'kouzlení', utok: 'útok (obrana se počítá)', strelba: 'střelba'}[r.zavazek] || r.zavazek;
    const stav = r.zavazek_hotovo + '/' + r.zavazek_akci;
    if (!dlouhy) return 'slib: ' + nazev + ' ' + stav;
    const co = r.zavazek === 'kouzleni' ? 'kouzlení' : 'útok';
    return 'Bonus k iniciativě za ' + nazev + ': ' + (r.akce_hotovo === 0 ? 'první akce kola má být ' + co + ' a ' : '')
      + 'aspoň ' + r.zavazek_akci + ' ' + akci(r.zavazek_akci) + ' kola má být ' + co + ' (splněno ' + stav + ') — jinak v příštím kole −3 k iniciativě.';
  }
  function pips(celkem, zbyva) {
    const w = el('span', 'ini-pips');
    if (celkem <= 0) { w.textContent = 'nehraje'; return w; }
    const prvniPulka = celkem - Math.floor(celkem / 2);
    for (let i = 0; i < celkem; i++) {
      if (i === prvniPulka && celkem > 1) w.append(el('span', 'pip-sep'));
      w.append(el('span', 'pip ' + (i < celkem - zbyva ? 'pouzita' : 'volna')));
    }
    w.title = 'Zbývá ' + zbyva + ' z ' + celkem + ' akcí (vlevo 1. půlka kola, vpravo 2.)';
    return w;
  }
  function tlacitko(text, trida, fn, title) {
    const b = el('button', 'btn ' + (trida || 'btn-ghost'), text);
    b.type = 'button';
    if (title) b.title = title;
    b.addEventListener('click', fn);
    return b;
  }
  function chyba(d) {
    if (d && d.error) { logLine('Chyba: ' + d.error); ukazChybu(d.error); return true; }
    return false;
  }
  // Kouzla „ihned“ ve frontě tvora se připojí k jeho další akci (pohyb, útok, obrana…) a po úspěchu se fronta vyprázdní.
  // Společné pro tlačítka akcí (volat) i pro potvrzení naplánovaného tahu na mapě (odeslatPlan).
  function sIhned(telo) {
    if (telo && telo.druh !== 'kouzleni' && telo.token_id) {
      const f = ihnedFronta.get(telo.token_id);
      if (f && f.length) {
        return Object.assign({}, telo, {ihned: f.map((k) => ({kouzlo_id: k.kouzlo_id, cil_token_id: k.cil_token_id, zbyva_kol: k.zbyva_kol, magy: k.magy}))});
      }
    }
    return telo;
  }
  function vyprazdniIhned(telo) {
    if (telo && telo.ihned) { ihnedFronta.set(telo.token_id, []); kreslit(); }
  }
  function volat(url, telo) {
    if (url.endsWith('tah_akce.php')) telo = sIhned(telo);
    return postJson(url, Object.assign({mapa_id: mapaId}, telo)).then((d) => {
      if (!chyba(d)) {
        vyprazdniIhned(telo);
        obnovitStav();
      }
      return d;
    });
  }

  // --- Ovládání souboje: pruh pořadí nahoře a jedna karta dole (návrh schválený 2026-11-12) ---
  // Postranní panel už souboj neřídí. Nahoře je pořadí tahů (#bojPoradi), dole jedna karta (#bojKarta) s tím, co se
  // po mně právě chce: ohlásit akci a hodit iniciativu, odehrát tah, vybrat kouzlo. Výzvy k hodu (útok, obrana) mají
  // vlastní kartu na stejném místě (vyzvaPanel) a tahle se pod nimi schová. Nestvůry hraje PJ stejnými kartami.
  const pruh = el('div', 'boj-poradi');
  pruh.id = 'bojPoradi';
  pruh.hidden = true;
  document.body.appendChild(pruh);
  const karta = el('div', 'boj-karta');
  karta.id = 'bojKarta';
  karta.hidden = true;
  karta.setAttribute('role', 'region');
  karta.setAttribute('aria-label', 'Souboj — co je na řadě');
  document.body.appendChild(karta);
  let rezim = null;          // podnabídka karty tahu: null | 'jine' | 'zbran' | 'kouzlo'
  let kouzloVyber = null;    // {id, cil: id tokenu | 0 = na sebe | null = ještě nevybráno, kola, magy, akci}
  let pjMenu = false;
  let upravaAkci = false;
  let hazim = false;
  let kartaChyba = '';
  let kartaChybaTimer = null;
  let posledniAktivni = null;
  const pjZaHrace = new Set();       // tahy (klicTahu), ve kterých se PJ rozhodl hrát za postavu hráče
  const rucniHod = new Set();        // skupiny s otevřeným polem „hodil jsem u stolu“
  const zbraneTvora = new Map();     // token_id → {klic, data} z api/postava_mini.php
  const UMYSL_DLAZDICE = [['utok', 'Útok'], ['strelba', 'Střelba'], ['kouzleni', 'Kouzlo'], ['pohyb', 'Pohyb']];
  const UMYSL_MALE = [['cekani', 'Čekám'], ['utek', 'Utíkám'], ['jina', 'Něco jiného']];
  const ZN = (n) => (n >= 0 ? '+' : '−') + Math.abs(n);

  function ukazChybu(text) {
    kartaChyba = text;
    clearTimeout(kartaChybaTimer);
    kartaChybaTimer = setTimeout(() => { kartaChyba = ''; kreslit(); }, 7000);
    kreslit();
  }
  // Otazníček s bublinou (vzor .tvor-hlp z okna tvora): vysvětlení patří sem, ne do plochy karty.
  function napoveda(text) {
    const b = el('button', 'tvor-hlp', '?');
    b.type = 'button';
    b.setAttribute('aria-label', 'Nápověda: ' + text);
    b.appendChild(el('span', 'tvor-tip', text));
    b.addEventListener('click', (e) => { e.stopPropagation(); b.classList.toggle('otevreno'); });
    return b;
  }
  function inicialy(label) {
    const s = String(label || '?').trim();
    const m = s.match(/^(\S)\S*\s+(\d+)$/);
    return m ? m[1].toUpperCase() + m[2] : s.slice(0, 2);
  }
  function avatar(t, label) {
    const a = el('span', 'boj-av');
    const obr = t && ctx.obrazekUrl ? ctx.obrazekUrl(t.obrazek_id) : null;
    if (obr) {
      a.style.backgroundImage = 'url("' + obr + '")';
      a.classList.add('s-obr');
    } else {
      a.textContent = inicialy(label);
      if (t && t.barva) a.style.backgroundColor = t.barva;
    }
    return a;
  }
  function kvotaTecky(n) {
    const w = el('span', 'bk-tecky');
    for (let i = 0; i < Math.min(n, 12); i++) w.append(el('span', 'bk-tecka'));
    w.append(el('span', 'bk-tecky-text', n + ' ' + akci(n)));
    w.setAttribute('role', 'img');
    w.setAttribute('aria-label', 'V tomhle tahu zbývá ' + n + ' ' + akci(n));
    return w;
  }

  function kreslit(vynutit) {
    if (tbSouboj) tbSouboj.title = kolo ? 'Kdo bojuje' : 'Zahájit souboj';
    if (tbSouboj) tbSouboj.classList.toggle('active', !!kolo);
    // Ruční „Konec kola“ (odpočet efektů) by v souboji odpočítal efekty dvakrát — kolo končí samo.
    if (tbKolo) tbKolo.style.display = kolo ? 'none' : '';
    seznam.innerHTML = '';
    akceEl.innerHTML = '';
    fazeEl.innerHTML = '';
    extra.clear();
    if (pjEl) pjEl.style.display = 'none';   // tlačítka PJ jsou v nabídce „PJ“ v pruhu pořadí
    document.body.classList.toggle('je-souboj', !!kolo);
    if (!kolo) {
      pruh.hidden = true;
      karta.hidden = true;
      rezim = null; kouzloVyber = null; pjMenu = false; upravaAkci = false;
      prekresliRaid();
      return;
    }
    titul.textContent = 'Souboj · kolo ' + kolo.cislo_kola + (kolo.faze === 'kolo' ? ' · ' + kolo.polovina + '. půlka' : '');
    kreslitPoradi();
    kreslitKartu(!!vynutit);
    prekresliRaid();
  }

  // ---------- Pruh pořadí ----------
  function cip(t, label, stav, trida, title) {
    const b = el('button', 'boj-cip' + (trida ? ' ' + trida : ''));
    b.type = 'button';
    if (t) b.dataset.tokenId = String(t.id);
    b.append(avatar(t, label), el('span', 'boj-cip-stav', stav));
    b.title = label + (title ? ' — ' + title : '');
    b.setAttribute('aria-label', label + ': ' + (title || stav));
    if (t) b.addEventListener('click', () => vystredit(t.x, t.y));
    return b;
  }
  function kreslitPoradi() {
    pruh.hidden = false;
    pruh.textContent = '';
    const hl = el('div', 'boj-poradi-hl');
    hl.append(el('strong', '', 'Kolo ' + kolo.cislo_kola), el('span', '', kolo.faze === 'kolo' ? kolo.polovina + '. půlka' : 'kdo co udělá'));
    if (kolo.cislo_kola === 1 && PREKVAPENI_TEXT[kolo.prekvapeni] && kolo.faze !== 'kolo') {
      const p = el('span', 'boj-prekvapeni', 'překvapení');
      p.title = 'Překvapení: ' + PREKVAPENI_TEXT[kolo.prekvapeni] + ' (−6 k iniciativě)';
      hl.append(p);
    }
    pruh.append(hl);
    const rada = el('div', 'boj-poradi-rada');
    const ukazane = new Set();
    if (kolo.faze !== 'kolo') {
      seskupit(ucastnici, (u) => (u.hozeno ? 'h' + u.vysledek : 'n') + '|' + (u.umysl || '')).forEach((s) => {
        const u = s.radky[0];
        const moje = s.radky.every((x) => x.ovladam) && !(jePj && !u.skryta);
        const stav = u.hozeno ? String(u.vysledek) : (moje ? (jePj ? 'hoď' : (u.umysl ? 'házíš' : 'volíš')) : 'čeká se');
        const c = cip(tokenPodleId(u.token_id), nazevSkupiny(s), stav,
          'strana-' + u.strana + (u.hozeno ? ' hotovo-hod' : (moje ? ' na-me' : '')) + (jePj && s.radky.every((r) => r.skryta) ? ' skryta' : ''),
          (u.umysl ? UMYSLY[u.umysl].toLowerCase() + ' · ' : '') + (u.hozeno ? 'iniciativa ' + u.vysledek + ', ' + u.akce_celkem + ' ' + akci(u.akce_celkem) : 'ještě nehodil(a)')
            + (jePj && s.radky.every((r) => r.skryta) ? ' (hráči nevidí)' : ''));
        if (s.radky.length > 1) c.append(el('span', 'boj-cip-pocet', '×' + s.radky.length));
        rada.append(c);
        s.radky.forEach((r) => ukazane.add(r.token_id));
      });
    } else {
      iniciativa.forEach((r) => {
        const naTahu = r.token_id === kolo.aktivni_token_id;
        const c = cip(tokenPodleId(r.token_id), r.label, naTahu ? 'na tahu' : (r.akce_celkem > 0 ? String(r.vysledek) : 'nehraje'),
          'strana-' + r.strana + (naTahu ? ' na-tahu' : '') + (r.akce_zbyvajici === 0 ? ' dohrano' : ''),
          'iniciativa ' + r.vysledek + ', zbývá ' + r.akce_zbyvajici + ' z ' + r.akce_celkem + ' akcí');
        c.append(pips(r.akce_celkem, r.akce_zbyvajici));
        rada.append(c);
        ukazane.add(r.token_id);
      });
      // Nepřítel na tahu, jehož pořadí hráč nezná: ukáže se jen ve chvíli, kdy hraje.
      if (kolo.aktivni_token_id !== null && !ukazane.has(kolo.aktivni_token_id)) {
        rada.append(cip(tokenPodleId(kolo.aktivni_token_id), kolo.aktivni_label || 'protivník', 'na tahu', 'strana-nepratele na-tahu', 'je na tahu'));
        ukazane.add(kolo.aktivni_token_id);
      } else if (kolo.aktivni_skryty) {
        rada.append(cip(null, '?', 'na tahu', 'strana-nepratele na-tahu', 'na tahu je protivník, kterého nevidíš'));
      }
    }
    // Hráč: nepřátelé, které na mapě vidí, ale jejichž iniciativu ani úmysl nezná (to je tajné).
    if (!jePj) {
      const ja = ucastnici.find((u) => u.ovladam);
      const n = ja ? Object.entries(kolo.strany || {}).filter(([id, s]) => s !== ja.strana && !ukazane.has(Number(id)) && tokenPodleId(Number(id))).length : 0;
      if (n) {
        const c = el('span', 'boj-cip strana-nepratele neznamy');
        c.append(avatar(null, '?'), el('span', 'boj-cip-stav', 'nepřátelé'), el('span', 'boj-cip-pocet', '×' + n));
        c.title = 'Nepřátelé, které vidíš. Kdy jdou na řadu, se dozvíš, až budou hrát.';
        rada.append(c);
      }
    }
    pruh.append(rada);
    if (jePj) pruh.append(pjNabidka());
  }

  // Nabídka PJ: věci mimo běžný tah (přeskočit, upravit akce, účastníci, konec souboje).
  function pjNabidka() {
    const obal = el('div', 'boj-pj');
    const b = tlacitko('PJ ▾', 'boj-pj-btn', () => { pjMenu = !pjMenu; if (!pjMenu) upravaAkci = false; kreslit(); }, 'Přeskočit tah, upravit akce, účastníci, ukončit souboj');
    b.setAttribute('aria-expanded', pjMenu ? 'true' : 'false');
    obal.append(b);
    if (!pjMenu) return obal;
    const m = el('div', 'boj-pj-menu');
    const polozka = (text, klic, fn, trida) => {
      const p = tlacitko(text, 'boj-pj-polozka' + (trida ? ' ' + trida : ''), fn);
      p.dataset.pj = klic;
      m.append(p);
    };
    if (kolo.faze === 'kolo') {
      polozka('Přeskočit tah', 'preskocit', () => { pjMenu = false; volat('api/tah_konec.php', {}); });
      polozka(upravaAkci ? 'Upravit akce ▴' : 'Upravit akce…', 'akce', () => { upravaAkci = !upravaAkci; kreslit(); });
      if (upravaAkci) {
        iniciativa.forEach((r) => {
          const radek = el('div', 'boj-pj-akce');
          radek.dataset.tokenId = String(r.token_id);
          radek.append(el('span', 'boj-pj-akce-jm', r.label + ' ' + r.akce_zbyvajici + '/' + r.akce_celkem));
          const uprav = (telo) => volat('api/akce_uprava.php', Object.assign({token_id: r.token_id}, telo));
          radek.append(
            tlacitko('−1', 'btn-ghost ini-mini', () => uprav({op: 'delta', delta: -1})),
            tlacitko('+1', 'btn-ghost ini-mini', () => uprav({op: 'delta', delta: 1})),
            tlacitko('×2', 'btn-ghost ini-mini', () => uprav({op: 'rychlost'}), 'Kouzlo Rychlost: zdvojí akce, které tvor právě má k dispozici'),
            tlacitko('½', 'btn-ghost ini-mini', () => uprav({op: 'protoplazma'}), 'Kouzlo Protoplazma: sníží akce na polovinu (dolů)'),
          );
          if (r.zavazek) radek.append(tlacitko('zrušit slib', 'btn-ghost ini-mini', () => uprav({op: 'zavazek_zrusit'}),
            'Tvor už nemusí první akci a polovinu akcí věnovat slíbenému úkonu a neztratí kvůli tomu iniciativu (bonus mu zůstává)'));
          m.append(radek);
        });
      }
    } else {
      const hozeno = ucastnici.filter((u) => u.hozeno).length;
      if (hozeno && hozeno < ucastnici.length) {
        polozka('Začít bez čekání', 'bez-cekani', () => {
          if (!window.confirm('Někdo ještě nehodil — nebude v tomto kole hrát. Opravdu začít?')) return;
          pjMenu = false;
          volat('api/souboj_faze.php', {kam: 'kolo', bez_cekani: true});
        });
      }
    }
    polozka('Kdo bojuje…', 'ucastnici', () => { pjMenu = false; kreslit(); otevritZahajeni(); });
    polozka('Ukončit souboj', 'konec', () => {
      if (!window.confirm('Ukončit souboj? Pořadí tahů se smaže a hráči se zase mohou volně hýbat.')) return;
      pjMenu = false;
      volat('api/souboj_konec.php', {});
    }, 'nebezpecna');
    obal.append(m);
    return obal;
  }

  // ---------- Karta dole ----------
  function kreslitKartu(vynutit) {
    // Rozepsané číslo (vlastní hod, magy…) se nepřekresluje pod rukama.
    const a = document.activeElement;
    if (!vynutit && a && karta.contains(a) && a.tagName === 'INPUT' && a.value !== '') return;
    let obsah = null;
    if (vyzvy.length) obsah = null;
    else if (kolo.faze !== 'kolo') obsah = kartaPriprava();
    else if (kolo.ovladam_aktivniho && kolo.aktivni_token_id !== null) obsah = kartaTah();
    karta.textContent = '';
    if (!obsah) { karta.hidden = true; return; }
    if (kartaChyba) obsah.append(el('p', 'bk-chyba', kartaChyba));
    karta.append(obsah);
    karta.hidden = false;
  }

  // Krok 1 a 2 v jednom: co udělám a hod na iniciativu.
  const jeNestvura = (u) => { const t = tokenPodleId(u.token_id); return !!t && t.typ_entity === 'nestvura_instance'; };
  const ulozUmysl = (s, umysl, navic) => volat('api/umysl_nastavit.php', Object.assign({token_ids: idSkupiny(s), umysl: umysl || null}, navic || {}));
  async function hoditIni(s, rucne) {
    if (hazim) return;
    hazim = true;
    kreslit(true);
    try {
      const u = s.radky[0];
      const ids = idSkupiny(s);
      // Nestvůra bez ohlášené akce útočí (PJ to nemusí klikat u každé skupiny zvlášť).
      if (!u.umysl && jeNestvura(u)) {
        const d0 = await postJson('api/umysl_nastavit.php', {mapa_id: mapaId, token_ids: ids, umysl: 'utok'});
        if (d0 && d0.error) { ukazChybu(d0.error); return; }
      }
      let hod = rucne || null;
      if (!hod) {
        const ok3d = await nactiKostky();
        if (ok3d && kostky3DPripraveny()) { const r = await hodit3D(1, 6); hod = r ? r[0] : null; }
        if (!hod) hod = 1 + Math.floor(Math.random() * 6);
      }
      const d = await postJson('api/souboj_hod.php', {mapa_id: mapaId, token_ids: ids, hod, rucne: !!rucne, auto: true});
      if (d.error) { ukazChybu(d.error); return; }
      (d.upozorneni || []).forEach((t) => logLine(t));
      const v = d.vysledky && d.vysledky[0];
      if (v && v.zavazek) logLine(v.label + ' si za ' + ZAVAZEK_NAZVY[v.zavazek] + ' vzal(a) bonus k iniciativě: první akce kola a aspoň ' + v.zavazek_akci + ' ' + akci(v.zavazek_akci) + ' mají být ' + ZAVAZEK_NAZVY[v.zavazek] + ' (jinak v příštím kole −3).');
      rucniHod.delete(s.klic);
    } finally {
      hazim = false;
      obnovitStav();
    }
  }
  function slozeniHodu(u) {
    const r = el('div', 'bk-slozeni');
    r.append(el('span', 'bk-stitek', 'k6'));
    const n = u.ini_nahled;
    if (n) {
      n.popisy.forEach((p) => r.append(el('span', 'bk-stitek zvyrazneny', p)));
      r.append(el('span', 'bk-celkem', n.modifikator ? 'celkem ' + ZN(n.modifikator) : 'bez oprav'));
    }
    return r;
  }
  function textSlibu(u) {
    if (!kolo.iniciativa_v2) return null;
    if (u.umysl === 'kouzleni') return 'Bonus za kouzlení zavazuje: první akce kola a aspoň polovina akcí má být kouzlení, jinak máš v příštím kole −3 k iniciativě.';
    if (['utok', 'strelba'].includes(u.umysl) && (u.ini_trida || u.utoku_za_2_kola)) return 'Bonus za vícenásobné útoky zavazuje: první akce kola a aspoň polovina akcí má být útok (obrana se počítá), jinak máš v příštím kole −3 k iniciativě.';
    return null;
  }
  function tlacitkaHodu(s) {
    const r = el('div', 'bk-hod');
    const b = tlacitko(hazim ? 'Kostka letí…' : 'Hodit kostkou', 'bk-hlavni', () => hoditIni(s, null));
    b.dataset.hod = 'ini';
    b.disabled = hazim;
    r.append(b);
    if (rucniHod.has(s.klic)) {
      const i = el('input', 'input bk-cislo');
      i.type = 'number'; i.min = '1'; i.max = '6'; i.placeholder = '1–6';
      i.setAttribute('aria-label', 'Hod z vlastní kostky');
      const zapis = () => {
        const h = parseInt(i.value, 10);
        if (!(h >= 1 && h <= 6)) { ukazChybu('Hod na 1k6 je číslo 1 až 6.'); return; }
        i.value = '';
        hoditIni(s, h);
      };
      i.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Enter') zapis(); });
      r.append(i, tlacitko('Zapsat', 'bk-vedlejsi', zapis));
    } else {
      r.append(tlacitko('Hodil jsem u stolu…', 'bk-vedlejsi', () => { rucniHod.add(s.klic); kreslit(true); }, 'Zapíšeš číslo z vlastní kostky'));
    }
    return r;
  }
  function kartaPriprava() {
    const moje = seskupit(ucastnici.filter((u) => u.ovladam), (u) => (u.hozeno ? 'h' + u.hod + '|' + u.modifikator : 'n') + '|' + (u.umysl || ''));
    if (!moje.length) return null;
    const box = el('div', 'bk bk-priprava');
    const nehozeniJini = ucastnici.filter((u) => !u.hozeno && !u.ovladam);
    const cekani = () => {
      const p = el('p', 'bk-pozn');
      const mojeNehozene = moje.filter((s) => !s.radky[0].hozeno && !(jePj && !s.radky[0].skryta)).length;
      if (mojeNehozene) p.textContent = 'Jakmile hodí poslední, kolo začne samo.';
      else if (jePj) { const hraci = ucastnici.filter((u) => !u.hozeno); p.textContent = hraci.length ? 'Čeká se na: ' + hraci.map((u) => u.label).join(', ') + '. Kolo pak začne samo.' : 'Kolo začíná.'; }
      else p.textContent = nehozeniJini.length ? 'Čeká se na: ' + nehozeniJini.map((u) => u.label).join(', ') + '. Kolo pak začne samo.' : 'Čeká se na hody protivníků. Kolo pak začne samo.';
      return p;
    };
    if (!jePj && moje.length === 1) {
      const s = moje[0];
      const u = s.radky[0];
      if (u.hozeno) {
        box.append(el('h2', 'bk-nadpis', 'Iniciativa ' + u.vysledek + ' · ' + u.akce_celkem + ' ' + akci(u.akce_celkem)));
        box.append(cekani());
        return box;
      }
      const hl = el('div', 'bk-hlava');
      hl.append(el('h2', 'bk-nadpis', u.label + ', co uděláš v tomhle kole?'));
      hl.append(napoveda('Podle ohlášené akce se mění hod na iniciativu (číslo u dlaždice). Od druhého kola je předvybraná akce z minula — stačí hodit.'));
      box.append(hl);
      const mriz = el('div', 'bk-dlazdice');
      UMYSL_DLAZDICE.forEach(([k, text]) => {
        const d = el('button', 'bk-dlazdice-b' + (u.umysl === k ? ' vybrana' : ''));
        d.type = 'button';
        d.dataset.umysl = k;
        d.setAttribute('aria-pressed', u.umysl === k ? 'true' : 'false');
        d.append(el('strong', '', text));
        let pod = '';
        const n = u.ini_nahled;
        if (k === 'strelba' && n && n.strelba === false) { d.disabled = true; pod = 'nemáš čím střílet'; }
        else if (k === 'kouzleni' && n && n.kouzlo === false) { d.disabled = true; pod = 'neznáš žádné kouzlo'; }
        else if (k === 'kouzleni' && kolo.ini_kouzleni) pod = ZN(kolo.ini_kouzleni) + ' k iniciativě';
        else if (k === 'pohyb') pod = 'jen se přesunu';
        if (pod) d.append(el('span', '', pod));
        d.addEventListener('click', () => ulozUmysl(s, k));
        mriz.append(d);
      });
      box.append(mriz);
      const male = el('div', 'bk-male');
      UMYSL_MALE.forEach(([k, text]) => {
        const d = tlacitko(text, 'bk-vedlejsi' + (u.umysl === k ? ' vybrana' : ''), () => ulozUmysl(s, k));
        d.dataset.umysl = k;
        d.setAttribute('aria-pressed', u.umysl === k ? 'true' : 'false');
        male.append(d);
      });
      box.append(male);
      if (u.umysl) {
        const sl = slozeniHodu(u);
        const slib = textSlibu(u);
        if (slib) sl.append(napoveda(slib));
        box.append(sl);
        if (u.ini_nahled && u.ini_nahled.bez_akci) box.append(el('p', 'bk-varovani', 'Efekt ' + u.ini_nahled.bez_akci + ': v tomhle kole nemáš žádné akce.'));
        box.append(tlacitkaHodu(s));
      } else {
        box.append(el('p', 'bk-pozn', 'Vyber, co uděláš — pak hodíš na iniciativu.'));
      }
      return box;
    }
    // PJ (a hráč s víc postavami): řádek na skupinu — akce, složení hodu, hod.
    const hl = el('div', 'bk-hlava');
    hl.append(el('h2', 'bk-nadpis', 'Ohlas akci a hoď na iniciativu'));
    hl.append(napoveda('Stejné nestvůry házejí jedním hodem. Nestvůra bez ohlášené akce útočí. „…“ otevře hod s ručními bonusy a postihy.'));
    box.append(hl);
    const tab = el('div', 'bk-radky');
    moje.forEach((s) => {
      const u = s.radky[0];
      const r = el('div', 'bk-radek' + (u.hozeno ? ' hozeno' : ''));
      r.dataset.skupina = s.klic;
      r.append(el('span', 'bk-radek-jm', nazevSkupiny(s)));
      if (jePj && !u.skryta && !u.hozeno) {
        // Postava hráče: ohlašuje a hází hráč sám; PJ za něj hodí jen výjimečně (hráč neodpovídá).
        r.classList.add('ceka-na-hrace');
        r.append(el('span', 'bk-radek-mod', (u.umysl ? UMYSLY[u.umysl].toLowerCase() + ' · ' : '') + 'čeká se na hráče'));
        r.append(tlacitko('Hodit za něj…', 'bk-vedlejsi maly', () => otevritHod(idSkupiny(s), nazevSkupiny(s), u), 'Hráč neodpovídá — hodíš a případně ohlásíš akci za něj'));
      } else if (u.hozeno) {
        const v = el('span', 'bk-radek-vysl', 'iniciativa ' + u.vysledek + ' · ' + u.akce_celkem + ' ' + akci(u.akce_celkem));
        v.title = 'Hod ' + u.hod + ' ' + ZN(u.modifikator) + ' = ' + u.vysledek;
        r.append(v);
        if (jePj) r.append(tlacitko('↻', 'btn-ghost ini-mini', () => otevritHod(idSkupiny(s), nazevSkupiny(s), u), 'Přehodit / opravit (jen PJ)'));
      } else {
        const sel = el('select', 'input bk-umysl');
        sel.setAttribute('aria-label', 'Akce v tomhle kole — ' + nazevSkupiny(s));
        if (!jeNestvura(u)) sel.append(new Option('— co udělá —', ''));
        Object.entries(UMYSLY).forEach(([k, t]) => sel.append(new Option(t, k)));
        sel.value = u.umysl || (jeNestvura(u) ? 'utok' : '');
        const utoky = el('input', 'input ini-utoky');
        utoky.type = 'number'; utoky.min = '2'; utoky.max = '8';
        utoky.value = String(u.utoku_za_2_kola || 2);
        utoky.title = 'Kolik útoků za 2 kola tvor umí (2 = běžně, bez bonusu). Bonus k iniciativě je 3 × (počet − 2).';
        utoky.setAttribute('aria-label', 'Útoků za 2 kola');
        const ukazUtoky = () => { utoky.style.display = jePj && kolo.iniciativa_v2 && !u.ini_trida && ['utok', 'strelba'].includes(sel.value) ? '' : 'none'; };
        ukazUtoky();
        const uloz = () => ulozUmysl(s, sel.value, kolo.iniciativa_v2 ? {utoku_za_2_kola: utoky.value === '' ? null : parseInt(utoky.value, 10)} : null);
        sel.addEventListener('change', () => { ukazUtoky(); uloz(); });
        utoky.addEventListener('change', uloz);
        utoky.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Enter') utoky.blur(); });
        const utokyObal = el('label', 'bk-pole bk-utoky', 'útoků za 2 kola ');
        utokyObal.append(utoky);
        utokyObal.style.display = utoky.style.display;
        utoky.style.display = '';
        sel.addEventListener('change', () => { utokyObal.style.display = jePj && kolo.iniciativa_v2 && !u.ini_trida && ['utok', 'strelba'].includes(sel.value) ? '' : 'none'; });
        r.append(sel, utokyObal);
        const n = u.ini_nahled;
        const mod = el('span', 'bk-radek-mod', n ? 'k6 ' + (n.modifikator ? ZN(n.modifikator) : '') : 'k6');
        if (n && n.popisy.length) mod.title = n.popisy.join(', ');
        r.append(mod);
        const h = tlacitko('Hodit', 'bk-hlavni maly', () => hoditIni(s, null));
        h.dataset.hod = 'ini';
        h.disabled = hazim || (!u.umysl && !jeNestvura(u));
        if (h.disabled && !hazim) h.title = 'Nejdřív vyber, co udělá';
        r.append(h, tlacitko('…', 'btn-ghost ini-mini', () => otevritHod(idSkupiny(s), nazevSkupiny(s), u), 'Hod s ručními bonusy a postihy, nebo zápis z vlastní kostky'));
      }
      tab.append(r);
    });
    box.append(tab);
    const nehozene = moje.filter((s) => !s.radky[0].hozeno && !(jePj && !s.radky[0].skryta));
    const pata = el('div', 'bk-pata');
    if (jePj && nehozene.length > 1) {
      const vse = tlacitko('Hodit za všechny', 'bk-vedlejsi', async () => {
        for (const s of nehozene) {
          if (!s.radky[0].umysl && !jeNestvura(s.radky[0])) continue;   // postava bez ohlášené akce počká na PJ
          await hoditIni(s, null);
        }
      }, 'Postupně hodí za každou skupinu, která má ohlášenou akci (nestvůry útočí)');
      vse.dataset.hod = 'vse';
      vse.disabled = hazim;
      pata.append(vse);
    }
    pata.append(cekani());
    box.append(pata);
    return box;
  }

  // Zbraně u sebe pro nabídku „v ruce“ (jen postavy); obnovují se po každé akci.
  function nactiZbrane(t) {
    if (!t || t.typ_entity !== 'postava') return null;
    const klic = kolo.cislo_kola + ':' + kolo.polovina + ':' + kolo.kvota;
    const z = zbraneTvora.get(t.id);
    if (z && z.klic === klic) return z.data;
    zbraneTvora.set(t.id, {klic, data: z ? z.data : null});
    fetch('api/postava_mini.php?mapa_id=' + mapaId + '&token_id=' + t.id, {cache: 'no-store', credentials: 'same-origin'})
      .then((r) => r.json())
      .then((d) => { zbraneTvora.set(t.id, {klic, data: d && !d.error ? d : {zbrane: [], zbran: null}}); kreslit(); })
      .catch(() => {});
    return z ? z.data : null;
  }
  const akceTahu = (druh, navic) => { rezim = null; return volat('api/tah_akce.php', Object.assign({token_id: kolo.aktivni_token_id, druh}, navic || {})); };
  const kouzli = (t) => !!t && (t.efekty || []).some((e) => String(e).startsWith('Kouzlí'));

  // Na tahu je postava hráče a PJ ji (zatím) nehraje.
  function hrajeHrac() {
    if (!jePj || !kolo || kolo.aktivni_token_id === null) return false;
    const r = iniciativa.find((x) => x.token_id === kolo.aktivni_token_id);
    return !!r && !r.skryta && !pjZaHrace.has(klicTahu());
  }
  // Krok 3: můj tah. Cíl se vybírá na mapě (klik na nepřítele = útok, na místo = přesun), karta jen potvrzuje.
  function kartaTah() {
    const t = aktivniToken();
    const r = iniciativa.find((x) => x.token_id === kolo.aktivni_token_id);
    const box = el('div', 'bk bk-tah');
    const hl = el('div', 'bk-hlava');
    hl.append(el('h2', 'bk-nadpis', jePj ? 'Na tahu: ' + (kolo.aktivni_label || 'tvor') : 'Jsi na tahu'));
    if (kolo.kvota) hl.append(kvotaTecky(kolo.kvota));
    if (r && r.zavazek) {
      const sl = el('span', 'bk-slib', textZavazku(r, false));
      sl.append(napoveda(textZavazku(r, true)));
      hl.append(sl);
    }
    box.append(hl);
    // Postavu hráče hraje hráč: PJ má jen malou kartu a tah převezme, až když o to stojí (hráč neodpovídá).
    if (hrajeHrac()) {
      const k = el('div', 'bk-tlacitka');
      k.append(el('p', 'bk-pozn', 'Hraje hráč. Hody uvidíš tady, jakmile k nim dojde.'));
      const b = tlacitko('Hrát za něj', 'bk-vedlejsi konec', () => { pjZaHrace.add(klicTahu()); zahajitPlan(null); kreslit(true); }, 'Převezmeš tah téhle postavy (hráč neodpovídá)');
      b.dataset.tah = 'za-hrace';
      k.append(b);
      box.append(k);
      return box;
    }
    if (rezim === 'kouzlo') { box.append(kartaKouzlo(t)); return box; }

    if (kouzli(t)) {
      const k = el('div', 'bk-plan');
      const tx = el('div', 'bk-plan-text');
      tx.append(el('strong', '', 'Kouzlení pokračuje'), el('span', '', 'další akce kouzlení · 1 akce'));
      k.append(tx);
      const dal = tlacitko('Kouzlit dál', 'bk-hlavni', () => akceTahu('kouzleni'));
      dal.dataset.akce = 'kouzlit-dal';
      k.append(dal, tlacitko('Přerušit', 'bk-vedlejsi', () => akceTahu('kouzleni', {preruseni: true}), 'Přerušení stojí jednu akci, magenergie propadne'));
      box.append(k);
    }
    const druh = plan ? druhPlanu() : null;
    const zbrane = nactiZbrane(t);
    if (plan && druh) {
      const cil = plan.cil ? tokenPodleId(plan.cil) : null;
      const k = el('div', 'bk-plan' + (cil ? ' utok' : ''));
      const tx = el('div', 'bk-plan-text');
      tx.append(el('strong', '', AKCE_DRUHY[druh] + (cil ? ' → ' + cil.label : '')));
      const pod = ['1 akce'];
      if (cil && t && t.typ_entity === 'postava' && zbrane) pod.push(zbrane.zbran ? zbrane.zbran.nazev : 'beze zbraně');
      if (plan.cesta.length) pod.push(popisHexu(plan.cesta.length));
      if (potrebujePast()) pod.push('napřed hod na dovednost');
      tx.append(el('span', '', pod.join(' · ')));
      k.append(tx);
      const potvrdit = tlacitko(cil ? 'Útok' : (plan.cesta.length ? 'Jít' : 'Otočit'), 'bk-hlavni', odeslatPlan, 'Provede plán (Enter nebo dvojklik na cíl)');
      potvrdit.dataset.plan = 'potvrdit';
      potvrdit.disabled = !planJeHotov();
      k.append(potvrdit);
      const jemne = el('div', 'bk-jemne');
      const zpet = tlacitko('↩', 'bk-ikona', zpetOBod, 'Zpět o jeden bod cesty (Backspace)');
      zpet.disabled = !plan.cesta.length;
      jemne.append(zpet, tlacitko('↶', 'bk-ikona', () => otocOPlan(-1), 'Otočit čelo na konci o 60° doleva'), tlacitko('↷', 'bk-ikona', () => otocOPlan(1), 'Otočit čelo na konci o 60° doprava'));
      if (plan.cil !== null) {
        const bez = tlacitko('bez útoku', 'bk-ikona text', () => { plan.cil = null; kreslit(); prekreslitOverlay(); }, 'Jen pohyb, útok vynechat');
        bez.dataset.plan = 'bez-utoku';
        jemne.append(bez);
        if (plan.cesta.length) { const zm = tlacitko('z místa', 'bk-ikona text', jenUtok, 'Zaútočit z místa (jen otočit, když je potřeba)'); zm.dataset.plan = 'z-mista'; jemne.append(zm); }
      }
      k.append(jemne);
      box.append(k);
    } else if (!jeHex()) {
      box.append(el('p', 'bk-pozn', 'Tahle mapa nemá hexy — pohyb a útok tu hra neumí vyhodnotit. Akce jde jen odečíst v nabídce „Jiné“.'));
    } else if (!plan) {
      // Klikání na mapu vypnuté klávesou Esc: jedním tlačítkem se zapne znovu.
      const k = el('div', 'bk-tlacitka');
      k.append(el('p', 'bk-pozn', 'Klikání na mapu je vypnuté.'));
      const b = tlacitko('Plánovat tah', 'bk-vedlejsi', () => { zruseno.delete(klicTahu()); zahajitPlan(null); }, 'Klikáním na mapu naplánuješ pohyb a útok');
      b.dataset.akce = 'planovat';
      k.append(b);
      box.append(k);
    } else {
      box.append(el('p', 'bk-pozn', plan.druhVynuceny ? 'Klepni na sousední hex, kam uděláš krok.' : 'Klepni na nepřítele = útok · na volné místo = přesun · do vlastního těla = otočení'));
    }
    const fronta = ihnedFronta.get(kolo.aktivni_token_id) || [];
    if (fronta.length) {
      const ch = el('div', 'ihned-chipy');
      ch.append(el('span', 'bk-pozn', 'K další akci se připojí:'));
      fronta.forEach((k, i) => {
        const c = el('span', 'ihned-chip', k.nazev + ' → ' + k.cil_nazev + ' ');
        c.append(tlacitko('✕', 'btn-ghost ihned-x', () => { fronta.splice(i, 1); kreslit(); }, 'Odebrat z fronty'));
        ch.append(c);
      });
      box.append(ch);
    }

    if (rezim === 'zbran' && zbrane) {
      const m = el('div', 'bk-nabidka');
      m.dataset.nabidka = 'zbran';
      zbrane.zbrane.forEach((z) => {
        const b = el('button', 'bk-nabidka-b' + (z.drzena ? ' vybrana' : ''));
        b.type = 'button';
        b.dataset.zbran = String(z.id);
        b.append(el('strong', '', z.nazev), el('span', '', z.drzena ? 'v ruce' : 'přezbrojit · 1 akce'));
        b.disabled = z.drzena;
        b.addEventListener('click', () => vytasit(t, z.id));
        m.append(b);
      });
      if (zbrane.zbran) {
        const b = el('button', 'bk-nabidka-b');
        b.type = 'button';
        b.dataset.zbran = '0';
        b.append(el('strong', '', 'Prázdné ruce'), el('span', '', 'schovat zbraň · 1 akce'));
        b.addEventListener('click', () => vytasit(t, 0));
        m.append(b);
      }
      if (!zbrane.zbrane.length) m.append(el('p', 'bk-pozn', 'Postava u sebe nemá žádnou zbraň (v batohu se v boji netasí).'));
      box.append(m);
    }
    if (rezim === 'jine') {
      const m = el('div', 'bk-nabidka');
      m.dataset.nabidka = 'jine';
      const pol = (text, pod, fn, klic) => {
        const b = el('button', 'bk-nabidka-b');
        b.type = 'button';
        b.dataset.akce = klic;
        b.append(el('strong', '', text), el('span', '', pod));
        b.addEventListener('click', fn);
        m.append(b);
      };
      pol('Čekání', '1 akce', () => akceTahu('cekani'), 'cekani');
      pol('Obrana', '1 akce', () => akceTahu('obrana'), 'obrana');
      if (jeHex()) pol('Obrana a krok', '1 akce · krok vybereš na mapě', () => { rezim = null; zahajitPlan('obrana_krok'); }, 'obrana_krok');
      pol('Jiná činnost', '1 akce', () => akceTahu('jina'), 'jina');
      if (jeHex()) {
        if (plan) pol(plan.drzetCelo ? 'Držím čelo' : 'Otáčím se po cestě', 'klepnutím přepneš', () => prepniDrzetCelo(), 'drzet-celo');

      } else {
        ['krok', 'beh', 'sprint', 'utok'].forEach((k) => pol(AKCE_DRUHY[k], '1 akce · jen odečte', () => akceTahu(k), k));
      }
      box.append(m);
    }

    const rada = el('div', 'bk-tlacitka');
    if (t && t.typ_entity === 'postava') {
      const z = el('button', 'bk-zbran' + (rezim === 'zbran' ? ' vybrana' : ''));
      z.type = 'button';
      z.dataset.tah = 'zbran';
      z.setAttribute('aria-expanded', rezim === 'zbran' ? 'true' : 'false');
      z.append(el('span', '', 'v ruce'), el('strong', '', (zbrane ? (zbrane.zbran ? zbrane.zbran.nazev : 'nic') : '…') + ' ▾'));
      z.addEventListener('click', () => { rezim = rezim === 'zbran' ? null : 'zbran'; kreslit(); });
      rada.append(z);
    }
    const data = t ? nactiKouzla(t.id) : null;
    if (jePj || (data && data.kouzla && data.kouzla.length)) {
      const kb = tlacitko('Kouzlo', 'bk-volba', () => { rezim = 'kouzlo'; kouzloVyber = null; kreslit(); prekreslitOverlay(); });
      kb.dataset.tah = 'kouzlo';
      rada.append(kb);
    }
    const jb = tlacitko('Jiné ▾', 'bk-volba' + (rezim === 'jine' ? ' vybrana' : ''), () => { rezim = rezim === 'jine' ? null : 'jine'; kreslit(); });
    jb.dataset.tah = 'jine';
    jb.setAttribute('aria-expanded', rezim === 'jine' ? 'true' : 'false');
    rada.append(jb);
    const konec = tlacitko('Konec tahu', 'bk-vedlejsi konec', () => volat('api/tah_konec.php', {}), 'Zbylé akce tohoto tahu propadnou jako čekání.');
    konec.dataset.tah = 'konec';
    rada.append(konec);
    box.append(rada);
    return box;
  }
  function vytasit(t, zbranId) {
    rezim = null;
    postJson('api/zbran_vytasit.php', {postava_id: t.entita_id, zbran_id: zbranId}).then((d) => {
      if (d && d.error) { ukazChybu(d.error); return; }
      zbraneTvora.delete(t.id);
      obnovitStav();
    });
  }

  // Krok 3b: kouzlo. Nejdřív které, pak cíl klepnutím na mapě (nebo „Na sebe“).
  function kartaKouzlo(t) {
    const box = el('div', 'bk-kouzlo');
    const hl = el('div', 'bk-hlava');
    const zpet = tlacitko('←', 'bk-ikona', () => { rezim = null; kouzloVyber = null; kreslit(); prekreslitOverlay(); }, 'Zpět na tah');
    zpet.setAttribute('aria-label', 'Zpět na tah');
    zpet.dataset.kouzlo = 'zpet';
    hl.append(zpet, el('h3', 'bk-podnadpis', 'Které kouzlo?'));
    if (t && t.magenergie !== null && t.magenergie !== undefined) hl.append(el('span', 'bk-magy', 'Magenergie ' + t.magenergie));
    box.append(hl);
    const data = t ? nactiKouzla(t.id) : null;
    if (!data) { box.append(el('p', 'bk-pozn', 'Načítám kouzla…')); return box; }
    if (data.kouzla.length) {
      const mriz = el('div', 'bk-dlazdice kouzla');
      data.kouzla.forEach((k) => {
        const vybrane = kouzloVyber && kouzloVyber.id === k.id;
        const d = el('button', 'bk-dlazdice-b' + (vybrane ? ' vybrana' : ''));
        d.type = 'button';
        d.dataset.kouzlo = String(k.id);
        d.setAttribute('aria-pressed', vybrane ? 'true' : 'false');
        d.append(el('strong', '', k.nazev));
        const cena = k.magy !== null && k.magy !== undefined ? k.magy + ' ' + (k.magy === 1 ? 'mag' : (k.magy < 5 ? 'magy' : 'magů')) : (k.cena ? k.cena : 'cena podle knihy');
        const doba = k.akci === 0 ? 'ihned' : (k.akci ? akciText(k.akci) : 'akce určí PJ');
        const malo = t && t.magenergie !== null && t.magenergie !== undefined && k.magy !== null && k.magy !== undefined && k.magy > t.magenergie;
        d.append(el('span', '', malo ? cena + ' · nemáš dost' : cena + ' · ' + doba));
        d.disabled = !!malo;
        d.addEventListener('click', () => { kouzloVyber = {id: k.id, cil: null, kola: '', magy: '', akci: ''}; kreslit(); prekreslitOverlay(); });
        mriz.append(d);
      });
      box.append(mriz);
    } else {
      box.append(el('p', 'bk-pozn', jePj ? 'Tvor nemá seznam kouzel — zadej, kolik akcí kouzlo zabere.' : 'Nemáš žádné kouzlo.'));
    }
    const kz = kouzloVyber ? data.kouzla.find((k) => k.id === kouzloVyber.id) : null;
    if (kz) {
      const cil = kouzloVyber.cil ? tokenPodleId(kouzloVyber.cil) : null;
      const k = el('div', 'bk-plan kouzlo');
      const tx = el('div', 'bk-plan-text');
      tx.append(el('strong', '', kz.nazev + (kouzloVyber.cil === 0 ? ' na sebe' : (cil ? ' → ' + cil.label : ''))));
      tx.append(el('span', '', kouzloVyber.cil === null ? 'klepni na cíl na mapě' + (kz.dosah_sahy ? ' (dosah ' + String(kz.dosah_sahy).replace('.', ',') + ' sáhů)' : '') : (kz.akci === 0 ? 'ihned — připojí se k další akci' : (kz.akci ? akciText(kz.akci) + ' kouzlení' : 'počet akcí určí PJ'))));
      k.append(tx);
      const sebe = tlacitko('Na sebe', 'bk-vedlejsi' + (kouzloVyber.cil === 0 ? ' vybrana' : ''), () => { kouzloVyber.cil = 0; kreslit(); prekreslitOverlay(); });
      sebe.dataset.kouzlo = 'na-sebe';
      k.append(sebe);
      const seslat = tlacitko(kz.akci === 0 ? 'Přidat k další akci' : 'Seslat', 'bk-hlavni', () => seslatKouzlo(t, kz));
      seslat.dataset.kouzlo = 'seslat';
      seslat.disabled = kouzloVyber.cil === null;
      k.append(seslat);
      box.append(k);
      // Co hra z knihy nepozná (trvání, cena vzorcem), doplní hráč nebo PJ.
      const det = document.createElement('details');
      det.className = 'bk-upresnit';
      det.open = !!(kouzloVyber.kola || kouzloVyber.magy || kouzloVyber.akci);
      det.append(el('summary', '', 'Upřesnit trvání a cenu'));
      const pole = (klic, popis, max) => {
        const l = el('label', 'bk-pole', popis + ' ');
        const i = el('input', 'input bk-cislo');
        i.type = 'number'; i.min = '0'; i.max = String(max);
        i.value = kouzloVyber[klic];
        i.dataset.kouzloPole = klic;
        i.addEventListener('input', () => { kouzloVyber[klic] = i.value; });
        i.addEventListener('keydown', (e) => e.stopPropagation());
        l.append(i);
        return l;
      };
      det.append(pole('kola', 'trvání v kolech', 99), pole('magy', 'magů', 999));
      if (jePj) det.append(pole('akci', 'akcí kouzlení', 20));
      box.append(det);
    }
    if (jePj) {
      const r = el('div', 'bk-rucne');
      const i = el('input', 'input bk-cislo');
      i.type = 'number'; i.min = '1'; i.max = '20'; i.placeholder = 'akcí';
      i.setAttribute('aria-label', 'Počet akcí kouzla bez seznamu');
      i.addEventListener('keydown', (e) => e.stopPropagation());
      const b = tlacitko('Kouzlit bez seznamu', 'bk-vedlejsi', () => {
        const telo = {};
        if (i.value) telo.akci = Number(i.value);
        i.value = '';
        akceTahu('kouzleni', telo);
      }, 'Nestvůra nebo kouzlo mimo seznam: odečte akci kouzlení, počet akcí zadáš ručně');
      b.dataset.kouzlo = 'rucne';
      r.append(i, b);
      box.append(r);
    }
    return box;
  }
  function seslatKouzlo(t, kz) {
    const v = kouzloVyber;
    if (!v || v.cil === null) return;
    const cil = v.cil ? tokenPodleId(v.cil) : null;
    if (kz.akci === 0) {
      const fronta = ihnedFronta.get(t.id) || [];
      if (fronta.length >= 5) { ukazChybu('K jedné akci jde připojit nejvýš 5 kouzel „ihned“.'); return; }
      fronta.push({kouzlo_id: kz.id, nazev: kz.nazev, cil_token_id: v.cil || undefined, cil_nazev: cil ? cil.label : 'na sebe',
        zbyva_kol: v.kola !== '' ? Number(v.kola) : undefined, magy: v.magy !== '' ? Number(v.magy) : undefined});
      ihnedFronta.set(t.id, fronta);
      rezim = null; kouzloVyber = null;
      kreslit(true);
      prekreslitOverlay();
      return;
    }
    const telo = {kouzlo_id: kz.id};
    if (v.cil) telo.cil_token_id = v.cil;
    if (v.kola !== '') telo.zbyva_kol = Number(v.kola);
    if (v.magy !== '') telo.magy = Number(v.magy);
    if (v.akci !== '') telo.akci = Number(v.akci);
    volat('api/tah_akce.php', Object.assign({token_id: t.id, druh: 'kouzleni'}, telo)).then((d) => {
      if (d && !d.error) { rezim = null; kouzloVyber = null; kreslit(true); prekreslitOverlay(); }
    });
  }

  // --- Tah na hexové mapě: jedno gesto místo hlášení akcí (docs/navrh-tahu-v2.md) ---
  // Tvor na tahu jen klikne na mapu: na hexy (body cesty A, B, C…), na nepřítele (cesta k němu + útok),
  // do vlastního těla (čelo). Z toho systém pozná akci (krok / běh / sprint / otočka / útok a jejich
  // spojení), spočítá cenu v akcích a po potvrzení ji pošle serveru. Plán začíná sám, jakmile je tvor
  // na tahu; Esc ho do dalšího tahu vypne. Na čtvercové mapě zůstává prosté odečítání akcí.
  let plan = null;
  const zruseno = new Set(); // tahy (tvor:kolo:půlka), kde hráč plánování vypnul
  const klicTahu = () => (kolo ? kolo.aktivni_token_id + ':' + kolo.cislo_kola + ':' + kolo.polovina : '');
  const jeHex = () => !!(jeHexMapa && jeHexMapa());
  const aktivniToken = () => (kolo && kolo.aktivni_token_id !== null ? tokenPodleId(kolo.aktivni_token_id) : null);
  const MODY_POHYBU = ['krok', 'beh', 'sprint'];
  // Co se z plánu pošle serveru: druh pohybu + útok → druh akce (každá stojí jednu akci).
  const DRUH_S_UTOKEM = {krok: 'krok_utok', beh: 'beh_utok', sprint: 'sprint_utok'};

  function popisHexu(n) { return n === 1 ? '1 hex' : n + (n <= 4 ? ' hexy' : ' hexů'); }
  function popisAkci(n) { return n + ' ' + akci(n); }

  // Kolik hexů smí tvor v pohybové akci (krok 1, běh 2, sprint 3; dovednost Běh na krátké vzdálenosti
  // přidá hex k běhu a sprintu — PJ ji nasazuje jako efekt).
  const maDovednostBehu = (token) => !!token && (token.efekty || []).some((e) => String(e).startsWith(EFEKT_BEH_NA_KRATKO));
  function dosahPohybu(mod, token) {
    const r = POHYB[mod] || POHYB.krok;
    return r.hexu + (r.bonus_dovednost > 0 && maDovednostBehu(token) ? r.bonus_dovednost : 0);
  }
  // Nejvíc hexů, které tvor smí v jedné akci: nejrychlejší pohyb, který mu únava nezakazuje.
  function nejdalPohybu(token) {
    const zakazy = (kolo && kolo.aktivni_zakazy) || [];
    for (const mod of ['sprint', 'beh']) {
      if (POHYB[mod] && !zakazy.includes(mod)) return dosahPohybu(mod, token);
    }
    return dosahPohybu('krok', token);
  }
  // Kolik hexů smí plán obsahovat: bez dovednosti Běh na krátké vzdálenosti je čtvrtý hex sprintu navíc —
  // povolí se, ale potvrzení nejdřív založí hod proti pasti (síla, střední; vidí ho i PJ) a pohyb se provede až po úspěchu.
  const vyzvyZapnute = () => !!(window.MAPA && window.MAPA.funkce && window.MAPA.funkce.vyzvy);
  function dosahPlanu(token) {
    const zakazy = (kolo && kolo.aktivni_zakazy) || [];
    const d = nejdalPohybu(token);
    const sprint = POHYB.sprint;
    if (vyzvyZapnute() && sprint && sprint.bonus_dovednost > 0 && !zakazy.includes('sprint') && !maDovednostBehu(token) && d === sprint.hexu) return d + 1;
    return d;
  }
  const potrebujePast = () => !!plan && !plan.druhVynuceny && plan.cesta.length > plan.dosahBezPast;
  // Nejlevnější pohyb, který pokryje daný počet hexů (krok ≤ 1, běh ≤ 2 (+1), sprint).
  function modPohybu(m, token) {
    return MODY_POHYBU.find((mod) => POHYB[mod] && m <= dosahPohybu(mod, token)) || 'sprint';
  }

  // Akce kouzlení: u postavy se vybírá kouzlo — hra z jeho vyvolání spočítá, kolik akcí kouzlení potřebuje (2 × kola), ohlásí, kdy začne působit, a štítek „Kouzlí“
  // po poslední akci sama sundá; zásah před předposlední akcí kouzlo zruší. Nestvůra a kouzla, jejichž vyvolání hra nepozná, mají ruční počet akcí (jen PJ);
  // bez výběru se akce jen odečte a štítek sundá PJ. Vybrané kouzlo se drží, dokud se kouzlení nedokončí nebo nepřeruší.
  const kouzlaTvora = new Map();   // token_id → {kouzla, pocita} (null = načítá se)
  const kouzloVolba = new Map();   // token_id → id vybraného kouzla
  function nactiKouzla(tokenId) {
    if (kouzlaTvora.has(tokenId)) return kouzlaTvora.get(tokenId);
    kouzlaTvora.set(tokenId, null);
    fetch('api/kouzla_tvora.php?token_id=' + tokenId, {cache: 'no-store'})
      .then((r) => r.json())
      .then((d) => { kouzlaTvora.set(tokenId, d && Array.isArray(d.kouzla) ? d : {kouzla: [], pocita: false}); kreslit(); kreslitVyzvy(true); })
      .catch(() => { kouzlaTvora.set(tokenId, {kouzla: [], pocita: false}); });
    return null;
  }
  const akciText = (n) => n + (n === 1 ? ' akce' : (n < 5 ? ' akce' : ' akcí'));

  // Kouzla „ihned“ nestojí žádnou akci a váží se k jiné akci (pohyb, útok, obrana…), jedna akce jich unese víc. Hráč je přidá do fronty (kouzlo, cíl, případně trvání a magy);
  // fronta se připojí k další akci a hra je vyhodnotí ve chvíli té akce. U obrany se místo toho sešlou hned tlačítkem (opravy obrany se přepočtou před hodem).
  const ihnedFronta = new Map();   // token_id → [{kouzlo_id, nazev, cil_token_id, cil_nazev, zbyva_kol, magy}]
  let ihnedOtevrene = false;
  function ihnedBlok(tokenId, poslat) {
    const data = nactiKouzla(tokenId);
    const wrap = el('div', 'ihned-blok');
    wrap.append(el('div', 'ihned-nadpis', poslat ? 'Kouzla ihned k obraně' : 'Kouzla ihned (připojí se k další akci)'));
    const ihnedKouzla = data && data.kouzla ? data.kouzla.filter((k) => k.akci === 0) : [];
    if (!data) { wrap.append(el('div', 'ini-napoveda', 'Načítám kouzla…')); return wrap; }
    if (!ihnedKouzla.length) { wrap.append(el('div', 'ini-napoveda', 'Tvor nezná žádné kouzlo s vyvoláním „ihned“.')); return wrap; }
    const fronta = ihnedFronta.get(tokenId) || [];
    ihnedFronta.set(tokenId, fronta);
    const radek = el('div', 'kouzleni-radek');
    const vyber = document.createElement('select');
    vyber.className = 'input kouzleni-vyber';
    vyber.append(new Option('— kouzlo ihned —', ''));
    ihnedKouzla.forEach((k) => vyber.append(new Option(k.nazev, String(k.id))));
    const cilSel = document.createElement('select');
    cilSel.className = 'input kouzleni-cil';
    cilSel.title = 'Na koho kouzlo míří (výchozí: na sebe)';
    cilSel.append(new Option('na sebe', ''));
    getTokeny().filter((t) => jeTvor(t) && t.id !== tokenId).forEach((t) => cilSel.append(new Option(t.label, String(t.id))));
    const kola = document.createElement('input');
    kola.type = 'number'; kola.min = '0'; kola.max = '99'; kola.placeholder = 'kol';
    kola.className = 'input kouzleni-akci';
    kola.title = 'Na kolik kol kouzlo působí (jen když to kouzlo vyžaduje; 0 = bez limitu)';
    const magy = document.createElement('input');
    magy.type = 'number'; magy.min = '0'; magy.max = '999'; magy.placeholder = 'magy';
    magy.className = 'input kouzleni-akci';
    magy.title = 'Kolik magů kouzlo stojí, když to hra nepozná z ceny (prázdné = podle ceny kouzla)';
    radek.append(vyber, cilSel, kola, magy, tlacitko('Přidat', 'btn-ghost akce-btn', () => {
      if (!vyber.value) return;
      if (fronta.length >= 5) { logLine('K jedné akci jde připojit nejvýš 5 kouzel „ihned“.'); return; }
      fronta.push({
        kouzlo_id: Number(vyber.value), nazev: vyber.selectedOptions[0].textContent,
        cil_token_id: cilSel.value ? Number(cilSel.value) : undefined, cil_nazev: cilSel.value ? cilSel.selectedOptions[0].textContent : 'na sebe',
        zbyva_kol: kola.value !== '' ? Number(kola.value) : undefined, magy: magy.value !== '' ? Number(magy.value) : undefined,
      });
      kreslit();
      if (poslat) kreslitVyzvy(true);
    }, 'Přidá kouzlo do fronty'));
    wrap.append(radek);
    const chipy = el('div', 'ihned-chipy');
    fronta.forEach((k, i) => {
      const c = el('span', 'ihned-chip', k.nazev + ' → ' + k.cil_nazev + ' ');
      c.append(tlacitko('✕', 'btn-ghost ihned-x', () => { fronta.splice(i, 1); kreslit(); if (poslat) kreslitVyzvy(true); }, 'Odebrat z fronty'));
      chipy.append(c);
    });
    wrap.append(chipy);
    if (poslat) {
      const b = tlacitko('Seslat teď', 'btn-secondary akce-btn', () => {
        const seznam = fronta.map((k) => ({kouzlo_id: k.kouzlo_id, cil_token_id: k.cil_token_id, zbyva_kol: k.zbyva_kol, magy: k.magy}));
        poslat(seznam).then((d) => { if (d && !d.error) { ihnedFronta.set(tokenId, []); kreslit(); kreslitVyzvy(true); } });
      }, 'Kouzla se vyhodnotí hned; opravy obrany se přepočtou před hodem');
      b.disabled = !fronta.length;
      wrap.append(b);
    }
    return wrap;
  }
  // Přepínač „držet čelo“ (při chůzi po cestě se tvor nenatáčí, třeba při ústupu čelem k nepříteli) si prohlížeč pamatuje.
  const DRZET_CELO_KLIC = 'dracak_souboj_drzet_celo';
  const ctiDrzetCelo = () => { try { return localStorage.getItem(DRZET_CELO_KLIC) === '1'; } catch (e) { return false; } };
  const ulozDrzetCelo = (v) => { try { localStorage.setItem(DRZET_CELO_KLIC, v ? '1' : '0'); } catch (e) { /* bez paměti prohlížeče */ } };

  // druhVynuceny: null = volný tah (pohyb, útok, čelo), 'obrana_krok' = obrana a krok (jen jeden hex, bez útoku)
  function zahajitPlan(druhVynuceny) {
    const t = aktivniToken();
    if (!t || !jeHex()) return;
    const u = ucastnici.find((x) => x.token_id === t.id);
    plan = {
      druhVynuceny: druhVynuceny || null, tokenId: t.id, odkud: pivotTokenu(t), tvar: tvarDe(t), orientPred: orientTokenu(t),
      strana: u ? u.strana : null, kam: null, orient: null, cil: null, x: t.x, y: t.y, smer0: t.smer || 0, hexu0: t.hexu || 1,
      cesta: [], drzetCelo: ctiDrzetCelo(), posledniKlik: {t: 0, kl: '', x: 0, y: 0}, bublina: null,
    };
    plan.otoceniMax = (POHYB.krok || {}).otoceni_max || 270;
    plan.maxOtoceni = Math.floor(plan.otoceniMax / 60);
    plan.dosah = plan.druhVynuceny ? 1 : dosahPlanu(t);
    plan.dosahBezPast = plan.druhVynuceny ? 1 : nejdalPohybu(t);
    plan.zabrane = obsazene(plan.tokenId);
    plan.zabraneSet = new Set(plan.zabrane.keys());
    kreslit();
    prekreslitOverlay();
  }
  // Vypnutí plánování (Esc, „Zrušit“): do dalšího tahu nebo do stisku „Plánovat tah“ se sám nezapne.
  function zrusitPlan() {
    if (!plan) return;
    zruseno.add(klicTahu());
    plan = null;
    kreslit();
    prekreslitOverlay();
  }

  const klicBunky = (b) => b.col + ',' + b.row;
  const stejnaBunka = (a, b) => a.col === b.col && a.row === b.row;

  // Tělo tvora: tvar podle počtu hexů, otočný hex = hex středu tokenu, orientace ze směru (assets/js/hexy.js).
  const tvarDe = (t) => (tvarTokenu ? tvarTokenu(t) : TVAR_JEDEN);
  const pivotTokenu = (t) => { const b = hexBunka(t.x, t.y); return {col: b.col, row: b.row}; };
  const orientTokenu = (t) => hexy.orientace(tvarDe(t), t.smer || 0);
  const teloTokenu = (t) => hexy.bunkyTvaru(tvarDe(t), pivotTokenu(t), orientTokenu(t));
  const jeTvor = (t) => ['postava', 'nestvura_instance'].includes(t.typ_entity);

  // Hexy, na kterých stojí jiní tvorové celým tělem (postavy a nestvůry — značky nepřekáží).
  function obsazene(krome) {
    const m = new Map();
    getTokeny().forEach((t) => {
      if (t.id === krome || !jeTvor(t)) return;
      teloTokenu(t).forEach((b) => m.set(klicBunky(b), t));
    });
    return m;
  }
  const teloVolne = (bunky, zabrane) => bunky.every((b) => !zabrane.has(klicBunky(b)));

  // Úsečka A–B protíná zeď, která blokuje pohyb? (jen pro hráče; PJ smí přes zeď.)
  function orient(ax, ay, bx, by, cx, cy) {
    const v = (by - ay) * (cx - bx) - (bx - ax) * (cy - by);
    return Math.abs(v) < 1e-9 ? 0 : (v > 0 ? 1 : 2);
  }
  function naUsecce(ax, ay, bx, by, cx, cy) {
    return cx <= Math.max(ax, bx) + 1e-9 && cx >= Math.min(ax, bx) - 1e-9 && cy <= Math.max(ay, by) + 1e-9 && cy >= Math.min(ay, by) - 1e-9;
  }
  function usecky(x1, y1, x2, y2, x3, y3, x4, y4) {
    const o1 = orient(x1, y1, x2, y2, x3, y3), o2 = orient(x1, y1, x2, y2, x4, y4);
    const o3 = orient(x3, y3, x4, y4, x1, y1), o4 = orient(x3, y3, x4, y4, x2, y2);
    if (o1 !== o2 && o3 !== o4) return true;
    return (o1 === 0 && naUsecce(x1, y1, x2, y2, x3, y3)) || (o2 === 0 && naUsecce(x1, y1, x2, y2, x4, y4))
      || (o3 === 0 && naUsecce(x3, y3, x4, y4, x1, y1)) || (o4 === 0 && naUsecce(x3, y3, x4, y4, x2, y2));
  }
  function zedVPrekazi(a, b) {
    if (jePj) return false;
    const sa = hexStred(a.col, a.row), sb = hexStred(b.col, b.row);
    return (getZdi() || []).some((z) => z.blokuje_pohyb && usecky(sa.x, sa.y, sb.x, sb.y, z.x1, z.y1, z.x2, z.y2));
  }

  // --- Plánování cesty ---
  // Cesta je řada bodů A, B, C… (u sprintu s dovedností až D): každý bod je hex hned vedle předchozího
  // (vzdálenější hex se doplní nejkratší trasou). Tvor se po cestě sám natáčí ve směru kroku (kdo chce
  // couvat nebo uhýbat bokem, zapne „držet čelo“) a každé otočení se počítá do rozpočtu akce (270°).
  // Ověření dělá stejná funkce jako na serveru (assets/js/hexy.js overCestu = includes/vtt_hexy.php).
  const PISMENA = 'ABCDEFGH';
  const oPoCeste = () => (plan.cesta.length ? plan.cesta[plan.cesta.length - 1].o : plan.orientPred);
  const konecCesty = () => (plan.cesta.length ? plan.cesta[plan.cesta.length - 1] : plan.odkud);
  const maUtok = () => plan.druhVynuceny === null;
  function overPlan(cesta, oKonec) {
    return hexy.overCestu(plan.tvar, plan.odkud, plan.orientPred, cesta, oKonec, plan.zabraneSet, zedVPrekazi, plan.dosah, plan.maxOtoceni);
  }
  // Bod cesty do hexu n z hexu cur s čelem oCur: čelo při vstupu podle režimu (po směru kroku / držet čelo).
  function sestavBod(cur, oCur, n, drzet) {
    const d = [0, 1, 2, 3, 4, 5].find((k) => stejnaBunka(hexy.soused(cur, k), n));
    const o = drzet ? oCur : hexy.orientDoSmeru(plan.tvar, d, oCur);
    return {col: n.col, row: n.row, o};
  }
  // Body z hexů od hexu cur s čelem oCur
  function bodyZeHexu(cur, oCur, hexyTrasy, drzet) {
    const body = [];
    for (const n of hexyTrasy) {
      const b = sestavBod(cur, oCur, n, drzet);
      body.push(b);
      cur = n;
      oCur = b.o;
    }
    return body;
  }
  // Všechny nejkratší řady hexů z cur do cíle (každý krok zmenší vzdálenost o 1; nejvýš pár možností).
  function nejkratsiTrasy(cur, cil) {
    const d = hexy.vzdalenost(cur, cil);
    if (d === 0) return [[]];
    const trasy = [];
    for (let k = 0; k < 6; k++) {
      const n = hexy.soused(cur, k);
      if (hexy.vzdalenost(n, cil) === d - 1) nejkratsiTrasy(n, cil).forEach((t) => trasy.push([n, ...t]));
    }
    return trasy;
  }
  // Všechny hexy do vzdálenosti r od b (včetně b).
  function hexyVPoloměru(b, r) {
    const [q0, r0] = hexy.naOsy(b);
    const out = [];
    for (let dq = -r; dq <= r; dq++) {
      for (let dr = Math.max(-r, -dq - r); dr <= Math.min(r, -dq + r); dr++) out.push(hexy.zOsy(q0 + dq, r0 + dr));
    }
    return out;
  }
  const popisChyby = (v, body) => {
    const bod = body[v.krok] ? 'bod ' + PISMENA[v.krok] : 'konec cesty';
    switch (v.chyba) {
      case 'dlouha': return 'Víc bodů už nemáš — dál než ' + popisHexu(plan.dosah) + ' nedojdeš.';
      case 'telo': return 'Do hexu (' + bod + ') se tělo nevejde — stojí tam nebo zasahuje cizí tvor.';
      case 'zed': return 'Přes zeď se do hexu (' + bod + ') nedostaneš.';
      case 'otoceni_blokovane': return 'U místa „' + bod + '“ se tělo nemůže otočit — překáží cizí tvor.';
      case 'otoceni_rozpocet': return 'To je moc otáčení — celkem nejvýš ' + plan.otoceniMax + '° za akci (zkus „držet čelo“ nebo kratší cestu).';
      case 'beze_zmeny': return 'Cesta končí tam, kde začala.';
      default: return 'Tudy cesta nevede.';
    }
  };
  // Proč plán nesahá dál než k plan.dosah hexům: únava, nebo chybějící dovednost na čtvrtý hex.
  function textMeze() {
    const t = tokenPodleId(plan.tokenId);
    const zakazy = (kolo && kolo.aktivni_zakazy) || [];
    if (plan.druhVynuceny) return 'Obrana a krok vede jen na sousední hex.';
    if (zakazy.includes('sprint')) return 'Unavená postava nesmí sprintovat — dál než ' + popisHexu(plan.dosah) + ' nedojdeš.';
    if (!maDovednostBehu(t) && (POHYB.sprint || {}).bonus_dovednost > 0 && !vyzvyZapnute()) {
      return 'Čtvrtý bod potřebuje dovednost Běh na krátké vzdálenosti — PJ ji nasazuje ručně jako efekt.';
    }
    return 'Víc bodů už nemáš — dál než ' + popisHexu(plan.dosah) + ' nedojdeš.';
  }

  // Přidá bod (nebo trasu k bodu) na konec cesty; vrací text chyby nebo null.
  function pridejDoHexu(c) {
    const cur = konecCesty();
    if (plan.cesta.length >= plan.dosah) return textMeze();
    const zbyva = plan.dosah - plan.cesta.length;
    const d = hexy.vzdalenost(cur, c);
    if (d === 0) return null;
    if (d > zbyva) return 'Tam už nedosáhneš — zbývá ' + popisHexu(zbyva) + '.';
    let nejlepsi = null, chyba = null;
    nejkratsiTrasy(cur, c).forEach((trasa) => {
      const body = plan.cesta.concat(bodyZeHexu(cur, oPoCeste(), trasa, plan.drzetCelo));
      const v = overPlan(body, body[body.length - 1].o);
      if (!v.ok) { if (!chyba) chyba = popisChyby(v, body); return; }
      if (nejlepsi === null || v.otoceni < nejlepsi.v.otoceni) nejlepsi = {body, v};
    });
    if (nejlepsi === null) return chyba || 'Tudy cesta nevede.';
    plan.cesta = nejlepsi.body;
    // Už zvolené konečné čelo musí na novém konci cesty pořád platit
    if (plan.orient !== null && !overPlan(plan.cesta, plan.orient).ok) {
      plan.orient = null;
      logLine('Zvolené čelo na konci už nejde — čelo se vrátilo na směr cesty.');
    }
    plan.kam = {col: c.col, row: c.row};
    return null;
  }
  function zpetOBod() {
    if (!plan || !plan.cesta.length) return;
    plan.cesta.pop();
    plan.kam = plan.cesta.length ? {col: konecCesty().col, row: konecCesty().row} : null;
    if (plan.orient !== null && plan.cesta.length && !overPlan(plan.cesta, plan.orient).ok) plan.orient = null;
    if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
    kreslit();
    prekreslitOverlay();
  }
  // Přepnutí „po směru cesty“ / „držet čelo“: cesta se přepočítá ze stejných hexů.
  function prepniDrzetCelo() {
    if (!plan) return;
    const nove = !plan.drzetCelo;
    if (plan.cesta.length) {
      const body = bodyZeHexu(plan.odkud, plan.orientPred, plan.cesta.map((p) => ({col: p.col, row: p.row})), nove);
      const v = overPlan(body, plan.orient !== null ? plan.orient : body[body.length - 1].o);
      if (!v.ok) { logLine('V tomhle režimu už stejná cesta nejde: ' + popisChyby(v, body)); return; }
      plan.cesta = body;
    }
    plan.drzetCelo = nove;
    ulozDrzetCelo(nove);
    if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
    kreslit();
    prekreslitOverlay();
  }
  // Hexy vedle konce cesty, kam lze udělat další krok (v aktuálním režimu otáčení).
  function dalsiKroky() {
    const m = new Map();
    if (plan.cesta.length >= plan.dosah) return m;
    const cur = konecCesty(), oCur = oPoCeste();
    for (let k = 0; k < 6; k++) {
      const n = hexy.soused(cur, k);
      const body = plan.cesta.concat([sestavBod(cur, oCur, n, plan.drzetCelo)]);
      if (overPlan(body, body[body.length - 1].o).ok) m.set(klicBunky(n), {col: n.col, row: n.row});
    }
    return m;
  }

  // Kde se tvor po akci ocitne a kam bude hledět.
  const duchBunka = () => plan.kam || plan.odkud;
  const duchOrient = () => (plan.orient !== null ? plan.orient : oPoCeste());

  function orientPovolena(o) {
    if (plan.cesta.length) return overPlan(plan.cesta, o).ok;
    // bez cesty je to otočka na místě: libovolný úhel, tělo se ale cestou nesmí střetnout s cizím tvorem
    return hexy.otoceniMezi(plan.tvar, plan.odkud, plan.orientPred, o, plan.zabraneSet) !== null;
  }
  function duvodZakazuOrient(o) {
    if (!teloVolne(hexy.bunkyTvaru(plan.tvar, duchBunka(), o), plan.zabrane)) return 'Tak otočené by tělo zasahovalo do cizího hexu.';
    if (!plan.cesta.length) return 'Otočit se tam nejde — cestou překáží cizí tvor.';
    return 'Při pohybu se tvor otočí celkem nejvýš o ' + plan.otoceniMax + '° (součet všech otočení před pohybem, během něj i po něm).';
  }
  const SIPKY_HRANA = ['↑', '↗', '↘', '↓', '↙', '↖'];
  const popisOrient = (o) => (plan.tvar.typ_otaceni === 'hrana' ? SIPKY_HRANA[o] : SMER_SIPKY[o]);

  // --- Co z plánu vychází: druh akce (vždy za jednu akci) ---
  // Pohyb o 1 hex = krok, 2 = běh, 3 = sprint (nebo 4 s dovedností); s útokem krok / běh / sprint a útok;
  // bez pohybu útok, otočka a útok, nebo jen otočka.
  function druhPlanu() {
    const m = plan.cesta.length;
    if (plan.druhVynuceny) return m === 1 ? plan.druhVynuceny : null;
    const t = tokenPodleId(plan.tokenId);
    const utok = plan.cil !== null;
    if (m >= 1) {
      const mod = modPohybu(m, t);
      return utok ? DRUH_S_UTOKEM[mod] : mod;
    }
    const otoceni = duchOrient() !== plan.orientPred;
    if (utok) return otoceni ? 'otocka_utok' : 'utok';
    return otoceni ? 'otocka' : null;
  }
  function planJeHotov() {
    const druh = druhPlanu();
    if (!druh) return false;
    if (plan.cesta.length && !overPlan(plan.cesta, duchOrient()).ok) return false;
    if (kolo && kolo.kvota !== null && kolo.kvota !== undefined && kolo.kvota < 1) return false;
    return true;
  }

  function popisPlanu() {
    const casti = [];
    const druh = druhPlanu();
    if (!druh && !plan.cesta.length && plan.cil === null) {
      casti.push(plan.druhVynuceny ? 'Klikni na sousední hex, kam uděláš krok.'
        : 'Klikni na hex (body cesty A, B, C… až ' + popisHexu(plan.dosah) + '), na nepřítele (dojde k němu a zaútočí) nebo do vlastního těla (čelo).');
    }
    if (plan.cesta.length) {
      casti.push('Cesta: ' + plan.cesta.map((p, i) => PISMENA[i]).join(' → ') + ' (' + plan.cesta.length + ' z ' + popisHexu(plan.dosah) + ')');
      const v = overPlan(plan.cesta, duchOrient());
      if (v.ok) casti.push('Otočení celkem: ' + v.otoceni * 60 + '° z ' + plan.otoceniMax + '°');
    }
    if (plan.cesta.length || plan.orient !== null) {
      casti.push('Čelo na konci: ' + popisOrient(duchOrient()) + (plan.cesta.length && plan.orient === null ? ' (změníš klikem do těla tvora nebo ↶ ↷; stojí otočení)' : ''));
    }
    if (potrebujePast()) {
      casti.push('Čtvrtý hex: napřed hod na dovednost Běh na krátké vzdálenosti (síla, střední) — PJ ho vidí a může upravit. Nepovede-li se, nepůjdeš a akce se neutratí.');
    }
    if (maUtok()) {
      const t = plan.cil ? tokenPodleId(plan.cil) : null;
      if (t) casti.push('Útok na: ' + t.label);
      else if (plan.cesta.length) casti.push('Útok: klikni na nepřítele v oranžových hexech (nebo potvrď jen pohyb).');
    }
    return casti;
  }

  function otocOPlan(krok) {
    if (!plan) return;
    let o = duchOrient();
    for (let i = 0; i < 5; i++) {
      o = ((o + krok) % 6 + 6) % 6;
      if (orientPovolena(o)) {
        plan.orient = (!plan.cesta.length && o === plan.orientPred) ? null : o;
        if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
        kreslit();
        prekreslitOverlay();
        return;
      }
    }
    logLine('Tak se otočit nejde.');
  }

  function odeslatPlan() {
    if (!plan || !planJeHotov()) return;
    const druh = druhPlanu();
    const telo = {token_id: plan.tokenId, druh};
    if (plan.cesta.length) telo.cesta = plan.cesta.map((p) => ({col: p.col, row: p.row, smer: hexy.smerZOrientace(plan.tvar, p.o)}));
    if (plan.orient !== null || druh === 'otocka' || druh === 'otocka_utok') telo.smer = hexy.smerZOrientace(plan.tvar, duchOrient());
    if (plan.cil) telo.cil_token_id = plan.cil;
    const poslano = sIhned(telo);   // kouzla „ihned“ z fronty se připojí i k naplánovanému pohybu a útoku
    postJson('api/tah_akce.php', Object.assign({mapa_id: mapaId}, poslano)).then((d) => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      vyprazdniIhned(poslano);
      plan = null;
      if (d.ceka_na_hod) logLine('Čtvrtý hex sprintu: nejdřív se hází na dovednost Běh na krátké vzdálenosti.');
      obnovitStav();
    });
  }

  // --- Útok: klik na nepřítele naplánuje cestu k němu a útok ---
  const jeNepritel = (t) => {
    // strana tokenu viditelného na mapě přichází i hráčům (nestvůry nejsou v seznamu účastníků, ten je tajný)
    const u = ucastnici.find((x) => x.token_id === t.id);
    const strana = kolo && kolo.strany && kolo.strany[t.id] !== undefined ? kolo.strany[t.id] : (u ? u.strana : null);
    return !!strana && !!plan.strana && strana !== plan.strana;
  };
  const jeVDosahuUtoku = (T, pivot, o) => {
    const telo = new Set(teloTokenu(T).map(klicBunky));
    return hexy.dosahUtoku(plan.tvar, pivot, o).some((b) => telo.has(klicBunky(b)));
  };
  // Vrací text chyby/oznámení (nebo null).
  function naplanujUtok(T) {
    // 1) Cesta už je (nebo se jen otáčím): stačí cíl, případně čelo na konci
    const konec = duchBunka();
    if (jeVDosahuUtoku(T, konec, duchOrient())) { plan.cil = T.id; return null; }
    let o1 = null;
    for (let o = 0; o < 6; o++) {
      if (!orientPovolena(o) || !jeVDosahuUtoku(T, konec, o)) continue;
      if (o1 === null || hexy.rozdilSmeru(duchOrient(), o) < hexy.rozdilSmeru(duchOrient(), o1)) o1 = o;
    }
    if (o1 !== null) {
      plan.orient = (!plan.cesta.length && o1 === plan.orientPred) ? null : o1;
      plan.cil = T.id;
      return null;
    }
    // 2) Od začátku: nejméně hexů, pak nejméně otáčení; z poslední polohy nejlepší hex, odkud cíl dosáhne
    const kandidati = [];
    hexyVPoloměru(plan.odkud, plan.dosah).forEach((P) => {
      const d = hexy.vzdalenost(plan.odkud, P);
      for (let o = 0; o < 6; o++) {
        if (!jeVDosahuUtoku(T, P, o)) continue;
        if (d === 0) {
          const t = hexy.otoceniMezi(plan.tvar, plan.odkud, plan.orientPred, o, plan.zabraneSet);
          if (t !== null) kandidati.push({d, turns: t, cesta: [], P, o});
        } else {
          nejkratsiTrasy(plan.odkud, P).forEach((trasa) => {
            const body = bodyZeHexu(plan.odkud, plan.orientPred, trasa, plan.drzetCelo);
            const v = overPlan(body, o);
            if (v.ok) kandidati.push({d, turns: v.otoceni, cesta: body, P, o});
          });
        }
      }
    });
    kandidati.sort((a, b) => a.d - b.d || a.turns - b.turns);
    if (kandidati.length) {
      const k = kandidati[0];
      plan.cesta = k.cesta;
      plan.kam = k.cesta.length ? {col: k.P.col, row: k.P.row} : null;
      const prirozene = k.cesta.length ? k.cesta[k.cesta.length - 1].o : plan.orientPred;
      plan.orient = k.o !== prirozene ? k.o : null;
      plan.cil = T.id;
      return null;
    }
    // 3) Nedosáhne: cesta co nejblíž (bez útoku)
    const teloT = teloTokenu(T);
    let nejblize = null;
    hexyVPoloměru(plan.odkud, plan.dosah).forEach((P) => {
      const d = hexy.vzdalenost(plan.odkud, P);
      if (d === 0) return;
      const kdeKonci = Math.min(...teloT.map((b) => hexy.vzdalenost(P, b)));
      nejkratsiTrasy(plan.odkud, P).forEach((trasa) => {
        const body = bodyZeHexu(plan.odkud, plan.orientPred, trasa, plan.drzetCelo);
        const v = overPlan(body, body[body.length - 1].o);
        if (!v.ok) return;
        if (nejblize === null || kdeKonci < nejblize.kdeKonci || (kdeKonci === nejblize.kdeKonci && (d < nejblize.d || (d === nejblize.d && v.otoceni < nejblize.turns)))) {
          nejblize = {kdeKonci, d, turns: v.otoceni, body, P};
        }
      });
    });
    if (nejblize !== null) {
      plan.cesta = nejblize.body;
      plan.kam = {col: nejblize.P.col, row: nejblize.P.row};
      plan.orient = null;
      plan.cil = null;
      return 'Na ' + T.label + ' tentokrát nedosáhneš (nejdál ' + popisHexu(plan.dosah) + ') — plán vede co nejblíž, zaútočíš dalším tahem.';
    }
    return 'Na ' + T.label + ' se nedá dojít ani zaútočit.';
  }
  // „Bez pohybu“: zaútočit z místa (otočit se jen, když je potřeba)
  function jenUtok() {
    const T = plan.cil ? tokenPodleId(plan.cil) : null;
    if (!T) return;
    const stareCesta = plan.cesta, stareKam = plan.kam, stareOrient = plan.orient;
    plan.cesta = [];
    plan.kam = null;
    plan.orient = null;
    let o1 = null;
    for (let o = 0; o < 6; o++) {
      if (!orientPovolena(o) || !jeVDosahuUtoku(T, plan.odkud, o)) continue;
      if (o1 === null || hexy.rozdilSmeru(plan.orientPred, o) < hexy.rozdilSmeru(plan.orientPred, o1)) o1 = o;
    }
    if (o1 === null) {
      plan.cesta = stareCesta; plan.kam = stareKam; plan.orient = stareOrient;
      logLine('Z místa na ' + T.label + ' nedosáhneš.');
      return;
    }
    plan.orient = o1 === plan.orientPred ? null : o1;
    kreslit();
    prekreslitOverlay();
  }

  // Dvojklik potvrdí: útok na zvoleném cíli, pohyb na posledním bodu cesty, otočku na vlastním hexu.
  function dvojklikPotvrzuje(c) {
    if (!planJeHotov()) return false;
    if (plan.cil) return (tvorNaBunce(c) || {}).id === plan.cil;
    if (plan.cesta.length) return stejnaBunka(c, konecCesty());
    return plan.orient !== null && stejnaBunka(c, plan.odkud);
  }

  // Klik na mapu při plánování; m = bod v souřadnicích mapy.
  function klik(m) {
    // Výběr cíle kouzla: klepnutí na tvora (na sebe = cíl „na sebe“).
    if (rezim === 'kouzlo' && kouzloVyber) {
      if (!jeHex()) return;
      const c0 = hexBunka(m.x, m.y);
      const kl0 = klicBunky(c0);
      const T0 = getTokeny().find((t) => jeTvor(t) && teloTokenu(t).some((b) => klicBunky(b) === kl0));
      if (T0) { kouzloVyber.cil = kolo && T0.id === kolo.aktivni_token_id ? 0 : T0.id; kreslit(); prekreslitOverlay(); }
      return;
    }
    if (!plan) return;
    // plovoucí potvrzení u posledního bodu
    if (plan.bublina && Math.hypot(m.x - plan.bublina.x, m.y - plan.bublina.y) < plan.bublina.r) { odeslatPlan(); return; }
    const c = hexBunka(m.x, m.y);
    const kl = klicBunky(c);
    const ted = Date.now();
    // Dvojklik = dva rychlé kliky na tom samém místě (klik do těla kousek vedle středu je volba čela, ne dvojklik)
    const dvojklik = plan.posledniKlik.kl === kl && ted - plan.posledniKlik.t < 400
      && Math.hypot(m.x - plan.posledniKlik.x, m.y - plan.posledniKlik.y) < gridPx() * 0.15;
    plan.posledniKlik = {t: ted, kl, x: m.x, y: m.y};
    if (dvojklik && dvojklikPotvrzuje(c)) { odeslatPlan(); return; }
    // klik na jiného tvora: nepřítel = útok, jinak nic
    const T = tvorNaBunce(c);
    if (T) {
      if (!maUtok()) { logLine('Obrana a krok je jen pohyb na sousední hex.'); return; }
      if (!jeNepritel(T)) { logLine(T.label + ' není nepřítel v souboji — útok ani cesta tudy nevede.'); return; }
      const zprava = naplanujUtok(T);
      if (zprava) logLine(zprava);
      kreslit();
      prekreslitOverlay();
      return;
    }
    const pivot = duchBunka();
    const telo = new Set(hexy.bunkyTvaru(plan.tvar, pivot, duchOrient()).map(klicBunky));
    const jeKrok = dalsiKroky().has(kl) && !stejnaBunka(c, pivot);
    if (telo.has(kl) && !jeKrok) {
      // klik do těla tvora: čelem k místu kliknutí (úhel od otočného hexu)
      const st = hexStred(pivot.col, pivot.row);
      if (Math.hypot(m.x - st.x, m.y - st.y) < gridPx() * 0.22) return;
      const o = hexy.orientace(plan.tvar, Math.atan2(m.y - st.y, m.x - st.x) * 180 / Math.PI);
      if (!orientPovolena(o)) {
        logLine(duvodZakazuOrient(o));
        return;
      }
      plan.orient = (!plan.cesta.length && o === plan.orientPred) ? null : o;
      if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
    } else {
      const chyba = pridejDoHexu(c);
      if (chyba) { logLine(chyba); return; }
      if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
    }
    kreslit();
    prekreslitOverlay();
  }
  function tvorNaBunce(c) {
    const kl = klicBunky(c);
    return getTokeny().find((t) => t.id !== plan.tokenId && jeTvor(t) && teloTokenu(t).some((b) => klicBunky(b) === kl)) || null;
  }
  // Hexy, na které dosáhne útok z místa a s čelem podle plánu (tvar určuje, které to jsou).
  const dosahUtoku = () => hexy.dosahUtoku(plan.tvar, duchBunka(), duchOrient());
  function predniJeCil(tokenId) {
    const t = tokenPodleId(tokenId);
    if (!t) return false;
    const dosah = new Set(dosahUtoku().map(klicBunky));
    return teloTokenu(t).some((b) => dosah.has(klicBunky(b)));
  }

  // --- Overlay na mapě (SVG nad tokeny, pod mlhou; neinteraktivní) ---
  function obrys(cx, cy, size) {
    let pts = '';
    for (let i = 0; i < 6; i++) {
      const ang = Math.PI / 180 * (60 * i - 30);
      pts += (i ? ' ' : '') + (cx + size * Math.cos(ang)).toFixed(1) + ',' + (cy + size * Math.sin(ang)).toFixed(1);
    }
    return pts;
  }
  function kresliHex(b, trida) {
    const st = hexStred(b.col, b.row);
    const p = document.createElementNS(SVGNS, 'polygon');
    p.setAttribute('points', obrys(st.x, st.y, gridPx() * 0.96));
    p.setAttribute('class', 'sb-hex ' + trida);
    soubojSvg.appendChild(p);
    return st;
  }
  // Šipka k jedné z šesti stran (úhel ve stupních, 0 = doprava, po směru hodinových ručiček)
  function kresliSipku(st, uhel, trida) {
    const size = gridPx();
    const a = Math.PI / 180 * uhel;
    const px = (r, w) => (st.x + r * Math.cos(a) - w * Math.sin(a)).toFixed(1) + ',' + (st.y + r * Math.sin(a) + w * Math.cos(a)).toFixed(1);
    const t = document.createElementNS(SVGNS, 'polygon');
    t.setAttribute('points', px(size * 0.9, 0) + ' ' + px(size * 0.5, -size * 0.24) + ' ' + px(size * 0.5, size * 0.24));
    t.setAttribute('class', 'sb-sipka ' + trida);
    soubojSvg.appendChild(t);
  }
  // Čelní hrana (tvary D a E): žlutá čára po vnějších stranách obou horních hexů
  function kresliCelo(tvar, pivot, orient, trida) {
    if (tvar.typ_otaceni !== 'hrana') return;
    hexy.bunkyTvaru(tvar, pivot, orient, 'predni').forEach((b) => {
      const st = hexStred(b.col, b.row);
      const cara = document.createElementNS(SVGNS, 'polyline');
      cara.setAttribute('points', hexy.celniHranaBody(st.x, st.y, gridPx() * 0.96, orient).map((v) => v[0].toFixed(1) + ',' + v[1].toFixed(1)).join(' '));
      cara.setAttribute('class', trida);
      soubojSvg.appendChild(cara);
    });
  }
  // Nepřátelé v souboji (cíle kliknutí): slabý kroužek, ať je vidět, kam se dá útočit
  function kresliNepratele() {
    getTokeny().forEach((t) => {
      if (t.id === plan.tokenId || !jeTvor(t) || !jeNepritel(t)) return;
      const p = pivotTokenu(t);
      const st = hexStred(p.col, p.row);
      const kruh = document.createElementNS(SVGNS, 'circle');
      kruh.setAttribute('cx', st.x.toFixed(1)); kruh.setAttribute('cy', st.y.toFixed(1));
      kruh.setAttribute('r', (gridPx() * 0.66).toFixed(1));
      kruh.setAttribute('class', 'sb-nepritel');
      soubojSvg.appendChild(kruh);
    });
  }
  // Plovoucí potvrzení u konce cesty (na dotyku se na dvojklik nedá spolehnout); klik na něj plán provede.
  function kresliBublinu(stDuch) {
    const g = gridPx();
    const x = stDuch.x, y = stDuch.y - g * (plan.tvar.bunky.length > 1 ? 2.4 : 1.55), r = g * 0.42;
    const kruh = document.createElementNS(SVGNS, 'circle');
    kruh.setAttribute('cx', x.toFixed(1)); kruh.setAttribute('cy', y.toFixed(1)); kruh.setAttribute('r', r.toFixed(1));
    kruh.setAttribute('class', 'sb-bublina');
    soubojSvg.appendChild(kruh);
    const t = document.createElementNS(SVGNS, 'text');
    t.setAttribute('x', x.toFixed(1)); t.setAttribute('y', y.toFixed(1)); t.setAttribute('font-size', (g * 0.55).toFixed(1));
    t.setAttribute('class', 'sb-bublina-text');
    t.textContent = '✓';
    soubojSvg.appendChild(t);
    plan.bublina = {x, y, r};
  }
  // Cesta: čára přes středy hexů od startu a body A, B, C… (poslední zvýrazněný)
  function kresliCestu() {
    if (!plan.cesta.length) return;
    const stredy = [plan.odkud].concat(plan.cesta).map((b) => hexStred(b.col, b.row));
    const cara = document.createElementNS(SVGNS, 'polyline');
    cara.setAttribute('points', stredy.map((st) => st.x.toFixed(1) + ',' + st.y.toFixed(1)).join(' '));
    cara.setAttribute('class', 'sb-cesta');
    soubojSvg.appendChild(cara);
    plan.cesta.forEach((p, i) => {
      const st = stredy[i + 1];
      const kruh = document.createElementNS(SVGNS, 'circle');
      kruh.setAttribute('cx', st.x.toFixed(1)); kruh.setAttribute('cy', st.y.toFixed(1));
      kruh.setAttribute('r', (gridPx() * 0.3).toFixed(1));
      kruh.setAttribute('class', 'sb-bod' + (i === plan.cesta.length - 1 ? ' posledni' : ''));
      soubojSvg.appendChild(kruh);
      const t = document.createElementNS(SVGNS, 'text');
      t.setAttribute('x', st.x.toFixed(1)); t.setAttribute('y', st.y.toFixed(1));
      t.setAttribute('font-size', (gridPx() * 0.4).toFixed(1));
      t.setAttribute('class', 'sb-bod-pismeno');
      t.textContent = PISMENA[i];
      soubojSvg.appendChild(t);
    });
  }
  function prekreslitOverlay() {
    if (!soubojSvg) return;
    soubojSvg.innerHTML = '';
    if (!jeHex() || !kolo) return;
    if (rezim === 'kouzlo') {
      // Kouzlo: dosah (když ho kniha udává číslem) a vybraný cíl; plán pohybu se během výběru kouzla nekreslí.
      const kdo = aktivniToken();
      const data = kdo ? kouzlaTvora.get(kdo.id) : null;
      const kz = kouzloVyber && data && data.kouzla ? data.kouzla.find((k) => k.id === kouzloVyber.id) : null;
      if (kdo && kz && kz.dosah_sahy) {
        const st = hexStred(pivotTokenu(kdo).col, pivotTokenu(kdo).row);
        const kruh = document.createElementNS(SVGNS, 'circle');
        kruh.setAttribute('cx', st.x.toFixed(1)); kruh.setAttribute('cy', st.y.toFixed(1));
        kruh.setAttribute('r', (kz.dosah_sahy * gridPx()).toFixed(1));
        kruh.setAttribute('class', 'sb-dosah-kouzla');
        soubojSvg.appendChild(kruh);
      }
      const cilT = kouzloVyber ? (kouzloVyber.cil === 0 ? kdo : (kouzloVyber.cil ? tokenPodleId(kouzloVyber.cil) : null)) : null;
      if (cilT) teloTokenu(cilT).forEach((b) => kresliHex(b, 'sb-cil-kouzla'));
      return;
    }
    if (plan) {
      dalsiKroky().forEach((b) => kresliHex(b, 'sb-cil'));
      if (maUtok()) kresliNepratele();
      const duch = duchBunka();
      const stDuch = hexStred(duch.col, duch.row);
      const velky = plan.tvar.bunky.length > 1;
      if (plan.kam || velky) {
        // Kde tvor skončí: u jednoho hexu kruh, u většího těla jeho hexy a čelo
        if (velky) {
          hexy.bunkyTvaru(plan.tvar, duch, duchOrient()).forEach((b) => kresliHex(b, 'sb-duch-bunka' + (stejnaBunka(b, duch) ? ' otocny' : '')));
          kresliCelo(plan.tvar, duch, duchOrient(), 'sb-duch-celo');
        } else {
          const kruh = document.createElementNS(SVGNS, 'circle');
          kruh.setAttribute('cx', stDuch.x); kruh.setAttribute('cy', stDuch.y);
          kruh.setAttribute('r', (gridPx() * 0.62).toFixed(1));
          kruh.setAttribute('class', 'sb-duch');
          soubojSvg.appendChild(kruh);
        }
      }
      kresliCestu();
      {
        for (let k = 0; k < 6; k++) {
          const vybrany = k === duchOrient();
          kresliSipku(stDuch, hexy.smerZOrientace(plan.tvar, k), vybrany ? 'vybrana' : (orientPovolena(k) ? 'mozna' : 'zakazana'));
        }
      }
      plan.bublina = null;
      if (planJeHotov()) kresliBublinu(stDuch);
      if (maUtok()) {
        const dosah = dosahUtoku();
        const vDosahu = new Set(dosah.map(klicBunky));
        dosah.forEach((b) => kresliHex(b, 'sb-predni'));
        getTokeny().forEach((t) => {
          if (t.id === plan.tokenId || !jeTvor(t)) return;
          const telo = teloTokenu(t);
          if (!telo.some((b) => vDosahu.has(klicBunky(b)))) return;
          const vybrany = plan.cil === t.id;
          if (telo.length > 1) {
            telo.forEach((b) => kresliHex(b, 'sb-terc-telo' + (vybrany ? ' vybrany' : '')));
          } else {
            const st = hexStred(telo[0].col, telo[0].row);
            const kruh = document.createElementNS(SVGNS, 'circle');
            kruh.setAttribute('cx', st.x); kruh.setAttribute('cy', st.y);
            kruh.setAttribute('r', (gridPx() * 0.7).toFixed(1));
            kruh.setAttribute('class', 'sb-terc' + (vybrany ? ' vybrany' : ''));
            soubojSvg.appendChild(kruh);
          }
        });
      }
      return;
    }
    // Bez plánování: hexy, kam smí útočit tvor na tahu (u jednoho hexu 3 hexy před ním), ať je to vidět všem.
    const t = kolo.faze === 'kolo' ? aktivniToken() : null;
    if (t && jeTvor(t)) {
      hexy.dosahUtoku(tvarDe(t), pivotTokenu(t), orientTokenu(t)).forEach((b) => kresliHex(b, 'sb-predni slaby'));
    }
  }

  // --- Okno hodu na iniciativu ---
  const mHod = document.getElementById('modalHod');
  const hodNadpis = document.getElementById('hodNadpis');
  const hodPopis = document.getElementById('hodPopis');
  const hodKostkaBtn = document.getElementById('hodKostkaBtn');
  const hodVysledek = document.getElementById('hodVysledek');
  const hodRucne = document.getElementById('hodRucne');
  const hodRucneBtn = document.getElementById('hodRucneBtn');
  const hodChyba = document.getElementById('hodChyba');
  let hodCil = null;
  let hodOdesilam = false;

  function otevritHod(tokenIds, label, u) {
    if (!mHod) return;
    hodCil = {tokenIds};
    hodNadpis.textContent = 'Iniciativa — ' + label;
    const prvni = tokenPodleId(tokenIds[0]);
    hodPopis.textContent = 'Hoď 1k6. Únava, naložení'
      + (kolo && kolo.cislo_kola === 1 && kolo.prekvapeni !== 'zadne' ? ', překvapení' : '')
      + (kolo && kolo.iniciativa_v2 ? ', kouzlení a vícenásobné útoky z ohlášeného úmyslu' : ' a kouzlení')
      + ' se přičtou samy.' + (pohybAuto.zbran && prvni && prvni.typ_entity === 'postava' ? ' Postih těžké zbraně v ruce (obouruční, supertěžké, těžká kuše) se přičte sám podle zbraně, kterou postava drží.' : '')
      + (u && u.hozeno ? ' (Přehození přepíše dřívější hod.)' : '');
    if (kolo && kolo.iniciativa_v2 && u) {
      // Co se z úmyslu přičte samo a k čemu se tvor tím zavazuje (h1622)
      const uk = ['utok', 'strelba'].includes(u.umysl) && u.utoku_za_2_kola && kolo.ini_utoky && kolo.ini_utoky[u.utoku_za_2_kola];
      if (u.umysl === 'kouzleni') hodPopis.textContent += ' Úmysl: kouzlení (+1) — první akce kola i aspoň polovina akcí kola mají být kouzlení, jinak v příštím kole −3 k iniciativě.';
      else if (u.ini_trida && ['utok', 'strelba'].includes(u.umysl)) hodPopis.textContent += ' Úmysl: ' + UMYSLY[u.umysl].toLowerCase() + ' — ' + u.ini_trida.popis + ' (bonus povolání se přičte sám). První akce kola i aspoň polovina akcí kola mají být útok (obrana se počítá k polovině), jinak v příštím kole −3 k iniciativě.';
      else if (uk) hodPopis.textContent += ' Úmysl: ' + UMYSLY[u.umysl].toLowerCase() + ', ' + u.utoku_za_2_kola + ' ' + (u.utoku_za_2_kola < 5 ? 'útoky' : 'útoků') + ' za 2 kola (+' + uk + ') — první akce kola i aspoň polovina akcí kola mají být útok (obrana se počítá k polovině), jinak v příštím kole −3 k iniciativě.';
      else if (!u.umysl) hodPopis.textContent += ' Nemáš ohlášený úmysl — kouzlení a vícenásobné útoky se nezapočítají.';
    }
    mHod.querySelectorAll('.hod-bonus-check').forEach((cb) => {
      cb.checked = false;
      const zamknout = prvni && prvni.typ_entity === 'postava' && pohybAuto[cb.dataset.auto];
      cb.disabled = !!zamknout;
      cb.parentElement.style.opacity = zamknout ? '.5' : '';
    });
    document.getElementById('hodJinyBonus').value = '0';
    hodVysledek.textContent = '';
    hodVysledek.classList.remove('padlo');
    hodRucne.value = '';
    hodChyba.style.display = 'none';
    nastavTlacitka(true);
    hodKostkaBtn.dataset.bez3d = '';
    mHod.style.display = '';
    nactiKostky().then((ok) => {
      if (!ok) {
        hodKostkaBtn.dataset.bez3d = '1';
        hodKostkaBtn.lastChild.textContent = ' Hodit (bez 3D kostky)';
      } else {
        hodKostkaBtn.lastChild.textContent = ' Hodit kostkou';
      }
    });
  }
  function zavritHod() { if (mHod) mHod.style.display = 'none'; hodCil = null; }
  function nastavTlacitka(povoleno) {
    hodKostkaBtn.disabled = !povoleno;
    hodRucneBtn.disabled = !povoleno;
    hodRucne.disabled = !povoleno;
  }
  function ukazChybuHodu(text) { hodChyba.textContent = text; hodChyba.style.display = ''; }

  async function odeslatHod(hod, rucne) {
    if (!hodCil || hodOdesilam) return;
    hodOdesilam = true;
    nastavTlacitka(false);
    hodChyba.style.display = 'none';
    const bonusIds = Array.from(mHod.querySelectorAll('.hod-bonus-check:checked')).map((c) => parseInt(c.value, 10));
    const jiny = parseInt(document.getElementById('hodJinyBonus').value, 10) || 0;
    try {
      const d = await postJson('api/souboj_hod.php', {mapa_id: mapaId, token_ids: hodCil.tokenIds, hod, rucne, bonus_ids: bonusIds, jiny_bonus: jiny, auto: true});
      if (d.error) {
        ukazChybuHodu(d.error);
        nastavTlacitka(true);
      } else {
        const v = d.vysledky && d.vysledky[0];
        if (v) hodVysledek.textContent = 'Padlo ' + hod + ' ' + (v.modifikator >= 0 ? '+' : '−') + Math.abs(v.modifikator) + ' = ' + v.vysledek + ' → ' + v.akce_celkem + ' ' + akci(v.akce_celkem);
        (d.upozorneni || []).forEach((t) => logLine(t));
        if (v && v.zavazek) logLine(v.label + ' si za ' + ZAVAZEK_NAZVY[v.zavazek] + ' vzal(a) bonus k iniciativě: první akce kola a aspoň ' + v.zavazek_akci + ' ' + akci(v.zavazek_akci) + ' mají být ' + ZAVAZEK_NAZVY[v.zavazek] + ' (jinak v příštím kole −3).');
        obnovitStav();
        setTimeout(zavritHod, 1100);
      }
    } catch (e) {
      ukazChybuHodu('Hod se nepodařilo odeslat.');
      nastavTlacitka(true);
    } finally {
      hodOdesilam = false;
    }
  }
  if (hodKostkaBtn) {
    hodKostkaBtn.addEventListener('click', async () => {
      if (!hodCil) return;
      if (hodKostkaBtn.dataset.bez3d === '1' && !kostky3DPripraveny()) {
        // 3D kostka se nepodařila načíst — číslo vybere prohlížeč na kliknutí
        const hod = 1 + Math.floor(Math.random() * 6);
        hodVysledek.textContent = 'Padlo ' + hod;
        odeslatHod(hod, false);
        return;
      }
      nastavTlacitka(false);
      hodVysledek.textContent = 'Kostka letí…';
      const hody = await hodit3D(1, 6);
      if (!hody) {
        hodVysledek.textContent = '';
        ukazChybuHodu('Kostku se nepodařilo zobrazit. Zapiš hod z vlastní kostky, nebo to zkus znovu.');
        nastavTlacitka(true);
        return;
      }
      hodVysledek.textContent = 'Padlo ' + hody[0];
      hodVysledek.classList.add('padlo');
      odeslatHod(hody[0], false);
    });
    hodRucneBtn.addEventListener('click', () => {
      const h = parseInt(hodRucne.value, 10);
      if (!(h >= 1 && h <= 6)) { ukazChybuHodu('Hod na 1k6 je číslo 1 až 6.'); return; }
      odeslatHod(h, true);
    });
    hodRucne.addEventListener('keydown', (e) => { if (e.key === 'Enter') hodRucneBtn.click(); });
    document.getElementById('modalHodZavrit').addEventListener('click', zavritHod);
    mHod.addEventListener('click', (e) => { if (e.target === mHod) zavritHod(); });
  }

  // --- Okno zahájení souboje / účastníků (PJ) ---
  const mSouboj = document.getElementById('modalSouboj');
  const mSeznam = document.getElementById('soubojUcastnici');
  const mPrekvapeni = document.getElementById('soubojPrekvapeni');
  const mUlozit = document.getElementById('soubojUlozit');
  const mChyba = document.getElementById('soubojChyba');

  function otevritZahajeni() {
    if (!jePj || !mSouboj) return;
    const uprava = !!kolo;
    document.getElementById('soubojNadpis').textContent = uprava ? 'Účastníci souboje' : 'Zahájit souboj';
    mUlozit.textContent = uprava ? 'Uložit' : 'Začít';
    mPrekvapeni.value = uprava ? kolo.prekvapeni : 'zadne';
    mPrekvapeni.disabled = uprava && (kolo.faze === 'kolo' || kolo.cislo_kola !== 1);
    // Zbraně v ruce se řeší jen při zahájení; při úpravě účastníků za boje je seznam rovnou rozbalený.
    document.getElementById('soubojZbraneBlok').style.display = uprava ? 'none' : '';
    document.getElementById('soubojPrekvapeniBlok').style.display = mPrekvapeni.disabled ? 'none' : '';
    document.getElementById('soubojUprava').open = uprava;
    mChyba.style.display = 'none';
    mSeznam.innerHTML = '';
    const tokeny = getTokeny().filter((t) => t.typ_entity === 'postava' || t.typ_entity === 'nestvura_instance');
    const vybrane = getVybrane().filter((id) => tokenPodleId(id) && ['postava', 'nestvura_instance'].includes(tokenPodleId(id).typ_entity));
    const soucasni = new Map(ucastnici.map((u) => [u.token_id, u]));
    tokeny.forEach((t) => {
      const jeUcastnik = soucasni.has(t.id);
      const predvolena = uprava ? jeUcastnik : (vybrane.length >= 2 ? vybrane.includes(t.id) : true);
      // Řádek není <label> — má dvě zaškrtávátka (účast a zbraň v ruce), každé s vlastním popiskem.
      const radek = el('div', 'souboj-radek');
      const kdo = el('label', 'souboj-kdo');
      const cb = el('input');
      cb.type = 'checkbox';
      cb.checked = predvolena;
      cb.dataset.tokenId = String(t.id);
      const jm = el('span', 'souboj-jmeno', t.label + (t.viditelny ? '' : ' (skrytý)'));
      const st = el('select', 'input');
      st.append(new Option('Družina', 'druzina'), new Option('Nepřátelé', 'nepratele'));
      st.value = jeUcastnik ? soucasni.get(t.id).strana : (t.typ_entity === 'postava' ? 'druzina' : 'nepratele');
      st.dataset.tokenId = String(t.id);
      kdo.append(cb, jm);
      radek.append(kdo);
      // Zbraň v ruce jde doladit u jednotlivce (jen postavy, jen při zahájení) — výchozí je, že tasili všichni.
      if (!uprava && t.typ_entity === 'postava') {
        const zb = el('label', 'souboj-zbran');
        const zcb = el('input');
        zcb.type = 'checkbox';
        zcb.checked = true;
        zcb.dataset.zbran = '1';
        zb.append(zcb, document.createTextNode(' zbraň v ruce'));
        radek.append(zb);
      }
      radek.append(st);
      mSeznam.append(radek);
    });
    if (!tokeny.length) mSeznam.append(el('p', 'note', 'Na mapě nejsou žádné postavy ani nestvůry.'));
    prepocitatVybrane();
    mSouboj.style.display = '';
  }
  // Kolik je zaškrtnuto z kolika — u dlouhého seznamu je jinak nevidět, co vlastně do iniciativy půjde.
  const radkyOkna = () => Array.from(mSeznam.querySelectorAll('.souboj-radek')).map((r) => ({
    r, cb: r.querySelector('input[type=checkbox]:not([data-zbran])'), zbran: r.querySelector('input[data-zbran]'),
    strana: r.querySelector('select').value, jmeno: r.querySelector('.souboj-jmeno').textContent,
  }));
  // Souhrn stran nahoře, stav voleb překvapení a zbraní — vše se odvozuje ze seznamu, ať se nic nedrží dvakrát.
  function prekresliSouhrn() {
    const radky = radkyOkna().filter((x) => x.cb.checked);
    ['druzina', 'nepratele'].forEach((strana) => {
      const moji = radky.filter((x) => x.strana === strana);
      const velke = strana === 'druzina' ? 'Druzina' : 'Nepratele';
      document.getElementById('soubojPocet' + velke).textContent = String(moji.length);
      // Stejná jména se sloučí („Goblin ×3“).
      const pocty = new Map();
      moji.forEach((x) => { const j = x.jmeno.replace(/ \d+$/, ''); pocty.set(j, (pocty.get(j) || 0) + 1); });
      document.getElementById('soubojJmena' + velke).textContent = moji.length
        ? Array.from(pocty, ([j, n]) => (n > 1 ? j + ' ×' + n : j)).join(', ') : 'nikdo';
      const tl = mSouboj.querySelector('[data-zbrane="' + strana + '"]');
      const sPostavou = moji.filter((x) => x.zbran);
      tl.style.display = sPostavou.length ? '' : 'none';
      tl.setAttribute('aria-pressed', sPostavou.length && sPostavou.every((x) => x.zbran.checked) ? 'true' : 'false');
    });
    const nejakaPostava = radky.some((x) => x.zbran);
    if (!kolo) document.getElementById('soubojZbraneBlok').style.display = nejakaPostava ? '' : 'none';
    mSouboj.querySelectorAll('[data-prekvapeni]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.prekvapeni === mPrekvapeni.value ? 'true' : 'false'));
  }
  if (mSouboj) {
    mSouboj.querySelectorAll('[data-prekvapeni]').forEach((b) => b.addEventListener('click', () => {
      if (mPrekvapeni.disabled) return;
      mPrekvapeni.value = b.dataset.prekvapeni;
      prekresliSouhrn();
    }));
    // Tlačítko strany přepne zbraň v ruce všem jejím postavám najednou.
    mSouboj.querySelectorAll('[data-zbrane]').forEach((b) => b.addEventListener('click', () => {
      const zapnout = b.getAttribute('aria-pressed') !== 'true';
      radkyOkna().filter((x) => x.zbran && x.strana === b.dataset.zbrane).forEach((x) => { x.zbran.checked = zapnout; });
      prekresliSouhrn();
    }));
    const hlp = mSouboj.querySelector('.tvor-hlp');
    if (hlp) hlp.addEventListener('click', () => hlp.classList.toggle('otevreno'));
  }
  function prepocitatVybrane() {
    const el2 = document.getElementById('soubojPocet');
    if (!el2 || !mSeznam) return;
    prekresliSouhrn();
    const vsechny = mSeznam.querySelectorAll('.souboj-radek input[type=checkbox]:not([data-zbran])');
    const zaskrtnuto = mSeznam.querySelectorAll('.souboj-radek input[type=checkbox]:not([data-zbran]):checked').length;
    el2.textContent = vsechny.length ? 'do iniciativy půjde ' + zaskrtnuto + ' z ' + vsechny.length : '';
  }
  if (mSeznam) mSeznam.addEventListener('change', prepocitatVybrane);
  // Rychlý výběr (jen družina / vše / nikoho): na mapě s desítkami tvorů se po jednom klikat nedá.
  // Kdo zaškrtnutý není, do pořadí tahů nevstoupí a PJ ho řídí ručně, kdy uzná za vhodné.
  document.querySelectorAll('[data-souboj-vyber]').forEach((b) => b.addEventListener('click', () => {
    const co = b.dataset.soubojVyber;
    mSeznam.querySelectorAll('.souboj-radek').forEach((r) => {
      const cb = r.querySelector('input[type=checkbox]:not([data-zbran])');
      const jeDruzina = r.querySelector('select').value === 'druzina';
      cb.checked = co === 'vse' ? true : (co === 'nic' ? false : jeDruzina);
    });
    prepocitatVybrane();
  }));

  function zavritZahajeni() { if (mSouboj) mSouboj.style.display = 'none'; }
  if (mSouboj) {
    mUlozit.addEventListener('click', async () => {
      const radky = Array.from(mSeznam.querySelectorAll('.souboj-radek')).map((r) => ({
        tokenId: parseInt(r.querySelector('input').dataset.tokenId, 10),
        zaskrtnuto: r.querySelector('input').checked,
        strana: r.querySelector('select').value,
        zbran: r.querySelector('input[data-zbran]') ? r.querySelector('input[data-zbran]').checked : null,
      }));
      const vybrani = radky.filter((r) => r.zaskrtnuto);
      if (!vybrani.length) { mChyba.textContent = 'Vyber aspoň jednoho účastníka.'; mChyba.style.display = ''; return; }
      let d;
      if (!kolo) {
        d = await postJson('api/souboj_start.php', {
          mapa_id: mapaId, prekvapeni: mPrekvapeni.value,
          ucastnici: vybrani.map((r) => Object.assign({token_id: r.tokenId, strana: r.strana}, r.zbran === null ? {} : {zbran: r.zbran})),
        });
        // Co se stalo se zbraněmi, vidí jen PJ (do události pro hráče to nejde — prozradilo by to výzbroj cizích postav).
        if (d && d.zbrane && logLine) {
          d.zbrane.tasili.forEach((z) => logLine(z.kdo + ' tasí: ' + z.zbran));
          d.zbrane.schovali.forEach((z) => logLine(z.kdo + ' začíná beze zbraně v ruce (' + z.zbran + ' zůstává u těla)'));
          if (d.zbrane.bez.length) logLine('Bez zbraně u sebe: ' + d.zbrane.bez.join(', '));
        }
      } else {
        const soucasni = new Map(ucastnici.map((u) => [u.token_id, u]));
        const telo = {mapa_id: mapaId, pridat: [], odebrat: [], strany: {}};
        radky.forEach((r) => {
          const byl = soucasni.get(r.tokenId);
          if (r.zaskrtnuto && !byl) telo.pridat.push({token_id: r.tokenId, strana: r.strana});
          else if (!r.zaskrtnuto && byl) telo.odebrat.push(r.tokenId);
          else if (r.zaskrtnuto && byl && byl.strana !== r.strana) telo.strany[r.tokenId] = r.strana;
        });
        if (!mPrekvapeni.disabled && mPrekvapeni.value !== kolo.prekvapeni) telo.prekvapeni = mPrekvapeni.value;
        d = await postJson('api/souboj_ucastnici.php', telo);
      }
      if (d.error) { mChyba.textContent = d.error; mChyba.style.display = ''; return; }
      zavritZahajeni();
      obnovitStav();
    });
    document.getElementById('modalSoubojZavrit').addEventListener('click', zavritZahajeni);
    mSouboj.addEventListener('click', (e) => { if (e.target === mSouboj) zavritZahajeni(); });
  }
  if (tbSouboj) tbSouboj.addEventListener('click', otevritZahajeni);
  const ucastniciBtn = document.getElementById('souborUcastniciBtn');
  if (ucastniciBtn) ucastniciBtn.addEventListener('click', otevritZahajeni);
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      zavritHod(); zavritZahajeni();
      // Esc nejdřív zavře podnabídku karty (kouzlo, zbraň, jiné, nabídka PJ), teprve pak vypne plánování.
      if (rezim || pjMenu) { rezim = null; kouzloVyber = null; pjMenu = false; upravaAkci = false; kreslit(true); prekreslitOverlay(); }
      else zrusitPlan();
    }
    const vPoli = e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT' || e.target.isContentEditable);
    if (plan && !vPoli && e.key === 'Backspace') { e.preventDefault(); zpetOBod(); }
    if (plan && !vPoli && e.key === 'Enter' && planJeHotov()) { e.preventDefault(); odeslatPlan(); }
  });

  // --- Výzvy k hodu (útok, obrana, past): karta dole uprostřed, vidí ji všichni u stolu ---
  // Čísla (ÚČ/OČ, opravy, součty) počítá server; klient je jen ukazuje, hází kostkou nebo vezme
  // hod z vlastní kostky a pošle ho (hra/api/vyzva_hod.php). PJ smí číslo před hodem upravit.
  let vyzvy = [];
  let vysledekKarta = null;
  let vysledekTimer = null;
  let vyzvaPodpis = '';
  let hodiSe = false;
  const vyzvaPanel = el('div', 'vyzva-panel');
  vyzvaPanel.style.display = 'none';
  document.body.appendChild(vyzvaPanel);

  const ZNAMENKO = (n) => (n >= 0 ? '+' : '−') + Math.abs(n);
  const BEZ_ZNAMENKA = (n) => (n < 0 ? '−' : '') + Math.abs(n);

  // 1k6+ : po šestce se hází znovu (kniha, kap. Kostky b58). Bez načtené 3D kostky rozhodne prohlížeč.
  async function hoditK6Plus() {
    const hody = [];
    const ok3d = await nactiKostky();
    for (let i = 0; i < 30; i++) {
      let h;
      if (ok3d && kostky3DPripraveny()) {
        const r = await hodit3D(1, 6);
        if (!r) return null;
        h = r[0];
      } else {
        h = 1 + Math.floor(Math.random() * 6);
      }
      hody.push(h);
      if (h !== 6) break;
    }
    return hody;
  }
  async function hoditK10() {
    const ok3d = await nactiKostky();
    if (ok3d && kostky3DPripraveny()) {
      const r = await hodit3D(1, 10);
      return r ? [r[0]] : null;
    }
    return [1 + Math.floor(Math.random() * 10)];
  }

  // Obecné kostky (tik efektu: 1k4, 2k6…): kolik kostek, kolik stěn.
  async function hoditKostky(pocet, typ) {
    const ok3d = await nactiKostky();
    if (ok3d && kostky3DPripraveny()) return hodit3D(pocet, typ);
    return Array.from({length: pocet}, () => 1 + Math.floor(Math.random() * typ));
  }

  async function odeslatHodVyzvy(v, hody, soucet) {
    if (hodiSe) return;
    hodiSe = true;
    kreslitVyzvy(true);
    try {
      const telo = {vyzva_id: v.id};
      if (hody) telo.hody = hody; else { telo.soucet = soucet; telo.rucne = true; }
      const d = await volat('api/vyzva_hod.php', telo);
      if (d && d.error) { /* chyba už je v logu */ }
    } finally {
      hodiSe = false;
      kreslitVyzvy(true);
    }
  }
  async function hoditKostkou(v) {
    if (hodiSe) return;
    hodiSe = true;
    kreslitVyzvy(true);
    const hody = v.druh === 'tik' ? await hoditKostky(v.pocet_kostek || 1, v.kostka)
      : (v.kostka === 10 ? await hoditK10() : await hoditK6Plus());
    hodiSe = false;
    if (!hody) { logLine('Kostku se nepodařilo zobrazit — zapiš hod z vlastní kostky, nebo to zkus znovu.'); kreslitVyzvy(true); return; }
    odeslatHodVyzvy(v, hody, null);
  }
  function upravitVyzvu(v, zmena) {
    return volat('api/vyzva_uprava.php', Object.assign({vyzva_id: v.id}, zmena));
  }

  function poleCisla(hodnota, min, max, onChange, sirka) {
    const i = el('input', 'input vyzva-cislo');
    i.type = 'number';
    i.min = String(min);
    i.max = String(max);
    i.value = hodnota === null || hodnota === undefined ? '' : String(hodnota);
    if (sirka) i.style.width = sirka;
    i.addEventListener('change', () => onChange(i.value === '' ? null : parseInt(i.value, 10)));
    i.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Enter') i.blur(); });
    return i;
  }

  function nazevDruhu(v) {
    return v.druh === 'utok' ? 'Útok' : (v.druh === 'obrana' ? 'Obrana' : (v.druh === 'tik' ? (v.efekt_nazev || 'Efekt') : 'Hod proti pasti'));
  }

  // Karta výzvy k hodu (kroky 4 a 5 návrhu): nadpis říká, co se po mně chce, pod ním je vidět číslo, které se k hodu
  // přičte, a jedno hlavní tlačítko. Hráč nic nevyplňuje; úpravy čísel má PJ schované pod „Upravit čísla…“.
  let vyzvaPjOtevrena = false;
  const vyzvaRucne = new Set();   // výzvy s otevřeným polem „hodil jsem u stolu“
  function kartaVyzvy(v) {
    const moje = !!v.ovladam && !v.je_pj;
    const k = el('div', 'vyzva-karta vyzva-' + v.druh + (moje ? ' moje' : ''));
    k.dataset.vyzva = String(v.id);
    const hlava = el('div', 'vyzva-hlava');
    const zbranNazev = v.druh === 'utok' && v.varianty ? ((v.varianty.find((z) => z.id === v.zbran_id) || {}).nazev || null) : null;
    let nadpis;
    let pod = '';
    if (v.druh === 'utok') {
      nadpis = moje ? 'Hoď na útok' : 'Útok — ' + v.label;
      pod = (zbranNazev ? zbranNazev + ' ' : '') + '→ ' + (v.cil_label || '?');
    } else if (v.druh === 'obrana') {
      nadpis = moje ? (v.utocnik_label || 'Protivník') + ' na tebe útočí' : 'Obrana — ' + v.label;
      pod = moje ? 'Rozhodni se a hoď — házíš v obou případech.' : 'proti ' + (v.utocnik_label || '?');
    } else {
      nadpis = nazevDruhu(v) + ' — ' + v.label;
    }
    hlava.appendChild(el('strong', '', nadpis));
    if (pod) hlava.appendChild(el('span', 'vyzva-pod', pod));
    k.appendChild(hlava);
    if (v.druh === 'tik' && v.popis) k.appendChild(el('div', 'vyzva-poznamka', v.popis + ' — hoď ' + (v.pocet_kostek || 1) + 'k' + v.kostka + ', na začátku kola'));
    if (v.druh === 'past' && v.popis) k.appendChild(el('div', 'vyzva-poznamka', v.popis + ' (' + (v.vlastnost || '') + ', ' + (v.nebezpecnost_nazev || '') + ')'));
    const popisKostky = v.druh === 'tik' ? (v.pocet_kostek || 1) + 'k' + v.kostka : (v.kostka === 10 ? '1k10' : '1k6+');

    // Už hozená výzva (útok čeká na obranu) se zmenší na výsledek hodu.
    if (v.stav !== 'ceka') {
      k.classList.add('hotovo');
      k.appendChild(el('div', 'vyzva-radek vyzva-celkem', '')).append(
        el('span', '', popisKostky + ' [' + (v.hody || []).join(' + ') + ']' + (v.cislo === null ? '' : ' ' + ZNAMENKO(v.cislo))),
        el('span', 'vyzva-hodnota', '= ' + BEZ_ZNAMENKA(v.celkem)));
      k.appendChild(el('div', 'vyzva-poznamka', v.druh === 'utok' ? 'Čeká se na obranu ' + (v.cil_label || '') + '.' : 'Hozeno.'));
      return k;
    }

    if (v.druh === 'obrana') {
      if (v.utok_celkem !== null && v.utok_celkem !== undefined) {
        k.appendChild(el('div', 'vyzva-poznamka', 'Útok ' + (v.utocnik_label || '?') + ': ' + v.utok_celkem + (v.strana === 'bok' ? ' · z boku nebo zezadu' : '')));
      } else if (v.strana === 'bok') {
        k.appendChild(el('div', 'vyzva-poznamka', 'Útok z boku nebo zezadu.'));
      }
      if (v.duvod_nebrani) k.appendChild(el('div', 'vyzva-poznamka', 'Nemůže se bránit zbraní: ' + (v.duvod_nebrani === 'vyřazený' ? 'je vyřazený' : v.duvod_nebrani) + (v.nehybny ? ' (platí jako nehybný)' : '')));
      if (v.nasobek_duvod) {
        const n = el('div', 'vyzva-radek');
        n.appendChild(el('span', '', 'zranění se násobí úrovní (' + v.nasobek_duvod + ')'));
        if (v.je_pj) n.appendChild(poleCisla(v.nasobek, 1, 99, (x) => upravitVyzvu(v, {nasobek: x}), '64px'));
        else n.appendChild(el('span', 'vyzva-hodnota', v.nasobek ? '×' + v.nasobek : ''));
        k.appendChild(n);
      }
      const volba = el('div', 'vyzva-volba');
      const tlVolby = (klic, text, podText, title) => {
        const b = el('button', 'vyzva-volba-b' + (v.volba === klic ? ' vybrana' : ''));
        b.type = 'button';
        b.dataset.volba = klic;
        b.setAttribute('aria-pressed', v.volba === klic ? 'true' : 'false');
        b.title = title;
        b.append(el('strong', '', text), el('span', '', podText));
        b.addEventListener('click', () => upravitVyzvu(v, {volba: klic}));
        return b;
      };
      const b1 = tlVolby('branit', 'Bráním se', 'zbraní · stojí akci',
        v.muze_branit || v.je_pj ? 'Brání se zbraní — stojí jednu akci ze zbytku' : 'Tvor už nemá žádnou akci');
      const b2 = tlVolby('nebranit', 'Nebráním se', 'jen zbrojí · −3', 'Jen brněním, bez zbraně — postih −3 (z boku nebo zezadu dohromady −6)');
      b1.disabled = !v.smi_volit || (!v.muze_branit && !v.je_pj) || !!v.duvod_nebrani;
      if (v.duvod_nebrani) b1.title = 'Nemůže se bránit zbraní — ' + v.duvod_nebrani;
      b2.disabled = !v.smi_volit;
      volba.append(b1, b2);
      k.appendChild(volba);
      if (!v.smi_volit && v.volba === null) k.appendChild(el('div', 'vyzva-poznamka', 'Čeká se, až se ' + v.label + ' rozhodne, jak se bude bránit.'));
      // Kouzla „ihned“ k obraně jen u toho, kdo nějaké zná (jinak by to byl řádek o ničem).
      const dataK = v.smi_volit ? nactiKouzla(v.token_id) : null;
      if (v.smi_volit && dataK && dataK.kouzla.some((x) => x.akci === 0)) k.appendChild(ihnedBlok(v.token_id, (seznam) => upravitVyzvu(v, {ihned: seznam})));
    }

    // Z čeho se hod skládá — číslo je vidět rovnou (u hodu nestvůry ho hráč nevidí vůbec).
    const skrytoSlozky = !v.je_pj && v.zaklad === null && v.cislo === null;
    if (!skrytoSlozky) {
      const sl = el('div', 'vyzva-slozeni');
      sl.appendChild(el('span', 'bk-stitek', popisKostky));
      if (v.druh !== 'tik') {
        const nazevZakladu = v.zaklad_nazev || (v.druh === 'utok' ? 'ÚČ' : (v.druh === 'obrana' ? 'OČ' : 'bonus vlastnosti'));
        const z = el('span', 'bk-stitek cislo');
        z.append(el('strong', '', v.zaklad === null ? '?' : (v.druh === 'past' ? ZNAMENKO(v.zaklad) : String(v.zaklad))), document.createTextNode(' ' + nazevZakladu));
        sl.appendChild(z);
      }
      (v.opravy || []).forEach((o) => sl.appendChild(el('span', 'bk-stitek zvyrazneny', ZNAMENKO(o.hodnota) + ' ' + o.nazev)));
      if (v.modifikator) sl.appendChild(el('span', 'bk-stitek zvyrazneny', ZNAMENKO(v.modifikator) + ' oprava PJ'));
      if (v.cislo !== null && v.druh !== 'tik') {
        const c = el('span', 'bk-celkem vyzva-soucet', 'k hodu ' + ZNAMENKO(v.cislo));
        c.title = 'Číslo bez hodu: ' + BEZ_ZNAMENKA(v.cislo);
        sl.appendChild(c);
      }
      k.appendChild(sl);
    }
    if (v.druh === 'utok' && v.varianty && v.varianty.length > 1 && (v.ovladam || v.je_pj)) {
      const r = el('div', 'vyzva-radek');
      r.appendChild(el('span', '', 'zbraň / útok'));
      const sel = el('select', 'input');
      sel.setAttribute('aria-label', 'Kterou zbraní se útočí');
      v.varianty.forEach((z) => {
        const o = el('option', '', z.nazev + ' (ÚČ ' + z.uc + ')');
        o.value = String(z.id);
        if (z.id === v.zbran_id) o.selected = true;
        sel.appendChild(o);
      });
      sel.addEventListener('change', () => upravitVyzvu(v, {zbran_id: parseInt(sel.value, 10)}));
      r.appendChild(sel);
      k.appendChild(r);
    }
    // PJ: úpravy čísel před hodem (základ, oprava, tma, útočnost, obtížnost) — schované, dokud je nepotřebuje.
    if (v.je_pj) {
      const det = document.createElement('details');
      det.className = 'vyzva-pj';
      det.open = vyzvaPjOtevrena || v.cislo === null || (v.druh === 'past' && !v.nebezpecnost_nastavena);
      det.addEventListener('toggle', () => { vyzvaPjOtevrena = det.open; });
      det.appendChild(el('summary', '', 'Upravit čísla…'));
      const tab = el('div', 'vyzva-slozky');
      const radekPole = (nazev, pole) => { const r = el('div', 'vyzva-radek'); r.append(el('span', '', nazev), pole); tab.appendChild(r); };
      if (v.druh !== 'tik') {
        radekPole(v.zaklad_nazev || (v.druh === 'utok' ? 'ÚČ' : (v.druh === 'obrana' ? 'OČ' : 'bonus vlastnosti')),
          v.druh === 'past' ? el('span', 'vyzva-hodnota', v.zaklad === null ? '—' : ZNAMENKO(v.zaklad)) : poleCisla(v.zaklad, 0, 60, (n) => upravitVyzvu(v, {zaklad: n}), '64px'));
      }
      radekPole('oprava (PJ)', poleCisla(v.modifikator, -30, 30, (n) => upravitVyzvu(v, {modifikator: n === null ? 0 : n}), '64px'));
      if (v.druh === 'utok' || v.druh === 'obrana') {
        // Boj ve tmě (h1613 b10738): kdo protivníka nevidí, má postih k útoku i k obraně. Server ho navrhne u hráčů na mapě „Tma“, PJ ho přepne.
        const sel = el('select', 'input');
        sel.setAttribute('aria-label', 'Vidí protivníka?');
        [['', 'vidí (bez postihu)'], ['nevidi', 'nevidí (−5)'], ['tusi', 'tuší, kde stojí (−4)'], ['nocni', 'noční vidění (−2)']].forEach(([hodn, text]) => {
          const o = el('option', '', text);
          o.value = hodn;
          if ((v.tma || '') === hodn) o.selected = true;
          sel.appendChild(o);
        });
        sel.addEventListener('change', () => upravitVyzvu(v, {tma: sel.value || null}));
        radekPole('vidí protivníka?', sel);
      }
      if (v.druh === 'utok') radekPole('útočnost zbraně', poleCisla(v.utocnost, -20, 20, (n) => upravitVyzvu(v, {utocnost: n === null ? 0 : n}), '64px'));
      if (v.druh === 'past') radekPole('obtížnost (' + (v.nebezpecnost_nazev || 'nebezpečnost') + ')', poleCisla(v.nebezpecnost, -20, 60, (n) => upravitVyzvu(v, {nebezpecnost: n}), '64px'));
      det.appendChild(tab);
      k.appendChild(det);
    }
    if (v.hody) {
      const tab = el('div', 'vyzva-slozky');
      const radek = (nazev, hodnota, trida) => { const r = el('div', 'vyzva-radek' + (trida ? ' ' + trida : '')); r.append(el('span', '', nazev), el('span', 'vyzva-hodnota', hodnota)); tab.appendChild(r); };
      radek('hod ' + popisKostky + ' [' + v.hody.join(' + ') + ']', String(v.soucet_hodu));
      radek('celkem', BEZ_ZNAMENKA(v.celkem), 'vyzva-celkem');
      k.appendChild(tab);
    }
    (v.upozorneni || []).forEach((t) => k.appendChild(el('div', 'vyzva-varovani', t)));

    // Akce
    const akce = el('div', 'vyzva-akce');
    if (v.smi_hodit) {
      const hod = tlacitko(hodiSe ? 'Kostka letí…' : (v.druh === 'tik' ? 'Hodit ' + popisKostky : 'Hodit kostkou'), 'btn btn-primary vyzva-hodit', () => hoditKostkou(v), 'Hodí ' + popisKostky);
      hod.dataset.hod = 'vyzva';
      hod.disabled = hodiSe;
      akce.appendChild(hod);
      // Vlastní kostka: PJ ji má po ruce pořád, hráči se pole ukáže až na požádání.
      if (v.je_pj || vyzvaRucne.has(v.id)) {
        const rucne = el('input', 'input vyzva-cislo');
        rucne.type = 'number';
        const tikPocet = v.druh === 'tik' ? (v.pocet_kostek || 1) : 1;
        rucne.max = v.druh === 'tik' ? String(tikPocet * v.kostka) : (v.kostka === 10 ? '10' : '180');
        rucne.min = v.druh === 'tik' ? String(tikPocet) : '1';
        rucne.placeholder = v.druh === 'tik' ? tikPocet + '–' + (tikPocet * v.kostka) : (v.kostka === 10 ? '1–10' : 'součet');
        rucne.title = v.druh === 'tik' ? 'Součet z vlastních kostek (' + popisKostky + ')'
          : (v.kostka === 10 ? 'Hod 1k10 z vlastní kostky' : 'Součet z vlastní kostky; šestka se přehazuje, takže 6 + 3 zapiš jako 9');
        rucne.setAttribute('aria-label', 'Hod z vlastní kostky');
        rucne.disabled = hodiSe;
        const zapis = tlacitko('Zapsat vlastní hod', 'btn btn-ghost', () => {
          const n = parseInt(rucne.value, 10);
          if (!(n >= 1)) { logLine('Zapiš číslo z vlastní kostky.'); return; }
          odeslatHodVyzvy(v, v.kostka === 10 && v.druh !== 'tik' ? [n] : null, n);
        });
        rucne.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Enter') zapis.click(); });
        zapis.disabled = hodiSe;
        akce.append(rucne, zapis);
      } else {
        akce.appendChild(tlacitko('Hodil jsem u stolu…', 'btn btn-ghost', () => { vyzvaRucne.add(v.id); kreslitVyzvy(true); }, 'Zapíšeš číslo z vlastní kostky'));
      }
    } else if (v.cislo === null) {
      akce.appendChild(el('span', 'vyzva-poznamka', v.je_pj ? 'Doplň číslo, jinak se nedá hodit.' : 'Čeká se, až PJ doplní číslo.'));
    } else if (v.druh === 'obrana' && v.volba === null) {
      if (v.smi_volit) akce.appendChild(el('span', 'vyzva-poznamka', 'Nejdřív vyber, jestli se bráníš.'));
    } else if (v.druh === 'past' && !v.nebezpecnost_nastavena) {
      akce.appendChild(el('span', 'vyzva-poznamka', v.je_pj ? 'Nastav obtížnost hodu.' : 'Čeká se, až PJ nastaví obtížnost hodu.'));
    } else {
      akce.appendChild(el('span', 'vyzva-poznamka', 'Čeká se na hod: ' + v.label));
    }
    if (v.druh === 'tik' && jePj) {
      akce.appendChild(tlacitko('Zrušit', 'btn btn-ghost', () => volat('api/vyzva_zrus.php', {vyzva_id: v.id}), 'PJ: tenhle tik se nehodí (tvor mezitím zemřel, efekt skončil…)'));
    }
    k.appendChild(akce);
    return k;
  }

  // Krok 6: výsledek se jen ukáže a sám zmizí — nic se nezavírá, tah jde dál.
  const vysledekEl = el('div', 'boj-vysledek');
  vysledekEl.hidden = true;
  vysledekEl.setAttribute('role', 'status');
  vysledekEl.setAttribute('aria-live', 'polite');
  document.body.appendChild(vysledekEl);
  const zavritVysledek = () => { vysledekKarta = null; clearTimeout(vysledekTimer); kreslitVyzvy(true); };
  function krizekVysledku() {
    const x = tlacitko('✕', 'btn btn-ghost vyzva-zavrit', zavritVysledek, 'Zavřít (zmizí i samo)');
    x.setAttribute('aria-label', 'Zavřít výsledek');
    return x;
  }
  function kartaVysledkuPasti(p) {
    const k = el('div', 'vyzva-karta vyzva-vysledek ' + (p.uspech ? 'minuti' : 'zasah'));
    k.appendChild(krizekVysledku());
    k.appendChild(el('strong', '', (p.label || 'Postava') + ': Běh na krátké vzdálenosti — ' + (p.uspech ? 'úspěch' : 'neúspěch')));
    k.appendChild(el('div', 'vyzva-poznamka', p.uspech ? 'Dovednost platí 3 kola: běh a sprint o hex dál, pohyb se provede.'
      : 'Rychlost zůstává — sprint nejvýš o 3 hexy, akce se neutratila, naplánuj tah znovu.'));
    return k;
  }
  function kartaVysledku(p) {
    if (p.past) return kartaVysledkuPasti(p);
    const k = el('div', 'vyzva-karta vyzva-vysledek ' + (p.zasah ? 'zasah' : 'minuti'));
    k.appendChild(krizekVysledku());
    const kdo = (p.label || 'Někdo') + ' → ' + (p.cil_label || 'někdo');
    k.appendChild(el('strong', '', p.zasah ? 'Zásah! ' + kdo : 'Minul(a). ' + kdo));
    if (p.jen_shrnuti) {
      // Karta hráče: jestli to sedlo, ne za kolik. Čísla zná PJ.
      if (p.vyrazen) k.appendChild(el('div', '', (p.cil_label || 'Obránce') + ' je vyřazen(a) z boje.'));
      return k;
    }
    k.appendChild(el('div', 'vyzva-poznamka', 'Útok ' + p.utok + ' proti obraně ' + p.obrana
      + (p.zasah ? ' → zranění ' + p.zraneni + (p.nasobek > 1 ? ' (' + p.zraneni_zaklad + ' × ' + p.nasobek + ', ' + p.nasobek_duvod + ')' : (p.nasobek_duvod ? ' (' + p.nasobek_duvod + ': násobí se úrovní)' : '')) : '')
      + (p.zasah && p.tma_bonus ? ' (zranění bez postihu za tmu +' + p.tma_bonus + ')' : '')));
    if (p.zasah && p.hp_nove !== null && p.hp_nove !== undefined) {
      k.appendChild(el('div', '', (p.cil_label || 'Obránce') + ': ' + p.hp_nove + (p.max_hp ? '/' + p.max_hp : '') + ' životů'
        + (p.vyrazen ? ' — VYŘAZEN(A) z boje' : '')));
    }
    // Zranitelnost nestvůry z bestiáře: násobek už je v zranění započítaný, chybějící skupina B je varování.
    // Chodí jen PJ (server to z veřejné události vyndává, b17728 — hráčům se neříká, čím je nestvůra nezranitelná).
    if (p.zranitelnost && p.zranitelnost.text) {
      k.appendChild(el('div', 'vyzva-poznamka vyzva-zranitelnost' + (p.zranitelnost.nasobek ? '' : ' varovani'), p.zranitelnost.text));
    }
    return k;
  }

  function kreslitVyzvy(vynutit) {
    const podpis = JSON.stringify([vyzvy, vysledekKarta, hodiSe]);
    if (!vynutit && podpis === vyzvaPodpis) return;
    // Rozepsaný vlastní hod se nepřekresluje pod rukama (čísla v polích PJ se ukládají hned při změně).
    const a = document.activeElement;
    if (!vynutit && a && vyzvaPanel.contains(a) && a.matches('.vyzva-akce input') && a.value !== '') return;
    vyzvaPodpis = podpis;
    vyzvaPanel.textContent = '';
    vysledekEl.textContent = '';
    vysledekEl.hidden = !vysledekKarta;
    if (vysledekKarta) vysledekEl.appendChild(kartaVysledku(vysledekKarta));
    if (!vyzvy.length) { vyzvaPanel.style.display = 'none'; return; }
    vyzvy.forEach((v) => vyzvaPanel.appendChild(kartaVyzvy(v)));
    if (jePj) {
      vyzvaPanel.appendChild(tlacitko('PJ: zrušit hod a jít dál', 'btn btn-ghost vyzva-zrusit', () => {
        if (window.confirm('Zrušit rozehraný hod? Nikdo neutrpí zranění, akce útočníka zůstává utracená.')) volat('api/vyzva_zrus.php', {});
      }, 'Nikdo neutrpí zranění, tah jde dál'));
    }
    vyzvaPanel.style.display = '';
  }
  function ukazVysledek(p) {
    vysledekKarta = p;
    clearTimeout(vysledekTimer);
    // Hráči stačí pár vteřin (animace ukázala zbytek), PJ čte čísla déle.
    vysledekTimer = setTimeout(() => { vysledekKarta = null; kreslitVyzvy(true); }, jePj ? 9000 : 4500);
    kreslitVyzvy(true);
  }

  // --- Řádky v logu ---
  function logUdalost(u) {
    const p = u.payload || {};
    const jm = (id) => { const t = tokenPodleId(id); return t ? t.label : 'protivník'; };
    switch (u.typ) {
      case 'souboj_start':
        logLine('Souboj začíná.' + (PREKVAPENI_TEXT[p.prekvapeni] ? ' Překvapení: ' + PREKVAPENI_TEXT[p.prekvapeni] + '.' : ''));
        break;
      case 'souboj_faze':
        logLine(p.faze === 'iniciativa' ? 'Hody na iniciativu.' : 'Začínají tahy.');
        break;
      case 'umysl_nastaven':
        logLine(p.label + ': ' + (p.umysl ? 'zamýšlí ' + UMYSLY[p.umysl].toLowerCase()
          + (p.utoku_za_2_kola && ['utok', 'strelba'].includes(p.umysl) ? ' (' + p.utoku_za_2_kola + ' útoků za 2 kola)' : '') : 'zrušil(a) úmysl'));
        break;
      case 'iniciativa_hozena':
        if (p.label) {
          logLine((p.hodil || '?') + ' hodil iniciativu za ' + p.label + ': ' + p.hod + (p.modifikator >= 0 ? ' +' : ' −') + Math.abs(p.modifikator)
            + ' = ' + p.vysledek + ' → ' + p.akce_celkem + ' ' + akci(p.akce_celkem)
            + (p.rucne ? ' (hod z vlastní kostky)' : '') + (p.bonusy && p.bonusy.length ? ' [' + p.bonusy.join(', ') + ']' : '')
            + (p.zavazek ? ' — slib: ' + ({kouzleni: 'kouzlení', utok: 'útok', strelba: 'střelba'}[p.zavazek] || p.zavazek) + ' (aspoň ' + p.zavazek_akci + ' ' + akci(p.zavazek_akci) + ')' : ''));
        }
        break;
      case 'tah_zmena':
        if (p.novy_token_id) logLine('Na tahu: ' + jm(p.novy_token_id) + ' (' + p.polovina + '. půlka kola)');
        else if (p.polovina === 2) logLine('2. půlka kola.');
        break;
      case 'akce_provedena':
        logLine(p.label + ': ' + p.druh_nazev + (p.zbran_nazev ? ' (' + p.zbran_nazev + ')' : '') + (p.zdarma_efekt ? ' — bez akce, ' + p.zdarma_efekt : '') + (p.pocet > 1 ? ' ×' + p.pocet : '') + (p.cil_label ? ' → ' + p.cil_label : '')
          + (p.unava_plus ? ' (+' + p.unava_plus + ' bod únavy' + (p.unava_nova !== null && p.unava_nova !== undefined ? ', celkem ' + p.unava_nova : '') + ')' : ''));
        break;
      case 'vyzva_hod': {
        const druh = p.druh === 'utok' ? 'útok' : (p.druh === 'obrana' ? 'obranu' : (p.druh === 'tik' ? 'hod za efekt' : 'hod proti pasti'));
        const kdo = p.label || 'Někdo';
        logLine((p.hodil || '?') + ' hodil(a) ' + druh + ' za ' + kdo + ': [' + (p.hody || []).join(' + ') + ']'
          + (p.rucne ? ' (vlastní kostka)' : '') + (p.cislo !== undefined && p.cislo !== null ? ' ' + ZNAMENKO(p.cislo) : '') + ' = ' + p.celkem
          + (p.druh === 'past' ? (p.uspech ? ' — úspěch' : ' — neúspěch') : ''));
        if (p.druh === 'past') ukazVysledek({past: true, uspech: !!p.uspech, label: p.label});
        break;
      }
      case 'souboj_vysledek':
        // Hráč dostane jen shrnutí — čísla (útok, obrana, zranění, životy) z události vystřihuje
        // server (hra/api/udalosti.php); plný log vidí PJ.
        if (p.jen_shrnuti) {
          logLine((p.zasah ? 'Zásah: ' : 'Minul(a): ') + (p.label || 'někdo') + ' → ' + (p.cil_label || 'někdo')
            + (p.vyrazen ? ' — VYŘAZEN(A)' : ''));
          ukazVysledek(p);
          break;
        }
        logLine((p.zasah ? 'Zásah: ' : 'Minul(a): ') + (p.label || 'někdo') + ' → ' + (p.cil_label || 'někdo') + ' — útok ' + p.utok + ' proti obraně ' + p.obrana
          + (p.zasah ? ', zranění ' + p.zraneni + (p.nasobek > 1 ? ' (' + p.zraneni_zaklad + ' × ' + p.nasobek + ')' : (p.nasobek_duvod ? ' (násobí se úrovní: ' + p.nasobek_duvod + ')' : '')) + (p.tma_bonus ? ' (bez postihu za tmu +' + p.tma_bonus + ')' : '') + (p.hp_nove !== null && p.hp_nove !== undefined ? ' (zbývá ' + p.hp_nove + (p.max_hp ? '/' + p.max_hp : '') + ' životů)' : '') : '')
          + (p.vyrazen ? ' — VYŘAZEN(A)' : '') + (p.kouzlo_zasazeno ? (p.kouzlo_stav === 'selhalo'
            ? ' — ZASAŽEN KOUZLÍCÍ: kouzlo' + (p.kouzlo_nazev ? ' ' + p.kouzlo_nazev : '') + ' se nezdařilo, magenergie propadla' + (p.kouzlo_cena ? ' (' + p.kouzlo_cena + ')' : '')
            : (p.kouzlo_stav === 'zdarilo'
              ? ' — zasažen kouzlící po předposlední akci: kouzlo se zdaří, jen se nemohl bránit (kouzlo s koncentrací se přeruší — PJ posoudí)'
              : ' — ZASAŽEN KOUZLÍCÍ: kouzlo se nezdaří a magenergie propadne, pokud ještě nebyla předposlední akce kouzlení (PJ posoudí)')) : ''));
        ukazVysledek(p);
        break;
      case 'vyzva_zrusena':
        logLine('PJ zrušil(a) rozehraný hod.');
        break;
      case 'efekt_tik':
        logLine((p.label || 'Někdo') + ': ' + p.efekt_nazev + ' — ' + (p.znamenko < 0 ? '−' : '+') + p.castka + ' životů'
          + (p.hp_nove !== null && p.hp_nove !== undefined ? ' (zbývá ' + p.hp_nove + (p.max_hp ? '/' + p.max_hp : '') + ')' : '') + (p.vyrazen ? ' — VYŘAZEN(A)' : ''));
        break;
      case 'efekt_aplikovan': {
        const t = getTokeny().find((x) => x.typ_entity === p.typ_entity && x.entita_id === p.entita_id);
        const kdo = t ? t.label : 'Tvor';
        logLine(kdo + ': efekt ' + p.efekt_nazev + (p.obnoveno ? ' obnoven' : '') + (p.zbyva_kol ? ' na ' + p.zbyva_kol + ' ' + (p.zbyva_kol === 1 ? 'kolo' : (p.zbyva_kol < 5 ? 'kola' : 'kol')) : '')
          + (p.akce ? ' — akce ' + p.akce.pred[1] + ' → ' + p.akce.po[1] : '') + (p.shrnuti && p.shrnuti.length ? ' (' + p.shrnuti.join(', ') + ')' : '') + (p.duvod_text ? ' — ' + p.duvod_text : ''));
        break;
      }
      case 'kouzleni_stav': {
        const kdo = p.label || 'Tvor';
        const co = p.kouzlo ? ' ' + p.kouzlo : '';
        const postup = p.celkem ? ' (akce ' + p.hotovo + ' z ' + p.celkem + ')' : '';
        // Magenergie: hra ji strhává při zahájení kouzlení (propadne, když se kouzlo přeruší nebo nezdaří); u kouzla, jehož cenu nepozná, ji upraví hráč nebo PJ ručně.
        const magyText = p.magy ? ' (−' + p.magy + ' magů)' : '';
        const propadla = p.seslano ? ' — kouzlo už působí' : (p.magy ? ' — magenergie propadla (' + p.magy + ')' : (p.cena ? ' — magenergie propadla (' + p.cena + ')' : ' — magenergie propadla'));
        const ucinek = p.seslano ? ' — kouzlo seslané, účinek je vyhodnocen' : (p.chyba ? ' — účinek se nepodařilo vyhodnotit: ' + p.chyba : '');
        // Postava bez nastavené magenergie kouzlí zdarma — upozornění v logu, ať PJ doplní maximum magenergie v deníku postavy.
        const upozMagy = p.bez_magenergie ? ' ⚠ postava nemá nastavenou magenergii, magy se nestrhly (doplň ji v deníku postavy)' : '';
        const texty = {
          zahajeno: kdo + ' začíná kouzlit' + co + postup + magyText + upozMagy,
          pokracuje: kdo + ' kouzlí' + co + postup,
          ucinkuje: kdo + ': kouzlo' + co + ' začíná působit' + postup + magyText + (p.seslano ? ' — účinek se vyhodnotil sám' : ' — účinek zadej z deníku') + (p.chyba ? ' (' + p.chyba + ')' : '') + upozMagy + '; poslední akce je odpočinek, kouzelník se nemůže bránit',
          dokonceno: kdo + ' dokončil(a) kouzlení' + co + (p.celkem ? ' (' + akciText(p.celkem) + ')' : '') + ucinek + upozMagy,
          preruseno: kdo + ' přerušil(a) kouzlení' + co + propadla,
          prerusene_jinou_akci: kdo + ' přerušil(a) kouzlení' + co + ' jinou akcí' + propadla,
          selhalo: kdo + ': kouzlo' + co + ' se nezdařilo, byl(a) zasažen(a) před předposlední akcí' + propadla,
          ihned: kdo + ' sesílá ihned' + co + (p.cil_sam ? ' na sebe' : (p.cil_label ? ' na ' + p.cil_label : '')) + magyText + (p.ceka_na_past ? ' — čeká na hod proti pasti (PJ)' : '') + upozMagy,
        };
        logLine(texty[p.stav] || (kdo + ': kouzlení — ' + p.stav));
        if (['dokonceno', 'preruseno', 'prerusene_jinou_akci', 'selhalo'].includes(p.stav) && p.token_id) kouzloVolba.delete(p.token_id);
        break;
      }
      case 'efekt_konci': {
        const t = getTokeny().find((x) => x.typ_entity === p.typ_entity && x.entita_id === p.entita_id);
        logLine((t ? t.label : 'Tvor') + ': efekt ' + p.efekt_nazev + (p.duvod === 'odebran' ? ' sundán' : ({kouzleni_prerusene: ' — kouzlení přerušeno jinou akcí', kouzleni_preruseno: ' — kouzlení přerušeno', kouzleni_dokonceno: ' — kouzlení dokončeno', kouzleni_selhalo: ' — kouzlení se nezdařilo'}[p.duvod] || ' skončil')));
        break;
      }
      case 'tah_konec':
        logLine(p.label + ' ukončil(a) tah' + (p.propadlo ? ' (propadlo ' + p.propadlo + ')' : ''));
        break;
      case 'akce_uprava':
        logLine(p.label + ': akce upraveny na ' + p.akce_zbyvajici + '/' + p.akce_celkem);
        break;
      case 'souboj_ucastnici':
        logLine('Změna účastníků souboje.');
        break;
      case 'kolo_nove':
        logLine(p.souboj_konec ? 'Souboj skončil.' : 'Kolo ' + p.nove_kolo + '.' + (p.efekty_konci ? ' Skončilo ' + p.efekty_konci + ' efektů.' : ''));
        break;
      default:
    }
  }

  return {
    aplikuj(stav) {
      kolo = stav.kolo;
      iniciativa = stav.iniciativa || [];
      ucastnici = stav.ucastnici || [];
      vyzvy = stav.vyzvy || [];
      // Jiný tvor na tahu (nebo konec boje): podnabídky karty patřily tomu předchozímu.
      const aktivniTed = kolo && kolo.faze === 'kolo' ? kolo.aktivni_token_id : null;
      if (aktivniTed !== posledniAktivni) { rezim = null; kouzloVyber = null; kartaChyba = ''; posledniAktivni = aktivniTed; }
      kreslitVyzvy(false);
      // Plán akce platí jen pro toho, kdo je pořád na tahu, a jen když se nehnul.
      if (plan) {
        const t = aktivniToken();
        if (!kolo || kolo.faze !== 'kolo' || !t || t.id !== plan.tokenId || t.x !== plan.x || t.y !== plan.y
          || (t.smer || 0) !== plan.smer0 || (t.hexu || 1) !== plan.hexu0) plan = null;
      }
      // Rozehraný plán drží krok se změnami: dosah (únava, dovednost) a obsazené hexy (ostatní se hýbou)
      if (plan) {
        const t = aktivniToken();
        plan.dosah = plan.druhVynuceny ? 1 : dosahPlanu(t);
        plan.dosahBezPast = plan.druhVynuceny ? 1 : nejdalPohybu(t);
        plan.zabrane = obsazene(plan.tokenId);
        plan.zabraneSet = new Set(plan.zabrane.keys());
        if (plan.cesta.length && (plan.cesta.length > plan.dosah || !overPlan(plan.cesta, duchOrient()).ok)) {
          plan.cesta = [];
          plan.kam = null;
          plan.orient = null;
          plan.cil = null;
          logLine('Situace se změnila (dosah nebo obsazené hexy) — cesta už nejde, naplánuj ji znovu.');
        }
      }
      // Hexová mapa: plán začíná sám, jakmile je tvor na tahu (Esc ho do dalšího tahu vypne)
      if (!plan && kolo && kolo.faze === 'kolo' && kolo.ovladam_aktivniho && kolo.aktivni_token_id !== null && jeHex() && !zruseno.has(klicTahu()) && !vyzvy.length && !hrajeHrac()) {
        const t = aktivniToken();
        if (t && jeTvor(t)) zahajitPlan(null);
      }
      kreslit();
      prekreslitOverlay();
    },
    planuje: () => !!plan || (rezim === 'kouzlo' && !!kouzloVyber),
    klik,
    zrusitPlan,
    prekreslitOverlay,
    logUdalost,
    // Ovládání tvora pro jeho box v raid panelu (úmysl, hod, akce); null, když tvor nic takového nemá.
    radekExtra: (tokenId) => extra.get(tokenId) || null,
  };
}
