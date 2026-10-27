# Deník postavy — prototyp v2 z Claude Design a předání do kódu

**Stav (2026-10-27):** prototyp je hotový a schválený po stránce směru,
v kódu (`hra/denik.php`, `assets/css/denik.css`, `assets/js/denik.js`) je
zatím **jen deník v1**. Pokyn uživatele: „Potřebuju to dostat do code.“
Tohle je podklad, ze kterého se v1 přestaví.

## Co v repu přibylo

- **`docs/prototypy/denik-v2.dc.html`** — zdroj prototypu ve formátu Claude
  Design. Není to PHP a mimo editor Claude Design se nedá spustit
  (odkazuje na `support.js` z editoru). Slouží jako **předloha**: CSS
  (tokeny shodné s `assets/css/organic.css`), struktura šablony a JS
  s vzorci jsou čitelné v souboru. Složka `docs/**` se při deployi na FTP
  nenahrává.
- Živý náhled (soukromý, jen vlastník):
  https://claude.ai/artifact/3uwYZDYszMzkYP7Ezcu3Nq
- Vzorová postava v prototypu: **Ronan** (elf, Warlock, 13. úroveň),
  data z uživatelova Excelu. Excel byl jen seznam toho, co chce vidět,
  ne předloha rozložení.

## Rozhodnutí uživatele (komentáře k prototypu, 2026-10-20)

1. **Deník je overlay** nad obrazovkou, ze které se otevřel („Zavřít
   deník“ vrací zpět). Žádný přepínač mapa / družina uvnitř deníku.
2. **Záhlaví** je přilepené úplně nahoře přes celou šířku okna, bez mezery
   nad ním, a **kompaktní** (pole zhruba na polovinu oproti první verzi).
   Při scrollu se zúží (CSS `animation-timeline: scroll()`, bez JS;
   Firefox zůstane v klidové velikosti, na šířce ≤ 720 px je statické).
   Obsah: avatar, jméno, rasa · povolání · úroveň, **přesvědčení**,
   5 vlastností s bonusem (základní hodnota přeškrtnutá, když ji mění
   efekt nebo únava), **postřeh** (objekty, mechanismy), OČ, ÚČ, Zavřít
   deník; pod tím měřidla Životy, Magy, Únava, Naložení.
3. **Nic dvakrát.** Nosnost / naložení / pohyblivost jsou v měřidle
   a v kartě s pásmy, ne ještě jednou jinde. Rasa a úroveň jen v záhlaví.
   Zkušenost zvlášť, postřeh je v záhlaví.
4. **Důsledky stavu nejsou karta.** Postihy z únavy, málo životů
   a naložení se promítnou rovnou do čísel (OČ, ÚČ, bonusy) a vysvětlení
   je v **tooltipu při najetí** na hodnotu (životy: mez vyřazení; únava:
   prahy; naložení: nosnost a −1; OČ/ÚČ: celý výpočet). Pozor: `title` se
   na dotyku ani z klávesnice neukáže; pro tablet bude potřeba klepací
   varianta.
5. **Aktivní zbraň.** Jedna ze slotů Zbraň 1–4 / Střelná 1–2. ÚČ =
   SZ + bonus Síly (na blízko) nebo Obratnosti (na dálku) − postihy;
   OČ v záhlaví je s aktivní zbraní (OČ + OZ u zbraně na blízko, u střelné
   jen OČ). Přepíná se dlaždicí ÚČ v záhlaví, tabulkou Boj a detailem
   zbraně. V DB už je `postavy.drzena_zbran_id` — ověřit, jestli pokryje
   i střelné sloty, jinak je potřeba jedna DDL migrace (ručně přes
   phpMyAdmin, viz CLAUDE.md).
6. **Sloty:** figurína (přilba … boty, plášť, Daemonicon jen Warlock),
   **prsteny rostoucí od 1** slotu (max. 20, přidává se tlačítkem), Zbraně
   1–4, Střelné 1–2. **Žádné „rychlé lektvary“** — v DrD nejsou. Každý
   slot počítá s vlastním obrázkem předmětu (`img`), bez něj ikona podle
   typu.
7. **Detail předmětu** se ukáže jen po výběru slotu / předmětu, žádná
   prázdná karta.
8. **Záložky:** Postava, Inventář, Kouzla (jen povolání s kouzly),
   Schopnosti, Dovednosti, Společníci (počet míst proměnný, 0–5;
   Warlock 1, od vyšší úrovně 2, hraničář pes, nekromant až 5 — **ověřit
   v pravidlech**). Aktivní záložka je spojená s panelem pod ní.
9. **Jazyky jsou dovednosti** (ne samostatné pole). Iniciativa v deníku
   není (počítá se sama jinde).
10. Warlock: Elixír Démonní krve přepočítá max. životy a magy (+30 %)
    a vlastnosti automaticky; **rezerva** magů (zarezervované magy nejdou
    doplnit, dokud kouzlo trvá) je v měřidle šrafovaně.

