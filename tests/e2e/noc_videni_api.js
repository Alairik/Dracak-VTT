// Stav mapy „Noc“ a noční vidění (includes/vtt_videni.php, DRACAK_VTT_OSVETLENI, migrace 0108 tokeny.videni_noc_sahy; lupič h630 b5663):
// v noci nevidí nikdo nic bez světla kromě tvora s nočním viděním — ten vidí všechny buňky do svého dosahu s volným paprskem (ne za zdí ani zavřenými dveřmi),
// zóna Tma / Temnota mu zrak bere, v úplné tmě (stav „Tma“) noční vidění nepomáhá. Nastavuje jen PJ. Mapa 1 (čtvercový grid 70 px = 1 sáh).
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node noc_videni_api.js
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
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 1015, y: 315 })).token_id;   // 5 sáhů vpravo
  ok(h && gob, 'token hráče a nestvůry jsou na mapě');

  // --- oprávnění a meze ---
  let r = await api(hrac, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'noc' });
  ok(r.s === 403, 'hráč stav „Noc“ nastavit nesmí (403): ' + r.s);
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'soumrak' });
  ok(r.s === 422, 'neznámý stav osvětlení se odmítne (422): ' + r.s);
  r = await api(hrac, 'token_uprava', { token_id: h, zmeny: { noc: 8 } });
  ok(r.s === 403, 'hráč si noční vidění nastavit nesmí (403): ' + r.s);
  r = await api(pj, 'token_uprava', { token_id: h, zmeny: { noc: 201 } });
  ok(r.s === 422, 'noční vidění nad 200 sáhů se odmítne (422): ' + r.s);
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'noc' });
  ok(r.s === 200 && sqlVal(`select osvetleni from mapy where id=${MAPA}`) === 'noc', 'PJ přepne mapu na „Noc“');

  // --- noc bez nočního vidění = jako tma ---
  ok(!(await tokenyHrace()).includes(gob), 'v noci bez světla a bez nočního vidění hráč nestvůru nevidí');
  ok(pocet((await mlha()).videne_b64) === 1, 'vidí jedinou buňku — tu, na které stojí');
  const pjVidi = (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny.map(x => x.id);
  ok(pjVidi.includes(gob), 'PJ vidí všechno vždycky');

  // --- noční vidění ---
  r = await api(pj, 'token_uprava', { token_id: h, zmeny: { noc: 8 } });
  ok(r.s === 200 && r.zmeny && r.zmeny.noc === 8, 'PJ dá postavě noční vidění 8 sáhů');
  const videno = pocet((await mlha()).videne_b64);
  ok(videno > 150 && videno < 250, 'noční vidění osvětlí kruh o poloměru 8 sáhů (~201 buněk): ' + videno);
  ok((await tokenyHrace()).includes(gob), 'nestvůra 5 sáhů daleko je v noci vidět');
  await api(pj, 'token_presun', { token_id: gob, x: 1365, y: 315 });   // 10 sáhů
  ok(!(await tokenyHrace()).includes(gob), 'nestvůra 10 sáhů daleko (za hranicí dosahu) vidět není');
  await api(pj, 'token_presun', { token_id: gob, x: 1015, y: 315 });
  r = await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: 840, y1: 100, x2: 840, y2: 500, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true, dvere: true });
  const dverId = r.id;
  ok(!(await tokenyHrace()).includes(gob), 'za zavřenými dveřmi noční vidění nevidí');
  await api(pj, 'zed_dvere', { id: dverId, otevrene: true });
  ok((await tokenyHrace()).includes(gob), 'otevřenými dveřmi ano');
  sql(`delete from zdi where mapa_id=${MAPA}`);

  // --- kouzelná tma ---
  const zona = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', nazev: 'Tma', x: 1015, y: 315 })).token_id;
  await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 2, svetlo_typ: 'tma', viditelny: false } });
  ok(!(await tokenyHrace()).includes(gob), 'nestvůra uvnitř zóny Tma není vidět ani nočním viděním');
  await api(pj, 'token_presun', { token_id: zona, x: 665, y: 315 });
  ok(pocet((await mlha()).videne_b64) === 1, 'lupič stojící uvnitř zóny Tma nevidí nic než svou buňku');
  await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 300, svetlo_typ: 'temnota' } });
  ok(pocet((await mlha()).videne_b64) === 1, 'v Temnotě druida noční vidění taky nepomáhá');
  await api(pj, 'token_smazat', { token_id: zona, mapa_id: MAPA });
  ok((await tokenyHrace()).includes(gob), 'po zrušení zóny noční vidění zase funguje');

  // --- světlo se s nočním viděním doplňuje ---
  await api(pj, 'token_presun', { token_id: gob, x: 1365, y: 315 });   // 10 sáhů, za hranicí nočního vidění
  ok(!(await tokenyHrace()).includes(gob), 'nestvůra 10 sáhů daleko bez světla vidět není');
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { svetlo: 2, svetlo_typ: 'vlastni' } });
  ok((await tokenyHrace()).includes(gob), 'když nestvůra sama svítí, je vidět i za hranicí nočního vidění (osvětlená buňka na přímý výhled)');
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { svetlo: null, svetlo_typ: null } });

  // --- úplná tma: noční vidění nepomáhá ---
  await api(pj, 'token_presun', { token_id: gob, x: 1015, y: 315 });
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  ok(r.s === 200, 'PJ přepne mapu na úplnou „Tmu“');
  ok(!(await tokenyHrace()).includes(gob), 'v úplné tmě noční vidění nepomáhá (b5663): nestvůru 5 sáhů daleko hráč nevidí');
  ok(pocet((await mlha()).videne_b64) === 1, 'v úplné tmě vidí jen svou buňku');

  // --- pět stupňů tmy podle knihy (Lektvar sovích očí): noční vidění v husté tmě, tmě i řídké tmě, ne v naprosté; pološero jako den ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { noc: 8 } });
  for (const [stav, vidiNoc] of [['ridka', true], ['noc', true], ['husta', true], ['tma', false]]) {
    r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: stav });
    ok(r.s === 200, 'PJ nastaví stupeň tmy „' + stav + '“');
    ok((await tokenyHrace()).includes(gob) === vidiNoc, stav + ': noční vidění ' + (vidiNoc ? 'funguje (nestvůra 5 sáhů daleko je vidět)' : 'nepomáhá (nestvůra není vidět)'));
  }
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'polosero' });
  ok(r.s === 200 && (await tokenyHrace()).includes(gob) && (await mlha()).videni !== true, 'pološero je jako den: hráč vidí bez světla, živé vidění je vypnuté');
  r = await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'soumrak' });
  ok(r.s === 422, 'neznámý stupeň se odmítne (422)');
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'den' });

  // --- prohlížeč: PJ nastaví noční vidění v panelu tokenu a stav „Noc“ v popoveru Mlha ---
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'den' });
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForTimeout(1200);
  ok(await pj.locator('#mapaOsvetleni option[value="noc"]').count() === 1, 'popover Mlha nabízí stav „Noc“');
  await pj.click('#tb-mlha');
  await pj.waitForTimeout(300);
  await pj.selectOption('#mapaOsvetleni', 'noc');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select osvetleni from mapy where id=${MAPA}`) === 'noc', 'PJ přepne mapu na Noc v rozhraní');
  await pj.locator('.vtt-token[data-id="' + h + '"] .puck').click();
  await pj.waitForTimeout(600);
  ok(await pj.locator('#vzhledNoc').isVisible(), 'v panelu postavy je řádek Noční vidění');
  await pj.fill('#vzhledNoc', '12');
  await pj.locator('#vzhledNoc').dispatchEvent('change');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select videni_noc_sahy from tokeny where id=${h}`) === '12', 'PJ nastaví noční vidění 12 sáhů');
  await pj.fill('#vzhledNoc', '');
  await pj.locator('#vzhledNoc').dispatchEvent('change');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select ifnull(videni_noc_sahy,'-') from tokeny where id=${h}`) === '-', 'prázdné pole noční vidění zruší');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`delete from zdi where mapa_id=${MAPA}; update mapy set osvetleni='den' where id=${MAPA}`);
  for (const tk of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: tk.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
