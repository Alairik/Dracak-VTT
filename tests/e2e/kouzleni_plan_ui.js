// Kouzla „ihned“ připojená k naplánovanému tahu na hexové mapě (klik na nepřítele → Krok a útok → Potvrdit): fronta z menu „Kouzla ihned…“ odejde spolu s akcí,
// kouzla se sešlou při té akci (magy, zranění cíle), akce stojí jen jednu akci a fronta se vyprázdní. Mapa 2 (hex). Po resetu dat: mysql dracak < ../fixture/seed.sql; node kouzleni_plan_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak', MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo, url) {
  const page = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  page.chyby = [];
  page.on('pageerror', (e) => page.chyby.push(e.message));
  page.on('dialog', (d) => d.accept());
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
  sql("delete from souboj_vyzva; update postavy set drzena_zbran_id=2, unava=0, max_magenergie=30, aktualni_magenergie=30 where id=2; update postava_predmety set u_sebe=1 where postava_id=2; insert ignore into postava_zna_kouzlo (postava_id, kouzlo_id) values (2, 674), (2, 242)");
  const pridej = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
  const sindor = await pridej('postava', 12, 10), gob = await pridej('nestvura', 10, 10);
  await api(pj, 'token_uprava', { token_id: sindor, zmeny: { smer: 3 } });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map((t) => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  const gobInst = sqlVal(`select entita_id from tokeny where id=${gob}`);
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${gobInst}`);
  const hpGob = () => Number(sqlVal(`select aktualni_hp from nestvura_instance where id=${gobInst}`));
  const magy = () => Number(sqlVal('select aktualni_magenergie from postavy where id=2'));
  const akce = () => Number(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`));

  const hr = await login(browser, 'hrac@local', 'hrac1234', '/hra/mapa.php?id=' + MAPA);
  await hr.waitForTimeout(1500);
  await hr.click('#zoom-fit'); await hr.waitForTimeout(300);
  const rect = (id) => hr.$eval('.vtt-token[data-id="' + id + '"] .puck', (e) => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
  const a = await rect(gob), b = await rect(sindor);
  const A = stred(10, 10), B = stred(12, 10);
  const sc = (b.x - a.x) / (B.x - A.x);
  const sx = (c, r) => { const m = stred(c, r); return { x: a.x + (m.x - A.x) * sc, y: a.y + (m.y - A.y) * sc }; };

  // fronta kouzel ihned z karty dole (od 2026-11-12: „Kouzlo“ → dlaždice → cíl klepnutím na mapě / „Na sebe“)
  await hr.click('#bojKarta [data-tah="kouzlo"]');
  await hr.waitForTimeout(800);
  await hr.locator('#bojKarta .bk-dlazdice-b', { hasText: 'Dusivý stín' }).click();
  { const c = sx(10, 10); await hr.mouse.click(c.x, c.y); await hr.waitForTimeout(400); }
  await hr.click('#bojKarta [data-kouzlo="seslat"]');
  await hr.waitForTimeout(400);
  await hr.click('#bojKarta [data-tah="kouzlo"]'); await hr.waitForTimeout(400);
  await hr.locator('#bojKarta .bk-dlazdice-b', { hasText: 'Obrana' }).click();
  await hr.click('#bojKarta [data-kouzlo="na-sebe"]');
  await hr.click('#bojKarta [data-kouzlo="seslat"]');
  await hr.waitForTimeout(400);
  ok(await hr.locator('#bojKarta .ihned-chip').count() === 2, 've frontě jsou dvě kouzla');

  // plánovaný tah: klik na goblina (2 hexy) → Krok a útok
  const akcePred = akce();
  const p = sx(10, 10);
  await hr.mouse.click(p.x, p.y);
  await hr.waitForTimeout(600);
  const hlavicka = await hr.$eval('#bojKarta .bk-plan-text strong', (x) => x.innerText).catch(() => '');
  ok(/Krok a útok/.test(hlavicka), 'klik na goblina naplánuje Krok a útok: ' + hlavicka);
  ok(await hr.locator('#bojKarta .ihned-chip').count() === 2, 'fronta kouzel přežila plánování tahu');
  await hr.click('#bojKarta [data-plan="potvrdit"]');
  await hr.waitForTimeout(2200);

  const tS = (await stav(pj)).tokeny.find((t) => t.id === sindor);
  ok(Math.abs(tS.x - stred(11, 10).x) <= 1, 'Sindor udělal krok na (11,10): ' + tS.x + ',' + tS.y);
  ok(akce() === akcePred - 1, 'krok a útok stál jednu akci, kouzla žádnou (' + akcePred + ' → ' + akce() + ')');
  ok(magy() === 23, 'kouzla se sešlou při té akci: magy 30 − 4 − 3 = ' + magy());
  ok(hpGob() < 20, 'Dusivý stín zranil goblina: 20 → ' + hpGob());
  ok(Number(sqlVal(`select count(*) from svet_udalosti where mapa_id=${MAPA} and typ='kouzleni_stav' and payload like '%ihned%'`)) === 2, 'v logu jsou dvě události „ihned“');
  ok(Number(sqlVal(`select count(*) from souboj_vyzva where mapa_id=${MAPA}`)) >= 1, 'útok založil výzvu k hodu (kouzla ho nezrušila)');
  ok(await hr.locator('.ihned-chip').count() === 0, 'fronta se po odeslání vyprázdnila');
  ok(hr.chyby.length === 0 && pj.chyby.length === 0, 'žádné chyby v JS: ' + hr.chyby.concat(pj.chyby).join(' | '));

  sql("delete from postava_zna_kouzlo where postava_id=2 and kouzlo_id in (674, 242); delete from souboj_vyzva");
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
