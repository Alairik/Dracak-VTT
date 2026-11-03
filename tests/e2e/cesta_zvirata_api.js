// Jezdecká zvířata z h2057 (migrace 0111): číselník má 43 zvířat (7 z h1635 + 36 z h2057), hodnoty sedí s knihou, nová zvířata jdou do kalkulačky cesty
// (náklad nad 4× nosnosti se odmítne podle nosnosti z tabulky, ne z odhadu). Po resetu dat: mysql dracak < ../fixture/seed.sql; node cesta_zvirata_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext()).newPage();
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const api = async (p, b) => { const r = await p.request.post(BASE + '/hra/api/cesta_vypocet.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };

  ok(sqlVal('select count(*) from jizdni_zvirata') === '43', 'v číselníku je 43 zvířat (7 + 36 z h2057)');
  const radek = (n) => sqlVal(`select concat_ws(',',pohyblivost,nosnost_mn,vytrvalost,ujede_mil) from jizdni_zvirata where nazev='${n}'`);
  ok(radek('válečný kůň') === '19,1000,18,38', 'válečný kůň 19 / 1000 / 18 / 38 podle h2057');
  ok(radek('slon') === '15,2500,20,49', 'slon 15 / 2500 / 20 / 49');
  ok(radek('velbloud') === '12,1500,24,59', 'velbloud 12 / 1500 / 24 / 59');
  ok(radek('orel královský') === '25,600,14,706', 'orel královský 25 / 600 / 14 / 706');
  ok(radek('sova obří 2 (na zemi)') === '5,200,6,10', 'sova obří na zemi 5 / 200 / 6 / 10');
  ok(sqlVal("select count(*) from jizdni_zvirata where nazev like 'draci%' or nazev like 'mořský%'") === '0', 'draci a mořský kůň (bez vytrvalosti / řídí bestiář) v číselníku nejsou');

  const slon = Number(sqlVal("select id from jizdni_zvirata where nazev='slon'"));
  const jednorozec = Number(sqlVal("select id from jizdni_zvirata where nazev='jednorožec'"));
  const zaklad = { svet_id: 1, vzdalenost: 30, povrch: 10, teren: 1, vliv: 1, tempo: 'chuze', zastav: 2, ucastnici: [{ postava_id: 2, vaha_jezdce: 0 }] };
  let r = await api(pj, { ...zaklad, zvirata: [{ zvire_id: slon, naklad: 3000 }] });
  ok(r.s === 200 && (r.chyby || []).length === 0, 'slon s nákladem 3000 mn (nosnost 2500) projde bez chyby: ' + JSON.stringify(r.chyby));
  const sl = (r.subjekty || []).find((s) => /slon/i.test(s.nazev));
  ok(!!sl && sl.pohyblivost < 15, 'naložený slon (3000 > nosnost 2500) má pohyblivost nižší než základních 15: ' + (sl && sl.pohyblivost));
  r = await api(pj, { ...zaklad, zvirata: [{ zvire_id: slon, naklad: 0 }] });
  const sl0 = (r.subjekty || []).find((s) => /slon/i.test(s.nazev));
  ok(!!sl0 && sl0.pohyblivost === 15, 'nenaložený slon má pohyblivost 15 podle h2057: ' + (sl0 && sl0.pohyblivost));
  r = await api(pj, { ...zaklad, zvirata: [{ zvire_id: jednorozec, naklad: 3000 }] });
  ok(r.s === 200 && (r.chyby || []).some((c) => /jednorožec/.test(c)), 'jednorožec (nosnost 500) s nákladem 3000 mn (nad 4×) se odmítne: ' + JSON.stringify(r.chyby));

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
