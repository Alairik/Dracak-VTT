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
const AKCE_DRUHY = {
  utok: 'Útok', obrana: 'Obrana', krok: 'Krok', beh: 'Běh', sprint: 'Sprint', otocka: 'Otočka', krok_utok: 'Krok a útok',
  beh_utok: 'Běh a útok', sprint_utok: 'Sprint a útok',
  otocka_utok: 'Otočka a útok', obrana_krok: 'Obrana a krok', kouzleni: 'Kouzlení', jina: 'Jiná činnost', cekani: 'Čekání',
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
  const panel = document.getElementById('panelIniciativa');
  if (!panel) return {aplikuj() {}, logUdalost() {}, planuje: () => false, klik() {}, zrusitPlan() {}, prekreslitOverlay() {}};
  const titul = document.getElementById('iniciativaTitle');
  const fazeEl = document.getElementById('iniciativaFaze');
  const seznam = document.getElementById('iniciativaList');
  const akceEl = document.getElementById('souborAkce');
  const tbSouboj = document.getElementById('tb-souboj');
  const tbKolo = document.getElementById('tb-kolo');

  // Sbalení panelu (na malé mapě zakrývá tokeny) si prohlížeč pamatuje
  const SBALENO_KLIC = 'dracak_souboj_sbaleno';
  const ctiSbaleno = () => { try { return localStorage.getItem(SBALENO_KLIC) === '1'; } catch (e) { return false; } };
  titul.title = 'Sbalit / rozbalit panel';
  titul.addEventListener('click', () => {
    const nove = !panel.classList.contains('sbaleno');
    panel.classList.toggle('sbaleno', nove);
    try { localStorage.setItem(SBALENO_KLIC, nove ? '1' : '0'); } catch (e) { /* bez paměti prohlížeče */ }
  });
  panel.classList.toggle('sbaleno', ctiSbaleno());

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

  function strana(u) {
    const d = el('span', 'ini-strana ' + (u.strana === 'druzina' ? 'druzina' : 'nepratele'));
    d.title = u.strana === 'druzina' ? 'Družina' : 'Nepřátelé';
    return d;
  }
  function jmenoBunka(s) {
    const u = s.radky[0];
    const w = el('span', 'ini-jmeno');
    w.append(strana(u), el('span', '', nazevSkupiny(s)));
    if (jePj && s.radky.every((r) => r.skryta)) {
      const t = el('span', 'ini-skryto', 'skryto');
      t.title = 'Hráči nevidí iniciativu ani úmysly tohoto tvora.';
      w.append(t);
    }
    return w;
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
    if (d && d.error) { logLine('Chyba: ' + d.error); return true; }
    return false;
  }
  function volat(url, telo) {
    return postJson(url, Object.assign({mapa_id: mapaId}, telo)).then((d) => { if (!chyba(d)) obnovitStav(); return d; });
  }

  // --- Panel ---
  function kreslit() {
    if (tbSouboj) tbSouboj.title = kolo ? 'Účastníci souboje' : 'Zahájit souboj';
    if (tbSouboj) tbSouboj.classList.toggle('active', !!kolo);
    // Ruční „Konec kola“ (odpočet efektů) by v souboji odpočítal efekty dvakrát — kolo končí samo.
    if (tbKolo) tbKolo.style.display = kolo ? 'none' : '';
    if (!kolo) { panel.style.display = 'none'; return; }
    panel.style.display = '';
    titul.textContent = 'Souboj · kolo ' + kolo.cislo_kola
      + (kolo.faze === 'kolo' ? ' · ' + kolo.polovina + '. půlka' : '')
      + (!jePj && kolo.faze === 'kolo' && kolo.ovladam_aktivniho && kolo.aktivni_token_id !== null ? ' · tvůj tah' : '');
    fazeEl.innerHTML = '';
    FAZE.forEach(([k, t], i) => fazeEl.append(el('span', 'faze-chip' + (k === kolo.faze ? ' aktivni' : ''), (i + 1) + '. ' + t)));
    if (kolo.cislo_kola === 1 && PREKVAPENI_TEXT[kolo.prekvapeni] && kolo.faze !== 'kolo') {
      fazeEl.append(el('span', 'faze-chip prekvapeni', 'Překvapení: ' + PREKVAPENI_TEXT[kolo.prekvapeni] + ' (−6)'));
    }
    seznam.innerHTML = '';
    akceEl.innerHTML = '';
    if (kolo.faze === 'umysly') kreslitUmysly();
    else if (kolo.faze === 'iniciativa') kreslitHody();
    else kreslitTahy();
  }

  function selectUmyslu(s) {
    const u = s.radky[0];
    const w = el('span', 'ini-umysl');
    const sel = el('select', 'input');
    sel.append(new Option('— zvol —', ''));
    Object.entries(UMYSLY).forEach(([k, t]) => sel.append(new Option(t, k)));
    sel.value = s.radky.every((r) => r.umysl === u.umysl) ? (u.umysl || '') : '';
    const cil = el('select', 'input');
    cil.append(new Option('— cíl —', ''));
    getTokeny().filter((t) => t.typ_entity !== 'obecny' && !idSkupiny(s).includes(t.id))
      .forEach((t) => cil.append(new Option(t.label, String(t.id))));
    cil.value = u.umysl_cil_token_id ? String(u.umysl_cil_token_id) : '';
    cil.style.display = UMYSLY_S_CILEM.includes(sel.value) ? '' : 'none';
    const uloz = () => volat('api/umysl_nastavit.php', {
      token_ids: idSkupiny(s), umysl: sel.value || null, cil_token_id: cil.value ? parseInt(cil.value, 10) : null,
    });
    sel.addEventListener('change', () => { cil.style.display = UMYSLY_S_CILEM.includes(sel.value) ? '' : 'none'; if (!UMYSLY_S_CILEM.includes(sel.value)) cil.value = ''; uloz(); });
    cil.addEventListener('change', uloz);
    w.append(sel, cil);
    return w;
  }
  function textUmyslu(u) {
    if (!u.umysl) return el('span', 'ini-ticho', '…');
    return el('span', 'ini-umysl-text', UMYSLY[u.umysl] + (u.umysl_cil_label ? ' → ' + u.umysl_cil_label : ''));
  }

  function kreslitUmysly() {
    seznam.append(el('p', 'ini-napoveda', 'Každý ohlásí, co bude v tomto kole dělat — podle toho se určí bonusy k iniciativě (kouzlení +1).'));
    const skupiny = seskupit(ucastnici, () => '');
    skupiny.forEach((s) => {
      const r = el('div', 'ini-radek');
      r.append(jmenoBunka(s));
      r.append(s.radky.every((u) => u.ovladam) ? selectUmyslu(s) : textUmyslu(s.radky[0]));
      r.addEventListener('click', (e) => { if (!e.target.closest('select')) stredNaToken(s.radky[0].token_id); });
      seznam.append(r);
    });
    if (jePj) {
      const bez = ucastnici.filter((u) => !u.umysl).length;
      akceEl.append(el('p', 'ini-napoveda', bez ? 'Úmysl zatím nemá ' + bez + ' z ' + ucastnici.length + '.' : 'Všichni mají úmysl.'));
      akceEl.append(tlacitko('Otevřít hody na iniciativu →', 'btn-secondary', () => volat('api/souboj_faze.php', {kam: 'iniciativa'})));
    } else {
      akceEl.append(el('p', 'ini-napoveda', 'Až všichni ohlásí úmysly, PJ otevře hody na iniciativu.'));
    }
  }

  function kreslitHody() {
    seznam.append(el('p', 'ini-napoveda', 'Každý hodí 1k6 na iniciativu. Hod je tlačítko — padne 3D kostka (nebo zapíšeš hod z vlastní kostky).'));
    const skupiny = seskupit(ucastnici, (u) => (u.hozeno ? 'h' + u.hod + '|' + u.modifikator : 'n'));
    skupiny.forEach((s) => {
      const u = s.radky[0];
      const r = el('div', 'ini-radek');
      r.append(jmenoBunka(s));
      const pravy = el('span', 'ini-pravy');
      if (u.hozeno) {
        const t = el('span', 'ini-vysledek', u.vysledek + ' · ' + u.akce_celkem + ' ' + akci(u.akce_celkem));
        t.title = 'Hod ' + u.hod + (u.modifikator >= 0 ? ' +' : ' ') + u.modifikator + ' = ' + u.vysledek;
        pravy.append(t);
        if (jePj) pravy.append(tlacitko('↻', 'btn-ghost ini-mini', () => otevritHod(idSkupiny(s), nazevSkupiny(s), u), 'Přehodit / opravit (jen PJ)'));
      } else if (s.radky.every((x) => x.ovladam)) {
        pravy.append(tlacitko('Hodit', 'btn-secondary ini-hodit', () => otevritHod(idSkupiny(s), nazevSkupiny(s), u)));
      } else {
        pravy.append(el('span', 'ini-ticho', 'čeká…'));
      }
      r.append(pravy);
      seznam.append(r);
    });
    if (jePj) {
      const nehozeno = ucastnici.filter((u) => !u.hozeno).length;
      akceEl.append(el('p', 'ini-napoveda', nehozeno ? 'Zatím nehodilo ' + nehozeno + ' z ' + ucastnici.length + '.' : 'Všichni hodili.'));
      akceEl.append(tlacitko('Začít kolo →', 'btn-secondary', () => {
        if (nehozeno && !confirm('Někdo ještě nehodil — nebude v tomto kole hrát. Opravdu začít?')) return;
        volat('api/souboj_faze.php', {kam: 'kolo'});
      }));
    } else {
      akceEl.append(el('p', 'ini-napoveda', 'Až budou všichni hotoví, PJ zahájí tahy.'));
    }
  }

  function kreslitTahy() {
    // Kdo je na tahu a co smí
    const info = el('div', 'tah-info');
    if (kolo.aktivni_token_id !== null || kolo.aktivni_skryty) {
      info.append(el('span', 'tah-na-tahu', 'Na tahu: '));
      info.append(el('b', '', kolo.aktivni_label || 'protivník'));
      if (kolo.kvota) info.append(el('span', 'tah-kvota', ' · v tomto tahu ještě ' + kolo.kvota + ' ' + akci(kolo.kvota)));
    } else {
      info.append(el('span', 'tah-na-tahu', 'Nikdo není na tahu.'));
    }
    seznam.append(info);

    iniciativa.forEach((r) => {
      const aktivni = r.token_id === kolo.aktivni_token_id;
      const radek = el('div', 'ini-radek' + (aktivni ? ' aktivni' : '') + (r.akce_zbyvajici === 0 ? ' hotovo' : ''));
      radek.dataset.tokenId = String(r.token_id);
      const jm = el('span', 'ini-jmeno');
      jm.append(strana(r), el('span', '', r.label));
      if (jePj && r.skryta) jm.append(el('span', 'ini-skryto', 'skryto'));
      const pr = el('span', 'ini-pravy');
      const v = el('b', 'ini-vysledek', String(r.vysledek));
      v.title = 'Hod ' + r.hod + (r.modifikator >= 0 ? ' +' : ' ') + r.modifikator + ' = ' + r.vysledek;
      pr.append(v, pips(r.akce_celkem, r.akce_zbyvajici));
      if (jePj) {
        pr.append(tlacitko('⋯', 'btn-ghost ini-mini', (e) => {
          e.stopPropagation();
          if (rozbaleno.has(r.token_id)) rozbaleno.delete(r.token_id); else rozbaleno.add(r.token_id);
          kreslit();
        }, 'Upravit počet akcí (PJ)'));
      }
      radek.append(jm, pr);
      radek.addEventListener('click', () => stredNaToken(r.token_id));
      seznam.append(radek);
      if (jePj && rozbaleno.has(r.token_id)) {
        const strip = el('div', 'ini-uprava');
        strip.append(el('span', 'ini-ticho', 'Akce:'));
        const uprav = (telo) => volat('api/akce_uprava.php', Object.assign({token_id: r.token_id}, telo));
        strip.append(
          tlacitko('−1', 'btn-ghost ini-mini', () => uprav({op: 'delta', delta: -1})),
          tlacitko('+1', 'btn-ghost ini-mini', () => uprav({op: 'delta', delta: 1})),
          tlacitko('×2 Rychlost', 'btn-ghost ini-mini', () => uprav({op: 'rychlost'}), 'Kouzlo Rychlost: zdvojí akce, které tvor právě má k dispozici'),
          tlacitko('½ Protoplazma', 'btn-ghost ini-mini', () => uprav({op: 'protoplazma'}), 'Kouzlo Protoplazma: sníží akce na polovinu (dolů)'),
        );
        seznam.append(strip);
      }
    });

    // Kdo se přidal uprostřed kola: akce dostane z iniciativy, tedy až v příštím kole
    const bez = ucastnici.filter((u) => !u.hozeno);
    if (bez.length) {
      seznam.append(el('p', 'ini-napoveda', 'Nastoupí v příštím kole (iniciativa se hází na jeho začátku):'));
      seskupit(bez, () => 'n').forEach((s) => {
        const r = el('div', 'ini-radek hotovo');
        r.append(jmenoBunka(s));
        seznam.append(r);
      });
    }

    // Ovládání tahu: jen ten, kdo smí za tvora na tahu jednat
    if (kolo.ovladam_aktivniho && kolo.aktivni_token_id !== null) {
      if (plan) kreslitPlan(); else kreslitListuAkci();
    }
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

  // Menu „Další akce…“: akce bez mapové části se jen odečtou (čekání, obrana, jiná činnost, kouzlení).
  let dalsiOtevrene = false;
  function kreslitDalsiAkce() {
    const d = document.createElement('details');
    d.className = 'dalsi-akce';
    d.open = dalsiOtevrene;
    d.addEventListener('toggle', () => { dalsiOtevrene = d.open; });
    d.append(el('summary', '', 'Další akce…'));
    const box = el('div', 'akce-lista');
    [['cekani', 'Čekání'], ['obrana', 'Obrana'], ['jina', 'Jiná činnost'], ['kouzleni', 'Kouzlení']].forEach(([k, text]) => {
      box.append(tlacitko(text, 'btn-ghost akce-btn', () => volat('api/tah_akce.php', {token_id: kolo.aktivni_token_id, druh: k}), 'Odečte jednu akci'));
    });
    if (jeHex()) {
      box.append(tlacitko('Obrana a krok', 'btn-ghost akce-btn mapova', () => zahajitPlan('obrana_krok'), 'Obrana a krok na sousední hex (1 akce) — vybereš na mapě'));
    }
    d.append(box);
    akceEl.append(d);
    akceEl.append(tlacitko('Ukončit tah', 'btn-ghost', () => volat('api/tah_konec.php', {}), 'Zbylé akce tohoto tahu propadnou jako čekání.'));
  }

  // Čtvercová mapa (bez hexů): bez plánování, každá akce se jen odečte a zapíše do logu.
  const AKCE_PROSTE = ['krok', 'beh', 'sprint', 'otocka', 'utok', 'krok_utok', 'otocka_utok', 'beh_utok', 'sprint_utok', 'obrana', 'obrana_krok', 'cekani', 'jina', 'kouzleni'];
  function kreslitListuAkci() {
    if (!jeHex()) {
      const box = el('div', 'akce-lista');
      AKCE_PROSTE.forEach((k) => {
        box.append(tlacitko(AKCE_DRUHY[k], 'btn-ghost akce-btn', () => volat('api/tah_akce.php', {token_id: kolo.aktivni_token_id, druh: k}),
          'Odečte ' + popisAkci(1)));
      });
      akceEl.append(box);
      akceEl.append(tlacitko('Ukončit tah', 'btn-ghost', () => volat('api/tah_konec.php', {}), 'Zbylé akce tohoto tahu propadnou jako čekání.'));
      akceEl.append(el('p', 'ini-napoveda', 'Na této mapě není hexový grid, akce se jen odečtou a zapíšou do logu.'));
      return;
    }
    // Hexová mapa: plánování vypnuté (Esc) — jedním tlačítkem se zapne znovu.
    akceEl.append(tlacitko('Plánovat tah', 'btn-secondary', () => { zruseno.delete(klicTahu()); zahajitPlan(null); },
      'Klikáním na mapu naplánuješ pohyb a útok'));
    kreslitDalsiAkce();
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

  function kreslitPlan() {
    const druh = druhPlanu();
    const box = el('div', 'tah-plan');
    const hlavicka = el('b', '', druh ? AKCE_DRUHY[druh] : 'Tah');
    box.append(hlavicka);
    if (druh) {
      box.append(el('span', 'tah-cena', ' · ' + popisAkci(1) + (kolo && kolo.kvota ? ' (v tomhle tahu zbývá ' + kolo.kvota + ')' : '')));
    }
    popisPlanu().forEach((t) => box.append(el('p', 'ini-napoveda', t)));
    const drzet = tlacitko(plan.drzetCelo ? 'Držet čelo' : 'Otáčet se po cestě', 'btn-ghost akce-btn' + (plan.drzetCelo ? ' aktivni' : ''), prepniDrzetCelo,
      plan.drzetCelo ? 'Tvor se při chůzi nenatáčí (couvání, úkroky). Klikni pro automatické otáčení ve směru kroku.'
        : 'Tvor se při chůzi sám natáčí ve směru kroku a otočení se počítá do rozpočtu. Klikni, když se nemá natáčet („držet čelo“).');
    box.append(drzet);
    const r = el('div', 'tah-ovladani');
    const zpet = tlacitko('↩ Bod', 'btn-ghost akce-btn', zpetOBod, 'Zpět o jeden bod cesty (Backspace)');
    zpet.disabled = !plan.cesta.length;
    r.append(
      zpet,
      tlacitko('↶', 'btn-ghost akce-btn', () => otocOPlan(-1), 'Otočit čelo na konci o 60° doleva'),
      tlacitko('↷', 'btn-ghost akce-btn', () => otocOPlan(1), 'Otočit čelo na konci o 60° doprava'),
    );
    const potvrdit = tlacitko('Potvrdit', 'btn-secondary', odeslatPlan, 'Provede plán (Enter)');
    potvrdit.disabled = !planJeHotov();
    r.append(potvrdit, tlacitko('Zrušit', 'btn-ghost', zrusitPlan, 'Vypne plánování do dalšího tahu (Esc)'));
    box.append(r);
    if (plan.cil !== null) {
      const u = el('div', 'tah-ovladani');
      u.append(tlacitko('Bez útoku', 'btn-ghost akce-btn', () => { plan.cil = null; kreslit(); prekreslitOverlay(); }, 'Jen pohyb, útok vynechat'));
      if (plan.cesta.length) u.append(tlacitko('Bez pohybu', 'btn-ghost akce-btn', jenUtok, 'Zaútočit z místa (jen otočit, když je potřeba)'));
      box.append(u);
    }
    akceEl.append(box);
    kreslitDalsiAkce();
  }

  function odeslatPlan() {
    if (!plan || !planJeHotov()) return;
    const druh = druhPlanu();
    const telo = {token_id: plan.tokenId, druh};
    if (plan.cesta.length) telo.cesta = plan.cesta.map((p) => ({col: p.col, row: p.row, smer: hexy.smerZOrientace(plan.tvar, p.o)}));
    if (plan.orient !== null || druh === 'otocka' || druh === 'otocka_utok') telo.smer = hexy.smerZOrientace(plan.tvar, duchOrient());
    if (plan.cil) telo.cil_token_id = plan.cil;
    postJson('api/tah_akce.php', Object.assign({mapa_id: mapaId}, telo)).then((d) => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
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

  function stredNaToken(id) {
    const t = tokenPodleId(id);
    if (t) vystredit(t.x, t.y);
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
      + ' a kouzlení se přičtou samy.' + (u && u.hozeno ? ' (Přehození přepíše dřívější hod.)' : '');
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
      const d = await postJson('api/souboj_hod.php', {mapa_id: mapaId, token_ids: hodCil.tokenIds, hod, rucne, bonus_ids: bonusIds, jiny_bonus: jiny});
      if (d.error) {
        ukazChybuHodu(d.error);
        nastavTlacitka(true);
      } else {
        const v = d.vysledky && d.vysledky[0];
        if (v) hodVysledek.textContent = 'Padlo ' + hod + ' ' + (v.modifikator >= 0 ? '+' : '−') + Math.abs(v.modifikator) + ' = ' + v.vysledek + ' → ' + v.akce_celkem + ' ' + akci(v.akce_celkem);
        (d.upozorneni || []).forEach((t) => logLine(t));
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
    mUlozit.textContent = uprava ? 'Uložit' : 'Zahájit souboj';
    mPrekvapeni.value = uprava ? kolo.prekvapeni : 'zadne';
    mPrekvapeni.disabled = uprava && (kolo.faze === 'kolo' || kolo.cislo_kola !== 1);
    mChyba.style.display = 'none';
    mSeznam.innerHTML = '';
    const tokeny = getTokeny().filter((t) => t.typ_entity === 'postava' || t.typ_entity === 'nestvura_instance');
    const vybrane = getVybrane().filter((id) => tokenPodleId(id) && ['postava', 'nestvura_instance'].includes(tokenPodleId(id).typ_entity));
    const soucasni = new Map(ucastnici.map((u) => [u.token_id, u]));
    tokeny.forEach((t) => {
      const jeUcastnik = soucasni.has(t.id);
      const predvolena = uprava ? jeUcastnik : (vybrane.length >= 2 ? vybrane.includes(t.id) : true);
      const radek = el('label', 'souboj-radek');
      const cb = el('input');
      cb.type = 'checkbox';
      cb.checked = predvolena;
      cb.dataset.tokenId = String(t.id);
      const jm = el('span', 'souboj-jmeno', t.label + (t.viditelny ? '' : ' (skrytý)'));
      const st = el('select', 'input');
      st.append(new Option('Družina', 'druzina'), new Option('Nepřátelé', 'nepratele'));
      st.value = jeUcastnik ? soucasni.get(t.id).strana : (t.typ_entity === 'postava' ? 'druzina' : 'nepratele');
      st.dataset.tokenId = String(t.id);
      radek.append(cb, jm, st);
      mSeznam.append(radek);
    });
    if (!tokeny.length) mSeznam.append(el('p', 'note', 'Na mapě nejsou žádné postavy ani nestvůry.'));
    mSouboj.style.display = '';
  }
  function zavritZahajeni() { if (mSouboj) mSouboj.style.display = 'none'; }
  if (mSouboj) {
    mUlozit.addEventListener('click', async () => {
      const radky = Array.from(mSeznam.querySelectorAll('.souboj-radek')).map((r) => ({
        tokenId: parseInt(r.querySelector('input').dataset.tokenId, 10),
        zaskrtnuto: r.querySelector('input').checked,
        strana: r.querySelector('select').value,
      }));
      const vybrani = radky.filter((r) => r.zaskrtnuto);
      if (!vybrani.length) { mChyba.textContent = 'Vyber aspoň jednoho účastníka.'; mChyba.style.display = ''; return; }
      let d;
      if (!kolo) {
        d = await postJson('api/souboj_start.php', {
          mapa_id: mapaId, prekvapeni: mPrekvapeni.value,
          ucastnici: vybrani.map((r) => ({token_id: r.tokenId, strana: r.strana})),
        });
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
    if (e.key === 'Escape') { zavritHod(); zavritZahajeni(); zrusitPlan(); }
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

  function kartaVyzvy(v) {
    const k = el('div', 'vyzva-karta vyzva-' + v.druh);
    const hlava = el('div', 'vyzva-hlava');
    const proti = v.druh === 'utok' ? ' → ' + (v.cil_label || '?') : (v.druh === 'obrana' ? ' (proti ' + (v.utocnik_label || '?') + ')' : '');
    hlava.appendChild(el('strong', '', nazevDruhu(v) + ' — ' + v.label + proti));
    k.appendChild(hlava);
    if (v.druh === 'tik' && v.popis) k.appendChild(el('div', 'vyzva-poznamka', v.popis + ' — hoď ' + (v.pocet_kostek || 1) + 'k' + v.kostka + ', na začátku kola'));
    if (v.druh === 'past' && v.popis) k.appendChild(el('div', 'vyzva-poznamka', v.popis + ' (' + (v.vlastnost || '') + ', ' + (v.nebezpecnost_nazev || '') + ')'));

    // Už hozená výzva (útok čeká na obranu) se zmenší na výsledek hodu.
    if (v.stav !== 'ceka') {
      k.classList.add('hotovo');
      k.appendChild(el('div', 'vyzva-radek vyzva-celkem', '') ).append(
        el('span', '', (v.kostka === 10 ? '1k10 ' : '1k6+ ') + '[' + (v.hody || []).join(' + ') + ']' + (v.cislo === null ? '' : ' ' + ZNAMENKO(v.cislo))),
        el('span', 'vyzva-hodnota', '= ' + BEZ_ZNAMENKA(v.celkem)));
      k.appendChild(el('div', 'vyzva-poznamka', v.druh === 'utok' ? 'Čeká se na obranu ' + (v.cil_label || '') + '.' : 'Hozeno.'));
      return k;
    }

    if (v.druh === 'obrana') {
      k.appendChild(el('div', 'vyzva-poznamka', 'Útok ' + (v.utocnik_label || '?') + ': ' + (v.utok_celkem !== null && v.utok_celkem !== undefined ? v.utok_celkem : '?')
        + (v.strana === 'bok' ? ' · útok z boku nebo zezadu' : '')));
      if (v.duvod_nebrani) k.appendChild(el('div', 'vyzva-poznamka', 'Nemůže se bránit zbraní: ' + v.duvod_nebrani + (v.nehybny ? ' (platí jako nehybný)' : '')));
      if (v.nasobek_duvod) {
        const n = el('div', 'vyzva-radek');
        n.appendChild(el('span', '', 'zranění se násobí úrovní (' + v.nasobek_duvod + ')'));
        if (v.je_pj && v.stav === 'ceka') n.appendChild(poleCisla(v.nasobek, 1, 99, (x) => upravitVyzvu(v, {nasobek: x}), '64px'));
        else n.appendChild(el('span', 'vyzva-hodnota', v.nasobek ? '×' + v.nasobek : ''));
        k.appendChild(n);
      }
      if (v.stav === 'ceka') {
        const volba = el('div', 'vyzva-volba');
        const b1 = tlacitko('Bránit se (stojí akci)', 'btn ' + (v.volba === 'branit' ? 'btn-primary' : 'btn-ghost'), () => upravitVyzvu(v, {volba: 'branit'}),
          v.muze_branit || v.je_pj ? 'Brání se zbraní — stojí jednu akci z tvého zbytku' : 'Tvor už nemá žádnou akci');
        const b2 = tlacitko('Nebránit se (−3)', 'btn ' + (v.volba === 'nebranit' ? 'btn-primary' : 'btn-ghost'), () => upravitVyzvu(v, {volba: 'nebranit'}),
          'Jen brněním, bez zbraně — postih −3 (z boku nebo zezadu dohromady −6)');
        b1.disabled = !v.smi_volit || (!v.muze_branit && !v.je_pj) || !!v.duvod_nebrani;
        if (v.duvod_nebrani) b1.title = 'Nemůže se bránit zbraní — ' + v.duvod_nebrani;
        b2.disabled = !v.smi_volit;
        volba.append(b1, b2);
        k.appendChild(volba);
        if (!v.smi_volit && v.volba === null) k.appendChild(el('div', 'vyzva-poznamka', 'Čeká se, až se ' + v.label + ' rozhodne, jak se bude bránit.'));
      }
    }

    // Složky čísla (u hodu nestvůry je hráč nevidí — jen hody a součet)
    const tab = el('div', 'vyzva-slozky');
    const skrytoSlozky = !v.je_pj && v.zaklad === null && v.cislo === null;
    const radek = (nazev, hodnota, trida) => {
      const r = el('div', 'vyzva-radek' + (trida ? ' ' + trida : ''));
      r.append(el('span', '', nazev), el('span', 'vyzva-hodnota', hodnota));
      return r;
    };
    if (skrytoSlozky) {
      // nic — jen hody níž
    } else if (v.druh === 'past') {
      tab.appendChild(radek(v.zaklad_nazev || 'bonus vlastnosti', v.zaklad === null ? '—' : ZNAMENKO(v.zaklad)));
    } else {
      const r = el('div', 'vyzva-radek');
      r.appendChild(el('span', '', v.zaklad_nazev || (v.druh === 'utok' ? 'ÚČ' : 'OČ')));
      if (v.je_pj && v.stav === 'ceka') {
        r.appendChild(poleCisla(v.zaklad, 0, 60, (n) => upravitVyzvu(v, {zaklad: n}), '64px'));
      } else {
        r.appendChild(el('span', 'vyzva-hodnota', v.zaklad === null ? '—' : String(v.zaklad)));
      }
      tab.appendChild(r);
    }
    (v.opravy || []).forEach((o) => tab.appendChild(radek(o.nazev, ZNAMENKO(o.hodnota))));
    if (v.je_pj && v.stav === 'ceka') {
      const r = el('div', 'vyzva-radek');
      r.appendChild(el('span', '', v.druh === 'past' ? 'oprava (PJ)' : 'oprava (PJ)'));
      r.appendChild(poleCisla(v.modifikator, -30, 30, (n) => upravitVyzvu(v, {modifikator: n === null ? 0 : n}), '64px'));
      tab.appendChild(r);
      if (v.druh === 'utok') {
        const u = el('div', 'vyzva-radek');
        u.appendChild(el('span', '', 'útočnost zbraně'));
        u.appendChild(poleCisla(v.utocnost, -20, 20, (n) => upravitVyzvu(v, {utocnost: n === null ? 0 : n}), '64px'));
        tab.appendChild(u);
      }
      if (v.druh === 'past') {
        const u = el('div', 'vyzva-radek');
        u.appendChild(el('span', '', 'obtížnost (' + (v.nebezpecnost_nazev || 'nebezpečnost') + ')'));
        u.appendChild(poleCisla(v.nebezpecnost, -20, 60, (n) => upravitVyzvu(v, {nebezpecnost: n}), '64px'));
        tab.appendChild(u);
      }
    } else if (v.modifikator) {
      tab.appendChild(radek('oprava (PJ)', ZNAMENKO(v.modifikator)));
    }
    if (v.druh === 'utok' && v.varianty && v.varianty.length > 1 && v.stav === 'ceka' && (v.ovladam || v.je_pj)) {
      const r = el('div', 'vyzva-radek');
      r.appendChild(el('span', '', 'zbraň / útok'));
      const sel = el('select', 'input');
      v.varianty.forEach((z) => {
        const o = el('option', '', z.nazev + ' (ÚČ ' + z.uc + ')');
        o.value = String(z.id);
        if (z.id === v.zbran_id) o.selected = true;
        sel.appendChild(o);
      });
      sel.addEventListener('change', () => upravitVyzvu(v, {zbran_id: parseInt(sel.value, 10)}));
      r.appendChild(sel);
      tab.appendChild(r);
    }
    if (v.cislo !== null) {
      tab.appendChild(radek('číslo bez hodu', BEZ_ZNAMENKA(v.cislo), 'vyzva-soucet'));
    }
    if (v.hody) {
      tab.appendChild(radek('hod ' + (v.druh === 'tik' ? (v.pocet_kostek || 1) + 'k' + v.kostka : (v.kostka === 10 ? '1k10' : '1k6+')) + ' [' + v.hody.join(' + ') + ']', String(v.soucet_hodu)));
      tab.appendChild(radek('celkem', BEZ_ZNAMENKA(v.celkem), 'vyzva-celkem'));
    }
    k.appendChild(tab);
    (v.upozorneni || []).forEach((t) => k.appendChild(el('div', 'vyzva-varovani', t)));

    // Akce
    const akce = el('div', 'vyzva-akce');
    if (v.stav === 'ceka') {
      if (v.smi_hodit) {
        const popisKostky = v.druh === 'tik' ? (v.pocet_kostek || 1) + 'k' + v.kostka : (v.kostka === 10 ? '1k10' : '1k6+');
        const hod = tlacitko(hodiSe ? 'Kostka letí…' : 'Hodit ' + popisKostky, 'btn btn-primary', () => hoditKostkou(v));
        hod.disabled = hodiSe;
        akce.appendChild(hod);
        const rucne = el('input', 'input vyzva-cislo');
        rucne.type = 'number';
        rucne.min = '1';
        const tikPocet = v.druh === 'tik' ? (v.pocet_kostek || 1) : 1;
        rucne.max = v.druh === 'tik' ? String(tikPocet * v.kostka) : (v.kostka === 10 ? '10' : '180');
        rucne.min = v.druh === 'tik' ? String(tikPocet) : '1';
        rucne.placeholder = v.druh === 'tik' ? tikPocet + '–' + (tikPocet * v.kostka) : (v.kostka === 10 ? '1–10' : 'součet');
        rucne.title = v.druh === 'tik' ? 'Součet z vlastních kostek (' + popisKostky + ')'
          : (v.kostka === 10 ? 'Hod 1k10 z vlastní kostky' : 'Součet z vlastní kostky; šestka se přehazuje, takže 6 + 3 zapiš jako 9');
        rucne.disabled = hodiSe;
        rucne.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Enter') zapis.click(); });
        const zapis = tlacitko('Zapsat vlastní hod', 'btn btn-ghost', () => {
          const n = parseInt(rucne.value, 10);
          if (!(n >= 1)) { logLine('Zapiš číslo z vlastní kostky.'); return; }
          odeslatHodVyzvy(v, v.kostka === 10 && v.druh !== 'tik' ? [n] : null, n);
        });
        zapis.disabled = hodiSe;
        akce.append(rucne, zapis);
      } else if (v.cislo === null) {
        akce.appendChild(el('span', 'vyzva-poznamka', v.je_pj ? 'Doplň číslo, jinak se nedá hodit.' : 'Čeká se, až PJ doplní číslo.'));
      } else if (v.druh === 'obrana' && v.volba === null) {
        // text o rozhodování je výš
      } else if (v.druh === 'past' && !v.nebezpecnost_nastavena) {
        akce.appendChild(el('span', 'vyzva-poznamka', v.je_pj ? 'Nastav obtížnost hodu.' : 'Čeká se, až PJ nastaví obtížnost hodu.'));
      } else {
        akce.appendChild(el('span', 'vyzva-poznamka', 'Čeká se na hod: ' + v.label));
      }
    } else {
      akce.appendChild(el('span', 'vyzva-poznamka', v.druh === 'utok' ? 'Útok hozen — čeká se na obranu.' : 'Hozeno.'));
    }
    if (v.druh === 'tik' && jePj && v.stav === 'ceka') {
      akce.appendChild(tlacitko('Zrušit', 'btn btn-ghost', () => volat('api/vyzva_zrus.php', {vyzva_id: v.id}), 'PJ: tenhle tik se nehodí (tvor mezitím zemřel, efekt skončil…)'));
    }
    k.appendChild(akce);
    return k;
  }

  function kartaVysledkuPasti(p) {
    const k = el('div', 'vyzva-karta vyzva-vysledek ' + (p.uspech ? 'minuti' : 'zasah'));
    k.appendChild(el('strong', '', (p.label || 'Postava') + ': Běh na krátké vzdálenosti — ' + (p.uspech ? 'úspěch' : 'neúspěch')));
    k.appendChild(el('div', 'vyzva-poznamka', p.uspech ? 'Dovednost platí 3 kola: běh a sprint o hex dál, pohyb se provede.'
      : 'Rychlost zůstává — sprint nejvýš o 3 hexy, akce se neutratila, naplánuj tah znovu.'));
    k.appendChild(tlacitko('Zavřít', 'btn btn-ghost', () => { vysledekKarta = null; kreslitVyzvy(true); }));
    return k;
  }
  function kartaVysledku(p) {
    if (p.past) return kartaVysledkuPasti(p);
    const k = el('div', 'vyzva-karta vyzva-vysledek ' + (p.zasah ? 'zasah' : 'minuti'));
    const kdo = (p.label || 'Někdo') + ' → ' + (p.cil_label || 'někdo');
    k.appendChild(el('strong', '', p.zasah ? 'Zásah! ' + kdo : 'Minul(a). ' + kdo));
    k.appendChild(el('div', 'vyzva-poznamka', 'Útok ' + p.utok + ' proti obraně ' + p.obrana
      + (p.zasah ? ' → zranění ' + p.zraneni + (p.nasobek > 1 ? ' (' + p.zraneni_zaklad + ' × ' + p.nasobek + ', ' + p.nasobek_duvod + ')' : (p.nasobek_duvod ? ' (' + p.nasobek_duvod + ': násobí se úrovní)' : '')) : '')));
    if (p.zasah && p.hp_nove !== null && p.hp_nove !== undefined) {
      k.appendChild(el('div', '', (p.cil_label || 'Obránce') + ': ' + p.hp_nove + (p.max_hp ? '/' + p.max_hp : '') + ' životů'
        + (p.vyrazen ? ' — VYŘAZEN(A) z boje' : '')));
    }
    const zavrit = tlacitko('Zavřít', 'btn btn-ghost', () => { vysledekKarta = null; kreslitVyzvy(true); });
    k.appendChild(zavrit);
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
    if (!vyzvy.length && !vysledekKarta) { vyzvaPanel.style.display = 'none'; return; }
    vyzvy.forEach((v) => vyzvaPanel.appendChild(kartaVyzvy(v)));
    if (vysledekKarta && !vyzvy.length) vyzvaPanel.appendChild(kartaVysledku(vysledekKarta));
    if (jePj && vyzvy.length) {
      vyzvaPanel.appendChild(tlacitko('PJ: zrušit hod a jít dál', 'btn btn-ghost vyzva-zrusit', () => {
        if (window.confirm('Zrušit rozehraný hod? Nikdo neutrpí zranění, akce útočníka zůstává utracená.')) volat('api/vyzva_zrus.php', {});
      }, 'Nikdo neutrpí zranění, tah jde dál'));
    }
    vyzvaPanel.style.display = '';
  }
  function ukazVysledek(p) {
    vysledekKarta = p;
    clearTimeout(vysledekTimer);
    vysledekTimer = setTimeout(() => { vysledekKarta = null; kreslitVyzvy(true); }, 12000);
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
        logLine(p.label + ': ' + (p.umysl ? 'zamýšlí ' + UMYSLY[p.umysl].toLowerCase() : 'zrušil(a) úmysl'));
        break;
      case 'iniciativa_hozena':
        if (p.label) {
          logLine((p.hodil || '?') + ' hodil iniciativu za ' + p.label + ': ' + p.hod + (p.modifikator >= 0 ? ' +' : ' −') + Math.abs(p.modifikator)
            + ' = ' + p.vysledek + ' → ' + p.akce_celkem + ' ' + akci(p.akce_celkem)
            + (p.rucne ? ' (hod z vlastní kostky)' : '') + (p.bonusy && p.bonusy.length ? ' [' + p.bonusy.join(', ') + ']' : ''));
        }
        break;
      case 'tah_zmena':
        if (p.novy_token_id) logLine('Na tahu: ' + jm(p.novy_token_id) + ' (' + p.polovina + '. půlka kola)');
        else if (p.polovina === 2) logLine('2. půlka kola.');
        break;
      case 'akce_provedena':
        logLine(p.label + ': ' + p.druh_nazev + (p.pocet > 1 ? ' ×' + p.pocet : '') + (p.cil_label ? ' → ' + p.cil_label : '')
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
        logLine((p.zasah ? 'Zásah: ' : 'Minul(a): ') + (p.label || 'někdo') + ' → ' + (p.cil_label || 'někdo') + ' — útok ' + p.utok + ' proti obraně ' + p.obrana
          + (p.zasah ? ', zranění ' + p.zraneni + (p.nasobek > 1 ? ' (' + p.zraneni_zaklad + ' × ' + p.nasobek + ')' : (p.nasobek_duvod ? ' (násobí se úrovní: ' + p.nasobek_duvod + ')' : '')) + (p.hp_nove !== null && p.hp_nove !== undefined ? ' (zbývá ' + p.hp_nove + (p.max_hp ? '/' + p.max_hp : '') + ' životů)' : '') : '')
          + (p.vyrazen ? ' — VYŘAZEN(A)' : ''));
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
          + (p.akce ? ' — akce ' + p.akce.pred[1] + ' → ' + p.akce.po[1] : '') + (p.shrnuti && p.shrnuti.length ? ' (' + p.shrnuti.join(', ') + ')' : ''));
        break;
      }
      case 'efekt_konci': {
        const t = getTokeny().find((x) => x.typ_entity === p.typ_entity && x.entita_id === p.entita_id);
        logLine((t ? t.label : 'Tvor') + ': efekt ' + p.efekt_nazev + (p.duvod === 'odebran' ? ' sundán' : ' skončil'));
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
      if (!plan && kolo && kolo.faze === 'kolo' && kolo.ovladam_aktivniho && kolo.aktivni_token_id !== null && jeHex() && !zruseno.has(klicTahu()) && !vyzvy.length) {
        const t = aktivniToken();
        if (t && jeTvor(t)) zahajitPlan(null);
      }
      kreslit();
      prekreslitOverlay();
    },
    planuje: () => !!plan,
    klik,
    zrusitPlan,
    prekreslitOverlay,
    logUdalost,
  };
}
