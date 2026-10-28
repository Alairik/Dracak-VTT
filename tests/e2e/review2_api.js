const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const DB = process.env.DB || 'dracak';
const BASE = (process.env.BASE || 'http://127.0.0.1:8099');
const MAPA = 2;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
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
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set aktualni_hp=20, max_hp=20, drzena_zbran_id=NULL where id=2");
  }
  const pridej = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
  const KRVACENI = parseInt(sqlVal("select id from efekty where nazev like 'Krvácení%' order by id limit 1"), 10);
  const RYCHLOST = parseInt(sqlVal("select id from efekty where nazev = 'Rychlost (lektvar)'"), 10);
  async function zacniBoj(tokeny) {
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: tokeny.map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    for (const t of tokeny) await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [t], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  }
  // Odehraje zbytek kola (každý tvor „čeká“), čímž kolo skončí a boj založí tiky; vrátí nové číslo kola.
  async function dokonciKolo() {
    const kolo0 = (await stav(pj)).kolo.cislo_kola;
    for (let i = 0; i < 40; i++) {
      const k = (await stav(pj)).kolo;
      if (k.cislo_kola > kolo0 || k.aktivni_token_id === null) return k.cislo_kola;
      await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.aktivni_token_id, druh: 'cekani' });
    }
    return (await stav(pj)).kolo.cislo_kola;
  }
  async function dalsiKolo(tokeny) {
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    for (const t of tokeny) await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [t], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  }
  const tiky = (stavy) => parseInt(sqlVal(`select count(*) from souboj_vyzva where mapa_id=${MAPA} and druh='tik' and stav in (${stavy})`), 10);

  // ---- tiky: dva tokeny téže postavy = jeden tik; nedohraný tik se s novým kolem zruší ----
  await uklid();
  const s1 = await pridej('postava', 13, 10), s2 = await pridej('postava', 15, 10), gob = await pridej('nestvura', 14, 12);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: KRVACENI });
  await zacniBoj([s1, gob]);
  let d;
  await dokonciKolo();
  ok(tiky("'ceka'") === 1, 'krvácení má po novém kole jeden čekající tik (ne dva za dva tokeny): ' + tiky("'ceka'"));
  await dalsiKolo([s1, gob]); await dokonciKolo();
  ok(tiky("'ceka'") === 1 && tiky("'zruseno'") >= 1, 'nedohraný tik z minulého kola se zrušil, čeká jen nový: ceka=' + tiky("'ceka'") + ' zruseno=' + tiky("'zruseno'"));
  // tik za efekt, který mezitím zmizel
  const tikId = parseInt(sqlVal(`select id from souboj_vyzva where mapa_id=${MAPA} and druh='tik' and stav='ceka' limit 1`), 10);
  sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2");
  const hp0 = sqlVal('select aktualni_hp from postavy where id=2');
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tikId, hody: [3] });
  ok(d.s === 422 && /už na tvorovi není/.test(d.error || '') && sqlVal('select aktualni_hp from postavy where id=2') === hp0, 'tik za sundaný efekt se neprovede, životy beze změny: ' + (d.error || JSON.stringify(d).slice(0, 100)));
  ok(sqlVal(`select stav from souboj_vyzva where id=${tikId}`) === 'zruseno', 'výzva je zrušená');

  // ---- obnova efektu přepíše kolo nasazení (kolo_se_nepocita) ----
  await uklid();
  const sA = await pridej('postava', 13, 10), gA = await pridej('nestvura', 14, 10);
  await zacniBoj([sA, gA]);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: RYCHLOST, zbyva_kol: 3 });
  const kolo1 = sqlVal("select nasazeno_kolo from aktivni_efekty where typ_entity='postava' and entita_id=2 and efekt_id=" + RYCHLOST);
  await dokonciKolo(); await dalsiKolo([sA, gA]); await dokonciKolo();
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: RYCHLOST, zbyva_kol: 3 });
  const kolo2 = sqlVal("select nasazeno_kolo from aktivni_efekty where typ_entity='postava' and entita_id=2 and efekt_id=" + RYCHLOST);
  ok(parseInt(kolo2, 10) > parseInt(kolo1, 10), 'po obnovení efektu platí kolo poslední aplikace: ' + kolo1 + ' → ' + kolo2);

  // ---- kolo_nove nevyzradí skrytou nestvůru ----
  await uklid();
  const sB = await pridej('postava', 13, 10), gB = await pridej('nestvura', 14, 10);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sB, gB].map(t => ({ token_id: t })) });
  await api(pj, 'umysl_nastavit', { mapa_id: MAPA, token_id: gB, umysl: 'kouzleni' });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sB], hod: 1 }); await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gB], hod: 6 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  const uzNeco = (await stav(pj)).kolo;
  // goblin splní první akci (kouzlení), ale ne polovinu akcí kola → trest až na konci kola (to je ten, co jde do veřejné události)
  let gobAkci = 0;
  sql("delete from svet_udalosti where typ='efekt_aplikovan' and payload like '%nesplněný slib%'");
  for (let i = 0; i < 12; i++) {
    const k = (await stav(pj)).kolo;
    if (k.cislo_kola > 1 || k.aktivni_token_id === null) break;
    const druh = k.aktivni_token_id === gB && gobAkci++ === 0 ? 'kouzleni' : 'cekani';
    await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.aktivni_token_id, druh });
  }
  const nove = sqlVal("select payload from svet_udalosti where typ='kolo_nove' order by id desc limit 1");
  const pl = JSON.parse(nove || '{}');
  ok(pl.nove_kolo >= 2, 'kolo skončilo: ' + (nove || '').slice(0, 120));
  ok(!(pl.ztrata_iniciativy || []).some((z) => z.token_id === gB), 'veřejná událost kolo_nove neobsahuje skrytou nestvůru (trest jí PJ vidí v efektech)');
  ok(parseInt(sqlVal("select count(*) from svet_udalosti where typ='efekt_aplikovan' and payload like '%nesplněný slib: kouzlení%'"), 10) >= 1, 'trest za nesplněnou polovinu akcí nestvůře přesto proběhl (je v logu efektů pro PJ)');

  // ---- souboj neběží ----
  await uklid();
  const sC = await pridej('postava', 13, 10), gC = await pridej('nestvura', 14, 10);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: KRVACENI });
  await zacniBoj([sC, gC]);
  await dokonciKolo();
  const tik2 = parseInt(sqlVal(`select id from souboj_vyzva where mapa_id=${MAPA} and druh='tik' and stav='ceka' limit 1`), 10);
  sql(`delete from kolo_stav where mapa_id=${MAPA}`);
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tik2, hody: [2] });
  ok(d.s === 422 && /žádný souboj neběží/.test(d.error || ''), 'hod k výzvě, když souboj neběží: srozumitelná hláška místo pádu: ' + (d.error || JSON.stringify(d).slice(0, 100)));

  await uklid();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD', e.message.split('\n').slice(0, 3).join(' | ')); process.exit(2); });
