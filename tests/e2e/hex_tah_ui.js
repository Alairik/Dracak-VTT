const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const SHOTS = (process.env.OUT || '/tmp/dracak-tests') + '/shots';
const errs = [];
const ok = (c, m) => console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m);
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql ${DB} -N -e "${q}"`).toString().trim();
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await (await browser.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
  pj.on('pageerror', e => errs.push('PAGEERROR ' + e.message));
  pj.on('dialog', d => d.accept());
  await pj.goto(BASE + '/index.php'); await pj.fill('#email', 'test@local'); await pj.fill('#password', 'test1234');
  await Promise.all([pj.waitForNavigation(), pj.click('button[type=submit]')]);
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(900);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  async function priprava(sinCol, sinRow, sinSmer, akceExtra, gSmer = 0, gCol = 10) {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql('update postavy set unava=0 where id=2');
    const add = async (typ, c, r) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
    const G = await add('nestvura', gCol, 10);
    await api(pj, 'token_uprava', { token_id: G, zmeny: { smer: gSmer } });
    const S = await add('postava', sinCol, sinRow);
    await api(pj, 'token_uprava', { token_id: S, zmeny: { smer: sinSmer } });
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [G, S].map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [G], hod: 6 });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [S], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
    for (let i = 0; i < akceExtra; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: G, op: 'delta', delta: 9 });
    await pj.reload(); await pj.waitForTimeout(1400);
    await pj.click('#zoom-fit'); await pj.waitForTimeout(300);
    const q = await (await pj.$('.vtt-token[data-id="' + G + '"] .puck')).boundingBox();
    await pj.mouse.move(q.x + q.width / 2 + 120, q.y + q.height / 2); await pj.mouse.wheel(0, -600); await pj.waitForTimeout(500);
    const rect = async (id) => pj.$eval('.vtt-token[data-id="' + id + '"] .puck', e => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
    const a = await rect(G), b = await rect(S);
    const A = stred(gCol, 10), B = stred(sinCol, sinRow);
    const sc = (b.x - a.x) / (B.x - A.x);
    const sx = (c, r) => { const m = stred(c, r); return { x: a.x + (m.x - A.x) * sc, y: a.y + (m.y - A.y) * sc }; };
    return { G, S, sx };
  }
  const plan = async () => (await pj.$$eval('.tah-plan .ini-napoveda', e => e.map(x => x.innerText))).join(' / ');
  const hlavicka = async () => pj.$eval('.tah-plan', x => x.querySelector('b').innerText + (x.querySelector('.tah-cena') ? x.querySelector('.tah-cena').innerText : '')).catch(() => '');
  const logTail = async () => (await pj.$eval('#logList', x => x.innerText)).split('\n').slice(-2).join(' | ');
  const posun = async (id) => (await stav(pj)).tokeny.find(t => t.id === id);

  // ===== 1) nepřítel 4 hexy daleko: Sprint a útok (2 akce) =====
  let k = await priprava(14, 10, 3, 3);
  let akceDo = +sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${k.G}`);
  ok(await pj.$$('.tah-plan').then(x => x.length) === 1, 'plán začal sám, jakmile je tvor na tahu');
  ok(await pj.$$('.akce-lista .akce-btn:has-text("Sprint")').then(x => x.length) === 0, 'lišta akcí s Krok / Běh / Sprint zmizela');
  ok(await pj.$$('details.dalsi-akce').then(x => x.length) === 1, 'zbylo menu „Další akce…“');
  ok(await pj.$$('#soubojSvg .sb-nepritel').then(x => x.length) === 1, 'nepřítel je na mapě označený kroužkem');
  let p = k.sx(14, 10);
  await pj.mouse.click(p.x, p.y); await pj.waitForTimeout(500);
  const h1 = await hlavicka();
  console.log('   plán:', h1, '|', await plan());
  ok(/Sprint a útok/.test(h1) && /1 akce/.test(h1), 'klik na nepřítele 4 hexy daleko: Sprint a útok za 1 akci (' + h1.replace(/\n/g, ' ') + ')');
  ok(await pj.$$('#soubojSvg .sb-bod').then(x => x.length) === 3, 'cesta A, B, C k nepříteli (3 hexy)');
  ok(/Útok na: /.test(await plan()), 'v plánu je cíl útoku');
  ok(await pj.$$('#soubojSvg .sb-bublina').then(x => x.length) === 1, 'u konce cesty je plovoucí potvrzení');
  await pj.screenshot({ path: SHOTS + '/90_sprint_utok.png' });
  await pj.click('.tah-plan button:has-text("Potvrdit")'); await pj.waitForTimeout(1800);
  let tG = await posun(k.G);
  ok(Math.abs(tG.x - stred(13, 10).x) <= 1 && Math.abs(tG.y - stred(13, 10).y) <= 1, 'G stojí na (13,10) vedle nepřítele: ' + tG.x + ',' + tG.y);
  console.log('   log:', await logTail());
  ok(/sprint a útok/.test(await logTail()) || /sprint a útok/.test(await pj.$eval('#logList', x => x.innerText)), 'log: sprint a útok');
  ok(+sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${k.G}`) === akceDo - 1, 'stálo to jen 1 akci');

  // ===== 2) nepřítel 2 hexy daleko: Krok a útok (1 akce) =====
  k = await priprava(12, 10, 3, 3);
  akceDo = +sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${k.G}`);
  p = k.sx(12, 10);
  await pj.mouse.click(p.x, p.y); await pj.waitForTimeout(500);
  const h2 = await hlavicka();
  ok(/Krok a útok/.test(h2) && /1 akce/.test(h2), 'nepřítel 2 hexy daleko: Krok a útok za 1 akci (' + h2.replace(/\n/g, ' ') + ')');
  // bubliny potvrzení: klik na plovoucí ✓
  const bubl = await pj.$eval('#soubojSvg .sb-bublina', e => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
  await pj.mouse.click(bubl.x, bubl.y); await pj.waitForTimeout(1800);
  tG = await posun(k.G);
  ok(Math.abs(tG.x - stred(11, 10).x) <= 1, 'klik na ✓ provedl krok a útok: G na (11,10)');
  ok(+sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${k.G}`) === akceDo - 1, 'stálo to 1 akci');

  // ===== 3) nepřítel hned vedle za zády: Otočka a útok (1 akce) =====
  k = await priprava(12, 10, 0, 3, 0, 13);   // G (13,10) hledí na východ, Sindor stojí na západě za zády
  p = k.sx(12, 10);
  await pj.mouse.click(p.x, p.y); await pj.waitForTimeout(500);
  const h3 = await hlavicka();
  ok(/Otočka a útok/.test(h3) && /1 akce/.test(h3), 'nepřítel za zády: Otočka a útok (' + h3.replace(/\n/g, ' ') + ')');
  await pj.mouse.dblclick(p.x, p.y); await pj.waitForTimeout(1800);
  tG = await posun(k.G);
  ok(tG.smer === 120, 'dvojklik na cíli útok potvrdil; G se otočil jen tolik, aby měl nepřítele mezi 3 hexy před sebou (čelem na jihozápad, 120°): ' + tG.smer);

  // ===== 4) nepřítel před tebou: samotný Útok =====
  k = await priprava(11, 10, 3, 3, 0);
  p = k.sx(11, 10);
  await pj.mouse.click(p.x, p.y); await pj.waitForTimeout(500);
  const h4 = await hlavicka();
  ok(/^Útok/.test(h4) && /1 akce/.test(h4), 'nepřítel před tebou: samotný Útok (' + h4.replace(/\n/g, ' ') + ')');
  ok(await pj.$$('#soubojSvg .sb-bod').then(x => x.length) === 0, 'bez pohybu žádné body cesty');

  // ===== 5) kvóta jedna akce: sprint a útok je pořád jedna akce, takže projde =====
  k = await priprava(14, 10, 3, 0);   // 2 akce celkem, v 1. půlce jen 1
  p = k.sx(14, 10);
  await pj.mouse.click(p.x, p.y); await pj.waitForTimeout(500);
  ok(/Sprint a útok/.test(await hlavicka()) && !(await pj.$eval('.tah-plan button:has-text("Potvrdit")', x => x.disabled)), 'při kvótě jedna akce jde sprint a útok potvrdit (stojí 1 akci)');
  await pj.click('.tah-plan button:has-text("Bez útoku")'); await pj.waitForTimeout(300);
  ok(/Sprint/.test(await hlavicka()) && !/útok/.test(await hlavicka()), 'po „Bez útoku“ zbyde jen Sprint');

  // ===== 6) Esc vypne plánování, „Plánovat tah“ ho zapne =====
  await pj.keyboard.press('Escape'); await pj.waitForTimeout(400);
  ok(await pj.$$('.tah-plan').then(x => x.length) === 0 && await pj.$$('button:has-text("Plánovat tah")').then(x => x.length) === 1, 'Esc vypne plán, nabídne „Plánovat tah“');
  await pj.click('button:has-text("Plánovat tah")'); await pj.waitForTimeout(400);
  ok(await pj.$$('.tah-plan').then(x => x.length) === 1, '„Plánovat tah“ plán zase zapne');

  // ===== 7) Další akce… → Čekání =====
  await pj.click('details.dalsi-akce summary'); await pj.waitForTimeout(200);
  await pj.click('details.dalsi-akce button:has-text("Čekání")'); await pj.waitForTimeout(1500);
  ok(/čekání/.test(await pj.$eval('#logList', x => x.innerText)), 'Další akce → Čekání se odečte a zapíše do logu');

  console.log(errs.length ? 'JS CHYBY:\n' + errs.join('\n') : 'bez JS chyb');
  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  await browser.close();
})();
