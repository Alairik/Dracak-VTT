// Přihlašovací stránka: špatná hesla se po šestém pokusu zablokují (429 a hláška), i správné heslo je z té adresy zablokované;
// pole poslaná jako pole (email[]=…) nesmí shodit stránku. Počítadla se po testu smažou.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
const uklid = () => fs.rmSync(ROOT + '/logs/login', { recursive: true, force: true });
(async () => {
  uklid();
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const poslat = async (email, heslo) => {
    await page.goto(BASE + '/index.php');
    await page.fill('#email', email); await page.fill('#password', heslo);
    const [odp] = await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    return { s: odp ? odp.status() : 0, text: (await page.locator('body').innerText()).replace(/\s+/g, ' ') };
  };
  let r;
  for (let i = 1; i <= 5; i++) { r = await poslat('test@local', 'spatne-' + i); }
  ok(r.s === 200 && /Nesprávný e-mail nebo heslo/.test(r.text), 'pátý špatný pokus: obyčejná hláška: ' + r.s);
  r = await poslat('test@local', 'spatne-6');
  ok(r.s === 429 && /Příliš mnoho nepovedených pokusů.*min/.test(r.text), 'šestý špatný pokus vyčerpá limit: 429 a hláška o čekání: ' + r.s + ' ' + r.text.slice(0, 120));
  r = await poslat('test@local', 'spatne-7');
  ok(r.s === 429, 'sedmý pokus je blokovaný: ' + r.s);
  r = await poslat('test@local', 'test1234');
  ok(r.s === 429, 'správné heslo ze zablokované adresy je taky odmítnuto: ' + r.s);
  r = await poslat('hrac@local', 'hrac1234');
  ok(r.s === 200 && /dashboard|Dashboard/.test(page.url() + r.text), 'jiný účet ze stejné adresy se přihlásí (blokuje se jen ten, kdo chybuje u jednoho e-mailu): ' + page.url());
  await ctx.close();
  // pole místo textu
  const odp = await (await browser.newContext()).request.post(BASE + '/index.php', { form: { 'email[]': 'a', 'password[]': 'b' } });
  ok(odp.status() < 500, 'email[]=… / password[]=… nespadne na chybu serveru: ' + odp.status());
  uklid();
  const po = await poslat2();
  async function poslat2() {
    const p = await (await browser.newContext()).newPage();
    await p.goto(BASE + '/index.php'); await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p.url();
  }
  ok(/dashboard/.test(po), 'po vymazání počítadel se správné heslo přihlásí: ' + po);
  await browser.close();
  uklid();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
