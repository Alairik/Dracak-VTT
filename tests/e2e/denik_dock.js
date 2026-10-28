const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo, w = 1400, h = 900) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h } });
  const page = await ctx.newPage();
  page.on('dialog', d => d.accept('2'));
  page.on('pageerror', e => { chyb++; console.log('CHYBA pageerror ' + e.message); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(1000);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  let pj = await login(browser, 'test@local', 'test1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const pridej = async (typ, col, row, id) => {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: id }));
    return d.token_id;
  };
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("update postavy set aktualni_hp=5, max_hp=5, drzena_zbran_id=NULL where id in (1,2); delete pp from postava_predmety pp join predmety p on p.id=pp.predmet_id where pp.postava_id=2 and p.nazev='Cep'");
  const tSindor = await pridej('postava', 12, 10, 2);
  const tHrun = await pridej('postava', 15, 10, 1);
  const tGob = await pridej('nestvura', 18, 10);
  await pj.reload(); await pj.waitForTimeout(1500);
  const polohy = async () => JSON.stringify(await pj.$$eval('.vtt-token .puck', els => els.map(e => { const r = e.getBoundingClientRect(); return [Math.round(r.x), Math.round(r.y)]; })));
  const klik = async (id) => { const b = await pj.$eval('.vtt-token[data-id="' + id + '"] .puck', e => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; }); await pj.mouse.click(b.x, b.y); await pj.waitForTimeout(900); };
  const frame = () => pj.frameLocator('#denikFrame');
  const postavaVRamci = () => pj.$eval('#denikPostava', e => e.value);

  // ===== PJ =====
  ok(await pj.locator('#tb-denik').count() === 0, 'v levé liště už tlačítko Deník není');
  ok(!(await pj.locator('#denikDock').isVisible()), 'panel je zavřený');
  await klik(tGob);
  ok(await pj.locator('#panel-spravovat.open').count() === 1 && !(await pj.locator('#spravovatDenik').isVisible()), 'v detailu nestvůry tlačítko deníku zatím není (deník nestvůry se teprve navrhuje)');
  await klik(tSindor);
  ok(await pj.locator('#spravovatDenik').isVisible(), 'v detailu tokenu postavy je tlačítko „Otevřít deník“');
  // skoky na sekce z detailu tokenu
  ok(await pj.locator('#spravovatDenikBlok [data-denik-sekce]').count() === 3, 'pod tlačítkem jsou skoky Boj / Kouzla / Výbava');
  const polohaSekce = async (id) => frame().locator('body').evaluate((b, i) => { const panel = ['vybaveni', 'kouzla', 'schopnosti'].includes(i); const el = panel ? document.querySelector('.dk-tabbar') : document.getElementById(i); const pas = document.querySelector('.dn-pas'); const drziSe = ['sticky', 'fixed'].includes(getComputedStyle(pas).position); const aktivni = document.querySelector('.dk-tab.on'); return { top: Math.round(el.getBoundingClientRect().top), pas: drziSe ? Math.round(pas.getBoundingClientRect().height) : 0, y: Math.round(window.scrollY), tab: aktivni ? aktivni.dataset.tab : null }; }, id);
  await pj.click('#spravovatDenikBlok [data-denik-sekce="vybaveni"]'); await pj.waitForTimeout(3000);
  ok(await pj.locator('#denikDock').isVisible(), 'skok na Výbavu otevře zavřený deník');
  let pol = await polohaSekce('vybaveni');
  ok(pol.tab === 'inventar', 'skok na Výbavu přepne záložku Inventář: ' + JSON.stringify(pol));
  await pj.click('#spravovatDenikBlok [data-denik-sekce="boj"]'); await pj.waitForTimeout(1800);
  pol = await polohaSekce('boj');
  ok(pol.top >= pol.pas && pol.top - pol.pas < 60, 'druhý skok (už otevřený deník) srovná sekci Boj: ' + JSON.stringify(pol));
  await pj.click('#spravovatDenikBlok [data-denik-sekce="kouzla"]'); await pj.waitForTimeout(1800);
  pol = await polohaSekce('kouzla');
  ok(pol.tab === 'kouzla', 'skok na Kouzla přepne záložku Kouzla: ' + pol.tab);

  await pj.click('#spravovatDenikBlok [data-denik-sekce="vybaveni"]'); await pj.waitForTimeout(1800);
  pol = await polohaSekce('vybaveni');
  ok(pol.tab === 'inventar', 'opakovaný skok na Výbavu přepne zpět na Inventář: ' + JSON.stringify(pol));
  // Inventář v detailu
  ok(await pj.locator('#spravovatInventar [data-denik-vybava]').count() === 1, 'v Inventáři detailu je „Upravit v deníku“');
  await frame().locator('body').evaluate(() => window.scrollTo(0, 0)); await pj.waitForTimeout(600);
  await pj.click('#spravovatInventar [data-denik-vybava]'); await pj.waitForTimeout(1800);
  pol = await polohaSekce('vybaveni');
  ok(pol.tab === 'inventar', 'z Inventáře v detailu se deník rozbalí rovnou na záložku Inventář: ' + JSON.stringify(pol));
  await pj.click('#denikZavrit'); await pj.waitForTimeout(500);
  // odkaz na pravidla
  const prav = await pj.$eval('.topbar .topbar-pravidla', e => ({ href: e.getAttribute('href'), target: e.target }));
  ok(prav.href === '../pravidla.php' && prav.target === '_blank', 'v horní liště je odkaz Pravidla do nové záložky');
  ok((await pj.evaluate(async () => { const r = await fetch('../pravidla.php'); await r.text(); return r.status; })) === 200, 'a pravidla.php odpovídá');
  await pj.click('#spravovatDenik'); await pj.waitForTimeout(3000);
  ok(await pj.locator('#denikDock').isVisible(), 'klik otevře panel vedle mapy');
  ok(await pj.locator('#panel-spravovat.open').count() === 1, 'detail tokenu zůstane otevřený (na širokém displeji si nepřekáží)');
  ok(await postavaVRamci() === '2', 'otevřel se deník té postavy, na jejímž tokenu se kliklo');
  { // okno plave přes mapu: zúžit ho zprava tak, aby nezakrývalo tokeny (pravý panel leží nad oknem a hráči se tam nepřekrývají)
    const minX = Math.min(...await pj.$$eval('.vtt-token .puck', els => els.map(e => e.getBoundingClientRect().left)));
    const h = await pj.locator('#denikDock').boundingBox();
    const pres = h.x + h.width - (minX - 20);
    if (pres > 0) { const e = await pj.locator('.denik-hrana[data-hrana="e"]').boundingBox(); await pj.mouse.move(e.x + 5, e.y + 200); await pj.mouse.down(); await pj.mouse.move(e.x + 5 - pres, e.y + 200, { steps: 4 }); await pj.mouse.up(); }
    console.log('   okno po zúžení: ' + JSON.stringify(await pj.locator('#denikDock').boundingBox()) + ', tokeny začínají na x=' + Math.round(minX));
  }
  ok((await pj.$$eval('#denikPostava option', o => o.length)) === 4, 'PJ vidí v nabídce všechny postavy světa (4)');
  ok(/denik\.php\?id=\d+&embed=1/.test(await pj.$eval('#denikFrame', e => e.src)), 'v rámečku je deník v režimu „embed“');
  { const n = await frame().locator('.dn-wrap.dn-embed').count(); ok(n === 1, 'deník se načetl (count ' + n + ', src ' + (await pj.$eval('#denikFrame', e => e.src)) + ')'); }
  ok(await frame().locator('a:has-text("Upravit základní údaje")').count() === 0, 'v rámečku nejsou odkazy, které by z mapy odvedly pryč');
  // sledování tokenu
  await klik(tSindor);
  console.log('   pozice pucků: ' + await polohy() + ' panel: ' + JSON.stringify(await pj.locator('#denikDock').boundingBox()) + ' viewport: ' + JSON.stringify(await pj.locator('#viewport').boundingBox()));
  ok(await postavaVRamci() === '2', 'klik na token Sindora přepne deník na Sindora');
  ok(/Sindor/.test(await frame().locator('.dn-jmeno').textContent()), 'v rámečku je Sindorův deník');
  await klik(tHrun);
  ok(await postavaVRamci() === '1', 'klik na token Hruna přepne deník na Hruna');
  await klik(tGob);
  ok(await postavaVRamci() === '1', 'klik na nestvůru deník nepřepne (nestvůra deník postavy nemá)');
  // pevně
  await pj.selectOption('#denikSledovat', 'nic');
  await klik(tSindor);
  ok(await postavaVRamci() === '1', 'v režimu „pevně“ klik na jiný token deník nepřepne');
  // výslovné tlačítko v detailu tokenu přebije i „pevně“
  await klik(tHrun);
  await pj.click('#spravovatDenik'); await pj.waitForTimeout(1500);
  ok(await postavaVRamci() === '1', 'tlačítko v detailu tokenu přepne deník i v režimu „pevně“');
  await pj.selectOption('#denikSledovat', 'vyber');
  await pj.selectOption('#denikPostava', '2'); await pj.waitForTimeout(1500);
  ok(await pj.$eval('#denikSledovat', e => e.value) === 'nic', 'ruční volba postavy přepne sledování na „pevně“');
  ok(/Sindor/.test(await frame().locator('.dn-jmeno').textContent()), 'ruční volba postavy načte její deník');

  // úpravy přímo v panelu: životy
  await frame().locator('[data-akce="hp"][data-delta="-1"]').first().click(); await pj.waitForTimeout(1800);
  ok(sqlVal('select aktualni_hp from postavy where id=2') === '4', 'životy z deníku v panelu se zapsaly');
  const hpNaTokenu = await pj.$eval('.vtt-token[data-id="' + tSindor + '"] .hp', e => e.textContent);
  ok(/4/.test(hpNaTokenu), 'a hned jsou vidět na tokenu na mapě: ' + hpNaTokenu.trim());
  // výbava: přidat z katalogu a vzít do ruky
  await frame().locator('[data-tab="inventar"]').click(); await pj.waitForTimeout(300);
  await frame().locator('.dn-picker[data-typy="predmet,lektvar"] .dn-picker-q').fill('Cep'); await pj.waitForTimeout(1500);
  await frame().locator('.dn-picker[data-typy="predmet,lektvar"] .dn-picker-vysledky li:has(span:text-is("Cep"))').first().click(); await pj.waitForTimeout(1800);
  ok(sqlVal("select count(*) from postava_predmety pp join predmety p on p.id=pp.predmet_id where pp.postava_id=2 and p.nazev='Cep'") === '1', 'Cep přidaný z katalogu v panelu je v inventáři');
  const cepId = sqlVal("select id from predmety where nazev='Cep' and typ='zbran'");
  await frame().locator('[data-tab="postava"]').click(); await pj.waitForTimeout(300);
  await frame().locator('button[data-vytasit="' + cepId + '"]').click(); await pj.waitForTimeout(1800);
  ok(sqlVal('select drzena_zbran_id from postavy where id=2') === cepId, 'zbraň v ruce nastavená v panelu se uložila');
  // zásah z mapy se projeví v deníku
  sql("update postavy set aktualni_hp=3 where id=2");
  await api(pj, 'hp_uprava', { typ_entity: 'postava', entita_id: 2, delta: -1, mapa_id: MAPA });
  await pj.waitForTimeout(4500);
  const hpVDeniku = await frame().locator('.dn-cell[aria-label="Životy"] .dn-metr-cislo strong').textContent();
  ok(hpVDeniku.trim() === '2', 'změna životů na mapě se objeví v deníku v panelu: ' + hpVDeniku.trim());
  // plovoucí okno: poloha, přesun, změna velikosti, sbalení, celá obrazovka
  const bbox = () => pj.locator('#denikDock').boundingBox();
  const vp = await pj.locator('#viewport').boundingBox();
  ok(Math.round(vp.x) === 0 && Math.round(vp.width) === 1400, 'mapa se otevřením deníku nezužuje (okno plave přes ni): ' + JSON.stringify([vp.x, vp.width]));
  let b0 = await bbox();
  ok(b0.x > 60 && b0.x < 200 && b0.width >= 340, 'okno vzniklo vedle levé lišty: ' + JSON.stringify([b0.x, b0.width]));
  { const sh = await pj.locator('.denik-hrana[data-hrana="s"]').boundingBox(); await pj.mouse.move(sh.x + 100, sh.y + 5); await pj.mouse.down(); await pj.mouse.move(sh.x + 100, sh.y + 5 - 300, { steps: 5 }); await pj.mouse.up(); }
  b0 = await bbox();
  ok(Math.abs(b0.height - 576) <= 14, 'dolní okraj zmenší výšku okna: ' + Math.round(b0.height));
  await pj.mouse.move(b0.x + 60, b0.y + 18); await pj.mouse.down(); await pj.mouse.move(b0.x + 560, b0.y + 118, { steps: 6 }); await pj.mouse.up();
  let b1 = await bbox();
  ok(Math.abs(b1.x - b0.x - 500) <= 2 && Math.abs(b1.y - b0.y - 100) <= 2, 'tažením za hlavičku jde okno přesunout o (500, 100): ' + JSON.stringify([b1.x - b0.x, b1.y - b0.y]));
  await pj.mouse.move(b1.x + 60, b1.y + 18); await pj.mouse.down(); await pj.mouse.move(-1200, -400, { steps: 4 }); await pj.mouse.up();
  let bz = await bbox();
  ok(bz.y >= 0 && bz.x + bz.width >= 195, 'okno nejde vytáhnout z obrazovky úplně (aspoň 200 px s hlavičkou zůstane k chycení): ' + JSON.stringify([bz.x, bz.y, bz.width]));
  await pj.mouse.move(bz.x + bz.width - 170, bz.y + 18); await pj.mouse.down(); await pj.mouse.move(bz.x + bz.width - 170 + 400, 60, { steps: 4 }); await pj.mouse.up();
  const bs = await bbox();
  const roh = await pj.locator('.denik-hrana[data-hrana="se"]').boundingBox();
  await pj.mouse.move(roh.x + 9, roh.y + 9); await pj.mouse.down(); await pj.mouse.move(roh.x + 9 + 150, roh.y + 9 - 120, { steps: 6 }); await pj.mouse.up();
  const b2 = await bbox();
  ok(Math.abs(b2.width - bs.width - 150) <= 2 && Math.abs(b2.height - bs.height + 120) <= 2, 'rohem jde měnit šířku i výšku: ' + Math.round(bs.width) + 'x' + Math.round(bs.height) + ' → ' + Math.round(b2.width) + 'x' + Math.round(b2.height));
  const levaH = await pj.locator('.denik-hrana[data-hrana="w"]').boundingBox();
  await pj.mouse.move(levaH.x + 5, levaH.y + 200); await pj.mouse.down(); await pj.mouse.move(levaH.x + 5 - 100, levaH.y + 200, { steps: 4 }); await pj.mouse.up();
  const b3 = await bbox();
  ok(Math.abs(b3.width - b2.width - 100) <= 2 && Math.abs(b3.x - b2.x + 100) <= 2, 'levý okraj roztáhne okno doleva a pravý okraj zůstane: ' + JSON.stringify([b3.x - b2.x, b3.width - b2.width]));
  const minW = await (async () => { const h = await pj.locator('.denik-hrana[data-hrana="e"]').boundingBox(); await pj.mouse.move(h.x + 5, h.y + 100); await pj.mouse.down(); await pj.mouse.move(h.x - 800, h.y + 100, { steps: 4 }); await pj.mouse.up(); return (await bbox()).width; })();
  ok(Math.round(minW) === 340, 'okno jde zúžit nejvýš na 340 px: ' + Math.round(minW));
  // sbalení
  await pj.click('#denikSbalit'); await pj.waitForTimeout(300);
  const bsb = await bbox();
  ok(bsb.height < 60 && !(await pj.locator('#denikFrame').isVisible()), 'sbalené okno je jen hlavička: ' + Math.round(bsb.height));
  await pj.click('#denikSbalit'); await pj.waitForTimeout(500);
  ok((await bbox()).height > 200 && await pj.locator('#denikFrame').isVisible(), 'rozbalení vrátí obsah i výšku');
  // celá obrazovka
  const predCela = await bbox();
  await pj.click('#denikCela'); await pj.waitForTimeout(500);
  const cela = await bbox();
  ok(Math.round(cela.x) === 0 && Math.round(cela.y) === 0 && Math.round(cela.width) === 1400 && Math.round(cela.height) === 900, 'celá obrazovka pokryje celé okno prohlížeče: ' + JSON.stringify([cela.width, cela.height]));
  ok(await pj.evaluate(() => { const r = document.querySelector('.toolbar').getBoundingClientRect(); const el = document.elementFromPoint(r.x + r.width / 2, r.y + 30); return !!el && !!el.closest('#denikDock'); }), 'celá obrazovka je nad lištou nástrojů (overlay)');
  ok(await frame().locator('.dn-wrap.dn-embed').count() === 1, 'deník v režimu celé obrazovky zůstal načtený a nepřeskočil');
  await pj.reload(); await pj.waitForTimeout(2500);
  ok(Math.round((await bbox()).width) === 1400, 'celá obrazovka se po obnovení stránky pamatuje');
  await pj.click('#denikCela'); await pj.waitForTimeout(500);
  const zpet = await bbox();
  ok(Math.abs(zpet.x - predCela.x) <= 1 && Math.abs(zpet.width - predCela.width) <= 1 && Math.abs(zpet.height - predCela.height) <= 1, 'Zpět do okna vrátí poslední polohu a velikost: ' + JSON.stringify([zpet.x, zpet.width, zpet.height]));
  // stav se pamatuje
  const w1 = zpet.width, x1 = zpet.x;
  await pj.reload(); await pj.waitForTimeout(2200);
  ok(await pj.locator('#denikDock').isVisible() && await postavaVRamci() === '2', 'po obnovení stránky okno zůstává otevřené u stejné postavy');
  ok(Math.abs((await bbox()).width - w1) < 3 && Math.abs((await bbox()).x - x1) < 3, 'i poloha a velikost se zapamatovaly');
  // po zmenšení okna prohlížeče zůstane okno na obrazovce
  await pj.setViewportSize({ width: 700, height: 500 }); await pj.waitForTimeout(400);
  const mal = await bbox();
  ok(mal.x + mal.width > 85 && mal.x < 700 - 85 && mal.y + 44 <= 500 && mal.height <= 500, 'po zmenšení okna prohlížeče se okno vejde zpět: ' + JSON.stringify([mal.x, mal.y, mal.width, mal.height]));
  await pj.setViewportSize({ width: 1400, height: 900 }); await pj.waitForTimeout(300);
  // zavřít
  await pj.click('#denikZavrit'); await pj.waitForTimeout(500);
  ok(!(await pj.locator('#denikDock').isVisible()) && (await pj.$eval('#denikFrame', e => e.src)) === 'about:blank', 'zavření skryje panel a zastaví deník');
  await pj.reload(); await pj.waitForTimeout(1500);
  ok(!(await pj.locator('#denikDock').isVisible()), 'zavřený panel zůstane zavřený i po obnovení');

  // ===== hráč: jen své postavy =====
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const klikHr = async (page, id) => { const b = await page.$eval('.vtt-token[data-id="' + id + '"] .puck', e => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; }); await page.mouse.click(b.x, b.y); await page.waitForTimeout(900); };
  await klikHr(hr, tSindor);
  ok(await hr.locator('#spravovatDenik').isVisible(), 'hráč má na detailu svého tokenu tlačítko Deník');
  await hr.click('#spravovatDenik'); await hr.waitForTimeout(1800);
  const moznosti = await hr.$$eval('#denikPostava option', o => o.map(x => x.textContent));
  ok(moznosti.length === 1 && moznosti[0] === 'Sindor', 'hráč má v nabídce jen svou postavu: ' + JSON.stringify(moznosti));
  ok(/Sindor/.test(await hr.frameLocator('#denikFrame').locator('.dn-jmeno').textContent()), 'hráč vidí Sindorův deník v panelu');
  const cizi = await hr.evaluate(async () => (await fetch('denik.php?id=1&embed=1')).status);
  ok(cizi === 403, 'cizí deník server hráči nedá (403): ' + cizi);
  // hráč upraví výbavu v panelu
  await hr.frameLocator('#denikFrame').locator('[data-akce="hp"][data-delta="1"]').first().click(); await hr.waitForTimeout(1500);
  ok(sqlVal('select aktualni_hp from postavy where id=2') === '3', 'hráč upraví životy v panelu (2 → 3)');

  // ===== telefon: spodní list =====
  const tel = await login(browser, 'hrac@local', 'hrac1234', 420, 800);
  await klikHr(tel, tSindor);
  await tel.click('#spravovatDenik'); await tel.waitForTimeout(1500);
  ok(await tel.locator('#panel-spravovat.open').count() === 0, 'na telefonu se detail tokenu po otevření deníku schová');
  const bb = await tel.locator('#denikDock').boundingBox();
  ok(Math.round(bb.width) === 420 && Math.round(bb.y + bb.height) === 800 && bb.height > 400, 'na telefonu je deník spodní list přes celou šířku: ' + JSON.stringify([bb.width, bb.height]));
  await tel.click('#denikCela'); await tel.waitForTimeout(400);
  const bt = await tel.locator('#denikDock').boundingBox();
  ok(Math.round(bt.width) === 420 && Math.round(bt.height) === 800 && Math.round(bt.y) === 0, 'na telefonu jde deník i na celou obrazovku: ' + JSON.stringify([bt.width, bt.height]));
  await tel.click('#denikCela'); await tel.waitForTimeout(400);
  ok(Math.round((await tel.locator('#denikDock').boundingBox()).height) < 800, 'a zase zpět do spodního listu');

  // ===== samostatná stránka deníku funguje jako dřív =====
  const st = await pj.evaluate(async () => { const t = await (await fetch('denik.php?id=2')).text(); return { odkaz: t.includes('Upravit základní údaje'), embed: t.includes('dn-embed') }; });
  ok(st.odkaz && !st.embed, 'samostatná stránka deníku je beze změny (s odkazy, bez režimu embed)');

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("update postavy set aktualni_hp=5, max_hp=5, drzena_zbran_id=NULL where id in (1,2)");
  await browser.close();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
