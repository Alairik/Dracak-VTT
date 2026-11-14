// Stínové životy (migrace 0121; hrac Rvačka h1654 b10988): rána beze zbraně (pěst) působí jen stínové zranění —
// NESNIŽUJE fyzické životy (tvora nezabije), ale přičítá se do stin_hp, do vyřazení se počítá spolu s fyzickým
// a po skončení souboje se stin_hp srovná zpět na 0.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node stin_api.js
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
  const denik = async (tokenId) => (await pj.request.get(BASE + '/hra/api/nestvura_denik.php?mapa_id=' + MAPA + '&token_id=' + tokenId)).json();
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(700);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const cs = Math.sqrt(3) * g.px, rs = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * cs + ((r & 1) ? cs / 2 : 0)), y: Math.round(r * rs) });
  const gobDruh = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10);
  const gobHp = (gob) => parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=(select entita_id from tokeny where id=${gob})`), 10);
  const gobStin = (gob) => parseInt(sqlVal(`select stin_hp from nestvura_instance where id=(select entita_id from tokeny where id=${gob})`), 10);

  // Sindor (postava 2) nic nedrží → útočí pěstí (stínové). Goblin má 30/30 životů.
  const utokPesti = async () => {
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
    sql(`update kolo_iniciativa set akce_zbyvajici=greatest(akce_zbyvajici, 1) where token_id=${sin}`);
    await post('tah_akce', { token_id: sin, druh: 'utok', cil_token_id: gob });
    const vU = ((await stav()).vyzvy || []).find((v) => v.druh === 'utok');
    return { gob, sin, vU };
  };

  // ===== 1) útok pěstí = stínové zranění: fyzické životy beze změny, stin_hp roste =====
  let s = await utokPesti();
  ok(!!s.vU && s.vU.stinove === true, 'výzva útoku pěstí nese příznak „stínové“: ' + (s.vU ? s.vU.stinove : 'bez výzvy'));
  await post('vyzva_hod', { vyzva_id: s.vU.id, hody: [5] });
  let vO = ((await stav()).vyzvy || []).find((v) => v.druh === 'obrana');
  await post('vyzva_uprava', { vyzva_id: vO.id, volba: 'nebranit', zaklad: 0 });
  const vysledek = await post('vyzva_hod', { vyzva_id: vO.id, hody: [1] });
  ok(vysledek.vysledek && vysledek.vysledek.zasah === true, 'zásah padl (útok ≥ obrana)');
  ok(vysledek.vysledek && vysledek.vysledek.stinove === true, 'výsledek je označený jako stínové zranění');
  ok(gobHp(s.gob) === 30, 'fyzické životy goblina se nezměnily (zůstalo 30): ' + gobHp(s.gob));
  ok(gobStin(s.gob) > 0, 'stínové zranění se zapsalo do stin_hp: ' + gobStin(s.gob));
  ok(vysledek.vysledek && vysledek.vysledek.stin_nove === gobStin(s.gob), 'výsledek hlásí stejné stínové, jaké je v DB: ' + (vysledek.vysledek || {}).stin_nove);

  // mapa_stav posílá stin_hp tokenu (pro raid panel i okno tvora)
  const tok = (await stav()).tokeny.find((t) => t.id === s.gob);
  ok(tok && tok.stin_hp === gobStin(s.gob), 'mapa_stav posílá stin_hp tokenu: ' + (tok ? tok.stin_hp : '—'));

  // ===== 2) vyřazení počítá stínové spolu s fyzickým, ale fyzické životy a smrt ne =====
  let d = await denik(s.gob);
  ok(d.bojeschopnost && d.bojeschopnost.vyrazena === false, 'při malém stínovém goblin ještě není vyřazený');
  sql(`update nestvura_instance set stin_hp=30 where id=(select entita_id from tokeny where id=${s.gob})`);
  d = await denik(s.gob);
  ok(d.bojeschopnost && d.bojeschopnost.vyrazena === true, 'stínové zranění až na mez vyřazení tvora vyřadí (bez poklesu fyzických životů)');
  ok(gobHp(s.gob) === 30, 'fyzické životy jsou pořád 30 — stínové zranění nezabíjí');

  // ===== 3) po skončení souboje se stínové zranění zahojí (stin_hp = 0) =====
  await post('souboj_konec', {});
  ok(gobStin(s.gob) === 0, 'po konci souboje je stin_hp zpět na 0: ' + gobStin(s.gob));
  ok(gobHp(s.gob) === 30, 'fyzické životy po konci souboje beze změny');

  for (const t of (await stav()).tokeny) await post('token_smazat', { token_id: t.id });
  sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2');
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
