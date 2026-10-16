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
