// Útok beze zbraně (Rvačka, hrac h1654, Tabulka rvačky b10982–b10985): postava, která nic nedrží, útočí pěstí
// a útočné číslo se spočítá samo = síla pěsti podle velikosti (A 2, B 3, C 4) + bonus za sílu. Dřív karta hodu
// chtěla číslo doplnit ručně („Postava nemá zbraň se spočítaným ÚČ“).
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node pest_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const CEP = 39;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
  const pj = await (await browser.newContext()).newPage();
  await pj.goto(BASE + '/index.php'); await pj.fill('#email', 'test@local'); await pj.fill('#password', 'test1234');
  await Promise.all([pj.waitForNavigation(), pj.click('button[type=submit]')]);
  const api = async (name, body) => (await pj.request.post(BASE + '/hra/api/' + name + '.php', { data: Object.assign({ mapa_id: MAPA }, body) })).json();
  const stav = async () => (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(700);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const cs = Math.sqrt(3) * g.px, rs = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * cs + ((r & 1) ? cs / 2 : 0)), y: Math.round(r * rs) });
  const gobDruh = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10);

  // Souboj: postava (id) stojí vedle goblina čelem k němu a je na tahu; vrátí výzvu k hodu na útok.
  const utok = async (postavaId) => {
    await api('souboj_konec', {});
    for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id });
    sql('delete from souboj_vyzva');
    const gob = (await api('token_pridat', { typ_entity: 'nestvura', nestvura_id: gobDruh, x: stred(10, 10).x, y: stred(10, 10).y })).token_id;
    const kdo = (await api('token_pridat', { typ_entity: 'postava', postava_id: postavaId, x: stred(11, 10).x, y: stred(11, 10).y })).token_id;
    await api('token_uprava', { token_id: kdo, zmeny: { smer: 180 } });
    await api('souboj_start', { ucastnici: [{ token_id: gob }, { token_id: kdo }] });
    await api('souboj_hod', { token_ids: [kdo], hod: 6, auto: true });
    await api('souboj_hod', { token_ids: [gob], hod: 1, auto: true });
    const r = await api('tah_akce', { token_id: kdo, druh: 'utok', cil_token_id: gob });
    if (r.error) console.log('   tah_akce: ' + r.error + ' | kolo: ' + JSON.stringify((await stav()).kolo).slice(0, 200));
    return { r, kdo, vyzva: ((await stav()).vyzvy || []).find((v) => v.druh === 'utok') };
  };

  // Očekávané číslo nezávisle na testovaném kódu: bonus za sílu = ÚČ Cepu z deníku − síla zbraně Cepu z katalogu.
  sql(`update postavy set drzena_zbran_id=${CEP} where id=2; update postava_predmety set u_sebe=1 where postava_id=2`);
  const tS = parseInt(sqlVal(`select id from tokeny where mapa_id=1 and typ_entity='postava' and entita_id=2`), 10);
  let mini = await (await pj.request.get(BASE + `/hra/api/postava_mini.php?mapa_id=1&token_id=${tS}`)).json();
  const bonusSily = mini.zbran.uc - parseInt(sqlVal(`select sila_zbrane from predmety where id=${CEP}`), 10);
  const velikost = sqlVal("select r.nazev from postavy p join rasy r on r.id=p.rasa_id where p.id=2");
  console.log('   Sindor: rasa ' + velikost + ', bonus za sílu ' + bonusSily + ', pěst podle karty ' + mini.pest);
  ok([2, 3, 4].includes(mini.pest - bonusSily), 'pěst = síla pěsti z Tabulky rvačky (2, 3 nebo 4 podle velikosti) + bonus za sílu: ' + mini.pest + ' = ' + (mini.pest - bonusSily) + ' + (' + bonusSily + ')');
  const ocekavanaPest = mini.pest;

  // ===== se zbraní v ruce se útočí zbraní; pěst je v nabídce =====
  let u = await utok(2);
  ok(u.vyzva && /Cep/.test(u.vyzva.zaklad_nazev) && u.vyzva.zaklad === mini.zbran.uc, 'se zbraní v ruce se útočí zbraní: ' + (u.vyzva ? u.vyzva.zaklad_nazev + ' ' + u.vyzva.zaklad : 'bez výzvy'));
  ok(u.vyzva.varianty.some((z) => z.nazev === 'pěst' && z.uc === ocekavanaPest), 'pěst je mezi možnostmi útoku: ' + u.vyzva.varianty.map((z) => z.nazev + ' ' + z.uc).join(', '));

  // ===== bez zbraně v ruce: pěst, číslo je spočítané =====
  sql('update postavy set drzena_zbran_id=NULL where id=2');
  u = await utok(2);
  ok(!!u.vyzva && u.vyzva.zaklad === ocekavanaPest, 'bez zbraně v ruce má hod útočné číslo spočítané samo: ' + (u.vyzva ? u.vyzva.zaklad : 'bez výzvy'));
  ok(u.vyzva.zaklad_nazev === 'ÚČ pěst', 'karta říká, čím se útočí: ' + u.vyzva.zaklad_nazev);
  ok(u.vyzva.smi_hodit === true && u.vyzva.cislo !== null, 'jde rovnou hodit, nic se nedoplňuje');
  ok(u.vyzva.utocnost === 0, 'útočnost pěsti je 0 (kniha ji neuvádí)');
  ok((u.vyzva.upozorneni || []).some((t) => /stínové/.test(t)), 'PJ vidí upozornění, že pěst působí jen stínové zranění');
  ok(!(u.vyzva.upozorneni || []).some((t) => /doplň útočné číslo|nedá spočítat/.test(t)), 'žádná výzva k ručnímu doplnění čísla');
  // jde přepnout na zbraň z inventáře a zpět
  let z = await api('vyzva_uprava', { vyzva_id: u.vyzva.id, zbran_id: CEP });
  let v2 = ((await stav()).vyzvy || []).find((v) => v.id === u.vyzva.id);
  ok(!z.error && /Cep/.test(v2.zaklad_nazev), 'na kartě jde přepnout na zbraň z inventáře: ' + v2.zaklad_nazev);
  z = await api('vyzva_uprava', { vyzva_id: u.vyzva.id, zbran_id: -1 });
  v2 = ((await stav()).vyzvy || []).find((v) => v.id === u.vyzva.id);
  ok(!z.error && v2.zaklad === ocekavanaPest, 'a zpět na pěst');
  mini = await (await pj.request.get(BASE + `/hra/api/postava_mini.php?mapa_id=1&token_id=${tS}`)).json();
  ok(mini.zbran === null && mini.pest === ocekavanaPest, 'malá karta postavy bez zbraně ukazuje ÚČ pěsti místo pomlčky');

  // ===== postava bez vyplněné síly: číslo se nehádá a karta řekne proč =====
  u = await utok(3);
  ok(!!u.vyzva && u.vyzva.zaklad === null, 'postava bez vyplněné síly: číslo se nehádá');
  ok((u.vyzva.upozorneni || []).some((t) => /sílu nebo velikost/.test(t)), 'karta řekne, co v deníku chybí: ' + (u.vyzva.upozorneni || []).join(' | ').slice(0, 160));

  await api('souboj_konec', {});
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id });
  sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2');
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
