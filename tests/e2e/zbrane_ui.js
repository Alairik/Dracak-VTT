const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = 'dracak', MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo, url) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  page.dialogy = [];
  page.on('dialog', d => { page.dialogy.push(d.message()); d.accept(); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + url); await page.waitForTimeout(700);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('/hra/api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('/hra/api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234', '/hra/mapa.php?id=' + MAPA);
  const hr = await login(browser, 'hrac@local', 'hrac1234', '/hra/denik.php?id=2&embed=1');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const drzena = () => sqlVal('select ifnull(drzena_zbran_id,0) from postavy where id=2');
  const zbyva = (tok) => parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${tok}`), 10);
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2; update postava_predmety set u_sebe=1 where postava_id=2");
  }
  async function pridej(typ, col, row) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  const DYKA = 2, CEP = 39;
  await uklid();

  // ---- mimo boj ----
  await hr.reload(); await hr.waitForTimeout(500);
  ok(await hr.locator('#denik[data-boj="0"]').count() === 1, 'mimo boj: data-boj=0');
  ok(await hr.locator('.dn-boj-stav').count() === 0, 'mimo boj: žádný pruh „Jsi v boji“');
  ok(await hr.locator('th', { hasText: 'U sebe' }).count() === 1, 'sloupec „U sebe“ je vidět');
  ok(await hr.locator('button[data-vytasit]').count() === 2 && await hr.locator('button[data-vytasit]:disabled').count() === 0, 'obě zbraně lze vytasit (2 tlačítka, žádné zakázané)');
  await hr.locator('button[data-vytasit="' + DYKA + '"]').click(); await hr.waitForTimeout(700);
  ok(drzena() === String(DYKA) && hr.dialogy.length === 0, 'mimo boj: vytasení bez potvrzení, zdarma');
  ok(await hr.locator('.dn-vruce').count() === 1 && await hr.locator('button[data-vytasit="' + DYKA + '"]').count() === 0, 'Dýka má štítek „v ruce“ a už nemá tlačítko');
  // pill ÚČ přepne na další
  ok(await hr.locator('[data-dalsi-zbran="' + CEP + '"]').count() === 1, 'klepací ÚČ míří na Cep');
  await hr.locator('[data-dalsi-zbran]').click(); await hr.waitForTimeout(700);
  ok(drzena() === String(CEP), 'klepnutí na ÚČ: Cep v ruce');
  // „U sebe“ přepínač
  const cb = hr.locator('input[data-zmena="zbran_u_sebe"][data-id="' + DYKA + '"]');
  ok(await cb.isChecked(), 'Dýka je ve výchozím stavu u sebe');
  await cb.uncheck(); await hr.waitForTimeout(700);
  ok(sqlVal(`select u_sebe from postava_predmety where postava_id=2 and predmet_id=${DYKA}`) === '0', 'odškrtnutí: Dýka do batohu (DB)');
  ok(await hr.locator('input[data-zmena="zbran_u_sebe"][data-id="' + CEP + '"]').isDisabled(), 'zbraň v ruce nejde dát do batohu (zamčená)');

  // ---- v boji ----
  await uklid();
  sql(`update postava_predmety set u_sebe=0 where postava_id=2 and predmet_id=${CEP}`);
  await api(hr, 'zbran_vytasit', { postava_id: 2, zbran_id: DYKA });
  const sindor = await pridej('postava', 13, 10), gob = await pridej('nestvura', 14, 10);
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(hr, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });   // 3 akce
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  await hr.reload(); await hr.waitForTimeout(500);
  const k = (await stav(pj)).kolo;
  ok(k.aktivni_token_id === sindor, 'Sindor na tahu');
  ok(await hr.locator('#denik[data-boj="1"]').count() === 1 && await hr.locator('.dn-boj-stav').count() === 1, 'v boji: pruh „Jsi v boji“');
  const txt = await hr.locator('.dn-boj-stav').innerText();
  ok(/Jsi na tahu/.test(txt) && /stojí jednu/.test(txt), 'pruh říká, že je na tahu a že vytasení stojí akci: ' + txt.replace(/\s+/g, ' '));
  const btnCep = hr.locator('button[data-vytasit="' + CEP + '"]');
  ok(await btnCep.isDisabled() && /není u sebe/.test(await btnCep.getAttribute('title')), 'Cep v batohu: tlačítko zakázané s důvodem');
  ok(await hr.locator('[data-dalsi-zbran]').count() === 0, 'ÚČ nemá co vytasit (Cep je v batohu)');
  ok(await hr.locator('input[data-zmena="zbran_u_sebe"]').first().isDisabled(), 'v boji je přepínač „U sebe“ zamčený');
  sql(`update postava_predmety set u_sebe=1 where postava_id=2 and predmet_id=${CEP}`);
  await hr.reload(); await hr.waitForTimeout(500);
  const pred = zbyva(sindor);
  hr.dialogy.length = 0;
  await hr.locator('[data-dalsi-zbran]').click(); await hr.waitForTimeout(900);
  ok(hr.dialogy.length === 1 && /stojí jednu akci/.test(hr.dialogy[0]), 'v boji se před vytasením ptá: ' + hr.dialogy[0]);
  ok(drzena() === String(CEP) && zbyva(sindor) === pred - 1, 'Cep vytasen a ubyla jedna akce: ' + pred + ' → ' + zbyva(sindor));
  ok((await hr.locator('#dn-hlaska').innerText()).includes('vytasena'), 'hláška o vytasení');
  // po vyčerpání akcí 1. půlky: tlačítka zakázaná (už není na tahu)
  const k2 = (await stav(pj)).kolo;
  ok(k2.aktivni_token_id === gob, 'tah přešel na goblina (kvóta půlky)');
  await hr.reload(); await hr.waitForTimeout(500);
  ok(await hr.locator('button[data-vytasit]:disabled').count() === await hr.locator('button[data-vytasit]').count(), 'mimo svůj tah jsou všechna „Vytasit“ zakázaná');
  ok(/na svém tahu/.test(await hr.locator('.dn-boj-stav').innerText()), 'pruh: zbraň vytasíš na svém tahu');

  // ---- PJ: checkbox „bez akce“ ----
  const pjD = await login(browser, 'test@local', 'test1234', '/hra/denik.php?id=2&embed=1');
  ok(await pjD.locator('#dn-bez-akce').count() === 1, 'PJ vidí „bez akce (PJ)“');
  ok(await hr.locator('#dn-bez-akce').count() === 0, 'hráč „bez akce“ nevidí');
  const kiPred = zbyva(sindor);
  await pjD.locator('#dn-bez-akce').check();
  pjD.dialogy.length = 0;
  await pjD.locator('button[data-vytasit="' + DYKA + '"]:not([disabled])').click(); await pjD.waitForTimeout(900);
  ok(pjD.dialogy.length === 0 && drzena() === String(DYKA) && zbyva(sindor) === kiPred, 'PJ s „bez akce“: bez potvrzení, zbraň se změnila, akce nezměněny');

  // ---- panel Souboj na mapě: menu „Vytasit zbraň…“ ----
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  await uklid();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})();
