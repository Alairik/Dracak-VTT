const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const OUT = process.env.OUT || '/tmp/dracak-tests';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak', MAPA = 2;
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
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
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
  // Od 2026-11-12 se zbraň v boji mění přímo na kartě tahu: „v ruce: X ▾“ → nabídka zbraní u sebe (výměna stojí akci).
  const zb = hr.locator('#bojKarta [data-tah="zbran"]');
  ok(await zb.count() === 1 && /Dýka/.test(await zb.innerText()), 'karta tahu ukazuje, co má postava v ruce: ' + (await zb.innerText()).replace(/\s+/g, ' '));
  await zb.click(); await hr.waitForTimeout(900);
  const polozky = (await hr.locator('#bojKarta [data-nabidka="zbran"] .bk-nabidka-b').allInnerTexts()).map((t) => t.replace(/\s+/g, ' '));
  ok(polozky.some((t) => /^Dýka v ruce/.test(t)), 'držená zbraň je v nabídce označená „v ruce“: ' + polozky.join(' | '));
  ok(polozky.some((t) => /^Cep přezbrojit · 1 akce/.test(t)), 'u jiné zbraně je předem vidět, že výměna stojí akci');
  ok(await hr.locator('#bojKarta [data-zbran="2"]').isDisabled(), 'zbraň, kterou už drží, znovu vytasit nejde');
  await hr.screenshot({ path: OUT + '/zbrane_mapa.png' });
  const pred = parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`), 10);
  await hr.click('#bojKarta [data-zbran="39"]'); await hr.waitForTimeout(1800);
  ok(parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`), 10) === pred - 1, 'přezbrojení stálo jednu akci');
  ok(sqlVal('select drzena_zbran_id from postavy where id=2') === '39', 'postava teď drží Cep');
  const log = await hr.locator('body').innerText();
  ok(/Cep/.test(log) && /vytasení zbraně/.test(log), 'v logu souboje na mapě: vytasení zbraně (Cep)');
  if (await hr.locator('#bojKarta [data-tah="zbran"]').count()) ok(/Cep/.test(await hr.locator('#bojKarta [data-tah="zbran"]').innerText()), 'karta ukazuje novou zbraň v ruce');
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("update postavy set drzena_zbran_id=NULL where id=2");
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})();
