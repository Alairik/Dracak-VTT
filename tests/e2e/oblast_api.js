// Export a import oblasti: mapa (nebo mapa pod pinem) a všechno pod ní se vyveze do ZIPu a dá přidat do jiného
// světa — s přečíslováním, bez postav a hráčů, bez katalogu pravidel (ten se dohledá jménem u příjemce).
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node oblast_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
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
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };

  sql("delete from svet where nazev='Cílový svět'");
  sql("delete from tokeny where typ_entity='nestvura_instance'; delete from nestvura_instance; delete from mapa_body");

  // hierarchie: mapa 1 → pin → mapa 2; na mapě 2 dvě nestvůry, zeď a vlastní token
  const bod = await api(pj, 'mapa_bod_pridat', { mapa_id: 1, nazev: 'Vstup do jeskyně', typ: 'prechod', x: 200, y: 200, cilova_mapa_id: 2, viditelny_hracum: 1 });
  ok(bod.s === 200, 'pin z mapy 1 na mapu 2: ' + bod.s);
  const gob = parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10);
  await api(pj, 'token_pridat', { mapa_id: 2, typ_entity: 'nestvura', nestvura_id: gob, x: 300, y: 300 });
  await api(pj, 'token_pridat', { mapa_id: 2, typ_entity: 'nestvura', nestvura_id: gob, x: 360, y: 300 });
  await api(pj, 'token_pridat', { mapa_id: 2, typ_entity: 'obecny', nazev: 'Sud', x: 420, y: 300 });
  await api(pj, 'zed_pridat', { mapa_id: 2, x1: 100, y1: 100, x2: 300, y2: 100, sirka_px: 6, blokuje_pohyb: true, blokuje_vystrel: true, viditelna_hracum: true });

  // --- práva ---
  let r = await hrac.request.get(BASE + '/hra/api/oblast_export.php?mapa_id=1&nahled=1');
  ok(r.status() === 403, 'hráč oblast nevyveze (403): ' + r.status());
  r = await pj.request.get(BASE + '/hra/api/oblast_export.php?mapa_id=999999&nahled=1');
  ok(r.status() === 404, 'neexistující mapa (404): ' + r.status());

  // --- náhled: bere mapu i všechno pod ní ---
  r = await pj.request.get(BASE + '/hra/api/oblast_export.php?mapa_id=1&nahled=1');
  let n = await r.json();
  ok(r.status() === 200 && n.souhrn.map === 2, 'oblast z mapy 1 bere i mapu pod pinem: ' + n.souhrn.map + ' mapy');
  // Počty se berou ze zdroje, ne natvrdo — seed má vlastní tokeny a zeď a jejich počet se může změnit.
  const tokenuZdroj = parseInt(sqlVal("select count(*) from tokeny where mapa_id in (1,2) and typ_entity<>'postava'"), 10);
  const zdiZdroj = parseInt(sqlVal('select count(*) from zdi where mapa_id in (1,2)'), 10);
  ok(n.souhrn.npc === 2 && n.souhrn.tokeny === tokenuZdroj, 'veze nestvůry i vlastní tokeny: ' + n.souhrn.npc + ' NPC / ' + n.souhrn.tokeny + ' tokenů');

  // export z pinu dá tu samou oblast jako export z mapy pod ním (bez výchozí mapy)
  r = await pj.request.get(BASE + '/hra/api/oblast_export.php?bod_id=' + bod.id + '&nahled=1');
  n = await r.json();
  ok(n.souhrn.map === 1, 'export z pinu vezme jen mapu pod ním: ' + n.souhrn.map);

  // --- ZIP ---
  r = await pj.request.get(BASE + '/hra/api/oblast_export.php?mapa_id=1');
  const zip = await r.body();
  ok(r.status() === 200 && zip.length > 500, 'ZIP se stáhl: ' + zip.length + ' B');
  ok(/filename\*=UTF-8/.test(r.headers()['content-disposition'] || ''), 'jméno souboru nese i podobu s diakritikou');

  // --- cílový svět a kontrola před zápisem ---
  sql("insert into svet (nazev, pocatecni_datum, pj_ucet_id) values ('Cílový svět','2026-01-01',(select id from ucty where email='test@local'))");
  const svet2 = parseInt(sqlVal("select id from svet where nazev='Cílový svět'"), 10);
  const form = { svet_id: String(svet2), akce: 'kontrola', soubor: { name: 'oblast.zip', mimeType: 'application/zip', buffer: zip } };
  let ri = await hrac.request.post(BASE + '/hra/api/oblast_import.php', { multipart: form });
  ok(ri.status() === 403 || ri.status() === 404, 'hráč cizí oblast do cizího světa nepřidá: ' + ri.status());
  ri = await pj.request.post(BASE + '/hra/api/oblast_import.php', { multipart: { ...form, soubor: { name: 'x.zip', mimeType: 'application/zip', buffer: Buffer.from('tohle neni zip') } } });
  ok(ri.status() === 422, 'nesmyslný soubor je odmítnut (422): ' + ri.status());
  ri = await pj.request.post(BASE + '/hra/api/oblast_import.php', { multipart: form });
  const k = (await ri.json()).kontrola;
  ok(ri.status() === 200 && k.pribude.map === 2 && k.pribude.npc === 2, 'kontrola řekne, co přibude: ' + JSON.stringify(k.pribude));
  ok(k.chybi_v_katalogu.length === 0, 'nic v katalogu nechybí (goblin je v bestiáři)');
  ok(sqlVal(`select count(*) from mapy where svet_id=${svet2}`) === '0', 'kontrola do světa nic nezapsala');

  // --- zápis ---
  ri = await pj.request.post(BASE + '/hra/api/oblast_import.php', { multipart: { ...form, akce: 'zapis' } });
  const v = await ri.json();
  ok(ri.status() === 200 && v.map === 2, 'přidaly se 2 mapy: ' + JSON.stringify(v.map));
  ok(sqlVal(`select count(*) from mapy where svet_id=${svet2}`) === '2', 'mapy jsou v cílovém světě');
  ok(sqlVal(`select count(*) from nestvura_instance where svet_id=${svet2}`) === '2', 'nestvůry taky');
  ok(sqlVal(`select count(*) from tokeny t join mapy m on m.id=t.mapa_id where m.svet_id=${svet2}`) === String(tokenuZdroj), 'tokeny taky: ' + tokenuZdroj);
  ok(sqlVal(`select count(*) from zdi z join mapy m on m.id=z.mapa_id where m.svet_id=${svet2}`) === String(zdiZdroj), 'zdi taky: ' + zdiZdroj);
  ok(sqlVal(`select count(*) from tokeny t join mapy m on m.id=t.mapa_id where m.svet_id=${svet2} and t.typ_entity='postava'`) === '0',
    'postavy se NEpřenesly (oblast je místo, ne družina)');

  // přečíslování: pin v novém světě ukazuje na novou mapu, ne na původní
  const cil = sqlVal(`select b.cilova_mapa_id from mapa_body b join mapy m on m.id=b.mapa_id where m.svet_id=${svet2} limit 1`);
  ok(cil !== '' && sqlVal(`select svet_id from mapy where id=${cil}`) === String(svet2),
    'pin vede na mapu v NOVÉM světě (přečíslování sedí): cíl ' + cil);
  ok(sqlVal(`select count(*) from mapy where svet_id=1`) === '2', 'původní svět zůstal nedotčený');

  // podklad mapy se zkopíroval jako nový soubor
  const podklad = sqlVal(`select obrazek_cesta from mapy where svet_id=${svet2} and obrazek_cesta is not null limit 1`);
  ok(/^import_/.test(podklad), 'podklad mapy se zkopíroval pod novým jménem: ' + podklad);

  // --- import podruhé: oblast se zduplikuje, nic se nepřepíše ---
  ri = await pj.request.post(BASE + '/hra/api/oblast_import.php', { multipart: { ...form, akce: 'zapis' } });
  ok(ri.status() === 200 && sqlVal(`select count(*) from mapy where svet_id=${svet2}`) === '4',
    'druhý import přidá další kopii (nepřepisuje): ' + sqlVal(`select count(*) from mapy where svet_id=${svet2}`));

  // --- chybějící druh v katalogu: ohlásí se a NPC se přeskočí, zbytek projde ---
  const fs = require('fs');
  fs.writeFileSync('/tmp/oblast_test.zip', zip);
  require('child_process').execSync(`python3 -c "
import zipfile, json, shutil
z = zipfile.ZipFile('/tmp/oblast_test.zip')
d = json.loads(z.read('data.json'))
m = z.read('manifest.json')
ostatni = [(i.filename, z.read(i.filename)) for i in z.infolist() if i.filename not in ('data.json','manifest.json')]
z.close()
for n in d['npc']:
    n['druh'] = 'Nestvůra která neexistuje'
w = zipfile.ZipFile('/tmp/oblast_chybi.zip','w')
w.writestr('manifest.json', m)
w.writestr('data.json', json.dumps(d, ensure_ascii=False))
for jm, obsah in ostatni: w.writestr(jm, obsah)
w.close()
"`);
  const zipChybi = fs.readFileSync('/tmp/oblast_chybi.zip');
  const form2 = { svet_id: String(svet2), akce: 'kontrola', soubor: { name: 'ch.zip', mimeType: 'application/zip', buffer: zipChybi } };
  ri = await pj.request.post(BASE + '/hra/api/oblast_import.php', { multipart: form2 });
  const k2 = (await ri.json()).kontrola;
  ok(k2.chybi_v_katalogu.length === 1 && /neexistuje/.test(k2.chybi_v_katalogu[0]),
    'kontrola ohlásí chybějící nestvůru: ' + JSON.stringify(k2.chybi_v_katalogu));
  const mapPred = parseInt(sqlVal(`select count(*) from mapy where svet_id=${svet2}`), 10);
  const npcPred = parseInt(sqlVal(`select count(*) from nestvura_instance where svet_id=${svet2}`), 10);
  ri = await pj.request.post(BASE + '/hra/api/oblast_import.php', { multipart: { ...form2, akce: 'zapis' } });
  const v2 = await ri.json();
  ok(ri.status() === 200 && (v2.preskoceno || []).length === 2, 'zápis přeskočí obě NPC a řekne to: ' + JSON.stringify(v2.preskoceno));
  ok(parseInt(sqlVal(`select count(*) from mapy where svet_id=${svet2}`), 10) === mapPred + 2, 'mapy se přesto přidaly');
  ok(parseInt(sqlVal(`select count(*) from nestvura_instance where svet_id=${svet2}`), 10) === npcPred, 'NPC bez druhu v katalogu nevznikla');

  // --- rozhraní: správa světa, nahrání souboru, kontrola a přidání ---
  sql("delete from mapy where svet_id=" + svet2);
  sql("delete from nestvura_instance where svet_id=" + svet2);
  await pj.goto(BASE + '/hra/svet_administrace.php?id=' + svet2);
  ok(await pj.locator('#oblastSoubor').count() === 1, 've správě světa je pole pro soubor s oblastí');
  ok(await pj.locator('#oblastPridatBtn').isDisabled(), 'tlačítko „Přidat do světa“ je na začátku zakázané');
  await pj.locator('#oblastSoubor').setInputFiles({ name: 'oblast.zip', mimeType: 'application/zip', buffer: zip });
  await pj.click('#oblastKontrolaBtn');
  await pj.waitForFunction(() => /Přibude/.test(document.getElementById('oblastVysledek').textContent), null, { timeout: 15000 });
  ok(/2 map/.test(await pj.textContent('#oblastVysledek')), 'stránka ukáže, co přibude: ' + (await pj.textContent('#oblastVysledek')).slice(0, 80));
  ok(!(await pj.locator('#oblastPridatBtn').isDisabled()), 'po kontrole jde oblast přidat');
  await pj.click('#oblastPridatBtn');
  await pj.waitForFunction(() => /Hotovo/.test(document.getElementById('oblastVysledek').textContent), null, { timeout: 20000 });
  ok(sqlVal(`select count(*) from mapy where svet_id=${svet2}`) === '2', 'přidání z rozhraní zapsalo mapy');
  ok(await pj.locator('#oblastVysledek a[href*="mapa.php"]').count() === 1, 'nabídne odkaz na přidanou mapu');
  // Výměna souboru po kontrole musí povolení shodit — jinak by šlo přidat něco jiného, než co se zkontrolovalo.
  await pj.locator('#oblastSoubor').setInputFiles({ name: 'jiny.zip', mimeType: 'application/zip', buffer: zip });
  ok(await pj.locator('#oblastPridatBtn').isDisabled(), 'výměna souboru zase zakáže přidání');

  // Úklid: test nahrává podklady map do uploads/ — po sobě je smaž, ať se nehromadí v pracovní kopii.
  sql(`delete from svet where id=${svet2}`);
  for (const slozka of ['mapy', 'obrazky']) {
    for (const f of require('fs').readdirSync(require('path').resolve(__dirname, '../../uploads/' + slozka)).filter((x) => x.startsWith('import_'))) {
      try { require('fs').unlinkSync(require('path').resolve(__dirname, '../../uploads/' + slozka, f)); } catch (e) {}
    }
  }
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
