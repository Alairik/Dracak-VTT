const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const DB = process.env.DB || 'dracak';
const BASE = (process.env.BASE || 'http://127.0.0.1:8099');
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
(async () => {
  sql("insert ignore into postava_predmety (postava_id,predmet_id,mnozstvi) values (2,90,20),(2,91,3),(2,92,5); insert ignore into postava_lektvary (postava_id,lektvar_id,mnozstvi) values (2,1,2)");
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const chybyJs = []; page.on('pageerror', (e) => chybyJs.push(e.message));
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/denik.php?id=2'); await page.waitForTimeout(600);
  const viditelne = () => page.$$eval('#dn-inv-tab tbody tr[data-inv-typ]', (r) => r.filter((x) => !x.hidden).map((x) => x.dataset.invNazev));
  ok(await page.locator('#dn-detail').isHidden(), 'detail se nezobrazuje, dokud nic nevybereš (žádná prázdná karta)');
  await page.click('[data-tab="inventar"]');
  const chipy = await page.$$eval('.dn-filtr-chip', (c) => c.map((x) => x.textContent.replace(/\s+/g, ' ').trim()));
  ok(chipy[0].startsWith('Vše') && chipy.some((c) => c.startsWith('Zbraně 2')) && chipy.some((c) => c.startsWith('Suroviny 3')) && chipy.some((c) => c.startsWith('Lektvary 1')), 'chipy se počty podle druhů: ' + chipy.join(' | '));
  ok(!chipy.some((c) => c.startsWith('Zbroje')), 'druh, který v inventáři není, chip nemá');
  const vse = await viditelne();
  ok(vse.length === 6, 'bez filtru je vidět všech 6 položek: ' + vse.join(', '));
  await page.click('[data-inv-filtr="zbran"]');
  ok(JSON.stringify((await viditelne()).sort()) === JSON.stringify(['Cep', 'Dýka']), 'filtr Zbraně: jen zbraně');
  await page.fill('#dn-inv-hledej', 'dyka');
  ok(JSON.stringify(await viditelne()) === JSON.stringify(['Dýka']), 'hledání „dyka“ bez diakritiky najde Dýku (spolu s filtrem Zbraně)');
  await page.fill('#dn-inv-hledej', 'sip');
  ok((await viditelne()).length === 0 && await page.locator('#dn-inv-nic').isVisible(), 'zbraň „šíp“ nenajde nic → hláška, že nic neodpovídá');
  await page.click('[data-inv-filtr=""]');
  ok((await viditelne()).length === 3 && (await viditelne()).every((n) => /Šíp/.test(n)), 'filtr Vše + hledání „sip“ → tři šípy');
  await page.fill('#dn-inv-hledej', '');
  ok((await viditelne()).length === 6, 'prázdné hledání vrací vše');
  // detail předmětu
  await page.click('#vybaveni [data-detail="predmet"][data-id="2"]');
  await page.waitForSelector('#dn-detail:not([hidden])');
  let d = (await page.locator('#dn-detail').innerText()).replace(/\s+/g, ' ');
  ok(/Dýka/.test(d) && /zbraň/.test(d) && /Váha 5 mn/.test(d) && /Cena 1 st/.test(d) && /Síla zbraně/.test(d), 'detail Dýky: název, druh, váha, cena (1 st), síla zbraně: ' + d.slice(0, 200));
  await page.click('#vybaveni [data-detail="predmet"][data-id="90"]');
  await page.waitForFunction(() => /Šíp\/šipka/.test(document.getElementById('dn-detail').innerText));
  d = (await page.locator('#dn-detail').innerText()).replace(/\s+/g, ' ');
  ok(/Šíp\/šipka/.test(d) && /Cena 2 st 5 md/.test(d), 'cena 0,25 zl = 2 st 5 md: ' + d.slice(0, 120));
  // zavření
  await page.click('[data-detail-zavrit]');
  ok(await page.locator('#dn-detail').isHidden(), 'křížek detail zavře');
  await page.click('#vybaveni [data-detail="predmet"][data-id="2"]'); await page.waitForSelector('#dn-detail:not([hidden])');
  await page.keyboard.press('Escape');
  ok(await page.locator('#dn-detail').isHidden(), 'Esc detail zavře');
  await page.click('#vybaveni [data-detail="predmet"][data-id="2"]'); await page.waitForSelector('#dn-detail:not([hidden])');
  await page.click('#vybaveni [data-detail="predmet"][data-id="2"]');
  ok(await page.locator('#dn-detail').isHidden(), 'druhé klepnutí na tutéž položku detail zavře');
  // lektvar
  await page.click('#vybaveni [data-detail="lektvar"][data-id="1"]'); await page.waitForSelector('#dn-detail:not([hidden])');
  d = (await page.locator('#dn-detail').innerText()).replace(/\s+/g, ' ');
  ok(/Antigravitační cloumák/.test(d) && /lektvar/.test(d) && /Efekty:/.test(d), 'detail lektvaru s efekty: ' + d.slice(0, 160));
  // přežije obnovu (kus +1 spustí překreslení fragmentu) i s filtrem
  await page.fill('#dn-inv-hledej', 'cep');
  await page.locator('tr[data-inv-nazev="Cep"] [data-inv="zmena"][data-delta="1"]').click();
  await page.waitForTimeout(1200);
  ok(JSON.stringify(await viditelne()) === JSON.stringify(['Cep']) && (await page.inputValue('#dn-inv-hledej')) === 'cep', 'po překreslení deníku filtr (hledání „cep“) zůstal');
  ok(await page.locator('#dn-detail').isVisible() && /Antigravitační/.test(await page.locator('#dn-detail').innerText()), 'po překreslení detail zůstal otevřený');
  sql("update postava_predmety set mnozstvi=1 where postava_id=2 and predmet_id=39");
  await page.fill('#dn-inv-hledej', ''); await page.click('[data-detail-zavrit]');
  // detail ze záložky Postava (tabulka Boj)
  await page.click('[data-tab="postava"]');
  await page.click('#boj [data-detail="predmet"][data-id="39"]'); await page.waitForSelector('#dn-detail:not([hidden])');
  d = (await page.locator('#dn-detail').innerText()).replace(/\s+/g, ' ');
  ok(/Cep/.test(d) && /Postih k iniciativě −2/.test(d) && /Třída těžká/.test(d), 'detail zbraně z tabulky Boj (i když je Inventář skrytý): ' + d.slice(0, 220));
  await page.screenshot({ path: 'inv_detail.png' });
  // mobil
  await page.setViewportSize({ width: 390, height: 800 });
  const box = await page.locator('#dn-detail').boundingBox();
  ok(box.width >= 370 && box.x <= 10, 'na telefonu je detail přes celou šířku: ' + JSON.stringify(box));
  await page.screenshot({ path: 'inv_detail_mobil.png' });
  ok(chybyJs.length === 0, 'žádné chyby JS: ' + chybyJs.join('; '));
  sql("delete from postava_predmety where postava_id=2 and predmet_id in (90,91,92); delete from postava_lektvary where postava_id=2 and lektvar_id=1");
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})();
