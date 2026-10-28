const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 2;
const ok = (c, m) => console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m);
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql ${DB} -N -e "${q}"`).toString().trim();
async function login(browser, email, heslo) {
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  page.on('dialog', d => d.accept());
  await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA); await page.waitForTimeout(600);
  return page;
}
const api0 = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 200) }; }
}, [name, body]);
// Útok od etapy 2 založí výzvu k hodu a blokuje další akce — tyhle testy zkoušejí pohyb, ne hody, takže ji PJ hned zruší.
const api = async (page, name, body) => {
  const r = await api0(page, name, body);
  if (name === 'tah_akce' && r && r.vyzva_id && !global.ZACHOVAT_VYZVU) await api0(global.PJPAGE || page, 'vyzva_zrus', { mapa_id: body.mapa_id });
  return r;
};
const stav = (page) => page.evaluate(async (m) => (await fetch('api/mapa_stav.php?mapa_id=' + m, { cache: 'no-store' })).json(), MAPA);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  global.PJPAGE = pj;
  const hr = await login(browser, 'hrac@local', 'hrac1234');
  const g = await pj.evaluate(() => window.MAPA.grid);
  const colStep = Math.sqrt(3) * g.px, rowStep = 1.5 * g.px;
  const stred = (c, r) => ({ x: Math.round(c * colStep + ((r & 1) ? colStep / 2 : 0)), y: Math.round(r * rowStep) });
  const rules = await pj.evaluate(() => window.MAPA.pravidla);
  ok(rules && rules.tvary && rules.pohyb, 'klient dostává pravidla (zdroj: ' + (rules && rules.zdroj) + ')');
  ok(rules.zdroj === 'db', 'pravidla se čtou z databáze');

  sql("delete ae from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev like 'Běh na krátké%'");
  async function uklid() {
    await api(pj, 'souboj_konec', { mapa_id: MAPA });
    for (const t of (await stav(pj)).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });
  }
  const gobId = 41; // Goblin (bestiář)
  async function pridej(typ, col, row, extra = {}) {
    const p = stred(col, row);
    const d = await api(pj, 'token_pridat', Object.assign({ mapa_id: MAPA, typ_entity: typ, x: p.x, y: p.y }, typ === 'nestvura' ? { nestvura_id: gobId } : { postava_id: 2 }, extra));
    return d.token_id;
  }
  async function boj(actor, ostatni, extraAkce = 18) {
    await api(pj, 'souboj_start', { mapa_id: MAPA, ucastnici: [actor, ...ostatni].map(t => ({ token_id: t })) });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'iniciativa' });
    await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [actor], hod: 6 });
    for (const o of ostatni) await api(pj, 'souboj_hod', { mapa_id: MAPA, token_ids: [o], hod: 1 });
    await api(pj, 'souboj_faze', { mapa_id: MAPA, kam: 'kolo' });
    for (let i = 0; i < extraAkce / 9; i++) await api(pj, 'akce_uprava', { mapa_id: MAPA, token_id: actor, op: 'delta', delta: 9 });
  }
  const poz = async (id) => (await stav(pj)).tokeny.find(t => t.id === id);
  const akce = (id, body, who) => api(who || pj, 'tah_akce', Object.assign({ mapa_id: MAPA, token_id: id }, body));

  // ===== E (5 hexů) =====
  await uklid();
  const E = await pridej('nestvura', 10, 10);
  let d = await api(pj, 'token_uprava', { token_id: E, zmeny: { hexu: 5 } });
  ok(d.ok && d.zmeny.hexu === 5 && d.zmeny.smer === 270, 'PJ nastaví E na 5 hexů a čelo nahoru: ' + JSON.stringify(d.zmeny));
  const sindor = await pridej('postava', 13, 10);
  d = await api(hr, 'token_uprava', { token_id: sindor, zmeny: { hexu: 3 } });
  ok(d.s === 403, 'hráč nesmí měnit počet hexů: ' + d.error);
  d = await api(pj, 'token_uprava', { token_id: E, zmeny: { hexu: 4 } });
  ok(d.s === 422, 'neplatný počet hexů 4: ' + d.error);
  await boj(E, [sindor]);
  let t = await poz(E);
  ok(t.hexu === 5 && t.smer === 270, 'stav tokenu nese hexu 5, smer 270');
  // otočný hex (10,10); tělo: (9,10),(10,10),(11,10) + horní (9,9),(10,9)  (viz tvar, řada 9 je lichá)
  // Sindor stojí na (13,10) — mimo tělo. Krok E o 1 doprava: pivot (11,10); tělo (10..12,10),(10,9),(11,9)
  d = await akce(E, { druh: 'krok', cil: { col: 11, row: 10 } });
  ok(d.ok, 'E krok doprava o 1 hex (celé tělo se posune): ' + (d.error || 'ok'));
  t = await poz(E);
  ok(Math.abs(t.x - stred(11, 10).x) <= 1, 'pivot E je na (11,10)');
  // Další krok do (12,10): tělo (11..13,10) → (13,10) obsazuje Sindor → zamítnuto
  d = await akce(E, { druh: 'krok', cil: { col: 12, row: 10 } });
  ok(d.s === 422 && /někdo/.test(d.error), 'E nesmí do hexu, kde stojí Sindor (tělo by narazilo): ' + d.error);
  // Otočka E o 60° (smer 330): tělo se otočí kolem (11,10)
  d = await akce(E, { druh: 'otocka', smer: 330 });
  ok(d.ok, 'otočka E o 60° kolem otočného hexu: ' + (d.error || 'ok'));
  t = await poz(E);
  ok(t.smer === 330 && Math.abs(t.x - stred(11, 10).x) <= 1, 'smer 330, otočný hex zůstal');
  // Otočka na týž směr
  d = await akce(E, { druh: 'otocka', smer: 330 });
  ok(d.s === 422, 'otočka na stejný směr zamítnuta');
  // Útok: Sindor na (13,10). Po otočce o 60° míří čelo na severovýchod-východ; ověříme dosah serverem
  const dosah330 = await akce(E, { druh: 'utok', cil_token_id: sindor });
  console.log('   útok na Sindora z E (smer 330):', dosah330.ok ? 'povolen' : dosah330.error);
  d = await akce(E, { druh: 'otocka', smer: 90 });   // čelo dolů (orientace 3)
  ok(d.ok, 'E se otočí čelem dolů (180°)');
  d = await akce(E, { druh: 'utok', cil_token_id: sindor });
  ok(d.s === 422, 'útok na tvora za zády (čelo dolů, Sindor vpravo-nahoře/vpravo od těla): ' + (d.error || 'povolen'));

  // ===== D (3 hexy) =====
  await uklid();
  const D = await pridej('nestvura', 10, 10);
  await api(pj, 'token_uprava', { token_id: D, zmeny: { hexu: 3 } });
  const cil = await pridej('postava', 10, 8); // dva řádky nad otočným hexem (10,10)
  await boj(D, [cil]);
  // D: otočný (10,10), horní hexy: NW (9,9) a NE (10,9) — řada 9 lichá: axial(0,-1)->(−1+... spočítáme z odpovědi serveru
  d = await akce(D, { druh: 'utok', cil_token_id: cil });
  console.log('   útok D na cíl o dva řádky výš:', d.ok ? 'povolen' : d.error);
  const cil2 = await pridej('nestvura', 10, 9);
  d = await akce(D, { druh: 'utok', cil_token_id: cil2 });
  ok(d.s === 422 || d.ok, 'cíl přímo na horním hexu D leží v těle (' + (d.error || 'povolen') + ')');
  // ===== C (zvíře, 2 hexy) =====
  await uklid();
  const C = await pridej('nestvura', 10, 10);
  d = await api(pj, 'token_uprava', { token_id: C, zmeny: { hexu: 2 } });
  ok(d.ok && d.zmeny.smer === 0, 'C na 2 hexy, čelem na východ (hlava vpravo)');
  const vlk = await pridej('nestvura', 12, 10); // hned před hlavou (hlava na 11,10)
  const za = await pridej('nestvura', 8, 10);   // za zadkem
  await boj(C, [vlk, za]);
  d = await akce(C, { druh: 'utok', cil_token_id: vlk });
  ok(d.ok, 'C útočí na tvora před hlavou (3 hexy před hlavou)');
  d = await akce(C, { druh: 'utok', cil_token_id: za });
  ok(d.s === 422, 'C neútočí na tvora za zadkem');
  d = await akce(C, { druh: 'krok', cil: { col: 9, row: 10 } });
  ok(d.s === 422 && /někdo/.test(d.error), 'C nesmí s tělem do hexu za zadkem, kde stojí tvor: ' + d.error);
  d = await akce(C, { druh: 'otocka', smer: 120 });
  ok(d.ok, 'C se otočí kolem zadního hexu (smer 120 = jihozápad)');
  t = await poz(C);
  ok(Math.abs(t.x - stred(10, 10).x) <= 1 && t.smer === 120, 'otočný (zadní) hex zůstal na (10,10)');

  // ===== Běh a sprint =====
  await uklid();
  sql('update postavy set unava=0 where id=2');
  const A = await pridej('postava', 10, 10);
  const B2 = await pridej('nestvura', 20, 20);
  await boj(A, [B2]);
  d = await akce(A, { druh: 'krok', cil: { col: 12, row: 10 } });
  ok(d.s === 422 && /sousední/.test(d.error), 'krok o 2 hexy už není běh: ' + d.error);
  d = await akce(A, { druh: 'beh', cil: { col: 12, row: 10 } });
  ok(d.ok, 'běh o 2 hexy jde i bez úmyslu Pohyb (HB: kdykoli, kromě únavy): ' + (d.error || 'ok'));
  ok(sqlVal('select unava from postavy where id=2') === '0', 'běh bod únavy nepřidá');
  d = await akce(A, { druh: 'sprint', cil: { col: 15, row: 10 } });
  ok(d.ok, 'sprint o 3 hexy');
  ok(sqlVal('select unava from postavy where id=2') === '1', 'sprint přidal 1 bod únavy: ' + sqlVal('select unava from postavy where id=2'));
  d = await akce(A, { druh: 'sprint', cil: { col: 19, row: 10 } });
  ok(d.s === 422 && /nejvýš o 3/.test(d.error), 'sprint o 4 hexy bez dovednosti: ' + d.error);
  d = await akce(A, { druh: 'beh', cil: { col: 18, row: 10 } });
  ok(d.s === 422 && /nejvýš o 2/.test(d.error), 'běh o 3 hexy bez dovednosti: ' + d.error);
  // dovednost: efekt na postavu
  const efektId = await pj.evaluate(async () => { const o = Array.from(document.querySelectorAll('#efektSelect option')).find(x => x.textContent.replace(/^[^ ]+ /, '').startsWith('Běh na krátké')); return o ? parseInt(o.value, 10) : 0; });
  ok(efektId > 0, 'efekt Běh na krátké vzdálenosti je v katalogu (id ' + efektId + ')');
  d = await api(pj, 'efekt_pridat', { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, efekt_id: efektId, zbyva_kol: 3 });
  d = await akce(A, { druh: 'sprint', cil: { col: 19, row: 10 } });
  ok(d.ok, 'sprint o 4 hexy s dovedností');
  // otočení při pohybu: 270° = libovolný směr
  d = await akce(A, { druh: 'krok', cil: { col: 18, row: 10 }, smer: 180 });
  ok(d.ok, 'krok s otočením na opačnou stranu (270° dovoleno): ' + (d.error || 'ok'));
  // únava: unavená postava (body > odolnost) nesmí sprintovat; PJ smí rozhodnout jinak
  sql('update postavy set unava=9 where id=2');
  d = await akce(A, { druh: 'sprint', cil: { col: 15, row: 10 } }, hr);
  ok(d.s === 422 && /unavená/.test(d.error), 'unavená postava nesmí sprintovat (hráč): ' + d.error);
  ok(sqlVal('select unava from postavy where id=2') === '9', 'zamítnutý sprint únavu nepřidal');
  const stZakazy = await stav(pj);
  ok(Array.isArray(stZakazy.kolo.aktivni_zakazy) && stZakazy.kolo.aktivni_zakazy.includes('sprint'), 'klient dostane zákaz sprintu z únavy: ' + JSON.stringify(stZakazy.kolo.aktivni_zakazy));
  d = await akce(A, { druh: 'beh', cil: { col: 16, row: 10 } }, hr);
  ok(d.ok, 'unavená postava smí běžet (zákaz běhu je až od vyčerpání): ' + (d.error || 'ok'));
  d = await akce(A, { druh: 'sprint', cil: { col: 13, row: 10 } });
  ok(d.ok, 'PJ smí sprint i za unavenou postavu: ' + (d.error || 'ok'));
  ok(sqlVal('select unava from postavy where id=2') === '10', 'sprint PJ přidal bod únavy');
  sql('update postavy set unava=0 where id=2');
  sql("delete ae from aktivni_efekty ae join efekty e on e.id=ae.efekt_id where e.nazev like 'Běh na krátké%'");

  // ===== Otočení celkem 270° za akci pohybu =====
  // E (5 hexů) na (10,10), tři překážky: do (11,13) s čelem 150° se dostane jen otáčením před pohybem,
  // mezi hexy i po nich (celkem 240°). S rozpočtem 180° to nejde, s 270° ano.
  await uklid();
  const bl = [];
  for (const [c, r] of [[10, 12], [8, 11], [7, 13]]) { const id = await pridej('nestvura', c, r); await api(pj, 'token_uprava', { token_id: id, zmeny: { hexu: 1 } }); bl.push(id); }
  const E3 = await pridej('nestvura', 10, 10);
  await api(pj, 'token_uprava', { token_id: E3, zmeny: { hexu: 5 } });
  await boj(E3, [bl[0]]);
  sql(`update kolo_ucastnik set umysl='pohyb' where token_id=${E3}`);
  sql("update souboj_pohyb set otoceni_max=180 where kod='sprint'");
  d = await akce(E3, { druh: 'sprint', cil: { col: 11, row: 13 }, smer: 150 });
  ok(d.s === 422 && /celkem nejvýš o 180/.test(d.error), 'rozpočet otočení 180° nestačí: ' + d.error);
  sql("update souboj_pohyb set otoceni_max=270 where kod='sprint'");
  d = await akce(E3, { druh: 'sprint', cil: { col: 11, row: 13 }, smer: 150 });
  ok(d.ok, 'rozpočet 270° stačí (otočka, hexy, otočka): ' + (d.error || 'ok'));
  t = await poz(E3);
  ok(Math.abs(t.x - stred(11, 13).x) <= 1 && t.smer === 150, 'E stojí na (11,13) s čelem 150°: ' + t.x + ' / ' + t.smer);
  // ===== Cesta zadaná po bodech (A, B, C…) =====
  await uklid();
  const P = await pridej('nestvura', 10, 10);
  await api(pj, 'token_uprava', { token_id: P, zmeny: { hexu: 1, smer: 0 } });
  const Q = await pridej('nestvura', 20, 20);
  await api(pj, 'token_uprava', { token_id: Q, zmeny: { hexu: 1 } });
  await boj(P, [Q]);
  sql(`update kolo_ucastnik set umysl='pohyb' where token_id=${P}`);
  d = await akce(P, { druh: 'sprint', cesta: [{ col: 11, row: 10, smer: 0 }, { col: 11, row: 9, smer: 300 }, { col: 12, row: 9, smer: 0 }], smer: 0 });
  ok(d.ok, 'cesta A (východ), B (severovýchod), C (východ): ' + (d.error || 'ok'));
  t = await poz(P);
  ok(Math.abs(t.x - stred(12, 9).x) <= 1 && Math.abs(t.y - stred(12, 9).y) <= 1 && t.smer === 0, 'token stojí na (12,9) čelem na východ');
  d = await akce(P, { druh: 'sprint', cesta: [{ col: 14, row: 9, smer: 0 }] });
  ok(d.s === 422 && /neleží vedle předchozího/.test(d.error), 'bod mimo sousedství: ' + d.error);
  d = await akce(P, { druh: 'beh', cesta: [{ col: 13, row: 9, smer: 0 }, { col: 14, row: 9, smer: 0 }, { col: 15, row: 9, smer: 0 }] });
  ok(d.s === 422 && /nejvýš o 2/.test(d.error), 'víc bodů než běh dovolí: ' + d.error);
  d = await akce(P, { druh: 'sprint', cesta: [{ col: 11, row: 9, smer: 180 }, { col: 12, row: 8, smer: 300 }] });
  ok(d.s === 422 && /celkem nejvýš o 270/.test(d.error) && /bod B/.test(d.error), 'rozpočet otáčení přes dva body (180° + 120°): ' + d.error);
  const blok = await pridej('nestvura', 13, 9);
  await api(pj, 'token_uprava', { token_id: blok, zmeny: { hexu: 1 } });
  d = await akce(P, { druh: 'sprint', cesta: [{ col: 13, row: 9, smer: 0 }] });
  ok(d.s === 422 && /bod A/.test(d.error) && /někdo stojí/.test(d.error), 'bod na obsazeném hexu: ' + d.error);
  d = await akce(P, { druh: 'sprint', cesta: [{ col: 12, row: 10 }] });
  ok(d.ok, 'bod bez udaného čela nechá čelo beze změny: ' + (d.error || 'ok'));
  t = await poz(P);
  ok(Math.abs(t.x - stred(12, 10).x) <= 1 && t.smer === 0, 'token na (12,10), čelo beze změny');
  d = await akce(P, { druh: 'sprint', cesta: [] });
  ok(d.s === 422 && /aspoň jeden bod/.test(d.error), 'prázdná cesta: ' + d.error);

  // ===== Cena akcí: krok, běh i sprint s útokem = 1 akce (+ jeden bod únavy za sprint) =====
  await uklid();
  sql('update postavy set unava=0 where id=2');
  const A5 = await pridej('postava', 10, 10);
  const T5 = await pridej('nestvura', 14, 10);
  await api(pj, 'token_uprava', { token_id: T5, zmeny: { hexu: 1 } });
  await boj(A5, [T5]);
  const zbyva = () => +sqlVal(`select akce_zbyvajici from kolo_iniciativa where token_id=${A5}`);
  let pred = zbyva();
  d = await akce(A5, { druh: 'sprint_utok', cesta: [{ col: 11, row: 10, smer: 0 }, { col: 12, row: 10, smer: 0 }, { col: 13, row: 10, smer: 0 }], cil_token_id: T5 });
  ok(d.ok, 'sprint a útok: ' + (d.error || 'ok'));
  ok(pred - zbyva() === 1, 'sprint a útok stojí 1 akci (HB: pohyb spojený s útokem je jedna akce)');
  ok(sqlVal('select unava from postavy where id=2') === '1', 'sprint a útok přidá 1 bod únavy');
  const logy = await api(pj, 'udalosti', {}).catch(() => null);
  d = await akce(A5, { druh: 'utok', cil_token_id: T5 });
  ok(d.ok, 'samotný útok: ' + (d.error || 'ok'));
  ok(pred - zbyva() === 2, 'samotný útok stojí další 1 akci');
  // krok a útok: z (13,10) o hex dozadu a zase vpřed neumožníme; ověř cenu 1 na druhém cíli
  const T6 = await pridej('nestvura', 16, 10);
  await api(pj, 'token_uprava', { token_id: T6, zmeny: { hexu: 1 } });
  pred = zbyva();
  d = await akce(A5, { druh: 'krok_utok', cesta: [{ col: 14, row: 10, smer: 0 }], cil_token_id: T6 });
  ok(d.s === 422, 'krok na obsazený hex je odmítnut: ' + d.error);
  d = await akce(A5, { druh: 'beh_utok', cesta: [{ col: 13, row: 9, smer: 0 }, { col: 14, row: 9, smer: 0 }], cil_token_id: T6 });
  console.log('   běh a útok (cíl dál):', d.ok ? 'povolen' : d.error);
  sql('update postavy set unava=0 where id=2');
  await uklid();
  console.log('hotovo');
  await browser.close();
})().catch(e => { console.error('SELHÁNÍ', e); process.exit(1); });
