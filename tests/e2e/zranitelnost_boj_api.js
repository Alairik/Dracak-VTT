// Zranitelnost nestvůry v boji (content/pravidla-bestiar.html b17729–b17753).
//
// Nestvůra je zranitelná jen těmi skupinami útoků, které má v bestiáři vyjmenované; za písmenem může stát
// číslo, kterým se násobí odebrané životy (b17753, „zaokrouhluj nahoru“). Fyzický útok, který hra umí
// vyhodnotit, spadá vždy pod skupinu B „obyčejná zbraň“ — podle b17731 sem patří i rána beze zbraně,
// pád z výšky a pařáty. Data (1559 vazeb) v databázi byla odjakživa, boj s nimi ale nepočítal.
//
// Co se ověřuje:
//   * násobek u B se v zranění opravdu projeví a zaokrouhluje nahoru,
//   * když nestvůra B v seznamu nemá, zranění se NEnuluje (hra nepozná kouzelnou ani stříbrnou zbraň),
//     ale PJ dostane varování,
//   * nestvůra bez vyplněných zranitelností se chová přesně jako dřív (žádná změna, žádné hlášení),
//   * hráč se z veřejné události nedozví, čím je nestvůra (ne)zranitelná (b17728).
//
// Mapa 2 (hex). Po resetu dat: mysql dracak < ../fixture/seed.sql; node zranitelnost_boj_api.js
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
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });

  // Nestvůra, se kterou se bojuje, i kódy zranitelnosti si test určuje sám — nespoléhá na pořadí v tabulce.
  const druh = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10) || 41;
  const kodB = parseInt(sqlVal("select id from kody_zranitelnosti where kod='B'"), 10);
  const kodC = parseInt(sqlVal("select id from kody_zranitelnosti where kod='C'"), 10);
  ok(kodB > 0 && kodC > 0, 'kódy zranitelnosti B a C jsou v číselníku: B=' + kodB + ' C=' + kodC);
  const puvodni = require('child_process').execSync(
    `mysql --default-character-set=utf8mb4 ${DB} -N -e "select concat(kod_zranitelnosti_id,':',coalesce(modifikator,'')) from nestvura_zranitelnosti where nestvura_id=${druh}"`
  ).toString().trim().split('\n').filter(Boolean);

  const pripravBoj = async () => {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql('delete from souboj_vyzva; update postavy set aktualni_hp=5, max_hp=5 where id=2');
    const pridej = async (typ, col, row) => (await api(pj, 'token_pridat', Object.assign(
      { mapa_id: MAPA, typ_entity: typ, x: stred(col, row).x, y: stred(col, row).y },
      typ === 'nestvura' ? { nestvura_id: druh } : { postava_id: 2 }))).token_id;
    const sindor = await pridej('postava', 13, 10);
    const gob = await pridej('nestvura', 14, 10);
    const inst = parseInt(sqlVal(`select entita_id from tokeny where id=${gob}`), 10);
    sql(`update nestvura_instance set aktualni_hp=40, max_hp=40 where id=${inst}`);
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
    await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });
    return { sindor, gob, inst };
  };

  // Jeden útok s pevnými hody, ať je zranění předvídatelné. Vrací výsledek, jak ho vidí PJ.
  const utok = async (t) => {
    let d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: t.sindor, druh: 'utok', cil_token_id: t.gob });
    const vUtok = d.vyzva_id;
    d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vUtok, hody: [6, 6, 2] });
    const vObr = d.dalsi_vyzva_id;
    await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, zaklad: 0 });
    await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, volba: 'nebranit' });
    d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vObr, hody: [1] });
    return d;
  };

  try {
    // --- 1) nestvůra bez vyplněných zranitelností: beze změny, bez hlášení ---
    sql(`delete from nestvura_zranitelnosti where nestvura_id=${druh}`);
    let t = await pripravBoj();
    let d = await utok(t);
    ok(d.ok && d.vysledek && d.vysledek.zasah === true, 'útok prošel: ' + (d.error || JSON.stringify(d.vysledek || {}).slice(0, 90)));
    const zakladniZraneni = d.vysledek.zraneni;
    ok(zakladniZraneni > 1, 'základní zranění je aspoň 2, aby šel poznat půlící násobek: ' + zakladniZraneni);
    ok(!d.vysledek.zranitelnost, 'u nestvůry bez vyplněných zranitelností se nic nehlásí');

    // --- 2) B s násobkem ½: zranění se půlí a zaokrouhluje nahoru ---
    sql(`insert into nestvura_zranitelnosti (nestvura_id, kod_zranitelnosti_id, modifikator) values (${druh}, ${kodB}, '1/2')`);
    t = await pripravBoj();
    d = await utok(t);
    const cekane = Math.ceil(zakladniZraneni / 2);
    ok(d.vysledek.zraneni === cekane, 'násobek ½ se použil a zaokrouhlil nahoru: ' + zakladniZraneni + ' → ' + d.vysledek.zraneni + ' (čekáno ' + cekane + ')');
    ok(!!d.vysledek.zranitelnost && d.vysledek.zranitelnost.kod === 'B' && /násobek/.test(d.vysledek.zranitelnost.text),
      'PJ dostane vysvětlení: ' + ((d.vysledek.zranitelnost || {}).text || '—'));
    ok(parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${t.inst}`), 10) === 40 - cekane,
      'v databázi ubyly opravdu jen zmenšené životy');

    // --- 3) B s násobkem 2: zranění se zdvojnásobí ---
    sql(`update nestvura_zranitelnosti set modifikator='2' where nestvura_id=${druh} and kod_zranitelnosti_id=${kodB}`);
    t = await pripravBoj();
    d = await utok(t);
    ok(d.vysledek.zraneni === zakladniZraneni * 2, 'násobek 2 zranění zdvojnásobil: ' + zakladniZraneni + ' → ' + d.vysledek.zraneni);

    // --- 4) B v seznamu chybí: zranění zůstává, ale PJ dostane varování ---
    sql(`delete from nestvura_zranitelnosti where nestvura_id=${druh}`);
    sql(`insert into nestvura_zranitelnosti (nestvura_id, kod_zranitelnosti_id, modifikator) values (${druh}, ${kodC}, NULL)`);
    t = await pripravBoj();
    d = await utok(t);
    ok(d.vysledek.zraneni === zakladniZraneni, 'bez skupiny B se zranění samo nenuluje (hra nepozná kouzelnou zbraň): ' + d.vysledek.zraneni);
    ok(!!d.vysledek.zranitelnost && d.vysledek.zranitelnost.nasobek === null && /NENÍ/.test(d.vysledek.zranitelnost.text),
      'PJ dostane varování: ' + ((d.vysledek.zranitelnost || {}).text || '—').slice(0, 80));

    // --- 5) hráč se z veřejné události nedozví nic (b17728) ---
    const udalosti = await hr.evaluate(async (m) => {
      const r = await fetch('api/mapa_stav.php?mapa_id=' + m + '&od=0', { cache: 'no-store' });
      return JSON.stringify(await r.json());
    }, MAPA);
    ok(!/zranitelnost/.test(udalosti), 'hráči se čím je nestvůra zranitelná neposílá');
  } finally {
    // vrátit zranitelnosti goblina do původního stavu, ať na sobě sady nezávisí
    sql(`delete from nestvura_zranitelnosti where nestvura_id=${druh}`);
    for (const r of puvodni) {
      const [kod, mod] = r.split(':');
      sql(`insert ignore into nestvura_zranitelnosti (nestvura_id, kod_zranitelnosti_id, modifikator) values (${druh}, ${parseInt(kod, 10)}, ${mod ? `'${mod}'` : 'NULL'})`);
    }
    await browser.close();
  }
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
