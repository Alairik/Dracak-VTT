// Okno tvora (jen PJ): klik na nestvůru otevře plovoucí kartu u tokenu, ne postranní panel. Ukazuje jen tvora —
// životy a stav, útoky, obranu, čím ho jde zranit, schopnosti a co na něj působí. Odolnost a pohyblivost tam
// vědomě nejsou (patří do deníku) a efekty se odtud nenasazují, jen vidí (rozhodnutí uživatele 2026-11-10).
// Připomínky uživatele k návrhu (2026-11-10): vysvětlivky jsou v bublině za otazníčkem, ne text v okně;
// zranitelnost je PÍSMENO z bestiáře a slovo vyjede na nájezd; životy mají jen − a + a číslo se dá přepsat rukou;
// velikost nepatří mezi čísla obrany; odkaz na deník je v hlavičce.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node tvor_okno_ui.js
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
  sql("delete from tokeny where typ_entity='nestvura_instance'; delete from nestvura_instance");
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  // Býk má v bestiáři spočítaný útok („(+3 + 5) = 8 (rohy)“) i zranitelnosti, takže je na zkoušku vidět všechno.
  const druh = parseInt(sqlVal("select id from nestvury where nazev='Býk'"), 10);
  const tok = await (await pj.request.post(BASE + '/hra/api/token_pridat.php', { data: { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: druh, x: 500, y: 400 } })).json();
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(1800);

  // Token je obalový prvek s nulovou velikostí (grafika je uvnitř), proto se kliká na jeho souřadnice.
  const bod = await pj.evaluate((id) => {
    const e = document.querySelector('.vtt-token[data-id="' + id + '"]');
    if (!e) return null;
    const r = e.getBoundingClientRect();
    return { x: Math.round(r.x), y: Math.round(r.y) };
  }, tok.token_id);
  ok(!!bod, 'token nestvůry je na mapě');
  await pj.mouse.click(bod.x, bod.y); await pj.waitForTimeout(1300);

  ok(await pj.locator('.tvor-okno').count() === 1, 'klik na nestvůru otevře okno tvora');
  ok(await pj.locator('#panel-spravovat.open').count() === 0, 'postranní panel se u nestvůry neotevře');
  const nadpisy = (await pj.locator('.tvor-okno .tvor-h').allTextContents()).join('|');
  ok(/Útoky/.test(nadpisy) && /Obrana/.test(nadpisy) && /Zraní ji/.test(nadpisy), 'okno má sekce Útoky, Obrana, Zraní ji: ' + nadpisy);

  // Co tam vědomě NENÍ
  const text = (await pj.locator('.tvor-okno').textContent()).replace(/\s+/g, ' ');
  ok(!/Odolnost/i.test(text), 'odolnost v okně není (patří do deníku)');
  ok(!/Pohyblivost/i.test(text), 'pohyblivost v okně není (patří do deníku)');
  ok(await pj.locator('.tvor-okno select').count() === 0, 'v okně se efekt nenasazuje (žádný výběr efektu)');

  // Útok a obrana z bestiáře
  ok(/rohy/.test(text) && /ÚČ 8/.test(text), 'útok z bestiáře i s číslem: ' + (text.match(/rohy[^·]*·[^|]{0,22}/) || [''])[0]);
  ok(/OČ 3/.test(text), 'obranné číslo je vidět');

  ok(!/velikost/i.test((await pj.locator('.tvor-okno .tvor-cipy').allTextContents()).join(' ')),
    'velikost není mezi čísly obrany (patří k druhu tvora v hlavičce)');
  ok(/velikost /.test(await pj.locator('.tvor-okno .tvor-druh').textContent()), 'velikost je v hlavičce u druhu tvora');

  // Zranitelnosti — v datech byly odjakživa, ale hra je dosud nikde neukazovala.
  // Na čipu je písmeno z bestiáře (kody_zranitelnosti.kod), celé slovo v nájezdu.
  const zrChipy = await pj.locator('.tvor-zr').all();
  const zrText = [];
  const zrSlovo = [];
  for (const c of zrChipy) { zrText.push((await c.textContent()).trim()); zrSlovo.push(await c.getAttribute('aria-label')); }
  ok(zrChipy.length >= 5, 'okno ukazuje, čím jde nestvůru zranit: ' + zrChipy.length + ' položek');
  ok(zrText.every((x) => /^[A-P](×[\d½¼/]+)?$/.test(x)), 'na čipu je jen písmeno (+ případný násobek): ' + zrText.join(', '));
  ok(zrSlovo.every((x) => x && /^[A-P] — .+/.test(x)), 'ke každému písmenu patří slovo pro nájezd: ' + (zrSlovo[0] || '—'));
  ok(zrSlovo.some((x) => /Obyčejná zbraň/.test(x)), 'mezi nimi obyčejná zbraň: ' + zrSlovo.slice(0, 3).join(' / '));
  ok(zrSlovo.every((x) => !/ — *$/.test(x)), 'žádné písmeno není bez jména (migrace 0118 doplnila skupiny kouzel)');

  // Vysvětlivky nejsou text v okně, ale bublina za otazníčkem (zadání uživatele 2026-11-10)
  const hlp = await pj.locator('.tvor-okno .tvor-hlp').count();
  ok(hlp >= 3, 'sekce mají otazníček s nápovědou: ' + hlp);
  const bubliny = (await pj.locator('.tvor-okno .tvor-hlp .tvor-tip').allTextContents()).join(' | ');
  ok(/Co v seznamu není/.test(bubliny), 'pravidlo „co v seznamu není, neublíží“ je v bublině: ' + bubliny.slice(0, 50));
  ok(await pj.locator('.tvor-okno .tvor-hlp .tvor-tip').first().isVisible() === false, 'bublina je schovaná, dokud na otazníček nikdo nejede');
  await pj.locator('.tvor-okno .tvor-hlp').first().click(); await pj.waitForTimeout(200);
  ok(await pj.locator('.tvor-okno .tvor-hlp.otevreno .tvor-tip').first().isVisible() === true, 'klepnutí na otazníček bublinu ukáže (telefon)');
  await pj.locator('.tvor-okno .tvor-hlp').first().click(); await pj.waitForTimeout(200);
  ok(await pj.locator('.tvor-okno .tvor-hlp.otevreno').count() === 0, 'druhé klepnutí bublinu zavře');

  // Stav podle životů počítá server
  ok(/Bojeschopná/.test(text), 'při plných životech je bojeschopná');
  const instanceId = sqlVal(`select entita_id from tokeny where id=${tok.token_id}`);
  const hpPredtim = parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${instanceId}`), 10);
  ok(await pj.locator('.tvor-okno .tvor-hpb').count() === 2, 'u pruhu životů jsou jen dvě tlačítka, − a +');
  ok(await pj.locator('.tvor-okno .tvor-pruh b').count() === 1, 'pruh má značku meze vyřazení');
  await pj.locator('.tvor-okno .tvor-hpb').first().click(); await pj.waitForTimeout(1300);
  ok(parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${instanceId}`), 10) === hpPredtim - 1,
    'tlačítko − ubralo jeden život v databázi');

  // Ruční přepis životů: PJ napíše cílové číslo, nemusí klikat po jednom
  await pj.locator('.tvor-okno .tvor-hp-vstup').fill('1');
  await pj.locator('.tvor-okno .tvor-hp-vstup').press('Enter');
  await pj.waitForTimeout(1400);
  ok(sqlVal(`select aktualni_hp from nestvura_instance where id=${instanceId}`) === '1', 'ruční přepis čísla uložil životy do databáze');
  const text2 = (await pj.locator('.tvor-okno').textContent()).replace(/\s+/g, ' ');
  ok(/Vyřazená z boje/.test(text2), 'po zásahu okno hlásí vyřazení z boje: ' + (text2.match(/(Vyřazená|Bojeschopná|Pod třetinou)[^·]*/) || [''])[0]);
  await pj.locator('.tvor-okno .tvor-hp-vstup').fill('abc');
  await pj.locator('.tvor-okno .tvor-hp-vstup').press('Enter');
  await pj.waitForTimeout(900);
  ok(sqlVal(`select aktualni_hp from nestvura_instance where id=${instanceId}`) === '1', 'nesmysl místo čísla životy nezmění');

  // Zkušenost a poklady, které PJ jinak hledá v knize — rovnou vidět, ne ve sbaleném bloku
  ok(/Zkušenost 70/.test(text2), 'zkušenost je vidět rovnou: ' + (text2.match(/Zkušenost[^|]{0,30}/) || [''])[0]);

  // Efekty: jen k vidění. Nasazuje je kouzlo/lektvar/akce — tady se objeví a jdou sundat.
  const efekt = parseInt(sqlVal('select id from efekty order by id limit 1'), 10);
  const instance = instanceId;
  await pj.request.post(BASE + '/hra/api/efekt_pridat.php', { data: { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: parseInt(instance, 10), efekt_id: efekt, kola: 3 } });
  await pj.waitForTimeout(2600);
  ok(await pj.locator('.tvor-okno .tvor-efekt').count() === 1, 'nasazený efekt se v okně objeví sám');
  await pj.locator('.tvor-okno .tvor-efekt-x').click(); await pj.waitForTimeout(1300);
  ok(sqlVal(`select count(*) from aktivni_efekty where typ_entity='nestvura_instance' and entita_id=${instance}`) === '0', 'křížkem jde efekt sundat');

  // Do deníku, výbavy a kořisti vede odkaz v hlavičce (spodní tlačítko byl jeho dvojník, viz připomínka uživatele)
  ok(await pj.locator('.tvor-paticka').count() === 0, 'spodní tlačítko s deníkem už v okně není');
  await pj.locator('.tvor-okno .tvor-denik').click(); await pj.waitForTimeout(700);
  ok(await pj.locator('#panel-spravovat.open').count() === 1, 'odkaz Deník v hlavičce otevře postranní panel s deníkem a výbavou');

  // Zavření
  await pj.locator('.tvor-zavrit').click(); await pj.waitForTimeout(300);
  ok(await pj.locator('.tvor-okno').count() === 0, 'křížek okno zavře');
  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.join(' | '));

  // Hráč okno tvora nemá (bestiář je tajný)
  const hrac = await prihlas('hrac@local', 'hrac1234');
  await hrac.goto(BASE + '/hra/mapa.php?id=' + MAPA); await hrac.waitForTimeout(1500);
  const bodH = await hrac.evaluate((id) => {
    const e = document.querySelector('.vtt-token[data-id="' + id + '"]');
    if (!e) return null;
    const r = e.getBoundingClientRect();
    return { x: Math.round(r.x), y: Math.round(r.y) };
  }, tok.token_id);
  if (bodH) { await hrac.mouse.click(bodH.x, bodH.y); await hrac.waitForTimeout(900); }
  ok(await hrac.locator('.tvor-okno').count() === 0, 'hráči se okno tvora neotevře (bestiář je tajný)');

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
