// Uložený XSS: do všech uživatelských textů (název světa, jména postav a účtů, poznámky, shrnutí, popisky tokenů a pinů, text kresby)
// se uloží útočný kód a pak se otevřou stránky, které ty texty vypisují. Nic se nesmí spustit (window.__xss, dialog, injektovaný prvek).
// Spuštění po resetu dat: mysql dracak < ../fixture/seed.sql; node xss_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
const P1 = '"><img src=x onerror="window.__xss=1">';
const P2 = '<svg onload=window.__xss=1>';
const P3 = "'</script><script>window.__xss=1</script>";
const SMES = P1 + P2 + P3;
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const page = await ctx.newPage();
  const dialogy = [];
  page.on('dialog', d => { dialogy.push(d.message()); d.dismiss(); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  const form = (url, f) => page.request.post(BASE + url, { form: f, maxRedirects: 0 });
  const json = (url, d) => page.request.post(BASE + url, { data: d });

  // --- uložení ---
  const heslo = 'Xss-testovaci-heslo-123';
  await form('/admin.php', { akce: 'vytvorit', email: 'xss-' + Date.now() + '@local', jmeno: SMES, heslo, role: 'hrac' });
  await form('/hra/svety.php', { nazev: 'Svět ' + SMES, popis: SMES, pocatecni_datum: '2026-01-01' });
  await form('/hra/postava.php?id=2', { nazev: 'Sindor ' + SMES, poznamky: SMES, rasa_id: '1', povolani_id: '1', uroven: '1', max_hp: '20', aktualni_hp: '20' });
  await form('/hra/svet.php?id=1', { akce: 'shrnuti', posledni_shrnuti: SMES, pripraveno_priste: SMES });
  await form('/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: SMES });
  await form('/hra/svet.php?id=1', { akce: 'nova_postava', nazev: 'Nová ' + SMES, max_hp: '10' });
  await json('/hra/api/token_pridat.php', { mapa_id: 2, typ_entity: 'obecny', nazev: SMES.slice(0, 95), x: 300, y: 300, barva: '#aa3300' });
  await json('/hra/api/mapa_bod_pridat.php', { mapa_id: 2, nazev: SMES, typ: 'mesto', x: 400, y: 400, popis: SMES, viditelny_hracum: true });
  await json('/hra/api/kresba_pridat.php', { mapa_id: 2, typ: 'text', body: [{ x: 500, y: 500 }], text: SMES, barva: '#000000', tloustka: 3 });
  await json('/hra/api/token_pridat.php', { mapa_id: 2, typ_entity: 'postava', postava_id: 2, x: 600, y: 600 });
  const svetyId = sqlVal("select id from svet where nazev like 'Svět <%' order by id desc limit 1");

  // --- čtení: stránky, které texty vypisují ---
  const stranky = [
    '/dashboard.php', '/admin.php', '/hra/svety.php', '/hra/svet.php?id=1', '/hra/svet.php?id=' + svetyId, '/hra/postava.php?id=2', '/hra/denik.php?id=2',
    '/hra/druzina.php?id=1', '/hra/cesta.php?id=1', '/hra/mapa.php?id=2', '/hra/svet_administrace.php?id=1', '/editor.php',
  ];
  for (const s of stranky) {
    await page.goto(BASE + s, { waitUntil: 'load' }).catch(() => {});
    await page.waitForTimeout(700);
    if (s.startsWith('/hra/mapa.php')) {
      // klik na token, pin a kresbu, ať se vykreslí jejich panely
      for (const sel of ['.token', '.mapa-bod-marker', '[data-token-id]']) {
        const el = page.locator(sel).first();
        if (await el.count()) { await el.click({ timeout: 1500 }).catch(() => {}); await page.waitForTimeout(300); }
      }
    }
    const x = await page.evaluate(() => ({ flag: window.__xss === 1, img: document.querySelectorAll('img[src="x"]').length, svg: document.querySelectorAll('svg[onload]').length, scr: [...document.querySelectorAll('script')].filter(s => /__xss=1/.test(s.textContent) && !/\\u003c|&lt;|\\"/.test(s.textContent) && s.textContent.indexOf('</script') === -1 && /^window\.__xss=1$/.test(s.textContent.trim())).length }));
    ok(!x.flag && x.img === 0 && x.svg === 0 && x.scr === 0 && dialogy.length === 0, s + ': nic se nespustilo ' + JSON.stringify(x));
  }
  // úklid
  sql("delete from ucty where email like 'xss-%'");
  sql("delete from svet where nazev like 'Svět <%'");
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
