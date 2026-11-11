// Výběr tvora s náhledy (zadání uživatele 2026-11-11: „aby tam byl náhledový obrázek potvory a pak
// její název“; připomínky k návrhu na plátně https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz,
// deska „Výběr tokenu s náhledy“ — rozhodnutí: „není to třída, je to velikost, stačí napsat B“,
// „kolik zabírá je nesmysl, jedinečný zbytečná informace“, „je potřeba žvt X“, „úč může být vícero,
// vypsat jich všechny“).
//
// Co se ověřuje:
//   * seznam v popoveru „Přidat token“ nabízí celý bestiář a každý řádek má náhled i jméno,
//   * v řádku je velikost (samotné písmeno, ne „třída …“), žvt, OČ a ÚČ — a NIC o tom,
//     kolik tvor zabírá políček ani jestli je jedinečný,
//   * víc útočných čísel se vypíše všechna (oddělená), celé znění je v nájezdu myši,
//   * hledání zúží seznam, klepnutí zapíše druh do skrytého <select> (z něj čte pokládání tokenu),
//   * tvar náhledu se řídí mřížkou: na hexové mapě šestiúhelník, na čtvercové kolečko,
//   * žádná chyba JS.
//
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node tvor_vyber_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
async function otevri(b, mapaId) {
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
  await p.goto(BASE + '/index.php');
  await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(BASE + '/hra/mapa.php?id=' + mapaId); await p.waitForTimeout(1500);
  await p.click('#tb-pridat'); await p.waitForTimeout(400);
  return p;
}
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });

  // --- hexová mapa ---
  const hex = await otevri(b, 2);
  const vsech = await hex.locator('#nestvuraSeznam .tv-radek').count();
  ok(vsech > 100, 'seznam nabízí celý bestiář: ' + vsech);
  ok(await hex.locator('#nestvuraSeznam .tv-radek .tv-nahled').count() === vsech, 'každý řádek má náhled');
  ok(!(await hex.locator('#novaNestvuraSelect').isVisible()), 'starý rozbalovací seznam se neukazuje');

  await hex.fill('#nestvuraHledat', 'goblin'); await hex.waitForTimeout(250);
  const po = await hex.locator('#nestvuraSeznam .tv-radek').count();
  ok(po > 0 && po < vsech, 'hledání seznam zúží: ' + po);
  const radek = hex.locator('#nestvuraSeznam .tv-radek').first();
  ok(/goblin/i.test(await radek.locator('strong').textContent()), 'první je Goblin');

  const chipy = await radek.locator('.tv-chip').allTextContents();
  const text = chipy.join(' | ');
  ok(/^A[^a-z]*B$|^A/.test(chipy[0] || ''), 'první štítek je velikost samotným písmenem: ' + (chipy[0] || '—'));
  ok(!/tříd/i.test(text), 'nikde není slovo „třída“: ' + text);
  ok(/žvt/.test(text), 'je tam žvt: ' + text);
  ok(/OČ/.test(text), 'je tam OČ');
  ok(/ÚČ/.test(text), 'je tam ÚČ');
  ok(!/zabír|jedineč/i.test(text), 'není tam „zabírá“ ani „jedinečný“');

  // Víc útoků naráz — v jednom štítku oddělené, celé znění v nájezdu myši.
  await hex.fill('#nestvuraHledat', 'bazil'); await hex.waitForTimeout(250);
  const uc = hex.locator('#nestvuraSeznam .tv-radek').first().locator('.tv-uc');
  ok(await uc.count() === 1, 'útočná čísla jsou v jednom štítku');
  ok(/ÚČ/.test(await uc.textContent()), 'štítek začíná ÚČ: ' + (await uc.textContent()).trim());
  const titulek = await uc.getAttribute('title');
  ok(/Útočn/.test(titulek) && titulek.length > (await uc.textContent()).length, 'celé znění je v nájezdu myši');

  // Tvar náhledu podle mřížky.
  const hexTvar = await hex.locator('#nestvuraSeznam .tv-radek').first().evaluate((el) => ({
    tridaHex: el.classList.contains('hex'),
    clip: getComputedStyle(el.querySelector('.tv-nahled')).clipPath,
  }));
  ok(hexTvar.tridaHex && /polygon/.test(hexTvar.clip), 'na hexové mapě je náhled šestiúhelník');

  // Výběr zapíše id do skrytého selectu.
  await hex.fill('#nestvuraHledat', 'goblin'); await hex.waitForTimeout(250);
  await hex.locator('#nestvuraSeznam .tv-radek').first().click(); await hex.waitForTimeout(200);
  const idVybrane = await hex.locator('#novaNestvuraSelect').inputValue();
  ok(/^\d+$/.test(idVybrane), 'klepnutí zapsalo druh do skrytého výběru: ' + idVybrane);
  ok(await hex.locator('#nestvuraSeznam .tv-radek.vybrano').count() === 1, 'vybraný řádek je zvýrazněný');

  // --- čtvercová mapa: kolečko ---
  const ctverec = await otevri(b, 1);
  const ctvTvar = await ctverec.locator('#nestvuraSeznam .tv-radek').first().evaluate((el) => ({
    tridaHex: el.classList.contains('hex'),
    radius: getComputedStyle(el.querySelector('.tv-nahled')).borderRadius,
  }));
  ok(!ctvTvar.tridaHex && /50%/.test(ctvTvar.radius), 'na čtvercové mapě je náhled kolečko: ' + ctvTvar.radius);

  ok(hex.chybyJs.length === 0, 'hex: žádná chyba JS: ' + hex.chybyJs.join(' | '));
  ok(ctverec.chybyJs.length === 0, 'čtverec: žádná chyba JS: ' + ctverec.chybyJs.join(' | '));
  await b.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
