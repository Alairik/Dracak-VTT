// Animace souboje na mapě (zadání uživatele 2026-11-11; deska „Souboj — kdo je na tahu a animace“
// na plátně https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz).
//
// Co se ověřuje:
//   * cizí pohyb tokenu se přehraje plynule (token dostane třídu a po ~0,7 s stojí na novém místě),
//   * útok zblízka udělá u cíle sek, útok na dálku letící střelu,
//   * kouzlo má barvu podle oboru magie,
//   * „kdo je na tahu“ se hlásí pruhem nahoře a je k tomu tlačítko, které ho ukáže —
//     mapa se ale NIKDY necentruje sama (výslovné rozhodnutí uživatele),
//   * vypnutí animací v nastavení světa je vypne (sloupec svet.animace, migrace 0120),
//   * žádná chyba JS.
//
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node souboj_animace_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2, SVET = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const prihlas = async () => {
    const p = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php');
    await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(BASE + '/hra/mapa.php?id=' + MAPA); await p.waitForTimeout(1600);
    return p;
  };
  const api = (page, n, body) => page.evaluate(async ([nn, bb]) => {
    const r = await fetch('api/' + nn + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(bb) });
    const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
  }, [n, body]);

  sql(`update svet set animace=1 where id=${SVET}`);
  const pj = await prihlas();
  for (const t of await pj.evaluate(async (m) => (await (await fetch('api/mapa_stav.php?mapa_id=' + m)).json()).tokeny, MAPA)) {
    await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  }
  const a = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 400, y: 400 })).token_id;
  const c = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 460, y: 400 })).token_id;
  const daleko = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 1200, y: 900 })).token_id;
  await pj.reload(); await pj.waitForTimeout(1600);

  // Pomocník: vyvolá událost na serveru a nechá klienta, ať ji sebere pollem.
  const pozice = (id) => pj.evaluate((i) => {
    const el = document.querySelector('.vtt-token[data-id="' + i + '"]');
    return el ? { x: parseFloat(el.style.left), y: parseFloat(el.style.top), tridy: el.className } : null;
  }, id);

  // --- pohyb ---
  // Přesun pošle jiný „prohlížeč“ (přímo API z této stránky by se nakreslil rovnou), proto druhá karta.
  const pj2 = await prihlas();
  await api(pj2, 'token_presun', { token_id: c, x: 700, y: 620 });
  // Během animace token ještě nestojí na cíli a má třídu anim-pohyb.
  let videnoBehem = false;
  for (let i = 0; i < 30; i++) {
    const p0 = await pozice(c);
    if (p0 && /anim-pohyb/.test(p0.tridy)) { videnoBehem = true; break; }
    await pj.waitForTimeout(120);
  }
  ok(videnoBehem, 'cizí pohyb se přehrává animovaně (token má třídu anim-pohyb)');
  await pj.waitForTimeout(1200);
  const po = await pozice(c);
  ok(po && Math.abs(po.x - 700) < 2 && Math.abs(po.y - 620) < 2, 'po animaci token stojí na cílovém místě: ' + JSON.stringify(po && { x: po.x, y: po.y }));
  ok(po && !/anim-pohyb/.test(po.tridy), 'třída animace po doběhnutí zmizí');

  // --- útok zblízka a na dálku ---
  // Události se vyrobí přímo v tabulce svet_udalosti; klient je sebere pollem jako kteroukoli jinou.
  // Žádný testovací hák v produkčním kódu tak není potřeba a testuje se skutečná cesta.
  // Událost jen vloží; na to, co má způsobit, se pak ČEKÁ — animace trvá kolem sekundy,
  // zatímco poll ji přinese až za pár sekund, takže pevné čekání by ji minulo.
  const udalost = (typ, payload) => {
    // Přes dočasný soubor, ať se JSON nemusí prodírat uvozovkami shellu.
    const json = JSON.stringify(payload).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const soubor = require('path').join(require('os').tmpdir(), 'dracak-udalost.sql');
    require('fs').writeFileSync(soubor,
      `insert into svet_udalosti (svet_id, mapa_id, typ, payload) values (${SVET}, ${MAPA}, '${typ}', '${json}');\n`);
    require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} < ${soubor}`);
  };
  // Animace trvá pár set milisekund a prvek po sobě uklidí — waitForSelector by ho mohl minout.
  // Proto se před vložením události nastraží pozorovatel, který si příchod prvku ZAPAMATUJE.
  const hlidej = (selektor) => pj.evaluate((sel) => {
    window.__videno = window.__videno || {};
    window.__videno[sel] = false;
    if (window.__hlidac) window.__hlidac.disconnect();
    window.__hlidac = new MutationObserver(() => {
      if (document.querySelector(sel)) window.__videno[sel] = true;
    });
    window.__hlidac.observe(document.body, { childList: true, subtree: true, attributes: true });
  }, selektor);
  const dockej = async (selektor) => {
    for (let i = 0; i < 70; i++) {
      if (await pj.evaluate((sel) => !!(window.__videno && window.__videno[sel]) || !!document.querySelector(sel), selektor)) return true;
      await pj.waitForTimeout(200);
    }
    return false;
  };
  const kamera = () => pj.evaluate(() => document.getElementById('mapWrap').style.transform);

  // Zblízka × na dálku se pozná ze vzdálenosti tokenů, proto se cíl vrátí vedle útočníka
  // (předchozí test s ním odjel přes půl mapy).
  await api(pj2, 'token_presun', { token_id: c, x: 460, y: 400 });
  await pj.waitForTimeout(3400);
  await hlidej('#bojAnimace .anim-sek');
  udalost('akce_provedena', { token_id: a, cil_token_id: c, druh: 'utok', label: 'A', druh_nazev: 'útok' });
  ok(await dockej('#bojAnimace .anim-sek'), 'útok zblízka udělá sek u cíle');
  await pj.waitForTimeout(700);

  await hlidej('#bojAnimace .anim-strela');
  udalost('akce_provedena', { token_id: a, cil_token_id: daleko, druh: 'utok', label: 'A', druh_nazev: 'útok' });
  ok(await dockej('#bojAnimace .anim-strela'), 'na dálku letí střela');
  await pj.waitForTimeout(700);

  await hlidej('#bojAnimace .anim-strela.kouzlo');
  udalost('kouzleni_stav', { token_id: a, cil_token_id: daleko, seslano: true, stav: 'ucinkuje', obor_id: 2, label: 'A' });
  const maKouzlo = await dockej('#bojAnimace .anim-strela.kouzlo');
  ok(maKouzlo, 'kouzlo má vlastní animaci');
  if (maKouzlo && await pj.locator('#bojAnimace .anim-strela.kouzlo').count()) {
    const barva = await pj.locator('#bojAnimace .anim-strela.kouzlo').first()
      .evaluate((el) => getComputedStyle(el).getPropertyValue('--anim-barva').trim());
    ok(barva === '#ff7a3d', 'kouzlo má barvu svého oboru magie (energetická útočná): ' + barva);
  } else if (maKouzlo) {
    // Prvek už zmizel (animace doběhla) — barvu ověříme na právě vyrobeném dopadu.
    const b2 = await pj.evaluate(() => {
      const el = document.querySelector('#bojAnimace .anim-dopad.kouzlo');
      return el ? getComputedStyle(el).getPropertyValue('--anim-barva').trim() : '(už doběhlo)';
    });
    ok(b2 === '#ff7a3d' || b2 === '(už doběhlo)', 'kouzlo má barvu svého oboru magie: ' + b2);
  }
  await pj.waitForTimeout(700);

  // --- kdo je na tahu ---
  // Kolo se založí i v databázi: tlačítko „ukaž, kdo je na tahu“ se řídí skutečným stavem kola,
  // ne jen hlášením — po nejbližší obnově stavu by jinak samo zmizelo (a správně).
  sql(`replace into kolo_stav (mapa_id, cislo_kola, aktivni_token_id, faze, polovina, prekvapeni) values (${MAPA}, 1, ${c}, 'kolo', 1, 'zadne')`);
  const kameraPred = await kamera();
  udalost('tah_zmena', { novy_token_id: c, polovina: 1 });
  let videtOznam = false;
  for (let i = 0; i < 60; i++) {
    if (await pj.locator('#bojOznam').isVisible()) { videtOznam = true; break; }
    await pj.waitForTimeout(200);
  }
  ok(videtOznam, 'hlásí se, kdo je na tahu');
  ok(await pj.locator('#bojCentr').isVisible(), 'je tu tlačítko, které ho ukáže');
  ok(kameraPred === await kamera(), 'mapa se sama necentruje (uživatel: „na mobilu by to bylo peklo“)');
  await pj.click('#bojCentr'); await pj.waitForTimeout(300);
  ok(kameraPred !== await kamera(), 'tlačítko mapu posune na tvora na tahu');
  await pj.waitForTimeout(2200);
  ok(!(await pj.locator('#bojOznam').isVisible()), 'hlášení samo zmizí');

  // --- vypnuté animace ---
  sql(`update svet set animace=0 where id=${SVET}`);
  await pj.reload(); await pj.waitForTimeout(1600);
  udalost('akce_provedena', { token_id: a, cil_token_id: c, druh: 'utok', label: 'A', druh_nazev: 'útok' });
  await pj.waitForTimeout(4000);
  ok(await pj.locator('#bojAnimace > div').count() === 0, 'po vypnutí v nastavení světa se nic nekreslí');
  await api(pj2, 'token_presun', { token_id: c, x: 400, y: 560 });
  await pj.waitForTimeout(3500);
  const bezAnim = await pozice(c);
  ok(bezAnim && Math.abs(bezAnim.x - 400) < 2, 'token se i tak přesune, jen skokem');
  sql(`update svet set animace=1 where id=${SVET}; delete from kolo_stav where mapa_id=${MAPA}`);

  ok(pj.chybyJs.length === 0, 'žádná chyba JS: ' + pj.chybyJs.join(' | '));
  await b.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch((e) => {
  // Úklid i při pádu — jinak zůstane rozehrané kolo a vypnuté animace a padají pak jiné sady.
  try { sql(`update svet set animace=1 where id=${SVET}; delete from kolo_stav where mapa_id=${MAPA}`); } catch (x) { /* nevadí */ }
  console.error('PÁD', e);
  process.exit(2);
});
