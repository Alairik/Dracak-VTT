const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo, w = 1280) {
  const page = await (await browser.newContext({ viewport: { width: w, height: 900 } })).newPage();
  page.on('dialog', d => d.accept('2'));
  page.on('pageerror', e => { chyb++; console.log('CHYBA pageerror ' + e.message); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(800);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  let pj = await login(browser, 'test@local', 'test1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
    sql("delete from souboj_vyzva; delete from aktivni_efekty where typ_entity='postava' and entita_id=2");
  }
  async function pridej(typ, col, row) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: 41 } : { postava_id: 2 }));
    return d.token_id;
  }
  await uklid();
  const sindor = await pridej('postava', 13, 10);
  const gob = await pridej('nestvura', 14, 10);
  const gobInst = parseInt(sqlVal(`select entita_id from tokeny where id=${gob}`), 10);
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${gobInst}`);
  await pj.reload(); await pj.waitForTimeout(900);

  // Panel Spravovat: výběr efektu ukazuje značky a výchozí trvání
  const klik = async (id) => { const b = await pj.$eval('.vtt-token[data-id="' + id + '"] .puck', e => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; }); await pj.mouse.click(b.x, b.y); await pj.waitForTimeout(900); };
  await klik(sindor);
  ok(await pj.$eval('#panel-spravovat', e => e.classList.contains('open')), 'klik na Sindora otevře panel');
  const jePanel = await pj.evaluate(() => !!document.getElementById('efektSelect'));
  ok(jePanel, 'v panelu je nabídka efektů');
  const moznosti = await pj.evaluate(() => [...document.querySelectorAll('#efektSelect option')].map(o => o.textContent.trim()));
  ok(moznosti.some(t => /^⚙ Rychlost \(lektvar\)/.test(t)), 'Rychlost (lektvar) má značku ⚙: ' + (moznosti.find(t => /Rychlost \(lektvar\)/.test(t)) || ''));
  ok(moznosti.some(t => /^✋ /.test(t)) && moznosti.some(t => /^◐ /.test(t)) && moznosti.some(t => /^❓ /.test(t)), 'všechny druhy značek v nabídce');
  ok(!moznosti.some(t => /Poškození \(|Léčení \(/.test(t.replace(/^.\s/, ''))), 'Poškození a Léčení v nabídce nejsou');
  ok(await pj.evaluate(() => !!document.getElementById('efektNapoveda')), 'pod nabídkou je legenda značek');
  await pj.selectOption('#efektSelect', { label: moznosti.find(t => /Rychlost \(lektvar\)/.test(t)) });
  const ph = await pj.evaluate(() => document.getElementById('efektKola').placeholder);
  ok(/5/.test(ph), 'pole kol ukazuje výchozí trvání: ' + ph);
  await pj.click('#efektBtn');
  await pj.waitForTimeout(900);
  const fx = await pj.evaluate((id) => { const t = window.__tokeny ? null : null; const el = [...document.querySelectorAll('.fx')].map(e => e.textContent + '|' + e.title); return el; }, sindor);
  ok(fx.some(t => /Rychlost \(lektvar\) \(5 kol\)/.test(t)), 'odznak na tokenu ukazuje trvání: ' + JSON.stringify(fx));
  ok(fx.some(t => /zdvojnásobí akce/.test(t)), 'tooltip odznaku říká, co efekt dělá');
  const radky = await pj.evaluate(() => [...document.querySelectorAll('#spravovatEfekty .efekt-radek')].map(r => r.textContent));
  ok(radky.length === 1 && /Rychlost \(lektvar\)/.test(radky[0]), 'v panelu je seznam aktivních efektů: ' + JSON.stringify(radky));
  // Sundat ✕
  await pj.click('#spravovatEfekty .efekt-sundat');
  await pj.waitForTimeout(900);
  ok(parseInt(sqlVal("select count(*) from aktivni_efekty where typ_entity='postava' and entita_id=2"), 10) === 0, 'tlačítko ✕ efekt sundalo');
  const radky2 = await pj.evaluate(() => document.getElementById('spravovatEfekty').style.display);
  ok(radky2 === 'none', 'prázdný seznam efektů se schová');

  // Tik: krvácení goblina, karta „Hodit 1k4“
  const krv = parseInt(sqlVal("select id from efekty where nazev like 'Krvácení (sečná%'"), 10);
  await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'nestvura_instance', entita_id: gobInst, efekt_id: krv });
  await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [sindor, gob].map(t => ({ token_id: t })) });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [sindor], hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [gob], hod: 1 });
  await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
  for (let i = 0; i < 12; i++) { const k = (await stav(pj)).kolo; if (k.cislo_kola !== 1) break; await api(pj, 'tah_konec', { mapa_id: MAPA }); }
  await pj.waitForTimeout(2600);
  const karta = await pj.evaluate(() => { const k = document.querySelector('.vyzva-karta.vyzva-tik'); return k ? k.textContent : null; });
  ok(karta && /Krvácení/.test(karta) && /1k4/.test(karta), 'na obrazovce je karta tiku s „1k4“: ' + (karta || '').slice(0, 120));
  const maTlacitka = await pj.evaluate(() => [...document.querySelectorAll('.vyzva-karta.vyzva-tik button')].map(b => b.textContent));
  ok(maTlacitka.some(t => /Hodit 1k4/.test(t)) && maTlacitka.some(t => /Zapsat/.test(t)) && maTlacitka.includes('Zrušit'), 'tlačítka: Hodit 1k4, Zapsat vlastní hod, Zrušit: ' + JSON.stringify(maTlacitka));
  await pj.evaluate(() => { const i = document.querySelector('.vyzva-karta.vyzva-tik .vyzva-akce input.vyzva-cislo'); i.value = '3'; });
  await pj.evaluate(() => [...document.querySelectorAll('.vyzva-karta.vyzva-tik button')].find(b => /Zapsat/.test(b.textContent)).click());
  await pj.waitForTimeout(1200);
  ok(parseInt(sqlVal(`select aktualni_hp from nestvura_instance where id=${gobInst}`), 10) === 17, 'ručně zapsané 3 → goblin má 17 životů');
  const log = await pj.evaluate(() => document.getElementById('logList').textContent);
  ok(/Krvácení.*−3 životů/.test(log), 'log: řádek o tiku krvácení');
  await pj.screenshot({ path: process.env.SHOTS + '/efekty_tik.png' });

  // Hráč vidí efekt na své postavě v deníku
  await uklid();
  const rych = parseInt(sqlVal("select id from efekty where nazev='Rychlost (lektvar)'"), 10);
  sql(`insert into aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) values ('postava', 2, ${rych}, 4)`);
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  await hr.goto(BASE + '/hra/denik.php?id=2'); await hr.waitForTimeout(700);
  const dn = await hr.evaluate(() => { const u = document.querySelector('.dn-efekty'); return u ? u.textContent.replace(/\s+/g, ' ') : null; });
  ok(dn && /Rychlost \(lektvar\)/.test(dn) && /zdvojnásobí akce/.test(dn), 'deník: aktivní efekt s popisem mechaniky: ' + (dn || '').slice(0, 140));
  await uklid();
  await browser.close();
  console.log(chyb === 0 ? '\nVŠE OK' : '\nCHYB: ' + chyb);
  process.exit(chyb === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(2); });
