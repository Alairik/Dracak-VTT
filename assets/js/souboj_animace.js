// Animace souboje na mapě (zadání uživatele 2026-11-11; návrh a jeho připomínky na plátně
// https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz, deska „Souboj — kdo je na tahu a animace“).
//
// Co si uživatel vybral:
//   * „announcementy — hlásí, kdo je na tahu“ → pruh nahoře, krátce,
//   * „animace pohybu, hodně plynule vypadající; hlavně otočka – pohyb – otočka“,
//   * „zkusme 0,7 na celou animaci vždy“ → DOBA_CELKEM, rozdělená na otočku, přesun a dotočení,
//   * „ať je jasné, kdo je na tahu, kdo se hýbe a kdo zaútočil“ → tokeny dostanou na chvíli třídu,
//   * „animace útoku zblízka / střelby / kouzla, kouzlo navíc barevné podle typu“,
//   * „automaticky bych mapu necentroval, na mobilu by to bylo peklo“ → centruje se jen tlačítkem.
//
// Zblízka vs. na dálku se nepozná z akce (pravidla mají jen „útok“), ale ze vzdálenosti tokenů:
// do jednoho a půl sáhu je to máchnutí zbraní, dál letící střela.
//
// Všechno kreslí do vrstvy nad mapou v SOUŘADNICÍCH MAPY, takže se to posouvá a zvětšuje s ní.

export const DOBA_CELKEM = 700;          // ms na celou animaci (volba uživatele)
const DOBA_OTOCKY = 130;                 // otočka před přesunem a po něm
const DOBA_PRESUNU = DOBA_CELKEM - 2 * DOBA_OTOCKY;
const DOBA_UTOKU = 420;
const DOBA_OZNAMU = 1900;

// Barvy kouzel podle oboru magie (obory_magie v databázi — pravidla jiné dělení pro barvu nenabízejí;
// barvit podle povolání nejde, protože kouzlo z téhož povolání bývá pokaždé jiné „živlem“).
export const BARVY_OBORU = {
  1: '#8a7bff',   // časoprostorová
  2: '#ff7a3d',   // energetická útočná (oheň, blesk)
  3: '#ffd166',   // energetická ochranná
  4: '#e06bd4',   // iluzionistická
  5: '#9bbf6a',   // materiální
  6: '#4fd17a',   // vitální
  7: '#49c6e8',   // psychická
  8: '#cfd8e3',   // magie poznávání
};
export const BARVA_KOUZLA_JINE = '#b9a7ff';

