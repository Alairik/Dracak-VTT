// Hodiny světa a hoření světel (includes/vtt_cas.php, migrace 0109): čas ve dni po směnách (1 směna = 10 minut), posun jen PJ, přelévání do dalšího dne,
// hoření světel (pochodeň 3 směny, lucerna 24, Temnota 3) — na nule zhasnou, hráč pak nevidí; zóna tmy dohoří taky; nastavení času ve dni v administraci.
// Mapa 1 (čtvercový grid 70 px = 1 sáh). Po resetu dat: mysql dracak < ../fixture/seed.sql; node hodiny_sveta_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1, SVET = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', e => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const tokenyHrace = async () => ((await (await hrac.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny || []).map(t => t.id);
  sql(`update svet set aktualni_smena=48, aktualni_den_offset=0 where id=${SVET}; delete from zdi where mapa_id=${MAPA}; delete from mlha_valky where mapa_id=${MAPA}`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });

  // --- oprávnění a meze ---
  let r = await api(hrac, 'cas_posun', { svet_id: SVET, smeny: 1 });
  ok(r.s === 403, 'hráč čas posunout nesmí (403): ' + r.s);
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 0 });
  ok(r.s === 422, 'posun o 0 směn se odmítne (422)');
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 1441 });
  ok(r.s === 422, 'posun o víc než 1440 směn se odmítne (422)');
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 'moc' });
  ok(r.s === 422, 'posun o nečíslo se odmítne (422)');
  r = await api(pj, 'cas_posun', { svet_id: SVET, nastav_smenu: 144 });
  ok(r.s === 422, 'čas ve dni 144 směn a víc se odmítne (422)');
  r = await api(pj, 'cas_posun', { svet_id: 99999, smeny: 1 });
  ok(r.s === 404, 'neexistující svět (404)');
  ok(sqlVal(`select aktualni_smena from svet where id=${SVET}`) === '48', 'odmítnuté požadavky čas nezměnily');

  // --- čas ---
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 1 });
  ok(r.s === 200 && r.text === '8:10' && r.smena === 49, 'posun o 1 směnu: 8:00 → 8:10 (' + r.text + ')');
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 6 });
  ok(r.text === '9:10', 'posun o 6 směn (hodinu): 9:10 (' + r.text + ')');
  const denPred = parseInt(sqlVal(`select aktualni_den_offset from svet where id=${SVET}`), 10);
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 138 });
  ok(r.text === '8:10' && r.den === denPred + 1, 'posun přes půlnoc se přelije do dalšího dne: den ' + denPred + ' → ' + r.den + ', ' + r.text);
  r = await api(pj, 'cas_posun', { svet_id: SVET, nastav_smenu: 0 });
  ok(r.s === 200 && r.text === '0:00', 'nastavení času ve dni na půlnoc');
  await api(pj, 'cas_posun', { svet_id: SVET, nastav_smenu: 48 });
  const posl = parseInt(sqlVal('select max(id) from svet_udalosti'), 10);
  await api(pj, 'cas_posun', { svet_id: SVET, smeny: 1 });
  const ud = await (await hrac.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(/cas_zmena/.test(ud) && /8:10/.test(ud), 'hráč dostane událost o změně času');
  await api(pj, 'cas_posun', { svet_id: SVET, nastav_smenu: 48 });

  // --- hoření světel ---
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 665, y: 315 })).token_id;
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 1015, y: 315 })).token_id;   // 5 sáhů vpravo
  r = await api(hrac, 'token_uprava', { token_id: h, zmeny: { zbyva: 3 } });
  ok(r.s === 403, 'hráč dobu hoření nastavit nesmí (403)');
  r = await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden', zbyva: 10000 } });
  ok(r.s === 422, 'doba hoření nad 9999 směn se odmítne (422)');
  r = await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden', zbyva: 3 } });
  ok(r.s === 200 && r.zmeny.zbyva === 3, 'PJ zapálí pochodeň na 3 směny');
  ok((await tokenyHrace()).includes(gob), 'pochodeň svítí: hráč vidí nestvůru 5 sáhů daleko');
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 2 });
  ok(r.zhasla.length === 0 && sqlVal(`select svetlo_zbyva from tokeny where id=${h}`) === '1', 'po 2 směnách pochodeň hoří ještě 1 směnu');
  ok((await tokenyHrace()).includes(gob), 'pochodeň pořád svítí');
  const posl2 = parseInt(sqlVal('select max(id) from svet_udalosti'), 10);
  r = await api(pj, 'cas_posun', { svet_id: SVET, smeny: 1 });
  ok(r.zhasla.length === 1 && r.zhasla[0] === h, 'po třetí směně pochodeň dohoří a zhasne');
  ok(sqlVal(`select concat(ifnull(svetlo_sahy,'-'), '|', ifnull(svetlo_typ,'-'), '|', ifnull(svetlo_zbyva,'-')) from tokeny where id=${h}`) === '-|-|-', 'zhaslé světlo je vymazané');
  ok(!(await tokenyHrace()).includes(gob), 'po zhasnutí hráč nestvůru nevidí');
  const ud2 = await (await hrac.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl2)).text();
  ok(/token_zmena/.test(ud2) && /cas_zmena/.test(ud2), 'hráč dostane událost o zhasnutém světle i o čase');

  // světlo bez limitu nehoří, lucerna 24 směn, zóna tmy dohoří
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'hul', zbyva: null } });
  await api(pj, 'cas_posun', { svet_id: SVET, smeny: 100 });
  ok(sqlVal(`select svetlo_sahy from tokeny where id=${h}`) === '6.0', 'světlo bez limitu (čarodějova hůl) po 100 směnách pořád svítí');
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 8, svetlo_typ: 'lucerna', zbyva: 24 } });
  await api(pj, 'cas_posun', { svet_id: SVET, smeny: 23 });
  ok(sqlVal(`select svetlo_zbyva from tokeny where id=${h}`) === '1', 'lucerna po 23 směnách z 24 hoří ještě 1');
  await api(pj, 'cas_posun', { svet_id: SVET, smeny: 1 });
  ok(sqlVal(`select ifnull(svetlo_sahy,'-') from tokeny where id=${h}`) === '-', 'lucerna po 24 směnách (láhev oleje, 4 hodiny) dohoří');
  const zona = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', nazev: 'Temnota', x: 1015, y: 315 })).token_id;
  await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 300, svetlo_typ: 'temnota', viditelny: false, zbyva: 3 } });
  await api(pj, 'cas_posun', { svet_id: SVET, smeny: 3 });
  ok(sqlVal(`select ifnull(svetlo_sahy,'-') from tokeny where id=${zona}`) === '-', 'Temnota druida po 3 směnách (b1936) pomine');

  // --- prohlížeč ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: null, svetlo_typ: null, zbyva: null } });
  sql(`update svet set aktualni_smena=48 where id=${SVET}`);
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await hrac.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForTimeout(1200); await hrac.waitForTimeout(1200);
  ok(/8:00/.test(await pj.textContent('#svetCasText')) && /8:00/.test(await hrac.textContent('#svetCasText')), 'PJ i hráč vidí čas v liště: ' + await pj.textContent('#svetCasText'));
  ok(await pj.locator('[data-cas-posun]').count() === 2 && await hrac.locator('[data-cas-posun]').count() === 0, 'tlačítka posunu času má jen PJ');
  await pj.click('[data-cas-posun="6"]');
  await pj.waitForTimeout(700);
  ok(/9:00/.test(await pj.textContent('#svetCasText')), 'PJ klikne +1 hodina a čas se přepíše na 9:00');
  await hrac.waitForTimeout(3500);
  ok(/9:00/.test(await hrac.textContent('#svetCasText')), 'hráči se čas přepíše přes polling: ' + await hrac.textContent('#svetCasText'));
  await pj.locator('.vtt-token[data-id="' + h + '"] .puck').click();
  await pj.waitForTimeout(600);
  await pj.selectOption('#vzhledSvetlo', 'pochoden:6');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select concat(svetlo_sahy, '|', svetlo_zbyva) from tokeny where id=${h}`) === '6.0|3', 'předvolba Pochodeň nastaví i dobu hoření 3 směny');
  ok(await pj.locator('#vzhledZbyva').isVisible() && await pj.inputValue('#vzhledZbyva') === '3', 'v panelu tokenu je vidět „Vydrží ještě“ = 3');
  await pj.selectOption('#vzhledSvetlo', 'lucerna:8');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select svetlo_zbyva from tokeny where id=${h}`) === '24', 'předvolba Lucerna nastaví 24 směn');
  await pj.fill('#vzhledZbyva', '5');
  await pj.locator('#vzhledZbyva').dispatchEvent('change');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select svetlo_zbyva from tokeny where id=${h}`) === '5', 'PJ upraví dobu hoření ručně na 5');
  await pj.selectOption('#vzhledSvetlo', '');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select ifnull(svetlo_zbyva,'-') from tokeny where id=${h}`) === '-', 'zhasnutí světla v panelu smaže i dobu hoření');

  // administrace: čas ve dni
  await pj.goto(BASE + '/hra/svet_administrace.php?id=' + SVET);
  await pj.fill('#cas_dne', '14:30');
  await Promise.all([pj.waitForNavigation(), pj.click('form:has(#cas_dne) button[type=submit]')]);
  ok(sqlVal(`select aktualni_smena from svet where id=${SVET}`) === '87', 'v administraci světa PJ nastaví čas 14:30 (směna 87)');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`delete from zdi where mapa_id=${MAPA}; update mapy set osvetleni='den' where id=${MAPA}; update svet set aktualni_smena=48, aktualni_den_offset=0 where id=${SVET}`);
  for (const tk of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: tk.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