## Vzorce v prototypu (před stavbou ověřit v `content/pravidla-*.html`)

Podle pravidla repa platí text pravidel, ne tenhle seznam. Hodnoty byly
v prototypu ověřeny proti příkladům z pravidel a Ronanovu Excelu:

- Bonus vlastnosti: 1 → −5, 2–3 → −4, 4–5 → −3, 6–7 → −2, 8–9 → −1,
  10–12 → 0, dál ⌊(h − 11) / 2⌋, nejvýš +6.
- Nosnost = 360 + 30 × bonus Síly; pásma naložení ×1 / ×2 / ×3 / ×4
  (žádné / mírné / střední / velké / neunese) → pohyblivost ×1, ¾, ½, ¼.
  Velké naložení −1 k ÚČ a OČ.
- OČ = KZ zbroje (1 bez zbroje) + KZ štítu + bonus Obr; ÚČ viz výše.
- Málo životů (< 1/3 max.): postih podle Odolnosti 1–5 → −3, 6–11 → −2,
  12–16 → −1, 17+ → 0.
- Mez vyřazení = max. životy / 4, / 6, / 8 podle Odolnosti (17+ → 1).
- Únava: stupně po násobcích Odolnosti (> 1× unavená, > 2× vyčerpaná,
  > 3× úplně vyčerpaná, > 4× vysílená) s postihy Sil/Obr −1/−2/−3,
  Int −2/−4, Cha −2; vyčerpaná a hůř nesmí sesílat.
- Dovednosti (homebrew): stupně vůbec … dokonale (0–6), past
  [14, 11, 8, 5, 2, −1, −4]; fond profibodů vlastnosti =
  hodnota × (5 + ⌊úroveň / 2⌋) + bonus Scholastiku × ⌊úroveň / 2⌋
  (bonus 0, 3, 4, 6, 8, 9, 12 podle stupně). **Vzorec fondu je odvozený
  z Ronanových čísel (Síla 213, Odolnost 158, Inteligence 257), ne
  z textu pravidel.**
- Mantry: stupeň → +0, 1, 2, 4, 5, 7, 10 % k šanci na seslání.

## Co v prototypu není ze zdroje / čeká na uživatele

1. **Elixír Démonní krve, „+2 stupeň síly a obratnosti – max 21+5“:**
   prototyp počítá +2 body vlastnosti. Pokud je „stupeň“ krok v tabulce
   bonusů (tj. +4 body), je výsledek jiný.
2. **Únava 30:** Excel říká „hranice vysílení“, kniha má vysílenou nad
   4× Odolnost (Ronan 40). Prototyp jede podle knihy.
3. **Váha:** prototyp Ronanovi počítá 1 228 mn, Excel 1 083 mn. Rozdíl
   145 mn nevysvětlen (obě hodnoty jsou ve stejném pásmu naložení).
4. **Torna** má v datech kapacitu 800 mn, ale jen Pavučina váží 800 mn
   (obsah celkem 933 mn).
5. **„Vyřazení 34“** z Excelu — nevysvětleno; podle knihy vychází mez 7.
6. **Postřeh na mechanismy:** Excel 20 %, podle pravidel 14 % (½ Int + 5
   za elfa).
7. **Základ šance na seslání 95 %** je odvozený (Excel 99 % − 4 % za
   Mantry stupeň 3), ne ze zdroje.
8. Stupně Ronanových 36 dovedností jsou přečtené z fotografie Excelu.
9. Vzhled prototypu **nebyl zkontrolován v prohlížeči**; kontrola
   rozložení na šířce 1440 px zbývá.
10. Rozpor v knize (tabulka „Objevení mechanismů“ 3× Obr, text 2× Obr)
    a výklad „stupně“ u Elixíru jsou zapsané v
    `docs/kontrolni-seznam-neuplnych-mist.md`, sekce „Na kontrolu při
    proofreadingu“.

## Návrh postupu stavby

1. **Záhlaví a měřidla** do stávajícího `hra/denik.php`: sticky přes celou
   šířku, vlastnosti s bonusy, postřeh, OČ/ÚČ s aktivní zbraní, tooltipy
   s výpočtem. Data už engine má (`vtt_pohyblivost.php`, hod z deníku).
2. **Aktivní zbraň** (viz bod 5; případně DDL).
3. **Figurína a sloty** včetně prstenů od 1 a Daemoniconu; obrázky
   předmětů zatím jen ikony.
4. **Záložky** a přesun existujících sekcí (Přehled / Boj / Schopnosti
   a kouzla / Vybavení / Postava) do nich.
5. **Dovednosti** s fondem profibodů — potřebuje v DB tabulky cen
   (BEZ / UČI / DOBA) a Scholastik; nejdřív ověřit, co už DB má, a vzorec
   fondu najít v pravidlech.
6. **Společníci** s proměnným počtem míst.

Každý krok se staví samostatně, s ověřením na lokální MariaDB podle
`docs/stav-projektu.md`.
