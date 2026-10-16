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
  otocka_utok: 'Otočka a útok', obrana_krok: 'Obrana a krok', kouzleni: 'Kouzlení', jina: 'Jiná činnost', cekani: 'Čekání',
};
// Pořadí tlačítek v liště akcí (h1623).
const AKCE_LISTA = ['krok', 'beh', 'sprint', 'otocka', 'utok', 'krok_utok', 'otocka_utok', 'obrana', 'obrana_krok', 'cekani', 'jina', 'kouzleni'];
// Které akce mají část na mapě (krok / otočka / cíl útoku), viz includes/vtt_souboj_pohyb.php.
const S_KROKEM = ['krok', 'krok_utok', 'obrana_krok', 'beh', 'sprint'];
const S_OTOCKOU = ['otocka', 'otocka_utok'];
const S_UTOKEM = ['utok', 'krok_utok', 'otocka_utok'];
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

  // --- Lišta akcí (h1623) a plánování akcí na mapě ---
  // Na hexové mapě mají Krok, Otočka a Útok (i jejich spojení) mapovou část:
  // klikáš na hexy (zeleně kam smíš, oranžově 3 hexy před tvorem) a až poté se
  // akce potvrdí. Ostatní akce (Čekání, Obrana, Jiná činnost, Kouzlení) se
  // odečtou hned. Na čtvercové mapě se odečte vše hned.
  let plan = null;
  const jeHex = () => !!(jeHexMapa && jeHexMapa());
  const aktivniToken = () => (kolo && kolo.aktivni_token_id !== null ? tokenPodleId(kolo.aktivni_token_id) : null);

  function popisHexu(n) { return n === 1 ? '1 hex' : n + (n <= 4 ? ' hexy' : ' hexů'); }
  const BEH_ZAMEK = (k) => {
    const r = POHYB[REZIM[k]];
    return r && r.jen_pri_umyslu ? r : null;
  };

  // Kolik hexů smí tvor v této pohybové akci (krok 1, běh 2, sprint 3; dovednost Běh na krátké
  // vzdálenosti přidá hex k běhu a sprintu — PJ ji nasazuje jako efekt).
  function dosahPohybu(druh, token) {
    const r = POHYB[REZIM[druh]] || POHYB.krok;
    const maDovednost = token && (token.efekty || []).some((e) => String(e).startsWith(EFEKT_BEH_NA_KRATKO));
    return r.hexu + (r.bonus_dovednost > 0 && maDovednost ? r.bonus_dovednost : 0);
  }

  function kreslitListuAkci() {
    const box = el('div', 'akce-lista');
    const aktivni = aktivniToken();
    const u = ucastnici.find((x) => x.token_id === kolo.aktivni_token_id);
    AKCE_LISTA.forEach((k) => {
      if (REZIM[k] && !POHYB[REZIM[k]]) return;
      const mapova = jeHex() && (S_KROKEM.includes(k) || S_OTOCKOU.includes(k) || S_UTOKEM.includes(k));
      const zamek = BEH_ZAMEK(k);
      const zamceno = !!zamek && (!u || u.umysl !== zamek.jen_pri_umyslu);
      const pohybova = k === 'beh' || k === 'sprint';
      const text = pohybova && jeHex() ? AKCE_DRUHY[k] + ' · ' + popisHexu(dosahPohybu(k, aktivni)) : AKCE_DRUHY[k];
      const b = tlacitko(text, 'btn-ghost akce-btn' + (mapova ? ' mapova' : ''), () => {
        if (mapova) zahajitPlan(k);
        else volat('api/tah_akce.php', {token_id: kolo.aktivni_token_id, druh: k});
      }, zamceno ? AKCE_DRUHY[k] + ' je jen při úmyslu „' + UMYSLY[zamek.jen_pri_umyslu] + '“ (ohlašuje se před hodem na iniciativu)'
        : (mapova ? 'Vybereš na mapě' : 'Odečte jednu akci'));
      if (zamceno) b.disabled = true;
      box.append(b);
    });
    akceEl.append(box);
    akceEl.append(tlacitko('Ukončit tah', 'btn-ghost', () => volat('api/tah_konec.php', {}), 'Zbylé akce tohoto tahu propadnou jako čekání.'));
    akceEl.append(el('p', 'ini-napoveda', jeHex()
      ? 'Akce s tečkou (Krok, Běh, Sprint, Otočka, Útok…) vybíráš klikem na mapě. Obrana a Kouzlení se zatím jen odečtou; hody a zranění přijdou v dalších krocích.'
      : 'Na této mapě není hexový grid, akce se jen odečtou a zapíšou do logu.'));
  }

  function zahajitPlan(druh) {
    const t = aktivniToken();
    if (!t) return;
    const zamek = BEH_ZAMEK(druh);
    const u = ucastnici.find((x) => x.token_id === t.id);
    if (zamek && (!u || u.umysl !== zamek.jen_pri_umyslu)) {
      logLine(AKCE_DRUHY[druh] + ' je jen při úmyslu „' + UMYSLY[zamek.jen_pri_umyslu] + '“.');
      return;
    }
    plan = {
      druh, tokenId: t.id, odkud: pivotTokenu(t), tvar: tvarDe(t), orientPred: orientTokenu(t),
      umysl: u ? u.umysl : null, kam: null, orient: null, cil: null, x: t.x, y: t.y, smer0: t.smer || 0, hexu0: t.hexu || 1,
    };
    plan.krok = S_KROKEM.includes(druh);
    plan.otocka = S_OTOCKOU.includes(druh);
    plan.utok = S_UTOKEM.includes(druh);
    plan.otoceniMax = plan.krok ? (POHYB[REZIM[druh]] || POHYB.krok).otoceni_max : 360;
    plan.dosah = plan.krok ? dosahPohybu(druh, t) : 0;
    plan.zabrane = obsazene(plan.tokenId);
    plan.mozne = plan.krok ? mozneKroky() : new Map();
    kreslit();
    prekreslitOverlay();
  }
  function zrusitPlan() {
    if (!plan) return;
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

  // Kam smí otočný hex: celé tělo (v původní orientaci) postupuje hex po hexu přes volná místa,
  // nejvýš o dosah akce (krok 1, běh 2, sprint 3); zeď hráče zastaví. Stejně to počítá server.
  function mozneKroky() {
    const m = new Map();
    const videno = new Set([klicBunky(plan.odkud)]);
    let fronta = [[plan.odkud, 0]];
    while (fronta.length) {
      const dalsi = [];
      fronta.forEach(([b, hloubka]) => {
        if (hloubka >= plan.dosah) return;
        for (let k = 0; k < 6; k++) {
          const n = hexy.soused(b, k);
          const kl = klicBunky(n);
          if (videno.has(kl)) continue;
          if (!teloVolne(hexy.bunkyTvaru(plan.tvar, n, plan.orientPred), plan.zabrane)) continue;
          if (zedVPrekazi(b, n)) continue;
          videno.add(kl);
          m.set(kl, n);
          dalsi.push([n, hloubka + 1]);
        }
      });
      fronta = dalsi;
    }
    return m;
  }

  // Kde se tvor po akci ocitne a kam bude hledět.
  const duchBunka = () => plan.kam || plan.odkud;
  const duchOrient = () => (plan.orient !== null ? plan.orient : plan.orientPred);

  function orientPovolena(o) {
    if (plan.krok && hexy.rozdilSmeru(plan.orientPred, o) * 60 > plan.otoceniMax) return false;
    if (plan.otocka && o === plan.orientPred) return false;
    return teloVolne(hexy.bunkyTvaru(plan.tvar, duchBunka(), o), plan.zabrane);
  }
  function duvodZakazuOrient(o) {
    if (plan.krok && hexy.rozdilSmeru(plan.orientPred, o) * 60 > plan.otoceniMax) return 'Při pohybu se tvor otočí nejvýš o ' + plan.otoceniMax + '°.';
    if (plan.otocka && o === plan.orientPred) return 'Tímhle směrem už hledíš.';
    return 'Tak otočené by tělo zasahovalo do cizího hexu.';
  }
  const SIPKY_HRANA = ['↑', '↗', '↘', '↓', '↙', '↖'];
  const popisOrient = (o) => (plan.tvar.typ_otaceni === 'hrana' ? SIPKY_HRANA[o] : SMER_SIPKY[o]);

  function popisPlanu() {
    const casti = [];
    const velky = plan.tvar.bunky.length > 1;
    if (plan.krok) {
      casti.push(plan.kam ? 'Cíl: vybrán' : 'Klikni na zelený hex, kam půjdeš (až o ' + popisHexu(plan.dosah) + ')'
        + (velky ? ' — přesune se otočný hex, tělo půjde s ním' : ''));
    }
    if (plan.krok || plan.otocka) {
      casti.push(plan.orient !== null ? 'Čelem: ' + popisOrient(plan.orient)
        : (plan.otocka ? 'Klikni do těla tvora směrem, kam se otočí (nebo použij tlačítka ↶ ↷)'
          : 'Čelem zůstaneš (nebo klikni do těla tvora směrem, kam se otočíš, případně ↶ ↷)'));
    }
    if (plan.utok) {
      const t = plan.cil ? tokenPodleId(plan.cil) : null;
      casti.push(t ? 'Útok na: ' + t.label : 'Klikni na tvora v oranžových hexech před sebou (nebo potvrď bez cíle)');
    }
    return casti;
  }
  function planJeHotov() {
    if (plan.krok && !plan.kam) return false;
    if (plan.otocka && plan.orient === null) return false;
    return true;
  }

  function otocOPlan(krok) {
    if (!plan || !(plan.krok || plan.otocka)) return;
    let o = duchOrient();
    for (let i = 0; i < 5; i++) {
      o = ((o + krok) % 6 + 6) % 6;
      if (orientPovolena(o)) {
        plan.orient = o;
        if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
        kreslit();
        prekreslitOverlay();
        return;
      }
    }
    logLine('Tak se otočit nejde.');
  }

  function kreslitPlan() {
    const box = el('div', 'tah-plan');
    box.append(el('b', '', AKCE_DRUHY[plan.druh]));
    popisPlanu().forEach((t) => box.append(el('p', 'ini-napoveda', t)));
    const r = el('div', 'tah-ovladani');
    if (plan.krok || plan.otocka) {
      r.append(
        tlacitko('↶', 'btn-ghost akce-btn', () => otocOPlan(-1), 'Otočit o 60° doleva'),
        tlacitko('↷', 'btn-ghost akce-btn', () => otocOPlan(1), 'Otočit o 60° doprava'),
      );
    }
    const potvrdit = tlacitko('Potvrdit', 'btn-secondary', odeslatPlan);
    potvrdit.disabled = !planJeHotov();
    r.append(potvrdit, tlacitko('Zrušit', 'btn-ghost', zrusitPlan));
    box.append(r);
    akceEl.append(box);
  }

  function odeslatPlan() {
    if (!plan || !planJeHotov()) return;
    const telo = {token_id: plan.tokenId, druh: plan.druh};
    if (plan.kam) telo.cil = {col: plan.kam.col, row: plan.kam.row};
    if (plan.orient !== null) telo.smer = hexy.smerZOrientace(plan.tvar, plan.orient);
    if (plan.cil) telo.cil_token_id = plan.cil;
    postJson('api/tah_akce.php', Object.assign({mapa_id: mapaId}, telo)).then((d) => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      plan = null;
      obnovitStav();
    });
  }

  // Klik na mapu při plánování; $m = bod v souřadnicích mapy.
  function klik(m) {
    if (!plan) return;
    const c = hexBunka(m.x, m.y);
    const pivot = duchBunka();
    const kl = klicBunky(c);
    const telo = new Set(hexy.bunkyTvaru(plan.tvar, pivot, duchOrient()).map(klicBunky));
    const jeCil = plan.krok && plan.mozne.has(kl) && !stejnaBunka(c, pivot);
    if ((plan.krok || plan.otocka) && telo.has(kl) && !jeCil) {
      // klik do těla tvora: čelem k místu kliknutí (úhel od otočného hexu)
      const st = hexStred(pivot.col, pivot.row);
      if (Math.hypot(m.x - st.x, m.y - st.y) < gridPx() * 0.22) return;
      const o = hexy.orientace(plan.tvar, Math.atan2(m.y - st.y, m.x - st.x) * 180 / Math.PI);
      if (!orientPovolena(o)) {
        logLine(duvodZakazuOrient(o));
        return;
      }
      plan.orient = o;
      if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
    } else if (jeCil) {
      plan.kam = plan.mozne.get(kl);
      if (plan.orient !== null && !orientPovolena(plan.orient)) {
        plan.orient = null;
        logLine('Tak otočené by tělo na novém místě narazilo — čelo se vrátilo.');
      }
      if (plan.cil && !predniJeCil(plan.cil)) plan.cil = null;
    } else if (plan.utok) {
      const t = tvorNaBunce(c);
      if (t && predniJeCil(t.id)) plan.cil = plan.cil === t.id ? null : t.id;
    } else {
      return;
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
  function prekreslitOverlay() {
    if (!soubojSvg) return;
    soubojSvg.innerHTML = '';
    if (!jeHex() || !kolo) return;
    if (plan) {
      if (plan.krok) {
        plan.mozne.forEach((b) => kresliHex(b, plan.kam && stejnaBunka(b, plan.kam) ? 'sb-cil-vybrany' : 'sb-cil'));
      }
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
      if (plan.krok || plan.otocka) {
        for (let k = 0; k < 6; k++) {
          const vybrany = k === duchOrient();
          kresliSipku(stDuch, hexy.smerZOrientace(plan.tvar, k), vybrany ? 'vybrana' : (orientPovolena(k) ? 'mozna' : 'zakazana'));
        }
      }
      if (plan.utok) {
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
  });

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
        logLine(p.label + ': ' + p.druh_nazev + (p.pocet > 1 ? ' ×' + p.pocet : '') + (p.cil_label ? ' → ' + p.cil_label : ''));
        break;
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
      // Plán akce platí jen pro toho, kdo je pořád na tahu, a jen když se nehnul.
      if (plan) {
        const t = aktivniToken();
        if (!kolo || kolo.faze !== 'kolo' || !t || t.id !== plan.tokenId || t.x !== plan.x || t.y !== plan.y
          || (t.smer || 0) !== plan.smer0 || (t.hexu || 1) !== plan.hexu0) plan = null;
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
