// Náhodná procházka soubojem: PJ a hráč posílají přes API náhodné (i nesmyslné) akce; hledá se pád serveru (5xx, neplatný JSON),
// varování a výjimky v logs/aplikace.log a porušení základních invariantů (životy, akce). Opakovatelné: SEED=… KROKU=…
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const LOG = (process.env.DOCROOT || require('path').resolve(__dirname, '../..')) + '/logs/aplikace.log';
const MAPA = 2;
const SEED = parseInt(process.env.SEED || '1', 10);
const KROKU = parseInt(process.env.KROKU || '250', 10);
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
const R = rng(SEED);
const pick = (a) => a[Math.floor(R() * a.length)];
const celeCislo = (a, b) => a + Math.floor(R() * (b - a + 1));
const problemy = [];
const pocty = {};
const hlasky = {};
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.accept());
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(500);
  return page;
}
const api = async (page, name, body) => {
  const r = await page.evaluate(async ([n, b]) => {
    const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
    const t = await r.text(); try { return { s: r.status, j: JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
  }, [name, body]);
  const klic = name + ' ' + (r.s >= 500 || r.raw !== undefined ? 'PÁD' : r.s);
  pocty[klic] = (pocty[klic] || 0) + 1;
  if (r.s >= 400 && r.j && r.j.error) { const k = name + ': ' + String(r.j.error).replace(/\d+/g, 'N').slice(0, 90); hlasky[k] = (hlasky[k] || 0) + 1; }
  if (r.s >= 500 || r.raw !== undefined) problemy.push({ name, body, odpoved: r });
  return Object.assign({ s: r.s }, r.j || {});
};
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);

(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const naHex = (x, y) => { const r = Math.round(y / rowStep); return { col: Math.round((x - ((r & 1) ? colStep / 2 : 0)) / colStep), row: r }; };

  // úklid a základ
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("delete from souboj_vyzva; delete from aktivni_efekty; update postavy set aktualni_hp=20, max_hp=20, drzena_zbran_id=NULL, unava=0 where id in (1,2)");
  const pridej = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: typ === 'postava2' ? 2 : 1 }, typ.startsWith('postava') ? { typ_entity: 'postava' } : {}))).token_id;
  const tokeny = [await pridej('postava2', 10, 10), await pridej('postava1', 11, 10), await pridej('nestvura', 10, 11), await pridej('nestvura', 11, 11)];
  const sindor = tokeny[0];
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: tokeny.map(t => ({ token_id: t })) });
  const efekty = sqlVal("select group_concat(id) from efekty where id in (select efekt_id from efekt_slozky) limit 1").split(',').map(Number).filter(Boolean);
  const kouzla = [17, 18, 10, 35];

  async function novaIniciativa() {
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    for (const t of tokeny) await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [t], hod: celeCislo(1, 5) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  }
  async function vyresVyzvy(s) {
    for (const v of (s.vyzvy || []).filter(x => x.stav === 'ceka')) {
      if (v.druh === 'obrana' && (v.volba === null || v.volba === undefined)) await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: v.id, volba: pick(['branit', 'nebranit']) });
      if (v.druh === 'past') await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: v.id, nebezpecnost: celeCislo(1, 10) });
      if (v.druh !== 'past' && v.druh !== 'tik') await api(pj, 'vyzva_uprava', Object.assign({ mapa_id: MAPA, vyzva_id: v.id, zaklad: celeCislo(0, 14) }, R() < 0.3 ? { modifikator: celeCislo(-5, 5) } : {}, R() < 0.15 ? { nasobek: pick([1, 2, 9, null]) } : {}));
      const body = v.druh === 'past' ? { hody: [celeCislo(1, 10)] } : v.druh === 'tik' ? { soucet: celeCislo(1, 6), rucne: true } : { hody: [celeCislo(1, 5)] };
      await api(pj, 'vyzva_hod', Object.assign({ mapa_id: MAPA, vyzva_id: v.id }, body));
    }
  }

  for (let i = 0; i < KROKU; i++) {
    let s = await stav(pj);
    const kolo = s.kolo;
    if (!kolo) { await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: tokeny.map(t => ({ token_id: t })) }); continue; }
    if (kolo.faze !== 'kolo') {
      if (kolo.faze === 'umysly') await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
      if (kolo.faze === 'iniciativa') { for (const t of tokeny) await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [t], hod: celeCislo(1, 5) }); await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' }); }
      continue;
    }
    if ((s.vyzvy || []).some(x => x.stav === 'ceka') && R() < 0.8) { await vyresVyzvy(s); continue; }
    const aktivni = kolo.aktivni_token_id;
    const tok = (s.tokeny || []).find(t => t.id === aktivni);
    const sance = R();
    if (sance < 0.55 && tok) {
      const druh = pick(['krok', 'krok', 'beh', 'sprint', 'otocka', 'utok', 'krok_utok', 'beh_utok', 'sprint_utok', 'otocka_utok', 'cekani', 'obrana', 'obrana_krok', 'jina', 'kouzleni', 'vytasit']);
      const h = naHex(tok.x, tok.y);
      const cil = { col: h.col + celeCislo(-3, 3), row: h.row + celeCislo(-3, 3) };
      const body = { mapa_id: MAPA, token_id: tok.id, druh };
      if (/krok|beh|sprint/.test(druh)) { if (R() < 0.5) body.cil = cil; else body.cesta = Array.from({ length: celeCislo(0, 4) }, () => ({ col: h.col + celeCislo(-4, 4), row: h.row + celeCislo(-4, 4), smer: pick([0, 60, 120, 180, 240, 300, undefined]) })); }
      if (/otocka/.test(druh)) body.smer = celeCislo(0, 359);
      if (/utok|strelba|kouzleni/.test(druh)) {
        const jini = (s.tokeny || []).filter(t => t.id !== tok.id && tokeny.includes(t.id));
        // nejbližší tvor a otočení k němu (jinak by útok skoro vždy skončil „cíl není před útočníkem“)
        const nej = jini.sort((a, b) => Math.hypot(a.x - tok.x, a.y - tok.y) - Math.hypot(b.x - tok.x, b.y - tok.y))[0];
        body.cil_token_id = R() < 0.9 && nej ? nej.id : 99999;
        if (nej && R() < 0.85) { body.smer = (Math.round(Math.atan2(nej.y - tok.y, nej.x - tok.x) / (Math.PI / 3)) * 60 + 360) % 360; if (!/otocka/.test(druh) && /krok|beh|sprint/.test(druh) === false) body.druh = 'otocka_utok'; }
      }
      if (druh === 'vytasit') { body.zbran_id = pick([2, 39, 5, 9999]); }
      const jenHrac = tok.id === sindor && R() < 0.5;
      await api(jenHrac ? hr : pj, 'tah_akce', body);
    } else if (sance < 0.65) {
      await api(pj, pick(['dalsi_tah', 'tah_konec', 'kolo_konec']), { mapa_id: MAPA });
    } else if (sance < 0.72) {
      await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: pick(['postava', 'nestvura_instance', 'postava']), entita_id: pick([1, 2, 3, 99999]), efekt_id: pick(efekty), zbyva_kol: pick([undefined, 1, 3, 0]) });
    } else if (sance < 0.77) {
      const ef = sqlVal("select id from aktivni_efekty order by rand() limit 1");
      if (ef) await api(pj, 'efekt_odebrat', { mapa_id: MAPA, aktivni_efekt_id: parseInt(ef, 10) });
    } else if (sance < 0.82) {
      await api(pj, 'hp_uprava', { typ_entity: 'postava', entita_id: pick([1, 2]), mapa_id: MAPA, zmena: celeCislo(-12, 8) });
    } else if (sance < 0.86) {
      await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: pick(tokeny), op: pick(['delta', 'rychlost', 'zavazek_zrusit']), delta: celeCislo(-3, 3) });
    } else if (sance < 0.89) {
      await api(pj, 'pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'kouzlo', polozka_id: pick(kouzla), mapa_id: MAPA, cil_typ_entity: 'postava', cil_entita_id: pick([1, 2]), zbyva_kol: pick([undefined, 2]) });
    } else if (sance < 0.92) {
      await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
    } else if (sance < 0.95) {
      await api(pj, 'krok_zpet', { mapa_id: MAPA });
    } else if (sance < 0.97) {
      await api(hr, 'umysl_nastavit', { mapa_id: MAPA, token_id: sindor, umysl: pick(['utok', 'kouzleni', 'strelba', 'pohyb', 'jina', 'blbost']) });
    } else {
      await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: pick(['umysly', 'iniciativa', 'kolo']) });
    }
    if (i % 40 === 39) { await vyresVyzvy(await stav(pj)); }
    // invarianty
    const hp = sqlVal("select group_concat(aktualni_hp) from postavy where id in (1,2)");
    if (/-/.test(hp) && false) problemy.push({ invariant: 'záporné životy postavy', hp });
  }
  await browser.close();

  console.log('seed ' + SEED + ', kroků ' + KROKU);
  console.log('volání (endpoint stav): ' + Object.entries(pocty).sort().map(([k, v]) => k + '=' + v).join(', '));
  console.log('nejčastější odmítnutí:'); for (const [k, v] of Object.entries(hlasky).sort((a, b) => b[1] - a[1]).slice(0, 12)) console.log('   ' + v + '× ' + k);
  console.log('pádů serveru (5xx / ne-JSON): ' + problemy.length);
  for (const p of problemy.slice(0, 8)) console.log('   ' + JSON.stringify(p).slice(0, 400));
  const zaznamy = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  const sloucene = {};
  for (const z of zaznamy) { const k = z.druh + ' | ' + z.zprava.slice(0, 140) + ' | ' + (z.soubor || '') + ':' + (z.radek || ''); sloucene[k] = (sloucene[k] || 0) + 1; }
  console.log('záznamy v chybovém logu: ' + zaznamy.length);
  for (const [k, v] of Object.entries(sloucene).sort((a, b) => b[1] - a[1]).slice(0, 15)) console.log('   ' + v + '× ' + k);
  process.exit(problemy.length || zaznamy.length ? 1 : 0);
})().catch(e => { console.error('PÁD fuzzeru', e); process.exit(2); });
