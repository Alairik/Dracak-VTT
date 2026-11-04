// Stránka Změny databáze ukazuje, co aplikace v databázi opravdu vidí: název databáze a sloupce z ručního SQL (✓ / ✗ chybí). Po resetu dat: node admin_migrace_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const sql = (q) => require('child_process').execSync(`mysql ${DB} -e "${q}"`);
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const p = await (await browser.newContext()).newPage();
  p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
  await p.goto(BASE + '/index.php'); await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(BASE + '/admin_migrace.php');
  const text = async (sloupec) => (await p.locator(`[data-sloupec="${sloupec}"]`).textContent()).trim();
  ok(await p.locator('#rucniSqlStav').count() === 1, 'na stránce je přehled sloupců z ručního SQL');
  ok((await p.locator('#rucniSqlStav code').first().textContent()).trim() === DB, 'ukazuje název databáze: ' + DB);
  ok(await text('aktivni_efekty.akci_celkem') === '✓', 'sloupec akci_celkem je v databázi (✓)');
  sql('alter table aktivni_efekty drop column akci_hotovo');
  await p.reload();
  ok((await text('aktivni_efekty.akci_hotovo')).startsWith('✗'), 'po smazání sloupce stránka ukáže „✗ chybí“');
  sql('alter table aktivni_efekty add column akci_hotovo tinyint unsigned null');
  await p.reload();
  ok(await text('aktivni_efekty.akci_hotovo') === '✓', 'po vrácení sloupce je zase ✓');
  ok(p.chybyJs.length === 0, 'žádná chyba JavaScriptu');
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
