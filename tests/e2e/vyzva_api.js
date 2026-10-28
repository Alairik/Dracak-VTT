const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.accept());
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(600);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const funkce = await pj.evaluate(() => window.MAPA.funkce);
  ok(funkce.vyzvy === true, 'funkce vyzvy je zapnutá (tabulka souboj_vyzva existuje)');

  async function uklid(zachovatVyzvy) {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    if (!zachovatVyzvy) sql("delete from souboj_vyzva");
    sql("update postavy set aktualni_hp=5, max_hp=5 where id=2");
  }
  async function pridej(typ, col, row, extra = {}) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }, extra));
    return d.token_id;
  }
  async function boj(actor, ostatni, akce = 18) {
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [actor, ...ostatni].map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [actor], hod: 6 });
    for (const o of ostatni) await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [o], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
    for (let i = 0; i < akce / 9; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: actor, op: 'delta', delta: 9 });
  }
  const tok = async (id) => (await stav(pj)).tokeny.find(t => t.id === id);
  const vyzvy = async (page) => (await stav(page)).vyzvy;
  const hpGob = (inst) => parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${inst}`), 10);

  // ===================== A: Sindor (hráč) útočí na goblina =====================
  await uklid();
  const sindor = await pridej('postava', 13, 10);
  const gob = await pridej('nestvura', 14, 10);
  const gobInst = parseInt(sqlVal(`select entita_id from tokeny where id=${gob}`), 10);
  sql(`update nestvura_instance set aktualni_hp=9, max_hp=9 where id=${gobInst}`);
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await boj(sindor, [gob]);
  let d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  ok(d.ok && d.vyzva_id, 'útok založí výzvu k hodu: ' + (d.error || 'vyzva ' + d.vyzva_id));
  const vUtok = d.vyzva_id;
  let st = await stav(pj);
  ok(st.kolo.aktivni_token_id === sindor, 'tah se po útoku neposunul (útok přeruší tah)');
  let vy = await vyzvy(hr);
  ok(vy.length === 1 && vy[0].druh === 'utok' && vy[0].smi_hodit === true, 'hráč vidí svoji výzvu a smí hodit');
  console.log('   složky útoku:', JSON.stringify({ z: vy[0].zaklad, n: vy[0].zaklad_nazev, o: vy[0].opravy, up: vy[0].upozorneni, cislo: vy[0].cislo, utocnost: vy[0].utocnost }));
  ok(vy[0].zaklad === 1 && /Dýka/.test(vy[0].zaklad_nazev), 'ÚČ Dýky z deníku postavy (SZ 2 + bonus síly −1)');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  ok(d.s === 422 && /hod/.test(d.error), 'dokud se nedohraje hod, další akce nejde: ' + d.error);
  d = await api(hr, 'tah_konec', { mapa_id: MAPA });
  ok(d.s === 422, 'ani ukončit tah nejde: ' + d.error);
  d = await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, modifikator: 5 });
  ok(d.s === 403, 'hráč nesmí měnit modifikátor: ' + d.error);
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, modifikator: 3 });
  ok(d.ok, 'PJ změní modifikátor o +3');
  vy = await vyzvy(hr);
  ok(vy[0].modifikator === 3 && vy[0].cislo === 4, 'hráč vidí změnu: číslo 1 + 3 = 4');
  // validace hodů
  for (const [body, popis] of [[{ hody: [7] }, 'hod 7'], [{ hody: [0] }, 'hod 0'], [{ hody: [6] }, 'samotná šestka'], [{ hody: [3, 2] }, 'dva hody bez šestky'], [{ soucet: 12 }, 'ruční součet 12'], [{ hody: [] }, 'prázdný hod']]) {
    d = await api(hr, 'vyzva_hod', Object.assign({ mapa_id: MAPA, vyzva_id: vUtok }, body));
    ok(d.s === 422, 'neplatný hod zamítnut (' + popis + '): ' + d.error);
  }
  d = await api(pj, 'souboj_hod', {});
  // hráč nesmí hodit za goblina; PJ smí hodit za hráče
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vUtok, soucet: 9, rucne: true });
  ok(d.ok && JSON.stringify(d.hody) === '[6,3]' && d.celkem === 13, 'ruční součet 9 = [6,3], celkem 9 + 4 = 13: ' + (d.error || JSON.stringify(d.hody) + ' celkem ' + d.celkem));
  ok(d.dalsi_vyzva_id > 0, 'po hodu útoku vznikla výzva obrany');
  const vObr = d.dalsi_vyzva_id;
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vUtok, hody: [2] });
  ok(d.s === 422, 'útok nejde hodit dvakrát: ' + d.error);
  vy = await vyzvy(hr);
  const ob = vy.find(x => x.druh === 'obrana');
  ok(vy.length === 2 && ob && ob.smi_hodit === false && ob.smi_volit === false, 'hráč obranu goblina nevidí jako svou (nehází, nevolí)');
  const obPj = (await vyzvy(pj)).find(x => x.druh === 'obrana');
  ok(ob.zaklad === null && ob.cislo === null && ob.upozorneni.length === 0, 'hráč nevidí staty goblina v obraně');
  ok(obPj.zaklad === null && obPj.upozorneni.length > 0, 'OČ goblina z textu bestiáře nejde spočítat -> PJ doplní: ' + (obPj.upozorneni[0] || '').slice(0, 60));
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vObr, hody: [2] });
  ok(d.s === 422 && /obranné číslo/i.test(d.error), 'bez OČ se obrana hodit nedá: ' + d.error);
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, zaklad: 5 });
  ok(d.ok, 'PJ doplní OČ goblina 5');
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vObr, hody: [2] });
  ok(d.s === 422 && /rozhodnout/.test(d.error), 'bez volby způsobu obrany se nehází: ' + d.error);
  d = await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, volba: 'branit' });
  ok(d.s === 403, 'hráč za goblina volit nesmí');
  vy = await vyzvy(pj);
  ok(vy.find(x => x.druh === 'obrana').muze_branit === true, 'goblin má akci -> smí se bránit akcí');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, volba: 'nebranit' });
  vy = await vyzvy(pj);
  let o2 = vy.find(x => x.druh === 'obrana');
  ok(o2.opravy.some(o => o.hodnota === -3) && o2.cislo === 2, 'nebránit se: −3 => OČ 5 − 3 = 2');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, volba: 'branit' });
  vy = await vyzvy(pj); o2 = vy.find(x => x.druh === 'obrana');
  ok(o2.cislo === 5 && o2.opravy.length === 0, 'bránit se (goblin, OZ neznámé): OČ 5 bez oprav');
  const akceGob0 = parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${gob}`), 10);
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vObr, hody: [2] });
  ok(d.ok && d.vysledek, 'obrana hozena, výsledek vyhodnocen: ' + (d.error || JSON.stringify(d.vysledek)));
  // útok 13 vs obrana 2 + 5 = 7 -> zásah, zranění 13 - 7 + 0 = 6
  ok(d.vysledek.zasah === true && d.vysledek.zraneni === 6 && d.vysledek.utok === 13 && d.vysledek.obrana === 7, 'zásah, zranění 6 (13 − 7 + útočnost 0)');
  ok(hpGob(gobInst) === 3, 'goblin má 9 − 6 = 3 životy');
  ok(parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${gob}`), 10) === akceGob0 - 1, 'bránil se zbraní -> ztratil jednu akci');
  ok(d.vysledek.vyrazen === false, 'goblin s 3 životy (odolnost 15, mez 1/8 z 9 = 1) není vyřazen');
  vy = await vyzvy(hr);
  ok(vy.length === 0, 'po vyhodnocení nezůstává žádná otevřená výzva');
  st = await stav(pj);
  ok(st.kolo.aktivni_token_id === sindor, 'tah útočníka pokračuje (má další akce)');
  const ev = sqlVal("select group_concat(distinct typ) from svet_udalosti where mapa_id=2 and id > (select max(id)-40 from svet_udalosti)");
  ok(/vyzva_nova/.test(ev) && /vyzva_hod/.test(ev) && /souboj_vysledek/.test(ev) && /hp_zmena/.test(ev), 'události výzev a životů jsou v logu: ' + ev);

  // ===================== B: goblin útočí na Sindora (PJ hází), z boku =====================
  await uklid();
  const sindorB = await pridej('postava', 13, 10);
  const gobB = await pridej('nestvura', 14, 10);
  const gobBInst = parseInt(sqlVal(`select entita_id from tokeny where id=${gobB}`), 10);
  sql(`update nestvura_instance set aktualni_hp=9, max_hp=9 where id=${gobBInst}`);
  await api(pj, 'token_uprava', { token_id: gobB, zmeny: { smer: 180 } });   // goblin hledí na Sindora
  await api(pj, 'token_uprava', { token_id: sindorB, zmeny: { smer: 0 } });   // Sindor hledí na goblina
  await boj(gobB, [sindorB]);
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gobB, druh: 'utok', cil_token_id: sindorB });
  ok(d.ok && d.vyzva_id, 'goblin útočí na Sindora: ' + (d.error || ''));
  let vyB = (await vyzvy(pj))[0];
  ok(vyB.zaklad === null && vyB.upozorneni.some(u => /Útočné číslo nestvůry/.test(u)), 'ÚČ goblina se z textu nespočítá -> PJ doplní');
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vyB.id, hody: [3] });
  ok(d.s === 422, 'bez ÚČ nejde házet: ' + d.error);
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vyB.id, zaklad: 4, utocnost: 1 });
  ok(d.ok, 'PJ doplní ÚČ 4 a útočnost +1');
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vyB.id, hody: [3] });
  ok(d.s === 403, 'hráč nesmí hodit za goblina');
  const vBhr = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(vBhr && vBhr.zaklad === null && vBhr.cislo === null && vBhr.opravy.length === 0 && vBhr.utocnost === 0 && vBhr.upozorneni.length === 0, 'hráč nevidí rozpis ÚČ nestvůry (stejně jako bestiář): ' + JSON.stringify({ z: vBhr && vBhr.zaklad, c: vBhr && vBhr.cislo }));
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vyB.id, hody: [6, 6, 2] });
  ok(d.ok && d.celkem === 18, 'řetěz 6+6+2 = 14, + ÚČ 4 = 18: ' + (d.error || d.celkem));
  const utokPoHodu = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(utokPoHodu && utokPoHodu.celkem === 18 && utokPoHodu.hody.join(',') === '6,6,2', 'hráč vidí hody a celkový součet útoku nestvůry');
  const obrB = (await vyzvy(hr)).find(x => x.druh === 'obrana');
  ok(obrB && obrB.smi_volit && !obrB.smi_hodit, 'hráč (obránce Sindor) smí volit způsob obrany, ale ještě nesmí hodit');
  ok(obrB.zaklad === 1, 'OČ Sindora = KZ 1 (bez zbroje) + bonus obratnosti 12 (0): ' + obrB.zaklad);
  ok(obrB.strana === 'predni', 'Sindor hledí na goblina -> útok zepředu');
  d = await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obrB.id, volba: 'branit' });
  ok(d.ok, 'hráč volí bránit se');
  const obrB2 = (await vyzvy(hr)).find(x => x.druh === 'obrana');
  console.log('   složky obrany:', JSON.stringify({ z: obrB2.zaklad, o: obrB2.opravy, c: obrB2.cislo }));
  ok(obrB2.opravy.some(o => /obrana zbraně|bez zbraně/.test(o.nazev)), 'Sindor nemá drženou zbraň -> bez zbraně −3');
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: obrB.id, hody: [1] });
  ok(d.ok && d.vysledek, 'Sindor hodí obranu: ' + (d.error || JSON.stringify(d.vysledek)));
  const hpSindor = parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10);
  ok(hpSindor === Math.max(0, 5 - d.vysledek.zraneni), 'životy Sindora v databázi odpovídají: ' + hpSindor);
  console.log('   výsledek:', JSON.stringify(d.vysledek));
  ok(d.vysledek.vyrazen === true, 'Sindor (5 životů, mez vyřazení 1) je po zásahu vyřazený');
  const pl = sqlVal("select payload from svet_udalosti where typ='souboj_vysledek' order by id desc limit 1");
  ok(!/utocnost/.test(pl) && /"utok":18/.test(pl), 'veřejná událost výsledku nenese útočnost zbraně: ' + pl.slice(0, 120));
  const plHod = sqlVal("select payload from svet_udalosti where typ='vyzva_hod' and payload like '%\"druh\":\"utok\"%' order by id desc limit 1");
  ok(!/"cislo"|"opravy"|"zaklad"/.test(plHod), 'událost hodu nestvůry nenese rozpis čísla: ' + plHod.slice(0, 120));
  // vyřazený tvor tah přeskočí
  d = await api(pj, 'tah_konec', { mapa_id: MAPA });
  st = await stav(pj);
  ok(d.ok && st.kolo.aktivni_token_id !== sindorB, 'po konci tahu goblina Sindor (vyřazený) tah nedostane: aktivní ' + st.kolo.aktivni_token_id);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindorB, druh: 'cekani' });
  ok(d.s === 422, 'vyřazený hráč nemůže jednat: ' + d.error);

  // ===================== C: útok zezadu (z boku) a zrušení PJ =====================
  await uklid();
  const sindorC = await pridej('postava', 13, 10);
  const gobC = await pridej('nestvura', 14, 10);
  await api(pj, 'token_uprava', { token_id: gobC, zmeny: { smer: 180 } });
  await api(pj, 'token_uprava', { token_id: sindorC, zmeny: { smer: 180 } });   // Sindor se dívá pryč od goblina
  await boj(gobC, [sindorC]);
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gobC, druh: 'utok', cil_token_id: sindorC });
  const vC = d.vyzva_id;
  await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vC, zaklad: 4 });
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vC, hody: [2] });
  const obrC = (await vyzvy(hr)).find(x => x.druh === 'obrana');
  ok(obrC && obrC.strana === 'bok', 'Sindor je goblinovi zády -> strana útoku "bok"');
  await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obrC.id, volba: 'branit' });
  let oc = (await vyzvy(hr)).find(x => x.druh === 'obrana');
  ok(oc.opravy.some(o => o.hodnota === -4), 'bránit se z boku/zezadu: −4 (+ −3 bez zbraně): ' + JSON.stringify(oc.opravy));
  await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: obrC.id, volba: 'nebranit' });
  oc = (await vyzvy(hr)).find(x => x.druh === 'obrana');
  ok(oc.opravy.length === 1 && oc.opravy[0].hodnota === -6, 'nebránit se z boku/zezadu: dohromady −6: ' + JSON.stringify(oc.opravy));
  d = await api(hr, 'vyzva_zrus', { mapa_id: MAPA });
  ok(d.s === 403, 'hráč nesmí zrušit hod');
  d = await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  ok(d.ok, 'PJ zruší rozehraný hod: ' + (d.error || ''));
  ok((await vyzvy(hr)).length === 0, 'po zrušení nezůstala žádná výzva');
  ok(parseInt(sqlVal('select aktualni_hp from postavy where id=2'), 10) === 5, 'Sindor nepřišel o žádné životy');
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: gobC, druh: 'cekani' });
  ok(d.ok, 'po zrušení se zase dá jednat');


  // ===================== D: čtvrtý hex sprintu = hod proti pasti =====================
  const efektBeh = () => parseInt(sqlVal("select count(*) from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev='Běh na krátké vzdálenosti' and ae.typ_entity='postava' and ae.entita_id=2"), 10);
  const cestaVychod = (c0, r0, n) => Array.from({ length: n }, (_, i) => ({ col: c0 + 1 + i, row: r0, smer: 0 }));
  let sTrvaly = null;   // token Sindora zůstává, aby se výzva s hodem proti pasti (FK na token) nesmazala
  async function priprava(sCol, zachovat) {
    if (zachovat && sTrvaly) {
      await api(pj, 'souboj_konec', { mapa_id: MAPA });
      for (const t of (await stav(pj)).tokeny) if (t.id !== sTrvaly) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
      sql(`update tokeny set x=${stred(sCol, 10).x}, y=${stred(sCol, 10).y}, smer=0 where id=${sTrvaly}`);
    } else {
      await uklid();
    }
    sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set unava=0 where id=2");
    const S = zachovat && sTrvaly ? sTrvaly : await pridej('postava', sCol, 10);
    sTrvaly = S;
    await api(pj, 'token_uprava', { token_id: S, zmeny: { smer: 0 } });
    const X = await pridej('nestvura', 24, 14);
    await boj(S, [X]);
    return { S, X };
  }
  let k = await priprava(5);
  const akceZbyva = (id) => parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${id}`), 10);
  let a0 = akceZbyva(k.S);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 5) });
  ok(d.s === 422, 'pět hexů nejde ani s hodem: ' + d.error);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 4), predpokladej_beh: true });
  ok(d.ok && d.ceka_na_hod && d.vyzva_id, 'čtyři hexy bez dovednosti založí hod proti pasti (a pokus obejít to polem v těle nepomůže): ' + (d.error || ''));
  const vPast = d.vyzva_id;
  let t0 = await tok(k.S);
  ok(Math.abs(t0.x - stred(5, 10).x) <= 1, 'token se zatím nepohnul');
  ok(akceZbyva(k.S) === a0, 'akce se zatím neutratila');
  let vp = (await vyzvy(pj))[0];
  ok(vp.druh === 'past' && vp.nebezpecnost === null && vp.smi_hodit === false && vp.zaklad === -1, 'PJ vidí past, obtížnost zatím není nastavená, bonus síly −1: ' + JSON.stringify({ n: vp.nebezpecnost, z: vp.zaklad, h: vp.smi_hodit }));
  let vph = (await vyzvy(hr))[0];
  ok(vph.druh === 'past' && vph.nebezpecnost === null && vph.nebezpecnost_nastavena === false && vph.smi_hodit === false, 'hráč past vidí, ale nesmí hodit, dokud PJ nenastaví obtížnost');
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vPast, hody: [10] });
  ok(d.s === 422 && /obtížnost/.test(d.error), 'bez obtížnosti nejde hodit: ' + d.error);
  d = await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vPast, nebezpecnost: 1 });
  ok(d.s === 403, 'hráč obtížnost měnit nesmí');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vPast, nebezpecnost: 7 });
  ok(d.ok, 'PJ nastaví obtížnost 7');
  vph = (await vyzvy(hr))[0];
  ok(vph.nebezpecnost === null && vph.nebezpecnost_nastavena === true && vph.smi_hodit === true, 'hráč obtížnost nevidí (jen že je nastavená) a smí hodit');
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vPast, hody: [11] });
  ok(d.s === 422, 'k10 nemá 11: ' + d.error);
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vPast, hody: [9] });
  ok(d.ok && d.vysledek && d.vysledek.uspech === true, 'hod 9 − 1 = 8 ≥ 7: úspěch, pohyb se provede: ' + (d.error || JSON.stringify(d.vysledek && { u: d.vysledek.uspech })));
  t0 = await tok(k.S);
  ok(Math.abs(t0.x - stred(9, 10).x) <= 1, 'Sindor stojí o čtyři hexy dál (9,10): ' + t0.x);
  ok(efektBeh() === 1, 'Sindor má efekt Běh na krátké vzdálenosti');
  ok(akceZbyva(k.S) === a0 - 1, 'pohyb stál jednu akci');
  ok(parseInt(sqlVal('select unava from postavy where id=2'), 10) === 1, 'sprint stál bod únavy');
  ok((await vyzvy(hr)).length === 0, 'po hodu nezůstala žádná výzva');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(9, 10, 4) });
  ok(d.ok && !d.ceka_na_hod && !d.vyzva_id, 's dovedností je čtvrtý hex hned, bez hodu: ' + (d.error || ''));
  // neúspěch: obtížnost se předvyplní z minula
  k = await priprava(5, true);
  a0 = akceZbyva(k.S);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 4) });
  vp = (await vyzvy(pj))[0];
  ok(vp.nebezpecnost === 7, 'obtížnost „střední“ se předvyplní z minulého hodu: ' + vp.nebezpecnost);
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vp.id, hody: [5] });
  ok(d.ok && d.vysledek.uspech === false, 'hod 5 − 1 = 4 < 7: neúspěch');
  t0 = await tok(k.S);
  ok(Math.abs(t0.x - stred(5, 10).x) <= 1 && efektBeh() === 0 && akceZbyva(k.S) === a0, 'nic se nepohnulo, žádný efekt, akce neutracena');
  ok((await vyzvy(hr)).length === 0, 'po neúspěchu nezůstala žádná výzva');
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 3) });
  ok(d.ok, 'sprint o tři hexy po neúspěchu jde: ' + (d.error || ''));
  // PJ zruší past
  k = await priprava(5, true);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 4) });
  d = await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  t0 = await tok(k.S);
  ok(d.ok && Math.abs(t0.x - stred(5, 10).x) <= 1 && akceZbyva(k.S) === a0, 'PJ past zruší: nic se neprovede, akce zůstává');

  // ===================== E: rozběh (HB) =====================
  async function pripravaRozbeh() {
    await uklid();
    sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set unava=0 where id=2");
    const S = await pridej('postava', 5, 10);
    await api(pj, 'token_uprava', { token_id: S, zmeny: { smer: 0 } });
    const X = await pridej('nestvura', 12, 10);
    await api(pj, 'token_uprava', { token_id: X, zmeny: { smer: 180 } });
    await boj(S, [X]);
    return { S, X };
  }
  const opravyUtoku = async () => ((await vyzvy(pj)).find(x => x.druh === 'utok') || { opravy: [] }).opravy.map(o => o.nazev).join('|');
  k = await pripravaRozbeh();
  global.ZACHOVAT_VYZVU = true;
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 3) });
  ok(d.ok, 'sprint 1');
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint_utok', cesta: cestaVychod(8, 10, 3), cil_token_id: k.X });
  ok(d.ok && d.vyzva_id, 'sprint 2 a útok: ' + (d.error || ''));
  ok(/rozběh/.test(await opravyUtoku()), 'po dvou sprintech za sebou má útok +1 za rozběh: ' + await opravyUtoku());
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  k = await pripravaRozbeh();
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 3) });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(8, 10, 3) });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'utok', cil_token_id: k.X });
  ok(d.ok && d.vyzva_id && /rozběh/.test(await opravyUtoku()), 'dva sprinty a pak samostatný útok: rozběh platí: ' + await opravyUtoku());
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  k = await pripravaRozbeh();
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint', cesta: cestaVychod(5, 10, 3) });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'krok', cesta: cestaVychod(8, 10, 1) });
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint_utok', cesta: cestaVychod(9, 10, 2), cil_token_id: k.X });
  ok(d.ok && d.vyzva_id && !/rozběh/.test(await opravyUtoku()), 'sprint, krok, sprint a útok: bez rozběhu (přerušená řada): ' + await opravyUtoku());
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  k = await pripravaRozbeh();
  d = await api(pj, 'tah_akce', { mapa_id: MAPA, token_id: k.S, druh: 'sprint_utok', cesta: cestaVychod(5, 10, 3).concat([]), cil_token_id: k.X });
  ok(d.error || !/rozběh/.test(await opravyUtoku()), 'jediný sprint a útok: bez rozběhu: ' + (d.error || await opravyUtoku()));
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });
  global.ZACHOVAT_VYZVU = false;

  await uklid();
  sql("delete from aktivni_efekty where typ_entity='postava' and entita_id=2; update postavy set unava=0 where id=2");
  console.log(chyb ? '\nCHYB: ' + chyb : '\nVŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})();
