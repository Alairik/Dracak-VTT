const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
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
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set aktualni_hp=5, max_hp=5, max_magenergie=NULL where id=2");
  }
  async function pridej(typ, col, row) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  const instOf = (tok) => parseInt(sqlVal(`select entita_id from tokeny where id=${tok}`), 10);
  const ki = (tok) => parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${tok}`) || '-1', 10);
  await uklid();
  sql("insert ignore into postava_zna_kouzlo (postava_id, kouzlo_id) values (2,17),(2,18),(2,35)");
  const sindor = await pridej('postava', 13, 10);
  const gob = await pridej('nestvura', 14, 10);
  const gobInst = instOf(gob);
  sql(`update nestvura_instance set aktualni_hp=30, max_hp=30 where id=${gobInst}`);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });

  // Protoplazma má hod proti pasti: efekt se při sesílání sám nenasadí
  let d = await api(hr, 'pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'kouzlo', polozka_id: 17, cil_typ_entity: 'nestvura_instance', cil_entita_id: gobInst, mapa_id: MAPA });
  ok(d.ok && (d.ceka_na_past || []).length === 1 && d.ceka_na_past[0].nazev === 'Protoplazma', 'Protoplazma: efekt čeká na hod cíle proti pasti: ' + (d.error || JSON.stringify(d.ceka_na_past)));
  ok(/Obr/.test(d.ceka_na_past[0].past), 'popis pasti: ' + d.ceka_na_past[0].past);
  ok(parseInt(sqlVal(`select count(*) from aktivni_efekty where typ_entity='nestvura_instance' and entita_id=${gobInst}`), 10) === 0, 'goblin zatím žádný efekt nemá');
  ok((d.efekty || []).length === 0, 'odpověď nehlásí aplikovaný efekt');
  // PJ po neúspěchu cíle efekt nasadí ručně: goblin 1 akce -> 0
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: gobInst, efekt_id: parseInt(sqlVal("select id from efekty where nazev='Protoplazma'"), 10), zbyva_kol: 4 });
  ok(d.ok, 'PJ nasadí Protoplazmu ručně po neúspěšném hodu cíle');
  // Rychlost na sebe: bez pasti, trvání ze sesílání — bez počtu kol se nejdřív zeptá, nic se neutratí
  const akcePred = ki(sindor);
  d = await api(hr, 'pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'kouzlo', polozka_id: 18, mapa_id: MAPA });
  ok(d.potrebuje_trvani === true && d.efekt === 'Rychlost (kouzlo)', 'bez počtu kol se server zeptá: ' + JSON.stringify(d));
  ok(ki(sindor) === akcePred && parseInt(sqlVal("select count(*) from aktivni_efekty where typ_entity='postava' and entita_id=2"), 10) === 0, 'při dotazu se nic nezměnilo');
  d = await api(hr, 'pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'kouzlo', polozka_id: 18, mapa_id: MAPA, zbyva_kol: 3 });
  const e = JSON.parse(sqlVal("select json_object('n', e.nazev, 'z', ae.zbyva_kol, 'k', ae.nasazeno_kolo) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where ae.typ_entity='postava' and ae.entita_id=2"));
  ok(d.ok && e.n === 'Rychlost (kouzlo)' && e.z === 3 && e.k === 1, 'Rychlost (kouzlo) sesláno na 3 kola: ' + (d.error || JSON.stringify(e)));
  ok(ki(sindor) === akcePred * 2, `Sindorovy akce se zdvojily ${akcePred} -> ${ki(sindor)}`);
  ok(d.efekty[0].akce && d.efekty[0].upozorneni.length === 0, 'odpověď nese změnu akcí');
  // znovu seslat: trvání se sčítá, akce se neznásobí
  d = await api(hr, 'pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'kouzlo', polozka_id: 18, mapa_id: MAPA, zbyva_kol: 2 });
  const e2 = JSON.parse(sqlVal("select json_object('z', ae.zbyva_kol) from aktivni_efekty ae where ae.typ_entity='postava' and ae.entita_id=2"));
  ok(d.ok && e2.z === 5 && d.efekty[0].obnoveno === true && ki(sindor) === akcePred * 2, 'druhé seslání: trvání 3+2 = 5, účinek se neznásobil');
  // Bílá střela: má Past v textu, ale poškození se aplikuje i tak (jen stavové efekty se drží)
  await uklid();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  await browser.close();
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
