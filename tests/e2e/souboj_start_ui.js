// Okno „Zahájit souboj“ — krok 0 nového průběhu souboje (návrh schválený 2026-11-12):
// souhrn stran místo seznamu, překvapení čtyřmi tlačítky, a zvlášť „kdo má zbraň v ruce“ (výchozí všichni, i překvapení).
// Kdo má prázdné ruce, vytasí bez akce zbraň, kterou má u sebe; odklepnutá strana zbraň zasune.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node souboj_start_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
const DYKA = 2, CEP = 39;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const api = async (p, name, body) => { const r = await p.request.post(BASE + '/hra/api/' + name + '.php', { data: body }); return { status: r.status(), d: await r.json().catch(() => ({})) }; };
  const drzena = (id) => sqlVal(`select ifnull(drzena_zbran_id,0) from postavy where id=${id}`);
  const tSindor = parseInt(sqlVal(`select id from tokeny where mapa_id=${MAPA} and typ_entity='postava' and entita_id=2`), 10);
  const tHrun = parseInt(sqlVal(`select id from tokeny where mapa_id=${MAPA} and typ_entity='postava' and entita_id=1`), 10);
  // Výchozí stav: oba s prázdnýma rukama; Sindor má u sebe jen Dýku (Cep v batohu), Hrun Dýku.
  const vychozi = () => sql(`update postavy set drzena_zbran_id=NULL where id in (1,2); update postava_predmety set u_sebe=1 where postava_id in (1,2); update postava_predmety set u_sebe=0 where postava_id=2 and predmet_id=${CEP}`);

  const pj = await prihlas('test@local', 'test1234');
  const hr = await prihlas('hrac@local', 'hrac1234');
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  const druh = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10);
  const gob = [];
  for (let i = 0; i < 3; i++) gob.push((await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: druh, x: 900 + i * 70, y: 300 })).d.token_id);
  vychozi();
  const otevri = async () => { await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(1500); await pj.click('#tb-souboj'); await pj.waitForSelector('#modalSouboj', { state: 'visible' }); };
  const stisk = (sel) => pj.getAttribute(sel, 'aria-pressed');

  // ===== vzhled okna =====
  await otevri();
  ok(!(await pj.locator('#soubojUcastnici').isVisible()), 'seznam tvorů je sbalený, okno ukazuje souhrn');
  // Souhrn proti řádkům seznamu (každý řádek = jeden tvor a jeho strana)
  const radky = await pj.$$eval('#soubojUcastnici .souboj-radek', (rs) => rs.map((r) => ({ strana: r.querySelector('select').value, zbran: !!r.querySelector('input[data-zbran]') })));
  const nDr = radky.filter((r) => r.strana === 'druzina').length, nNe = radky.filter((r) => r.strana === 'nepratele').length;
  ok(await pj.textContent('#soubojPocetDruzina') === String(nDr) && await pj.textContent('#soubojPocetNepratele') === String(nNe), `souhrn stran sedí se seznamem: družina ${nDr}, nepřátelé ${nNe}`);
  ok(/Sindor/.test(await pj.textContent('#soubojJmenaDruzina')) && /Hrun/.test(await pj.textContent('#soubojJmenaDruzina')), 'družina je vyjmenovaná: ' + await pj.textContent('#soubojJmenaDruzina'));
  ok(/Goblin ×3/.test(await pj.textContent('#soubojJmenaNepratele')), 'stejní nepřátelé se sloučí: ' + await pj.textContent('#soubojJmenaNepratele'));
  ok(await stisk('[data-prekvapeni="zadne"]') === 'true', 'výchozí překvapení: nikdo');
  ok(await stisk('[data-zbrane="druzina"]') === 'true', 'výchozí: družina má zbraň v ruce');
  ok(!(await pj.locator('[data-zbrane="nepratele"]').isVisible()), 'volba zbraní pro nepřátele není, když mezi nimi není žádná postava (nestvůry zbraň netasí)');
  ok(await pj.locator('#modalSouboj select:visible').count() === 0, 'v základním okně není žádné rozbalovací pole');

  // překvapení se přepíná tlačítkem a zbraní se netýká
  await pj.click('[data-prekvapeni="druzina"]');
  ok(await stisk('[data-prekvapeni="druzina"]') === 'true' && await stisk('[data-prekvapeni="zadne"]') === 'false', 'tlačítko překvapení se přepne');
  ok(await stisk('[data-zbrane="druzina"]') === 'true', 'překvapená družina má zbraň v ruce dál (jsou to dvě různé otázky)');

  // seznam: rozbalení, jednotlivec bez zbraně
  await pj.click('#soubojUprava > summary'); await pj.waitForTimeout(150);
  ok(await pj.locator('#soubojUcastnici').isVisible(), '„Upravit, kdo bojuje…“ rozbalí seznam');
  ok(radky.filter((r) => r.zbran).length === nDr && radky.filter((r) => r.strana === 'nepratele' && r.zbran).length === 0, 'zaškrtávátko „zbraň v ruce“ mají jen postavy');
  const radekHrun = pj.locator('#soubojUcastnici .souboj-radek', { hasText: 'Hrun' });
  await radekHrun.locator('input[data-zbran]').uncheck();
  ok(await stisk('[data-zbrane="druzina"]') === 'false', 'odškrtnutý jednotlivec: tlačítko strany už nesvítí');
  await pj.click('[data-zbrane="druzina"]');
  ok(await radekHrun.locator('input[data-zbran]').isChecked() && await stisk('[data-zbrane="druzina"]') === 'true', 'tlačítko strany zapne zbraň všem jejím postavám');
  await radekHrun.locator('input[data-zbran]').uncheck();

  // ===== zahájení: Sindor tasí, Hrun začíná beze zbraně =====
  sql(`update postavy set drzena_zbran_id=${DYKA} where id=1`);   // Hrun Dýku drží — má ji zasunout
  await pj.click('#soubojUlozit'); await pj.waitForTimeout(1200);
  ok(sqlVal(`select prekvapeni from kolo_stav where mapa_id=${MAPA}`) === 'druzina', 'překvapení se uložilo (družina)');
  ok(drzena(2) === String(DYKA), 'Sindor (prázdné ruce) vytasil zbraň, kterou má u sebe — Dýku, ne Cep z batohu');
  ok(drzena(1) === '0', 'Hrun (odškrtnutý) zbraň zasunul');
  ok(sqlVal(`select u_sebe from postava_predmety where postava_id=1 and predmet_id=${DYKA}`) === '1', 'Hrunova Dýka zůstala u těla');
  const log = await pj.evaluate(() => document.getElementById('logList').textContent);
  ok(/Sindor tasí: Dýka/.test(log), 'PJ vidí v logu, kdo co vytasil');
  ok(/Hrun začíná beze zbraně/.test(log), 'PJ vidí v logu, kdo začíná beze zbraně');
  await hr.goto(BASE + '/hra/mapa.php?id=' + MAPA); await hr.waitForTimeout(1500);
  const udalosti = await hr.evaluate(async (m) => JSON.stringify(await (await fetch('api/udalosti.php?mapa_id=' + m + '&od=0')).json()), MAPA);
  ok(!/tasili|schovali/.test(udalosti), 'hráči se výzbroj při zahájení v událostech neposílá');
  // za boje: úprava účastníků zbraně neřeší
  await pj.click('#tb-souboj'); await pj.waitForSelector('#modalSouboj', { state: 'visible' });
  ok(!(await pj.locator('#soubojZbraneBlok').isVisible()), 'při úpravě účastníků za boje se zbraně neřeší');
  ok(await pj.locator('#soubojUcastnici').isVisible(), 'při úpravě účastníků je seznam rovnou rozbalený');
  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu ' + pj.chybyJs.join(' | '));

  // ===== API =====
  const start = (kdo, ucastnici) => api(kdo, 'souboj_start', { mapa_id: MAPA, prekvapeni: 'zadne', ucastnici });
  await api(pj, 'souboj_konec', { mapa_id: MAPA }); vychozi();
  let r = await start(hr, [{ token_id: tSindor, strana: 'druzina', zbran: true }]);
  ok(r.status === 403 && drzena(2) === '0', 'hráč souboj zahájit nesmí a zbraň se nikomu nezmění (' + r.status + ')');

  r = await start(pj, [{ token_id: tSindor, strana: 'druzina' }, { token_id: gob[0], strana: 'nepratele' }]);
  ok(r.d.ok && drzena(2) === '0' && r.d.zbrane.tasili.length === 0, 'bez údaje o zbrani se nic nemění (starší volání)');

  await api(pj, 'souboj_konec', { mapa_id: MAPA }); vychozi();
  sql('update postava_predmety set u_sebe=0 where postava_id=2');
  r = await start(pj, [{ token_id: tSindor, strana: 'druzina', zbran: true }, { token_id: tHrun, strana: 'druzina', zbran: true }]);
  ok(drzena(2) === '0' && r.d.zbrane.bez.includes('Sindor'), 'kdo u sebe zbraň nemá, zůstane s prázdnýma rukama a PJ se to dozví');
  ok(drzena(1) === String(DYKA) && r.d.zbrane.tasili.some((z) => z.kdo === 'Hrun' && z.zbran === 'Dýka'), 'druhý ve stejném zahájení tasí normálně');

  await api(pj, 'souboj_konec', { mapa_id: MAPA }); vychozi();
  sql(`update postavy set drzena_zbran_id=${CEP} where id=2`);
  r = await start(pj, [{ token_id: tSindor, strana: 'druzina', zbran: true }]);
  ok(drzena(2) === String(CEP) && r.d.zbrane.tasili.length === 0, 'kdo už zbraň drží, drží ji dál (nic se nepřehazuje)');

  await api(pj, 'souboj_konec', { mapa_id: MAPA }); vychozi();
  r = await start(pj, [{ token_id: tSindor, strana: 'druzina', zbran: 'ano' }, { token_id: gob[0], strana: 'nepratele', zbran: true }]);
  ok(r.d.ok && drzena(2) === '0', 'nesmyslná hodnota a „zbraň“ u nestvůry se ignorují');

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const id of gob) await api(pj, 'token_smazat', { token_id: id, mapa_id: MAPA });
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
