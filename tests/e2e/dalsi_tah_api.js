// „Další tah“ u tvora, kterému PJ snížil akce na 0 (dřív pád: BIGINT UNSIGNED value is out of range), a obrana akcí bez akcí.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.accept());
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(500);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  const a = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(10, 10).x, y: stred(10, 10).y })).token_id;
  const b = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(11, 10).x, y: stred(11, 10).y })).token_id;
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [a, b].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [a], hod: 5 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [b], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  // první „Další tah“ vybere prvního v pořadí
  let d = await api(pj, 'dalsi_tah', { mapa_id: MAPA });
  ok(d.ok, 'první Další tah: ' + (d.error || JSON.stringify(d).slice(0, 80)));
  const aktivni = (await stav(pj)).kolo.aktivni_token_id;
  // Stav z náhodné procházky: aktivní tvor má 0 akcí a pořád je na tahu (akce_uprava ho mohla rovnou posunout, proto přímo v DB).
  sql(`update kolo_iniciativa set akce_zbyvajici=0 where token_id=${aktivni}`);
  const zbyva = sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${aktivni}`);
  ok(zbyva === '0' && (await stav(pj)).kolo.aktivni_token_id === aktivni, 'aktivní tvor má 0 akcí a je na tahu: ' + zbyva);
  d = await api(pj, 'dalsi_tah', { mapa_id: MAPA });
  ok(d.s !== 500 && d.raw === undefined, 'Další tah u tvora s 0 akcemi nespadne na chybu serveru: ' + JSON.stringify(d).slice(0, 120));
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
