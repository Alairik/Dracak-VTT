const { otevri } = require('./lib');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
let chyb = 0;
const ok = (n, c, v = '') => { console.log((c ? 'OK    ' : 'CHYBA ') + n + (v ? ': ' + v : '')); if (!c) chyb++; };
(async () => {
  const { browser, page } = await otevri({ viewport: { width: 1280, height: 800 } });
  // menu: všechny admin stránky mají stejné položky a správnou aktivní
  const klice = { 'admin.php': 'Správa účtů', 'admin_migrace.php': 'Změny databáze', 'admin_nasazeni.php': 'Kontrola nasazení', 'admin_log.php': 'Chybový log', 'admin_zaloha.php': 'Záloha databáze' };
  for (const [url, nazev] of Object.entries(klice)) {
    await page.goto(BASE + '/' + url);
    const polozky = await page.locator('.sidebar .mock-item').allInnerTexts();
    const aktivni = await page.locator('.sidebar .mock-item.active').allInnerTexts();
    ok('menu na ' + url, polozky.length === 7 && aktivni.length === 1 && aktivni[0] === nazev, polozky.join(' | ') + ' → aktivní: ' + aktivni.join());
  }
  await page.goto(BASE + '/admin_zaloha.php');
  await page.screenshot({ path: (process.env.OUT || '/tmp/dracak-tests') + '/zaloha_page.png', fullPage: true });
  const [stazeni] = await Promise.all([page.waitForEvent('download'), page.click('button:has-text("Stáhnout zálohu")')]);
  const nazev = stazeni.suggestedFilename();
  ok('název souboru', /^dracak-zaloha-\d{4}-\d{2}-\d{2}-\d{4}\.sql$/.test(nazev), nazev);
  const cesta = '/tmp/zaloha_stazena.sql';
  await stazeni.saveAs(cesta);
  const text = fs.readFileSync(cesta, 'utf8');
  ok('soubor končí řádkem „Konec zálohy“', /-- Konec zálohy: \d+ tabulek, \d+ řádků\.\n$/.test(text), text.slice(-80).trim());
  ok('není v něm DROP ani žádná CHYBA', !/DROP |-- CHYBA/.test(text));
  ok('obsahuje účty', /CREATE TABLE IF NOT EXISTS `ucty`/.test(text) && /INSERT INTO `ucty`/.test(text));
  ok('binární sloupce (mlha) jsou hexadecimálně, pokud nějaké řádky jsou', !/INSERT INTO `mlha_valky`/.test(text) || /INSERT INTO `mlha_valky`[\s\S]*?0x[0-9a-f]{20}/.test(text));
  // POST bez tokenu a s cizím tokenem
  const bez = await page.evaluate(async () => { const f = new URLSearchParams({ akce: 'stahnout' }); const r = await fetch('/admin_zaloha.php', { method: 'POST', body: f }); return r.status; });
  ok('POST bez tokenu = 403', bez === 403, String(bez));
  const spatny = await page.evaluate(async () => { const f = new URLSearchParams({ akce: 'stahnout', token: 'a'.repeat(64) }); const r = await fetch('/admin_zaloha.php', { method: 'POST', body: f }); return r.status; });
  ok('POST s cizím tokenem = 403', spatny === 403, String(spatny));
  const get = await page.evaluate(async () => { const r = await fetch('/admin_zaloha.php?akce=stahnout'); return (r.headers.get('content-type') || ''); });
  ok('GET nestahuje (jen stránka)', /text\/html/.test(get), get);
  // hráč
  const hr = await otevri({ email: 'hrac@local', heslo: 'hrac1234' });
  const r = await hr.page.goto(BASE + '/admin_zaloha.php');
  ok('hráč dostane 403', r.status() === 403, String(r.status()));
  const rp = await hr.page.evaluate(async () => (await fetch('/admin_zaloha.php', { method: 'POST', body: new URLSearchParams({ akce: 'stahnout', token: 'x' }) })).status);
  ok('hráč POST = 403', rp === 403, String(rp));
  await hr.browser.close();
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
