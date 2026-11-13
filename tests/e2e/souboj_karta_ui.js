// Nový průběh souboje (návrh schválený 2026-11-12), kroky 4–7 a telefon: karta hodu na útok a na obranu, výsledek,
// nabídka PJ a rozložení na telefonu. Ohlášení akce a iniciativu hlídá souboj_ui.js, tah na mapě hex_tah_ui.js,
// kouzla kouzleni_ui.js.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node souboj_karta_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: ['--no-sandbox', '--use-gl=swiftshader', '--enable-webgl', '--ignore-gpu-blocklist'] });
  const prihlas = async (email, heslo, w, h) => {
    const p = await (await browser.newContext({ viewport: { width: w || 1280, height: h || 860 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const api = async (name, body) => (await pj.request.post(BASE + '/hra/api/' + name + '.php', { data: Object.assign({ mapa_id: MAPA }, body) })).json();
  const stav = async () => (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json();
  const txt = async (p, sel) => (await p.locator(sel).innerText().catch(() => '')).replace(/\s+/g, ' ').trim();

  // Sindor (hráč) drží Cep a stojí dva hexy od goblina, čelem k němu; je první na tahu se dvěma akcemi.
  await api('souboj_konec', {});
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id });
  sql("delete from souboj_vyzva; update postavy set drzena_zbran_id=39, unava=0, aktualni_hp=5, max_hp=5 where id=2; update postava_predmety set u_sebe=1 where postava_id=2");
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA); await pj.waitForTimeout(900);
  const g = await pj.evaluate(() => window.MAPA.grid);
  const cs = Math.sqrt(3) * g.px, rs = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * cs + ((r & 1) ? cs / 2 : 0)), y: Math.round(r * rs) });
  const pridej = async (typ, c, r, navic) => (await api('token_pridat', Object.assign({ typ_entity: typ, x: stred(c, r).x, y: stred(c, r).y }, navic))).token_id;
  const gob = await pridej('nestvura', 10, 10, { nestvura_id: parseInt(sqlVal("select id from nestvury where nazev='Goblin'"), 10) });
  const sindor = await pridej('postava', 12, 10, { postava_id: 2 });
  await api('token_uprava', { token_id: sindor, zmeny: { smer: 3 } });
  const gobInst = sqlVal(`select entita_id from tokeny where id=${gob}`);
  sql(`update nestvura_instance set aktualni_hp=20, max_hp=20 where id=${gobInst}`);
  await api('souboj_start', { ucastnici: [{ token_id: gob }, { token_id: sindor }] });
  await api('umysl_nastavit', { token_ids: [sindor], umysl: 'utok' });
  await api('souboj_hod', { token_ids: [sindor], hod: 6, auto: true });
  const posledni = await api('souboj_hod', { token_ids: [gob], hod: 1, auto: true });
  ok(posledni.zacalo_kolo === true && sqlVal(`select faze from kolo_stav where mapa_id=${MAPA}`) === 'kolo', 'po hodu posledního účastníka začnou tahy samy');

  const hr = await prihlas('hrac@local', 'hrac1234');
  await pj.reload(); await hr.goto(BASE + '/hra/mapa.php?id=' + MAPA); await hr.waitForTimeout(1800);
  const priblizit = async (p) => { await p.click('#zoom-fit'); const q = await (await p.$('.vtt-token[data-id="' + sindor + '"] .puck')).boundingBox(); await p.mouse.move(q.x, q.y); await p.mouse.wheel(0, -600); await p.waitForTimeout(500); };
  await priblizit(hr); await priblizit(pj);
  const stredTokenu = (p, id) => p.$eval('.vtt-token[data-id="' + id + '"] .puck', (e) => { const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });

  // ===== krok 3 → 4: klepnutí na nepřítele, potvrzení, hod na útok =====
  { const b = await stredTokenu(hr, gob); await hr.mouse.click(b.x, b.y); await hr.waitForTimeout(600); }
  ok(/Krok a útok → Goblin/.test(await txt(hr, '#bojKarta .bk-plan-text')), 'klepnutí na nepřítele naplánuje cestu i útok: ' + await txt(hr, '#bojKarta .bk-plan-text'));
  ok(/Cep/.test(await txt(hr, '#bojKarta .bk-plan-text span')), 'plán říká, kterou zbraní se útočí (tou v ruce)');
  await hr.click('#bojKarta [data-plan="potvrdit"]'); await hr.waitForTimeout(2200);
  const vUtok = (await stav()).vyzvy.find((v) => v.druh === 'utok');
  ok(!!vUtok, 'útok založil výzvu k hodu');
  ok(await hr.locator('#bojKarta').isHidden(), 'karta tahu se pod výzvou k hodu schová (dole je vždy jen jedna karta)');
  ok(/^Hoď na útok/.test(await txt(hr, '.vyzva-utok .vyzva-hlava')), 'hráč dostane výzvu „Hoď na útok“: ' + await txt(hr, '.vyzva-utok .vyzva-hlava'));
  ok(/Cep → Goblin/.test(await txt(hr, '.vyzva-utok .vyzva-pod')), 'pod nadpisem je zbraň a cíl');
  const cisloNaKarte = await txt(hr, '.vyzva-utok .bk-stitek.cislo strong');
  ok(cisloNaKarte === String(vUtok.zaklad) && vUtok.zaklad !== null, 'útočné číslo je vidět rovnou a sedí se serverem: ' + cisloNaKarte);
  ok(await hr.locator('.vyzva-utok input').count() === 0, 'hráč u hodu nic nevyplňuje (žádné pole)');
  ok(await hr.locator('.vyzva-utok .vyzva-pj').count() === 0, 'úpravy čísel hráč nemá');
  ok((await txt(hr, '.vyzva-utok [data-hod="vyzva"]')) === 'Hodit kostkou', 'jedno hlavní tlačítko „Hodit kostkou“');
  await hr.click('.vyzva-utok button:has-text("Hodil jsem u stolu")'); await hr.waitForTimeout(300);
  await hr.fill('.vyzva-utok .vyzva-akce input', '4');
  await hr.click('.vyzva-utok button:has-text("Zapsat vlastní hod")'); await hr.waitForTimeout(2200);
  ok(sqlVal(`select stav from souboj_vyzva where id=${vUtok.id}`) !== 'ceka', 'vlastní hod se zapsal');

  // ===== krok 5: obrana — nestvůru brání PJ: volba a pak hod =====
  await pj.waitForTimeout(1500);
  const vObr = (await stav()).vyzvy.find((v) => v.druh === 'obrana');
  ok(!!vObr && /^Obrana — Goblin/.test(await txt(pj, '.vyzva-obrana .vyzva-hlava')), 'PJ dostane kartu obrany nestvůry: ' + await txt(pj, '.vyzva-obrana .vyzva-hlava'));
  ok(await pj.locator('.vyzva-obrana [data-volba]').count() === 2, 'karta má dvě volby: Bráním se / Nebráním se');
  ok(/stojí akci/.test(await txt(pj, '.vyzva-obrana [data-volba="branit"]')) && /−3/.test(await txt(pj, '.vyzva-obrana [data-volba="nebranit"]')), 'u obou voleb je vidět, co stojí');
  ok(await pj.locator('.vyzva-obrana .vyzva-pj').count() === 1, 'PJ má úpravy čísel pod „Upravit čísla…“');
  ok(await hr.locator('.vyzva-obrana [data-volba]:not([disabled])').count() === 0 && await hr.locator('.vyzva-obrana [data-hod="vyzva"]').count() === 0, 'hráč za cizí nestvůru volit ani házet nesmí');
  await api('vyzva_uprava', { vyzva_id: vObr.id, zaklad: 3 });
  await pj.waitForTimeout(1500);
  ok(await pj.locator('.vyzva-obrana [data-hod="vyzva"]').count() === 0, 'dokud není zvoleno, hodit nejde');
  await pj.click('.vyzva-obrana [data-volba="nebranit"]'); await pj.waitForTimeout(1500);
  ok(await pj.getAttribute('.vyzva-obrana [data-volba="nebranit"]', 'aria-pressed') === 'true', 'zvolená možnost je zvýrazněná');
  ok(await pj.locator('.vyzva-obrana [data-hod="vyzva"]').count() === 1, 'po volbě se hází v obou případech');
  await pj.fill('.vyzva-obrana .vyzva-akce input', '1');
  await pj.click('.vyzva-obrana button:has-text("Zapsat vlastní hod")'); await pj.waitForTimeout(2500);

  // ===== krok 6: výsledek se ukáže a sám zmizí =====
  await hr.waitForTimeout(600);
  ok(await pj.locator('.boj-vysledek .vyzva-vysledek').count() === 1 && await hr.locator('.boj-vysledek .vyzva-vysledek').count() === 1, 'výsledek vidí PJ i hráč');
  ok(/Zásah|Minul/.test(await txt(hr, '.boj-vysledek')), 'hráč: ' + await txt(hr, '.boj-vysledek'));
  ok(!/proti obraně|zranění \d|životů/.test(await txt(hr, '.boj-vysledek')), 'hráč čísla nevidí (jen jestli to sedlo)');
  ok(/proti obraně/.test(await txt(pj, '.boj-vysledek')), 'PJ čísla vidí: ' + await txt(pj, '.boj-vysledek'));
  ok(await hr.locator('.boj-vysledek button:has-text("Zavřít")').count() === 0, 'výsledek nemá tlačítko, které by se muselo odklikávat');
  // Sindor měl v 1. půlce jednu akci, takže tah přešel na goblina: hra jde dál sama, nikdo nic neodklikává.
  ok(sqlVal(`select aktivni_token_id from kolo_stav where mapa_id=${MAPA}`) === String(gob), 'po vyhodnocení jde tah sám dál (na řadě je goblin)');
  ok(/Goblin/.test(await txt(hr, '#bojCentr')), 'hráč vidí, že je na tahu nepřítel (tlačítko „kdo je na tahu“): ' + await txt(hr, '#bojCentr'));
  ok(await pj.locator('#bojKarta [data-tah="jine"]').count() === 1 && /Na tahu: Goblin/.test(await txt(pj, '#bojKarta .bk-nadpis')), 'PJ má pro nestvůru stejnou kartu tahu jako hráč');
  const kartaBox = await pj.locator('#bojKarta').boundingBox(), vyslBox = await pj.locator('.boj-vysledek').boundingBox(), pruhBox = await pj.locator('#bojPoradi').boundingBox();
  ok(vyslBox.y >= pruhBox.y + pruhBox.height - 1 && vyslBox.y + vyslBox.height <= kartaBox.y, 'výsledek leží mezi pruhem pořadí a kartou tahu, nic nepřekrývá');
  await hr.waitForTimeout(5200);
  ok(await hr.locator('.boj-vysledek').isHidden(), 'výsledek po pár vteřinách zmizí sám');

  // ===== krok 7: nabídka PJ =====
  await pj.click('#bojPoradi .boj-pj-btn'); await pj.waitForTimeout(200);
  await pj.click('#bojPoradi [data-pj="akce"]'); await pj.waitForTimeout(200);
  const akcePred = parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${gob}`), 10);
  await pj.locator(`#bojPoradi .boj-pj-akce[data-token-id="${gob}"] button`, { hasText: '+1' }).click(); await pj.waitForTimeout(1300);
  ok(parseInt(sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${gob}`), 10) === akcePred + 1, 'PJ → Upravit akce: +1 akce goblinovi');
  if (!(await pj.locator('#bojPoradi [data-pj="preskocit"]').count())) { await pj.click('#bojPoradi .boj-pj-btn'); await pj.waitForTimeout(200); }
  const aktivniPred = sqlVal(`select aktivni_token_id from kolo_stav where mapa_id=${MAPA}`);
  await pj.click('#bojPoradi [data-pj="preskocit"]'); await pj.waitForTimeout(1500);
  ok(sqlVal(`select aktivni_token_id from kolo_stav where mapa_id=${MAPA}`) !== aktivniPred, 'PJ → Přeskočit tah: na řadě je další');

  // ===== krok 5 z druhé strany: goblin útočí na hráče =====
  let k = (await stav()).kolo;
  for (let i = 0; i < 6 && k.aktivni_token_id !== gob; i++) { await api('tah_konec', {}); k = (await stav()).kolo; }
  await api('token_uprava', { token_id: gob, zmeny: { smer: 0 } });
  await api('tah_akce', { token_id: gob, druh: 'utok', cil_token_id: sindor });
  const vG = (await stav()).vyzvy.find((v) => v.druh === 'utok');
  if (vG) { await api('vyzva_uprava', { vyzva_id: vG.id, zaklad: 3 }); await api('vyzva_hod', { vyzva_id: vG.id, hody: [3] }); }
  await hr.waitForTimeout(2500);
  ok(/Goblin na tebe útočí/.test(await txt(hr, '.vyzva-obrana.moje .vyzva-hlava')), 'napadený hráč: ' + await txt(hr, '.vyzva-obrana .vyzva-hlava'));
  ok(await hr.locator('.vyzva-obrana [data-volba]:not([disabled])').count() >= 1, 'hráč volí sám, jestli se brání');
  await hr.click('.vyzva-obrana [data-volba="nebranit"]'); await hr.waitForTimeout(1500);
  ok(/^\d+$/.test(await txt(hr, '.vyzva-obrana .bk-stitek.cislo strong')), 'obranné číslo je vidět rovnou: ' + await txt(hr, '.vyzva-obrana .bk-stitek.cislo'));
  ok(await hr.locator('.vyzva-obrana [data-hod="vyzva"]').count() === 1, 'a hází si sám');
  await hr.screenshot({ path: (process.env.OUT || '/tmp/dracak-tests') + '/souboj_karta_obrana.png' });
  // Šestka se u 1k6+ přehazuje, součet 6 tedy padnout nemůže: server ho odmítne a hráč to musí vidět na kartě.
  await hr.click('.vyzva-obrana button:has-text("Hodil jsem u stolu")'); await hr.fill('.vyzva-obrana .vyzva-akce input', '6');
  await hr.click('.vyzva-obrana button:has-text("Zapsat vlastní hod")'); await hr.waitForTimeout(1500);
  ok(await hr.locator('.vyzva-panel .bk-chyba').count() === 1, 'odmítnutý hod se hráči vypíše na kartě: ' + await txt(hr, '.vyzva-panel .bk-chyba'));
  await hr.fill('.vyzva-obrana .vyzva-akce input', '5');
  await hr.click('.vyzva-obrana button:has-text("Zapsat vlastní hod")'); await hr.waitForTimeout(2000);
  { const zb = (await stav()).vyzvy; ok(zb.length === 0, 'po hodu na obranu je útok vyhodnocený' + (zb.length ? ': zbývá ' + JSON.stringify(zb.map((v) => [v.druh, v.stav, v.label, v.volba, v.smi_hodit])) + ' | karta: ' + await txt(hr, '.vyzva-panel') : '')); }
  ok(pj.chybyJs.length === 0 && hr.chybyJs.length === 0, 'žádná chyba JavaScriptu ' + pj.chybyJs.concat(hr.chybyJs).join(' | '));

  // ===== telefon: karta nad lištou nástrojů, pruh pořadí nahoře, nic nepřetéká =====
  k = (await stav()).kolo;
  for (let i = 0; i < 8 && k && k.aktivni_token_id !== sindor; i++) {
    if (k.faze !== 'kolo') { await api('souboj_hod', { token_ids: [sindor], hod: 6, auto: true }); await api('souboj_hod', { token_ids: [gob], hod: 1, auto: true }); }
    else await api('tah_konec', {});
    k = (await stav()).kolo;
  }
  const tel = await prihlas('hrac@local', 'hrac1234', 390, 844);
  await tel.goto(BASE + '/hra/mapa.php?id=' + MAPA); await tel.waitForTimeout(2200);
  ok(await tel.locator('#bojKarta').isVisible() && await tel.locator('#bojPoradi').isVisible(), 'telefon: karta tahu i pruh pořadí jsou vidět');
  ok(await tel.locator('#bojPoradi .boj-cip.na-tahu').count() === 1 && await tel.locator('#raidPanel').isHidden(), 'telefon: pořadí je ve vodorovném pruhu, okno tvorů se samo neotvírá (zabralo by půl obrazovky)');
  const kb = await tel.locator('#bojKarta').boundingBox(), pb = await tel.locator('#bojPoradi').boundingBox(), lb = await tel.locator('.toolbar').boundingBox();
  ok(kb.x >= 0 && kb.x + kb.width <= 390 && pb.x >= 0 && pb.x + pb.width <= 390, 'telefon: karta ani pruh nevyčnívají do stran');
  ok(kb.y + kb.height <= lb.y + 1, 'telefon: karta sedí nad lištou nástrojů, nepřekrývá ji');
  ok(pb.y + pb.height <= kb.y, 'telefon: pruh pořadí kartu nepřekrývá');
  ok(await tel.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'telefon: stránka nepřetéká vodorovně');
  // Nic plovoucího nesmí ležet přes tlačítka karty (dřív je kryla lupa, kostky a „kdo je na tahu“).
  const zakryta = await tel.$$eval('#bojKarta .bk-tlacitka button', (bs) => bs.filter((b) => {
    const r = b.getBoundingClientRect();
    const e = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return !(e && (e === b || b.contains(e)));
  }).map((b) => b.innerText.trim().replace(/\s+/g, ' ')));
  ok(zakryta.length === 0, 'telefon: všechna tlačítka karty jdou zmáčknout, nic je nepřekrývá' + (zakryta.length ? ' — zakrytá: ' + zakryta.join(', ') : ''));
  const kostkyB = await tel.locator('#kostkyOtevrit').boundingBox();
  ok(!!kostkyB && (kostkyB.x >= pb.x + pb.width || kostkyB.y >= pb.y + pb.height), 'telefon: házecí místo zůstává dostupné a neleží přes pruh pořadí');
  const vysky = await tel.$$eval('#bojKarta .bk-tlacitka button', (bs) => bs.map((b) => Math.round(b.getBoundingClientRect().height)));
  ok(vysky.length >= 3 && vysky.every((h) => h >= 44), 'telefon: tlačítka karty jdou trefit prstem (' + vysky.join(', ') + ' px)');
  await tel.screenshot({ path: (process.env.OUT || '/tmp/dracak-tests') + '/souboj_karta_telefon.png' });
  ok(tel.chybyJs.length === 0, 'telefon: žádná chyba JavaScriptu ' + tel.chybyJs.join(' | '));

  await api('souboj_konec', {});
  for (const t of (await stav()).tokeny) await api('token_smazat', { token_id: t.id });
  sql('delete from souboj_vyzva; update postavy set drzena_zbran_id=NULL where id=2');
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  await browser.close();
  process.exit(chyb ? 1 : 0);
})().catch((e) => { console.log('PÁD ' + e.message); process.exit(1); });