export function initSoubojAnimace(ctx) {
  const { vrstva, oznamEl, tokenEl, tokenData, pxNaSah } = ctx;
  if (!vrstva) return null;
  let zapnuto = ctx.zapnuto !== false;
  let oznamTimer = null;

  const sah = () => (typeof pxNaSah === 'function' ? pxNaSah() : 0) || 50;
  const stred = (id) => { const t = tokenData(id); return t ? { x: t.x, y: t.y } : null; };

  function prvek(trida, styl) {
    const el = document.createElement('div');
    el.className = trida;
    if (styl) el.style.cssText = styl;
    vrstva.appendChild(el);
    return el;
  }
  const uklid = (el, ms) => setTimeout(() => el.remove(), ms);

  // --- kdo je na tahu: pruh nahoře ---
  function oznamTah(text, mujTah) {
    if (!oznamEl || !zapnuto) return;
    clearTimeout(oznamTimer);
    oznamEl.textContent = text;
    oznamEl.classList.toggle('muj', !!mujTah);
    oznamEl.hidden = false;
    // Restart animace i při dvou hlášeních hned po sobě.
    oznamEl.classList.remove('bezi');
    void oznamEl.offsetWidth;
    oznamEl.classList.add('bezi');
    oznamTimer = setTimeout(() => { oznamEl.hidden = true; oznamEl.classList.remove('bezi'); }, DOBA_OZNAMU);
  }

  // --- pohyb: otočka → přesun → dotočení ---
  // Vrací počet ms, po které animace běží (0 = neběží a volající může token překreslit hned).
  function pohyb(tokenId, zX, zY, naX, naY, smerPo) {
    const el = tokenEl(tokenId);
    if (!el || !zapnuto) return 0;
    const dx = naX - zX, dy = naY - zY;
    if (Math.hypot(dx, dy) < 1) return 0;
    const face = el.querySelector('.face');
    const puvodni = smerPo === null || smerPo === undefined ? null : Number(smerPo);
    const smerPohybu = Math.round(Math.atan2(dy, dx) * 180 / Math.PI) + 90;   // 0° = vzhůru, jako otočení tokenu

    el.classList.add('anim-pohyb');
    if (face) {
      face.style.transition = 'transform ' + DOBA_OTOCKY + 'ms ease';
      face.style.transform = 'rotate(' + smerPohybu + 'deg)';
    }
    setTimeout(() => {
      el.style.transition = 'left ' + DOBA_PRESUNU + 'ms cubic-bezier(.4,0,.2,1), top ' + DOBA_PRESUNU + 'ms cubic-bezier(.4,0,.2,1)';
      el.style.left = Math.round(naX) + 'px';
      el.style.top = Math.round(naY) + 'px';
    }, DOBA_OTOCKY);
    setTimeout(() => {
      if (face && puvodni !== null) face.style.transform = 'rotate(' + puvodni + 'deg)';
    }, DOBA_OTOCKY + DOBA_PRESUNU);
    setTimeout(() => {
      el.classList.remove('anim-pohyb');
      el.style.transition = '';
      if (face) face.style.transition = '';
    }, DOBA_CELKEM + 40);
    return DOBA_CELKEM;
  }

  // --- útok: máchnutí zblízka, střela na dálku, kouzlo barevné podle oboru ---
  function utok(odId, naId, druh, barva) {
    if (!zapnuto) return 0;
    const a = stred(odId), b = stred(naId);
    if (!a || !b) return 0;
    const elA = tokenEl(odId), elB = tokenEl(naId);
    if (elA) { elA.classList.add('anim-utoci'); setTimeout(() => elA.classList.remove('anim-utoci'), DOBA_UTOKU); }
    if (elB) { setTimeout(() => { elB.classList.add('anim-zasazen'); setTimeout(() => elB.classList.remove('anim-zasazen'), 300); }, DOBA_UTOKU - 120); }

    const d = Math.hypot(b.x - a.x, b.y - a.y);
    const naDalku = druh === 'kouzlo' || d > sah() * 1.5;
    const velikost = Math.max(10, Math.round(sah() * 0.34));

    if (!naDalku) {
      // Máchnutí: oblouk u cíle, natočený od útočníka.
      const uhel = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
      const sek = prvek('anim-sek', 'left:' + b.x + 'px;top:' + b.y + 'px;width:' + (velikost * 2.2) + 'px;height:'
        + (velikost * 2.2) + 'px;transform:translate(-50%,-50%) rotate(' + uhel + 'deg);');
      uklid(sek, DOBA_UTOKU + 200);
      return DOBA_UTOKU;
    }

    const strela = prvek('anim-strela' + (druh === 'kouzlo' ? ' kouzlo' : ''),
      'left:' + a.x + 'px;top:' + a.y + 'px;width:' + velikost + 'px;height:' + velikost + 'px;'
      + (barva ? '--anim-barva:' + barva + ';' : ''));
    requestAnimationFrame(() => {
      strela.style.transition = 'left ' + DOBA_UTOKU + 'ms linear, top ' + DOBA_UTOKU + 'ms linear';
      strela.style.left = b.x + 'px';
      strela.style.top = b.y + 'px';
    });
    setTimeout(() => {
      const vybuch = prvek('anim-dopad' + (druh === 'kouzlo' ? ' kouzlo' : ''),
        'left:' + b.x + 'px;top:' + b.y + 'px;width:' + (velikost * 2) + 'px;height:' + (velikost * 2) + 'px;'
        + (barva ? '--anim-barva:' + barva + ';' : ''));
      uklid(vybuch, 500);
      strela.remove();
    }, DOBA_UTOKU);
    return DOBA_UTOKU;
  }

  return {
    oznamTah,
    pohyb,
    utok,
    barvaOboru: (oborId) => BARVY_OBORU[Number(oborId)] || BARVA_KOUZLA_JINE,
    zapnuto: (b) => { zapnuto = !!b; },
    jeZapnuto: () => zapnuto,
  };
}
