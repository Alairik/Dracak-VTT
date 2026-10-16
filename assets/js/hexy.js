// Hexová geometrie pro souboj — stejná matematika jako includes/vtt_hexy.php
// (pravidla: content/pravidla-hrac.html h1621, h1623). Mapa kreslí hexy jako
// "pointy-top" v rozložení odd-r; buňka = {col, row}. Směr čelem je ve stupních,
// 0 = doprava, po směru hodinových ručiček; šest stran hexu jsou násobky 60°,
// číslované 0..5 (0 východ, 1 jihovýchod, 2 jihozápad, 3 západ, 4 severozápad,
// 5 severovýchod).

const SMERY = [[1, 0], [0, 1], [-1, 1], [-1, 0], [0, -1], [1, -1]];

export function naOsy(b) { return [b.col - ((b.row - (b.row & 1)) / 2), b.row]; }
export function zOsy(q, r) { return {col: q + ((r - (r & 1)) / 2), row: r}; }
export function soused(b, smer) {
  const [q, r] = naOsy(b);
  const d = SMERY[((smer % 6) + 6) % 6];
  return zOsy(q + d[0], r + d[1]);
}
export function vzdalenost(a, b) {
  const [q1, r1] = naOsy(a);
  const [q2, r2] = naOsy(b);
  const dq = q1 - q2, dr = r1 - r2;
  return Math.max(Math.abs(dq), Math.abs(dr), Math.abs(dq + dr));
}
export function smerIdx(stupne) { return ((Math.round(stupne / 60) % 6) + 6) % 6; }
export function smerStupne(idx) { return (((idx % 6) + 6) % 6) * 60; }
export function rozdilSmeru(a, b) {
  const d = Math.abs((((a - b) % 6) + 6) % 6);
  return Math.min(d, 6 - d);
}
// Tři hexy před tvorem (ten přímo před ním a oba vedle něj).
export function predni(b, smer) { return [soused(b, smer - 1), soused(b, smer), soused(b, smer + 1)]; }
export function klic(b) { return b.col + ',' + b.row; }

// --- Tvorové na více hexech (HB; stejné jako includes/vtt_hexy.php) ---
// Tvar = {typ_otaceni: 'smer'|'hrana', utok: 'predni3'|'okoli', bunky: [[q,r]…], predni: [[q,r]…]}
// (data z tabulky tvary_tvoru, předává je server v window.MAPA.pravidla).
export function otocOs(qr, kroky) {
  const k = ((kroky % 6) + 6) % 6;
  let [q, r] = qr;
  for (let i = 0; i < k; i++) [q, r] = [-r, q + r];
  return [q, r];
}
export function bunkyTvaru(tvar, pivot, orientace, klic = 'bunky') {
  const [pq, pr] = naOsy(pivot);
  return tvar[klic].map((o) => { const [dq, dr] = otocOs([o[0], o[1]], orientace); return zOsy(pq + dq, pr + dr); });
}
export function zakladSmeru(tvar) { return tvar.typ_otaceni === 'hrana' ? 270 : 0; }
export function orientace(tvar, smerStupne) { return smerIdx(smerStupne - zakladSmeru(tvar)); }
export function smerZOrientace(tvar, orient) { return (((zakladSmeru(tvar) + 60 * orient) % 360) + 360) % 360; }
export function dosahUtoku(tvar, pivot, orient) {
  const telo = new Set(bunkyTvaru(tvar, pivot, orient, 'bunky').map(klic));
  const dosah = new Map();
  bunkyTvaru(tvar, pivot, orient, 'predni').forEach((c) => {
    const smery = tvar.utok === 'okoli' ? [0, 1, 2, 3, 4, 5] : [orient - 1, orient, orient + 1];
    smery.forEach((k) => { const n = soused(c, k); if (!telo.has(klic(n))) dosah.set(klic(n), n); });
  });
  return Array.from(dosah.values());
}
// Tři vrcholy čelní hrany jednoho předního hexu (střed [cx, cy], poloměr size): jeho dvě
// vnější strany ve směrech 4+orientace a 5+orientace (u orientace 0 sever a severovýchod
// strany "nahoru"). Vrcholy jsou číslované jako v obrysu hexu (úhel 60·i − 30°).
export function celniHranaBody(cx, cy, size, orient) {
  const a = ((orient % 6) + 6 + 4) % 6;
  return [a, a + 1, a + 2].map((i) => {
    const ang = Math.PI / 180 * (60 * (i % 6) - 30);
    return [cx + size * Math.cos(ang), cy + size * Math.sin(ang)];
  });
}

