// Nová mapa rovnou z okna pinu (zadání uživatele 2026-11-11: „v pinu na mapě mi chybí možnost přidat
// mapu odtamtud. musím nejdřív zvenku vytvořit mapu a pin pak napojit. líbilo by se mi při vytváření
// nebo úpravě pinu moct nahrát mapu“).
//
// Co se ověřuje:
//   * u existujícího pinu nahrání obrázku založí mapu a pin se na ni rovnou napojí,
//   * u nového pinu se mapa založí a předvybere v „Cílová mapa“, takže se napojí při uložení pinu,
//   * bez názvu to nejde a hráč mapu zakládat nesmí; jiný soubor než obrázek se odmítne,
//   * posuvník „Velikost značek míst“ mění velikost pinů a prohlížeč si volbu pamatuje,
//   * žádná chyba JS.
//
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node pin_mapa_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1, SVET = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };

// Nejmenší platný PNG (1×1 px), ať test nepotřebuje žádný soubor v repu.
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64');

(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const nahrat = (page, pole) => page.request.post(BASE + '/hra/api/mapa_z_pinu.php', { multipart: pole })
    .then(async (r) => { let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; });
  const souborPng = (jmeno) => ({ name: jmeno, mimeType: 'image/png', buffer: PNG });

  sql(`delete from mapa_body where mapa_id=${MAPA}`);
  const bodId = parseInt((await (await pj.request.post(BASE + '/hra/api/mapa_bod_pridat.php', {
    data: { mapa_id: MAPA, nazev: 'Opuštěná šachta', typ: 'sklep', x: 400, y: 400, viditelny_hracum: true },
  })).json()).id, 10);
  ok(bodId > 0, 'pin bez cílové mapy je připravený');

  // --- práva a vstupy ---
  let r = await nahrat(hrac, { svet_id: String(SVET), nazev: 'Cizí mapa', obrazek: souborPng('m.png') });
  ok(r.s === 403, 'hráč mapu zakládat nesmí (403): ' + r.s);
  r = await nahrat(pj, { svet_id: String(SVET), nazev: '', obrazek: souborPng('m.png') });
  ok(r.s === 422, 'bez názvu to server odmítne (422): ' + r.s);
  r = await nahrat(pj, { svet_id: String(SVET), nazev: 'Nesmysl', obrazek: { name: 'a.txt', mimeType: 'text/plain', buffer: Buffer.from('ne') } });
  ok(r.s === 422, 'jiný soubor než obrázek se odmítne (422): ' + r.s);

  // --- mapa z existujícího pinu ---
  const map0 = parseInt(sqlVal(`select count(*) from mapy where svet_id=${SVET}`), 10);
  r = await nahrat(pj, { svet_id: String(SVET), nazev: 'Opuštěná šachta', bod_id: String(bodId), obrazek: souborPng('sachta.png') });
  ok(r.s === 200 && r.mapa_id > 0, 'mapa se založila: ' + JSON.stringify(r).slice(0, 110));
  ok(parseInt(sqlVal(`select count(*) from mapy where svet_id=${SVET}`), 10) === map0 + 1, 've světě je o mapu víc');
  ok(sqlVal(`select cilova_mapa_id from mapa_body where id=${bodId}`) === String(r.mapa_id), 'pin na ni rovnou ukazuje');
  ok(r.napojeno === true, 'odpověď napojení potvrzuje');
  ok(sqlVal(`select concat(sirka_px,'x',vyska_px) from mapy where id=${r.mapa_id}`) === '1x1', 'rozměr se vzal z obrázku');
  const novaMapaId = r.mapa_id;

  // --- v prohlížeči: nový pin, nahrání mapy ji předvybere ---
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(1500);
  await pj.click('#tb-pin'); await pj.waitForTimeout(300);
  await pj.mouse.click(600, 500); await pj.waitForTimeout(500);
  ok(await pj.locator('#popover-pin-novy').isVisible(), 'okno nového pinu je otevřené');
  ok(await pj.locator('#pinNovyNahratMapu').isVisible(), 'je v něm tlačítko „Nahrát mapu odsud…“');
  await pj.click('#pinNovyNahratMapu'); await pj.waitForTimeout(200);
  ok(/název/i.test(await pj.locator('#pinNovyMapaStav').textContent()), 'bez názvu pinu to řekne: ' + (await pj.locator('#pinNovyMapaStav').textContent()).trim());

  await pj.fill('#pinNovyNazev', 'Stará kobka');
  await pj.setInputFiles('#pinNovyMapaSoubor', { name: 'kobka.png', mimeType: 'image/png', buffer: PNG });
  await pj.waitForTimeout(1500);
  const vybrano = await pj.locator('#pinNovyCilovaMapa').inputValue();
  ok(/^\d+$/.test(vybrano) && vybrano !== String(novaMapaId), 'nová mapa je v nabídce rovnou vybraná: ' + vybrano);
  ok(/Hotovo/.test(await pj.locator('#pinNovyMapaStav').textContent()), 'okno hlásí hotovo');
  await pj.click('#pinNovyUlozitBtn'); await pj.waitForTimeout(900);
  ok(sqlVal(`select cilova_mapa_id from mapa_body where nazev='Stará kobka'`) === vybrano, 'uložený pin vede na nahranou mapu');

  // --- velikost značek míst (nastavení vzhledu, drží ho prohlížeč) ---
  await pj.goto(BASE + '/hra/mapa.php?id=2'); await pj.waitForTimeout(1500);
  await pj.click('#tb-grid'); await pj.waitForTimeout(300);
  const pinD = () => pj.evaluate(() => parseFloat(getComputedStyle(document.body).getPropertyValue('--pin-d')) || 0);
  const pred = await pinD();
  ok(pred > 0, 'na mapě s mřížkou mají piny velikost podle sáhu: ' + pred + ' px');
  await pj.locator('#pinyVelikostSlider').fill('160');
  await pj.locator('#pinyVelikostSlider').dispatchEvent('input');
  await pj.waitForTimeout(250);
  const po = await pinD();
  ok(po > pred * 1.4, 'posuvník piny zvětší: ' + pred + ' → ' + po + ' px');
  ok((await pj.locator('#pinyVelikostHodnota').textContent()).trim() === '160', 'popisek ukazuje procenta');
  await pj.reload(); await pj.waitForTimeout(1600);
  ok(Math.abs(await pinD() - po) < 1, 'volba vydrží i po načtení stránky znovu');
  await pj.evaluate(() => { try { localStorage.removeItem('dracak_piny_meritko_2'); } catch (e) {} });

  ok(pj.chybyJs.length === 0, 'žádná chyba JS: ' + pj.chybyJs.join(' | '));

  // Úklid: nahrané obrázky nesmí zůstat v repu (uploads/mapy/ je z větší části verzovaná složka).
  const fs = require('fs'), path = require('path');
  for (const soubor of sqlVal(`select obrazek_cesta from mapy where svet_id=${SVET} and nazev in ('Opuštěná šachta','Stará kobka')`).split('\n')) {
    const jmeno = soubor.trim();
    if (jmeno) { try { fs.unlinkSync(path.join(__dirname, '..', '..', 'uploads', 'mapy', jmeno)); } catch (e) { /* už není */ } }
  }
  sql(`delete from mapa_body where mapa_id=${MAPA}; delete from mapy where svet_id=${SVET} and nazev in ('Opuštěná šachta','Stará kobka')`);
  await b.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.error('PÁD', e); process.exit(2); });
