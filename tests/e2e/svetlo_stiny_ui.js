// Světlo a stíny na mapě „Tma“ (assets/js/svetlo_stiny.js, includes/vtt_polygon.php, dracak_vtt_videni_geometrie): server pošle hráči jen mnohoúhelníky viditelnosti jeho
// tokenů a zdrojů světla, které osvětlují něco, co vidí (cizí zdroj za zdí se neposílá, zdi ne); klient z nich vykreslí plynulé světlo (slábne od zdroje ke kraji)
// a stín za zdí. Kontrola na pixelech plátna s mlhou. Mapa 1 (čtvercový grid 70 px = 1 sáh). Po resetu dat: mysql dracak < ../fixture/seed.sql; node svetlo_stiny_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hr = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const geomHrace = async () => (await (await hr.request.get(BASE + '/hra/api/mlha_stav.php?mapa_id=' + MAPA)).json()).geom;

  sql(`delete from zdi where mapa_id=${MAPA}; delete from mlha_valky where mapa_id=${MAPA}`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 665, y: 315 })).token_id;
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  const zed = (x1, y1, x2, y2) => sql(`insert into zdi (mapa_id,x1,y1,x2,y2,sirka_px,blokuje_pohyb,blokuje_vystrel,viditelna_hracum) values (${MAPA},${x1},${y1},${x2},${y2},4,1,1,1)`);
  zed(875, 140, 875, 420);   // svislá zeď 3 sáhy vpravo od hráče
  zed(1000, 0, 1000, 1800);  // dlouhá zeď přes celou mapu: co je za ní, hráč nevidí vůbec

  // --- co server pošle ---
  let g = await geomHrace();
  ok(g && g.svetla.length === 1 && g.divaci.length === 1, 'hráč dostane geometrii: 1 zdroj světla a 1 divák');
  ok(g.svetla[0].r === 420 && g.svetla[0].x === 665 && g.svetla[0].poly.length >= 4, 'světlo pochodně: poloměr 6 sáhů = 420 px, mnohoúhelník má vrcholy: ' + JSON.stringify([g.svetla[0].r, g.svetla[0].poly.length]));
  ok(!JSON.stringify(g).includes('"zdi"') && !('zdi' in g), 'zdi samotné klient nedostane');
  // zdroj světla za dlouhou zdí, který hráč nijak nezahlédne, se neposílá
  const cizi = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', x: 1400, y: 300, nazev: 'Cizí pochodeň' })).token_id;
  await api(pj, 'token_uprava', { token_id: cizi, zmeny: { svetlo: 4, svetlo_typ: 'pochoden' } });
  g = await geomHrace();
  ok(g.svetla.length === 1, 'zdroj světla, který hráč nevidí a který nic viditelného neosvětluje, se neposílá (' + g.svetla.length + ')');
  // stejná pochodeň na dosah se pošle
  await api(pj, 'token_presun', { token_id: cizi, x: 595, y: 175 });
  g = await geomHrace();
  ok(g.svetla.length === 2, 'pochodeň, jejíž světlo hráč vidí, se pošle (' + g.svetla.length + ')');
  await api(pj, 'token_smazat', { token_id: cizi, mapa_id: MAPA });
  // PJ náhled cizí mlhy má geometrii taky; hráč náhled nesmí
  const hracUcet = 3;
  const nahled = await (await pj.request.get(BASE + '/hra/api/mlha_nahled.php?mapa_id=' + MAPA + '&ucet_id=' + hracUcet)).json();
  ok(nahled.geom && nahled.geom.svetla.length === 1, 'PJ náhled hráčovy mlhy nese geometrii');
  ok((await hr.request.get(BASE + '/hra/api/mlha_nahled.php?mapa_id=' + MAPA + '&ucet_id=' + hracUcet)).status() === 403, 'hráč náhled cizí mlhy nedostane (403)');
  // na mapě „Světlo“ žádná geometrie
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'den' });
  const den = await (await hr.request.get(BASE + '/hra/api/mlha_stav.php?mapa_id=' + MAPA)).json();
  ok(den.videni === false && !den.geom, 'na mapě „Světlo“ se geometrie nepočítá');
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  sql(`delete from mlha_valky where mapa_id=${MAPA}`);

  // --- vykreslení: pixely plátna s mlhou (souřadnice plátna = souřadnice mapy; průhlednost = kolik tmy zbývá) ---
  await hr.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await hr.waitForFunction(() => { const c = document.getElementById('mlhaCanvas'); return c && c.width > 100; }, null, { timeout: 15000 });
  await hr.waitForTimeout(1500);
  const alfa = (body) => hr.evaluate((b) => { const c = document.getElementById('mlhaCanvas'); const x = c.getContext('2d'); return b.map(([px, py]) => x.getImageData(px, py, 1, 1).data[3]); }, body);
  const [uZdroje, uBlizko, uStred, uKraje, zaZdi, predZdi, daleko, nahore] = await alfa([[665, 315], [665, 350], [665, 470], [665, 700], [980, 315], [850, 315], [1900, 1400], [665, 120]]);
  ok(uZdroje < 90, 'u pochodně je tma skoro pryč (průhlednost ' + uZdroje + ' z 255)');
  ok(uStred > uBlizko && uKraje > uStred, 'světlo plynule slábne od zdroje ke kraji: ' + [uBlizko, uStred, uKraje].join(' < '));
  ok(predZdi < 140, 'před zdí je světlo (' + predZdi + ')');
  ok(zaZdi > 225, 'přímo za zdí je stín, tma plná (' + zaZdi + ')');
  ok(daleko > 245, 'daleko od světla je plná tma (' + daleko + ')');
  ok(zaZdi > predZdi + 100, 'stín za zdí je výrazně tmavší než světlo před zdí');
  ok(nahore < 200, 'nad koncem zdi se světlo dostane dál (' + nahore + ')');
  // PJ otevře dveře — tady jen kontrola, že přepnutí na jiné osvětlení mapy nic nerozbije
  ok(hr.chybyJs.length === 0, 'žádná chyba JavaScriptu u hráče: ' + hr.chybyJs.join('|'));

  // --- dohořívající světlo slábne (sila = zbývající směny / 3, nejvýš 1), dosah podle pravidel se nemění ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { zbyva: 3 } });
  g = await geomHrace();
  ok(g.svetla[0].sila === 1 && g.svetla[0].r === 420, 'pochodeň se 3 směnami zbývá hoří naplno (síla 1, dosah 420 px)');
  await api(pj, 'token_uprava', { token_id: h, zmeny: { zbyva: 1 } });
  g = await geomHrace();
  ok(g.svetla[0].sila === 0.33 && g.svetla[0].r === 420, 'poslední směna: síla 0,33, dosah podle pravidel beze změny: ' + JSON.stringify([g.svetla[0].sila, g.svetla[0].r]));
  await hr.reload();
  await hr.waitForFunction(() => { const c = document.getElementById('mlhaCanvas'); return c && c.width > 100; }, null, { timeout: 15000 });
  await hr.waitForTimeout(1500);
  const [slabyStred, slabyKraj] = await alfa([[665, 470], [665, 640]]);
  ok(slabyStred > uStred + 20, 'dohořívající světlo je v téže vzdálenosti tmavší než plné (' + slabyStred + ' > ' + uStred + ')');
  await api(pj, 'token_uprava', { token_id: h, zmeny: { zbyva: 3 } });
  await api(pj, 'cas_posun', { svet_id: 1, smeny: 1 });
  g = await geomHrace();
  ok(g.svetla[0].sila === 0.67, 'po posunu času o směnu zbývají 2 → síla 0,67: ' + g.svetla[0].sila);
  await api(pj, 'token_uprava', { token_id: h, zmeny: { zbyva: 3 } });

  // --- přehled světla pro PJ: všechna světla, bez diváků; hráč ho nedostane ---
  const prehled = await (await pj.request.get(BASE + '/hra/api/svetlo_pj.php?mapa_id=' + MAPA)).json();
  ok(prehled.videni === true && prehled.geom.bez_divaku === true && prehled.geom.divaci.length === 0 && prehled.geom.svetla.length === 1, 'PJ dostane přehled světla bez diváků: ' + JSON.stringify([prehled.videni, prehled.geom && prehled.geom.svetla.length]));
  ok((await hr.request.get(BASE + '/hra/api/svetlo_pj.php?mapa_id=' + MAPA)).status() === 403, 'hráč přehled světla nedostane (403)');
  // přepínač v okně Mlha: PJ uvidí zatemněnou mapu se světlem a stínem za zdí
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForFunction(() => !!document.getElementById('svetloPjChk'), null, { timeout: 15000 });
  const alfaPj = (body) => pj.evaluate((b) => { const c = document.getElementById('mlhaCanvas'); const x = c.getContext('2d'); return b.map(([px, py]) => x.getImageData(px, py, 1, 1).data[3]); }, body);
  await pj.waitForTimeout(800);
  ok((await alfaPj([[665, 315]]))[0] === 0, 'bez přepínače PJ mlhu ani tmu nevidí (plátno prázdné)');
  await pj.evaluate(() => { const c = document.getElementById('svetloPjChk'); c.checked = true; c.dispatchEvent(new Event('change')); });
  await pj.waitForFunction(() => { const c = document.getElementById('mlhaCanvas'); return c && c.width > 100 && c.getContext('2d').getImageData(1900, 1400, 1, 1).data[3] > 100; }, null, { timeout: 15000 });
  const [pjZdroj, pjPredZdi, pjZaZdi, pjDaleko] = await alfaPj([[665, 315], [850, 315], [980, 315], [1900, 1400]]);
  ok(pjZdroj < 90 && pjPredZdi < 140, 'PJ vidí světlo kolem pochodně (' + pjZdroj + ', před zdí ' + pjPredZdi + ')');
  ok(pjZaZdi > pjPredZdi + 60, 'PJ vidí stín za zdí (' + pjZaZdi + ' proti ' + pjPredZdi + ' před zdí)');
  ok(pjDaleko > 130 && pjDaleko < 180, 'daleko od světla je mapa zatemněná, ne zcela zhasnutá (' + pjDaleko + ')');
  await pj.evaluate(() => { const c = document.getElementById('svetloPjChk'); c.checked = false; c.dispatchEvent(new Event('change')); });
  await pj.waitForTimeout(800);
  ok((await alfaPj([[1900, 1400]]))[0] === 0, 'po vypnutí přepínače je plátno zase prázdné');
  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu u PJ: ' + pj.chybyJs.join('|'));

  sql(`delete from zdi where mapa_id=${MAPA}`);
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'den' });
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
