// 3D kostky (dice-box) pro mapu. Hod je skutečný: čísla, která kostky ukážou,
// jsou výsledek hodu (posílají se na server), nic se nelosuje „za zády“.
// Když se 3D kostky nepodaří načíst (starý prohlížeč, bez WebGL), vrací
// hodit3D() null a volající nabídne ruční zápis hodu z fyzické kostky.

let box = null;
let nacitani = null;
let bezi = false;
let uklidTimer = null;

export function nactiKostky() {
  if (nacitani) return nacitani;
  nacitani = import('./vendor/dice-box/dice-box.es.min.js').then(async (mod) => {
    const b = new mod.default('#diceBoxContainer', {assetPath: new URL('../assets/js/vendor/dice-box/assets/', location.href).pathname, scale: 5});
    await b.init();
    box = b;
    return true;
  }).catch(() => false);
  return nacitani;
}

export function kostky3DPripraveny() { return box !== null; }

// Hodí `pocet` kostek s `typ` stěnami a vrátí pole čísel, která padla (nebo
// null, když 3D kostky nejsou k dispozici / hod se nepovedl).
export async function hodit3D(pocet, typ) {
  if (bezi) return null;
  await nactiKostky();
  if (!box) return null;
  bezi = true;
  try {
    clearTimeout(uklidTimer);
    box.clear();
    const vysledek = await box.roll(pocet + 'd' + typ);
    const hodnoty = (Array.isArray(vysledek) ? vysledek : []).map((k) => Number(k.value));
    uklidTimer = setTimeout(() => { try { box.clear(); } catch (e) { /* nevadí */ } }, 3500);
    if (hodnoty.length !== pocet || hodnoty.some((h) => !Number.isInteger(h) || h < 1 || h > typ)) return null;
    return hodnoty;
  } catch (e) {
    return null;
  } finally {
    bezi = false;
  }
}
