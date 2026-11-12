const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const SHOTS = (process.env.OUT || '/tmp/dracak-tests') + '/shots';
require('fs').mkdirSync(SHOTS, { recursive: true });
const errs = [];
async function login(browser, email, heslo, label, vp) {
  const ctx = await browser.newContext({ viewport: vp || { width: 1280, height: 860 } });
  const page = await ctx.newPage();
  page.on('pageerror', e => errs.push(label + ' PAGEERROR ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errs.push(label + ' CONSOLE ' + m.text()); });
  page.on('response', r => { if (r.status() >= 400) errs.push(label + ' HTTP ' + r.status() + ' ' + r.url().replace(BASE, '')); });
  page.on('dialog', d => { console.log(label, 'DIALOG', d.message()); d.accept(); });
  await page.goto(BASE + '/index.php');
  await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=1');
  await page.waitForTimeout(800);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  return r.json();
}, [name, body]);
const ok = (c, m) => console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m);
const txt = (page, sel) => page.$eval(sel, e => e.innerText).catch(() => '(nenalezeno)');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--use-gl=swiftshader', '--enable-webgl', '--ignore-gpu-blocklist'] });
  const pj = await login(browser, 'test@local', 'test1234', 'PJ');
  const hr = await login(browser, 'hrac@local', 'hrac1234', 'HRAC');
  await api(pj, 'souboj_konec', { mapa_id: 1 });
  const gob = [];
  for (let i = 0; i < 3; i++) { const d = await api(pj, 'token_pridat', { mapa_id: 1, typ_entity: 'nestvura', nestvura_id: 41, x: 900 + i * 70, y: 300 }); gob.push(d.token_id); }
  await pj.reload(); await hr.reload(); await pj.waitForTimeout(800); await hr.waitForTimeout(500);
  await pj.screenshot({ path: SHOTS + '/00_pred.png' });
  ok(await pj.$('#tb-souboj') !== null, 'tlačítko Zahájit souboj u PJ');
  ok(await hr.$('#tb-souboj') === null, 'hráč tlačítko nemá');

  // --- zahájení ---
  await pj.click('#tb-souboj');
  await pj.waitForSelector('#modalSouboj', { state: 'visible' });
  await pj.screenshot({ path: SHOTS + '/01_zahajeni.png' });
  const rad = await pj.$$eval('#soubojUcastnici .souboj-radek', r => r.map(x => x.innerText.trim().replace(/\s+/g, ' ')));
  console.log('účastníci v okně:', rad.join(' | '));

  // Rychlý výběr, kdo vstoupí do iniciativy (na mapě s hodně tvory se po jednom klikat nedá; kdo zaškrtnutý není,
  // do pořadí tahů nevstoupí a PJ ho řídí ručně). Rozhodnutí uživatele 2026-11-09.
  // Od 2026-11-12 (krok 0 nového průběhu) je seznam schovaný pod „Upravit, kdo bojuje…“ a nahoře je jen souhrn stran.
  ok(!(await pj.locator('#soubojUcastnici').isVisible()), 'seznam tvorů je při zahájení sbalený');
  await pj.click('#soubojUprava > summary'); await pj.waitForTimeout(150);
  const UCAST = '#soubojUcastnici input[type=checkbox]:not([data-zbran])';
  const zaskrtnuto = () => pj.locator(UCAST + ':checked').count();
  const vsech = await pj.locator(UCAST).count();
  ok((await txt(pj, '#soubojPocet')).includes('z ' + vsech), 'okno píše, kolik tvorů půjde do iniciativy: ' + (await txt(pj, '#soubojPocet')));
  await pj.click('[data-souboj-vyber="nic"]'); await pj.waitForTimeout(150);
  ok(await zaskrtnuto() === 0, '„Nikoho“ odškrtne všechny');
  await pj.click('[data-souboj-vyber="vse"]'); await pj.waitForTimeout(150);
  ok(await zaskrtnuto() === vsech, '„Vše“ zaškrtne všechny: ' + vsech);
  await pj.click('[data-souboj-vyber="druzina"]'); await pj.waitForTimeout(150);
  const jenDruzina = await zaskrtnuto();
  const druzinaRadku = await pj.locator('#soubojUcastnici .souboj-radek select option[value="druzina"]:checked').count();
  ok(jenDruzina > 0 && jenDruzina === druzinaRadku, '„Jen družina“ nechá zaškrtnuté jen tvory na straně družiny: ' + jenDruzina + ' z ' + vsech);
  ok((await txt(pj, '#soubojPocet')).includes('půjde ' + jenDruzina), 'počítadlo sedí po rychlém výběru: ' + (await txt(pj, '#soubojPocet')));
  await pj.click('[data-souboj-vyber="vse"]'); await pj.waitForTimeout(150);

  await pj.click('#soubojUlozit');
  await pj.waitForSelector('#raidPanel', { state: 'visible' });
  await pj.waitForTimeout(600);
  await pj.screenshot({ path: SHOTS + '/02_umysly_pj.png' });
  ok((await txt(pj, '#raidTitul')).includes('kolo 1'), 'panel: ' + (await txt(pj, '#raidTitul')));

  // hráč vidí jen Sindora
  await hr.waitForTimeout(2500);
  await hr.screenshot({ path: SHOTS + '/03_umysly_hrac.png' });
  const hrRad = await hr.$$eval('#raidSeznam .raid-box .raid-jmeno', r => r.map(x => x.innerText.trim().replace(/\s+/g, ' ')));
  ok(hrRad.length === 1 && hrRad[0].startsWith('Sindor'), 'hráč v panelu: ' + hrRad.join(' | '));
  ok(await hr.locator('#raidSeznam .raid-podtitul, #raidSeznam .raid-hp-text, #raidSeznam .raid-mag').count() === 0 && await hr.locator('#raidSeznam .raid-hp').count() === 1, 'hráč v panelu vidí jen pruh životů, bez čísel a dalších údajů');

  // úmysly: hráč kouzlení; PJ: gobliny útok (skupina)
  await hr.selectOption('#raidSeznam .ini-umysl select >> nth=0', 'kouzleni');
  await pj.waitForTimeout(300);
  const pjSel = await pj.$$('#raidSeznam .raid-box');
  console.log('PJ řádků v úmyslech:', pjSel.length);
  const gobRadek = pj.locator('#raidSeznam .raid-box').filter({ has: pj.locator('.raid-jmeno', { hasText: 'Goblin' }) }).filter({ has: pj.locator('select') });
  ok(await gobRadek.count() === 1, 'gobliny sloučeni do skupiny (ovládání má jen první): ' + (await gobRadek.first().innerText()).replace(/\s+/g, ' '));
  await gobRadek.locator('select').first().selectOption('utok');
  await pj.waitForTimeout(1800);
  await pj.screenshot({ path: SHOTS + '/04_umysly_pj_po.png' });

  // --- iniciativa ---
  await pj.click('text=Otevřít hody na iniciativu');
  await pj.waitForTimeout(2200);
  await hr.waitForTimeout(500);
  await hr.screenshot({ path: SHOTS + '/05_ini_hrac.png' });
  await hr.click('#raidSeznam button:has-text("Hodit")');
  await hr.waitForSelector('#modalHod', { state: 'visible' });
  await hr.screenshot({ path: SHOTS + '/06_hod_okno.png' });
  console.log('tlačítko hodu:', (await txt(hr, '#hodKostkaBtn')).trim());
  await hr.click('#hodKostkaBtn');
  await hr.waitForTimeout(1500);
  await hr.screenshot({ path: SHOTS + '/07_hod_leti.png' });
  await hr.waitForFunction(() => document.getElementById('modalHod').style.display === 'none', null, { timeout: 15000 }).catch(() => console.log('modál hodu se nezavřel'));
  console.log('výsledek hodu:', (await txt(hr, '#hodVysledek')).trim(), '| chyba:', (await txt(hr, '#hodChyba')).trim());
  await hr.waitForTimeout(500);
  await hr.screenshot({ path: SHOTS + '/08_po_hodu_hrac.png' });

  // PJ: gobliny 3D hod (skupinový), Hrun ručně
  await pj.waitForTimeout(2000);
  await pj.screenshot({ path: SHOTS + '/09_ini_pj.png' });
  await pj.locator('#raidSeznam .raid-box').filter({ has: pj.locator('.raid-jmeno', { hasText: 'Goblin' }) }).locator('button:has-text("Hodit")').first().click();
  await pj.waitForSelector('#modalHod', { state: 'visible' });
  console.log('nadpis hodu PJ:', (await txt(pj, '#hodNadpis')));
  await pj.click('#hodKostkaBtn');
  await pj.waitForFunction(() => document.getElementById('modalHod').style.display === 'none', null, { timeout: 15000 }).catch(() => console.log('modál PJ se nezavřel'));
  await pj.waitForTimeout(600);
  await pj.locator('#raidSeznam .raid-box').filter({ has: pj.locator('.raid-jmeno', { hasText: 'Hrun' }) }).locator('button:has-text("Hodit")').first().click();
  await pj.waitForSelector('#modalHod', { state: 'visible' });
  await pj.fill('#hodRucne', '4');
  await pj.click('#hodRucneBtn');
  await pj.waitForFunction(() => document.getElementById('modalHod').style.display === 'none', null, { timeout: 10000 }).catch(() => console.log('modál ručně se nezavřel'));
  await pj.waitForTimeout(700);
  await pj.screenshot({ path: SHOTS + '/10_ini_pj_po.png' });
  console.log('PJ řádky iniciativy:', (await pj.$$eval('#raidSeznam .raid-box', r => r.map(x => x.innerText.trim().replace(/\s+/g, ' ')))).join(' | '));

  // --- tahy ---
  await pj.click('text=Začít kolo');
  await pj.waitForTimeout(1800);
  await hr.waitForTimeout(1800);
  await pj.screenshot({ path: SHOTS + '/11_tahy_pj.png' });
  await hr.screenshot({ path: SHOTS + '/12_tahy_hrac.png' });
  console.log('PJ tahy:', (await pj.$$eval('#raidInfo > *', r => r.map(x => x.innerText.trim().replace(/\s+/g, ' ')))).join(' | '));
  console.log('Hráč tahy:', (await hr.$$eval('#raidInfo > *', r => r.map(x => x.innerText.trim().replace(/\s+/g, ' ')))).join(' | '));

  // proklikat celé kolo: vždy ten, kdo má na tahu ovládání
  for (let i = 0; i < 20; i++) {
    const fz = await txt(pj, '#raidFaze');
    if (!fz.includes('Tahy') || (await txt(pj, '#raidFaze')).includes('Tahy') === false) break;
    const t = await txt(pj, '#raidTitul');
    if (!t.includes('kolo 1')) break;
    const jenHrac = (await txt(hr, '#raidInfo .tah-info')).includes('Sindor');
    const strana = jenHrac ? hr : pj;
    if (!(await strana.$('.akce-lista'))) { console.log('žádné ovládání, konec smyčky', t); break; }
    await strana.click('.akce-btn:has-text("' + (jenHrac ? 'Kouzlení' : 'Čekání') + '")');   // Sindor slíbil kouzlení (h1622)
    await pj.waitForTimeout(1500);
  }
  await pj.waitForTimeout(1500);
  await pj.screenshot({ path: SHOTS + '/13_kolo2_pj.png' });
  ok((await txt(pj, '#raidTitul')).includes('kolo 2'), 'po kole: ' + (await txt(pj, '#raidTitul')) + ' / ' + (await txt(pj, '#raidFaze')).replace(/\s+/g, ' '));

  // konec
  await pj.click('#souborjKonecBtn');
  await pj.waitForTimeout(1500);
  ok((await txt(pj, '#raidFaze')).trim() === '' && (await txt(pj, '#raidTitul')) === 'Na mapě', 'po konci souboje zmizí fáze a nadpis se vrátí na „Na mapě“');
  ok(await pj.locator('#souborPj').isHidden(), 'tlačítka souboje (Účastníci, Ukončit) po konci souboje zmizí');
  const log = await pj.$$eval('#logList div', d => d.map(x => x.textContent));
  console.log('LOG:\n' + log.slice(-25).join('\n'));
  console.log(errs.length ? 'JS CHYBY:\n' + errs.join('\n') : 'bez JS chyb');
  for (const g of gob) await api(pj, 'token_smazat', { token_id: g, mapa_id: 1 });
  await browser.close();
})().catch(e => { console.error('SELHÁNÍ', e); process.exit(1); });
