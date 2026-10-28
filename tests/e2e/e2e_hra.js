// „První ostrá hra“ — průchod toku: účty → svět → pozvání hráčů → postavy přes wizard → co kdo vidí.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const DB = process.env.DB || 'dracak';
const BASE = (process.env.BASE || 'http://127.0.0.1:8099');
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
const HESLO = 'E2e-heslo-12345';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
function uklid() {
  sql("delete from postavy where nazev like 'E2E %'; delete from svet where nazev like 'E2E %'; delete from ucty where email like '%_e2e@local'");
}
(async () => {
  uklid();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  async function login(email, heslo) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    page.chyby = []; page.on('pageerror', (e) => page.chyby.push(e.message));
    await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    return page;
  }
  // 1) admin zakládá účty
  const adm = await login('test@local', 'test1234');
  await adm.goto(BASE + '/admin.php');
  for (const [email, jmeno, role] of [['pj_e2e@local', 'E2E PJ', 'pj'], ['h1_e2e@local', 'E2E Hráč 1', 'hrac'], ['h2_e2e@local', 'E2E Hráč 2', 'hrac']]) {
    const f = adm.locator('form:has(input[name="akce"][value="vytvorit"])');
    await f.locator('input[name=email]').fill(email); await f.locator('input[name=jmeno]').fill(jmeno);
    await f.locator('input[name=heslo]').fill(HESLO); await f.locator('select[name=role]').selectOption(role);
    await Promise.all([adm.waitForNavigation(), f.locator('button[type=submit]').click()]);
  }
  ok(sqlVal("select count(*) from ucty where email like '%_e2e@local'") === '3', 'admin založil tři účty (PJ + 2 hráči)');
  // 2) PJ zakládá svět
  const pj = await login('pj_e2e@local', HESLO);
  await pj.goto(BASE + '/hra/svety.php');
  await pj.fill('#nazev', 'E2E svět');
  await Promise.all([pj.waitForNavigation(), pj.click('button:has-text("Založit")')]);
  const svetId = parseInt(sqlVal("select id from svet where nazev='E2E svět'"), 10);
  ok(svetId > 0, 'PJ založil svět: id ' + svetId + ', url ' + pj.url().replace(BASE, ''));
  ok(sqlVal(`select pj_ucet_id=(select id from ucty where email='pj_e2e@local') from svet where id=${svetId}`) === '1', 'PJ světa je zakladatel');
  // 3) PJ přidá oba hráče
  for (const jmeno of ['E2E Hráč 1', 'E2E Hráč 2']) {
    await pj.goto(BASE + '/hra/svet.php?id=' + svetId);
    await pj.evaluate(() => document.getElementById('dlg-hraci').showModal());
    await pj.selectOption('#dlg-hraci select[name=ucet_id]', { label: jmeno });
    await Promise.all([pj.waitForNavigation(), pj.click('#dlg-hraci button:has-text("Přidat")')]);
  }
  ok(sqlVal(`select count(*) from svet_hraci where svet_id=${svetId}`) === '2', 'oba hráči jsou ve světě');
  // 4) hráči: vidí svět a zakládají postavu přes wizard
  async function zalozPostavu(page, nazev) {
    await page.goto(BASE + '/hra/svety.php');
    ok((await page.locator('main').innerText()).includes('E2E svět'), nazev + ': ve výpisu světů vidí „E2E svět“');
    await page.goto(BASE + '/hra/postava_nova.php?id=' + svetId);
    await page.locator('select[name=rasa_id]').selectOption({ index: 1 });
    await Promise.all([page.waitForNavigation(), page.click('button[value=dalsi]')]);
    await page.locator('select[name=povolani_id]').selectOption({ index: 1 });
    await Promise.all([page.waitForNavigation(), page.click('button[value=dalsi]')]);
    await Promise.all([page.waitForNavigation(), page.click('button[value=dalsi]')]);   // atributy (předhozené)
    await Promise.all([page.waitForNavigation(), page.click('button[value=dalsi]')]);   // životy
    await page.fill('input[name=nazev]', nazev);
    await Promise.all([page.waitForNavigation(), page.click('button[value=ulozit]')]);
    return parseInt(sqlVal(`select id from postavy where nazev='${nazev}'`), 10);
  }
  const h1 = await login('h1_e2e@local', HESLO), h2 = await login('h2_e2e@local', HESLO);
  const p1 = await zalozPostavu(h1, 'E2E Postava 1');
  const p2 = await zalozPostavu(h2, 'E2E Postava 2');
  ok(p1 > 0 && p2 > 0 && p1 !== p2, 'obě postavy založené přes wizard: ' + p1 + ', ' + p2);
  ok(sqlVal(`select vlastnik_ucet_id=(select id from ucty where email='h1_e2e@local') and svet_id=${svetId} from postavy where id=${p1}`) === '1', 'postava 1 patří hráči 1 a světu');
  const stat = sqlVal(`select concat(sila,',',obratnost,',',odolnost,',',inteligence,',',charisma,',',max_hp) from postavy where id=${p1}`);
  ok(/^\d+,\d+,\d+,\d+,\d+,\d+$/.test(stat), 'postava má atributy a životy: ' + stat);
  // 5) co kdo vidí
  const r1 = await h1.goto(BASE + '/hra/denik.php?id=' + p1);
  ok(r1.status() === 200, 'hráč 1 vidí deník své postavy');
  const r2 = await h1.goto(BASE + '/hra/denik.php?id=' + p2);
  ok(r2.status() === 403, 'hráč 1 deník postavy hráče 2 nevidí (403)');
  const r3 = await h1.goto(BASE + '/hra/postava.php?id=' + p2);
  ok([403, 404].includes(r3.status()), 'hráč 1 nemůže editovat cizí postavu (' + r3.status() + ')');
  const r4 = await h1.evaluate(async (id) => { const r = await fetch('api/postava_stav.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ postava_id: id, akce: 'hp', delta: -1 }) }); return r.status; }, p2);
  ok(r4 === 403, 'hráč 1 cizí postavě neubere životy přes API (403)');
  for (const [adresa, kod] of [['/admin.php', 403], ['/admin_migrace.php', 403], ['/admin_nasazeni.php', 403]]) {
    const r = await h1.goto(BASE + adresa);
    ok(r.status() === kod, 'hráč nevidí ' + adresa + ' (' + r.status() + ')');
  }
  const rs = await h1.goto(BASE + '/hra/svet.php?id=' + svetId);
  ok(rs.status() === 200, 'hráč vidí svět, ve kterém je');
  const sh = (await h1.locator('body').innerText());
  ok(!/Přidat hráče do světa/.test(sh), 'hráč nemá nabídku „Přidat hráče“ (jen PJ)');
  // 6) PJ vidí družinu
  const rd = await pj.goto(BASE + '/hra/druzina.php?id=' + svetId);
  const dt = await pj.locator('body').innerText();
  ok(rd.status() === 200 && /E2E Postava 1/.test(dt) && /E2E Postava 2/.test(dt), 'PJ vidí obě postavy v přehledu družiny');
  const rp = await pj.goto(BASE + '/hra/denik.php?id=' + p1);
  ok(rp.status() === 200, 'PJ otevře deník hráčovy postavy');
  // 7) cizí svět: hráč 1 nevidí svět, ve kterém není
  sql("insert into svet (nazev, pj_ucet_id) select 'E2E cizí svět', id from ucty where email='pj_e2e@local'");
  const cizi = parseInt(sqlVal("select id from svet where nazev='E2E cizí svět'"), 10);
  const rc = await h1.goto(BASE + '/hra/svet.php?id=' + cizi);
  ok([403, 404].includes(rc.status()), 'hráč 1 nevidí svět, do kterého nepatří (' + rc.status() + ')');
  ok(!(await (await h1.goto(BASE + '/hra/svety.php')).text()).includes('E2E cizí svět'), 'a ve výpisu světů ho nemá');
  const chybyJs = [...pj.chyby, ...h1.chyby, ...h2.chyby];
  ok(chybyJs.length === 0, 'žádné chyby JS: ' + chybyJs.join('; '));
  uklid();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD', e.message.split('\n')[0]); uklid(); process.exit(2); });
