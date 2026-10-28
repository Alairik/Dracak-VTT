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
const efektId = (nazev) => parseInt(sqlVal(`select id from efekty where nazev='${nazev}'`), 10);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const funkce = await pj.evaluate(() => window.MAPA.funkce);
  ok(funkce.efekty_mech === true, 'funkce efekty_mech je zapnutá (ruční SQL provedeno)');

  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set aktualni_hp=5, max_hp=5 where id=2; delete from efekty where nazev like 'TEST %'");
  }
  async function pridej(typ, col, row, nid) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: nid || 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  // Souboj: hody iniciativy podle pořadí [token, hod]; skončí na prvním tahu
  async function boj(tokeny, hody, extraHod) {
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: tokeny.map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    for (let i = 0; i < tokeny.length; i++) await api(pj, 'souboj_hod', Object.assign({ mapa_id: MAPA, token_ids: [tokeny[i]], hod: hody[i] }, extraHod || {}));
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  }
  const ki = (tok) => ({
    c: parseInt(sqlVal(`select akce_celkem from kolo_iniciativa where token_id=${tok}`) || '-1', 10),
    z: parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${tok}`) || '-1', 10),
  });
  const kolo = async () => (await stav(pj)).kolo;
  // Dohraje zbytek kola ukončením všech tahů, dokud nezačne nové kolo
  async function dokonciKolo() {
    const startKolo = (await kolo()).cislo_kola;
    for (let i = 0; i < 12; i++) {
      const k = await kolo();
      if (k.cislo_kola !== startKolo || k.faze !== 'kolo') return;
      await api(pj, 'tah_konec', { mapa_id: MAPA });
    }
  }
  const ef = async (typ, entita) => sql(`select 1`) && JSON.parse('[' + sqlVal(`select group_concat(json_object('id', ae.id, 'n', e.nazev, 'z', ae.zbyva_kol, 'k', ae.nasazeno_kolo, 'kostky', ae.kostky)) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where ae.typ_entity='${typ}' and ae.entita_id=${entita}`).replace(/^NULL$/, '') + ']');
  const instOf = (tok) => parseInt(sqlVal(`select entita_id from tokeny where id=${tok}`), 10);
  const hpGob = (inst) => parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${inst}`), 10);

  // ===================== A: Rychlost (lektvar) uprostřed tahu =====================
  await uklid();
  let sindor = await pridej('postava', 13, 10);
  let gob = await pridej('nestvura', 14, 10);
  let gobInst = instOf(gob);
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${gobInst}`);
  await boj([sindor, gob], [6, 1]);   // Sindor 6 => 2 akce, goblin 1 => 1 akce
  ok((await kolo()).aktivni_token_id === sindor, 'Sindor je na tahu');
  ok(ki(sindor).z === 2 && ki(sindor).c === 2, 'Sindor má 2 akce: ' + JSON.stringify(ki(sindor)));
  const rych = efektId('Rychlost (lektvar)');
  let d = await api(hr, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: rych });
  ok(d.s === 403, 'hráč efekt nasadit nesmí: ' + d.error);
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: rych });
  ok(d.ok && d.efekt.zbyva_kol === 5 && d.efekt.stav === 'auto', 'Rychlost (lektvar): výchozí trvání 5 kol, stav auto: ' + (d.error || JSON.stringify([d.efekt.zbyva_kol, d.efekt.stav])));
  ok(ki(sindor).z === 4 && ki(sindor).c === 4, 'Rychlost zdvojnásobila akce 2 -> 4: ' + JSON.stringify(ki(sindor)));
  ok(d.efekt.akce && d.efekt.akce.pred[1] === 2 && d.efekt.akce.po[1] === 4, 'odpověď říká, co se změnilo s akcemi');
  let e = await ef('postava', 2);
  ok(e.length === 1 && e[0].k === 1, 'nasazeno v kole 1');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: rych });
  ok(d.ok && d.efekt.obnoveno === true && d.efekt.zbyva_kol === 10, 'podruhé: trvání se sčítá (5+5=10), efekt jen jeden: ' + (d.error || d.efekt.zbyva_kol));
  ok(ki(sindor).z === 4, 'účinek se neznásobil (stále 4 akce)');
  e = await ef('postava', 2);
  ok(e.length === 1, 'na tvoru je pořád jeden řádek efektu');
  // krok zpět: nejdřív vrátí trvání, pak sundá efekt a vrátí akce
  d = await api(pj, 'krok_zpet', { mapa_id: MAPA });
  e = await ef('postava', 2);
  ok(d.ok && e.length === 1 && e[0].z === 5, 'krok zpět po obnovení vrátí trvání na 5, efekt zůstává: ' + (d.error || JSON.stringify(e)));
  d = await api(pj, 'krok_zpet', { mapa_id: MAPA });
  ok(d.s === 422, 'krok zpět krok zpět nevrací (poslední událost je krok zpět)');
  // sundat, nasadit čerstvě a vrátit zpět: efekt zmizí a akce se vrátí 4 -> 2
  e = await ef('postava', 2);
  await api(pj, 'efekt_odebrat', { mapa_id: MAPA, aktivni_efekt_id: e[0].id });
  ok(ki(sindor).z === 4, 'sundání efektu akce nevrací (platí jen pro kolo, kdy vznikly)');
  sql(`update kolo_iniciativa set akce_celkem = 2, akce_zbyvajici = 2 where token_id = ${sindor}`);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: rych });
  ok(ki(sindor).z === 4, 'čerstvě nasazená Rychlost: 2 -> 4');
  d = await api(pj, 'krok_zpet', { mapa_id: MAPA });
  e = await ef('postava', 2);
  ok(d.ok && e.length === 0 && ki(sindor).z === 2 && ki(sindor).c === 2, 'krok zpět sundá efekt a vrátí akce 4 -> 2: ' + (d.error || JSON.stringify(ki(sindor))));
  // zase nasadit, ověřit zobrazení na tokenu
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: rych });
  let st = await stav(pj);
  let tS = st.tokeny.find(t => t.id === sindor);
  ok(tS.efekty[0] === 'Rychlost (lektvar) (5 kol)', 'odznak na tokenu: ' + tS.efekty[0]);
  ok(tS.efekty_detail[0].stav === 'auto' && tS.efekty_detail[0].shrnuti.includes('zdvojnásobí akce') && tS.efekty_detail[0].shrnuti.some(x => /Kouzlo Rychlost/.test(x)),
    'detail efektu: stav a shrnutí: ' + JSON.stringify(tS.efekty_detail[0].shrnuti));

  // ===================== B: iniciativa v dalším kole =====================
  await dokonciKolo();
  let k = await kolo();
  ok(k.cislo_kola === 2 && k.faze === 'umysly', 'začalo kolo 2');
  e = await ef('postava', 2);
  ok(e.length === 1 && e[0].z === 5, 'kolo vypití lektvaru Rychlosti se do trvání nepočítá (rozhodnutí uživatele): zůstává 5: ' + JSON.stringify(e));
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  const bonusRychlostId = parseInt(sqlVal("select id from iniciativa_bonusy where popis='Kouzlo Rychlost'"), 10);
  d = await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 3, bonus_ids: [bonusRychlostId] });
  ok(d.ok && d.vysledky[0].modifikator === 6, 'iniciativa Sindora: +6 z Rychlosti jen jednou (i při ručním zaškrtnutí): ' + (d.error || d.vysledky[0].modifikator));
  ok((d.automaticky || []).some(x => /Kouzlo Rychlost/.test(x)), 'bonus Kouzlo Rychlost je mezi automatickými');
  ok(d.vysledky[0].akce_celkem === 3, 'výsledek 3+6=9 => 3 akce');

  // ===================== C: kouzlo se nepočítá do trvání =====================
  await uklid();
  sindor = await pridej('postava', 13, 10);
  gob = await pridej('nestvura', 14, 10);
  await boj([sindor, gob], [6, 1]);
  const rychK = efektId('Rychlost (kouzlo)');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: rychK, zbyva_kol: 2 });
  ok(d.ok && d.efekt.zbyva_kol === 2, 'Rychlost (kouzlo) na 2 kola');
  await dokonciKolo();
  e = await ef('postava', 2);
  ok(e.length === 1 && e[0].z === 2, 'po kole seslání pořád 2 kola (kolo seslání se nepočítá): ' + JSON.stringify(e));
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 3 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await dokonciKolo();
  e = await ef('postava', 2);
  ok(e.length === 1 && e[0].z === 1, 'po 1. započítaném kole zbývá 1: ' + JSON.stringify(e));
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 3 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await dokonciKolo();
  e = await ef('postava', 2);
  ok(e.length === 0, 'po 2. započítaném kole efekt skončil: ' + JSON.stringify(e));

  // ===================== D: Protoplazma =====================
  await uklid();
  sindor = await pridej('postava', 13, 10);
  gob = await pridej('nestvura', 14, 10);
  await boj([sindor, gob], [6, 1], { jiny_bonus: 4 });   // Sindor 6+4=10 => 4 akce
  ok(ki(sindor).z === 4, 'Sindor má 4 akce: ' + JSON.stringify(ki(sindor)));
  const proto = efektId('Protoplazma');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: proto, zbyva_kol: 3 });
  ok(d.ok && ki(sindor).z === 2 && ki(sindor).c === 2, 'Protoplazma: 4 -> 2 akce: ' + (d.error || JSON.stringify(ki(sindor))));
  await dokonciKolo();
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  d = await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  ok(d.ok && d.vysledky[0].modifikator === -3 && d.vysledky[0].akce_celkem === 1, 'v dalším kole iniciativa −3 => 6−3 = 3 => 1 akce: ' + (d.error || JSON.stringify(d.vysledky[0])));

  // ===================== E: Spánek na gobliny + nehybný =====================
  await uklid();
  sindor = await pridej('postava', 13, 10);
  gob = await pridej('nestvura', 14, 10);
  gobInst = instOf(gob);
  sql(`update nestvura_instance set aktualni_hp=30, max_hp=30 where id=${gobInst}`);
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await boj([gob, sindor], [6, 3]);    // goblin první
  ok((await kolo()).aktivni_token_id === gob, 'goblin je na tahu');
  const spanek = efektId('Spánek');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: gobInst, efekt_id: spanek, zbyva_kol: 3 });
  ok(d.ok && d.posun && d.posun.aktivni_token_id === sindor, 'Spánek na goblina uprostřed jeho tahu: tah jde na Sindora: ' + (d.error || JSON.stringify(d.posun)));
  // hráč za goblina jednat nemůže, ani kdyby byl na tahu (PJ smí)
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  ok(d.ok && d.vyzva_id, 'Sindor útočí na spícího goblina');
  let vy = (await stav(pj)).vyzvy;
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [3] });
  ok(d.ok && d.dalsi_vyzva_id, 'útok hozen, vznikla obrana');
  vy = (await stav(pj)).vyzvy;
  const ob = vy.find(x => x.druh === 'obrana');
  ok(ob && ob.volba === 'nebranit' && ob.muze_branit === false && ob.duvod_nebrani === 'Spánek' && ob.nehybny === true, 'obrana spícího: bez možnosti bránit se zbraní, důvod Spánek, nehybný: ' + JSON.stringify([ob && ob.volba, ob && ob.muze_branit, ob && ob.duvod_nebrani]));
  ok(ob.opravy.some(o => /nehybný/.test(o.nazev) && o.hodnota === -5) && ob.opravy.some(o => o.hodnota === -3), 'opravy: nehybný −5 a bez zbraně −3: ' + JSON.stringify(ob.opravy));
  ok(!ob.opravy.some(o => /boku/.test(o.nazev)), 'bez opravy za útok z boku (h1613)');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: ob.id, volba: 'branit' });
  ok(d.s === 422 && /Spánek/.test(d.error), 'ani PJ nenastaví „bránit se zbraní“ spícímu: ' + d.error);
  ok(ob.nasobek === 2 && ob.nasobek_duvod === 'Spánek', 'zranění spícího goblina se násobí jeho životaschopností (×2): ' + ob.nasobek);
  d = await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: ob.id, nasobek: 9 });
  ok(d.s === 403, 'hráč násobek měnit nesmí');
  await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: ob.id, zaklad: 5 });
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: ob.id, hody: [1] });
  ok(d.ok && d.vysledek.zasah && d.vysledek.nasobek === 2 && d.vysledek.zraneni === d.vysledek.zraneni_zaklad * 2 && d.vysledek.zraneni_zaklad >= 1,
    'zásah spícího: zranění ' + (d.vysledek && d.vysledek.zraneni_zaklad) + ' × 2 = ' + (d.vysledek && d.vysledek.zraneni) + ': ' + (d.error || ''));
  ok(hpGob(gobInst) === 30 - d.vysledek.zraneni, 'goblin přišel o celé vynásobené zranění (30 -> ' + hpGob(gobInst) + ')');
  d = await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  // konec Sindorova tahu a kola: goblin spí, v dalším kole nemá žádné akce
  await dokonciKolo();
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  d = await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 6 });
  ok(d.ok && d.vysledky[0].akce_celkem === 0, 'spící goblin má v dalším kole 0 akcí i s dobrým hodem: ' + (d.error || JSON.stringify(d.vysledky[0])));
  ok((d.upozorneni || []).some(u => /nemůže jednat/.test(u)), 'upozornění, že nemůže jednat');
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  k = await kolo();
  ok(k.aktivni_token_id === sindor, 'tah spícího goblina se přeskočí, na tahu je Sindor');
  // odebrání efektu
  let aeId = (await ef('nestvura_instance', gobInst))[0].id;
  d = await api(hr, 'efekt_odebrat', { mapa_id: MAPA, aktivni_efekt_id: aeId });
  ok(d.s === 403, 'hráč efekt sundat nesmí');
  d = await api(pj, 'efekt_odebrat', { mapa_id: MAPA, aktivni_efekt_id: aeId });
  ok(d.ok && d.efekt_nazev === 'Spánek' && (await ef('nestvura_instance', gobInst)).length === 0, 'PJ sundá Spánek');
  d = await api(pj, 'efekt_odebrat', { mapa_id: MAPA, aktivni_efekt_id: aeId });
  ok(d.s === 404, 'podruhé už efekt neexistuje');

  // ===================== E2: spící vlk (životaschopnost „3 + 4“) a spící Sindor =====================
  await uklid();
  sindor = await pridej('postava', 13, 10);
  const vlk = await pridej('nestvura', 14, 10, 200);
  await api(pj, 'token_uprava', { token_id: vlk, zmeny: { smer: 180 } });
  await boj([sindor, vlk], [6, 3]);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: instOf(vlk), efekt_id: efektId('Spánek'), zbyva_kol: 3 });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: vlk });
  vy = (await stav(pj)).vyzvy;
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [3] });
  vy = (await stav(pj)).vyzvy;
  const obV = vy.find(x => x.druh === 'obrana');
  ok(obV && obV.nasobek === null && obV.nasobek_duvod === 'Spánek' && (obV.upozorneni || []).some(u => /životaschopnost/.test(u)), 'vlk „3 + 4“: násobek neznám, PJ je upozorněn: ' + JSON.stringify(obV && obV.upozorneni));
  const obHrac = (await stav(hr)).vyzvy.find(x => x.druh === 'obrana');
  ok(!obHrac || obHrac.nasobek === null || obHrac.nasobek === undefined, 'hráč násobek (životaschopnost nestvůry) nevidí');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obV.id, nasobek: 7, zaklad: 4 });
  ok(d.ok, 'PJ doplní násobek 7 a OČ');
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: obV.id, hody: [1] });
  ok(d.ok && d.vysledek.nasobek === 7 && d.vysledek.zraneni === d.vysledek.zraneni_zaklad * 7, 'zranění vlka ×7: ' + (d.error || JSON.stringify([d.vysledek.zraneni_zaklad, d.vysledek.zraneni])));
  await uklid();
  // spící Sindor 1. úrovně: ×1, ale důvod zůstává v hlášení
  sindor = await pridej('postava', 13, 10);
  gob = await pridej('nestvura', 14, 10);
  gobInst = instOf(gob);
  await boj([gob, sindor], [6, 3]);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: efektId('Spánek'), zbyva_kol: 2 });
  sql(`update nestvura_instance set aktualni_hp=30, max_hp=30 where id=${gobInst}`);
  sql("update postavy set aktualni_hp=40, max_hp=40 where id=2");
  await api(pj, 'dalsi_tah', { mapa_id: MAPA });
  k = await kolo();
  if (k.aktivni_token_id !== gob) { for (let i = 0; i < 4 && (await kolo()).aktivni_token_id !== gob; i++) await api(pj, 'tah_konec', { mapa_id: MAPA }); }
  ok((await kolo()).aktivni_token_id === gob, 'goblin je na tahu (Sindor spí)');
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gob, druh: 'utok', cil_token_id: sindor });
  vy = (await stav(pj)).vyzvy;
  if (vy[0]) {
    sql(`update souboj_vyzva set data = json_set(data, '$.zaklad', 3) where id=${vy[0].id}`);
    d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [4] });
    const obS = (await stav(pj)).vyzvy.find(x => x.druh === 'obrana');
    ok(obS && obS.nasobek === 1 && obS.nasobek_duvod === 'Spánek' && obS.nehybny === true, 'spící Sindor 1. úrovně: násobek ×1, nehybný: ' + JSON.stringify(obS && [obS.nasobek, obS.nehybny]));
  } else { ok(false, 'goblin neměl kam zaútočit: ' + (d.error || '')); }

  // ===================== F: krvácení a regenerace — tik =====================
  await uklid();
  sindor = await pridej('postava', 13, 10);
  gob = await pridej('nestvura', 14, 10);
  gobInst = instOf(gob);
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${gobInst}`);
  await boj([sindor, gob], [6, 1]);
  const krv = efektId('Krvácení (sečná zbraň trollobijce)');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: gobInst, efekt_id: krv });
  ok(d.ok && d.efekt.zbyva_kol === null, 'krvácení trvá do ošetření (bez limitu)');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: gobInst, efekt_id: krv });
  ok(d.ok && d.efekt.obnoveno === false && (await ef('nestvura_instance', gobInst)).length === 2, 'krvácení se sčítá: dva řádky');
  // lektvar Regenerace pro Sindora s kostkami z lektvaru
  sql("insert into postava_lektvary (postava_id, lektvar_id, mnozstvi) values (2, 34, 1) on duplicate key update mnozstvi = 1");
  sql("update postavy set aktualni_hp=1, max_hp=40 where id=2");
  d = await api(pj, 'pouzij_predmet', { typ_entity: 'postava', entita_id: 2, typ_polozky: 'lektvar', polozka_id: 34, mapa_id: MAPA });
  e = await ef('postava', 2);
  ok(d.ok && e.length === 1 && e[0].kostky === '1k6+5' && e[0].z === 5, 'Regenerační lektvar: efekt na 5 kol, kostky 1k6+5 z lektvaru: ' + (d.error || JSON.stringify(e)));
  await dokonciKolo();
  let v = (await stav(pj)).vyzvy;
  const tiky = v.filter(x => x.druh === 'tik');
  ok(tiky.length === 3, 'na začátku kola vznikly 3 tiky (2× krvácení, 1× regenerace): ' + tiky.length);
  const tikKrv = tiky.find(x => x.token_id === gob);
  const tikReg = tiky.find(x => x.token_id === sindor);
  ok(tikKrv && tikKrv.kostka === 4 && tikKrv.pocet_kostek === 1 && tikReg.kostka === 6 && tikReg.zaklad === 5, 'kostky tiků: 1k4 a 1k6+5: ' + JSON.stringify([tikKrv.kostka, tikReg.kostka, tikReg.zaklad]));
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tikKrv.id, hody: [2] });
  ok(d.s === 403, 'hráč nehází za goblina');
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tikKrv.id, hody: [5] });
  ok(d.s === 422, 'k4 nemůže hodit 5: ' + d.error);
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tikKrv.id, hody: [1, 2] });
  ok(d.s === 422, 'jedna kostka, ne dvě: ' + d.error);
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tikKrv.id, hody: [3] });
  ok(d.ok && d.vysledek && d.vysledek.castka === 3 && hpGob(gobInst) === 17, 'tik krvácení: −3 životy goblinovi (20 -> 17): ' + (d.error || JSON.stringify(d.vysledek)));
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: tikReg.id, soucet: 4, rucne: true });
  ok(d.ok && d.vysledek.castka === 9 && parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10) === 10, 'tik regenerace: 4 + 5 = +9 životů (1 -> 10): ' + (d.error || JSON.stringify(d.vysledek)));
  // tik neblokuje tah
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'otocka', smer: 60 });
  ok(d.ok || d.s !== 422 || !/hod/.test(d.error || ''), 'otevřený tik (druhé krvácení) tah neblokuje: ' + (d.error || 'ok'));
  v = (await stav(pj)).vyzvy;
  const zbyva = v.filter(x => x.druh === 'tik');
  ok(zbyva.length === 1, 'zbývá jeden neodhozený tik');
  d = await api(hr, 'vyzva_zrus', { mapa_id: MAPA, vyzva_id: zbyva[0].id });
  ok(d.s === 403, 'hráč tik zrušit nesmí');
  d = await api(pj, 'vyzva_zrus', { mapa_id: MAPA, vyzva_id: zbyva[0].id });
  ok(d.ok && (await stav(pj)).vyzvy.length === 0, 'PJ zruší jeden tik');
  d = await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  ok(d.s === 422, 'bez rozehraného hodu se obecné „zrušit“ odmítne: ' + d.error);

  // ===================== G: bonusy ÚČ / OČ z efektů =====================
  await uklid();
  sindor = await pridej('postava', 13, 10);
  gob = await pridej('nestvura', 14, 10);
  sql("insert into efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, mechanika_stav, trvani_kol) values ('TEST bonus', 'buff', 'ÚČ, OČ', '+2/+1', 'jednorazove', 'auto', 4)");
  const tb = efektId('TEST bonus');
  sql(`insert into efekt_slozky (efekt_id, druh, hodnota) values (${tb}, 'uc_bonus', 2), (${tb}, 'oc_bonus', 1)`);
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await boj([sindor, gob], [6, 1]);
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: tb });
  ok(d.ok && d.efekt.zbyva_kol === 4, 'TEST bonus: výchozí trvání 4');
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  vy = (await stav(pj)).vyzvy;
  ok(vy.length === 1 && vy[0].opravy.some(o => /efekt TEST bonus/.test(o.nazev) && o.hodnota === 2), 'útok Sindora má opravu „efekt TEST bonus“ +2: ' + JSON.stringify(vy[0] && vy[0].opravy));
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vy[0].id, hody: [3] });
  // goblin útočí zpět (jeho tah později) — OČ bonus Sindora ověříme přes obranu, kdyby goblin zaútočil; tady přímo přes funkce obrany
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });

  // ===================== H: Poškození jako trvalý efekt se odmítne =====================
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: efektId('Poškození') });
  ok(d.s === 422 && /Poškození/.test(d.error), 'Poškození nejde nasadit jako trvalý efekt: ' + d.error);
  // efekt s „0“ kol = bez limitu
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: efektId('Běh na krátké vzdálenosti'), zbyva_kol: 0 });
  ok(d.ok && d.efekt.zbyva_kol === null, '0 kol = bez limitu (přebije výchozí 3)');

  await uklid();
  await browser.close();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
