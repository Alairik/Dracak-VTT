const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const SLOUPEC = process.env.SLOUPEC !== '0';   // 0 = bez sloupce u_sebe (degradace)
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.accept());
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(600);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const drzena = () => sqlVal('select ifnull(drzena_zbran_id,0) from postavy where id=2');
  const ki = (tok) => JSON.parse(sqlVal(`select json_object('c', akce_celkem, 'z', akce_zbyvajici, 'ah', akce_hotovo) from kolo_iniciativa where token_id=${tok}`));
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set drzena_zbran_id=NULL where id=2");
    if (SLOUPEC) sql('update postava_predmety set u_sebe=1 where postava_id=2');
  }
  async function pridej(typ, col, row) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  const DYKA = 2, CEP = 39;

  // ---- mimo boj: zdarma, kdokoli z inventáře ----
  await uklid();
  let d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA });
  ok(d.ok && d.zdarma === true && drzena() === String(DYKA), 'mimo boj: vytasení zdarma (hráč): ' + JSON.stringify(d));
  d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA });
  ok(d.s === 422, 'tu samou zbraň už drží: ' + d.error);
  d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: 9999 });
  ok(d.s === 422, 'zbraň, kterou nemá: ' + d.error);
  if (SLOUPEC) {
    d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: CEP });
    ok(d.ok && drzena() === String(CEP), 'výměna na Cep zdarma');
    ok(sqlVal(`select u_sebe from postava_predmety where postava_id=2 and predmet_id=${DYKA}`) === '1', 'odložená Dýka přešla do „u sebe“');
    d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'zbran_u_sebe', polozka_id: DYKA, hodnota: 0 });
    ok(d.ok && sqlVal(`select u_sebe from postava_predmety where postava_id=2 and predmet_id=${DYKA}`) === '0', 'mimo boj jde Dýku dát do batohu');
  } else {
    d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'zbran_u_sebe', polozka_id: DYKA, hodnota: 0 });
    ok(d.s === 422, 'bez sloupce se „u sebe“ nedá ukládat: ' + d.error);
  }

  if (SLOUPEC) {
    // starý zápis přes postava_stav vyber jde taky přes společnou funkci: odložená zbraň se zasune k tělu
    await uklid();
    await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA });
    sql(`update postava_predmety set u_sebe=0 where postava_id=2 and predmet_id=${DYKA}`);
    d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'vyber', pole: 'drzena_zbran_id', hodnota: CEP });
    ok(d.ok && drzena() === String(CEP) && sqlVal(`select u_sebe from postava_predmety where postava_id=2 and predmet_id=${DYKA}`) === '1', 'vyber zbraně mimo boj: odložená Dýka přešla do „u sebe“');
    d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'vyber', pole: 'drzena_zbran_id', hodnota: CEP });
    ok(d.ok && drzena() === String(CEP), 'vyber téže zbraně je v pořádku (nic se nemění)');
    d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'vyber', pole: 'drzena_zbran_id', hodnota: 0 });
    ok(d.ok && drzena() === '0', 'vyber prázdných rukou');
  }

  // ---- v boji ----
  await uklid();
  if (SLOUPEC) sql(`update postava_predmety set u_sebe=0 where postava_id=2 and predmet_id=${CEP}`);
  await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA });
  const sindor = await pridej('postava', 13, 10), gob = await pridej('nestvura', 14, 10);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: CEP });
  ok(d.s === 422, 'v úmyslech (ne na tahu) hráč zbraň nevymění: ' + d.error);
  d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'vyber', pole: 'drzena_zbran_id', hodnota: CEP });
  ok(d.s === 422 && drzena() === String(DYKA), 'v boji přímý „vyber“ zbraně hráči nejde: ' + d.error);
  if (SLOUPEC) {
    d = await api(hr, 'postava_stav', { postava_id: 2, akce: 'zbran_u_sebe', polozka_id: CEP, hodnota: 1 });
    ok(d.s === 422, 'v boji hráč výbavu nepřebaluje: ' + d.error);
  }
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(hr, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  let k = (await stav(pj)).kolo;
  ok(k.aktivni_token_id === sindor, 'Sindor je na tahu');
  const pred = ki(sindor);
  if (SLOUPEC) {
    d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: CEP });
    ok(d.s === 422 && /není u sebe/.test(d.error || '') && drzena() === String(DYKA) && ki(sindor).z === pred.z, 'v boji zbraň z batohu nevytasí a akci neztratí: ' + d.error);
    sql(`update postava_predmety set u_sebe=1 where postava_id=2 and predmet_id=${CEP}`);
  }
  d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: CEP });
  ok(d.ok && d.zdarma === false && drzena() === String(CEP), 'v boji na tahu: zbraň vytasena: ' + JSON.stringify(d));
  const po = ki(sindor);
  ok(po.z === pred.z - 1 && po.ah === pred.ah + 1, 'stálo to jednu akci: ' + JSON.stringify([pred, po]));
  ok(parseInt(sqlVal("select count(*) from svet_udalosti where typ='akce_provedena' and payload like '%vytasen%' and payload like '%Cep%'"), 10) >= 1, 'v logu světa je „vytasení zbraně (Cep)“');
  if (SLOUPEC) ok(sqlVal(`select u_sebe from postava_predmety where postava_id=2 and predmet_id=${DYKA}`) === '1', 'Dýka se zasunula k tělu');
  // kvóta 1. půlky je vyčerpaná → tah přešel na goblina
  k = (await stav(pj)).kolo;
  ok(k.aktivni_token_id === gob, 'po vyčerpání akcí 1. půlky tah přešel na goblina');
  d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA });
  ok(d.s === 422 && drzena() === String(CEP), 'mimo svůj tah zbraň nevymění: ' + d.error);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'vytasit', zbran_id: DYKA });
  ok(d.s === 422 && drzena() === String(CEP), 'ani přes obecný tah_akce: ' + d.error);
  // PJ bez akce (zbraň se mění, počítadla akcí zůstávají)
  const kiPred = ki(sindor);
  d = await api(pj, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA, bez_akce: true });
  ok(d.ok && d.zdarma === true && drzena() === String(DYKA) && JSON.stringify(ki(sindor)) === JSON.stringify(kiPred), 'PJ změní zbraň bez akce: ' + JSON.stringify(d));
  d = await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: CEP, bez_akce: true });
  ok(d.s === 422 && drzena() === String(DYKA), 'hráč „bez_akce“ použít nesmí (zbraň zůstala, 422): ' + d.error);
  // 2. půlka: Sindor jde znovu, zbývající akce, stejná akce přes tah_akce (souboj.js)
  await api(pj, 'tah_konec', { mapa_id: MAPA });
  k = (await stav(pj)).kolo;
  ok(k.aktivni_token_id === sindor && k.polovina === 2, 've 2. půlce je Sindor znovu na tahu');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'vytasit', zbran_id: CEP });
  ok(d.ok && drzena() === String(CEP), 'akce vytasit přes obecný tah_akce (souboj.js) funguje: ' + (d.error || ''));
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'vytasit', zbran_id: 0 });
  ok(d.s === 422 || d.s === 200, 'schování zbraně (prázdné ruce): ' + JSON.stringify(d.error || 'ok'));

  // ---- diář: vykreslení ----
  const html = await hr.evaluate(async () => (await fetch('denik.php?id=2&fragment=1&embed=1', { cache: 'no-store' })).text());
  ok(/data-boj="1"/.test(html), 'deník: #denik má data-boj="1" při boji');
  ok(/Jsi v boji/.test(html), 'deník: stav boje');
  ok(/data-vytasit="/.test(html), 'deník: tlačítka Vytasit');
  ok(SLOUPEC ? /data-zmena="zbran_u_sebe"/.test(html) : !/zbran_u_sebe/.test(html), 'deník: sloupec „U sebe“ jen s DDL');
  await uklid();
  const html2 = await hr.evaluate(async () => (await fetch('denik.php?id=2&fragment=1&embed=1', { cache: 'no-store' })).text());
  ok(/data-boj="0"/.test(html2) && !/Jsi v boji/.test(html2), 'deník mimo boj: žádný stav boje');

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
