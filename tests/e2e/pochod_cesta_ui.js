// Pochod po mapě světa (plán C bod 14 + 17): měřítko mapy (mapy.mil_na_1000px, migrace 0110) → měřidlo ukáže míle a pošle je do kalkulačky cesty;
// zápis cesty (únava postavám) posune i čas světa (hodiny světa, includes/vtt_cas.php). Mapa 1. Po resetu dat: mysql dracak < ../fixture/seed.sql; node pochod_cesta_ui.js
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
    const p = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', e => p.chybyJs.push(e.message));
    p.on('dialog', d => d.accept());
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  sql(`update mapy set mil_na_1000px=NULL where id=${MAPA}; update svet set aktualni_smena=48, aktualni_den_offset=0 where id=${SVET}; update postavy set unava=0 where id=2`);

  // --- měřítko: oprávnění a meze ---
  let r = await api(hrac, 'mapa_meritko', { mapa_id: MAPA, mil_na_1000px: 25 });
  ok(r.s === 403, 'hráč měřítko nastavit nesmí (403): ' + r.s);
  r = await api(pj, 'mapa_meritko', { mapa_id: MAPA, mil_na_1000px: 0 });
  ok(r.s === 422, 'měřítko 0 se odmítne (422)');
  r = await api(pj, 'mapa_meritko', { mapa_id: MAPA, mil_na_1000px: 100001 });
  ok(r.s === 422, 'měřítko nad 100 000 se odmítne (422)');
  r = await api(pj, 'mapa_meritko', { mapa_id: 99999, mil_na_1000px: 5 });
  ok(r.s === 404, 'neexistující mapa (404)');
  r = await api(pj, 'mapa_meritko', { mapa_id: MAPA, mil_na_1000px: 25 });
  ok(r.s === 200 && sqlVal(`select mil_na_1000px from mapy where id=${MAPA}`) === '25.00', 'PJ nastaví měřítko 25 mil na 1000 px');

  // --- měřidlo v mílích a odkaz do kalkulačky ---
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForTimeout(1500);
  await hrac.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await hrac.waitForTimeout(1000);
  ok(await hrac.locator('#meridloCesta').count() === 0 && await hrac.locator('#meritkoMil').count() === 0, 'hráč nemá pole měřítka ani odkaz do kalkulačky');
  const zoom = parseFloat((await pj.textContent('#zoomInfo')).replace(/[^\d.,]/g, '').replace(',', '.')) / 100;
  await pj.click('#tb-ruler');
  await pj.mouse.move(500, 450); await pj.mouse.down(); await pj.mouse.move(900, 450, { steps: 8 });
  const pill = pj.locator('#meridloCesta');
  await pj.waitForTimeout(300);
  ok(await pill.isVisible(), 'po změření se objeví odkaz „Spočítat cestu“');
  const text = await pill.textContent();
  const mil = parseFloat(text.replace(/[^\d,]/g, '').replace(',', '.'));
  const cekane = 400 / zoom / 1000 * 25;
  ok(Math.abs(mil - cekane) < Math.max(0.3, cekane * 0.05), 'vzdálenost v mílích odpovídá měřítku: ' + text + ' (čekáno ≈ ' + cekane.toFixed(1) + ')');
  await pj.mouse.up();
  const href = await pill.getAttribute('href');
  ok(/cesta\.php\?id=1&vzdalenost=\d/.test(href), 'odkaz míří do kalkulačky s vzdáleností: ' + href);
  await Promise.all([pj.waitForNavigation(), pill.click()]);
  ok(Math.abs(parseFloat(await pj.inputValue('#cs-vzdalenost')) - mil) < 0.2, 'kalkulačka má vzdálenost z měřidla: ' + await pj.inputValue('#cs-vzdalenost'));

  // --- zápis cesty posune i čas světa ---
  await pj.fill('#cs-vzdalenost', '12');
  await pj.click('#cs-spocitat');
  await pj.waitForSelector('#cs-zapsat', { timeout: 15000 });
  ok(await pj.locator('#cs-posunout-cas').isChecked(), 'u zápisu je zaškrtnuté „posunout čas světa“');
  const odpoved = pj.waitForResponse(x => x.url().includes('cesta_vypocet.php') && x.request().postData().includes('"zapsat":true'));
  await pj.click('#cs-zapsat');
  const d = await (await odpoved).json();
  ok(d.cas && d.cas.smena !== undefined, 'odpověď nese nový čas světa: ' + JSON.stringify(d.cas).slice(0, 80));
  const hodin = d.vysledek.hodin;
  const smeny = Math.round(hodin * 6);
  const celkem = 48 + smeny;
  ok(parseInt(sqlVal(`select aktualni_smena from svet where id=${SVET}`), 10) === celkem % 144 && parseInt(sqlVal(`select aktualni_den_offset from svet where id=${SVET}`), 10) === Math.floor(celkem / 144),
    'čas světa se posunul o ' + hodin + ' h = ' + smeny + ' směn (' + sqlVal(`select concat(aktualni_den_offset, ' / ', aktualni_smena) from svet where id=${SVET}`) + ')');
  ok(parseInt(sqlVal('select unava from postavy where id=2'), 10) >= 0 && d.zapsano.length >= 1, 'únava byla zapsána postavě');
  await pj.waitForTimeout(500);
  ok(/Čas světa posunut/.test(await pj.textContent('#cs-vysledek')), 'stránka potvrdí posun času');

  // --- bez zaškrtnutí se čas nehýbe ---
  sql(`update svet set aktualni_smena=48, aktualni_den_offset=0 where id=${SVET}`);
  r = await api(pj, 'cesta_vypocet', { svet_id: SVET, vzdalenost: 12, povrch: 10, teren: 1, vliv: 1, tempo: 'chuze', zastav: 2, ucastnici: [{ postava_id: 2 }], zvirata: [], zapsat: true, posunout_cas: false });
  ok(r.s === 200 && r.cas === null && sqlVal(`select aktualni_smena from svet where id=${SVET}`) === '48', 'bez „posunout čas“ se čas světa nemění');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`update mapy set mil_na_1000px=NULL where id=${MAPA}; update svet set aktualni_smena=48, aktualni_den_offset=0 where id=${SVET}; update postavy set unava=0 where id=2`);
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