// Kolik 60° otočení stojí otočit tělo na místě z orientace o1 na o2: nejkratší cestou, a když
// na ní překáží cizí tvor (těla v mezipolohách musí být na volných hexech), druhou stranou.
// null = nejde. `obsazene` = Set klíčů "col,row". Stejná funkce je v PHP (includes/vtt_hexy.php).
export function otoceniMezi(tvar, pivot, o1, o2, obsazene) {
  o1 = ((o1 % 6) + 6) % 6;
  o2 = ((o2 % 6) + 6) % 6;
  if (o1 === o2) return 0;
  let nejlepsi = null;
  for (const strana of [1, -1]) {
    const kroku = ((((o2 - o1) * strana) % 6) + 6) % 6;
    let jde = true;
    for (let i = 1; i <= kroku && jde; i++) {
      const o = (((o1 + strana * i) % 6) + 6) % 6;
      if (bunkyTvaru(tvar, pivot, o).some((b) => obsazene.has(klic(b)))) jde = false;
    }
    if (jde && (nejlepsi === null || kroku < nejlepsi)) nejlepsi = kroku;
  }
  return nejlepsi;
}

// Ověří cestu zadanou po bodech (pohyb s plánováním cesty): cesta = [{col, row, o}, …] — každý bod je
// hex hned vedle předchozího a o je čelo, se kterým do něj tvor vstupuje (před posunem se na místě
// otočí z předchozí orientace na tuhle); oKonec je čelo po posledním bodu. Tělo musí v každé poloze
// a mezipoloze stát na volných hexech, zeď (je-li zed(a, b)) posun zastaví, bodů je nejvýš dosah
// a všechna otočení dohromady nejvýš maxOtoceni (kroků po 60°). Vrací {ok: true, otoceni, pivot, orient}
// nebo {ok: false, chyba, krok}; chyby: prazdna, dlouha, beze_zmeny, nesousedi, telo, zed,
// otoceni_blokovane, otoceni_rozpocet. Stejná funkce je v PHP (dracak_vtt_hex_over_cestu).
export function overCestu(tvar, odkud, o0, cesta, oKonec, obsazene, zed, dosah, maxOtoceni) {
  if (!cesta.length) return {ok: false, chyba: 'prazdna', krok: 0};
  if (cesta.length > dosah) return {ok: false, chyba: 'dlouha', krok: dosah};
  let p = {col: odkud.col, row: odkud.row};
  let o = ((o0 % 6) + 6) % 6;
  let otoceni = 0;
  for (let i = 0; i < cesta.length; i++) {
    const n = {col: cesta[i].col, row: cesta[i].row};
    const oi = ((cesta[i].o % 6) + 6) % 6;
    if (vzdalenost(p, n) !== 1) return {ok: false, chyba: 'nesousedi', krok: i};
    const t = otoceniMezi(tvar, p, o, oi, obsazene);
    if (t === null) return {ok: false, chyba: 'otoceni_blokovane', krok: i};
    otoceni += t;
    if (otoceni > maxOtoceni) return {ok: false, chyba: 'otoceni_rozpocet', krok: i};
    if (bunkyTvaru(tvar, n, oi).some((b) => obsazene.has(klic(b)))) return {ok: false, chyba: 'telo', krok: i};
    if (zed && zed(p, n)) return {ok: false, chyba: 'zed', krok: i};
    p = n;
    o = oi;
  }
  if (p.col === odkud.col && p.row === odkud.row) return {ok: false, chyba: 'beze_zmeny', krok: cesta.length - 1};
  const t = otoceniMezi(tvar, p, o, oKonec, obsazene);
  if (t === null) return {ok: false, chyba: 'otoceni_blokovane', krok: cesta.length};
  otoceni += t;
  if (otoceni > maxOtoceni) return {ok: false, chyba: 'otoceni_rozpocet', krok: cesta.length};
  return {ok: true, otoceni, pivot: p, orient: ((oKonec % 6) + 6) % 6};
}

// Čelo při chůzi po cestě: tvor se natáčí ve směru kroku (směr 0..5 = k hexu v tom směru).
// U tvarů s jednou stranou (jeden a dva hexy) je to přímo orientace = směr kroku; u tvarů s čelní
// hranou (D, E) leží směr kroku mezi dvěma čely — vybere se to, které je od současného čela blíž.
export function orientDoSmeru(tvar, smerKroku, oAktualni) {
  if (tvar.typ_otaceni !== 'hrana') return ((smerKroku % 6) + 6) % 6;
  const kandidati = [smerKroku + 1, smerKroku + 2].map((k) => ((k % 6) + 6) % 6);
  return kandidati.sort((a, b) => rozdilSmeru(oAktualni, a) - rozdilSmeru(oAktualni, b))[0];
}
