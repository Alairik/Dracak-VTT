// Světlo se zapaluje u předmětu v deníku, ne ručně na tokenu (návrh docs/panel-tokenu-navrh.md, část D).
//
// Dřív PJ klikal „Pochodeň (6 sáhů)“ na tokenu, přestože postava pochodeň fyzicky nesla v inventáři — dvě
// místa pro jednu věc. Teď má zdroj světla v inventáři akci Zapálit / Zhasnout a token se rozsvítí sám.
// Ruční nastavení na tokenu zůstává pro nestvůry, obecné tokeny (ohniště, svítící socha) a opravy.
//
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node svetlo_predmet_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 950 } })).newPage();
  page.chybyJs = []; page.on('pageerror', (e) => page.chybyJs.push(e.message));
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return page;
}
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });

  // Předměty si test vybírá sám podle kategorie z katalogu, ne podle pořadí v tabulce.
  const pochoden = parseInt(sqlVal("select id from predmety where kategorie_vybaveni='Svícení' and nazev like '%ochod%' order by id limit 1"), 10);
  const kresadlo = parseInt(sqlVal("select id from predmety where kategorie_vybaveni='Svícení' and nazev like '%řesadl%' order by id limit 1"), 10);
  const lano = parseInt(sqlVal("select id from predmety where typ='vybaveni' and coalesce(kategorie_vybaveni,'')<>'Svícení' order by id limit 1"), 10);
  ok(pochoden > 0 && kresadlo > 0 && lano > 0, 'v katalogu je pochodeň, křesadlo i běžné vybavení: ' + [pochoden, kresadlo, lano].join('/'));

  const POSTAVA = 2;   // Sindor — má token na mapě i hráčského vlastníka
  sql(`delete from postava_predmety where postava_id=${POSTAVA} and predmet_id in (${pochoden},${kresadlo},${lano})`);
  sql(`insert into postava_predmety (postava_id, predmet_id, mnozstvi) values (${POSTAVA},${pochoden},1),(${POSTAVA},${kresadlo},1),(${POSTAVA},${lano},1)`);
  const token = sqlVal(`select id from tokeny where typ_entity='postava' and entita_id=${POSTAVA} order by id desc limit 1`);
  ok(token !== '', 'postava má token na mapě: ' + token);
  sql(`update tokeny set svetlo_sahy=NULL, svetlo_typ=NULL, svetlo_zbyva=NULL where id=${token}`);
  const svetlo = () => sqlVal(`select concat(coalesce(svetlo_typ,'-'),'|',coalesce(svetlo_sahy,'-'),'|',coalesce(svetlo_zbyva,'-')) from tokeny where id=${token}`);

  const pj = await login(browser, 'test@local', 'test1234');
  await pj.goto(BASE + '/hra/denik.php?id=' + POSTAVA); await pj.waitForTimeout(1200);
  await pj.click('.dk-tab[data-tab="inventar"]').catch(() => {});
  await pj.waitForTimeout(400);

  // 1) tlačítko jen u toho, co opravdu svítí
  ok(await pj.locator('[data-svetlo="' + pochoden + '"]').count() === 1, 'pochodeň má v inventáři akci Zapálit');
  ok(await pj.locator('[data-svetlo="' + kresadlo + '"]').count() === 0, 'křesadlo akci nemá (samo nesvítí)');
  ok(await pj.locator('[data-svetlo="' + lano + '"]').count() === 0, 'běžné vybavení akci nemá');

  // 2) zapálení se propíše do tokenu
  const btn = pj.locator('[data-svetlo="' + pochoden + '"]').first();
  ok((await btn.textContent()).trim() === 'Zapálit', 'dokud nic nehoří, nabízí tlačítko Zapálit');
  await btn.click(); await pj.waitForTimeout(1200);
  ok(svetlo() === 'pochoden|6.0|3', 'po zapálení token svítí jako pochodeň: ' + svetlo());
  ok((await btn.textContent()).trim() === 'Zhasnout', 'tlačítko se přepnulo na Zhasnout');

  // 3) stav přežije načtení stránky (čte se z tokenu, ne z paměti prohlížeče)
  await pj.reload(); await pj.waitForTimeout(1200);
  await pj.click('.dk-tab[data-tab="inventar"]').catch(() => {});
  await pj.waitForTimeout(400);
  ok((await pj.locator('[data-svetlo="' + pochoden + '"]').first().textContent()).trim() === 'Zhasnout',
    'po obnovení stránky deník ví, že pochodeň hoří');

  // 4) zhasnutí
  await pj.locator('[data-svetlo="' + pochoden + '"]').first().click(); await pj.waitForTimeout(1200);
  ok(svetlo() === '-|-|-', 'po zhasnutí token nesvítí: ' + svetlo());
  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.join(' | '));

  // 5) hráč to smí na své postavě
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  let d = await hr.evaluate(async ([pid, prid]) => {
    const r = await fetch('/hra/api/svetlo_predmet.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ postava_id: pid, predmet_id: prid, zapnout: true }) });
    return { s: r.status, t: (await r.text()).slice(0, 120) };
  }, [POSTAVA, pochoden]);
  ok(d.s === 200 && svetlo().startsWith('pochoden'), 'hráč si vlastní pochodeň zapálí sám: ' + d.s + ' ' + svetlo());

  // 6) cizí postava a předmět, který postava nemá — server odmítne
  d = await hr.evaluate(async (prid) => {
    const r = await fetch('/hra/api/svetlo_predmet.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ postava_id: 1, predmet_id: prid, zapnout: true }) });
    return r.status;
  }, pochoden);
  ok(d === 403, 'cizí postavu hráč nerozsvítí (403): ' + d);
  d = await hr.evaluate(async (prid) => {
    const r = await fetch('/hra/api/svetlo_predmet.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ postava_id: 2, predmet_id: prid, zapnout: true }) });
    return r.status;
  }, 999999);
  ok(d === 404, 'předmět, který postava nemá, se zapálit nedá (404): ' + d);

  sql(`update tokeny set svetlo_sahy=NULL, svetlo_typ=NULL, svetlo_zbyva=NULL where id=${token}`);
  sql(`delete from postava_predmety where postava_id=${POSTAVA} and predmet_id in (${pochoden},${kresadlo},${lano})`);
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
