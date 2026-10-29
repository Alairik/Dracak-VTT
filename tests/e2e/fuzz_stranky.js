// Náhodné parametry v adrese u všech stránek (kořen i hra/*.php): GET s id, mapa_id, svet_id… špatného typu nebo neexistujících,
// jako admin/PJ, hráč a nepřihlášený. Stránka nesmí skončit chybou serveru (5xx) ani zapsat chybu do logu.
//   SEED=1 KOLIK=12 node fuzz_stranky.js      (před spuštěním reset dat: mysql dracak < ../fixture/seed.sql)
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const LOG = ROOT + '/logs/aplikace.log';
const SEED = parseInt(process.env.SEED || '1', 10);
const KOLIK = parseInt(process.env.KOLIK || '12', 10);
// Nefuzzují se: odhlášení (zničí relaci), jednorázové zakládání účtu a přímé stahování souboru zálohy.
const VYNECHAT = new Set(['logout.php', 'setup-admin.php', 'admin_zaloha.php']);
function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
const R = rng(SEED);
const pick = (a) => a[Math.floor(R() * a.length)];
const hodnoty = ['1', '2', '3', '41', '0', '-1', '99999999999999', 'abc', '', '1e9', '1.5', 'null', '%00', "'", '"><script>alert(1)</script>', '../../etc/passwd', 'x'.repeat(2500)];
const nazvy = ['id', 'mapa_id', 'svet_id', 'postava_id', 'token_id', 'sekce', 'embed', 'fragment', 'tab', 'q', 'filtr', 'typ', 'strana', 'od', 'cesta', 'akce', 'zdroj', 'cil'];
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.dismiss());
  if (email) {
    await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }
  return page;
}
(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const stranky = [...fs.readdirSync(ROOT).filter(f => f.endsWith('.php')), ...fs.readdirSync(ROOT + '/hra').filter(f => f.endsWith('.php')).map(f => 'hra/' + f)].filter(f => !VYNECHAT.has(f.split('/').pop())).sort();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const role = [['pj', await login(browser, 'test@local', 'test1234')], ['hrac', await login(browser, 'hrac@local', 'hrac1234')], ['anonym', await login(browser, null)]];
  const pady = [];
  let celkem = 0;
  for (const s of stranky) {
    for (const [jmeno, page] of role) {
      for (let i = 0; i < KOLIK; i++) {
        const q = Array.from({ length: Math.floor(R() * 4) }, () => encodeURIComponent(pick(nazvy)) + '=' + encodeURIComponent(pick(hodnoty))).join('&');
        const url = BASE + '/' + s + (q ? '?' + q : '');
        let kod = -1;
        try { const r = await page.request.get(url, { maxRedirects: 0, timeout: 40000 }); kod = r.status(); } catch (e) { kod = -1; }
        celkem++;
        if (kod >= 500 || kod === -1) pady.push({ s, jmeno, kod, url: url.slice(BASE.length, BASE.length + 260) });
      }
    }
  }
  await browser.close();
  console.log(`seed ${SEED}: ${stranky.length} stránek × 3 role × ${KOLIK} = ${celkem} požadavků`);
  console.log('stránky s pádem (5xx): ' + new Set(pady.map(p => p.s)).size);
  const videno = new Set();
  for (const p of pady) { if (videno.has(p.s)) continue; videno.add(p.s); console.log(`  ${p.s} [${p.jmeno}] ${p.kod}  ${p.url}`); }
  const zaznamy = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  const sl = {};
  for (const z of zaznamy) { const k = z.druh + ' | ' + z.zprava.slice(0, 150) + ' | ' + (z.soubor || '') + ':' + (z.radek || ''); sl[k] = (sl[k] || 0) + 1; }
  console.log('záznamy v chybovém logu: ' + zaznamy.length);
  for (const [k, v] of Object.entries(sl).sort((a, b) => b[1] - a[1]).slice(0, 25)) console.log(`  ${v}× ${k}`);
  process.exit(pady.length || zaznamy.length ? 1 : 0);
})().catch(e => { console.error('PÁD fuzzeru', e); process.exit(2); });
