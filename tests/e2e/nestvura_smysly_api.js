// Smysly nestvůry z bestiáře (migrace 0116): infravidění a „infravidění ho nerozezná“ se na token doplní samy
// podle knihy, místo aby je PJ u každého tokenu zadával ručně. Velikost tokenu se odvodí z počtu hexů
// (1 hex = 1 sáh, h1622 b10798; třída A0 = půl sáhu). Po resetu dat: mysql dracak < ../fixture/seed.sql; node nestvura_smysly_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;   // hexová mapa — kvůli počtu hexů
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const p = await (await browser.newContext()).newPage();
  await p.goto(BASE + '/index.php'); await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  sql("delete from tokeny where typ_entity='nestvura_instance'; delete from nestvura_instance");

  const polozit = async (jmeno) => {
    const id = parseInt(sqlVal(`select id from nestvury where nazev='${jmeno.replace(/'/g, "''")}'`), 10);
    const r = await p.request.post(BASE + '/hra/api/token_pridat.php', { data: { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: id, x: 300, y: 300 } });
    const j = await r.json();
    return sqlVal(`select concat(ifnull(videni_infra_sahy,'-'), '|', studeny, '|', hexu, '|', velikost_sahu) from tokeny where id=${j.token_id}`).split('|');
  };

  // --- infravidění s dosahem z knihy ---
  let t = await polozit('Zmije rohatá');
  ok(t[0] === '10', 'Zmije rohatá má infravidění 10 sáhů (b18526): ' + t[0]);
  t = await polozit('Vážka jeskynní');
  ok(t[0] === '30', 'Vážka jeskynní má infravidění 30 sáhů (b18710): ' + t[0]);
  t = await polozit('Kostlivec');
  ok(t[0] === '30', 'nemrtvý má infravidění 30 sáhů (b19754): ' + t[0]);

  // --- infravidění bez uvedeného dosahu: nic se nevymýšlí ---
  t = await polozit('Bazilišek');
  ok(t[0] === '-', 'Bazilišek infravidění má, ale kniha dosah neuvádí — nedoplní se číslo (b17822): ' + t[0]);
  ok(sqlVal("select count(*) from nestvura_schopnosti ns join nestvury n on n.id=ns.nestvura_id join zvlastni_schopnosti z on z.id=ns.schopnost_id where n.nazev='Bazilišek' and z.nazev='Infravidění'") === '1',
    'ale v datech nestvůry schopnost zapsaná je');

  // --- koho infravidění nerozezná ---
  t = await polozit('Hlen zelený');
  ok(t[1] === '1', 'Hlen zelený je pro infravidění neviditelný (b18616)');
  t = await polozit('Mlok');
  ok(t[1] === '1', 'Mlok je pro infravidění neviditelný (b19690)');
  t = await polozit('Kamenná socha');
  ok(t[1] === '1', 'oživlá socha je pro infravidění neviditelná (b95001)');
  t = await polozit('Haterie');
  ok(t[0] === '30' && t[1] === '1', 'Haterie obojí naráz: vidí na 30 sáhů a sama viditelná není (b18603): ' + t.slice(0, 2).join('/'));

  // --- tvor, o kterém kniha nic neříká, zůstane prázdný ---
  t = await polozit('Goblin');
  ok(t[0] === '-' && t[1] === '0', 'Goblin nemá ani jedno (bestiář o tom mlčí): ' + t.slice(0, 2).join('/'));

  // --- velikost tokenu z počtu hexů (1 hex = 1 sáh) ---
  t = await polozit('Bílý drak');
  ok(t[2] === '5' && t[3] === '5.00', 'drak (třída E) zabírá 5 hexů a je 5 sáhů velký: ' + t.slice(2).join(' / '));
  t = await polozit('Goblin');
  ok(t[2] === '1' && t[3] === '1.00', 'goblin zabírá 1 hex a je 1 sáh velký: ' + t.slice(2).join(' / '));
  t = await polozit('Blátoplazové');
  ok(t[3] === '0.50', 'drobný tvor (A0) se kreslí na půl sáhu — na hex se jich vejde víc: ' + t[3]);

  // --- PJ může přepsat ručně a další kus téhož druhu to zdědí ---
  const drak = sqlVal(`select t.id from tokeny t join nestvura_instance ni on ni.id=t.entita_id join nestvury n on n.id=ni.nestvura_id where n.nazev='Bílý drak' order by t.id desc limit 1`);
  let r = await p.request.post(BASE + '/hra/api/token_uprava.php', { data: { token_id: parseInt(drak, 10), zmeny: { velikost: 3 } } });
  ok(r.status() === 200, 'PJ velikost přepíše ručně: ' + r.status());
  t = await polozit('Bílý drak');
  ok(t[3] === '3.00', 'další drak zdědí ručně nastavenou velikost, odvození ji nepřebije: ' + t[3]);

  // --- infravidění postavy z rasy (migrace 0117): trpaslík 20 sáhů (hrac b790), ostatní rasy nic ---
  const trpaslik = parseInt(sqlVal("select id from rasy where nazev='Trpaslík'"), 10);
  const puvodni = sqlVal('select rasa_id from postavy where id=1');
  sql(`update postavy set rasa_id=${trpaslik} where id=1`);
  sql(`delete from tokeny where mapa_id=${MAPA} and typ_entity='postava' and entita_id=1`);
  let j = await (await p.request.post(BASE + '/hra/api/token_pridat.php', { data: { mapa_id: MAPA, typ_entity: 'postava', postava_id: 1, x: 700, y: 700 } })).json();
  ok(sqlVal(`select ifnull(videni_infra_sahy,'-') from tokeny where id=${j.token_id}`) === '20',
    'token trpaslíka dostane infravidění 20 sáhů z pravidel');
  sql(`update postavy set rasa_id=${puvodni} where id=1`);
  sql(`delete from tokeny where mapa_id=${MAPA} and typ_entity='postava' and entita_id=1`);
  j = await (await p.request.post(BASE + '/hra/api/token_pridat.php', { data: { mapa_id: MAPA, typ_entity: 'postava', postava_id: 1, x: 700, y: 700 } })).json();
  ok(sqlVal(`select ifnull(videni_infra_sahy,'-') from tokeny where id=${j.token_id}`) === '-',
    'token jiné rasy infravidění nedostane (pravidla ho mají jen u trpaslíka)');

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
