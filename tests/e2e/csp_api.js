// Content-Security-Policy (includes/csp.php, csp-report.php): hlavička je na stránkách, nonce v hlavičce sedí k inline skriptům a je pokaždé jiný,
// žádná stránka nemá inline on* atribut, a příjem hlášení (csp-report.php) bere jen POST, drží limit velikosti a počtu a píše do logu druh „csp“.
// Spuštění po resetu dat: mysql dracak < ../fixture/seed.sql; node csp_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const LOG = ROOT + '/logs/aplikace.log';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
const radkyCsp = () => (fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)).filter(z => z.druh === 'csp') : []);
const uklid = () => {
  for (const f of fs.readdirSync(ROOT + '/logs')) if (/^csp-\d+\.cnt$/.test(f)) fs.unlinkSync(ROOT + '/logs/' + f);
  if (fs.existsSync(LOG)) fs.writeFileSync(LOG, fs.readFileSync(LOG, 'utf8').split('\n').filter(l => l && JSON.parse(l).druh !== 'csp').map(l => l + '\n').join(''));
};
(async () => {
  uklid();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  const get = async (url) => { const r = await page.request.get(BASE + url); return { s: r.status(), h: r.headers(), t: await r.text() }; };

  // --- hlavička a nonce na stránkách ---
  const stranky = ['/dashboard.php', '/admin.php', '/admin_log.php', '/admin_poznamky.php', '/admin_migrace.php', '/admin_nasazeni.php', '/admin_zaloha.php', '/editor.php', '/editor.php?tabulka=rasy&akce=edit&id=1',
    '/pravidla.php', '/hra/svety.php', '/hra/svet.php?id=1', '/hra/postava.php?id=2', '/hra/denik.php?id=2', '/hra/denik_tisk.php?id=2', '/hra/druzina.php?id=1', '/hra/cesta.php?id=1',
    '/hra/mapa.php?id=2', '/hra/svet_administrace.php?id=1'];
  for (const s of stranky) {
    const r = await get(s);
    const hl = r.h['content-security-policy-report-only'] || r.h['content-security-policy'] || '';
    const nonceHl = (hl.match(/'nonce-([^']+)'/) || [])[1];
    const skripty = [...r.t.matchAll(/<script\b([^>]*)>/gi)].map(m => m[1]);
    const inline = skripty.filter(a => !/\bsrc\s*=/.test(a) && !/type\s*=\s*["']application\/(json|ld\+json)/.test(a));
    const bezNonce = inline.filter(a => !new RegExp('nonce="' + (nonceHl || '§') + '"').test(a));
    const on = r.t.match(/<[a-z][^>]*\son(click|submit|change|load|error|input|keydown|keyup|focus|blur|mouse\w+)\s*=/i);
    ok(r.s === 200 && nonceHl && bezNonce.length === 0 && !on, s + ': hlavička s nonce, ' + inline.length + ' inline skriptů, všechny s nonce, bez on* atributů' + (on ? ' NALEZENO ' + on[0].slice(0, 80) : '') + (bezNonce.length ? ' BEZ NONCE ' + bezNonce[0].slice(0, 60) : ''));
  }
  const r1 = await get('/admin.php'), r2 = await get('/admin.php');
  const n = (r) => ((r.h['content-security-policy-report-only'] || r.h['content-security-policy'] || '').match(/'nonce-([^']+)'/) || [])[1];
  ok(n(r1) && n(r2) && n(r1) !== n(r2), 'nonce je v každém požadavku jiný');
  ok(/frame-ancestors/.test(r1.h['content-security-policy-report-only'] || '') || /frame-ancestors/.test(r1.h['content-security-policy'] || ''), 'hlavička zakazuje vkládání do cizích rámů (frame-ancestors)');

  // --- příjem hlášení ---
  const post = (data, ctype = 'application/csp-report') => fetch(BASE + '/csp-report.php', { method: 'POST', headers: { 'Content-Type': ctype }, body: data });
  let r = await fetch(BASE + '/csp-report.php');
  ok(r.status === 405, 'GET na csp-report.php: 405 (' + r.status + ')');
  const pred = radkyCsp().length;
  r = await post('není json');
  ok(r.status === 204 && radkyCsp().length === pred, 'nesmyslné tělo: 204 a nic se nezapíše');
  r = await post(JSON.stringify({ x: 1 }));
  ok(r.status === 204 && radkyCsp().length === pred, 'JSON bez „csp-report“: nic se nezapíše');
  r = await post(JSON.stringify({ 'csp-report': { 'violated-directive': 'script-src-elem', 'blocked-uri': 'inline', 'document-uri': BASE + '/test?token=tajne', 'source-file': BASE + '/x.js', 'line-number': 7 } }));
  const z = radkyCsp();
  ok(r.status === 204 && z.length === pred + 1 && z[z.length - 1].zprava.includes('script-src-elem') && z[z.length - 1].zprava.includes('inline'), 'platné hlášení se zapíše jako druh csp: ' + (z.length ? z[z.length - 1].zprava : ''));
  ok(z.length && !JSON.stringify(z[z.length - 1]).includes('tajne'), 'tajná hodnota v adrese se do logu nedostane');
  r = await post(JSON.stringify({ 'csp-report': { 'violated-directive': ['pole'], 'blocked-uri': { a: 1 }, 'line-number': [1] } }));
  ok(r.status === 204, 'pole a objekty místo textu server nezhroutí (' + r.status + ')');
  r = await post(JSON.stringify({ 'csp-report': { 'violated-directive': 'x'.repeat(6000) } }));
  ok(r.status === 204 && radkyCsp().every(q => q.zprava.length < 1500), 'obří hlášení se ořízne a nezapíše celé');
  // strop 60 za minutu: dalších 80 různých hlášení nesmí zaplnit log
  const pred2 = radkyCsp().length;
  for (let i = 0; i < 80; i++) await post(JSON.stringify({ 'csp-report': { 'violated-directive': 'script-src-elem', 'blocked-uri': 'https://cizi' + i + '.example/x.js' } }));
  const pridano = radkyCsp().length - pred2;
  ok(pridano > 0 && pridano <= 60, 'strop počtu hlášení za minutu drží: přidáno ' + pridano + ' z 80');
  uklid();
  ok(radkyCsp().length === 0, 'úklid testovacích záznamů');
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
