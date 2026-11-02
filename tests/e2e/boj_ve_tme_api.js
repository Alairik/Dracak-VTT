// Boj ve tmě (h1613 b10738, lupič h630 b5663): na mapě „Tma“ má hráčova postava, která protivníka nevidí, postih −5 k hodu na útok i na obranu; PJ ho přepne
// (−4 tuší, kde stojí / −2 noční vidění / žádný). Zasáhne-li útočník s postihem, zranění se počítá, jako by postih neměl. Za nestvůry se postih sám nenavrhuje.
// Mapa 2 (hex). Po resetu dat: mysql dracak < ../fixture/seed.sql; node boj_ve_tme_api.js
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
  const vyzvy = async (page) => (await stav(page)).vyzvy;
  const hpGob = (inst) => parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${inst}`), 10);

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql('delete from souboj_vyzva; delete from mlha_valky where mapa_id=' + MAPA + '; update postavy set aktualni_hp=5, max_hp=5 where id=2');
  const pridej = async (typ, col, row) => (await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: stred(col, row).x, y: stred(col, row).y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }))).token_id;
  const sindor = await pridej('postava', 13, 10);
  const gob = await pridej('nestvura', 14, 10);
  const gobInst = parseInt(sqlVal(`select entita_id from tokeny where id=${gob}`), 10);
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${gobInst}`);
  await api(pj, 'token_uprava', { token_id: gob, zmeny: { smer: 180 } });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  for (let i = 0; i < 2; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: sindor, op: 'delta', delta: 9 });

  // --- mapa „Světlo“: žádný postih za tmu ---
  let d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  ok(d.ok && d.vyzva_id, 'útok na mapě „Světlo“ založí výzvu');
  let vy = (await vyzvy(hr))[0];
  ok(!vy.tma && !vy.opravy.some(o => /tma|nevid/i.test(o.nazev)), 'na mapě „Světlo“ není žádný postih za tmu');
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });

  // --- mapa „Tma“, bez světla: hráč goblina nevidí ---
  sql(`update mapy set osvetleni='tma', mlha_aktivni=1 where id=${MAPA}`);
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  ok(d.ok && d.vyzva_id, 'útok ve tmě založí výzvu: ' + (d.error || ''));
  const vUtok = d.vyzva_id;
  vy = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(vy.tma === 'nevidi' && vy.opravy.some(o => o.hodnota === -5), 'hráč nevidí cíl: postih −5 k útoku se navrhne sám (' + vy.tma + ')');
  ok(vy.cislo === vy.zaklad - 5, 'číslo útoku je ÚČ − 5: ' + vy.cislo);
  ok(/nevidí cíl/.test((vy.upozorneni || []).join(' ')), 'okno vysvětlí postih a upozorní na ztrátu iniciativy');

  // --- přepínání (jen PJ) ---
  d = await api(hr, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, tma: null });
  ok(d.s === 403, 'hráč si postih za tmu odebrat nesmí (403)');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, tma: 'tusi' });
  vy = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(d.ok && vy.opravy.some(o => o.hodnota === -4) && !vy.opravy.some(o => o.hodnota === -5), 'PJ přepne na „tuší, kde stojí“: −4');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, tma: 'nocni' });
  vy = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(d.ok && vy.opravy.some(o => o.hodnota === -2), 'PJ přepne na noční vidění: −2');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, tma: null });
  vy = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(d.ok && !vy.opravy.some(o => o.hodnota < 0), 'PJ postih zruší (vidí)');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, tma: 'hodne' });
  ok(d.s === 422, 'neznámá volba vidění se odmítne (422)');
  d = await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vUtok, tma: 'nevidi' });
  ok(d.ok, 'PJ vrátí −5');

  // --- zranění bez postihu za tmu ---
  // útok: hody [6,6,2] = 14 + (ÚČ − 5); obrana goblina: OČ 5 + hod 2 = 7 → zásah; zranění = útok − 7 + útočnost + 5 (bez postihu za tmu)
  vy = (await vyzvy(hr)).find(x => x.druh === 'utok');
  const ucUtok = vy.zaklad, utocnost = vy.utocnost;
  const utokCelkem = 14 + ucUtok - 5;
  d = await api(hr, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vUtok, hody: [6, 6, 2] });
  ok(d.ok && d.celkem === utokCelkem, 'útok hozen: 14 + (ÚČ ' + ucUtok + ' − 5) = ' + utokCelkem + ': ' + (d.error || d.celkem));
  const vObr = d.dalsi_vyzva_id;
  await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, zaklad: 5 });
  await api(pj, 'vyzva_uprava', { mapa_id: MAPA, vyzva_id: vObr, volba: 'branit' });
  const obr = (await vyzvy(pj)).find(x => x.druh === 'obrana');
  ok(!obr.tma, 'nestvůra v obraně nemá žádný automatický postih za tmu (rozhoduje PJ)');
  d = await api(pj, 'vyzva_hod', { mapa_id: MAPA, vyzva_id: vObr, hody: [2] });
  ok(d.ok && d.vysledek && d.vysledek.zasah === true && d.vysledek.obrana === 7 && d.vysledek.utok === utokCelkem, 'zásah: útok ' + utokCelkem + ' proti obraně 7: ' + (d.error || JSON.stringify(d.vysledek)));
  const cekane = utokCelkem - 7 + utocnost + 5;
  ok(d.vysledek.zraneni === cekane && d.vysledek.tma_bonus === 5, 'zranění ' + cekane + ' = ' + utokCelkem + ' − 7 + útočnost ' + utocnost + ' + 5 (bez postihu za tmu): ' + d.vysledek.zraneni + ' / bonus ' + d.vysledek.tma_bonus);
  ok(hpGob(gobInst) === Math.max(0, 20 - cekane), 'goblin má 20 − ' + cekane + ' životů: ' + hpGob(gobInst));

  // --- se světlem hráč cíl vidí: postih zmizí ---
  await api(pj, 'token_uprava', { token_id: sindor, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  d = await api(hr, 'tah_akce', { mapa_id: MAPA, token_id: sindor, druh: 'utok', cil_token_id: gob });
  ok(d.ok && d.vyzva_id, 'další útok už se světlem: ' + (d.error || ''));
  vy = (await vyzvy(hr)).find(x => x.druh === 'utok');
  ok(!vy.tma && !vy.opravy.some(o => o.hodnota === -5), 'se světlem hráč goblina vidí: žádný postih');
  await api(pj, 'vyzva_zrus', { mapa_id: MAPA });

  // --- funkce přímo: kdo koho nevidí ---
  const cli = (a, b) => require('child_process').execSync(`php -r 'require "${process.cwd().replace(/\/tests\/e2e$/, '')}/includes/vtt_videni.php"; $pdo = dracak_db(); $m = dracak_vtt_videni_mapa($pdo, ${MAPA}); echo var_export(dracak_vtt_videni_tma_volba($pdo, $m, ${a}, ${b}), true);'`).toString().trim();
  ok(cli(sindor, gob) === 'NULL', 'se světlem: hráč vidí goblina (funkce vrací null)');
  await api(pj, 'token_uprava', { token_id: sindor, zmeny: { svetlo: null } });
  ok(cli(sindor, gob) === "'nevidi'", 'bez světla: hráč goblina nevidí (nevidi)');
  ok(cli(gob, sindor) === 'NULL', 'goblin jako pozorovatel: nestvůra nemá automatický postih, rozhoduje PJ');
  // --- lupič s nočním viděním (b5663): v noci −2 místo −5, v absolutní tmě ani v kouzelné tmě nepomáhá ---
  sql(`update tokeny set x=${stred(21, 10).x}, y=${stred(21, 10).y} where id=${gob}; update tokeny set videni_noc_sahy=1 where id=${sindor}`);   // goblin 8 hexů daleko, mimo dosah 1 sáhu
  ok(cli(sindor, gob) === "'nevidi'", 'v úplné tmě noční vidění nepomáhá: −5 (nevidi)');
  sql(`update mapy set osvetleni='noc' where id=${MAPA}`);
  ok(cli(sindor, gob) === "'nocni'", 'v noci lupič cíl mimo dosah nevidí, ale bojuje s postihem −2 (nocni)');
  sql(`update tokeny set videni_noc_sahy=60 where id=${sindor}`);
  ok(cli(sindor, gob) === 'NULL', 'v noci cíl v dosahu nočního vidění lupič vidí: žádný postih');
  const zonaId = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', nazev: 'Tma', x: stred(21, 10).x, y: stred(21, 10).y })).token_id;
  await api(pj, 'token_uprava', { token_id: zonaId, zmeny: { svetlo: 3, svetlo_typ: 'tma', viditelny: false } });
  ok(cli(sindor, gob) === "'nevidi'", 'cíl uvnitř kouzelné tmy (Tma) lupič nevidí a noční vidění mu nepomáhá: −5');
  await api(pj, 'token_smazat', { token_id: zonaId, mapa_id: MAPA });
  sql(`update tokeny set videni_noc_sahy=NULL where id=${sindor}`);
  ok(cli(sindor, gob) === "'nevidi'", 'bez nočního vidění je v noci postih −5');
  sql(`update mapy set osvetleni='den' where id=${MAPA}`);
  ok(cli(sindor, gob) === 'NULL', 'na mapě „Světlo“ se nic nezjišťuje');

  await api(pj, 'souboj_konec', { mapa_id: MAPA });
  for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  sql('delete from souboj_vyzva; update postavy set aktualni_hp=5, max_hp=5 where id=2');
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
