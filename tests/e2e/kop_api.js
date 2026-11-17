// Kop (Tabulka rvačky, hrac h1654 b10987): volitelná varianta útoku — ÚČ = síla nohy (A 3, B 5, C 7) + bonus za
// sílu, útočnost +3, působí stínové zranění (útok beze zbraně, b10988). Jde použít jen JEDNOU ZA KOLO a v nejbližší
// další akci útok/obrana má útočník postih −4 (−2 bojovník/šermíř/lupič/chodec).
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node kop_api.js
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
  await pj.goto(BASE + '/index.php'); await pj.fill('#email', 'test@local'); await pj.fill('#password', 'test1234');
  await Promise.all([pj.waitForNavigation(), pj.click('button[type=submit]')]);
  const post = async (name, body) => { const r = await pj.request.post(BASE + '/hra/api/' + name + '.php', { data: Object.assign({ mapa_id: MAPA }, body) }); return Object.assign({ http: r.status() }, await r.json().catch(() => ({}))); };
  const stav = async () => (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  const vyzvy = async () => (await stav()).vyzvy || [];
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(700);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const cs = Math.sqrt(3) * g.px, rs = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * cs + ((r & 1) ? cs / 2 : 0)), y: Math.round(r * rs) });
  const gobDruh = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10);
  const gobStin = (gob) => parseInt(sqlVal(`select stin_hp from nestvura_instance where id=(select entita_id from tokeny where id=${gob})`), 10);
  const gobHp = (gob) => parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=(select entita_id from tokeny where id=${gob})`), 10);
  const kopStav = (tok) => sqlVal(`select concat(kop_kolo,'/',kop_postih) from kolo_iniciativa where token_id=${tok}`);

  // Očekávaný postih podle povolání Sindora (postava 2).
  const pov = sqlVal("select pv.nazev from postavy po left join povolani pv on pv.id=po.povolani_id where po.id=2");
  const ocekPostih = ['Bojovník', 'Šermíř', 'Lupič', 'Chodec'].includes(pov) ? 2 : 4;
  console.log('   Sindor je ' + pov + ' → postih za kop má být ' + ocekPostih);

  const zahaj = async () => {
    await post('souboj_konec', {});
    for (const t of (await stav()).tokeny) await post('token_smazat', { token_id: t.id });
    sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2');
    const gob = (await post('token_pridat', { typ_entity: 'nestvura', nestvura_id: gobDruh, x: stred(10, 10).x, y: stred(10, 10).y })).token_id;
    const sin = (await post('token_pridat', { typ_entity: 'postava', postava_id: 2, x: stred(11, 10).x, y: stred(11, 10).y })).token_id;
    await post('token_uprava', { token_id: sin, zmeny: { smer: 180 } });
    sql(`update nestvura_instance set aktualni_hp=30, max_hp=30, stin_hp=0 where id=(select entita_id from tokeny where id=${gob})`);
    await post('souboj_start', { ucastnici: [{ token_id: gob }, { token_id: sin }] });
    await post('souboj_hod', { token_ids: [sin], hod: 6, auto: true });
    await post('souboj_hod', { token_ids: [gob], hod: 1, auto: true });
    for (let i = 0; i < 4 && (await stav()).kolo.aktivni_token_id !== sin; i++) await post('tah_konec', {});
    sql(`update kolo_iniciativa set akce_zbyvajici=4 where token_id=${sin}`);
    return { gob, sin };
  };
  // nový útok tahem a vrácení výzvy útoku
  const utok = async (sin, gob) => { await post('tah_akce', { token_id: sin, druh: 'utok', cil_token_id: gob }); return (await vyzvy()).find((v) => v.druh === 'utok'); };

  const s = await zahaj();
  let vU = await utok(s.sin, s.gob);

  // ===== 1) kop je ve variantách a počítá se správně =====
  // ÚČ pěsti i kopu = (síla pěsti/nohy podle velikosti) + stejný bonus za sílu, takže rozdíl = síla nohy − síla pěsti,
  // což je podle velikosti 1 (A: 3−2), 2 (B: 5−3) nebo 3 (C: 7−4). Nezávisí na neznámém bonusu za sílu.
  const pest = vU.varianty.find((z) => z.id === -1);
  const kop = vU.varianty.find((z) => z.id === -2);
  ok(!!kop && kop.nazev === 'kop', 'kop je ve variantách útoku: ' + vU.varianty.map((z) => z.nazev).join(', '));
  ok(kop && pest && [1, 2, 3].includes(kop.uc - pest.uc), 'ÚČ kopu je o sílu nohy nad pěstí (rozdíl 1/2/3 podle velikosti): kop ' + (kop ? kop.uc : '—') + ', pěst ' + (pest ? pest.uc : '—'));
  ok(kop && kop.utocnost === 3, 'útočnost kopu je +3: ' + (kop ? kop.utocnost : '—'));

  // ===== 2) přepnutí na kop: stínové, útočnost 3 =====
  let r = await post('vyzva_uprava', { vyzva_id: vU.id, zbran_id: -2 });
  vU = (await vyzvy()).find((v) => v.id === vU.id);
  ok(!r.error && vU.zbran_id === -2 && vU.stinove === true && vU.utocnost === 3, 'po přepnutí na kop: stínové, útočnost 3');

  // ===== 3) kop se vyhodnotí: stínové zranění + nastaví se postih do další akce =====
  await post('vyzva_hod', { vyzva_id: vU.id, hody: [5] });
  let vO = (await vyzvy()).find((v) => v.druh === 'obrana');
  await post('vyzva_uprava', { vyzva_id: vO.id, volba: 'nebranit', zaklad: 0 });
  const vys = await post('vyzva_hod', { vyzva_id: vO.id, hody: [1] });
  ok(vys.vysledek && vys.vysledek.stinove === true && gobStin(s.gob) > 0 && gobHp(s.gob) === 30, 'kop působí stínové zranění (fyzické beze změny)');
  const [kopKolo, kopPost] = kopStav(s.sin).split('/');
  ok(kopKolo !== '0' && Number(kopPost) === ocekPostih, 'po kopu je v kolo_iniciativa kop_kolo>0 a kop_postih = ' + ocekPostih + ' (' + kopStav(s.sin) + ')');

  // ===== 4) další útok téhož tvora v tomtéž kole: postih za kop se promítne a kop už nejde =====
  vU = await utok(s.sin, s.gob);
  const maPostih = (vU.opravy || []).find((o) => /postih za kop/.test(o.nazev));
  ok(maPostih && maPostih.hodnota === -ocekPostih, 'další útok nese opravu „postih za kop“ = −' + ocekPostih + ': ' + (maPostih ? maPostih.hodnota : 'není'));
  r = await post('vyzva_uprava', { vyzva_id: vU.id, zbran_id: -2 });
  ok(r.http === 422 && /jednou za kolo/.test(r.error || ''), 'druhý kop v tomtéž kole nejde (' + r.http + '): ' + (r.error || ''));

  // ===== 5) po vyhodnocení toho útoku se postih spotřebuje (kop_postih zpět na 0) =====
  await post('vyzva_hod', { vyzva_id: vU.id, hody: [4] });
  vO = (await vyzvy()).find((v) => v.druh === 'obrana');
  await post('vyzva_uprava', { vyzva_id: vO.id, volba: 'nebranit', zaklad: 0 });
  await post('vyzva_hod', { vyzva_id: vO.id, hody: [1] });
  ok(kopStav(s.sin).split('/')[1] === '0', 'postih za kop se po další akci spotřeboval (kop_postih = 0): ' + kopStav(s.sin));

  await post('souboj_konec', {});
  for (const t of (await stav()).tokeny) await post('token_smazat', { token_id: t.id });
  sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2');
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
