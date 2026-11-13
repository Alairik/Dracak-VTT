// Obrana akcí: (1) bez akce se zbraní bránit nejde — ani když volí PJ (dřív měl výjimku); (2) kdo se brání akcí,
// smí se v ní otočit čelem k útočníkovi a postih −4 za útok z boku nebo zezadu pak neplatí (domácí pravidlo,
// hrac b93095–b93096). Token se otočí až při vyhodnocení útoku.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node obrana_otocit_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const pj = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const chybyJs = []; pj.on('pageerror', (e) => chybyJs.push(e.message));
  await pj.goto(BASE + '/index.php'); await pj.fill('#email', 'test@local'); await pj.fill('#password', 'test1234');
  await Promise.all([pj.waitForNavigation(), pj.click('button[type=submit]')]);
  const post = async (name, body) => { const r = await pj.request.post(BASE + '/hra/api/' + name + '.php', { data: Object.assign({ mapa_id: MAPA }, body) }); return Object.assign({ http: r.status() }, await r.json().catch(() => ({}))); };
  const stav = async () => (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(700);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const cs = Math.sqrt(3) * g.px, rs = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * cs + ((r & 1) ? cs / 2 : 0)), y: Math.round(r * rs) });
  const gobDruh = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10);
  const soucetOprav = (v) => (v.opravy || []).reduce((a, o) => a + o.hodnota, 0);
  const maOpravu = (v, re) => (v.opravy || []).some((o) => re.test(o.nazev));

  // Sindor stojí ZA goblinem (goblin hledí na východ, Sindor je na západ od něj) a útočí mu do zad.
  const utokZezadu = async (akciGoblina) => {
    await post('souboj_konec', {});
    for (const t of (await stav()).tokeny) await post('token_smazat', { token_id: t.id });
    sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=39 where id=2; update postava_predmety set u_sebe=1 where postava_id=2');
    const gob = (await post('token_pridat', { typ_entity: 'nestvura', nestvura_id: gobDruh, x: stred(10, 10).x, y: stred(10, 10).y })).token_id;
    const sin = (await post('token_pridat', { typ_entity: 'postava', postava_id: 2, x: stred(9, 10).x, y: stred(9, 10).y })).token_id;
    await post('token_uprava', { token_id: gob, zmeny: { smer: 0 } });
    await post('token_uprava', { token_id: sin, zmeny: { smer: 0 } });
    sql(`update nestvura_instance set aktualni_hp=30, max_hp=30 where id=(select entita_id from tokeny where id=${gob})`);
    await post('souboj_start', { ucastnici: [{ token_id: gob }, { token_id: sin }] });
    await post('souboj_hod', { token_ids: [sin], hod: 6, auto: true });
    await post('souboj_hod', { token_ids: [gob], hod: 4, auto: true });
    // Kdo je první, záleží na opravách iniciativy — útočit má Sindor, goblinovi se akce nastaví až potom.
    for (let i = 0; i < 4 && (await stav()).kolo.aktivni_token_id !== sin; i++) await post('tah_konec', {});
    sql(`update kolo_iniciativa set akce_zbyvajici=greatest(akce_zbyvajici, 1) where token_id=${sin}`);
    sql(`update kolo_iniciativa set akce_zbyvajici=${akciGoblina} where token_id=${gob}`);
    const r = await post('tah_akce', { token_id: sin, druh: 'utok', cil_token_id: gob });
    if (r.error) console.log('   tah_akce: ' + r.error);
    const vU = ((await stav()).vyzvy || []).find((v) => v.druh === 'utok');
    await post('vyzva_hod', { vyzva_id: vU.id, hody: [3] });
    const vO = ((await stav()).vyzvy || []).find((v) => v.druh === 'obrana');
    await post('vyzva_uprava', { vyzva_id: vO.id, zaklad: 3 });
    return { gob, sin, id: vO.id };
  };
  const vyzva = async (id) => ((await stav()).vyzvy || []).find((v) => v.id === id);
  const smer = (id) => sqlVal(`select smer from tokeny where id=${id}`);

  // ===== 1) otočení při obraně =====
  let s = await utokZezadu(2);
  let v = await vyzva(s.id);
  ok(v.strana === 'bok' && v.muze_branit === true, 'útok do zad: strana „bok“, goblin má akci a bránit se může');
  let r = await post('vyzva_uprava', { vyzva_id: s.id, otocit: true });
  ok(r.http === 422, 'otočit se bez zvolené obrany akcí nejde (' + r.http + ')');
  await post('vyzva_uprava', { vyzva_id: s.id, volba: 'branit' });
  v = await vyzva(s.id);
  ok(maOpravu(v, /z boku nebo zezadu/) && v.otocit === false, 'obrana akcí bez otočení: postih −4 za útok z boku platí');
  const bezOtoceni = soucetOprav(v);
  r = await post('vyzva_uprava', { vyzva_id: s.id, otocit: true });
  v = await vyzva(s.id);
  ok(r.ok && v.otocit === true && !maOpravu(v, /z boku nebo zezadu/), 'po „otočit se čelem“ postih −4 zmizí');
  ok(soucetOprav(v) === bezOtoceni + 4, 'opravy hodu se zlepšily přesně o 4 (' + bezOtoceni + ' → ' + soucetOprav(v) + ')');
  ok(smer(s.gob) === '0', 'token se zatím neotočil — otočí se až při vyhodnocení');
  // nebránit se otočení ruší
  await post('vyzva_uprava', { vyzva_id: s.id, volba: 'nebranit' });
  v = await vyzva(s.id);
  ok(v.otocit === false && maOpravu(v, /bez obrany zbraní, útok z boku/), '„Nebráním se“ otočení zruší a platí −6');
  // v prohlížeči: tlačítko otočení je na kartě jen při obraně akcí
  await pj.reload(); await pj.waitForTimeout(1500);
  ok(await pj.locator('.vyzva-obrana .vyzva-otocit').count() === 0, 'karta: bez obrany akcí tlačítko otočení není');
  await pj.click('.vyzva-obrana [data-volba="branit"]'); await pj.waitForTimeout(1300);
  ok(await pj.locator('.vyzva-obrana .vyzva-otocit').count() === 1, 'karta: po „Bráním se“ se nabídne otočení čelem');
  await pj.click('.vyzva-obrana .vyzva-otocit'); await pj.waitForTimeout(1300);
  ok(await pj.getAttribute('.vyzva-obrana .vyzva-otocit', 'aria-pressed') === 'true' && !/z boku nebo zezadu/.test(await pj.innerText('.vyzva-obrana .vyzva-slozeni')), 'karta: po kliknutí je otočení zapnuté a štítek −4 zmizel');
  const akcePred = parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${s.gob}`), 10);
  await post('vyzva_hod', { vyzva_id: s.id, hody: [2] });
  ok(smer(s.gob) === '180', 'po vyhodnocení se goblin otočil čelem k útočníkovi (180°): ' + smer(s.gob));
  ok(parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${s.gob}`), 10) === akcePred - 1, 'obrana s otočením stála jednu akci (ne dvě)');

  // ===== 2) bez akce se bránit zbraní nejde, ani když volí PJ =====
  s = await utokZezadu(0);
  v = await vyzva(s.id);
  ok(v.muze_branit === false && v.volba === 'nebranit', 'goblin bez akcí: rovnou „nebrání se“');
  r = await post('vyzva_uprava', { vyzva_id: s.id, volba: 'branit' });
  ok(r.http === 422 && /nemá žádnou akci/.test(r.error || ''), 'PJ mu obranu akcí nezvolí (' + r.http + '): ' + (r.error || ''));
  v = await vyzva(s.id);
  ok(v.volba === 'nebranit', 'volba zůstala „nebránit se“');
  await pj.reload(); await pj.waitForTimeout(1500);
  ok(await pj.locator('.vyzva-obrana [data-volba="branit"]').isDisabled(), 'karta: „Bráním se“ je i pro PJ zamčené');
  ok(/nemá žádnou akci/.test(await pj.innerText('.vyzva-obrana [data-volba="branit"]')), 'a říká proč: ' + (await pj.innerText('.vyzva-obrana [data-volba="branit"]')).replace(/\s+/g, ' '));
  ok(await pj.locator('.vyzva-obrana .vyzva-otocit').count() === 0, 'bez akce se nejde ani otočit');
  ok(chybyJs.length === 0, 'žádná chyba JavaScriptu ' + chybyJs.join(' | '));

  await post('souboj_konec', {});
  for (const t of (await stav()).tokeny) await post('token_smazat', { token_id: t.id });
  sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2');
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
