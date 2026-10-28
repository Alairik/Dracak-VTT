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
  const funkce = await pj.evaluate(() => window.MAPA.funkce);
  const v2 = process.env.V2 !== '0';
  ok(funkce.iniciativa_v2 === v2, 'funkce iniciativa_v2 = ' + v2);
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set aktualni_hp=5, max_hp=5, uroven=1 where id=2");
  }
  async function pridej(typ, col, row) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  const instOf = (tok) => parseInt(sqlVal(`select entita_id from tokeny where id=${tok}`), 10);
  const kiRow = (tok) => JSON.parse(sqlVal(`select json_object('c', akce_celkem, 'z', akce_zbyvajici${v2 ? ", 'zv', zavazek, 'za', zavazek_akci, 'zh', zavazek_hotovo, 'ah', akce_hotovo" : ''}) from kolo_iniciativa where token_id=${tok}`));
  const idRadku = (popis) => parseInt(sqlVal(`select id from iniciativa_bonusy where popis='${popis}'`), 10);
  async function start(tokeny) {
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: tokeny.map(t => ({ token_id: t })) });
  }
  const umysl = (page, tok, u, n) => api(page, 'umysl_nastavit', Object.assign({ mapa_id: MAPA, token_id: tok, umysl: u }, n !== undefined ? { utoku_za_2_kola: n } : {}));
  const hod = (page, tok, h, extra) => api(page, 'souboj_hod', Object.assign({ mapa_id: MAPA, token_ids: [tok], hod: h }, extra || {}));

  // ===================== A: vícenásobné útoky jedním polem =====================
  await uklid();
  let sindor = await pridej('postava', 13, 10);
  let gob = await pridej('nestvura', 14, 10);
  await start([sindor, gob]);
  let d;
  if (v2) {
    d = await umysl(hr, sindor, 'utok', 9);
    ok(d.s === 422, 'víc než 8 útoků za 2 kola tabulka nemá: ' + d.error);
    d = await umysl(hr, sindor, 'utok', 1);
    ok(d.s === 422, 'méně než 2 útoky za 2 kola nejde: ' + d.error);
    d = await umysl(hr, sindor, 'utok', 4);
    ok(d.ok, 'hráč ohlásí útok, 4 útoky za 2 kola');
    let st = (await stav(pj)).kolo;
    ok(st.iniciativa_v2 === true && st.ini_utoky['4'] === 6 && st.ini_utoky['8'] === 18, 'klient dostane tabulku útoků: ' + JSON.stringify(st.ini_utoky));
    const u = (await stav(hr)).ucastnici.find(x => x.token_id === sindor);
    ok(u.umysl === 'utok' && u.utoku_za_2_kola === 4, 'uloženo u účastníka: ' + JSON.stringify([u.umysl, u.utoku_za_2_kola]));
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    d = await hod(hr, sindor, 3, { bonus_ids: [idRadku('3 útoky za 2 kola'), idRadku('2 útoky za 1 kolo'), idRadku('5 útoků za 2 kola')] });
    ok(d.ok && d.vysledky[0].modifikator === 6, 'bonus +6 jen z pole (ručně zaškrtnuté řádky tabulky se neslučují): ' + (d.error || d.vysledky[0].modifikator));
    ok((d.ignorovano_rucne || []).length === 3, 'všechny tři ručně zaškrtnuté řádky se ignorovaly: ' + JSON.stringify(d.ignorovano_rucne));
    ok(d.vysledky[0].akce_celkem === 3 && d.vysledky[0].zavazek === 'utok' && d.vysledky[0].zavazek_akci === 2, '3+6 = 9 → 3 akce, slib: útok, aspoň 2 akce: ' + JSON.stringify(d.vysledky[0]));
    ok(JSON.stringify(kiRow(sindor)) === JSON.stringify({ c: 3, z: 3, zv: 'utok', za: 2, zh: 0, ah: 0 }), 'v DB: ' + JSON.stringify(kiRow(sindor)));
    // úmysl Pohyb: pole zůstane uložené, ale bonus se nepočítá
    await uklid();
    sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
    await start([sindor, gob]);
    await umysl(hr, sindor, 'utok', 4);
    await umysl(hr, sindor, 'pohyb');
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    d = await hod(hr, sindor, 3);
    ok(d.ok && d.vysledky[0].modifikator === 0 && d.vysledky[0].zavazek === null, 'při úmyslu Pohyb se vícenásobné útoky nepočítají a nic neslibují: ' + JSON.stringify([d.vysledky[0].modifikator, d.vysledky[0].zavazek]));
    const ucP = (await stav(hr)).ucastnici.find(x => x.token_id === sindor);
    ok(ucP.utoku_za_2_kola === 4, 'pole zůstává uložené mezi úmysly');
  } else {
    d = await umysl(hr, sindor, 'utok', 4);
    ok(d.ok, 'bez ručního SQL se pole tiše ignoruje');
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    d = await hod(hr, sindor, 3, { bonus_ids: [idRadku('3 útoky za 2 kola'), idRadku('2 útoky za 1 kolo')] });
    ok(d.ok && d.vysledky[0].modifikator === 9, 'bez SQL platí staré zaškrtávání (3 + 6 = 9, dosavadní chování): ' + (d.error || d.vysledky[0].modifikator));
  }

  if (v2) {
  // ===================== B: kouzlení — první akce a polovina akcí =====================
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await start([sindor, gob]);
  await umysl(hr, sindor, 'kouzleni');
  await umysl(pj, gob, 'jina');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  d = await hod(hr, sindor, 6);   // 6 + 1 (kouzlení) = 7 -> 3 akce
  ok(d.ok && d.vysledky[0].akce_celkem === 3 && d.vysledky[0].zavazek === 'kouzleni' && d.vysledky[0].zavazek_akci === 2, 'kouzlení +1: 3 akce, slib kouzlení aspoň 2: ' + JSON.stringify(d.vysledky[0]));
  await hod(pj, gob, 1);   // goblin 1 akce
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  ok((await stav(pj)).kolo.aktivni_token_id === sindor, 'Sindor je na tahu');
  const pjSlib = (await stav(pj)).iniciativa.find(x => x.token_id === sindor);
  ok(pjSlib.zavazek === 'kouzleni' && pjSlib.zavazek_akci === 2 && pjSlib.zavazek_hotovo === 0 && pjSlib.akce_hotovo === 0, 'klient vidí slib: ' + JSON.stringify([pjSlib.zavazek, pjSlib.zavazek_akci, pjSlib.zavazek_hotovo]));
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok, 'kouzlení jako první akce projde');
  ok(JSON.stringify(kiRow(sindor)) === JSON.stringify({ c: 3, z: 2, zv: 'kouzleni', za: 2, zh: 1, ah: 1 }), 'počítadla: ' + JSON.stringify(kiRow(sindor)));
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  ok(d.ok, 'jiná akce teď ještě projde (zbývá místo na druhé kouzlení)');
  // druhá půlka: PJ ukončí goblinův tah
  let k = (await stav(pj)).kolo;
  if (k.aktivni_token_id === gob) await api(pj, 'tah_konec', { mapa_id: MAPA });
  k = (await stav(pj)).kolo;
  ok(k.aktivni_token_id === sindor && k.polovina === 2, 'druhá půlka, na tahu Sindor');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok(d.ok && (await stav(pj)).kolo.cislo_kola === 2, 'druhé kouzlení projde (slib splněn 2/2, kolo končí): ' + (d.error || ''));
  const ztrataPocet = () => parseInt(sqlVal("select count(*) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where ae.typ_entity='postava' and ae.entita_id=2 and e.nazev like 'Ztráta iniciativy%'"), 10);
  ok(ztrataPocet() === 0, 'splněný slib: žádná ztráta iniciativy');

  // Polovina akcí se nehlídá zákazem — kdo ji nesplní, přijde o iniciativu v příštím kole (−3)
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await start([sindor, gob]);
  await umysl(hr, sindor, 'kouzleni');
  await umysl(pj, gob, 'jina');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(hr, sindor, 6); await hod(pj, gob, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  k = (await stav(pj)).kolo;
  if (k.aktivni_token_id === gob) await api(pj, 'tah_konec', { mapa_id: MAPA });
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  ok(d.ok, 'poslední akce smí být jiná než kouzlení (polovina akcí se nehlídá zákazem): ' + (d.error || ''));
  ok((await stav(pj)).kolo.cislo_kola === 2, 'kolo skončilo');
  ok(ztrataPocet() === 1 && parseInt(sqlVal("select ae.zbyva_kol from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where ae.typ_entity='postava' and ae.entita_id=2 and e.nazev like 'Ztráta iniciativy%'"), 10) === 1,
    'nesplněný slib (kouzlení 1/2): Sindor dostal efekt Ztráta iniciativy na 1 kolo');
  const logZ = sqlVal("select count(*) from svet_udalosti where typ='efekt_aplikovan' and payload like '%nesplněný slib%'");
  ok(parseInt(logZ, 10) >= 1, 'v logu je důvod: ' + logZ);
  await umysl(hr, sindor, 'jina');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  d = await hod(hr, sindor, 4);
  ok(d.ok && d.vysledky[0].modifikator === -3 && (d.bonusy || []).some(x => /Automatická ztráta iniciativy/.test(x)),
    'v příští iniciativě −3 (4 − 3 = 1 → 1 akce): ' + JSON.stringify([d.vysledky && d.vysledky[0].modifikator, d.bonusy]));
  ok(d.vysledky[0].zavazek === null, 'bez ohlášeného úkonu žádný nový slib');

  // První akce kola jiná než slib: nezakazuje se, ale hned se potrestá (−3 v příštím kole); PJ slib může zrušit
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await start([sindor, gob]);
  await umysl(hr, sindor, 'kouzleni');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(hr, sindor, 6); await hod(pj, gob, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  sql("delete from svet_udalosti where typ='efekt_aplikovan' and payload like '%první akce kola měla být%'");
  const efZtr = () => sqlVal("select concat(ae.zbyva_kol, '/', ae.nasazeno_kolo) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where ae.typ_entity='postava' and ae.entita_id=2 and e.nazev like 'Ztráta iniciativy%'");
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  ok(d.ok, 'první akce jiná než slíbené kouzlení se neodmítne: ' + (d.error || ''));
  ok(efZtr() === '1/1', 'ale hned se potrestá: efekt Ztráta iniciativy na 1 kolo, nasazen v kole 1: ' + efZtr());
  ok(parseInt(sqlVal("select count(*) from svet_udalosti where typ='efekt_aplikovan' and payload like '%první akce kola měla být kouzlení%'"), 10) === 1, 'v logu je důvod');
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  k = (await stav(pj)).kolo;
  if (k.aktivni_token_id === gob) await api(pj, 'tah_konec', { mapa_id: MAPA });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'kouzleni' });
  ok((await stav(pj)).kolo.cislo_kola === 2, 'kolo skončilo');
  ok(efZtr() === '1/1', 'půlka akcí je splněná, ale trest za první akci zůstává jen jednou (ne dvakrát), drží do příštího kola: ' + efZtr());
  await umysl(hr, sindor, 'jina');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  d = await hod(hr, sindor, 4);
  ok(d.ok && d.vysledky[0].modifikator === -3, 'v kole 2 má −3: ' + JSON.stringify(d.vysledky && d.vysledky[0].modifikator));
  // efekt po kole 2 skončí
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await hod(pj, gob, 1);
  // PJ: zrušení slibu = žádný trest
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await start([sindor, gob]);
  await umysl(hr, sindor, 'kouzleni');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(hr, sindor, 6); await hod(pj, gob, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  d = await api(hr, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'zavazek_zrusit' });
  ok(d.s === 403, 'hráč slib zrušit nesmí');
  d = await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'zavazek_zrusit' });
  ok(d.ok && kiRow(sindor).zv === null, 'PJ slib zruší');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  ok(d.ok && ztrataPocet() === 0, 'po zrušení slibu smí hráč první akci udělat jinak bez trestu');
  // Efekt z minulého kola se při dalším porušení obnoví (neskončí po kole 2, ale drží i kolo 3)
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await start([sindor, gob]);
  await umysl(hr, sindor, 'kouzleni');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(hr, sindor, 6); await hod(pj, gob, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });   // trest v kole 1
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  k = (await stav(pj)).kolo;
  if (k.aktivni_token_id === gob) await api(pj, 'tah_konec', { mapa_id: MAPA });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });
  ok((await stav(pj)).kolo.cislo_kola === 2, 'kolo 2');
  await umysl(hr, sindor, 'kouzleni');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  d = await hod(hr, sindor, 6);   // 6 − 3 + 1 = 4 → 2 akce, slib: aspoň 1
  ok(d.ok && d.vysledky[0].modifikator === -2 && d.vysledky[0].zavazek === 'kouzleni', 'kolo 2: −3 za trest, +1 za kouzlení: ' + JSON.stringify(d.vysledky && d.vysledky[0]));
  await hod(pj, gob, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'cekani' });   // opět porušení v kole 2
  ok(efZtr() === '1/2', 'efekt z minulého kola se obnovil pro kolo 3: ' + efZtr());

  // ===================== C: obrana se počítá k útoku, do první akce ne =====================
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await start([gob, sindor]);
  await umysl(pj, gob, 'utok');
  await umysl(hr, sindor, 'utok', 4);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(pj, gob, 6, { jiny_bonus: 6 }); await hod(hr, sindor, 3);   // Sindor 3+6 = 9 → 3 akce, slib útok aspoň 2
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  ok((await stav(pj)).kolo.aktivni_token_id === gob, 'goblin je na tahu');
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gob, druh: 'utok', cil_token_id: sindor });
  let vy = (await stav(pj)).vyzvy;
  sql(`update souboj_vyzva set data = json_set(data, '$.zaklad', 3) where id=${vy[0].id}`);
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [2] });
  vy = (await stav(pj)).vyzvy;
  const ob = vy.find(x => x.druh === 'obrana');
  await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: ob.id, volba: 'branit' });
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: ob.id, hody: [3] });
  ok(d.ok, 'Sindor se brání akcí');
  ok(kiRow(sindor).zh === 1 && kiRow(sindor).ah === 0 && kiRow(sindor).z === 2, 'obrana se počítá k slíbenému útoku (1/2), ale ne jako vlastní první akce: ' + JSON.stringify(kiRow(sindor)));

  }

  // ===================== D: útok na vyřazeného =====================
  await uklid();
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  gobInst = instOf(gob);
  sql(`update nestvura_instance set aktualni_hp=0, max_hp=30 where id=${gobInst}`);
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await start([sindor, gob]);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(pj, sindor, 6); await hod(pj, gob, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  vy = (await stav(pj)).vyzvy;
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [3] });
  vy = (await stav(pj)).vyzvy;
  const obV = vy.find(x => x.druh === 'obrana');
  ok(obV && obV.duvod_nebrani === 'vyřazený' && obV.nehybny === true && obV.volba === 'nebranit' && obV.nasobek === 2 && obV.nasobek_duvod === 'vyřazený',
    'goblin na nule životů: vyřazený = nehybný, nebrání se, zranění ×2: ' + JSON.stringify(obV && [obV.duvod_nebrani, obV.nehybny, obV.nasobek]));
  ok(obV.opravy.some(o => /nehybný \(vyřazený\)/.test(o.nazev) && o.hodnota === -5), 'oprava nehybného −5: ' + JSON.stringify(obV.opravy));
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obV.id, volba: 'branit' });
  ok(d.s === 422 && /je vyřazený/.test(d.error), 'vyřazenému nejde nastavit bránění zbraní: ' + d.error);
  await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obV.id, zaklad: 4 });
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: obV.id, hody: [1] });
  ok(d.ok && d.vysledek.nasobek === 2 && d.vysledek.zraneni === d.vysledek.zraneni_zaklad * 2, 'zranění vyřazeného ×2: ' + (d.error || JSON.stringify([d.vysledek.zraneni_zaklad, d.vysledek.zraneni])));
  // vyřazená postava vyšší úrovně
  await uklid();
  sql("update postavy set uroven=12, aktualni_hp=0, max_hp=40 where id=2");
  sindor = await pridej('postava', 13, 10); gob = await pridej('nestvura', 14, 10);
  await api(pj, 'token_uprava', { token_id: sindor, zmeny: { smer: 0 } });
  await start([gob, sindor]);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await hod(pj, gob, 6); await hod(pj, sindor, 1);
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gob, druh: 'utok', cil_token_id: sindor });
  vy = (await stav(pj)).vyzvy;
  sql(`update souboj_vyzva set data = json_set(data, '$.zaklad', 3) where id=${vy[0].id}`);
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [4] });
  const obS = (await stav(pj)).vyzvy.find(x => x.druh === 'obrana');
  ok(obS && obS.nasobek === 9 && obS.nehybny === true, 'vyřazená postava 12. úrovně: násobek nejvýš ×9: ' + JSON.stringify(obS && [obS.nasobek, obS.nehybny]));

  await uklid();
  await browser.close();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
