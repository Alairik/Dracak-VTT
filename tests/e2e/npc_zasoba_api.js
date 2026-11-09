// Zásoba NPC a NPC jako bytost světa (migrace 0115): výroba dávkou, navázání na místo, pokládání ze zásoby,
// sundání tokenu vrací NPC do zásoby, jedinečné NPC má vlastní čísla a vyplněný deník z kusu unikát udělá.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node npc_zasoba_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const SVET = 1;
const MAPA = 1;         // Testovací mapa (čtverec)
const MAPA2 = 2;        // druhá mapa téhož světa
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext()).newPage();
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const get = async (p, u) => { const r = await p.request.get(BASE + '/hra/api/' + u); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const druh = parseInt(sqlVal("select id from nestvury where nazev='Goblin' limit 1") || sqlVal('select min(id) from nestvury'), 10);

  sql(`delete from tokeny where typ_entity='nestvura_instance'; delete from nestvura_instance`);

  // --- práva ---
  let r = await get(hrac, 'npc_zasoba.php?svet_id=' + SVET);
  ok(r.s === 403, 'hráč zásobu NPC nevidí (403): ' + r.s);
  r = await api(hrac, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 2 });
  ok(r.s === 403, 'hráč NPC nevyrobí (403): ' + r.s);

  // --- meze počtu ---
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 0 });
  ok(r.s === 422, 'počet 0 je odmítnut (422): ' + r.s);
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 51 });
  ok(r.s === 422, 'počet 51 je nad stropem (422): ' + r.s);
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: 999999, pocet: 1 });
  ok(r.s === 404, 'neznámý druh (404): ' + r.s);

  // --- výroba do zásoby bez místa ---
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 3 });
  ok(r.s === 200 && r.vyrobeno === 3, 'PJ vyrobí 3 kusy: ' + JSON.stringify(r.vyrobeno));
  const jmena = r.npc.map((n) => n.nazev);
  ok(new Set(jmena).size === 3, 'kusy mají různá jména (číslují se): ' + jmena.join(', '));
  ok(sqlVal(`select count(*) from nestvura_instance where svet_id=${SVET} and mapa_id is null and lokace_mapa_id is null`) === '3',
    'tři kusy jsou v zásobě bez mapy i bez místa');

  // --- výroba navázaná na místo ---
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 2, lokace_mapa_id: MAPA });
  ok(r.s === 200 && r.vyrobeno === 2, 'PJ vyrobí 2 kusy navázané na místo: ' + r.s);
  ok(sqlVal(`select count(*) from nestvura_instance where lokace_mapa_id=${MAPA}`) === '2', 'dva kusy patří k mapě ' + MAPA);
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 1, lokace_mapa_id: 999999 });
  ok(r.s === 404, 'neexistující místo je odmítnuto (404): ' + r.s);

  // --- výpis: zásoba, lokace, naposledy ---
  r = await get(pj, 'npc_zasoba.php?svet_id=' + SVET + '&mapa_id=' + MAPA);
  ok(r.s === 200 && r.funkce === true, 'zásoba se načte: ' + r.s);
  ok(r.zasoba.length === 5, 'v zásobě je 5 kusů: ' + r.zasoba.length);
  ok(r.zasoba.filter((n) => n.lokace_mapa_id === MAPA).length === 2, 'z toho 2 patří k téhle lokaci');
  ok(r.zasoba.filter((n) => n.lokace_mapa_id === null).length === 3, 'a 3 jsou bez místa');
  ok(r.lokace.length === 2, 'výpis lokace má 2 NPC: ' + r.lokace.length);
  ok(r.lokace.every((n) => n.na_mape === false), 'žádné z nich zatím na mapě nestojí');
  ok(r.naposledy.length === 1 && r.naposledy[0].kusu === 5, 'naposledy použité: jeden druh, 5 kusů');

  // --- položení ze zásoby ---
  const kus = r.zasoba[0].instance_id;
  const jmenoKusu = r.zasoba[0].nazev;
  const lokacePred = sqlVal(`select ifnull(lokace_mapa_id, 'null') from nestvura_instance where id=${kus}`);
  r = await api(hrac, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', instance_id: kus, x: 300, y: 300 });
  ok(r.s === 403, 'hráč NPC ze zásoby nepoloží (403): ' + r.s);
  r = await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', instance_id: kus, x: 300, y: 300 });
  ok(r.s === 200 && r.nazev === jmenoKusu, 'PJ položí NPC ze zásoby a jméno zůstane: ' + r.nazev);
  const tokenId = r.token_id;
  ok(sqlVal(`select mapa_id from nestvura_instance where id=${kus}`) === String(MAPA), 'NPC má teď mapu ' + MAPA);
  ok(sqlVal(`select count(*) from nestvura_instance where id=${kus}`) === '1', 'nevznikl duplikát');
  r = await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', instance_id: kus, x: 400, y: 300 });
  ok(r.s === 409, 'tentýž kus nejde položit podruhé (409): ' + r.s);
  r = await get(pj, 'npc_zasoba.php?svet_id=' + SVET + '&mapa_id=' + MAPA);
  ok(r.zasoba.length === 4, 'v zásobě zbyly 4 kusy: ' + r.zasoba.length);

  // NPC z jiného světa / neexistující
  r = await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', instance_id: 999999, x: 300, y: 300 });
  ok(r.s === 404, 'neexistující NPC ze zásoby (404): ' + r.s);

  // --- sundání tokenu vrátí NPC do zásoby (nemaže ho) ---
  r = await api(pj, 'token_smazat', { token_id: tokenId });
  ok(r.s === 200, 'PJ sundá token: ' + r.s);
  ok(sqlVal(`select count(*) from nestvura_instance where id=${kus}`) === '1', 'NPC po sundání tokenu nezmizelo');
  ok(sqlVal(`select ifnull(mapa_id, 'null') from nestvura_instance where id=${kus}`) === 'null', 'NPC je zpátky v zásobě');
  ok(sqlVal(`select ifnull(lokace_mapa_id, 'null') from nestvura_instance where id=${kus}`) === lokacePred,
    'místo (lokace) se sundáním tokenu nemění — NPC pořád patří tam, kam patřilo: ' + lokacePred);

  // --- nestvůra položená rovnou na mapu patří k té lokaci ---
  r = await api(pj, 'token_pridat', { mapa_id: MAPA2, typ_entity: 'nestvura', nestvura_id: druh, x: 200, y: 200 });
  ok(r.s === 200, 'PJ položí nestvůru rovnou na druhou mapu: ' + r.s);
  const primy = parseInt(sqlVal(`select entita_id from tokeny where id=${r.token_id}`), 10);
  ok(sqlVal(`select lokace_mapa_id from nestvura_instance where id=${primy}`) === String(MAPA2), 'patří k mapě, na které vznikla');

  // --- navázání na místo a uvolnění ---
  r = await api(hrac, 'npc_lokace', { instance_id: kus, mapa_id: MAPA });
  ok(r.s === 403, 'hráč místo NPC nemění (403): ' + r.s);
  r = await api(pj, 'npc_lokace', { instance_id: kus, mapa_id: MAPA2 });
  ok(r.s === 200 && sqlVal(`select lokace_mapa_id from nestvura_instance where id=${kus}`) === String(MAPA2), 'PJ naváže NPC na druhou mapu');
  r = await api(pj, 'npc_lokace', { instance_id: kus });
  ok(r.s === 200 && sqlVal(`select ifnull(lokace_mapa_id, 'null') from nestvura_instance where id=${kus}`) === 'null', 'PJ NPC z místa zase uvolní');

  // --- mazání ze zásoby ---
  r = await api(hrac, 'npc_smazat', { instance_id: kus });
  ok(r.s === 403, 'hráč NPC nemaže (403): ' + r.s);
  r = await api(pj, 'npc_smazat', { instance_id: primy });
  ok(r.s === 409, 'NPC stojící na mapě nejde smazat ze zásoby (409): ' + r.s);
  r = await api(pj, 'npc_smazat', { instance_id: kus });
  ok(r.s === 200 && sqlVal(`select count(*) from nestvura_instance where id=${kus}`) === '0', 'PJ smaže NPC ze zásoby');

  // --- dvě úrovně NPC: jedinečný vs. běžný kus ---
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 2, jedinecny: true, lokace_mapa_id: MAPA });
  ok(r.s === 200 && sqlVal(`select count(*) from nestvura_instance where jedinecny=1`) === '2', 'vyrobené „každý jedinečný“ mají příznak');
  r = await get(pj, 'npc_zasoba.php?svet_id=' + SVET + '&mapa_id=' + MAPA);
  const unikat = r.zasoba.find((n) => n.jedinecny);
  ok(!!unikat, 'zásoba označí jedinečné NPC');

  // deník: běžný kus ukládá čísla celému druhu, jedinečný jen sobě
  r = await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', instance_id: unikat.instance_id, x: 500, y: 500 });
  const tokUnikat = r.token_id;
  r = await api(pj, 'nestvura_denik', { mapa_id: MAPA, token_id: tokUnikat, akce: 'uloz', staty: { utoky: [{ nazev: 'tesák', uc: 7, utocnost: 4 }], oc: 3, oz: null, velikost: 'B', odolnost: 9 } });
  ok(r.s === 200, 'PJ uloží čísla jedinečnému NPC: ' + r.s);
  ok(sqlVal(`select count(*) from nestvura_staty_sveta where svet_id=${SVET} and nestvura_id=${druh}`) === '0',
    'čísla jedinečného kusu se NEuložila celému druhu');
  ok(sqlVal(`select data is not null from nestvura_instance where id=${unikat.instance_id}`) === '1', 'uložila se jemu');
  r = await api(pj, 'nestvura_denik', { mapa_id: MAPA, token_id: tokUnikat, akce: 'uloz', staty: { utoky: [], oc: 5, oz: null, velikost: 'B', odolnost: 9 } });
  ok(r.s === 200 && r.jedinecny === true, 'deník jedinečného kusu se hlásí jako jedinečný');

  // běžný kus: „jen tento“ z něj unikát udělá
  r = await api(pj, 'npc_vyrobit', { svet_id: SVET, nestvura_id: druh, pocet: 1, lokace_mapa_id: MAPA });
  const bezny = r.npc[0].instance_id;
  r = await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', instance_id: bezny, x: 600, y: 500 });
  const tokBezny = r.token_id;
  r = await api(pj, 'nestvura_denik', { mapa_id: MAPA, token_id: tokBezny, akce: 'uloz', staty: { utoky: [], oc: 2, oz: null, velikost: 'B', odolnost: 8 } });
  ok(r.s === 200 && sqlVal(`select count(*) from nestvura_staty_sveta where nestvura_id=${druh}`) === '1',
    'bez „jen tento“ se čísla uloží celému druhu');
  r = await api(pj, 'nestvura_denik', { mapa_id: MAPA, token_id: tokBezny, akce: 'uloz', jen_tento: true, staty: { utoky: [], oc: 4, oz: null, velikost: 'B', odolnost: 8 } });
  ok(r.s === 200 && sqlVal(`select jedinecny from nestvura_instance where id=${bezny}`) === '1',
    'vyplněný deník „jen tento“ udělá z kusu jedinečné NPC');
  r = await api(pj, 'nestvura_denik', { mapa_id: MAPA, token_id: tokBezny, akce: 'uloz', staty: { utoky: [], oc: 6, oz: null, velikost: 'B', odolnost: 8 } });
  ok(sqlVal(`select json_extract(data, '$.oc') from nestvura_instance where id=${bezny}`) === '6',
    'další uložení už jde jen jemu (druh se nepřepíše)');
  ok(sqlVal(`select json_extract(data, '$.oc') from nestvura_staty_sveta where nestvura_id=${druh}`) === '2',
    'čísla druhu zůstala na původní hodnotě');

  // --- vrácení čísel ---
  r = await api(pj, 'nestvura_denik', { mapa_id: MAPA, token_id: tokBezny, akce: 'vrat' });
  ok(r.s === 200 && sqlVal(`select jedinecny from nestvura_instance where id=${bezny}`) === '0', 'vrácení čísel udělá z kusu zase běžný');

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
