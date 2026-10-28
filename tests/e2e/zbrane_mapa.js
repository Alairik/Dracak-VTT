const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = 'dracak', MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo, url) {
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  page.dialogy = [];
  page.on('dialog', d => { page.dialogy.push(d.message()); d.accept(); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + url); await page.waitForTimeout(900);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('/hra/api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('/hra/api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234', '/hra/mapa.php?id=' + MAPA);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("delete from souboj_vyzva; update postavy set drzena_zbran_id=2 where id=2; update postava_predmety set u_sebe=1 where postava_id=2");
  const pridej = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
  const sindor = await pridej('postava', 13, 10), gob = await pridej('nestvura', 14, 10);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });

  const hr = await login(browser, 'hrac@local', 'hrac1234', '/hra/mapa.php?id=' + MAPA);
  await hr.waitForTimeout(1500);
  const sum = hr.locator('details.dalsi-akce summary');
  ok(await sum.count() === 1, 'hráč na tahu vidí menu „Další akce…“');
  await sum.click();
  const tl = hr.locator('button', { hasText: 'Vytasit zbraň…' });
  ok(await tl.count() === 1, 'v menu je „Vytasit zbraň…“');
  await tl.click(); await hr.waitForTimeout(1500);
  const viditelne = await hr.locator('#denikDock').isVisible();
  ok(viditelne, 'otevřelo se okno deníku');
  const src = await hr.locator('#denikFrame').getAttribute('src');
  ok(/id=2/.test(src || ''), 'deník postavy 2: ' + src);
  const fr = hr.frameLocator('#denikFrame');
  ok(await fr.locator('#boj').isVisible(), 'v deníku je vidět sekce Boj');
  await hr.screenshot({ path: 'zbrane_mapa.png' });
  // vytasit z okna deníku → mapa ukáže log a ubyde akce
  const pred = parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`), 10);
  hr.dialogy.length = 0;
  await fr.locator('button[data-vytasit="39"]').click(); await hr.waitForTimeout(1500);
  ok(parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`), 10) === pred - 1, 'z okna deníku: akce ubyla');
  const log = await hr.locator('body').innerText();
  ok(/Cep/.test(log) && /vytasení zbraně/.test(log), 'v logu souboje na mapě: vytasení zbraně (Cep)');
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("update postavy set drzena_zbran_id=NULL where id=2");
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})();
