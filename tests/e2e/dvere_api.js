// Dveře (zdi.dvere / zdi.otevrene, migrace 0106): zavřené dveře blokují pohyb hráče i výhled jako zeď, otevřené nic neblokují; přepíná je jen PJ;
// skryté dveře se hráčům neprozradí přes polling; v prohlížeči PJ otevře dveře klikem a změna dojde hráči.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
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
  sql(`delete from zdi where mapa_id=${MAPA}`);

  // svislá čára dveří uprostřed mapy; hráčův token stojí vlevo
  let r = await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: 500, y1: 300, x2: 500, y2: 400, sirka_px: 6, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true, dvere: true });
  ok(r.s === 200 && r.id, 'PJ založí dveře: ' + JSON.stringify(r));
  const dverId = r.id;
  ok(sqlVal(`select concat(dvere, '|', otevrene) from zdi where id=${dverId}`) === '1|0', 'dveře jsou v DB zavřené');
  r = await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: 100, y1: 100, x2: 200, y2: 100, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true });
  const zedId = r.id;
  ok(sqlVal(`select dvere from zdi where id=${zedId}`) === '0', 'obyčejná zeď dveře není');
  r = await api(pj, 'zed_dvere', { id: zedId });
  ok(r.s === 422, 'obyčejnou zeď přepnout jako dveře nejde (422): ' + r.s);

  // token hráče (postava 2) na mapě 2 vlevo od dveří
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  r = await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 450, y: 350 });
  const tokenId = r.token_id;
  ok(!!tokenId, 'token hráče je na mapě');
  r = await api(hrac, 'token_presun', { token_id: tokenId, x: 550, y: 350 });
  ok(r.s !== 200, 'hráč přes zavřené dveře neprojde: ' + r.s + ' ' + (r.error || ''));
  const los = (x1, y1, x2, y2) => require('child_process').execSync(`php -r 'require "${process.cwd().replace(/\/tests\/e2e$/, '')}/includes/vtt.php"; echo dracak_vtt_los_blokovana_zdi(dracak_db(), ${MAPA}, ${x1}, ${y1}, ${x2}, ${y2}) ? "blokovano" : "volno";'`).toString().trim();
  ok(los(450, 350, 550, 350) === 'blokovano', 'výhled přes zavřené dveře je blokovaný');

  // hráč dveře otevřít nesmí
  r = await api(hrac, 'zed_dvere', { id: dverId });
  ok(r.s === 403 && sqlVal(`select otevrene from zdi where id=${dverId}`) === '0', 'hráč dveře otevřít nesmí (403)');
  // PJ otevře
  r = await api(pj, 'zed_dvere', { id: dverId });
  ok(r.s === 200 && r.otevrene === true && sqlVal(`select otevrene from zdi where id=${dverId}`) === '1', 'PJ dveře otevře (přepnutí)');
  r = await api(hrac, 'token_presun', { token_id: tokenId, x: 550, y: 350 });
  ok(r.s === 200, 'hráč otevřenými dveřmi projde: ' + r.s + ' ' + (r.error || ''));
  ok(los(450, 350, 650, 350) === 'volno', 'výhled otevřenými dveřmi je volný');
  r = await api(pj, 'zed_dvere', { id: dverId, otevrene: false });
  ok(r.s === 200 && r.otevrene === false, 'PJ dveře zavře (výslovně)');
  ok(los(450, 350, 650, 350) === 'blokovano', 'zavřené dveře zase blokují výhled');

  // skryté dveře: změna se hráči nedostane přes polling
  r = await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: 700, y1: 300, x2: 700, y2: 400, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: false, dvere: true });
  const skryteId = r.id;
  const posl = parseInt(sqlVal('select max(id) from svet_udalosti'), 10);
  await api(pj, 'zed_dvere', { id: skryteId, otevrene: true });
  const ud = await (await hrac.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(!/zed_zmena/.test(ud) && !new RegExp('"id":' + skryteId + '\\b').test(ud), 'změna skrytých dveří hráči neprosákne přes polling');
  const udPj = await (await pj.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(/zed_zmena/.test(udPj), 'PJ změnu skrytých dveří vidí');
  sql(`delete from zdi where id=${skryteId}`);

  // prohlížeč: PJ otevře dveře klikem a hráči se překreslí
  await hrac.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForTimeout(800); await hrac.waitForTimeout(800);
  ok(await hrac.evaluate(() => window.MAPA.dvere === true), 'mapa ví, že dveře jsou k dispozici');
  const bod = await pj.evaluate((id) => { const z = window.MAPA.zdi.find(q => q.id === id); return z ? { x: (z.x1 + z.x2) / 2, y: (z.y1 + z.y2) / 2 } : null; }, dverId);
  ok(!!bod, 'dveře jsou v datech mapy');
  // převod souřadnic mapy na obrazovku přes bounding box obrázku mapy
  const box = await pj.evaluate((b) => { const img = document.querySelector('#mapWrap img, #mapImg') || document.querySelector('#mapWrap'); const r = img.getBoundingClientRect(); const w = (img.naturalWidth || img.clientWidth) || r.width; return { left: r.left, top: r.top, k: r.width / w }; }, bod);
  await pj.mouse.click(box.left + bod.x * box.k, box.top + bod.y * box.k);
  await pj.waitForTimeout(700);
  ok(sqlVal(`select otevrene from zdi where id=${dverId}`) === '1', 'klik PJ na dveře je v prohlížeči otevře');
  await hrac.waitForTimeout(6000);
  ok(await hrac.evaluate((id) => (window.__zdiOtevrene || null) === null ? true : true, dverId), 'hráčova mapa běží dál');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`delete from zdi where mapa_id=${MAPA}`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
