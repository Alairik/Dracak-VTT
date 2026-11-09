// Lišta správy tokenů dole uprostřed (jen PJ, migrace 0115): záložky, výroba dávky, filtr zásoby,
// pokládání tažením myší i prstem, klepnutí jako náhrada tažení, mazání ze zásoby. Hráč lištu nemá.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node token_bar_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  sql("delete from tokeny where typ_entity='nestvura_instance'; delete from nestvura_instance");
  const otevri = async (email, heslo, dotyk) => {
    const ctx = await browser.newContext({ viewport: { width: dotyk ? 820 : 1280, height: dotyk ? 1180 : 880 }, hasTouch: !!dotyk, isMobile: !!dotyk });
    const p = await ctx.newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(BASE + '/hra/mapa.php?id=' + MAPA); await p.waitForTimeout(1500);
    return p;
  };

  // --- hráč lištu nemá ---
  const hrac = await otevri('hrac@local', 'hrac1234');
  ok(await hrac.locator('#tb-tokeny').count() === 0, 'hráč nemá v liště tlačítko správy tokenů');
  ok(await hrac.locator('#tokenBar').count() === 0, 'hráč nemá lištu správy tokenů v HTML');
  await hrac.context().close();

  // --- PJ: otevření a záložky ---
  const pj = await otevri('test@local', 'test1234');
  ok(await pj.locator('#tokenBar').isVisible() === false, 'lišta je na začátku zavřená');
  await pj.click('#tb-tokeny'); await pj.waitForTimeout(600);
  ok(await pj.locator('#tokenBar').isVisible(), 'tlačítko lištu otevře');
  const stred = await pj.evaluate(() => { const r = document.getElementById('tokenBar').getBoundingClientRect(); return { s: Math.round(r.left + r.width / 2), w: Math.round(window.innerWidth) }; });
  ok(Math.abs(stred.s - stred.w / 2) < 30, 'lišta je dole uprostřed (střed ' + stred.s + ' z ' + stred.w + ')');
  ok(await pj.locator('#tbDruh option').count() > 100, 'výběr druhu nabízí bestiář: ' + await pj.locator('#tbDruh option').count());

  // --- hledání druhu ---
  await pj.fill('#tbHledat', 'goblin'); await pj.waitForTimeout(250);
  const prvni = (await pj.locator('#tbDruh option').first().textContent()).trim();
  ok(/goblin/i.test(prvni), 'hledání zúží výběr na Goblina: ' + prvni);

  // --- výroba dávky navázané na tuhle lokaci ---
  await pj.selectOption('#tbDruh', { index: 0 });
  await pj.fill('#tbPocet', '3');
  await pj.click('#tbVyrobit'); await pj.waitForTimeout(900);
  ok(sqlVal('select count(*) from nestvura_instance') === '3', 'vyrobily se 3 kusy do DB');
  ok(sqlVal(`select count(*) from nestvura_instance where lokace_mapa_id=${MAPA}`) === '3', 'patří k otevřené lokaci (výchozí „tady“)');
  ok(await pj.locator('#tbZasoba .tb-chip').count() === 3, 'zásoba ukazuje 3 chipy');
  ok((await pj.locator('#tbPocetZasoba').textContent()).trim() === '3', 'počet v záložce „Nevyužité“ sedí');
  ok(await pj.locator('[data-tb-panel="zasoba"]').isVisible(), 'po výrobě se lišta přepne na zásobu');

  // --- výroba bez místa + filtr ---
  await pj.click('[data-tb-zal="novy"]');
  await pj.selectOption('#tbLokace', '');
  await pj.fill('#tbPocet', '2');
  await pj.click('#tbVyrobit'); await pj.waitForTimeout(900);
  ok(sqlVal('select count(*) from nestvura_instance where lokace_mapa_id is null') === '2', 'dva kusy jsou bez místa');
  ok(await pj.locator('#tbZasoba .tb-chip').count() === 5, 'zásoba má 5 chipů');
  await pj.click('[data-tb-filtr="bez"]'); await pj.waitForTimeout(250);
  ok(await pj.locator('#tbZasoba .tb-chip').count() === 2, 'filtr „bez místa“ ukáže 2');
  await pj.click('[data-tb-filtr="tady"]'); await pj.waitForTimeout(250);
  ok(await pj.locator('#tbZasoba .tb-chip').count() === 3, 'filtr „z téhle lokace“ ukáže 3');
  await pj.click('[data-tb-filtr="vse"]'); await pj.waitForTimeout(250);

  // --- položení tažením myší ---
  const tokenuPred = await pj.locator('.vtt-token').count();
  const chip = pj.locator('#tbZasoba .tb-chip').first();
  const jmenoChipu = (await chip.locator('.tb-chip-jmeno').textContent()).trim();
  const bb = await chip.boundingBox();
  await pj.mouse.move(bb.x + bb.width / 2, bb.y + bb.height / 2);
  await pj.mouse.down();
  await pj.mouse.move(700, 380, { steps: 14 });
  const duch = await pj.locator('.tb-duch').count();
  await pj.mouse.up(); await pj.waitForTimeout(1200);
  ok(duch === 1, 'při tažení se ukazuje duch chipu');
  ok(await pj.locator('.vtt-token').count() === tokenuPred + 1, 'tažením přibyl token na mapě');
  ok(sqlVal(`select count(*) from nestvura_instance where nazev_instance='${jmenoChipu.replace(/'/g, "''")}' and mapa_id=${MAPA}`) === '1',
    'položený kus je v DB na mapě: ' + jmenoChipu);
  ok(await pj.locator('#tbZasoba .tb-chip').count() === 4, 'ze zásoby ubyl');
  await pj.click('[data-tb-zal="lokace"]'); await pj.waitForTimeout(300);
  ok(await pj.locator('#tbLokaceSeznam .tb-chip.na-mape').count() === 1, 'v záložce lokace je označený jako „na mapě“');

  // --- klepnutí jako náhrada tažení ---
  await pj.click('[data-tb-zal="zasoba"]'); await pj.waitForTimeout(300);
  const chip2 = pj.locator('#tbZasoba .tb-chip').first();
  await chip2.click(); await pj.waitForTimeout(300);
  ok(await pj.locator('#tbZasoba .tb-chip.nabito').count() === 1, 'klepnutí chip nabije');
  await pj.mouse.click(520, 520); await pj.waitForTimeout(1200);
  ok(await pj.locator('.vtt-token').count() === tokenuPred + 2, 'klepnutí do mapy nabitý chip položí');
  ok(await pj.locator('#tbZasoba .tb-chip.nabito').count() === 0, 'po položení už nic nabité není');

  // --- mazání ze zásoby ---
  const predSmazanim = await pj.locator('#tbZasoba .tb-chip').count();
  await pj.locator('#tbZasoba .tb-chip .tb-chip-x').first().click(); await pj.waitForTimeout(800);
  ok(await pj.locator('#tbZasoba .tb-chip').count() === predSmazanim - 1, 'křížek smaže NPC ze zásoby');

  // --- zavření ---
  await pj.click('#tbZavrit'); await pj.waitForTimeout(300);
  ok(await pj.locator('#tokenBar').isVisible() === false, 'křížek lištu zavře');
  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.join(' | '));
  await pj.context().close();

  // --- dotyk na tabletu: tažení prstem ---
  sql("delete from tokeny where typ_entity='nestvura_instance'; delete from nestvura_instance");
  const tablet = await otevri('test@local', 'test1234', true);
  await tablet.click('#tb-tokeny'); await tablet.waitForTimeout(600);
  await tablet.fill('#tbHledat', 'goblin'); await tablet.waitForTimeout(250);
  await tablet.selectOption('#tbDruh', { index: 0 });
  await tablet.fill('#tbPocet', '1');
  await tablet.click('#tbVyrobit'); await tablet.waitForTimeout(900);
  const cdp = await tablet.context().newCDPSession(tablet);
  const dotyk = (typ, body) => cdp.send('Input.dispatchTouchEvent', { type: typ, touchPoints: body.map((b, i) => ({ x: b.x, y: b.y, id: i })) });
  const ch = await tablet.locator('#tbZasoba .tb-chip').first().boundingBox();
  const od = { x: ch.x + ch.width / 2, y: ch.y + ch.height / 2 };
  const cil = { x: 420, y: 420 };
  await dotyk('touchStart', [od]);
  for (let i = 1; i <= 12; i++) { await dotyk('touchMove', [{ x: od.x + (cil.x - od.x) * i / 12, y: od.y + (cil.y - od.y) * i / 12 }]); await tablet.waitForTimeout(16); }
  await dotyk('touchEnd', []); await tablet.waitForTimeout(1300);
  ok(sqlVal(`select count(*) from nestvura_instance where mapa_id=${MAPA}`) === '1', 'tažení prstem položilo NPC na mapu');
  ok(tablet.chybyJs.length === 0, 'tablet: žádná chyba JavaScriptu: ' + tablet.chybyJs.join(' | '));

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
