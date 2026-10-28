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
  const zb = process.env.ZBRANE !== '0';
  ok(funkce.zbrane_ini === zb, 'funkce zbrane_ini = ' + zb);
  const zbranId = (n) => parseInt(sqlVal(`select id from predmety where nazev='${n}' and typ='zbran'`), 10);
  const povId = (n) => parseInt(sqlVal(`select id from povolani where nazev='${n}'`), 10);
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set aktualni_hp=5, max_hp=5, uroven=1, unava=0 where id=2");
  }
  async function pridej(typ, col, row) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  const idRadku = (popis) => parseInt(sqlVal(`select id from iniciativa_bonusy where popis='${popis}'`), 10);
  const hodnota = (m) => m.ok ? m.vysledky[0].modifikator : ('chyba: ' + m.error);
  // jedna zkouška: postava drží zbraň, má povolání a úroveň, ohlásí úmysl, hodí 4 → vrátí odpověď hodu
  async function zkus(zbran, povolani, uroven, umysl, extra) {
    await uklid();
    sql(`update postavy set drzena_zbran_id=${zbran ? zbranId(zbran) : 'NULL'}, povolani_id=${povolani ? povId(povolani) : 'NULL'}, uroven=${uroven} where id=2`);
    const sindor = await pridej('postava', 13, 10);
    const gob = await pridej('nestvura', 14, 10);
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [{ token_id: sindor }, { token_id: gob }] });
    if (umysl) await api(hr, 'umysl_nastavit', { mapa_id: MAPA, token_id: sindor, umysl });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    return api(hr, 'souboj_hod', Object.assign({ mapa_id: MAPA, token_ids: [sindor], hod: 4 }, extra || {}));
  }
  let d;
  if (zb) {
    d = await zkus('Cep', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === -2 && d.bonusy.some(x => /Cep \(těžká obouruční zbraň\): −2/.test(x)), 'Cep v ruce + útok: −2 sám: ' + JSON.stringify([hodnota(d), d.bonusy]));
    d = await zkus('Cep', 'Kouzelník', 3, null);
    ok(hodnota(d) === -2, 'neohlášený úmysl: postih zbraně platí: ' + hodnota(d));
    d = await zkus('Cep', 'Kouzelník', 3, 'kouzleni');
    ok(hodnota(d) === 1, 'úmysl kouzlení: jen +1, zbraň nepočítá: ' + hodnota(d));
    d = await zkus('Cep', 'Kouzelník', 3, 'pohyb');
    ok(hodnota(d) === 0, 'úmysl pohyb: bez postihu: ' + hodnota(d));
    d = await zkus('Těžká kuše', 'Kouzelník', 3, 'strelba');
    ok(hodnota(d) === -3, 'Těžká kuše + střelba: −3: ' + JSON.stringify([hodnota(d), d.bonusy]));
    d = await zkus('Dlouhý meč', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === 0, 'Dlouhý meč (těžký jednoruční) postih nemá: ' + hodnota(d));
    d = await zkus('Nodachi', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === 0, 'Nodachi (těžké obouruční bez hvězdičky) postih nemá: ' + hodnota(d));
    d = await zkus('Obří kyj', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === -2 && d.bonusy.some(x => /supertěžká obouruční zbraň/.test(x)), 'supertěžká zbraň: −2: ' + JSON.stringify([hodnota(d), d.bonusy]));
    d = await zkus('Řetězový meč', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === 0, 'Řetězový meč postih nemá: ' + hodnota(d));
    d = await zkus(null, 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === 0, 'bez zbraně v ruce: bez postihu: ' + hodnota(d));
    // výjimky povolání
    d = await zkus('Cep', 'Bojovník', 5, null);   // bez úmyslu: bonus povolání se nepočítá, jen postih zbraně
    ok(hodnota(d) === -2, 'Bojovník 5. úrovně postih má: ' + hodnota(d));
    d = await zkus('Cep', 'Bojovník', 6, null);
    ok(hodnota(d) === 0 && d.bonusy.some(x => /postih −2 zrušen \(Bojovník od 6\. úrovně\)/.test(x)), 'Bojovník od 6. úrovně ho nemá, log říká proč: ' + JSON.stringify([hodnota(d), d.bonusy]));
    d = await zkus('Těžká kuše', 'Bojovník', 11, 'strelba');
    ok(hodnota(d) === -3, 'Bojovník 11. úrovně: těžká kuše postih má (výjimka pro boj tváří v tvář se kuše netýká): ' + hodnota(d));
    d = await zkus('Těžká kuše', 'Bojovník', 20, 'strelba');
    ok(hodnota(d) === 0, 'Bojovník od 12. úrovně: těžká kuše ztrácí postih: ' + hodnota(d));
    d = await zkus('Obouruční meč', 'Šermíř', 7, 'utok');
    ok(hodnota(d) === -2, 'Šermíř 7. úrovně: obouruční meč postih má: ' + hodnota(d));
    d = await zkus('Obouruční meč', 'Šermíř', 8, 'utok');
    ok(hodnota(d) === 0, 'Šermíř od 8. úrovně: obouruční meč bez postihu: ' + hodnota(d));
    d = await zkus('Cep', 'Šermíř', 30, 'utok');
    ok(hodnota(d) === -2, 'Šermíř: cep postih zůstává (výjimka jen pro obouruční meč): ' + hodnota(d));
    d = await zkus('Cep', 'Lovec stínů', 7, 'utok');
    ok(hodnota(d) === 0, 'Lovec stínů od 7. úrovně: bez postihu: ' + hodnota(d));
    d = await zkus('Obří kyj', 'Divoch', 2, 'utok');
    ok(hodnota(d) === 0, 'Divoch: Obří kyj bez postihu (tabulka supertěžkých zbraní): ' + hodnota(d));
    d = await zkus('Odachi', 'Divoch', 2, 'utok');
    ok(hodnota(d) === -2, 'Divoch: Odachi postih má: ' + hodnota(d));
    d = await zkus('Arbalest', 'Mechanik', 4, 'strelba');
    ok(hodnota(d) === 0, 'Mechanik: Arbalest bez postihu: ' + hodnota(d));
    d = await zkus('Vertolův luk', 'Lučištník', 4, 'strelba');
    ok(hodnota(d) === 0, 'Lučištník: Vertolův luk bez postihu: ' + hodnota(d));
    d = await zkus('Fuuma shuriken', 'Lupič', 4, 'strelba');
    ok(hodnota(d) === 0, 'Lupič: Fuuma shuriken bez postihu: ' + hodnota(d));
    // Bojovník / Šermíř a supertěžké zbraně podle toho, jestli jsou podobné meči (seznam potvrdil uživatel)
    d = await zkus('Obří kyj', 'Bojovník', 2, 'utok');
    ok(hodnota(d) === 0, 'Bojovník: supertěžký kyj (nepodobný meči) bez postihu i na 2. úrovni: ' + hodnota(d));
    d = await zkus('Flamberg', 'Bojovník', 2, 'utok');
    ok(hodnota(d) === -2, 'Bojovník 2. úrovně: Flamberg (podobný meči) postih má: ' + hodnota(d));
    d = await zkus('Flamberg', 'Bojovník', 6, null);
    ok(hodnota(d) === 0, 'Bojovník od 6. úrovně: každá zbraň tváří v tvář bez postihu: ' + hodnota(d));
    d = await zkus('Flamberg', 'Šermíř', 2, 'utok');
    ok(hodnota(d) === 0, 'Šermíř: Flamberg (podobný meči) bez postihu: ' + hodnota(d));
    d = await zkus('Warglaive', 'Šermíř', 2, 'utok');
    ok(hodnota(d) === 0, 'Šermíř: Warglaive bez postihu: ' + hodnota(d));
    d = await zkus('Dvě katany', 'Šermíř', 2, 'utok');
    ok(hodnota(d) === 0, 'Šermíř: Dvě katany bez postihu: ' + hodnota(d));
    d = await zkus('Obří kyj', 'Šermíř', 2, 'utok');
    ok(hodnota(d) === -2, 'Šermíř: Obří kyj (nepodobný meči) postih má: ' + hodnota(d));
    d = await zkus('Válečné kladivo (obouruční)', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === -2, 'nově doplněné Válečné kladivo (obouruční) má −2: ' + hodnota(d));
    d = await zkus('Dvě dýky', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === 0, 'kombinace Dvě dýky bez postihu: ' + hodnota(d));
    // ruční zaškrtnutí se u postavy ignoruje (jinak by postih platil dvakrát)
    d = await zkus('Cep', 'Kouzelník', 3, 'utok', { bonus_ids: [idRadku('Obouruční zbraň'), idRadku('Těžká kuše')] });
    ok(hodnota(d) === -2 && (d.ignorovano_rucne || []).length === 2, 'ručně zaškrtnuté Obouruční zbraň + Těžká kuše se ignorují: ' + JSON.stringify([hodnota(d), d.ignorovano_rucne]));
    // nestvůra: ručně zaškrtnuté řádky platí dál (nemá údaje o zbraních)
    await uklid();
    const sindor = await pridej('postava', 13, 10); const gob = await pridej('nestvura', 14, 10);
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [{ token_id: sindor }, { token_id: gob }] });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    d = await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 4, bonus_ids: [idRadku('Obouruční zbraň')] });
    ok(hodnota(d) === -2, 'nestvůra: ručně zaškrtnutá Obouruční zbraň platí dál: ' + hodnota(d));
    // stav postavy se promítá do klienta: dialog je pro postavy zamčený (data-auto="zbran")
    const zamk = await pj.evaluate(() => Array.from(document.querySelectorAll('#hodBonusyList .hod-bonus-check[data-auto="zbran"]')).length);
    ok(zamk === 2, 'v dialogu hodu jsou řádky Obouruční zbraň / Těžká kuše označené jako automatické: ' + zamk);
  } else {
    d = await zkus('Cep', 'Kouzelník', 3, 'utok');
    ok(hodnota(d) === 0, 'bez ručního SQL se postih zbraně nepočítá sám: ' + hodnota(d));
  }
  await uklid();
  sql("update postavy set drzena_zbran_id=NULL where id=2");
  await browser.close();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
