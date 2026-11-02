// Zóny kouzel Tma (Obscurum) a Temnota (druid) — includes/vtt_videni.php, DRACAK_VTT_ZONY_TMY (bez nového SQL: token se svetlo_typ 'tma' / 'temnota' a poloměrem ve svetlo_sahy).
// Tma: uvnitř nic nesvítí, zdroj světla uvnitř nesvítí vůbec, infravidění nefunguje (pozorovatel ani cíl v zóně). Temnota: světlo vidět jen 0,3 sáhu, infravidění 1/10 dosahu.
// Zóna sama není zdroj světla. Mapa 1 (čtvercový grid 70 px = 1 sáh). Po resetu dat: mysql dracak < ../fixture/seed.sql; node zony_tmy_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const MAPA = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', e => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const tokenyHrace = async () => ((await (await hrac.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny || []).map(t => t.id);
  const mlha = async () => (await hrac.request.get(BASE + '/hra/api/mlha_stav.php?mapa_id=' + MAPA)).json();
  const pocet = (b64) => [...Buffer.from(b64, 'base64')].filter(x => x === 1).length;
  sql(`delete from zdi where mapa_id=${MAPA}; delete from mlha_valky where mapa_id=${MAPA}`);
  for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: MAPA });

  const h = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'postava', postava_id: 2, x: 665, y: 315 })).token_id;   // sloupec 9, řádek 4
  const gob = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'nestvura', nestvura_id: 41, x: 1015, y: 315 })).token_id;   // 5 sáhů vpravo
  ok(h && gob, 'token hráče a nestvůry jsou na mapě');
  await api(pj, 'mapa_osvetleni', { mapa_id: MAPA, osvetleni: 'tma' });
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden' } });
  ok((await tokenyHrace()).includes(gob), 'základ: pochodeň (6 sáhů) osvětlí nestvůru 5 sáhů daleko, hráč ji vidí');
  const videnoZaklad = pocet((await mlha()).videne_b64);

  // --- oprávnění a meze ---
  const zona = (await api(pj, 'token_pridat', { mapa_id: MAPA, typ_entity: 'obecny', nazev: 'Tma', x: 1015, y: 315 })).token_id;
  ok(zona > 0, 'PJ založí obecný token pro zónu');
  let r = await api(hrac, 'token_uprava', { token_id: zona, zmeny: { svetlo: 3, svetlo_typ: 'tma' } });
  ok(r.s === 403, 'hráč zónu tmy nastavit nesmí (403): ' + r.s);
  r = await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 301, svetlo_typ: 'tma' } });
  ok(r.s === 422, 'zóna větší než 300 sáhů se odmítne (422): ' + r.s);
  r = await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 61, svetlo_typ: 'pochoden' } });
  ok(r.s === 422, 'obyčejné světlo nad 60 sáhů se pořád odmítne (422): ' + r.s);
  r = await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 3, svetlo_typ: 'noc' } });
  ok(r.s === 422, 'neznámý druh zóny se odmítne (422): ' + r.s);
  ok(sqlVal(`select ifnull(svetlo_sahy,'-') from tokeny where id=${zona}`) === '-', 'odmítnuté změny nic nezapsaly');

  // --- Tma (Obscurum) kolem nestvůry: světlo dovnitř nedosáhne ---
  r = await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 3, svetlo_typ: 'tma', viditelny: false } });
  ok(r.s === 200, 'PJ nastaví zónu Tma o poloměru 3 sáhy kolem nestvůry');
  ok(!(await tokenyHrace()).includes(gob), 'nestvůra uvnitř Tmy není vidět, ani když ji osvětluje pochodeň v dosahu');
  ok(!(await tokenyHrace()).includes(zona), 'zónu jako token hráč nevidí (skrytá)');
  ok(pocet((await mlha()).videne_b64) < videnoZaklad, 'osvětlená plocha se o zónu zmenšila (' + pocet((await mlha()).videne_b64) + ' < ' + videnoZaklad + ')');
  await api(pj, 'token_presun', { token_id: gob, x: 1015 + 70 * 4, y: 315 });   // 9 sáhů od hráče: mimo pochodeň (6) i mimo zónu (končí 8 sáhů od hráče)
  ok(!(await tokenyHrace()).includes(gob), 'mimo pochodeň a mimo zónu ho taky nevidí (kontrola, že zóna nesvítí)');
  await api(pj, 'token_presun', { token_id: gob, x: 1015, y: 315 });

  // --- zdroj světla uvnitř Tmy nesvítí vůbec ---
  await api(pj, 'token_presun', { token_id: zona, x: 665, y: 315 });   // zóna se přesune na hráče
  ok(pocet((await mlha()).videne_b64) === 1, 'pochodeň uvnitř Tmy nesvítí: hráč vidí jedinou buňku — tu, na které stojí');
  ok(!(await tokenyHrace()).includes(gob), 'nestvůru 5 sáhů daleko při zhasnuté pochodni nevidí');

  // --- infravidění ve Tmě ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: null, svetlo_typ: null, infra: 20 } });
  await api(pj, 'token_presun', { token_id: zona, x: 1415, y: 315 });   // zóna 10 sáhů od hráče, mimo cíl i pozorovatele
  ok((await tokenyHrace()).includes(gob), 'infravidění (20) ukáže nestvůru 5 sáhů daleko, když zóna stojí jinde');
  await api(pj, 'token_presun', { token_id: zona, x: 1015, y: 315 });   // zóna na nestvůře
  ok(!(await tokenyHrace()).includes(gob), 'Tma vyřazuje infravidění: nestvůra uvnitř zóny není vidět');
  await api(pj, 'token_presun', { token_id: zona, x: 665, y: 315 });   // zóna na pozorovateli
  ok(!(await tokenyHrace()).includes(gob), 'Tma vyřazuje infravidění i tomu, kdo stojí uvnitř zóny');
  await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: null, svetlo_typ: null } });
  ok((await tokenyHrace()).includes(gob), 'po zrušení zóny infravidění zase funguje');

  // --- Temnota (druid): světlo jen 0,3 sáhu, infravidění 1/10 ---
  await api(pj, 'token_uprava', { token_id: h, zmeny: { svetlo: 6, svetlo_typ: 'pochoden', infra: null } });
  await api(pj, 'token_presun', { token_id: gob, x: 665 + 70, y: 315 });   // sousední buňka, 1 sáh
  ok((await tokenyHrace()).includes(gob), 'bez Temnoty pochodeň osvětlí i sousední buňku');
  r = await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 300, svetlo_typ: 'temnota', viditelny: false } });
  ok(r.s === 200, 'PJ rozprostře Temnotu o poloměru 300 sáhů kolem hráče');
  ok(!(await tokenyHrace()).includes(gob), 'v Temnotě pochodeň nedosvítí na sousední buňku (je vidět jen na 30 coulů = 0,3 sáhu)');
  ok(pocet((await mlha()).videne_b64) === 1, 'v Temnotě hráč s pochodní vidí jen svou buňku: ' + pocet((await mlha()).videne_b64));
  await api(pj, 'token_uprava', { token_id: h, zmeny: { infra: 20 } });
  ok((await tokenyHrace()).includes(gob), 'infravidění 20 v Temnotě má dosah 2 sáhy: nestvůru 1 sáh daleko uvidí');
  await api(pj, 'token_presun', { token_id: gob, x: 665 + 70 * 3, y: 315 });
  ok(!(await tokenyHrace()).includes(gob), 'nestvůru 3 sáhy daleko (nad 1/10 dosahu = 2 sáhy) už ne');
  await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: 0.5, svetlo_typ: 'temnota' } });
  await api(pj, 'token_presun', { token_id: zona, x: 1415, y: 315 });   // malá Temnota 10 sáhů od hráče: hráč ani nestvůra v ní nestojí
  ok((await tokenyHrace()).includes(gob), 'mimo Temnotu infravidění dosáhne celých 20 sáhů (nestvůra 3 sáhy daleko je vidět)');

  // --- prohlížeč: PJ vidí zónu fialově, hráč ne; panel tokenu nabízí Tmu a Temnotu ---
  await api(pj, 'token_presun', { token_id: zona, x: 665, y: 315 });
  await pj.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await hrac.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await pj.waitForTimeout(1200); await hrac.waitForTimeout(1200);
  ok(await pj.locator('#svetlaSvg circle[stroke="#8a6bd1"]').count() === 1, 'PJ vidí zónu Temnoty jako fialový kruh');
  ok(await hrac.locator('#svetlaSvg circle').count() === 0, 'hráč žádný kruh nevidí');
  await api(pj, 'token_uprava', { token_id: zona, zmeny: { svetlo: null, svetlo_typ: null, viditelny: true } });
  await pj.reload(); await pj.waitForTimeout(1200);
  await pj.locator('.vtt-token[data-id="' + zona + '"] .puck').click();
  await pj.waitForTimeout(600);
  await pj.selectOption('#vzhledSvetlo', 'temnota:300');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select concat(svetlo_sahy, '|', svetlo_typ, '|', viditelny_hracum) from tokeny where id=${zona}`) === '300.0|temnota|0', 'PJ v panelu tokenu vybere Temnotu: 300 sáhů, token se skryje před hráči');
  await pj.selectOption('#vzhledSvetlo', 'tma:vlastni');
  await pj.waitForTimeout(300);
  ok(await pj.locator('#vzhledSvetloSahy').isVisible(), 'u Tmy se objeví pole pro poloměr');
  await pj.fill('#vzhledSvetloSahy', '2.5');
  await pj.locator('#vzhledSvetloSahy').dispatchEvent('change');
  await pj.waitForTimeout(800);
  ok(sqlVal(`select concat(svetlo_sahy, '|', svetlo_typ) from tokeny where id=${zona}`) === '2.5|tma', 'Tma o poloměru 2,5 sáhu (10 magů)');
  await pj.reload(); await pj.waitForTimeout(1200);
  await pj.locator('.vtt-token[data-id="' + zona + '"] .puck').click();
  await pj.waitForTimeout(600);
  ok(await pj.inputValue('#vzhledSvetlo') === 'tma:vlastni' && await pj.inputValue('#vzhledSvetloSahy') === '2.5', 'panel po načtení ukáže uloženou Tmu a její poloměr');
  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`delete from zdi where mapa_id=${MAPA}; update mapy set osvetleni='den' where id=${MAPA}`);
  for (const tk of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + MAPA)).json()).tokeny) await api(pj, 'token_smazat', { token_id: tk.id, mapa_id: MAPA });
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
