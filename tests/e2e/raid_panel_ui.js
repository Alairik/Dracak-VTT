// Raid panel (assets/js/raid_panel.js; jen PJ): plovoucí okno s boxy tvorů — nestvůry a NPC nahoře, pak družina; v souboji podle iniciativy; myš nad boxem
// zvýrazní token a naopak; klik vybere token (bez centrování mapy); boxy se přetahují, okno se přesouvá, kompaktní řádky; stav se pamatuje v prohlížeči.
// Mapa 1 (čtverec 70 px). Po resetu dat: mysql dracak < ../fixture/seed.sql; node raid_panel_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const ctxPj = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const pj = await ctxPj.newPage();
  const ctxHr = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const hr = await ctxHr.newPage();
  for (const p of [pj, hr]) { p.chybyJs = []; p.on('pageerror', e => p.chybyJs.push(e.message)); }
  const prihlas = async (p, email, heslo) => {
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  };
  await prihlas(pj, 'test@local', 'test1234');
  await prihlas(hr, 'hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  sql(`delete from kolo_stav; delete from souboj_vyzva`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  const g1 = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 300, y: 300 })).token_id;
  const g2 = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 450, y: 300 })).token_id;
  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 300, y: 450 })).token_id;
  ok(g1 && g2 && h, 'dva goblini a postava hráče jsou na mapě');
  const poradi = async () => (await pj.$$eval('#raidSeznam .raid-box', bs => bs.map(b => parseInt(b.dataset.tokenId, 10))));

  // --- hráč: družina a jen pruh životů ---
  await hr.goto(BASE + '/hra/mapa.php?id=' + MAPA); await hr.waitForTimeout(1000);
  ok(await hr.locator('#tb-raid').count() === 1 && await hr.locator('#souborPj').count() === 0, 'hráč má tlačítko panelu v liště, ale ne tlačítka PJ souboje');
  await hr.click('#tb-raid');
  await hr.waitForTimeout(500);
  const hrJmena = await hr.$$eval('#raidSeznam .raid-box .raid-jmeno', e => e.map(x => x.textContent));
  ok(hrJmena.length === 1 && !/Goblin/.test(hrJmena.join()), 'hráč vidí v panelu jen družinu, žádné nestvůry PJ: ' + hrJmena.join(', '));
  ok(await hr.locator('#raidSeznam .raid-podtitul, #raidSeznam .raid-hp-text, #raidSeznam .raid-mag, #raidSeznam .raid-stitky, #raidSeznam .raid-odznaky, #raidSeznam .raid-skupina').count() === 0
    && await hr.locator('#raidSeznam .raid-hp').count() === 1, 'u hráče je v boxu jen avatar, jméno a pruh životů (bez čísel, podtitulu, magenergie, štítků)');
  const stavHr = await (await hr.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  ok(stavHr.tokeny.every(t => !('podtitul' in t) && !('unava' in t)) && stavHr.tokeny.some(t => t.strana === 'hrac'), 'hráč nedostane PJ doplňky tokenu (podtitul, únava), jen stranu');

  // --- PJ: otevření a řazení ---
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(1200);
  ok(await pj.locator('#raidPanel').isHidden(), 'panel je na začátku zavřený');
  await pj.click('#tb-raid');
  await pj.waitForTimeout(500);
  ok(await pj.locator('#raidPanel').isVisible(), 'tlačítko v liště otevře panel');
  let p = await poradi();
  // Mimo boj je nahoře družina (rozhodnutí uživatele 2026-11-09) — na mapě s hodně tvory PJ nejdřív potřebuje vidět partu.
  ok(p.length === 3 && p[0] === h && p[1] === g1 && p[2] === g2, 'nejdřív družina, pak nestvůry: ' + p.join(','));
  const skupiny = await pj.$$eval('#raidSeznam .raid-skupina', e => e.map(x => x.textContent));
  ok(skupiny.length === 2 && /^Družina · 1/.test(skupiny[0]) && /^Nestvůry a NPC · 2/.test(skupiny[1]), 'dvě skupiny s počty, družina první: ' + skupiny.join(' | '));
  const podtitul = await pj.textContent('#raidSeznam .raid-box[data-token-id="' + h + '"] .raid-podtitul');
  ok(/hráč/.test(podtitul), 'pod jménem postavy je povolání a jméno hráče: ' + podtitul);
  ok(/^Nestvůra/.test(await pj.textContent('#raidSeznam .raid-box[data-token-id="' + g1 + '"] .raid-podtitul')), 'pod jménem nestvůry je druh');
  ok(/\d+ \/ \d+/.test(await pj.textContent('#raidSeznam .raid-box[data-token-id="' + h + '"] .raid-hp-text')), 'box ukazuje životy');

  // --- hover box ↔ token ---
  await pj.hover('#raidSeznam .raid-box[data-token-id="' + g2 + '"]');
  await pj.waitForTimeout(150);
  ok(await pj.locator('.vtt-token[data-id="' + g2 + '"]').evaluate(e => e.classList.contains('raid-hover')), 'myš nad boxem zvýrazní token na mapě');
  await pj.mouse.move(5, 5);
  await pj.waitForTimeout(150);
  ok(!(await pj.locator('.vtt-token[data-id="' + g2 + '"]').evaluate(e => e.classList.contains('raid-hover'))), 'po odchodu myši se zvýraznění zruší');
  await pj.hover('.vtt-token[data-id="' + g1 + '"] .puck');
  await pj.waitForTimeout(150);
  ok(await pj.locator('#raidSeznam .raid-box[data-token-id="' + g1 + '"]').evaluate(e => e.classList.contains('zvyrazneny')), 'myš nad tokenem na mapě zvýrazní jeho box');
  await pj.mouse.move(5, 5);

  // --- klik: výběr bez centrování ---
  const kameraPred = await pj.evaluate(() => document.getElementById('mapWrap') ? document.getElementById('mapWrap').style.transform : '');
  await pj.click('#raidSeznam .raid-box[data-token-id="' + h + '"]');
  await pj.waitForTimeout(400);
  ok(await pj.locator('.vtt-token[data-id="' + h + '"]').evaluate(e => e.classList.contains('vybrany')), 'klik na box vybere token na mapě');
  const kameraPo = await pj.evaluate(() => document.getElementById('mapWrap') ? document.getElementById('mapWrap').style.transform : '');
  ok(kameraPred === kameraPo, 'klik mapu nevycentruje (kamera beze změny)');
  ok(await pj.locator('#raidSeznam .raid-box[data-token-id="' + h + '"]').evaluate(e => e.classList.contains('vybrany')), 'vybraný box je označený');

  // --- přetažení boxu ---
  // Klik na token od 2026-11-10 postranní panel neotevírá (u postavy vyjede rychlý deník); zavřeme, co překáží.
  if (await pj.locator('#panel-spravovat.open').count()) { await pj.click('#panel-spravovat .close-x'); await pj.waitForTimeout(200); }
  if (await pj.locator('#denikDock').isVisible()) { await pj.click('#denikZavrit'); await pj.waitForTimeout(200); }
  await pj.dragAndDrop('#raidSeznam .raid-box[data-token-id="' + g2 + '"] .raid-grip', '#raidSeznam .raid-box[data-token-id="' + g1 + '"]', { targetPosition: { x: 100, y: 4 } });
  await pj.waitForTimeout(400);
  p = await poradi();
  ok(p.indexOf(g2) < p.indexOf(g1), 'přetažením se goblin 2 dostal před goblina 1: ' + p.join(','));
  ok(await pj.locator('#raidReset').isVisible(), 'po ručním přeřazení je vidět „↺ pořadí“');
  await pj.reload(); await pj.waitForTimeout(1500);
  p = await poradi();
  ok(await pj.locator('#raidPanel').isVisible() && p.indexOf(g2) < p.indexOf(g1), 'otevřený panel i ruční pořadí přežijí obnovení stránky: ' + p.join(','));
  await pj.click('#raidReset');
  await pj.waitForTimeout(300);
  p = await poradi();
  ok(p[0] === h && p.indexOf(g1) < p.indexOf(g2) && !(await pj.locator('#raidReset').isVisible()),
    'tlačítko vrátí výchozí pořadí (družina nahoře, goblini v původním pořadí): ' + p.join(','));

  // --- kompaktní řádky a přesun okna ---
  await pj.click('#raidRezim');
  await pj.waitForTimeout(300);
  ok(await pj.locator('#raidPanel.kompakt .raid-box.kompakt').count() === 3, 'přepínač udělá kompaktní řádky');
  const box0 = await pj.locator('#raidPanel').boundingBox();
  await pj.mouse.move(box0.x + 60, box0.y + 18);
  await pj.mouse.down();
  await pj.mouse.move(box0.x - 240, box0.y + 160, { steps: 6 });
  await pj.mouse.up();
  await pj.waitForTimeout(200);
  const box1 = await pj.locator('#raidPanel').boundingBox();
  ok(Math.abs((box1.x - box0.x) + 300) < 6 && Math.abs((box1.y - box0.y) - 142) < 6, 'okno se dá přetáhnout za hlavičku (' + Math.round(box1.x - box0.x) + ', ' + Math.round(box1.y - box0.y) + ')');
  await pj.reload(); await pj.waitForTimeout(1500);
  const box2 = await pj.locator('#raidPanel').boundingBox();
  ok(Math.abs(box2.x - box1.x) < 3 && Math.abs(box2.y - box1.y) < 3 && await pj.locator('#raidPanel.kompakt').count() === 1, 'poloha okna i kompaktní režim přežijí obnovení');
  await pj.click('#raidRezim');
  await pj.click('#raidSbalit');
  await pj.waitForTimeout(200);
  ok(await pj.locator('#raidSeznam').isHidden(), 'tlačítko sbalí okno na hlavičku');
  await pj.click('#raidSbalit');

  // --- živá aktualizace: životy a vyřazení ---
  await api(pj, 'hp_uprava', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: parseInt(require('child_process').execSync(`mysql ${DB} -N -e "select entita_id from tokeny where id=${g1}"`).toString().trim(), 10), delta: -100 });
  await pj.waitForTimeout(3500);
  ok(await pj.locator('#raidSeznam .raid-box[data-token-id="' + g1 + '"].vyrazen').count() === 1, 'vyřazený tvor je v panelu šedý');
  p = await poradi();
  ok(p[p.length - 1] !== g1 || p.indexOf(g2) < p.indexOf(g1), 'vyřazený goblin je na konci své skupiny, před ním goblin 2: ' + p.join(','));

  // --- souboj: pořadí podle iniciativy ---
  await api(pj, 'hp_uprava', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: parseInt(require('child_process').execSync(`mysql ${DB} -N -e "select entita_id from tokeny where id=${g1}"`).toString().trim(), 10), delta: 100 });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [g1, g2, h].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [g1], hod: 2 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [g2], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [h], hod: 4 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await pj.reload(); await pj.waitForTimeout(1800);
  ok(/^Souboj · kolo/.test(await pj.textContent('#raidTitul')), 'nadpis panelu ukazuje souboj: ' + await pj.textContent('#raidTitul'));
  const ini = await pj.$$eval('#raidSeznam .raid-box', bs => bs.map(b => [parseInt(b.dataset.tokenId, 10), b.querySelector('.raid-ini-cislo') ? b.querySelector('.raid-ini-cislo').textContent : null]));
  const cisla = ini.map(x => parseInt(x[1], 10));
  ok(ini.length === 3 && cisla.every((c, i) => i === 0 || cisla[i - 1] >= c) && ini.every(x => x[1] !== null), 'v souboji jsou boxy podle iniciativy sestupně: ' + ini.map(x => x[0] + '=' + x[1]).join(', '));
  ok(await pj.locator('#raidSeznam .raid-skupina').count() === 0, 've souboji nejsou nadpisy skupin');
  ok(await pj.locator('#raidSeznam .raid-box.na-tahu').count() === 1, 'tvor na tahu má zlatý box');
  // hráč v souboji: účastníci, které smí znát — nestvůry PJ v panelu nejsou
  await hr.goto(BASE + '/hra/mapa.php?id=' + MAPA); await hr.waitForTimeout(1800);
  const hrSouboj = await hr.$$eval('#raidSeznam .raid-box .raid-jmeno', e => e.map(x => x.textContent));
  ok(await hr.locator('#raidPanel').isVisible() && hrSouboj.length === 1 && !/Goblin/.test(hrSouboj.join()), 'hráč v souboji vidí v panelu jen sebe, ne nestvůry PJ: ' + hrSouboj.join(', '));
  await api(pj, 'souboj_konec', { mapa_id: MAPA });

  ok(pj.chybyJs.length === 0 && hr.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hr.chybyJs).join('; '));
  sql(`delete from kolo_stav; delete from souboj_vyzva`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
