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
