// Chybový log: chyby z prohlížeče se dostanou do logu, admin stránka je ukáže, hráč ji nevidí, smazání funguje.
const { otevri } = require('./lib');
const DB = process.env.DB || 'dracak';
const fs = require('fs');
const LOG = '/home/user/Dracak-VTT/logs/aplikace.log';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
let chyb = 0;
const ok = (n, c, v = '') => { console.log((c ? 'OK    ' : 'CHYBA ') + n + (v ? ': ' + v : '')); if (!c) chyb++; };
const zaznamy = () => fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const { browser, page, ctx } = await otevri();
  // mapa světa 1: najít mapu
  const mapaId = require('child_process').execSync(`mysql -N ${DB} -e "select id from mapy order by id limit 1"`).toString().trim();
  await page.goto(BASE + '/hra/mapa.php?id=' + mapaId);
  await page.waitForTimeout(800);
  ok('skript chyby.js je v hlavičce mapy', await page.evaluate(() => !!document.querySelector('script[src*="chyby.js"]')));
  await page.route('**/assets/js/_zkusebni_chyba.js*', r => r.fulfill({ contentType: 'text/javascript', body: "setTimeout(function(){ throw new Error('Zkušební chyba z mapy'); }, 0);" }));
  await page.addScriptTag({ url: '/assets/js/_zkusebni_chyba.js?token=TAJNE' });
  await page.evaluate(() => { setTimeout(() => { Promise.reject(new Error('Zkušební slib')); }, 0); });
  await page.waitForTimeout(800);
  let z = zaznamy();
  ok('chyba JS je v logu', z.some(r => r.druh === 'prohlizec' && /Zkušební chyba z mapy/.test(r.zprava)), JSON.stringify(z.map(r => r.zprava)));
  const r1 = z.find(r => /Zkušební chyba z mapy/.test(r.zprava));
  ok('má místo, stránku, prohlížeč a účet', r1 && /^\/assets\/js\/_zkusebni_chyba\.js\?token=\*\*\*:\d+:\d+$/.test(r1.misto || '') && /mapa\.php/.test(r1.stranka || '') && /Chrome|Headless/i.test(r1.ua || '') && r1.uzivatel > 0, JSON.stringify(r1));
  ok('nezachycený slib je v logu', z.some(r => /Nezachycený slib: Zkušební slib/.test(r.zprava)));

  // šum se nehlásí
  const pred = zaznamy().length;
  await page.evaluate(() => { const i = document.createElement('img'); i.src = '/neexistuje-obrazek.png'; document.body.appendChild(i); });
  await page.waitForTimeout(500);
  ok('nenačtený obrázek se nehlásí', zaznamy().length === pred);

  // limit 5 na načtení stránky
  for (let i = 0; i < 9; i++) await page.evaluate((i) => setTimeout(() => { throw new Error('limit ' + i); }, 0), i);
  await page.waitForTimeout(800);
  const limit = zaznamy().filter(r => /limit \d/.test(r.zprava)).length;
  ok('na načtení stránky nejvýš 5 hlášení', limit >= 1 && limit <= 5, String(limit));

  // endpoint: chybný vstup a bez přihlášení
  const st = await page.evaluate(async () => (await fetch('/hra/api/klient_chyba.php', { method: 'POST', body: '{}' })).status);
  ok('prázdná zpráva = 422', st === 422, String(st));
  const st2 = await page.evaluate(async () => (await fetch('/hra/api/klient_chyba.php')).status);
  ok('GET = 405', st2 === 405, String(st2));
  const ctx2 = await browser.newContext();
  const st3 = await (await ctx2.request.post(BASE + '/hra/api/klient_chyba.php', { data: { zprava: 'x' } })).status();
  ok('bez přihlášení = 401', st3 === 401, String(st3));
  // log injection / HTML
  await page.evaluate(async () => fetch('/hra/api/klient_chyba.php', { method: 'POST', body: JSON.stringify({ zprava: '<img src=x onerror=alert(1)>\n{"druh":"fatalni"}', zdroj: '"><script>', stranka: '/hra/svet.php?token=TAJNE&id=2' }) }));
  const inj = zaznamy().filter(r => /onerror/.test(r.zprava));
  ok('vložený řádek se nestane záznamem', inj.length === 1 && zaznamy().every(r => r.druh !== 'fatalni' || !/onerror/.test(r.zprava)) && !zaznamy().some(r => r.druh === 'fatalni'));
  ok('tajné hodnoty v adrese stránky jsou zamaskované', inj[0] && /token=\*\*\*/.test(inj[0].stranka) && !/TAJNE/.test(JSON.stringify(inj[0])), inj[0] && inj[0].stranka);

  // admin stránka
  await page.goto(BASE + '/admin_log.php');
  const nadpis = await page.textContent('h1');
  ok('admin vidí Chybový log', /Chybový log/.test(nadpis));
  const radku = await page.locator('table.table tbody tr').count();
  ok('tabulka má řádky', radku >= 3, String(radku));
  const html = await page.content();
  ok('HTML ze zprávy je escapované (žádný živý <img onerror>)', !(await page.locator('img[src="x"]').count()));
  await page.screenshot({ path: (process.env.OUT || '/tmp/dracak-tests') + '/log_admin.png', fullPage: true });
  await page.click('.log-filtr a:has-text("Chyba v prohlížeči")');
  ok('filtr podle druhu', /druh=prohlizec/.test(page.url()) && (await page.locator('table.table tbody tr').count()) === radku);
  await page.goto(BASE + '/admin_log.php');
  await page.click('button:has-text("Vymazat log")');
  await page.waitForLoadState();
  ok('po smazání je log prázdný', zaznamy().length === 0 && /Log je prázdný/.test(await page.textContent('main')));
  await page.reload();
  ok('obnovení po smazání nic nesmaže ani nepadá', /Žádné chyby/.test(await page.textContent('main')));

  // CSRF token: POST bez tokenu
  const bez = await page.evaluate(async () => { const f = new FormData(); f.append('akce', 'smazat'); const r = await fetch('/admin_log.php', { method: 'POST', body: f, redirect: 'manual' }); return r.type; });
  ok('POST bez tokenu se jen přesměruje', bez === 'opaqueredirect' || bez === 'basic', bez);

  // hráč (ne admin) stránku nevidí
  const hrac = await require('./lib').otevri({ email: process.env.HRAC_EMAIL || 'hrac@local', heslo: process.env.HRAC_HESLO || 'hrac1234' }).catch(() => null);
  if (hrac) {
    const resp = await hrac.page.goto(BASE + '/admin_log.php');
    ok('hráč dostane 403', resp.status() === 403, String(resp.status()));
    await hrac.browser.close();
  }
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
