// Lišta nástrojů po sekcích a házecí místo mimo ni (zadání uživatele 2026-11-11: „levé boční menu je
// masivní, potřeboval bych ho rozsekat na logické sekce“ + „kostka by měla být floating sama o sobě
// někde bokem, vyskakovací okno, které vyvolá házecí místo — dice tray“).
//
// Co se ověřuje:
//   * lišta má pět pojmenovaných sekcí a každá má aspoň jedno tlačítko,
//   * lišta je JEDEN sloupec — všechna tlačítka mají stejnou vodorovnou polohu
//     (obecná .toolbar v organic.css má flex-wrap:wrap, kvůli kterému se lišta rozpadala na dva),
//   * klepnutí na popisek sekci sbalí a prohlížeč si to pamatuje i po načtení znovu,
//   * kostka v liště NENÍ a plovoucí tlačítko otevře házecí místo se sedmi kostkami,
//   * hod z házecího místa se zapíše do logu a ostatní u stolu ho uvidí jako kartu s číslem,
//   * žádná chyba JS.
//
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node lista_skupiny_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAPA = 2;
const SEKCE = ['Pohyb', 'Souboj', 'Tvorové', 'Mapa', 'Ostatní'];
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
async function login(b, email, heslo) {
  const p = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
  await p.goto(BASE + '/index.php');
  await p.fill('#email', email); await p.fill('#password', heslo);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(BASE + '/hra/mapa.php?id=' + MAPA); await p.waitForTimeout(1500);
  return p;
}
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const pj = await login(b, 'test@local', 'test1234');
  const hr = await login(b, 'hrac@local', 'hrac1234');

  // --- sekce ---
  const nazvy = await pj.$$eval('.toolbar .tb-skup .tb-nazev', (e) => e.map((x) => x.textContent.trim()));
  ok(JSON.stringify(nazvy) === JSON.stringify(SEKCE), 'sekce v liště: ' + nazvy.join(' / '));
  const prazdne = await pj.$$eval('.toolbar .tb-skup', (e) => e.filter((s) => !s.querySelector('.tbtn')).length);
  ok(prazdne === 0, 'žádná sekce není prázdná');
  const mimoSekci = await pj.$$eval('.toolbar .tbtn', (e) => e.filter((x) => !x.closest('.tb-skup')).length);
  ok(mimoSekci === 0, 'každé tlačítko lišty patří do nějaké sekce');

  // --- jeden sloupec ---
  const sloupcu = await pj.$$eval('.toolbar .tbtn', (e) => new Set(e.map((x) => Math.round(x.getBoundingClientRect().x))).size);
  ok(sloupcu === 1, 'lišta je jeden sloupec (různých poloh X: ' + sloupcu + ')');

  // --- sbalení sekce a paměť ---
  const vidu = (page, sekce) => page.$$eval('.toolbar .tb-skup', (e, s) => {
    const sk = e.find((x) => x.querySelector('.tb-nazev').textContent.trim() === s);
    return [...sk.querySelectorAll('.tbtn')].filter((b) => b.offsetParent !== null).length;
  }, sekce);
  const predSbalenim = await vidu(pj, 'Mapa');
  ok(predSbalenim > 0, 'sekce Mapa má vidět tlačítka (' + predSbalenim + ')');
  await pj.locator('.toolbar .tb-nazev', { hasText: 'Mapa' }).click();
  await pj.waitForTimeout(200);
  ok(await vidu(pj, 'Mapa') === 0, 'po klepnutí na popisek je sekce sbalená');
  ok(await vidu(pj, 'Pohyb') > 0, 'ostatní sekce zůstaly rozbalené');
  await pj.reload(); await pj.waitForTimeout(1500);
  ok(await vidu(pj, 'Mapa') === 0, 'sbalení vydrží i po načtení stránky znovu');
  await pj.locator('.toolbar .tb-nazev', { hasText: 'Mapa' }).click();
  await pj.waitForTimeout(200);
  ok(await vidu(pj, 'Mapa') === predSbalenim, 'druhé klepnutí sekci vrátí');

  // --- házecí místo ---
  ok(await pj.locator('.toolbar #tb-kostky').count() === 0, 'kostka už není v liště nástrojů');
  ok(await pj.locator('#kostkyOtevrit').isVisible(), 'plovoucí tlačítko kostek je vidět');
  ok(!(await pj.locator('#kostkyTray').isVisible()), 'házecí místo je zavřené');
  await pj.click('#kostkyOtevrit'); await pj.waitForTimeout(250);
  ok(await pj.locator('#kostkyTray').isVisible(), 'tlačítko házecí místo otevře');
  ok(await pj.locator('#kostkyTray .kostky-k').count() === 7, 'na výběr je sedm kostek (k4–k100)');

  // --- hod: log u hazeče, karta u ostatních ---
  await pj.locator('#kostkyTray .kostky-k[data-kostka="100"]').click();
  await pj.waitForTimeout(1200);
  const log = await pj.locator('#panel-log').textContent();
  ok(/k100/.test(log), 'hod je v logu u hazeče');
  await hr.waitForTimeout(3500);
  ok(await hr.locator('.kostka-hod').count() >= 1, 'ostatní u stolu vidí kartu s hodem');
  const cislo = (await hr.locator('.kostka-cislo').first().textContent()).trim();
  ok(/^\d+$/.test(cislo) && Number(cislo) >= 1 && Number(cislo) <= 100, 'na kartě je číslo z rozsahu k100: ' + cislo);
  ok(/k100/.test(await hr.locator('#panel-log').textContent()), 'hod je i v logu ostatních');
  await pj.click('#kostkyZavrit'); await pj.waitForTimeout(200);
  ok(!(await pj.locator('#kostkyTray').isVisible()), 'křížek házecí místo zavře');

  ok(pj.chybyJs.length === 0, 'PJ bez chyby JS: ' + pj.chybyJs.join(' | '));
  ok(hr.chybyJs.length === 0, 'hráč bez chyby JS: ' + hr.chybyJs.join(' | '));
  await b.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
