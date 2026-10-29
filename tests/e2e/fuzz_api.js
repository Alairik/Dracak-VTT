// Náhodné vstupy do všech endpointů hra/api/*.php: názvy parametrů se vytáhnou z kódu, hodnoty jsou záměrně špatné
// (jiný typ, záporné, obří, null, pole, objekt, dlouhý text, diakritika). Server nesmí spadnout (5xx) ani zapsat chybu do logu;
// 4xx s hláškou je správná odpověď. Jako admin/PJ, jako hráč a jako nepřihlášený.
//   SEED=1 KOLIK=20 node fuzz_api.js            (KOLIK = náhodných požadavků na endpoint a roli)
// Před spuštěním vrať data do výchozího stavu: mysql dracak < ../fixture/seed.sql
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const LOG = ROOT + '/logs/aplikace.log';
const SEED = parseInt(process.env.SEED || '1', 10);
const KOLIK = parseInt(process.env.KOLIK || '20', 10);
const JEN = process.env.JEN ? process.env.JEN.split(',') : null;
// Endpointy, které se nefuzzují: nahrávání souborů (multipart) a klient_chyba (jeho účelem je záznam do logu).
const VYNECHAT = new Set(['obrazek_nahrat', 'klient_chyba']);   // klient_chyba z principu zapisuje do logu
function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
const R = rng(SEED);
const pick = (a) => a[Math.floor(R() * a.length)];

function klice(zdroj) {
  const k = new Set();
  for (const m of zdroj.matchAll(/\$(?:data|body|vstup|in)\[['"]([a-z_0-9]+)['"]\]/g)) k.add(m[1]);
  for (const m of zdroj.matchAll(/array_key_exists\(['"]([a-z_0-9]+)['"],\s*\$(?:data|body)/g)) k.add(m[1]);
  for (const m of zdroj.matchAll(/\$_(?:GET|POST|REQUEST)\[['"]([a-z_0-9]+)['"]\]/g)) k.add(m[1]);
  return [...k];
}
const platneId = { mapa_id: 2, svet_id: 1, postava_id: 2, token_id: 1, entita_id: 2, efekt_id: 5, kouzlo_id: 10, predmet_id: 2, zbran_id: 2, polozka_id: 17, vyzva_id: 1, nestvura_id: 41, lektvar_id: 1, kontejner_id: 1, zed_id: 1, kresba_id: 1, bod_id: 1, obrazek_id: 1, cil_token_id: 2, cil_entita_id: 1, aktivni_efekt_id: 1 };
const textove = ['', 'a', 'blbost', 'ěščřžýáíé', "'; DROP TABLE ucty; --", '<script>alert(1)</script>', 'x'.repeat(3000), '0', '-1', '1e9', 'null', 'true'];
function hodnota(klic) {
  const r = R();
  if (r < 0.35 && platneId[klic] !== undefined) return platneId[klic];
  if (r < 0.45) return null;
  if (r < 0.52) return [];
  if (r < 0.57) return {};
  if (r < 0.62) return [1, 'a', null];
  if (r < 0.67) return { a: 1 };
  if (r < 0.72) return true;
  if (r < 0.77) return 99999999999999;
  if (r < 0.82) return -1;
  if (r < 0.86) return 0;
  if (r < 0.90) return 3.14;
  if (/_id$|^id$|hod|pocet|delta|zmena|kol|smer|^x$|^y$|col|row|zaklad|modifikator|nasobek/.test(klic) && r < 0.97) return Math.floor(R() * 40) - 3;
  return pick(textove);
}
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.accept());
  if (email) {
    await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  }
  await page.goto(BASE + '/hra/mapa.php?id=2'); await page.waitForTimeout(300);
  return page;
}
const poslat = (page, endpoint, metoda, body, query) => page.evaluate(async ([e, m, b, q]) => {
  const url = '/hra/api/' + e + '.php' + (q ? '?' + q : '');
  try {
    const r = await fetch(url, m === 'GET' ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
    const t = await r.text();
    return { s: r.status, t: t.slice(0, 160) };
  } catch (err) { return { s: -1, t: String(err) }; }
}, [endpoint, metoda, body, query]);

(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const endpointy = fs.readdirSync(ROOT + '/hra/api').filter(f => f.endsWith('.php')).map(f => f.slice(0, -4)).filter(e => !VYNECHAT.has(e) && (!JEN || JEN.includes(e))).sort();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const role = [['pj', await login(browser, 'test@local', 'test1234')], ['hrac', await login(browser, 'hrac@local', 'hrac1234')], ['anonym', await login(browser, null)]];
  const pady = [];
  let celkem = 0;
  for (const e of endpointy) {
    const zdroj = fs.readFileSync(`${ROOT}/hra/api/${e}.php`, 'utf8');
    const k = klice(zdroj);
    const jeGet = /\$_GET\[/.test(zdroj);
    for (const [jmeno, page] of role) {
      for (let i = 0; i < KOLIK; i++) {
        const body = {};
        for (const kl of k) if (R() < 0.8) body[kl] = hodnota(kl);
        if (R() < 0.5 && body.mapa_id === undefined && /mapa_id/.test(zdroj)) body.mapa_id = 2;
        const get = jeGet && R() < 0.5;
        const query = get ? Object.entries(body).filter(([, v]) => v !== null && typeof v !== 'object').map(([a, v]) => encodeURIComponent(a) + '=' + encodeURIComponent(String(v))).join('&') : '';
        const o = await poslat(page, e, get ? 'GET' : 'POST', body, query);
        celkem++;
        if (o.s >= 500 || o.s === -1) pady.push({ e, jmeno, s: o.s, body: get ? query : JSON.stringify(body).slice(0, 300), odpoved: o.t });
      }
    }
    // po každém endpointu ukončit případný souboj, ať další endpointy nenarážejí na stejný stav
    await poslat(role[0][1], 'souboj_konec', 'POST', { mapa_id: 2 }, '');
  }
  await browser.close();
  console.log(`seed ${SEED}: ${endpointy.length} endpointů × 3 role × ${KOLIK} = ${celkem} požadavků`);
  const podleEndpointu = {};
  for (const p of pady) (podleEndpointu[p.e] = podleEndpointu[p.e] || []).push(p);
  console.log('endpointy s pádem (5xx): ' + Object.keys(podleEndpointu).length);
  for (const [e, v] of Object.entries(podleEndpointu)) { console.log(`  ${e}: ${v.length}×  např. [${v[0].jmeno}] ${v[0].body}  → ${v[0].s} ${v[0].odpoved}`); }
  const zaznamy = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  const sl = {};
  for (const z of zaznamy) { const k = z.druh + ' | ' + z.zprava.slice(0, 150) + ' | ' + (z.soubor || '') + ':' + (z.radek || ''); sl[k] = (sl[k] || 0) + 1; }
  console.log('záznamy v chybovém logu: ' + zaznamy.length);
  for (const [k, v] of Object.entries(sl).sort((a, b) => b[1] - a[1]).slice(0, 25)) console.log(`  ${v}× ${k}`);
  process.exit(pady.length || zaznamy.length ? 1 : 0);
})().catch(e => { console.error('PÁD fuzzeru', e); process.exit(2); });
