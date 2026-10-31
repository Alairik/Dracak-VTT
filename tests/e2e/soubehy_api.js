// Souběžné požadavky: dva hráči (nebo PJ a hráč) mění v jednu chvíli totéž. Úpravy životů a magenergie se nesmí ztratit (dřív se hodnota přečetla,
// v PHP přepočítala a zapsala zpět, takže dva současné zásahy daly jen jeden) a akce v souboji se nesmí utratit víckrát, než kolik jich tvor má.
// Spuštění po resetu dat: mysql dracak < ../fixture/seed.sql; node soubehy_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const LOG = ROOT + '/logs/aplikace.log';
const MAPA = 2;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  // Požadavky jednoho přihlášení se v PHP řadí za sebou (zámek relace), proto je souběh vidět až při víc přihlášeních naráz (PJ na dvou zařízeních, PJ a hráči).
  const pocetRelaci = 8;
  const relace = [];
  for (let i = 0; i < pocetRelaci; i++) {
    const p = await (await browser.newContext()).newPage();
    await p.goto(BASE + '/index.php'); await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    relace.push(p);
  }
  const page = relace[0];
  let rr = 0;
  const api = async (nazev, telo) => {
    const r = await relace[rr++ % pocetRelaci].request.post(BASE + '/hra/api/' + nazev + '.php', { data: telo });
    let j = {}; try { j = await r.json(); } catch (e) {}
    return { s: r.status(), ...j };
  };
  const naraz = (n, f) => Promise.all(Array.from({ length: n }, (_, i) => f(i)));

  // --- životy: 60 současných „−1“ ---
  sql('update postavy set max_hp=0, aktualni_hp=1000 where id=2');
  let r = await naraz(60, () => api('hp_uprava', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: -1 }));
  let hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(r.every(x => x.s === 200) && hp === 940, '60 současných zásahů −1 života: zbývá ' + hp + ' (čekáno 940), stavy ' + [...new Set(r.map(x => x.s))].join(','));
  // zásah a léčení současně
  r = await naraz(40, (i) => api('hp_uprava', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: i % 2 ? 3 : -2 }));
  hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(hp === 940 + 20 * 3 - 20 * 2, 'zásah −2 a léčení +3 současně: ' + hp + ' (čekáno 960)');
  // nikdy pod nulu
  sql('update postavy set aktualni_hp=5 where id=2');
  r = await naraz(30, () => api('hp_uprava', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: -1 }));
  hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(hp === 0, 'životy neklesnou pod nulu při 30 současných zásazích: ' + hp);
  // strop max_hp
  sql('update postavy set max_hp=20, aktualni_hp=10 where id=2');
  r = await naraz(30, () => api('hp_uprava', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: 1 }));
  hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(hp === 20, 'životy nepřesáhnou maximum při 30 současných léčeních: ' + hp);

  // --- magenergie ---
  sql('update postavy set max_magenergie=1000, aktualni_magenergie=500 where id=2');
  r = await naraz(50, () => api('magenergie_uprava', { mapa_id: MAPA, entita_id: 2, delta: 1 }));
  const me = parseInt(sqlVal('select aktualni_magenergie from postavy where id=2'), 10);
  ok(r.every(x => x.s === 200) && me === 550, '50 současných +1 magenergie: ' + me + ' (čekáno 550)');

  // krajní hodnoty: obří změna bez stropu max_hp se ořízne na rozsah sloupce, ne chyba serveru
  sql('update postavy set max_hp=0, aktualni_hp=10 where id=2');
  r = await api('hp_uprava', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: 99999999999 });
  hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(r.s === 200 && hp === 32767, 'obří léčení u postavy bez maxima skončí na horním okraji sloupce, ne na chybě: ' + r.s + ' ' + hp);
  r = await api('hp_uprava', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: -99999999999 });
  hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(r.s === 200 && hp === 0, 'obří zásah skončí na nule: ' + r.s + ' ' + hp);

  // --- zápisy z deníku (postava_stav): životy, únava, zkušenost ---
  sql('update postavy set max_hp=0, aktualni_hp=500, unava=0, zkusenost=0 where id=2');
  r = await naraz(40, () => api('postava_stav', { postava_id: 2, akce: 'hp', delta: -1 }));
  hp = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(r.every(x => x.s === 200) && hp === 460, 'deník: 40 současných −1 života: ' + hp + ' (čekáno 460)');
  r = await naraz(40, () => api('postava_stav', { postava_id: 2, akce: 'unava', delta: 1 }));
  const un = parseInt(sqlVal('select unava from postavy where id=2'), 10);
  ok(r.every(x => x.s === 200) && un === 40, 'deník: 40 současných +1 únavy: ' + un + ' (čekáno 40)');
  r = await naraz(40, () => api('postava_stav', { postava_id: 2, akce: 'zkusenost', delta: 5 }));
  const zk = parseInt(sqlVal('select zkusenost from postavy where id=2'), 10);
  ok(r.every(x => x.s === 200) && zk === 200, 'deník: 40 současných +5 zkušenosti: ' + zk + ' (čekáno 200)');

  // --- poslední kus lektvaru: 8 současných použití, účinek smí nastat jednou ---
  sql('delete from postava_lektvary where postava_id=2');
  sql('insert into postava_lektvary (postava_id, lektvar_id, mnozstvi) values (2, 1, 1)');
  r = await naraz(8, () => api('pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'lektvar', polozka_id: 1, mapa_id: MAPA }));
  const vypito = r.filter(x => x.s === 200 && x.ok).length;
  const zbylo = sqlVal('select count(*) from postava_lektvary where postava_id=2 and lektvar_id=1');
  ok(vypito === 1 && zbylo === '0', 'poslední kus lektvaru jde vypít jen jednou při 8 současných pokusech: vypito ' + vypito + ', zbylo řádků ' + zbylo + ' (' + r.map(x => x.s).join(',') + ')');
  // tři kusy, osm pokusů: přesně tři povedené
  sql('insert into postava_lektvary (postava_id, lektvar_id, mnozstvi) values (2, 1, 3)');
  r = await naraz(8, () => api('pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'lektvar', polozka_id: 1, mapa_id: MAPA }));
  const vypito3 = r.filter(x => x.s === 200 && x.ok).length;
  ok(vypito3 === 3 && sqlVal('select count(*) from postava_lektvary where postava_id=2') === '0', 'ze tří kusů jdou při 8 současných pokusech vypít přesně tři: ' + vypito3);

  // --- inventář: současné změny počtu kusů se nepřepíšou ---
  sql('insert into postava_lektvary (postava_id, lektvar_id, mnozstvi) values (2, 1, 100)');
  r = await naraz(30, () => api('postava_stav', { postava_id: 2, akce: 'inventar_zmena', typ_polozky: 'lektvar', polozka_id: 1, delta: 1 }));
  const ks = sqlVal('select mnozstvi from postava_lektvary where postava_id=2 and lektvar_id=1');
  ok(r.every(x => x.s === 200) && ks === '130', '30 současných +1 kus lektvaru: ' + ks + ' (čekáno 130)');
  sql('delete from postava_lektvary where postava_id=2');

  // --- souboj: víc současných akcí téhož tvora ---
  await api('souboj_konec', { mapa_id: MAPA });
  const stav = async () => (await page.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id, mapa_id: MAPA });
  const g = await (async () => { await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); return page.evaluate(() => window.MAPA.grid); })();
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r2) => ({ x: Math.round(c * colStep + ((r2 & 1) ? colStep / 2 : 0)), y: Math.round(r2 * rowStep) });
  const a = (await api('token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(10, 10).x, y: stred(10, 10).y })).token_id;
  const b = (await api('token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(14, 10).x, y: stred(14, 10).y })).token_id;
  await api('souboj_start', { mapa_id: MAPA, ucastnici: [a, b].map(t => ({ token_id: t })) });
  await api('souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api('souboj_hod', { mapa_id: MAPA, token_ids: [a], hod: 6 });
  await api('souboj_hod', { mapa_id: MAPA, token_ids: [b], hod: 1 });
  await api('souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  const d = await api('dalsi_tah', { mapa_id: MAPA });
  const kolo = (await stav()).kolo;
  const aktivni = kolo.aktivni_token_id;
  const celkem = parseInt(sqlVal(`select akce_celkem from kolo_iniciativa where token_id=${aktivni}`), 10);
  ok(d.ok && aktivni && celkem >= 1, 'souboj připraven, na tahu token ' + aktivni + ' s ' + celkem + ' akcemi');
  r = await naraz(10, () => api('tah_akce', { mapa_id: MAPA, token_id: aktivni, druh: 'jina' }));
  const uspechy = r.filter(x => x.ok).length;
  ok(r.every(x => x.s === 200 || x.s === 422) && uspechy >= 1 && uspechy <= celkem, '10 současných akcí: povedlo se ' + uspechy + ' z max. ' + celkem + ', ostatní odmítnuty (' + r.map(x => x.s).join(',') + ')');
  const zbyva = parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${aktivni}`), 10);
  ok(zbyva >= 0 && zbyva <= celkem, 'zbývající akce zůstaly v mezích: ' + zbyva);
  // současné „Další tah“: přesně jeden tvor je na tahu, nikdo nepadá na chybě serveru
  r = await naraz(8, () => api('dalsi_tah', { mapa_id: MAPA }));
  const kolo2 = (await stav()).kolo;
  ok(r.every(x => x.s < 500) && [null, a, b].includes(kolo2.aktivni_token_id), '8 současných „Další tah“: bez chyby serveru, na tahu nikdo nebo jeden z účastníků (' + kolo2.aktivni_token_id + ')');
  await api('souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id, mapa_id: MAPA });

  // chybový log: žádný deadlock ani jiná chyba databáze
  const zaznamy = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  ok(zaznamy.length === 0, 'chybový log po souběžných požadavcích je prázdný' + (zaznamy.length ? ': ' + zaznamy.slice(0, 3).map(z => z.zprava.slice(0, 120)).join(' | ') : ''));
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
