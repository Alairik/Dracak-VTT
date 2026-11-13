// Kouzla „ihned“ v klientu: menu „Kouzla ihned…“ u tahu, fronta, připojení k další akci; blok „Seslat teď“ v kartě obrany.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak', MAPA = 2;
const OUT = process.env.OUT || '/tmp/dracak-tests';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo, url) {
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();
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
  sql("delete from souboj_vyzva; update postavy set max_magenergie=30, aktualni_magenergie=30 where id=2; insert ignore into postava_zna_kouzlo (postava_id, kouzlo_id) values (2, 674), (2, 242)");
  const pridej = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
  const sindor = await pridej('postava', 13, 10), gob = await pridej('nestvura', 14, 10);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map((t) => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${sqlVal(`select entita_id from tokeny where id=${gob}`)}`);
  const magy = () => Number(sqlVal('select aktualni_magenergie from postavy where id=2'));

  // ===== 1) hráč na tahu: menu „Kouzla ihned…“, fronta, připojení k čekání =====
  const hr = await login(browser, 'hrac@local', 'hrac1234', '/hra/mapa.php?id=' + MAPA);
  await hr.waitForTimeout(1500);
  // Od 2026-11-12 se kouzlo vybírá v kartě dole: „Kouzlo“ → dlaždice kouzel → klepnutí na cíl (nebo „Na sebe“).
  await hr.click('#zoom-fit'); await hr.waitForTimeout(400);
  const stredTokenu = (id) => hr.$eval('.vtt-token[data-id="' + id + '"] .puck', (e) => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
  ok(await hr.locator('#bojKarta [data-tah="kouzlo"]').count() === 1, 'hráč na tahu má v kartě tlačítko „Kouzlo“');
  ok(await hr.locator('#bojKarta select').count() === 0, 'karta tahu nemá žádné rozbalovací pole');
  await hr.click('#bojKarta [data-tah="kouzlo"]');
  await hr.waitForTimeout(900);
  const dlazdice = await hr.locator('#bojKarta .bk-dlazdice.kouzla .bk-dlazdice-b').allInnerTexts();
  const najdi = (n) => (dlazdice.find((d) => d.startsWith(n)) || '').replace(/\s+/g, ' ');
  ok(/ihned/.test(najdi('Dusivý stín')) && /ihned/.test(najdi('Obrana')), 'kouzla „ihned“ jsou v nabídce označená: ' + najdi('Dusivý stín') + ' | ' + najdi('Obrana'));
  ok(/akc/.test(najdi('Modré blesky')) && !/ihned/.test(najdi('Modré blesky')), 'kouzlo na kola ukazuje počet akcí: ' + najdi('Modré blesky'));
  ok(/4 magy/.test(najdi('Dusivý stín')), 'u kouzla je vidět cena v mazích: ' + najdi('Dusivý stín'));
  ok(/Magenergie 30/.test(await hr.innerText('#bojKarta')), 'karta kouzel ukazuje magenergii postavy');
  await hr.locator('#bojKarta .bk-dlazdice-b', { hasText: 'Dusivý stín' }).click();
  await hr.waitForTimeout(300);
  ok(await hr.locator('#bojKarta [data-kouzlo="seslat"]').isDisabled(), 'bez cíle kouzlo seslat nejde');
  { const b = await stredTokenu(gob); await hr.mouse.click(b.x, b.y); await hr.waitForTimeout(400); }
  ok(/Dusivý stín → Goblin/.test(await hr.innerText('#bojKarta .bk-plan.kouzlo')), 'klepnutí na tvora na mapě ho vybere jako cíl kouzla');
  ok(await hr.locator('#soubojSvg .sb-cil-kouzla').count() >= 1, 'cíl kouzla je na mapě zvýrazněný');
  ok((await hr.innerText('#bojKarta [data-kouzlo="seslat"]')).trim() === 'Přidat k další akci', 'kouzlo „ihned“ se nepřipojuje akcí, ale přidá se k další akci');
  await hr.click('#bojKarta [data-kouzlo="seslat"]');
  await hr.waitForTimeout(500);
  ok(await hr.locator('#bojKarta .ihned-chip').count() === 1, 'kouzlo je ve frontě');
  await hr.click('#bojKarta [data-tah="kouzlo"]'); await hr.waitForTimeout(400);
  await hr.locator('#bojKarta .bk-dlazdice-b', { hasText: 'Obrana' }).click();
  await hr.click('#bojKarta [data-kouzlo="na-sebe"]');
  await hr.click('#bojKarta [data-kouzlo="seslat"]');
  await hr.waitForTimeout(500);
  ok(await hr.locator('#bojKarta .ihned-chip').count() === 2, 've frontě jsou dvě kouzla');
  await hr.screenshot({ path: OUT + '/kouzleni-ihned-fronta.png' });
  const akcePred = Number(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`));
  await hr.click('#bojKarta [data-tah="jine"]');
  await hr.click('#bojKarta [data-akce="cekani"]');
  await hr.waitForTimeout(1800);
  ok(Number(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`)) === akcePred - 1, 'čekání stálo jednu akci, kouzla žádnou');
  ok(magy() === 23, 'magy 30 − 4 − 3 = ' + magy());
  ok(await hr.locator('#bojKarta .ihned-chip').count() === 0, 'fronta se po odeslání vyprázdnila');
  const log = await hr.$eval('#logList', (x) => x.innerText);
  ok(/Dusivý stín/.test(log) && /Obrana/.test(log), 'v logu jsou obě kouzla');

  // ===== 2) karta obrany: blok „Seslat teď“ =====
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  let k = (await stav(pj)).kolo;
  for (let i = 0; i < 6 && k.aktivni_token_id !== gob; i++) { await api(pj, 'tah_konec', { mapa_id: MAPA }); k = (await stav(pj)).kolo; }
  await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gob, druh: 'utok', cil_token_id: sindor });
  const vy = (await stav(pj)).vyzvy;
  sql(`update souboj_vyzva set data = json_set(data, '$.zaklad', 3) where id=${vy[0].id}`);
  await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [4] });
  await hr.waitForTimeout(2500);
  const blok = hr.locator('.ihned-blok', { hasText: 'Kouzla ihned k obraně' });
  ok(await blok.count() === 1, 'v kartě obrany je blok „Kouzla ihned k obraně“');
  if (await blok.count() === 1) {
    await blok.locator('select.kouzleni-vyber').selectOption({ label: 'Obrana' });
    await blok.locator('button', { hasText: 'Přidat' }).click();
    await hr.waitForTimeout(800);
    const blok2 = hr.locator('.ihned-blok', { hasText: 'Kouzla ihned k obraně' });
    const seslat = blok2.locator('button', { hasText: 'Seslat teď' });
    ok(await seslat.isEnabled(), 'po přidání do fronty jde „Seslat teď“');
    await hr.screenshot({ path: OUT + '/kouzleni-ihned-obrana.png' });
    await seslat.click();
    await hr.waitForTimeout(1800);
    ok(magy() === 20, 'kouzlo k obraně zaplatilo magy: 23 − 3 = ' + magy());
  }

  ok(pj.chyby.length === 0 && hr.chyby.length === 0, 'žádné chyby v JS: ' + pj.chyby.concat(hr.chyby).join(' | '));
  sql("delete from postava_zna_kouzlo where postava_id=2 and kouzlo_id in (674, 242)");
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
