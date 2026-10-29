// Náhodné hodnoty ve formulářích (POST) všech stránek: názvy polí se vytáhnou z `$_POST['…']` v kódu stránky, hodnoty jsou záměrně
// špatné (jiný typ, záporné, obří, dlouhý text, diakritika, pole). Stránka nesmí skončit chybou serveru (5xx) ani zapsat chybu do logu.
// Jako admin/PJ, hráč a nepřihlášený. admin.php umí měnit a mazat účty, proto se před během uloží tabulka ucty a po běhu se vrátí.
//   SEED=1 KOLIK=10 node fuzz_formulare.js     (před spuštěním reset dat: mysql dracak < ../fixture/seed.sql)
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const LOG = ROOT + '/logs/aplikace.log';
const SEED = parseInt(process.env.SEED || '1', 10);
const KOLIK = parseInt(process.env.KOLIK || '10', 10);
// Nefuzzují se: jednorázové zakládání účtu a stahování zálohy (odpověď je soubor).
const VYNECHAT = new Set(['setup-admin.php', 'admin_zaloha.php']);
function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
const R = rng(SEED);
const pick = (a) => a[Math.floor(R() * a.length)];
const textove = ['', 'a', 'blbost', 'ěščřžýáíé', "'; DROP TABLE ucty; --", '<script>alert(1)</script>', 'x'.repeat(3000), '0', '-1', '1e9', 'null', 'true', '99999999999999', '2026-02-30', '0000-00-00', 'hex', 'ctverec', 'admin', 'pj', 'hrac'];
const znamaId = { id: '2', mapa_id: '2', svet_id: '1', postava_id: '2', rasa_id: '1', povolani_id: '1', vlastnik_ucet_id: '3', ucet_id: '3', polozka_id: '2', novy_vlastnik_id: '3', poznamka_id: '1', doprava_zvire_id: '1' };
const hodnota = (pole) => (R() < 0.3 && znamaId[pole] !== undefined) ? znamaId[pole] : pick(textove);
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  if (email) {
    await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }
  return page;
}
(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const zaloha = '/tmp/dracak_ucty_zaloha.sql';
  execSync(`mysqldump --default-character-set=utf8mb4 --no-create-info --replace --skip-comments ${DB} ucty > ${zaloha}`);
  const maxTexty = execSync(`mysql -N ${DB} -e "select ifnull(max(id),0) from pravidla_texty"`).toString().trim();
  const stranky = [...fs.readdirSync(ROOT).filter(f => f.endsWith('.php')), ...fs.readdirSync(ROOT + '/hra').filter(f => f.endsWith('.php')).map(f => 'hra/' + f)]
    .filter(f => !VYNECHAT.has(f.split('/').pop())).filter(f => /\$_POST\[/.test(fs.readFileSync(`${ROOT}/${f}`, 'utf8'))).sort();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const role = [['hrac', await login(browser, 'hrac@local', 'hrac1234')], ['anonym', await login(browser, null)], ['pj', await login(browser, 'test@local', 'test1234')]];
  const pady = [];
  let celkem = 0;
  for (const s of stranky) {
    const zdroj = fs.readFileSync(`${ROOT}/${s}`, 'utf8');
    const pole = [...new Set([...zdroj.matchAll(/\$_POST\[['"]([a-z_0-9]+)['"]\]/g)].map(m => m[1]))];
    for (const [jmeno, page] of role) {
      for (let i = 0; i < KOLIK; i++) {
        const form = {};
        for (const p of pole) if (R() < 0.85) form[p] = hodnota(p);
        const q = ['id', 'mapa_id', 'svet_id'].filter(k => /\$_GET\[/.test(zdroj) && R() < 0.5).map(k => k + '=' + (znamaId[k] || '1')).join('&');
        const url = BASE + '/' + s + (q ? '?' + q : '');
        let kod = -1;
        try { const r = await page.request.post(url, { form, maxRedirects: 0, timeout: 40000 }); kod = r.status(); } catch (e) { kod = -1; }
        celkem++;
        if (kod >= 500 || kod === -1) pady.push({ s, jmeno, kod, form: JSON.stringify(form).slice(0, 260) });
      }
    }
  }
  await browser.close();
  // úklid po admin.php a pravidla-text-save.php
  execSync(`mysql ${DB} < ${zaloha}`);
  sql(`delete from pravidla_texty where id > ${maxTexty}`);
  console.log(`seed ${SEED}: ${stranky.length} stránek s formulářem × 3 role × ${KOLIK} = ${celkem} požadavků (${stranky.join(', ')})`);
  console.log('stránky s pádem (5xx): ' + new Set(pady.map(p => p.s)).size);
  const videno = new Set();
  for (const p of pady) { if (videno.has(p.s)) continue; videno.add(p.s); console.log(`  ${p.s} [${p.jmeno}] ${p.kod}  ${p.form}`); }
  const zaznamy = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  const sl = {};
  for (const z of zaznamy) { const k = z.druh + ' | ' + z.zprava.slice(0, 150) + ' | ' + (z.soubor || '') + ':' + (z.radek || ''); sl[k] = (sl[k] || 0) + 1; }
  console.log('záznamy v chybovém logu: ' + zaznamy.length);
  for (const [k, v] of Object.entries(sl).sort((a, b) => b[1] - a[1]).slice(0, 25)) console.log(`  ${v}× ${k}`);
  process.exit(pady.length || zaznamy.length ? 1 : 0);
})().catch(e => { console.error('PÁD fuzzeru', e); process.exit(2); });
