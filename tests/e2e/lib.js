const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
async function otevri(opts = {}) {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ viewport: opts.viewport || { width: 1280, height: 800 }, hasTouch: !!opts.touch, isMobile: !!opts.touch });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', e => errs.push('PAGEERROR ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errs.push('CONSOLE ' + m.text()); });
  page.on('dialog', d => { console.log('DIALOG', d.message()); d.accept(); });
  await page.goto('' + (process.env.BASE || 'http://127.0.0.1:8099') + '/index.php');
  await page.fill('#email', opts.email || 'test@local'); await page.fill('#password', opts.heslo || 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  return { browser, page, errs, ctx };
}
module.exports = { otevri };
