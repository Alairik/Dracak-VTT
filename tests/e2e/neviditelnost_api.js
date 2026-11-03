// Neviditelný tvor (efekt „Neviditelnost“, lektvar neviditelnosti h3237 b3237–b3241): hráč ho nevidí ani na osvětlené mapě (seznam tokenů ani události), PJ a vlastník ano;
// útok na neviditelného má postih −5 (h1613 b10738); po sundání efektu je tvor zase vidět. Mapa 2 (hex). Po resetu dat: mysql dracak < ../fixture/seed.sql; node neviditelnost_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hr = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const stav = async (p) => (await (await p.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json());
  sql('delete from kolo_stav; delete from souboj_vyzva; delete from aktivni_efekty where efekt_id=76');
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  const g = Math.sqrt(3) * 45;
  const c = (col, row) => ({ x: Math.round(col * g + ((row & 1) ? g / 2 : 0)), y: Math.round(row * 1.5 * 45) });
  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, ...c(13, 10) })).token_id;
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, ...c(14, 10) })).token_id;
  const gobEntita = sqlVal(`select entita_id from tokeny where id=${gob}`);
  const hEntita = sqlVal(`select entita_id from tokeny where id=${h}`);
  const idsHrac = async () => (await stav(hr)).tokeny.map(t => t.id);
  ok((await idsHrac()).includes(gob), 'bez efektu hráč goblina vidí');

  // --- neviditelný goblin ---
  sql(`insert into aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) values ('nestvura_instance', ${gobEntita}, 76, 10)`);
  ok(!(await idsHrac()).includes(gob) && (await idsHrac()).includes(h), 'neviditelného goblina hráč v seznamu tokenů nemá (svou postavu ano)');
  const pjTok = (await stav(pj)).tokeny.find(t => t.id === gob);
  ok(pjTok && pjTok.neviditelny === true, 'PJ goblina vidí s příznakem neviditelný');
  const posl = parseInt(sqlVal('select max(id) from svet_udalosti'), 10);
  await api(pj, 'token_presun', { token_id: gob, ...c(15, 10) });
  const ud = await (await hr.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(!/token_presun/.test(ud), 'pohyb neviditelného se hráči neprozradí přes polling');
  await api(pj, 'token_presun', { token_id: gob, ...c(14, 10) });

  // --- vlastní neviditelná postava: vlastník se vidí ---
  sql(`insert into aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) values ('postava', ${hEntita}, 76, 10)`);
  const sam = (await stav(hr)).tokeny.find(t => t.id === h);
  ok(sam && sam.neviditelny === true, 'vlastník neviditelné postavy ji pořád vidí (s příznakem)');
  sql(`delete from aktivni_efekty where efekt_id=76 and typ_entity='postava'`);

  // --- útok na neviditelného: −5 ---
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [h, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [h], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  for (let i = 0; i < 2; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: h, op: 'delta', delta: 9 });
  let d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: h, druh: 'utok', cil_token_id: gob });
  ok(d.ok && d.vyzva_id, 'hráč zaútočí na neviditelného goblina: ' + (d.error || ''));
  let vy = ((await stav(hr)).vyzvy || [])[0];
  ok(vy && vy.tma === 'nevidi' && vy.opravy.some(o => o.hodnota === -5), 'útok na neviditelného má postih −5 (' + (vy && vy.tma) + ')');
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });

  // --- po sundání efektu ---
  sql('delete from aktivni_efekty where efekt_id=76');
  ok((await idsHrac()).includes(gob), 'po sundání efektu hráč goblina zase vidí');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: h, druh: 'utok', cil_token_id: gob });
  vy = ((await stav(hr)).vyzvy || [])[0];
  ok(d.ok && vy && !vy.tma && !vy.opravy.some(o => o.hodnota === -5), 'bez neviditelnosti žádný postih');

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  sql('delete from souboj_vyzva; delete from aktivni_efekty where efekt_id=76');
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
