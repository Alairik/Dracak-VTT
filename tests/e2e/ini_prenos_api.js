// Přenos výsledku iniciativy ≤ 0 do dalšího kola (h1622 b10805, rozhodnutí uživatele 2026-11-01): kdo skončí na 0 a méně, v dalším kole
// hází znovu, ale místo obvyklých postihů použije výsledek z minulého kola. Kdo skončí na 1 a víc, nic nepřenáší.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await (await browser.newContext()).newPage();
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  const api = async (n, b) => { const r = await page.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const stav = async () => (await page.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  const g = await page.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  await api('souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id, mapa_id: MAPA });
  const a = (await api('token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(10, 10).x, y: stred(10, 10).y })).token_id;
  const b = (await api('token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: stred(13, 10).x, y: stred(13, 10).y })).token_id;
  await api('souboj_start', { mapa_id: MAPA, ucastnici: [a, b].map(t => ({ token_id: t })) });
  const vysledek = (t) => sqlVal(`select concat(hod, '|', modifikator, '|', vysledek, '|', akce_celkem) from kolo_iniciativa where mapa_id=${MAPA} and token_id=${t}`);
  const prenos = (t) => sqlVal(`select ifnull(ini_prenos, 'NULL') from kolo_ucastnik where mapa_id=${MAPA} and token_id=${t}`);
  const dokonciKolo = async () => {
    for (let i = 0; i < 12; i++) {
      const f = sqlVal(`select faze from kolo_stav where mapa_id=${MAPA}`);
      if (f === 'umysly') return true;
      await api('tah_konec', { mapa_id: MAPA });
    }
    return sqlVal(`select faze from kolo_stav where mapa_id=${MAPA}`) === 'umysly';
  };

  // kolo 1: A má ruční postih −4 a hodí 1 → −3 (0 akcí), B hodí 6
  await api('souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  let r = await api('souboj_hod', { mapa_id: MAPA, token_ids: [a], hod: 1, jiny_bonus: -4 });
  ok(r.s === 200 && vysledek(a) === '1|-4|-3|0', 'kolo 1: A hodil 1 s postihem −4, výsledek −3 a 0 akcí: ' + vysledek(a));
  r = await api('souboj_hod', { mapa_id: MAPA, token_ids: [b], hod: 6 });
  ok(r.s === 200 && vysledek(b) === '6|0|6|2', 'kolo 1: B hodil 6, výsledek 6 a 2 akce: ' + vysledek(b));
  await api('souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  ok(await dokonciKolo(), 'kolo 1 se dohrálo, začíná kolo 2 (úmysly)');
  ok(prenos(a) === '-3' && prenos(b) === 'NULL', 'A má přenos −3, B žádný: A=' + prenos(a) + ' B=' + prenos(b));

  // kolo 2: A hodí 5 → 5 + (−3) = 2 (1 akce), obvyklý modifikátor se nepoužije; B bez přenosu
  await api('souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  r = await api('souboj_hod', { mapa_id: MAPA, token_ids: [a], hod: 5 });
  ok(r.s === 200 && vysledek(a) === '5|-3|2|1', 'kolo 2: A hodí 5 a místo obvyklých postihů přičte −3 → 2, 1 akce: ' + vysledek(a));
  const udalost = sqlVal(`select payload from svet_udalosti where mapa_id=${MAPA} and typ='iniciativa_hozena' order by id desc limit 1`);
  ok(/minulého kola/.test(JSON.parse(udalost).bonusy.join(' ')), 'událost říká, proč: ' + JSON.parse(udalost).bonusy.join('; '));
  r = await api('souboj_hod', { mapa_id: MAPA, token_ids: [b], hod: 2 });
  ok(r.s === 200 && vysledek(b) === '2|0|2|1', 'kolo 2: B (bez přenosu) hodil 2 → 2: ' + vysledek(b));
  // PJ opraví hod (přepis): přenos platí dál, ruční úprava se přičte
  r = await api('souboj_hod', { mapa_id: MAPA, token_ids: [a], hod: 5, jiny_bonus: 1 });
  ok(r.s === 200 && vysledek(a) === '5|-2|3|1', 'přepis hodu s ruční úpravou +1: modifikátor −3 + 1 = −2 → 3, 1 akce (1–3): ' + vysledek(a));
  await api('souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  ok(await dokonciKolo(), 'kolo 2 se dohrálo');
  ok(prenos(a) === 'NULL' && prenos(b) === 'NULL', 'výsledek ≥ 1 nic nepřenáší: A=' + prenos(a) + ' B=' + prenos(b));

  // kolo 3: bez přenosu hází A podle obvyklých pravidel
  await api('souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  r = await api('souboj_hod', { mapa_id: MAPA, token_ids: [a], hod: 4 });
  ok(r.s === 200 && vysledek(a) === '4|0|4|2', 'kolo 3: bez přenosu A hází jako dřív: ' + vysledek(a));

  await api('souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
