// Tažení tokenu se světlem (PJ): kruh dosahu světla se hýbe s tokenem v reálném čase, po položení se hned přepočítá světlo a stíny (bez čekání na další dotaz
// na změny); zrušené tažení vrátí kruh na původní místo. Mapa 1 (čtvercový grid 70 px). Po resetu dat: mysql dracak < ../fixture/seed.sql; node svetlo_tazeni_ui.js
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
  const pj = await (await browser.newContext({ viewport: { width: 1600, height: 1000 } })).newPage();
  pj.chybyJs = []; pj.on('pageerror', (e) => pj.chybyJs.push(e.message));
  await pj.goto(BASE + '/index.php'); await pj.fill('#email', 'test@local'); await pj.fill('#password', 'test1234');
  await Promise.all([pj.waitForNavigation(), pj.click('button[type=submit]')]);
  const api = async (n, b) => { const r = await pj.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };

  sql(`delete from zdi where mapa_id=${MAPA}; delete from mlha_valky where mapa_id=${MAPA}`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api('token_smazat', { token_id: t.id, mapa_id: MAPA });
  await api('mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  const h = (await api('token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 665, y: 315 })).token_id;
  await api('token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  const zona = (await api('token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', x: 1295, y: 805, nazev: 'Temnota' })).token_id;
  await api('token_uprava', { token_id: zona, zmeny: { svetlo: 2, svetlo_typ: 'tma', viditelny: false } });

  await pj.evaluate((m) => { try { localStorage.setItem('dracak_svetlo_pj_' + m, '1'); } catch (e) {} }, MAPA);
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForSelector('.vtt-token[data-id="' + h + '"] .puck', { timeout: 15000 });
  await pj.waitForFunction(() => { const c = document.getElementById('mlhaCanvas'); return c && c.width > 100 && c.getContext('2d').getImageData(1900, 1400, 1, 1).data[3] > 100; }, null, { timeout: 15000 });
  await pj.waitForTimeout(500);

  const kruh = (id) => pj.evaluate((i) => { const c = document.querySelector('#svetlaSvg circle[data-token-id="' + i + '"]'); return c ? { x: Number(c.getAttribute('cx')), y: Number(c.getAttribute('cy')) } : null; }, id);
  const tokenPos = (id) => pj.evaluate((i) => { const e = document.querySelector('.vtt-token[data-id="' + i + '"]'); return { x: parseFloat(e.style.left), y: parseFloat(e.style.top) }; }, id);
  const kamera = () => pj.evaluate(() => { const w = document.getElementById('mapWrap'); const r = w.getBoundingClientRect(); return { x: r.x, y: r.y, k: r.width / w.offsetWidth }; });
  const alfa = (x, y) => pj.evaluate(([px, py]) => document.getElementById('mlhaCanvas').getContext('2d').getImageData(px, py, 1, 1).data[3], [x, y]);
  let pozadavky = [];
  pj.on('request', (r) => { if (/svetlo_pj\.php/.test(r.url())) pozadavky.push(Date.now()); });

  ok(JSON.stringify(await kruh(h)) === JSON.stringify({ x: 665, y: 315 }), 'kruh světla leží na pochodni: ' + JSON.stringify(await kruh(h)));
  ok(JSON.stringify(await kruh(zona)) === JSON.stringify({ x: 1295, y: 805 }), 'kruh zóny tmy leží na svém tokenu');

  // --- tažení pochodně o 6 buněk doprava: kruh jede s tokenem ---
  const cam = await kamera();
  const naObrazovku = (mx, my) => ({ x: cam.x + mx * cam.k, y: cam.y + my * cam.k });
  const od = naObrazovku(665, 315), do6 = naObrazovku(1085, 315);
  ok(do6.x < 1580 && do6.y < 990 && od.x > 5, 'cíl tažení je v okně prohlížeče (zoom ' + cam.k.toFixed(2) + ')');
  const alfaCil0 = await alfa(1085, 315);
  await pj.mouse.move(od.x, od.y);
  await pj.mouse.down();
  await pj.mouse.move((od.x + do6.x) / 2, od.y, { steps: 6 });
  const pul = await kruh(h), pulTok = await tokenPos(h);
  ok(pul && pul.x > 665 && pul.x < 1085 && Math.abs(pul.x - pulTok.x) < 1.5 && Math.abs(pul.y - pulTok.y) < 1.5, 'uprostřed tažení kruh jede s tokenem v reálném čase: kruh ' + JSON.stringify(pul) + ', token ' + JSON.stringify(pulTok));
  await pj.mouse.move(do6.x, do6.y, { steps: 6 });
  const konec = await kruh(h), konecTok = await tokenPos(h);
  ok(konec && Math.abs(konec.x - konecTok.x) < 1.5 && konec.x === 1085, 'na konci tažení je kruh na cílové buňce: ' + JSON.stringify(konec));
  ok(Math.abs((await alfa(1085, 315)) - alfaCil0) < 10, 'během tažení se světlo a stíny nepřepočítávají (v cíli ještě stará tma ' + alfaCil0 + ' → ' + (await alfa(1085, 315)) + ')');
  pozadavky = [];
  await pj.mouse.up();
  const tUp = Date.now();
  // po položení se světlo přepočte hned (dřív než dorazí běžný dotaz na změny)
  await pj.waitForFunction(() => { const c = document.getElementById('mlhaCanvas'); return c.getContext('2d').getImageData(1085, 315, 1, 1).data[3] < 90; }, null, { timeout: 8000 });
  const trvani = Date.now() - tUp;
  ok(trvani < 1500, 'po položení se světlo kolem nové polohy přepočetlo do ' + trvani + ' ms');
  ok(pozadavky.length >= 1, 'klient si po položení sám vyžádal nový přehled světla (' + pozadavky.length + '×)');
  ok(+sqlVal(`select x from tokeny where id=${h}`) === 1085, 'server má novou polohu pochodně');
  const kruhPo = await kruh(h);
  ok(kruhPo && kruhPo.x === 1085 && kruhPo.y === 315, 'kruh zůstal na nové poloze: ' + JSON.stringify(kruhPo));

  // --- zóna tmy se táhne stejně (druhý druh kruhu) ---
  const zOd = naObrazovku(1295, 805), zDo = naObrazovku(1295, 595);
  await pj.mouse.move(zOd.x, zOd.y);
  await pj.mouse.down();
  await pj.mouse.move(zDo.x, zDo.y, { steps: 8 });
  const zKruh = await kruh(zona);
  ok(zKruh && zKruh.y === 595, 'kruh zóny tmy jede s tokenem (y = ' + (zKruh && zKruh.y) + ')');
  // Esc / zrušení: druhý prst přeruší tažení a kruh se vrátí
  await pj.keyboard.press('Escape');
  await pj.mouse.up();
  await pj.waitForTimeout(1200);
  const zPo = await kruh(zona);
  const zSrv = +sqlVal(`select y from tokeny where id=${zona}`);
  ok(zPo && zPo.y === zSrv, 'po tažení leží kruh zóny tam, kde je token na serveru (' + (zPo && zPo.y) + ' = ' + zSrv + ')');

  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.join('|'));
  await pj.evaluate((m) => { try { localStorage.removeItem('dracak_svetlo_pj_' + m); } catch (e) {} }, MAPA);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api('token_smazat', { token_id: t.id, mapa_id: MAPA });
  await api('mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'den' });
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
