// Poznámky světa (hra/svet.php, includes/vtt_poznamky.php, admin_poznamky.php): štítky s auto-návrhem, vazba na postavu, úprava na místě (jen autor),
// odznak nepřečtených (svět i seznam světů), odebrání hráče ze světa ruší sdílení, admin dohled. PJ světa 1 je účet test@local (admin), hráč hrac@local.
// Spuštění po resetu dat: mysql dracak < ../fixture/seed.sql; node poznamky_ui.js
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
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    const chybyJs = [];
    p.on('pageerror', e => chybyJs.push(e.message));
    p.chybyJs = chybyJs;
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const post = (p, url, f) => p.request.post(BASE + url, { form: f, maxRedirects: 0 });
  const text = async (p, url) => { const r = await p.request.get(BASE + url); return { s: r.status(), t: await r.text() }; };
  const poznamka = (q) => sqlVal(`select ${q}`);

  // --- založení: auto-návrh štítku ---
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Hospodský Bartoš nabídl úkol: najít ztracený meč', 'sdileno[]': '1' });
  ok(poznamka("stitek from svet_poznamky where text like 'Hospodský%'") === 'quest', 'klíčové slovo „úkol“ navrhne štítek Úkol');
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Něco se tu zdá divné', postava_id: '1' });
  ok(poznamka("stitek from svet_poznamky where text like 'Něco%'") === 'npc' && poznamka("postava_id from svet_poznamky where text like 'Něco%'") === '1', 'vazba na postavu navrhne štítek NPC a uloží postavu');
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Za mostem se stmívá', mapa_id: '1' });
  ok(poznamka("stitek from svet_poznamky where text like 'Za mostem%'") === 'misto', 'vazba na místo navrhne štítek Místo');
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Jen tak' });
  ok(poznamka("ifnull(stitek, 'NULL') from svet_poznamky where text = 'Jen tak'") === 'NULL', 'bez náznaku zůstane poznámka bez štítku');
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Hospoda U draka', stitek: 'lore' });
  ok(poznamka("stitek from svet_poznamky where text = 'Hospoda U draka'") === 'lore', 'zvolený štítek má přednost před návrhem');
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Cizí vazby', postava_id: '99999', stitek: 'neexistuje', 'sdileno[]': '99999' });
  ok(poznamka("concat(ifnull(stitek, 'NULL'), '|', ifnull(postava_id, 'NULL')) from svet_poznamky where text = 'Cizí vazby'") === 'NULL|NULL', 'neexistující štítek a postava se zahodí');
  ok(poznamka("count(*) from svet_poznamka_sdileni s join svet_poznamky p on p.id = s.poznamka_id where p.text = 'Cizí vazby'") === '0', 'sdílení účtu, který do světa nepatří, se zahodí');

  // --- odznak nepřečtených ---
  let r = await text(pj, '/hra/svety.php');
  ok(/1 nová poznámka/.test(r.t), 'seznam světů ukazuje „1 nová poznámka“ (nasdílená PJ)');
  r = await text(pj, '/hra/svet.php?id=1');
  ok(r.s === 200 && /1 nová/.test(r.t) && /svet-note-badge">Nové/.test(r.t), 'svět ukazuje počet nových a štítek „Nové“ u poznámky');
  r = await text(pj, '/hra/svet.php?id=1');
  ok(!/svet-note-badge">Nové/.test(r.t) && !/svet-note-new-count/.test(r.t), 'po zobrazení už poznámka není nová');
  r = await text(pj, '/hra/svety.php');
  ok(!/nová poznámka|nové poznámky|nových poznámek/.test(r.t), 'odznak ze seznamu světů zmizel');

  // --- úprava: jen autor, změna textu poznámku zase označí za novou ---
  const id1 = poznamka("id from svet_poznamky where text like 'Hospodský%'");
  r = await post(pj, '/hra/svet.php?id=1', { akce: 'upravit_poznamku', poznamka_id: id1, text: 'Cizí zásah' });
  ok(r.status() === 403 && poznamka(`text from svet_poznamky where id = ${id1}`).startsWith('Hospodský'), 'cizí poznámku upravit nejde (403) a nic se nezměnilo');
  r = await post(hrac, '/hra/svet.php?id=1', { akce: 'upravit_poznamku', poznamka_id: id1, text: 'Hospodský Bartoš nabídl práci: najít ztracený meč v kobce', stitek: 'quest', mapa_id: '2', postava_id: '2', 'sdileno[]': '1' });
  ok(r.status() === 302 && poznamka(`concat(stitek, '|', mapa_id, '|', postava_id, '|', (upraveno is not null)) from svet_poznamky where id = ${id1}`) === 'quest|2|2|1', 'autor upraví text, místo, postavu a štítek; čas úpravy se zapíše');
  r = await text(pj, '/hra/svety.php');
  ok(/1 nová poznámka/.test(r.t), 'upravená poznámka je pro příjemce zase nová');
  r = await text(pj, '/hra/svet.php?id=1');
  ok(/upraveno/.test(r.t) && /Postava: Sindor/.test(r.t), 'výpis ukazuje „upraveno“ a vázanou postavu');
  // odebrání sdílení v úpravě
  await post(hrac, '/hra/svet.php?id=1', { akce: 'upravit_poznamku', poznamka_id: id1, text: 'Hospodský Bartoš nabídl práci: najít ztracený meč v kobce', stitek: 'quest' });
  ok(poznamka(`count(*) from svet_poznamka_sdileni where poznamka_id = ${id1}`) === '0', 'úpravou jde sdílení zrušit');
  await post(hrac, '/hra/svet.php?id=1', { akce: 'upravit_poznamku', poznamka_id: id1, text: 'Hospodský Bartoš nabídl práci: najít ztracený meč v kobce', stitek: 'quest', 'sdileno[]': '1' });

  // --- filtr podle štítku a XSS ---
  await post(hrac, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: '<script>window.__xss=1</script><img src=x onerror=window.__xss=1>', stitek: 'loot', 'sdileno[]': '1' });
  r = await text(hrac, '/hra/svet.php?id=1&stitek=loot');
  ok(r.s === 200 && /Kořist \(1\)/.test(r.t) && !/Hospodský/.test(r.t) && !/<script>window\.__xss/.test(r.t) && /&lt;script&gt;/.test(r.t), 'filtr ukáže jen štítek Kořist a text je escapovaný');
  await hrac.goto(BASE + '/hra/svet.php?id=1');
  ok(!(await hrac.evaluate(() => window.__xss === 1)), 'vložený skript se nespustil');
  await hrac.goto(BASE + '/hra/svet.php?id=1');
  const upravit = hrac.locator('[data-otevri]').first();
  if (await upravit.count()) {
    await upravit.click();
    ok(await hrac.locator('details.svet-note-edit[open]').count() === 1, 'odkaz „Upravit“ rozbalí úpravu poznámky');
  } else {
    ok(false, 'chybí odkaz Upravit');
  }

  // --- admin dohled ---
  r = await text(pj, '/admin_poznamky.php');
  ok(r.s === 200 && /Hospodský/.test(r.t) && /Jen tak/.test(r.t) && /Cizí vazby/.test(r.t), 'admin vidí všechny poznámky (i nenasdílené)');
  r = await text(pj, '/admin_poznamky.php?stitek=lore');
  ok(/Hospoda U draka/.test(r.t) && !/Hospodský/.test(r.t), 'admin filtruje podle štítku');
  r = await hrac.request.get(BASE + '/admin_poznamky.php', { maxRedirects: 0 });
  ok(r.status() !== 200, 'hráč na stránku dohledu nesmí: ' + r.status());

  // --- odebrání hráče ruší sdílení ---
  await post(pj, '/hra/svet.php?id=1', { akce: 'pridat_poznamku', text: 'Jen pro hráče', 'sdileno[]': '3' });
  const idPj = poznamka("id from svet_poznamky where text = 'Jen pro hráče'");
  ok(poznamka(`count(*) from svet_poznamka_sdileni where poznamka_id = ${idPj}`) === '1', 'PJ nasdílí poznámku hráči');
  await post(pj, '/hra/svet.php?id=1', { akce: 'odebrat_hrace', ucet_id: '3' });
  ok(poznamka('count(*) from svet_hraci where svet_id = 1 and ucet_id = 3') === '0', 'hráč je ze světa odebrán');
  ok(poznamka(`count(*) from svet_poznamka_sdileni where poznamka_id = ${idPj}`) === '0', 'nasdílení poznámek světa odebranému hráči se zrušilo');
  ok(poznamka("count(*) from svet_poznamky where autor_ucet_id = 3") !== '0', 'poznámky, které odebraný hráč napsal, zůstaly');
  ok(poznamka('count(*) from postavy where svet_id = 1 and vlastnik_ucet_id = 3') === '1', 'jeho postava ve světě zůstala');
  r = await hrac.request.get(BASE + '/hra/svet.php?id=1', { maxRedirects: 0 });
  ok(r.status() !== 200, 'odebraný hráč do světa nevstoupí: ' + r.status());
  r = await post(hrac, '/hra/svet.php?id=1', { akce: 'odebrat_hrace', ucet_id: '1' });
  ok(r.status() !== 302 || poznamka('count(*) from svet_hraci where svet_id = 1 and ucet_id = 1') === '1', 'hráč nemůže odebírat hráče');
  await post(pj, '/hra/svet.php?id=1', { akce: 'odebrat_hrace', ucet_id: '1' });
  ok(poznamka('pj_ucet_id from svet where id = 1') === '1', 'PJ světa zůstává PJ');

  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
