// Dosah pohybu postavy mimo boj (includes/vtt_pohyblivost.php dracak_vtt_dosah_pohybu; h1634 b10867, míle = 1000 sáhů b1622/b10863): sáhy za kolo (body běhu ÷ náročnost × 10)
// a za směnu (body chůze za hodinu ÷ náročnost × 1000 ÷ 6); smí PJ a vlastník; v panelu tokenu a jako kruhy na mapě. Mapa 1. Po resetu dat: mysql dracak < ../fixture/seed.sql; node dosah_pohybu_ui.js
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
    const p = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', e => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const dosah = async (p, tok, n) => { const r = await p.request.get(BASE + '/hra/api/dosah_pohybu.php?token_id=' + tok + (n ? '&narocnost=' + n : '')); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  sql(`delete from kolo_stav; update postavy set unava=0 where svet_id=1`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 700, y: 500 })).token_id;
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 900, y: 500 })).token_id;
  const cizi = sqlVal(`select id from postavy where svet_id=1 and vlastnik_ucet_id <> (select id from ucty where email='hrac@local') limit 1`);
  const cizi_t = cizi ? (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: parseInt(cizi, 10), x: 1100, y: 500 })).token_id : null;

  // --- čísla podle pravidel ---
  let d = await dosah(pj, h);
  ok(d.s === 200 && d.ok === true && d.narocnost === 10, 'PJ dostane dosah pohybu postavy hráče (náročnost 10): ' + JSON.stringify(d.kolo));
  const bodyBeh = JSON.parse('[' + sqlVal(`select concat(poklus, ',', beh, ',', sprint) from rychlost_behu where bonus_pohyblivosti=${d.bonus}`) + ']');
  const bodyChuze = JSON.parse('[' + sqlVal(`select concat(prochazka, ',', chuze, ',', spech) from rychlost_chuze where bonus_pohyblivosti=${d.bonus}`) + ']');
  ok(d.kolo.poklus === bodyBeh[0] && d.kolo.beh === bodyBeh[1] && d.kolo.sprint === bodyBeh[2], 'při náročnosti 10 je dosah za kolo roven bodům běhu v tabulce (body ÷ 10 × 10 sáhů): ' + bodyBeh.join('/'));
  ok(d.smena.chuze === Math.round(bodyChuze[1] / 10 * 1000 / 6) && d.smena.spech === Math.round(bodyChuze[2] / 10 * 1000 / 6), 'dosah za směnu = body chůze za hodinu ÷ 10 × 1000 sáhů ÷ 6: chůze ' + d.smena.chuze + ' sáhů');
  d = await dosah(pj, h, 20);
  ok(Math.abs(d.kolo.beh - bodyBeh[1] / 2) < 0.06 && Math.abs(d.smena.chuze - bodyChuze[1] * 1000 / 20 / 6) <= 1, 'dvojnásobná náročnost (hustý les) dosah půlí');
  d = await dosah(pj, h, '0');
  ok(d.s === 422, 'náročnost 0 se odmítne (422)');

  // --- oprávnění ---
  d = await dosah(hrac, h);
  ok(d.s === 200 && d.ok, 'hráč vidí dosah své postavy');
  if (cizi_t) { d = await dosah(hrac, cizi_t); ok(d.s === 403, 'cizí postavu hráč zjišťovat nesmí (403): ' + d.s); }
  d = await dosah(pj, gob);
  ok(d.s === 404, 'u nestvůry dosah nejde (404): ' + d.s);

  // --- únava zpomaluje ---
  const bezUnavy = (await dosah(pj, h)).kolo.beh;
  sql(`update postavy set unava=odolnost+1 where id=2`);   // první stupeň únavy (nad 1× vytrvalosti)
  const sUnavou = (await dosah(pj, h)).kolo.beh;
  ok(sUnavou < bezUnavy, 'únava dosah zmenší (' + bezUnavy + ' → ' + sUnavou + ')');
  sql(`update postavy set unava=0 where id=2`);

  // --- prohlížeč ---
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(1500);
  await pj.locator('.vtt-token[data-id="' + h + '"] .puck').click();
  await pj.waitForTimeout(600);
  ok(await pj.locator('#dosahBlok').isVisible(), 'v detailu postavy je blok Dosah pohybu');
  await pj.click('#dosahBtn');
  await pj.waitForTimeout(800);
  ok(/sáhů/.test(await pj.textContent('#dosahVysl')) && await pj.locator('#dosahPovrch option').count() > 5, 'blok ukáže čísla a nabídku povrchů: ' + (await pj.textContent('#dosahVysl')).slice(0, 80));
  await pj.selectOption('#dosahPovrch', { label: /hustý les/ }).catch(async () => { await pj.selectOption('#dosahPovrch', '20'); });
  await pj.waitForTimeout(600);
  ok(/náročnost 20/.test(await pj.textContent('#dosahVysl')), 'změna povrchu přepočítá dosah');
  await pj.click('#dosahUkazat');
  await pj.waitForTimeout(300);
  ok(await pj.locator('#rulerSvg circle').count() === 3, 'na mapě se ukážou tři kruhy (poklus, běh, sprint)');
  // Nestvůra má od 2026-11-10 plovoucí okno tvora; dosah pohybu je věc postavy, takže ani v něm,
  // ani v postranním panelu (kam vede tlačítko z patičky) být nesmí.
  await pj.locator('.vtt-token[data-id="' + gob + '"] .puck').click();
  await pj.waitForTimeout(700);
  ok(await pj.locator('.tvor-okno').count() === 1, 'klik na nestvůru otevře okno tvora');
  ok(!/Dosah pohybu/.test(await pj.locator('.tvor-okno').textContent()), 'okno tvora dosah pohybu nenabízí');
  await pj.locator('.tvor-paticka .btn').click();
  await pj.waitForTimeout(600);
  ok(await pj.locator('#dosahBlok').isHidden(), 'ani v postranním panelu se u nestvůry blok Dosah pohybu nenabízí');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
