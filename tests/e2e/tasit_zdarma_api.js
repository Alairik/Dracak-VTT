const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak', MAPA = 2;
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
const DYKA = 2, CEP = 39, VRH = 71, HVEZ = 72, LUK = 61;
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
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set drzena_zbran_id=NULL where id=2; update postava_predmety set u_sebe=1 where postava_id=2");
  }
  const pridej = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
  // Sindor dostane vrhací zbraně do inventáře
  sql(`insert ignore into postava_predmety (postava_id,predmet_id,mnozstvi) values (2,${VRH},1),(2,${HVEZ},1),(2,${LUK},1)`);
  async function zacniBoj(hodSindor) {
    await uklid();
    const sindor = await pridej('postava', 13, 10), gob = await pridej('nestvura', 14, 10);
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
    await api(hr, 'umysl_nastavit', { mapa_id: MAPA, token_id: sindor, umysl: 'kouzleni' });   // +1 → hod 6 dá 3 akce
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    const dh = await api(hr, "souboj_hod", { mapa_id: MAPA, token_ids: [sindor], hod: 6 }); if (!dh.ok) console.log("HOD", JSON.stringify(dh));
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
    return sindor;
  }
  const vytasit = (page, zbran, extra) => api(page, 'zbran_vytasit', Object.assign({ postava_id: 2, zbran_id: zbran }, extra || {}));
  const udalost = () => sqlVal("select payload from svet_udalosti where typ='akce_provedena' order by id desc limit 1");

  // ======== bez efektu: vytasení stojí akci (obecné pravidlo) ========
  let sindor = await zacniBoj(9);   // 9 → 3 akce, kvóta 1. půlky 2
  let pred = ki(sindor);
  let d = await vytasit(hr, DYKA);
  ok(d.ok && d.zdarma === false && !d.efekt && ki(sindor).z === pred.z - 1, 'obyčejná postava: vytasení stojí akci: ' + JSON.stringify(d));

  // ======== Erythen 6. úrovně — Rychlé tasení ========
  sql("update postavy set povolani_id=29, uroven=5 where id=2");
  sindor = await zacniBoj(9); pred = ki(sindor);
  d = await vytasit(hr, DYKA);
  ok(d.ok && !d.efekt && ki(sindor).z === pred.z - 1, 'Erythen 5. úrovně: schopnost ještě nemá, vytasení stojí akci');
  sql("update postavy set uroven=6 where id=2");
  sindor = await zacniBoj(9); pred = ki(sindor);
  d = await vytasit(hr, DYKA);
  ok(d.ok && d.zdarma === true && d.efekt === 'Rychlé tasení (Erythen)' && drzena() === String(DYKA), 'Erythen 6. úrovně: vytasení tváří v tvář bez akce: ' + JSON.stringify(d));
  ok(JSON.stringify(ki(sindor)) === JSON.stringify(pred), 'počítadla akcí beze změny: ' + JSON.stringify(ki(sindor)));
  let ev = JSON.parse(udalost());
  ok(ev.druh === 'vytasit' && ev.zdarma_efekt === 'Rychlé tasení (Erythen)' && ev.pocet === 0 && ev.zbran_nazev === 'Dýka', 'v logu: bez akce, efekt, zbraň: ' + JSON.stringify(ev).slice(0, 200));
  ok((await stav(pj)).kolo.aktivni_token_id === sindor, 'tah zůstává Sindorovi');
  d = await vytasit(hr, CEP);
  ok(d.ok && d.zdarma === false && ki(sindor).z === pred.z - 1, 'druhé vytasení v témže kole už stojí akci (jednou za kolo): ' + JSON.stringify(d));
  // vrhací zbraň sdílí tentýž limit
  sindor = await zacniBoj(9); pred = ki(sindor);
  d = await vytasit(hr, VRH);
  ok(d.ok && d.zdarma === true && d.efekt === 'Rychlé tasení (Erythen)', 'Erythen: vrhací zbraň bez akce (vytasit a hodit)');
  d = await vytasit(hr, DYKA);
  ok(d.ok && d.zdarma === false && ki(sindor).z === pred.z - 1, 'po vrhací zbrani už limit „jednou za kolo“ vyčerpán, tváří v tvář stojí akci');
  // střelná zbraň se nepočítá
  sindor = await zacniBoj(9); pred = ki(sindor);
  d = await vytasit(hr, LUK);
  ok(d.ok && d.zdarma === false && ki(sindor).z === pred.z - 1, 'luk (střelná zbraň) není vrhací ani TvT: stojí akci');
  // zbraň v batohu: ani u Erythena bez akce
  sindor = await zacniBoj(9);
  sql(`update postava_predmety set u_sebe=0 where postava_id=2 and predmet_id=${HVEZ}`);
  d = await vytasit(hr, HVEZ);
  ok(d.s === 422 && /není u sebe/.test(d.error || ''), 'zbraň z batohu nejde vytasit ani bez akce: ' + d.error);
  sql(`update postava_predmety set u_sebe=1 where postava_id=2 and predmet_id=${HVEZ}`);
  // první akce kola: bezplatné vytasení není „akce“, tedy žádný trest za slib úkonu
  await uklid();
  sindor = await pridej('postava', 13, 10); const gob = await pridej('nestvura', 14, 10);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(hr, 'umysl_nastavit', { mapa_id: MAPA, token_id: sindor, umysl: 'kouzleni' });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(hr, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 }); await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  d = await vytasit(hr, DYKA);
  ok(d.ok && d.zdarma === true && ki(sindor).ah === 0, 'bezplatné vytasení se nepočítá jako první akce kola: akce_hotovo = 0');
  const trest = () => parseInt(sqlVal("select count(*) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where ae.typ_entity='postava' and ae.entita_id=2 and e.nazev like 'Ztráta iniciativy%'"), 10);
  ok(trest() === 0, 'a nespustí trest −3 za nesplněný slib');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok && trest() === 0, 'první skutečná akce je slíbené kouzlení: bez trestu');

  // ======== Dikobraz (aktivní efekt od PJ) ========
  sql("update postavy set povolani_id=1, uroven=1 where id=2");
  sindor = await zacniBoj(9); pred = ki(sindor);
  d = await vytasit(hr, VRH);
  ok(d.ok && d.zdarma === false && ki(sindor).z === pred.z - 1, 'obyčejná postava bez Dikobraza: vrhací zbraň stojí akci');
  sindor = await zacniBoj(12); pred = ki(sindor);   // 12 → 4 akce
  const efDik = parseInt(sqlVal("select id from efekty where nazev='Dikobraz (Zlaté tetování)'"), 10);
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: efDik });
  ok(d.ok, 'PJ nasadí Dikobraza: ' + (d.error || ''));
  d = await vytasit(hr, VRH);
  ok(d.ok && d.zdarma === true && d.efekt === 'Dikobraz (Zlaté tetování)', 'Dikobraz: vrhací zbraň bez akce');
  d = await vytasit(hr, HVEZ);
  ok(d.ok && d.zdarma === true, 'Dikobraz: bez omezení počtu za kolo (druhá vrhací zbraň také zdarma)');
  d = await vytasit(hr, VRH);
  ok(d.ok && d.zdarma === true, 'a třetí (zpět na první)');
  ok(JSON.stringify(ki(sindor)) === JSON.stringify(pred), 'akce beze změny po třech vytaseních: ' + JSON.stringify(ki(sindor)));
  d = await vytasit(hr, DYKA);
  ok(d.ok && d.zdarma === false && ki(sindor).z === pred.z - 1, 'Dikobraz se týká jen vrhacích zbraní: Dýka tváří v tvář stojí akci');
  // efekt sundán → zase platí obecné pravidlo
  sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2");
  d = await vytasit(hr, VRH);
  ok(d.ok && d.zdarma === false, 'po sundání efektu zase stojí akci');

  // ======== deník ========
  sql("update postavy set povolani_id=29, uroven=6 where id=2");
  sindor = await zacniBoj(9);
  let html = await hr.evaluate(async () => (await fetch('denik.php?id=2&fragment=1&embed=1', { cache: 'no-store' })).text());
  ok(/Bez akce: <strong>Rychlé tasení \(Erythen\)[^<]*zbývá 1× v tomhle kole/.test(html), 'deník v boji: pruh „Bez akce: Rychlé tasení…, zbývá 1×“');
  ok(/data-vytasit="2"[^>]*data-zdarma="Rychlé tasení \(Erythen\)"/.test(html) && /Vytasit \(bez akce\)/.test(html), 'tlačítko „Vytasit (bez akce)“');
  await vytasit(hr, DYKA);
  html = await hr.evaluate(async () => (await fetch('denik.php?id=2&fragment=1&embed=1', { cache: 'no-store' })).text());
  ok(!/Bez akce: <strong>/.test(html) && !/Vytasit \(bez akce\)/.test(html), 'po využití v tomhle kole už pruh ani tlačítko „bez akce“ nenabízí');
  // PJ a efekt „pasivni“ se nenasazuje do aktivních efektů
  ok(parseInt(sqlVal("select count(*) from aktivni_efekty where typ_entity='postava' and entita_id=2"), 10) === 0, 'Rychlé tasení není v aktivních efektech (trvalá schopnost)');

  await uklid();
  sql(`delete from postava_predmety where postava_id=2 and predmet_id in (${VRH},${HVEZ},${LUK}); update postavy set povolani_id=1, uroven=1, drzena_zbran_id=NULL where id=2`);
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})();
