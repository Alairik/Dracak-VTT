// „Hoď si“ — PJ vyzve hráče k hodu a kostku vidí celý stůl (zadání uživatele 2026-11-11; návrh a jeho
// připomínky na plátně https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz).
//
// Co se ověřuje:
//   * blok „Hoď si“ je v panelu jen u postavy hráče, u nestvůry ne (té hází PJ sám),
//   * výzva na konkrétní kostku: hráč nemá co vybírat, jen tlačítko Hodit,
//   * výzva bez kostky: hráč si vybere z k6 / k10 / k100,
//   * okno dostane jen ten, kdo postavu hraje — PJ si vlastní výzvu neotevře,
//   * kdo hází, hodí si 3D kostkou; ostatní u stolu uvidí kartu se stejným číslem,
//   * práva: hráč výzvu poslat nemůže, nestvůru ani neznámou kostku server odmítne.
//
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node hod_vyzva_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
async function login(b, email, heslo) {
  const p = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
  await p.goto(BASE + '/index.php');
  await p.fill('#email', email); await p.fill('#password', heslo);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(BASE + '/hra/mapa.php?id=' + MAPA); await p.waitForTimeout(1500);
  return p;
}
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const pj = await login(b, 'test@local', 'test1234');
  const hr = await login(b, 'hrac@local', 'hrac1234');
  const api = (page, n, body) => page.evaluate(async ([nn, bb]) => {
    const r = await fetch('api/' + nn + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(bb) });
    const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
  }, [n, body]);
  const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m)).json(), MAPA);

  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  const g = await pj.evaluate(() => window.MAPA.grid);
  const col = Math.sqrt(3) * g.px, row = 1.5 * g.px;
  const at = (c, r) => ({ x: Math.round(c * col + ((r & 1) ? col / 2 : 0)), y: Math.round(r * row) });
  const tok = (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: 'postava', postava_id: 2 }, at(12, 10)))).token_id;
  const gob = (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41 }, at(14, 10)))).token_id;
  await pj.reload(); await pj.waitForTimeout(1500);
  await hr.reload(); await hr.waitForTimeout(1500);

  // --- PJ: blok se ukáže jen u postavy ---
  const otevriPanel = async (page, id) => {
    if (await page.locator('#denikDock').isVisible()) { await page.click('#denikZavrit'); await page.waitForTimeout(300); }
    const p0 = await page.$eval('.vtt-token[data-id="' + id + '"] .puck', (e) => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
    await page.mouse.click(p0.x, p0.y, { button: 'right' }); await page.waitForTimeout(400);
    await page.locator('#kontextMenu button', { hasText: 'Nastavení tokenu' }).click(); await page.waitForTimeout(600);
  };
  await otevriPanel(pj, tok);
  ok(await pj.locator('#hodVyzvaBlok').isVisible(), 'u postavy je v panelu blok Hoď si');
  await otevriPanel(pj, gob);
  ok(!(await pj.locator('#hodVyzvaBlok').isVisible()), 'u nestvůry blok Hoď si není');

  // --- PJ pošle výzvu na konkrétní kostku ---
  await otevriPanel(pj, tok);
  await pj.locator('#hodVyzvaBlok [data-hod-vyzva="10"]').click();
  await hr.waitForTimeout(3500);
  ok(await hr.locator('#hodVyzvaDialog').isVisible(), 'hráči vyskočilo okno s výzvou');
  ok((await hr.locator('[data-vyzva-nadpis]').textContent()).trim() === 'Hoď si k10', 'nadpis říká, jakou kostkou: ' + (await hr.locator('[data-vyzva-nadpis]').textContent()).trim());
  ok(!(await hr.locator('[data-vyzva-volba]').isVisible()), 'při určené kostce se hráč nerozhoduje');
  ok(await hr.locator('[data-vyzva-hodit]').isVisible(), 'je tam jen tlačítko Hodit');
  ok(!(await pj.locator('#hodVyzvaDialog').isVisible()), 'PJ svoji vlastní výzvu jako okno nedostane');

  // --- hráč hodí, hod vidí celý stůl ---
  // Kdo hází, hodí si 3D kostkou (assets/js/kostky3d.js) a kartu s číslem už nedostane —
  // ostatní u stolu ji dostanou, protože dice-box neumí přehrát cizí hod na dané číslo.
  await hr.locator('[data-vyzva-hodit]').click();
  await hr.waitForTimeout(1200);
  ok(!(await hr.locator('#hodVyzvaDialog').isVisible()), 'po hodu okno výzvy zmizelo');
  const kostek3D = await hr.evaluate(() => document.querySelectorAll('#diceBoxContainer canvas').length);
  ok(kostek3D > 0 || (await hr.locator('.kostka-hod').count()) >= 1, 'hazeč vidí hod (3D kostka nebo karta)');
  // Log a karta u ostatních přijdou pollem — čeká se na ně, ne na pevný čas.
  const vLogu = async (page) => {
    try {
      await page.waitForFunction(() => /hodil k10: \[\d+\]/.test(document.getElementById('panel-log').textContent), null, { timeout: 15000 });
    } catch (e) { return null; }
    return (await page.locator('#panel-log').textContent()).match(/hodil k10: \[(\d+)\]/)[1];
  };
  const cislo = await vLogu(hr);
  ok(cislo !== null, 'hráč má svůj hod v logu: ' + cislo);
  const cisloPj = await vLogu(pj);
  ok(cisloPj === cislo, 'oba mají v logu stejné číslo: hráč ' + cislo + ' / PJ ' + cisloPj);
  let naKarte = null;
  try {
    await pj.waitForFunction(() => {
      const el = document.querySelector('.kostka-hod.dopadlo .kostka-cislo');
      return el && /^\d+$/.test(el.textContent.trim());
    }, null, { timeout: 15000 });
    naKarte = (await pj.locator('.kostka-hod.dopadlo .kostka-cislo').first().textContent()).trim();
  } catch (e) { /* zůstane null */ }
  ok(naKarte === cislo, 'ostatní u stolu vidí kartu s týmž číslem: ' + naKarte);

  // --- výzva bez určené kostky ---
  await pj.locator('#hodVyzvaBlok [data-hod-vyzva=""]').click();
  await hr.waitForTimeout(3500);
  ok((await hr.locator('[data-vyzva-nadpis]').textContent()).trim() === 'Hoď si', 'bez určené kostky je nadpis jen „Hoď si“');
  ok(await hr.locator('[data-vyzva-volba]').isVisible(), 'hráč si kostku vybere sám');
  ok(await hr.locator('[data-vyzva-volba] [data-vyzva-kostka]').count() === 3, 'na výběr jsou tři kostky');

  // --- práva: hráč nesmí vyzývat ---
  let d = await api(hr, 'hod_vyzva', { mapa_id: MAPA, token_id: tok, kostka: 10 });
  ok(d.s === 403, 'hráč výzvu poslat nemůže (403): ' + d.s);
  d = await api(pj, 'hod_vyzva', { mapa_id: MAPA, token_id: gob, kostka: 10 });
  ok(d.s === 422, 'nestvůru vyzvat nejde (422): ' + d.s);
  d = await api(pj, 'hod_vyzva', { mapa_id: MAPA, token_id: tok, kostka: 7 });
  ok(d.s === 422, 'neznámá kostka se odmítne (422): ' + d.s);

  ok(pj.chybyJs.length === 0, 'PJ bez chyby JS: ' + pj.chybyJs.join(' | '));
  ok(hr.chybyJs.length === 0, 'hráč bez chyby JS: ' + hr.chybyJs.join(' | '));
  await hr.screenshot({ path: (process.env.OUT || '/tmp/dracak-tests') + '/hod-vyzva-hrac.png' });
  await b.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
