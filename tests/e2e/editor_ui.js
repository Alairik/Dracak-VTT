// Editor číselníků po přechodu z inline atributů (onclick…) na data-atributy a delegovanou obsluhu (assets/js/obecne.js, skript v editor.php):
// kroky +/− u hodnoty, přidání a odebrání štítku (i u právě přidaného), odeslání filtru po změně, potvrzení před smazáním.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const chybyJs = [];
  page.on('pageerror', e => chybyJs.push(e.message));
  const dialogy = [];
  page.on('dialog', d => { dialogy.push(d.message()); d.dismiss(); });
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

  // rasy mají hodnotové štítky (bonusy vlastností)
  await page.goto(BASE + '/editor.php?tabulka=rasy&akce=edit&id=1');
  const hodnota = () => page.locator('.pill-x .chip-val').first().innerText();
  const pocatek = await hodnota().catch(() => null);
  ok(pocatek !== null, 'rasa má štítek s hodnotou: ' + pocatek);
  if (pocatek !== null) {
    const v0 = parseInt(pocatek.replace('+', ''), 10);
    await page.locator('.pill-x .chip-stepper button').nth(1).click();
    const v1 = parseInt((await hodnota()).replace('+', ''), 10);
    ok(v1 === v0 + 1, 'tlačítko + zvýší hodnotu: ' + v0 + ' → ' + v1);
    await page.locator('.pill-x .chip-stepper button').nth(0).click();
    await page.locator('.pill-x .chip-stepper button').nth(0).click();
    const v2 = parseInt((await hodnota()).replace('+', ''), 10);
    ok(v2 === v0 - 1, 'tlačítko − sníží hodnotu: ' + v2);
    const skryte = await page.locator('.pill-x .chip-extra').first().inputValue();
    ok(parseInt(skryte, 10) === v2, 'skryté pole formuláře má stejnou hodnotu: ' + skryte);
  }
  // přidání a odebrání štítku u rasy
  const selectRel = page.locator('select[data-rel][data-extra="stepper"]').first();
  if (await selectRel.count()) {
    const predTag = await page.locator('.tagbox[data-rel="' + await selectRel.getAttribute('data-rel') + '"] .pill-x').count();
    const moznosti = await selectRel.locator('option').evaluateAll(o => o.filter(x => x.value).map(x => x.value));
    await selectRel.selectOption(moznosti[0]);
    // tlačítko patří ke stejnému výběru (rasa má víc vazeb, první tlačítko na stránce by přidávalo jinam)
    await selectRel.locator('xpath=following-sibling::button[@data-akce="pridat-tag"]').click();
    const tagbox = page.locator('.tagbox[data-rel="' + await selectRel.getAttribute('data-rel') + '"]');
    ok((await tagbox.locator('.pill-x').count()) === predTag + 1, 'přidání štítku (tlačítko + Přidat)');
    const novy = tagbox.locator('.pill-x').last();
    await novy.locator('[data-akce="bump"]').nth(1).click();
    ok((await novy.locator('.chip-val').innerText()) === '+1', 'u právě přidaného štítku funguje + (delegovaná obsluha)');
    await novy.locator('[data-akce="odebrat-tag"]').click();
    ok((await tagbox.locator('.pill-x').count()) === predTag, 'odebrání štítku ×');
  } else {
    ok(false, 'chybí výběr štítku s hodnotou');
  }
  // filtr se po změně odešle
  await page.goto(BASE + '/editor.php?tabulka=kouzla');
  const filtr = page.locator('select[data-odeslat-pri-zmene]').first();
  if (await filtr.count()) {
    const hodnotyFiltru = await filtr.locator('option').evaluateAll(o => o.filter(x => x.value).map(x => x.value));
    await Promise.all([page.waitForNavigation(), filtr.selectOption(hodnotyFiltru[0])]);
    ok(/[?&]\w+=/.test(page.url()) && page.url().includes(encodeURIComponent(hodnotyFiltru[0]).replace(/%20/g, '+').slice(0, 3)) || /povolani|skola|obor|kategorie/.test(page.url()), 'změna filtru odešle formulář: ' + page.url().slice(-60));
  } else {
    ok(false, 'chybí filtr s data-odeslat-pri-zmene');
  }
  // potvrzení před smazáním (Zrušit nesmí nic smazat)
  const radku = await page.locator('table tbody tr').count();
  const smazat = page.locator('form[data-potvrdit] button').first();
  if (await smazat.count()) {
    await smazat.click();
    await page.waitForTimeout(300);
    ok(dialogy.some(d => /Opravdu smazat/.test(d)) && (await page.locator('table tbody tr').count()) === radku, 'smazání se před provedením zeptá a po Zrušit nic nesmaže: ' + JSON.stringify(dialogy));
  } else {
    ok(false, 'chybí tlačítko Smazat s potvrzením');
  }
  ok(chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + chybyJs.join('; '));
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
