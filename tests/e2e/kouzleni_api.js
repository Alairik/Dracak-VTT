// Kouzlení v souboji (kapitola Kouzlení b10842–b10847, migrace 0112): akce kouzlení nasadí štítek „Kouzlí“ (pro útočníky nehybný), druhá akce kouzlení ho nenasadí znovu,
// jiná akce ho sundá (kouzlení se musí držet po sobě), PJ ho sundá po poslední akci kouzlení, konec souboje ho sundá taky; zásah kouzlícího boj ohlásí.
// Mapa 2 (hex). Po resetu dat: mysql dracak < ../fixture/seed.sql; node kouzleni_api.js
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
  const stitek = () => parseInt(sqlVal("select count(*) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev='Kouzlí' and ae.typ_entity='postava' and ae.entita_id=2"), 10);
  const udalost = (typ) => sqlVal(`select count(*) from svet_udalosti where mapa_id=${MAPA} and typ='${typ}' and payload like '%Kouzl%'`);

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set aktualni_hp=40, max_hp=40 where id=2; delete from mlha_valky where mapa_id=" + MAPA);
  const sindor = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: stred(13, 10).x, y: stred(13, 10).y })).token_id;
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(14, 10).x, y: stred(14, 10).y })).token_id;
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  for (let i = 0; i < 2; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });

  ok(stitek() === 0, 'před kouzlením nemá Sindor žádný štítek');
  // --- první akce kouzlení: štítek vznikne ---
  let d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok === true, 'akce kouzlení projde: ' + (d.error || ''));
  ok(stitek() === 1, 'po akci kouzlení má Sindor štítek „Kouzlí“');
  ok(udalost('efekt_aplikovan') === '1', 'v logu je událost o nasazení štítku');
  const st = await stav(pj);
  const tokS = st.tokeny.find(t => t.id === sindor);
  ok(JSON.stringify(tokS.efekty || tokS).includes('Kouzl'), 'štítek je vidět na tokenu ve stavu mapy');
  // --- druhá akce kouzlení: štítek zůstane jeden ---
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok === true && stitek() === 1, 'druhá akce kouzlení štítek nezdvojí (' + stitek() + ')');
  ok(udalost('efekt_aplikovan') === '1', 'druhý štítek se nelogoval');

  // --- goblin zaútočí na kouzlícího Sindora: nehybný, zásah ohlášen ---
  let k = (await stav(pj)).kolo;
  for (let i = 0; i < 4 && k.aktivni_token_id !== gob; i++) { await api(pj, 'tah_konec', { mapa_id: MAPA }); k = (await stav(pj)).kolo; }
  ok(k.aktivni_token_id === gob, 'na tahu je goblin');
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gob, druh: 'utok', cil_token_id: sindor });
  let vy = (await stav(pj)).vyzvy;
  ok(d.ok && vy[0], 'goblin útočí na Sindora: ' + (d.error || ''));
  sql(`update souboj_vyzva set data = json_set(data, '$.zaklad', 3) where id=${vy[0].id}`);
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [4] });
  ok(d.ok === true, 'útok goblina hozen: ' + (d.error || JSON.stringify(d).slice(0, 200)));
  const obr = (await stav(pj)).vyzvy.find(x => x.druh === 'obrana');
  ok(obr && obr.nehybny === true, 'obrana kouzlícího Sindora platí jako nehybná: ' + JSON.stringify(obr && obr.nehybny));
  ok(obr && /Kouzl/.test(JSON.stringify(obr.nehybny_duvod || obr.upozorneni || obr.opravy)), 'důvod nehybnosti říká „Kouzlí“');
  await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obr.id, volba: 'nebranit' });
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: obr.id, hody: [1] });
  ok(d.ok && d.vysledek && d.vysledek.zasah === true && d.vysledek.zraneni > 0, 'zásah kouzlícího: ' + (d.error || JSON.stringify(d.vysledek && [d.vysledek.zasah, d.vysledek.zraneni])));
  ok(d.vysledek && d.vysledek.kouzlo_zasazeno === true, 'výsledek hlásí, že byl zasažen kouzlící');
  const udVys = sqlVal(`select payload from svet_udalosti where mapa_id=${MAPA} and typ='souboj_vysledek' order by id desc limit 1`);
  ok(JSON.parse(udVys).kouzlo_zasazeno === true, 'událost souboj_vysledek nese příznak pro log');
  ok(stitek() === 1, 'štítek po zásahu zůstává (rozhoduje PJ, předposlední akce)');

  // --- PJ sundá štítek ručně ---
  const aeId = sqlVal("select ae.id from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev='Kouzlí' and ae.entita_id=2 and ae.typ_entity='postava'");
  d = await api(pj, 'efekt_odebrat', { mapa_id: MAPA, aktivni_efekt_id: Number(aeId) });
  ok(d.ok && stitek() === 0, 'PJ štítek sundá po poslední akci kouzlení');

  // --- jiná akce štítek sundá (kouzlení přerušeno) ---
  sql("update postavy set aktualni_hp=40 where id=2");
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  for (let i = 0; i < 2; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok && stitek() === 1, 'znovu kouzlí: štítek je tu');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  ok(d.ok === true && stitek() === 0, 'jiná akce (čekání) štítek sundá — kouzlení se přerušilo: ' + (d.error || stitek()));
  ok(sqlVal(`select count(*) from svet_udalosti where mapa_id=${MAPA} and typ='efekt_konci' and payload like '%kouzleni_prerusene%'`) === '1', 'v logu je důvod „kouzlení přerušeno“');

  // --- konec souboje štítek sundá ---
  sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2");
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(stitek() === 1, 'třetí souboj: štítek nasazen');
  d = await api(pj, 'souboj_konec', { mapa_id: MAPA });
  ok(d.ok && stitek() === 0, 'konec souboje štítek „Kouzlí“ sundá');

  // =============== kouzlo s vyvoláním: hra počítá akce (migrace 0113) ===============
  const MODRE = 10, PROTOPLAZMA = 17;   // Sindor je zná: Modré blesky (vyvolání 1 kolo = 2 akce), Protoplazma (2 kola = 4 akce)
  const stavKouzleni = () => sqlVal("select concat_ifnull(ae.kouzlo_id,'-'),'|',ifnull(ae.akci_hotovo,'-'),'|',ifnull(ae.akci_celkem,'-')) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev='Kouzlí' and ae.typ_entity='postava' and ae.entita_id=2".replace('concat_ifnull(', "concat(ifnull(").replace("'-'),'|',ifnull(ae.akci_hotovo", "'-'),'|',ifnull(ae.akci_hotovo"));
  const udalosti = (stavTxt) => sqlVal(`select count(*) from svet_udalosti where mapa_id=${MAPA} and typ='kouzleni_stav' and payload like '%${stavTxt}%'`);
  const novySouboj = async (hodSindor) => {
    sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2; delete from souboj_vyzva; delete from svet_udalosti where typ='kouzleni_stav'; update postavy set aktualni_hp=40, max_hp=40 where id=2");
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: hodSindor });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
    for (let i = 0; i < 2; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });
  };
  const seznam = await hr.evaluate(async (t) => (await fetch('api/kouzla_tvora.php?token_id=' + t)).json(), sindor);
  ok(seznam.pocita === true && seznam.kouzla.length === 4, 'seznam kouzel Sindora pro výběr: ' + seznam.kouzla.map(k => k.nazev + '=' + k.akci).join(', '));
  ok(seznam.kouzla.find(k => k.id === MODRE).akci === 2 && seznam.kouzla.find(k => k.id === PROTOPLAZMA).akci === 4, 'Modré blesky 2 akce, Protoplazma 4 akce (2 × vyvolání v kolech)');
  const jineHrace = await pj.evaluate(async (t) => (await fetch('api/kouzla_tvora.php?token_id=' + t)).status, gob);
  ok(jineHrace === 200, 'PJ smí načíst kouzla nestvůry (prázdný seznam)');

  // A) Modré blesky: 2 akce. První: štítek 1/2; druhá: dokončeno, štítek zmizí.
  await novySouboj(6);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: MODRE });
  ok(d.ok && stavKouzleni() === '10|1|2', 'Modré blesky, akce 1 z 2: štítek nese postup: ' + stavKouzleni());
  ok(udalosti('ucinkuje') === '1', 'po první ze dvou akcí kouzlo začíná působit (je to předposlední akce)');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: MODRE });
  ok(d.ok && stitek() === 0 && udalosti('dokonceno') === '1', 'druhá akce kouzlení dokončí kouzlo a štítek zmizí sám: ' + stitek());

  // B) Hráč nesmí vybrat kouzlo, které postava nezná; jiné kouzlo než své nepůjde ani zadat; ruční počet akcí hráč nesmí
  await novySouboj(6);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: 3 });
  ok(d.s === 422 && /nezná/.test(d.error || ''), 'hráč vybere cizí kouzlo (Hyperprostor): 422 „' + d.error + '“');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', akci: 3 });
  ok(d.s === 422 && /PJ/.test(d.error || ''), 'ruční počet akcí hráč zadat nesmí: ' + d.error);
  ok(stitek() === 0, 'zamítnutá akce nic nezapsala');

  // C) Protoplazma: 4 akce. Zásah po 1. akci (před předposlední) kouzlo zruší; zásah po 3. (předposlední) ne.
  const goblinUtoci = async () => {
    let k = (await stav(pj)).kolo;
    for (let i = 0; i < 6 && k.aktivni_token_id !== gob; i++) { await api(pj, 'tah_konec', { mapa_id: MAPA }); k = (await stav(pj)).kolo; }
    await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
    await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gob, druh: 'utok', cil_token_id: sindor });
    const vy = (await stav(pj)).vyzvy;
    sql(`update souboj_vyzva set data = json_set(data, '$.zaklad', 3) where id=${vy[0].id}`);
    await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [4] });
    const obr = (await stav(pj)).vyzvy.find(x => x.druh === 'obrana');
    await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obr.id, volba: 'nebranit' });
    return api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: obr.id, hody: [1] });
  };
  await novySouboj(6);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: PROTOPLAZMA });
  ok(d.ok && stavKouzleni() === '17|1|4', 'Protoplazma, akce 1 ze 4: ' + stavKouzleni());
  ok(Number(udalosti('zahajeno')) >= 1, 'zahájení kouzlení je v logu');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: PROTOPLAZMA });
  ok(d.ok && stavKouzleni() === '17|2|4', 'akce 2 ze 4 (ještě nepůsobí): ' + stavKouzleni());
  let v = await goblinUtoci();
  ok(v.ok && v.vysledek && v.vysledek.kouzlo_stav === 'selhalo' && v.vysledek.kouzlo_nazev === 'Protoplazma', 'zásah před předposlední akcí: kouzlo Protoplazma se nezdařilo: ' + JSON.stringify(v.vysledek && [v.vysledek.kouzlo_stav, v.vysledek.kouzlo_nazev, v.vysledek.kouzlo_cena]));
  ok(stitek() === 0 && udalosti('selhalo') === '1', 'štítek zmizel, v logu je „selhalo“');

  // D) zásah po předposlední akci: kouzlo uspěje, štítek zůstává, dokončí se poslední akcí
  await novySouboj(6);
  for (let i = 0; i < 2; i++) await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: PROTOPLAZMA });
  sql(`update kolo_iniciativa set akce_zbyvajici = 0 where token_id=${sindor}`);
  await api(pj, 'tah_konec', { mapa_id: MAPA });
  // Sindor je po dvou akcích 2/4; ručně posuneme na předposlední (3/4), jako by už třetí akci odehrál v dalším kole
  sql("update aktivni_efekty set akci_hotovo = 3 where typ_entity='postava' and entita_id=2 and kouzlo_id=17");
  v = await goblinUtoci();
  ok(v.ok && v.vysledek && v.vysledek.kouzlo_stav === 'zdarilo' && stitek() === 1, 'zásah po předposlední akci: kouzlo se zdaří, štítek zůstává: ' + JSON.stringify(v.vysledek && v.vysledek.kouzlo_stav));

  // E) přerušení kouzla výslovnou akcí
  await novySouboj(6);
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: PROTOPLAZMA });
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', preruseni: true });
  ok(d.ok && stitek() === 0 && udalosti('preruseno') === '1', 'výslovné přerušení kouzla sundá štítek („preruseno“ v logu)');

  // F) PJ: ruční počet akcí (nestvůra, neznámé vyvolání); bez kouzla štítek nepočítá
  await novySouboj(6);
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', akci: 3 });
  ok(d.ok && stavKouzleni() === '-|1|3', 'PJ zadá ruční počet 3 akce: ' + stavKouzleni());
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok && stavKouzleni() === '-|2|3', 'další akce kouzlení se počítá (2 ze 3): ' + stavKouzleni());
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', akci: 25 });
  ok(d.s === 422, 'ruční počet nad 20 akcí se odmítne (422)');

  // G) jednoakční kouzlo: sesláno hned, štítek nevznikne (Falešná čepel „1 akce“)
  await novySouboj(6);
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: 416 });
  ok(d.ok && stitek() === 0 && Number(udalosti('dokonceno')) >= 1, 'kouzlo na 1 akci (Falešná čepel) je sesláno hned, štítek nevznikne');

  // H) kouzlo s vyvoláním „ihned“ nestojí akci kouzlení (Sestřel šípy): v seznamu má 0 akcí, jako akci kouzlení ho hra odmítne
  sql("insert ignore into postava_zna_kouzlo (postava_id, kouzlo_id) values (2, 241)");
  const seznam2 = await hr.evaluate(async (t) => (await fetch('api/kouzla_tvora.php?token_id=' + t)).json(), sindor);
  ok((seznam2.kouzla.find(k => k.id === 241) || {}).akci === 0, 'kouzlo „ihned“ má v seznamu 0 akcí');
  await novySouboj(6);
  const akcePred = sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni', kouzlo_id: 241 });
  ok(d.s === 422 && /ihned/.test(d.error || ''), 'akce kouzlení s kouzlem „ihned“ se odmítne: ' + d.error);
  ok(stitek() === 0 && sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${sindor}`) === akcePred, 'odmítnutá akce nestála nic (akcí zbývá stejně: ' + akcePred + ')');
  sql("delete from postava_zna_kouzlo where postava_id=2 and kouzlo_id=241");

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
