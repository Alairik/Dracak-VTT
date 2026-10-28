const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1300, height: 1000 } });
  const errs = [];
  page.on('pageerror', e => errs.push('PAGEERROR ' + e.message));
  await page.goto('http://127.0.0.1:8099/index.php');
  await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto('http://127.0.0.1:8099/hra/druzina.php?id=1');
  const pj = await page.$('.dz-pj');
  console.log('PJ sekce:', !!pj);
  if (pj) console.log((await pj.textContent()).replace(/\s+/g, ' '));
  await page.screenshot({ path: process.argv[2] || 'druzina_pj.png', fullPage: true });
  console.log(errs.join('\n') || 'bez JS chyb');
  await browser.close();
})();
