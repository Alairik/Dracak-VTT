// Malá karta postavy (rozhodnutí uživatele 2026-11-12): JEDEN klik na token postavy otevře jen malou kartu
// (životy, magenergie, ÚČ, OČ), plný deník až DVOJKLIK nebo tlačítko „Deník“. Karta se zavře křížkem, klikem
// mimo a Esc. Čísla musí sedět s databází; cizí postavu hráč nedostane.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node postava_mini_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  sql("update postavy set aktualni_hp=4, max_hp=5, aktualni_magenergie=3, max_magenergie=7 where id=2");
  // Tokeny si test určí sám podle postavy, ne podle pořadí.
  const tSindor = parseInt(sqlVal(`select id from tokeny where mapa_id=${MAPA} and typ_entity='postava' and entita_id=2`), 10);
  const tHrun = parseInt(sqlVal(`select id from tokeny where mapa_id=${MAPA} and typ_entity='postava' and entita_id=1`), 10);
  const druh = parseInt(sqlVal("select id from nestvury order by id limit 1"), 10);

  const prihlas = async (email, heslo, w, h) => {
    const p = await (await browser.newContext({ viewport: { width: w || 1280, height: h || 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(BASE + '/hra/mapa.php?id=' + MAPA); await p.waitForTimeout(1800);
    return p;
  };
  const bodTokenu = (p, id) => p.evaluate((i) => {
    const e = document.querySelector('.vtt-token[data-id="' + i + '"]');
    if (!e) return null;
    const r = e.getBoundingClientRect();
    return { x: Math.round(r.x), y: Math.round(r.y) };
  }, id);
  const klik = async (p, id) => { const b = await bodTokenu(p, id); await p.mouse.click(b.x, b.y); await p.waitForTimeout(900); };
  const dvojklik = async (p, id) => {
    const b = await bodTokenu(p, id);
    await p.mouse.click(b.x, b.y); await p.waitForTimeout(120); await p.mouse.click(b.x, b.y); await p.waitForTimeout(1500);
  };
  const dokOtevreny = (p) => p.locator('#denikDock').isVisible();
  const zavriDok = async (p) => { if (await dokOtevreny(p)) { await p.click('#denikZavrit'); await p.waitForTimeout(300); } };

  // ===== PJ =====
  const pj = await prihlas('test@local', 'test1234');
  const tok = await (await pj.request.post(BASE + '/hra/api/token_pridat.php', { data: { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: druh, x: 900, y: 600 } })).json();
  await zavriDok(pj);

  await klik(pj, tSindor);
  ok(await pj.locator('.pmini').count() === 1, 'jeden klik na postavu otevře malou kartu');
  ok(!(await dokOtevreny(pj)), 'jeden klik plný deník neotevře');
  ok(await pj.locator('#panel-spravovat.open').count() === 0, 'postranní panel se neotevře');
  const text = (await pj.locator('.pmini').textContent()).replace(/\s+/g, ' ');
  ok(/Sindor/.test(text), 'karta nese jméno postavy: ' + text.slice(0, 60));
  ok(/Životy\s*4 \/ 5/.test(text), 'životy sedí s databází (4 / 5)');
  ok(/Magenergie\s*3 \/ 7/.test(text), 'magenergie sedí s databází (3 / 7)');
  ok(/ÚČ/.test(text) && /OČ/.test(text), 'karta ukazuje ÚČ a OČ');
  // ÚČ a OČ proti tomu, s čím počítá deník (stejný zdroj jako souboj)
  const api = await (await pj.request.get(BASE + `/hra/api/postava_mini.php?mapa_id=${MAPA}&token_id=${tSindor}`)).json();
  const staty = await pj.locator('.pmini .pmini-stat strong').allTextContents();
  ok(staty[0] === (api.zbran ? String(api.zbran.uc) : (api.pest === null ? '–' : String(api.pest))), 'ÚČ na kartě = ÚČ zbraně v ruce z deníku, bez zbraně ÚČ pěsti (' + staty[0] + ')');
  ok(staty[1] === (api.oc === null ? '–' : String(api.oc)), 'OČ na kartě = OČ z deníku (' + staty[1] + ')');
  ok(await pj.locator('.pmini input, .pmini select').count() === 0, 'karta je jen ke čtení');

  // zavírání: klik mimo, Esc, křížek
  await pj.mouse.click(520, 760); await pj.waitForTimeout(300);
  ok(await pj.locator('.pmini').count() === 0, 'klik mimo kartu ji zavře');
  await klik(pj, tSindor);
  await pj.keyboard.press('Escape'); await pj.waitForTimeout(200);
  ok(await pj.locator('.pmini').count() === 0, 'Esc kartu zavře');
  await klik(pj, tSindor);
  await pj.click('.pmini-zavrit'); await pj.waitForTimeout(200);
  ok(await pj.locator('.pmini').count() === 0, 'křížek kartu zavře');

  // přepnutí na jinou postavu a na nestvůru
  await klik(pj, tSindor);
  await klik(pj, tHrun);
  ok(await pj.locator('.pmini').count() === 1 && /Hrun/.test(await pj.locator('.pmini').textContent()), 'klik na jinou postavu kartu přepne');
  ok(!/Magenergie/.test(await pj.locator('.pmini').textContent()), 'postava bez magenergie její řádek nemá');
  await klik(pj, tok.token_id);
  ok(await pj.locator('.pmini').count() === 0 && await pj.locator('.tvor-okno').count() === 1, 'klik na nestvůru kartu postavy zavře a otevře okno tvora');
  await pj.click('.tvor-zavrit');

  // plný deník: tlačítko a dvojklik
  await klik(pj, tSindor);
  await pj.click('.pmini-denik'); await pj.waitForTimeout(1500);
  ok(await dokOtevreny(pj) && await pj.locator('.pmini').count() === 0, 'tlačítko „Deník“ otevře plný deník a kartu zavře');
  await zavriDok(pj);
  await dvojklik(pj, tSindor);
  ok(await dokOtevreny(pj), 'dvojklik na token otevře plný deník');
  ok(await pj.locator('.pmini').count() === 0, 'po dvojkliku malá karta nezůstane viset');
  // otevřený deník klik jen přepne (karta by byla dvojmo)
  await klik(pj, tHrun);
  ok(await pj.locator('.pmini').count() === 0, 'při otevřeném deníku klik malou kartu neotvírá');
  await zavriDok(pj);

  // živá čísla: zásah se na kartě projeví
  await klik(pj, tSindor);
  await pj.request.post(BASE + '/hra/api/hp_uprava.php', { data: { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: -2 } });
  await pj.waitForTimeout(3500);
  ok(/Životy\s*2 \/ 5/.test((await pj.locator('.pmini').textContent()).replace(/\s+/g, ' ')), 'po zásahu karta ukáže nové životy (2 / 5)');
  ok(pj.chybyJs.length === 0, 'PJ: žádná chyba JavaScriptu ' + pj.chybyJs.join(' | '));

  // ===== hráč =====
  const hr = await prihlas('hrac@local', 'hrac1234');
  await zavriDok(hr);
  await klik(hr, tSindor);
  ok(await hr.locator('.pmini').count() === 1 && !(await dokOtevreny(hr)), 'hráč: klik na vlastní postavu otevře malou kartu, ne deník');
  const cizi = await hr.request.get(BASE + `/hra/api/postava_mini.php?mapa_id=${MAPA}&token_id=${tHrun}`);
  ok(cizi.status() === 403, 'hráč čísla cizí postavy nedostane (403): ' + cizi.status());
  const nestv = await hr.request.get(BASE + `/hra/api/postava_mini.php?mapa_id=${MAPA}&token_id=${tok.token_id}`);
  ok(nestv.status() === 404, 'token nestvůry kartu postavy nemá (404): ' + nestv.status());
  await klik(hr, tHrun);
  ok(await hr.locator('.pmini').count() === 0, 'hráč: klik na cizí postavu kartu neotevře');
  ok(hr.chybyJs.length === 0, 'hráč: žádná chyba JavaScriptu ' + hr.chybyJs.join(' | '));

  // ===== telefon =====
  const tel = await prihlas('hrac@local', 'hrac1234', 420, 800);
  await zavriDok(tel);
  await klik(tel, tSindor);
  ok(await tel.locator('.pmini').count() === 1 && !(await dokOtevreny(tel)), 'telefon: klik otevře malou kartu, ne deník');
  const bb = await tel.locator('.pmini').boundingBox();
  ok(bb.width > 380 && bb.y + bb.height > 780, 'telefon: karta sedí dole přes šířku: ' + JSON.stringify([Math.round(bb.width), Math.round(bb.y + bb.height)]));
  const tl = await tel.locator('.pmini-denik').boundingBox();
  ok(tl.height >= 36, 'telefon: tlačítko „Deník“ jde trefit prstem (' + Math.round(tl.height) + ' px)');
  await tel.click('.pmini-denik'); await tel.waitForTimeout(1500);
  ok(await dokOtevreny(tel), 'telefon: tlačítko „Deník“ otevře plný deník');

  const nepristup = await (await browser.newContext()).request.get(BASE + `/hra/api/postava_mini.php?mapa_id=${MAPA}&token_id=${tSindor}`);
  ok(nepristup.status() === 401, 'nepřihlášený nedostane nic (401): ' + nepristup.status());

  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
