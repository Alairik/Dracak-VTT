const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAPA = 2;
const ok = (c, m) => console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m);
async function login(browser, email, heslo) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const page = await ctx.newPage();
  page.on('dialog', d => d.accept());
  await page.goto(BASE + '/index.php');
  await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await page.waitForTimeout(700);
  return page;
}
const api0 = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
}, [name, body]);
// Útok od etapy 2 založí výzvu k hodu a blokuje další akce — tyhle testy zkoušejí pohyb, ne hody, takže ji PJ hned zruší.
const api = async (page, name, body) => {
  const r = await api0(page, name, body);
  if (name === 'tah_akce' && r && r.vyzva_id && !global.ZACHOVAT_VYZVU) await api0(global.PJPAGE || page, 'vyzva_zrus', { mapa_id: body.mapa_id });
  return r;
};
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  global.PJPAGE = pj;
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const offX = ((g.offX % colStep) + colStep) % colStep, offY = ((g.offY % rowStep) + rowStep) % rowStep;
  const stred = (col, row) => ({ x: Math.round(offX + col * colStep + ((row & 1) ? colStep / 2 : 0)), y: Math.round(offY + row * rowStep) });
  console.log('grid', JSON.stringify(g));
  require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${process.env.DB || 'dracak'} -e "delete ae from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev like 'Běh na krátké%'"`);
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  // úklid tokenů testu
  const s0 = await stav(pj);
  for (const t of s0.tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  const sindorC = { col: 10, row: 10 };
  const pos = stred(sindorC.col, sindorC.row);
  const sindor = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: pos.x, y: pos.y })).token_id;
  const gobPos = stred(12, 10);   // vzdálenost 2 doprava
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: gobPos.x, y: gobPos.y })).token_id;
  const gob2Pos = stred(11, 10);  // hned vedle Sindora vpravo
  const gob2 = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: gob2Pos.x, y: gob2Pos.y })).token_id;
  await api(pj, 'token_uprava', { token_id: sindor, zmeny: { smer: 0 } });
  console.log('tokeny', sindor, gob, gob2);
  let d = await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [{ token_id: sindor }, { token_id: gob }, { token_id: gob2 }] });
  ok(d.ok, 'start souboje na hexové mapě');
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(hr, 'souboj_hod', { mapa_id: MAPA, token_id: sindor, hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob, gob2], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  d = await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 }); d = await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });
  let sp = await stav(pj);
  console.log('Sindor', JSON.stringify(sp.iniciativa.find(r => r.token_id === sindor)));
  const poz = async (id) => { const t = (await stav(pj)).tokeny.find(x => x.id === id); return t; };
  const akce = (body, who) => api(who || hr, 'tah_akce', Object.assign({ mapa_id: MAPA, token_id: sindor }, body));

  // --- hráč nesmí táhnout ani otáčet volně
  d = await api(hr, 'token_presun', { token_id: sindor, x: pos.x + 5, y: pos.y });
  ok(d.s === 403, 'hráč nesmí táhnout tokenem v souboji: ' + d.error);
  d = await api(hr, 'token_uprava', { token_id: sindor, zmeny: { smer: 60 } });
  ok(d.s === 403, 'hráč nesmí volně otáčet v souboji: ' + d.error);
  d = await api(pj, 'token_uprava', { token_id: sindor, zmeny: { smer: 0 } });
  ok(d.ok, 'PJ smí otáčet volně');

  // --- krok: chybové případy
  d = await akce({ druh: 'krok' }); ok(d.s === 422, 'krok bez cíle: ' + d.error);
  d = await akce({ druh: 'krok', cil: { col: 12, row: 10 } }); ok(d.s === 422, 'krok o 2 hexy bez úmyslu Pohyb: ' + d.error);
  d = await akce({ druh: 'krok', cil: { col: 11, row: 10 } }); ok(d.s === 422 && /někdo/.test(d.error), 'krok na obsazený hex: ' + d.error);
  d = await akce({ druh: 'krok', cil: { col: 9, row: 10 }, smer: 180 });
  ok(d.ok, 'krok doleva s otočením o 180° (HB: až 270°): ' + (d.error || 'ok'));
  let t = await poz(sindor);
  ok(Math.abs(t.x - stred(9, 10).x) <= 1 && t.smer === 180, 'token je na hexu (9,10) čelem doleva: ' + t.x + ',' + t.y + ' smer ' + t.smer);
  d = await akce({ druh: 'krok', cil: { col: 10, row: 10 }, smer: 120 });
  ok(d.ok, 'krok zpět doprava, otočení o 60° projde');
  t = await poz(sindor);
  ok(t.smer === 120, 'směr 120: ' + t.smer);
  // --- otočka
  d = await akce({ druh: 'otocka', smer: 120 }); ok(d.s === 422, 'otočka na stejný směr: ' + d.error);
  d = await akce({ druh: 'otocka' }); ok(d.s === 422, 'otočka bez směru: ' + d.error);
  d = await akce({ druh: 'otocka', smer: 0 }); ok(d.ok, 'otočka o 120° zpět na východ');
  t = await poz(sindor);
  ok(t.smer === 0, 'čelem na východ');
  // --- útok: goblin2 stojí na (11,10) hned před Sindorem (10,10) čelem na východ
  d = await akce({ druh: 'utok', cil_token_id: gob });
  ok(d.s === 422 && /3 hexy/.test(d.error), 'útok na tvora o dva hexy dál: ' + d.error);
  d = await akce({ druh: 'utok', cil_token_id: gob2 });
  ok(d.ok, 'útok na tvora přímo před sebou');
  // krok a útok: z (10,10) na severovýchod (10,9), čelem na východ, vpředu SE hex (11,10) = goblin2
  d = await akce({ druh: 'krok_utok', cil: { col: 10, row: 9 }, smer: 0, cil_token_id: gob2 });
  ok(d.ok, 'krok na severovýchod a útok na goblina, který je teď vpravo dole: ' + (d.error || 'ok'));
  t = await poz(sindor);
  ok(Math.abs(t.x - stred(10, 9).x) <= 1 && Math.abs(t.y - stred(10, 9).y) <= 1, 'token stojí na (10,9)');
  // otočka a útok: čelem na jih-východ (60°) z (10,9) → vpředu E (11,9), SE (11,10) ✓ goblin2, ESE... 
  d = await akce({ druh: 'otocka_utok', smer: 60, cil_token_id: gob2 });
  ok(d.ok, 'otočka na jihovýchod a útok na goblina: ' + (d.error || 'ok'));
  d = await akce({ druh: 'otocka_utok', smer: 180, cil_token_id: gob2 });
  ok(d.s === 422 && /3 hexy/.test(d.error), 'otočka na západ a útok na goblina za zády: ' + d.error);
  // --- Pohyb o dva hexy jen při úmyslu "pohyb"
  d = await akce({ druh: 'krok', cil: { col: 10, row: 7 } }); ok(d.s === 422 && /sousední/.test(d.error), 'krok o 2 hexy bez úmyslu pohyb: ' + d.error);
  require('child_process').execSync(`mysql ${process.env.DB || 'dracak'} -e "update kolo_ucastnik set umysl='pohyb' where token_id=${sindor}"`);
  // z (10,9): dva hexy na sever → (10,7)? řádek 7 je lichý; vzdálenost z (10,9) do (10,7) = 2
  d = await akce({ druh: 'krok', cil: { col: 10, row: 7 } }); ok(d.s === 422 && /Běhu/.test(d.error), 'ani při pohybu není krok o dva hexy (to je Běh): ' + d.error);
  d = await akce({ druh: 'beh', cil: { col: 10, row: 7 } });
  ok(d.ok, 'při úmyslu Pohyb běh o dva hexy: ' + (d.error || 'ok'));
  d = await akce({ druh: 'beh', cil: { col: 10, row: 4 } }); ok(d.s === 422 && /nejvýš o 2/.test(d.error), 'běh ne o tři hexy (to je Sprint): ' + d.error);
  // --- zeď: hráči nelze přes zeď, PJ ano
  t = await poz(sindor);
  const a0 = stred(10, 7), a1 = stred(10, 6);
  const zed = await api(pj, 'zed_pridat', { mapa_id: MAPA, x1: Math.min(a0.x, a1.x) - 60, y1: (a0.y + a1.y) / 2, x2: Math.max(a0.x, a1.x) + 60, y2: (a0.y + a1.y) / 2, sirka_px: 6, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true });
  ok(zed.id, 'zeď přidána');
  d = await akce({ druh: 'krok', cil: { col: 10, row: 6 } });
  ok(d.s === 422 && /zeď|zablok/.test(d.error), 'hráč přes zeď: ' + d.error);
  d = await akce({ druh: 'krok', cil: { col: 10, row: 6 } }, pj);
  ok(d.ok, 'PJ smí krokem přes zeď (jako při tažení)');
  await api(pj, 'zed_smazat', { id: zed.id, mapa_id: MAPA });
  // A0 / značky: obecný token nepřekáží
  const znacka = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', nazev: 'Sud', x: stred(9, 6).x, y: stred(9, 6).y })).token_id;
  d = await akce({ druh: 'krok', cil: { col: 9, row: 6 } });
  ok(d.ok, 'krok na hex se značkou: ' + (d.error || 'projde (značka nepřekáží)'));
  await api(pj, 'token_smazat', { token_id: znacka, mapa_id: MAPA });
  // události v logu: klientům jdou token_presun, token_zmena a akce_provedena
  const typy = await pj.evaluate(async (od) => (await (await fetch('api/udalosti.php?svet_id=1&od=' + od)).json()).udalosti.map(u => u.typ), sp.posledni_udalost_id);
  ok(['token_presun', 'token_zmena', 'akce_provedena'].every(x => typy.includes(x)), 'v logu jsou události pohybu: ' + [...new Set(typy)].join(', '));
  console.log('konec');
  // úklid
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  await browser.close();
})();
