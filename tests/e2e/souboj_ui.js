const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const SHOTS = (process.env.OUT || '/tmp/dracak-tests') + '/shots';
require('fs').mkdirSync(SHOTS, { recursive: true });
const errs = [];
async function login(browser, email, heslo, label, vp) {
  const ctx = await browser.newContext({ viewport: vp || { width: 1280, height: 860 } });
  const page = await ctx.newPage();
  page.on('pageerror', e => errs.push(label + ' PAGEERROR ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errs.push(label + ' CONSOLE ' + m.text()); });
  page.on('response', r => { if (r.status() >= 400) errs.push(label + ' HTTP ' + r.status() + ' ' + r.url().replace(BASE, '')); });
  page.on('dialog', d => { console.log(label, 'DIALOG', d.message()); d.accept(); });
  await page.goto(BASE + '/index.php');
  await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=1');
  await page.waitForTimeout(800);
  return page;
}
const api = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  return r.json();
}, [name, body]);
const ok = (c, m) => { if (!c) process.exitCode = 1; console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m); };
const txt = (page, sel) => page.$eval(sel, e => e.innerText).catch(() => '(nenalezeno)');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--use-gl=swiftshader', '--enable-webgl', '--ignore-gpu-blocklist'] });
  const pj = await login(browser, 'test@local', 'test1234', 'PJ');
  const hr = await login(browser, 'hrac@local', 'hrac1234', 'HRAC');
  await api(pj, 'souboj_konec', { mapa_id: 1 });
  const gob = [];
  for (let i = 0; i < 3; i++) { const d = await api(pj, 'token_pridat', { mapa_id: 1, typ_entity: 'nestvura', nestvura_id: 41, x: 900 + i * 70, y: 300 }); gob.push(d.token_id); }
  await pj.reload(); await hr.reload(); await pj.waitForTimeout(800); await hr.waitForTimeout(500);
  await pj.screenshot({ path: SHOTS + '/00_pred.png' });
  ok(await pj.$('#tb-souboj') !== null, 'tlačítko Zahájit souboj u PJ');
  ok(await hr.$('#tb-souboj') === null, 'hráč tlačítko nemá');

  // --- zahájení ---
  errs.length = 0;   // příprava (úklid souboje, který neběží = 422; ikona stránky = 404) do kontroly chyb nepatří
  await pj.click('#tb-souboj');
  await pj.waitForSelector('#modalSouboj', { state: 'visible' });
  await pj.screenshot({ path: SHOTS + '/01_zahajeni.png' });
  const rad = await pj.$$eval('#soubojUcastnici .souboj-radek', r => r.map(x => x.innerText.trim().replace(/\s+/g, ' ')));
  console.log('účastníci v okně:', rad.join(' | '));

  // Rychlý výběr, kdo vstoupí do iniciativy (na mapě s hodně tvory se po jednom klikat nedá; kdo zaškrtnutý není,
  // do pořadí tahů nevstoupí a PJ ho řídí ručně). Rozhodnutí uživatele 2026-11-09.
  // Od 2026-11-12 (krok 0 nového průběhu) je seznam schovaný pod „Upravit, kdo bojuje…“ a nahoře je jen souhrn stran.
  ok(!(await pj.locator('#soubojUcastnici').isVisible()), 'seznam tvorů je při zahájení sbalený');
  await pj.click('#soubojUprava > summary'); await pj.waitForTimeout(150);
  const UCAST = '#soubojUcastnici input[type=checkbox]:not([data-zbran])';
  const zaskrtnuto = () => pj.locator(UCAST + ':checked').count();
  const vsech = await pj.locator(UCAST).count();
  ok((await txt(pj, '#soubojPocet')).includes('z ' + vsech), 'okno píše, kolik tvorů půjde do iniciativy: ' + (await txt(pj, '#soubojPocet')));
  await pj.click('[data-souboj-vyber="nic"]'); await pj.waitForTimeout(150);
  ok(await zaskrtnuto() === 0, '„Nikoho“ odškrtne všechny');
  await pj.click('[data-souboj-vyber="vse"]'); await pj.waitForTimeout(150);
  ok(await zaskrtnuto() === vsech, '„Vše“ zaškrtne všechny: ' + vsech);
  await pj.click('[data-souboj-vyber="druzina"]'); await pj.waitForTimeout(150);
  const jenDruzina = await zaskrtnuto();
  const druzinaRadku = await pj.locator('#soubojUcastnici .souboj-radek select option[value="druzina"]:checked').count();
  ok(jenDruzina > 0 && jenDruzina === druzinaRadku, '„Jen družina“ nechá zaškrtnuté jen tvory na straně družiny: ' + jenDruzina + ' z ' + vsech);
  ok((await txt(pj, '#soubojPocet')).includes('půjde ' + jenDruzina), 'počítadlo sedí po rychlém výběru: ' + (await txt(pj, '#soubojPocet')));
  await pj.click('[data-souboj-vyber="vse"]'); await pj.waitForTimeout(150);

  await pj.click('#soubojUlozit');
  // ===== Nový průběh (2026-11-12): pruh pořadí nahoře, jedna karta dole, postranní panel souboj neřídí =====
  const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${process.env.DB || 'dracak'} -N -e "${q}"`).toString().trim();
  await pj.waitForSelector('#bojPoradi', { state: 'visible' });
  await pj.waitForTimeout(600);
  await pj.screenshot({ path: SHOTS + '/02_priprava_pj.png' });
  ok(await pj.locator('#raidPanel').isVisible(), 'okno tvorů se při zahájení souboje otevře jako svislý přehled pořadí');
  ok(await pj.locator('#raidPanel select, #raidAkce button, #raidInfo button').count() === 0, 'ovládání souboje v okně tvorů není (je v kartě dole)');
  ok(/Kolo 1/.test(await txt(pj, '#bojPoradi')), 'pruh pořadí: ' + (await txt(pj, '#bojPoradi')).replace(/\s+/g, ' '));

  // --- krok 1: hráč ohlásí akci jedním klepnutím ---
  await hr.waitForTimeout(2500);
  await hr.screenshot({ path: SHOTS + '/03_co_udelam_hrac.png' });
  ok(await hr.locator('#bojKarta .bk-dlazdice-b').count() === 4, 'hráč má kartu se čtyřmi dlaždicemi (Útok, Střelba, Kouzlo, Pohyb)');
  ok(/Sindor, co uděláš/.test(await txt(hr, '#bojKarta')), 'karta se ptá jménem: ' + (await txt(hr, '#bojKarta .bk-nadpis')));
  ok(await hr.locator('#bojKarta select').count() === 0 && await hr.locator('#bojKarta input').count() === 0, 'hráč při ohlášení nic nevyplňuje (žádné pole, žádný výběr cíle)');
  ok(await hr.locator('#bojKarta [data-hod="ini"]').count() === 0, 'dokud akci neohlásí, tlačítko hodu tam není');
  ok(await hr.locator('#bojKarta [data-umysl="strelba"]').isDisabled(), 'Střelba je zamčená, když postava nemá čím střílet');
  ok(/\+1/.test(await txt(hr, '#bojKarta [data-umysl="kouzleni"]')), 'u Kouzla je vidět bonus k iniciativě: ' + (await txt(hr, '#bojKarta [data-umysl="kouzleni"]')).replace(/\s+/g, ' '));
  const boxyHrace = await hr.$$eval('#raidSeznam .raid-box .raid-jmeno', (r) => r.map((x) => x.innerText.trim()));
  ok(boxyHrace.length === 1 && /^Sindor/.test(boxyHrace[0]), 'hráč vidí v okně tvorů jen družinu, nepřátele a jejich pořadí ne: ' + boxyHrace.join(', '));
  await hr.click('#bojKarta [data-umysl="kouzleni"]');
  await hr.waitForTimeout(1500);
  ok(await hr.getAttribute('#bojKarta [data-umysl="kouzleni"]', 'aria-pressed') === 'true', 'ohlášená akce je zvýrazněná');
  ok(/Kouzlení/.test(await txt(hr, '#bojKarta .bk-slozeni')) && /\+1/.test(await txt(hr, '#bojKarta .bk-slozeni')), 'složení hodu je vidět rovnou: ' + (await txt(hr, '#bojKarta .bk-slozeni')).replace(/\s+/g, ' '));

  // --- krok 2: hod na iniciativu (tady z vlastní kostky, ať je výsledek daný) ---
  await hr.click('#bojKarta button:has-text("Hodil jsem u stolu")');
  await hr.fill('#bojKarta .bk-hod input', '4');
  await hr.click('#bojKarta .bk-hod button:has-text("Zapsat")');
  await hr.waitForTimeout(1800);
  ok(sqlVal(`select concat(hod, '/', vysledek) from kolo_iniciativa ki join tokeny t on t.id=ki.token_id where t.entita_id=2 and t.typ_entity='postava' and t.mapa_id=1`) === '4/5', 'hod 4 + 1 za kouzlení = 5');
  ok(/Iniciativa 5/.test(await txt(hr, '#bojKarta')), 'po hodu karta ukáže výsledek a čeká: ' + (await txt(hr, '#bojKarta')).replace(/\s+/g, ' '));
  ok(sqlVal('select faze from kolo_stav where mapa_id=1') === 'iniciativa', 'kolo ještě nezačalo — nehodili všichni');
  await hr.screenshot({ path: SHOTS + '/05_po_hodu_hrac.png' });

  // --- PJ: řádek na skupinu, nestvůry útočí samy, postava hráče se jen čeká ---
  await pj.waitForTimeout(1500);
  await pj.screenshot({ path: SHOTS + '/06_priprava_pj.png' });
  const radkyPj = await pj.$$eval('#bojKarta .bk-radek', (r) => r.map((x) => x.innerText.trim().replace(/\s+/g, ' ')));
  console.log('PJ řádky:', radkyPj.join(' | '));
  ok(radkyPj.filter((r) => /^Goblin ×3/.test(r)).length === 1, 'tři goblini jsou jedna skupina s jedním hodem');
  ok(await pj.locator('#bojKarta .bk-radek', { hasText: 'Goblin' }).locator('select').inputValue() === 'utok', 'nestvůra má předvolený útok');
  const radekHrun = pj.locator('#bojKarta .bk-radek', { hasText: 'Hrun' });
  ok(await radekHrun.locator('[data-hod="ini"]').isDisabled(), 'postava vedená PJ bez ohlášené akce hodit nejde');
  await radekHrun.locator('select').selectOption('utok');
  await pj.waitForTimeout(1500);
  ok(await pj.locator('#bojKarta .bk-radek', { hasText: 'Hrun' }).locator('[data-hod="ini"]').isEnabled(), 'po ohlášení akce už ano');
  ok(/iniciativa 5/.test(radkyPj.find((r) => /^Sindor/.test(r)) || ''), 'PJ vidí, co hodil hráč: ' + (radkyPj.find((r) => /^Sindor/.test(r)) || ''));
  await pj.click('#bojKarta [data-hod="vse"]');
  await pj.waitForFunction(() => /půlka/.test(document.getElementById('bojPoradi').innerText), null, { timeout: 30000 }).catch(() => console.log('kolo nezačalo'));
  await pj.waitForTimeout(1200);
  ok(sqlVal('select faze from kolo_stav where mapa_id=1') === 'kolo', 'jakmile hodil poslední, tahy začaly samy (PJ nic neodklikával)');
  ok(sqlVal('select count(*) from kolo_iniciativa where mapa_id=1') === '5', 'hozeno mají všichni: 3 goblini, Hrun, Sindor');
  ok(await pj.locator('#raidSeznam .raid-box').count() === 5 && await pj.locator('#raidSeznam .raid-box.na-tahu').count() === 1, 'okno tvorů ukazuje pět tvorů v pořadí a jednoho na tahu');
  ok(await pj.locator('#raidSeznam .raid-box .ini-pips').count() === 5, 'u každého jsou tečky akcí');
  await pj.screenshot({ path: SHOTS + '/11_tahy_pj.png' });
  await hr.waitForTimeout(1500);
  await hr.screenshot({ path: SHOTS + '/12_tahy_hrac.png' });

  // --- tahy: mapa 1 nemá hexy, akce jdou jen odečíst v „Jiné“; proklikat celé kolo ---
  let videlZaHrace = false, videlMujTah = false;
  for (let i = 0; i < 30; i++) {
    if (sqlVal('select cislo_kola from kolo_stav where mapa_id=1') !== '1') break;
    const naTahu = sqlVal(`select ifnull(t.entita_id * (t.typ_entity='postava'), 0) from kolo_stav k join tokeny t on t.id=k.aktivni_token_id where k.mapa_id=1`);
    const jeSindor = naTahu === '2';
    const strana = jeSindor ? hr : pj;
    await strana.waitForTimeout(1300);
    if (jeSindor) {
      if (!videlMujTah) {
        videlMujTah = true;
        ok(/Jsi na tahu/.test(await txt(hr, '#bojKarta')), 'hráč na tahu: ' + (await txt(hr, '#bojKarta .bk-hlava')).replace(/\s+/g, ' '));
        ok(/nemá hexy/.test(await txt(hr, '#bojKarta')), 'na mapě bez hexů karta řekne, že pohyb a útok tu vyhodnotit nejde');
        ok(await hr.locator('#bojKarta [data-tah="zbran"]').count() === 1, 'karta ukazuje, co má postava v ruce');
      }
      if (!videlZaHrace && await pj.locator('#bojKarta [data-tah="za-hrace"]').count() === 1) {
        videlZaHrace = true;
        ok(await pj.locator('#bojKarta [data-tah="jine"]').count() === 0, 'PJ na tahu hráče tah nehraje — má jen „Hrát za něj“');
      }
    }
    if (!(await strana.$('#bojKarta [data-tah="jine"]'))) { console.log('žádné ovládání, konec smyčky'); break; }
    await strana.click('#bojKarta [data-tah="jine"]');
    await strana.click('#bojKarta [data-akce="cekani"]');
    await pj.waitForTimeout(1300);
  }
  ok(videlMujTah && videlZaHrace, 'v kole přišel na řadu hráč i PJ (a PJ viděl kartu „Hrát za něj“)');
  await pj.waitForTimeout(1500);
  await hr.waitForTimeout(1500);
  await pj.screenshot({ path: SHOTS + '/13_kolo2_pj.png' });
  ok(/Kolo 2/.test(await txt(pj, '#bojPoradi')), 'po odehrání všech akcí začalo samo kolo 2: ' + (await txt(pj, '#bojPoradi .boj-poradi-hl')).replace(/\s+/g, ' '));

  // --- kolo 2: akce z minula je předvybraná, stačí hodit ---
  ok(await hr.getAttribute('#bojKarta [data-umysl="kouzleni"]', 'aria-pressed') === 'true', 'ohlášená akce z minulého kola zůstala předvybraná');
  ok(await hr.locator('#bojKarta [data-hod="ini"]').count() === 1, 'hráč může rovnou hodit');
  ok(await pj.locator('#bojKarta .bk-radek', { hasText: 'Hrun' }).locator('select').inputValue() === 'utok', 'i tvorům PJ zůstala akce z minula');

  // --- nabídka PJ a konec ---
  await pj.click('#bojPoradi .boj-pj-btn');
  ok(await pj.locator('#bojPoradi [data-pj="ucastnici"]').count() === 1 && await pj.locator('#bojPoradi [data-pj="konec"]').count() === 1, 'nabídka PJ má „Kdo bojuje…“ a „Ukončit souboj“');
  await pj.click('#bojPoradi [data-pj="konec"]');
  await pj.waitForTimeout(1500);
  await hr.waitForTimeout(1800);
  ok(await pj.locator('#bojPoradi').isHidden() && await pj.locator('#bojKarta').isHidden(), 'po konci souboje zmizí pruh pořadí i karta');
  ok(await hr.locator('#bojPoradi').isHidden() && await hr.locator('#bojKarta').isHidden(), 'i u hráče');
  const log = await pj.$$eval('#logList div', d => d.map(x => x.textContent));
  console.log('LOG:\n' + log.slice(-12).join('\n'));
  ok(errs.length === 0, 'žádná chyba JavaScriptu ani serveru' + (errs.length ? ':\n' + errs.join('\n') : ''));
  for (const g of gob) await api(pj, 'token_smazat', { token_id: g, mapa_id: 1 });
  await browser.close();
  process.exit(process.exitCode || 0);
})().catch(e => { console.error('SELHÁNÍ', e); process.exit(1); });
