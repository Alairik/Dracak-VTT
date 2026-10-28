const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, w = 1400, h = 1000) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h } });
  const page = await ctx.newPage();
  page.on('dialog', d => d.accept());
  page.on('pageerror', e => { chyb++; console.log('CHYBA pageerror ' + e.message); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return page;
}
const txt = (page, sel) => page.$eval(sel, e => e.textContent.replace(/\s+/g, ' ').trim());
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  sql("update postavy set aktualni_hp=5, max_hp=5, unava=0, drzena_zbran_id=NULL where id=2; update postavy set unava=21, drzena_zbran_id=NULL where id=1");
  const p = await login(browser);
  await p.goto(BASE + '/hra/denik.php?id=2'); await p.waitForTimeout(800);

  // ===== záhlaví =====
  ok(/Sindor/.test(await txt(p, '.dn-jmeno')) && /Elf · Kouzelník · 1\. úroveň/.test(await txt(p, '.dn-pod')), 'záhlaví: jméno a „rasa · povolání · úroveň“');
  const attrs = await p.$$eval('.dn-attr', els => els.map(e => e.textContent.replace(/\s+/g, ' ').trim()));
  ok(attrs.length === 5 && /Sil 8 −1/.test(attrs[0]) && /Obr 12 0/.test(attrs[1]) && /Int 18 \+3/.test(attrs[3]) && /Cha 17 \+3/.test(attrs[4]), 'záhlaví: pět vlastností se stupněm a bonusem: ' + JSON.stringify(attrs));
  ok(/objekty 18 %/.test(await txt(p, '.dn-postreh')) && /mechanismy 14 %/.test(await txt(p, '.dn-postreh')), 'záhlaví: postřeh na objekty 18 % a mechanismy 14 %');
  ok(/OČ 1/.test(await txt(p, '.dn-bojcisla .dn-velke')) && /kvalita zbroje 1/.test(await p.$eval('.dn-bojcisla .dn-velke', e => e.title)), 'záhlaví: OČ 1 s rozpisem ve vysvětlení');
  ok(await p.locator('[data-dalsi-zbran]').count() === 1 && /—/.test(await txt(p, '[data-dalsi-zbran]')) && /vyber zbraň/.test(await txt(p, '[data-dalsi-zbran]')), 'záhlaví: bez aktivní zbraně je ÚČ „— vyber zbraň“ a jde klepnout');
  const cells = await p.$$eval('.dn-cell', els => els.map(e => e.getAttribute('aria-label')));
  ok(JSON.stringify(cells) === JSON.stringify(['Životy', 'Únava', 'Naložení']), 'měřidla: Životy, Únava, Naložení (Pohyblivost přesunuta do karty, Magy jen u kouzelníka s magenergií): ' + JSON.stringify(cells));
  ok(await p.$eval('.dn-pas', e => getComputedStyle(e).position) === 'sticky', 'záhlaví je na širokém okně přilepené nahoře');
  await p.evaluate(() => window.scrollTo(0, 700)); await p.waitForTimeout(400);
  ok(Math.round(await p.$eval('.dn-pas', e => e.getBoundingClientRect().top)) === 0, 'po posunu stránky zůstává záhlaví nahoře bez mezery');
  await p.evaluate(() => window.scrollTo(0, 0));
  ok(await p.locator('a[data-zavrit]').count() === 1, 'samostatná stránka má „Zavřít deník“');

  // ===== aktivní zbraň z hlavičky =====
  await p.click('[data-dalsi-zbran]'); await p.waitForTimeout(1500);
  ok(sqlVal('select drzena_zbran_id from postavy where id=2') === '39', 'klepnutí na ÚČ vybere první zbraň (Cep): ' + sqlVal('select drzena_zbran_id from postavy where id=2'));
  ok(/ÚČ 4/.test(await txt(p, '.dn-velke-btn')) && /Cep · na blízko/.test(await txt(p, '.dn-velke-btn')), 'ÚČ aktivního Cepu je 4: ' + await txt(p, '.dn-velke-btn'));
  await p.click('[data-dalsi-zbran]'); await p.waitForTimeout(1500);
  ok(sqlVal('select drzena_zbran_id from postavy where id=2') === '2' && /ÚČ 1/.test(await txt(p, '.dn-velke-btn')) && /Dýka/.test(await txt(p, '.dn-velke-btn')), 'druhé klepnutí přepne na Dýku (ÚČ 1)');

  // ===== postihy v číslech =====
  sql("update postavy set aktualni_hp=3, max_hp=12 where id=2");
  await p.reload(); await p.waitForTimeout(800);
  const oc = await txt(p, '.dn-bojcisla .dn-velke');
  ok(/OČ 1 −1/.test(oc), 'málo životů (odolnost 8 → −2): OČ −1, původní 1 přeškrtnuté: ' + oc);
  ok(/ÚČ 1 −1/.test(await txt(p, '.dn-velke-btn')), 'ÚČ Dýky 1 − 2 = −1 s přeškrtnutým původním číslem');
  ok(/postih −2 k ÚČ a OČ/.test(await txt(p, '.dn-cell[aria-label="Životy"]')), 'u životů je štítek s postihem');
  ok(/méně než 1\/3 životů −2/.test(await p.$eval('.dn-bojcisla .dn-velke', e => e.title)), 'rozpis OČ v tooltipu jmenuje postih za málo životů');
  sql("update postavy set aktualni_hp=2 where id=2");
  await p.reload(); await p.waitForTimeout(600);
  ok(/vyřazena z boje/.test(await txt(p, '.dn-cell[aria-label="Životy"]')), 'životy na mezi vyřazení (2 z 12, odolnost 8): štítek „vyřazena z boje“');
  sql("update postavy set aktualni_hp=5, max_hp=5 where id=2");

  // ===== záložky =====
  await p.reload(); await p.waitForTimeout(600);
  ok(await p.locator('[data-panel="postava"]').isVisible() && !(await p.locator('[data-panel="inventar"]').isVisible()), 'výchozí záložka je Postava, ostatní jsou skryté');
  await p.click('[data-tab="inventar"]'); await p.waitForTimeout(200);
  ok(await p.locator('[data-panel="inventar"]').isVisible() && !(await p.locator('[data-panel="postava"]').isVisible()) && await p.getAttribute('[data-tab="inventar"]', 'aria-selected') === 'true', 'klik na Inventář přepne panel');
  await p.reload(); await p.waitForTimeout(600);
  ok(await p.locator('[data-panel="inventar"]').isVisible(), 'aktivní záložka se pamatuje i po obnovení stránky');
  await p.click('[data-akce="hp"][data-delta="-1"]'); await p.waitForTimeout(1500);
  ok(sqlVal('select aktualni_hp from postavy where id=2') === '4' && /^4/.test(await txt(p, '.dn-cell[aria-label="Životy"] .dn-metr-cislo')) && await p.locator('[data-panel="inventar"]').isVisible(), 'životy −1 se zapíšou, překreslí a záložka zůstane Inventář');
  await p.click('[data-akce="hp"][data-delta="1"]'); await p.waitForTimeout(1200);
  ok(await p.locator('.dk-tab', { hasText: 'Kouzla' }).count() === 1 && await p.locator('.dk-tab', { hasText: 'Schopnosti' }).count() === 1, 'jsou záložky Kouzla (postava má kouzla) a Schopnosti');
  await p.click('[data-tab="postava"]');

  // ===== vysvětlení na klepnutí =====
  await p.click('.dn-attr >> nth=0'); await p.waitForTimeout(200);
  ok(await p.locator('#dn-info').isVisible() && /Síla 8, bonus −1/.test(await txt(p, '#dn-info')), 'klepnutí na vlastnost ukáže vysvětlení (použitelné i na dotyku): ' + (await p.locator('#dn-info').count() ? await txt(p, '#dn-info') : '—'));
  await p.click('.dn-jmeno'); await p.waitForTimeout(100);
  ok(await p.locator('#dn-info').count() === 0, 'klepnutí jinam vysvětlení zavře');
  await p.click('.dn-cell-i >> nth=0'); await p.waitForTimeout(200);
  ok(/Mez vyřazení/.test(await txt(p, '#dn-info')), 'tlačítko „i“ u životů ukáže mez vyřazení');
  await p.keyboard.press('Escape');

  // ===== karty záložky Postava =====
  const zeb = await p.$$eval('.dk-ladder tbody tr', rs => rs.map(r => ({ cur: r.classList.contains('cur'), t: r.textContent.replace(/\s+/g, ' ').trim() })));
  ok(zeb.length === 4 && zeb.filter(r => r.cur).length === 1 && zeb[0].cur, 'Pohyb a naložení: žebříček má 4 pásma a aktuální je zvýrazněné: ' + JSON.stringify(zeb.map(r => r.t)));
  await p.click('[data-hod="obrana"]'); await p.waitForTimeout(1200);
  ok(/Obrana/.test(await txt(p, '#dn-hlaska')), 'hod na obranu z karty Obrana funguje');
  ok(await p.locator('#boj table.dn-tab').count() === 1 && await p.locator('#boj button[data-vytasit], #boj .dn-vruce').count() === 2, 'karta Boj má tabulku zbraní s tlačítkem Vytasit / štítkem v ruce');

  // ===== únava (Hrun) =====
  await p.goto(BASE + '/hra/denik.php?id=1'); await p.waitForTimeout(800);
  const a1 = await p.$$eval('.dn-attr', els => els.map(e => ({ zm: e.classList.contains('zm'), t: e.textContent.replace(/\s+/g, ' ').trim() })));
  ok(/Sil 19 \+4 \+3/.test(a1[0].t) && a1[0].zm, 'únava: bonus Síly přeškrtnutý +4 → +3 a čip je zvýrazněný: ' + a1[0].t);
  ok(/unavená/.test(await txt(p, '.dn-cell[aria-label="Únava"]')), 'únava 21/15: štítek „unavená“');
  ok(await p.locator('[data-dalsi-zbran]').count() === 0 && /Dýka/.test(await txt(p, '.dn-bojcisla')), 'jediná zbraň je rovnou aktivní, ÚČ se nepřepíná');

  // ===== úzké okno (deník v okně na mapě) =====
  const n = await login(browser, 480, 860);
  await n.goto(BASE + '/hra/denik.php?id=2&embed=1'); await n.waitForTimeout(800);
  ok(await n.locator('a[data-zavrit]').count() === 0, 'v okně na mapě není „Zavřít deník“ (okno má vlastní ✕)');
  ok(await n.$eval('.dn-pas', e => getComputedStyle(e).position) === 'static', 'v úzkém okně se záhlaví odroluje s obsahem');
  ok(await n.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'v úzkém okně nepřetéká vodorovně: ' + await n.evaluate(() => document.documentElement.scrollWidth + '/' + document.documentElement.clientWidth));
  ok(await n.evaluate(() => [...document.querySelectorAll('.dn-cell')].every(c => c.scrollWidth <= c.clientWidth + 1)), 'měřidla se v úzkém okně vejdou');
  ok(await n.evaluate(() => window.dracakDenik && window.dracakDenik.ukazSekci('vybaveni') === true) && await n.locator('[data-panel="inventar"]').isVisible(), 'window.dracakDenik.ukazSekci(vybaveni) přepne záložku (pro skoky z mapy)');
  ok(await n.evaluate(() => window.dracakDenik.ukazSekci('neni-takova') === false), 'neznámá sekce: nic se nestane');

  sql("update postavy set aktualni_hp=5, max_hp=5, unava=0, drzena_zbran_id=NULL where id=2; update postavy set unava=21, drzena_zbran_id=NULL where id=1");
  await browser.close();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
