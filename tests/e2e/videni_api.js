// Vidění a světlo (includes/vtt_videni.php, migrace 0107): mapa „Tma“ — hráč vidí jen osvětlené a viditelné místo, nestvůry mimo dohled mu server vůbec nepošle,
// zdi a zavřené dveře výhled blokují, infravidění ukáže tvory s teplou krví (ne studenokrevné, ne za zdí), co hráč viděl, zůstává jako paměť,
// světlo a osvětlení mapy mění jen PJ. Mapa 1 (čtvercový grid 70 px = 1 sáh). Po resetu dat: mysql dracak < ../fixture/seed.sql; node videni_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
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
  const mlha = async () => (await hrac.request.get(BASE + '/hra/api/mlha_stav.php?mapa_id=' + MAPA)).json();
  const pocet = (b64) => [...Buffer.from(b64, 'base64')].filter(x => x === 1).length;
  sql(`delete from zdi where mapa_id=${MAPA}; delete from mlha_valky where mapa_id=${MAPA}`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });

  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 665, y: 315 })).token_id;
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 1365, y: 315 })).token_id;   // 10 sáhů vpravo
  ok(h && gob, 'token hráče a nestvůry jsou na mapě');

  // --- „Světlo“ (výchozí): dosavadní chování, nestvůra je vidět ---
  ok((await tokenyHrace()).includes(gob), 'na mapě „Světlo“ hráč nestvůru vidí (nic se nezměnilo)');

  // --- oprávnění ---
  let r = await api(hrac, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  ok(r.s === 403 && sqlVal(`select osvetleni from mapy where id=${MAPA}`) === 'den', 'hráč osvětlení mapy měnit nesmí (403)');
  r = await api(hrac, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  ok(r.s === 403, 'hráč si světlo nastavit nesmí (403): ' + r.s);
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'noc' });
  ok(r.s === 422, 'neplatné osvětlení se odmítne (422)');

  // --- tma bez světla ---
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  ok(r.s === 200, 'PJ zhasne mapu');
  let t = await tokenyHrace();
  ok(t.includes(h) && !t.includes(gob), 've tmě bez světla hráč vidí jen sebe, nestvůru ne');
  let m = await mlha();
  ok(m.videni === true && pocet(m.videne_b64) === 1, 'vidí jedinou buňku — tu, na které stojí: ' + pocet(m.videne_b64));
  const pjVidi = (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny.map(x => x.id);
  ok(pjVidi.includes(gob) && pjVidi.includes(h), 'PJ vidí všechny tokeny vždycky');

  // --- pochodeň ---
  r = await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  ok(r.s === 200, 'PJ dá postavě pochodeň (6 sáhů)');
  m = await mlha();
  const videnoPochoden = pocet(m.videne_b64);
  ok(videnoPochoden > 60 && videnoPochoden < 140, 'pochodeň osvětlí kruh o poloměru 6 sáhů (~113 buněk): ' + videnoPochoden);
  ok(!(await tokenyHrace()).includes(gob), 'nestvůra 10 sáhů daleko (mimo dosah pochodně) pořád není vidět');
  await api(pj, 'token_presun', { token_id: gob, x: 1015, y: 315 });   // 5 sáhů
  ok((await tokenyHrace()).includes(gob), 'nestvůra 5 sáhů daleko v dosahu pochodně je vidět');
  // zeď mezi nimi
  r = await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: 840, y1: 100, x2: 840, y2: 500, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true, dvere: true });
  const dverId = r.id;
  ok(!(await tokenyHrace()).includes(gob), 'za zavřenými dveřmi nestvůru nevidí, ani když je v dosahu světla');
  await api(pj, 'zed_dvere', { id: dverId, otevrene: true });
  ok((await tokenyHrace()).includes(gob), 'otevřenými dveřmi ji zase uvidí');
  await api(pj, 'zed_dvere', { id: dverId, otevrene: false });

  // --- infravidění ---
  await api(pj, 'token_presun', { token_id: gob, x: 1365, y: 315 });   // 10 sáhů, mimo světlo
  sql(`delete from zdi where mapa_id=${MAPA}`);
  r = await api(pj, 'token_uprava', { token_id: h, zmeny: { infra: 20 } });
  ok(r.s === 200, 'PJ dá postavě infravidění 20 sáhů');
  ok((await tokenyHrace()).includes(gob), 'infravidění: teplokrevného tvora 10 sáhů daleko ve tmě uvidí');
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { studeny: true } });
  ok(!(await tokenyHrace()).includes(gob), 'studenokrevného tvora infravidění neukáže');
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { studeny: false } });
  await api(pj, 'token_presun', { token_id: gob, x: 665 + 70 * 25, y: 315 });   // 25 sáhů
  ok(!(await tokenyHrace()).includes(gob), 'za hranicí infravidění (25 > 20 sáhů) tvora nevidí');
  await api(pj, 'token_presun', { token_id: gob, x: 1365, y: 315 });
  await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: 1000, y1: 100, x2: 1000, y2: 500, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true });
  ok(!(await tokenyHrace()).includes(gob), 'infravidění neproniká zdí');
  sql(`delete from zdi where mapa_id=${MAPA}`);

  // --- paměť ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: null, infra: null } });
  m = await mlha();
  ok(pocet(m.videne_b64) === 1, 'po zhasnutí vidí zase jen svou buňku');
  ok(pocet(m.bitmapa_b64) >= videnoPochoden, 'co viděl při světle, zůstalo v paměti (' + pocet(m.bitmapa_b64) + ' ≥ ' + videnoPochoden + ')');
  ok(!(await tokenyHrace()).includes(gob), 'nestvůra mimo dohled se po zhasnutí zase skryje');

  // --- události: pohyb nestvůry ve tmě hráči neprozradí její polohu ---
  const posl = parseInt(sqlVal('select max(id) from svet_udalosti'), 10);
  await api(pj, 'token_presun', { token_id: gob, x: 1435, y: 315 });
  const ud = await (await hrac.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(!/token_presun/.test(ud), 'pohyb nestvůry ve tmě se hráči nepošle přes polling');
  const udPj = await (await pj.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(/token_presun/.test(udPj), 'PJ pohyb vidí');

  // --- prohlížeč: hráč nikdy nedostane tmavou mapu bez chyby, PJ vidí kruh světla ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  await hrac.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await hrac.waitForTimeout(1200); await pj.waitForTimeout(1200);
  ok(await hrac.evaluate(() => window.MAPA.osvetleni === 'tma' && window.MAPA.funkce.videni === true), 'mapa ví, že je tma a že vidění je zapnuté');
  ok(await pj.locator('#svetlaSvg circle').count() === 1, 'PJ vidí kruh dosahu světla');
  ok(await hrac.locator('#svetlaSvg circle').count() === 0, 'hráč kruh dosahu světla nevidí');
  ok(await hrac.evaluate(() => document.querySelectorAll('.vtt-token').length) === 1, 'hráč má na mapě jen svůj token');

  // PJ změní světlo v panelu tokenu (klik na token → sekce Vzhled → Světlo)
  await pj.locator('.vtt-token[data-id="' + h + '"] .puck').click();
  await pj.waitForTimeout(600);
  await pj.selectOption('#vzhledSvetlo', 'lucerna:8');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select concat(svetlo_sahy, '|', svetlo_typ) from tokeny where id=${h}`) === '8.0|lucerna', 'PJ v panelu tokenu vymění pochodeň za lucernu (8 sáhů)');
  await pj.selectOption('#vzhledSvetlo', 'pochoden:6');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select concat(svetlo_sahy, '|', svetlo_typ) from tokeny where id=${h}`) === '6.0|pochoden', 'a zase zpět na pochodeň');

  // --- zpět na „Světlo“ ---
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'den' });
  ok((await tokenyHrace()).includes(gob), 'po rozsvícení mapy hráč vidí nestvůru zase (dosavadní chování)');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`delete from zdi where mapa_id=${MAPA}; update mapy set osvetleni='den' where id=${MAPA}`);
  for (const tk of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: tk.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
