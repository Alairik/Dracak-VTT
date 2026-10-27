# Deník postavy — prototyp v2 z Claude Design a předání do kódu

**Stav (2026-10-27):** prototyp je hotový a schválený po stránce směru,
v kódu (`hra/denik.php`, `assets/css/denik.css`, `assets/js/denik.js`) je
zatím **jen deník v1**. Pokyn uživatele: „Potřebuju to dostat do code.“
Tohle je podklad, ze kterého se v1 přestaví.

## Stav stavby (průběžně)

**Krok 1 hotový (2026-10-27, bez ručního SQL): záhlaví, měřidla a záložky.** `hra/denik.php`, `assets/css/denik.css`, `assets/js/denik.js`
přestavěné podle prototypu; engine (`includes/vtt_denik.php`) a API zůstaly, přibyly jen pomocné funkce `dracak_vtt_denik_hlavicka()`,
`dracak_vtt_denik_postihy()`, `dracak_vtt_denik_aktivni_zbran()`, `dracak_vtt_denik_zebricek_nalozeni()`. Co je hotové:

- Záhlaví přilepené nahoře přes celou šířku, kompaktní, při posunu se zúží (CSS `animation-timeline`, bez JS); na šířce ≤ 720 px je statické.
  Avatar, jméno, „rasa · povolání · úroveň“, pět vlastností s bonusem (základ přeškrtnutý, když ho mění únava), postřeh, OČ, ÚČ aktivní zbraně,
  „Zavřít deník“ (jen na samostatné stránce; v okně na mapě má okno vlastní ✕, tlačítko vrací tam, odkud se deník otevřel).
- Měřidla Životy, Magy (jen s magenergií), Únava, Naložení; Pohyblivost přešla do karty „Pohyb a naložení“ se žebříčkem pásem.
- Vysvětlení k číslům: tooltip při najetí **i klepnutí** (`data-info`, tlačítko „i“) — řeší upozornění z bodu 4 rozhodnutí.
- Záložky Postava (Postup, Obrana, Boj, Pohyb a naložení, Aktivní efekty, Příběh a poznámky), Inventář (+ Kontejnery, Peníze),
  Kouzla (jen když postava má magenergii nebo kouzla), Schopnosti. Aktivní záložka se pamatuje a přežije obnovu deníku.
- Aktivní zbraň: klepnutí na ÚČ v záhlaví přepne na další zbraň z inventáře (`postavy.drzena_zbran_id`); radio v tabulce Boj dělá totéž.
- Skoky z mapy: `window.dracakDenik.ukazSekci(id)` — `boj`, `kouzla`, `schopnosti`, `vybaveni` (a `prehled`, `obrana`, `pohyb`, `efekty`,
  `postava`, `kontejnery`, `penize`) přepne záložku a srovná sekci.

**Odchylky od prototypu (z pravidel, ne podle odhadu):**

- **OČ v záhlaví nezahrnuje OZ držené zbraně.** Kniha (h1611): OČ = KZ zbroje + bonus Obr; štít je **+1 k hodu na obranu** (ne část KZ); obrana zbraní je
  *nepovinné pravidlo* a bez ní/po vyčerpání obran je −3. Číslo v záhlaví = základ + štít + postihy za málo životů a velké naložení, tedy přesně to, co přičítá hod
  z deníku (`hra/api/hod_postavy.php`) — záhlaví a hod se nerozejdou. „Obrana s ní“ (OČ + OZ) z prototypu **zatím není**; čeká na rozhodnutí uživatele, jestli se
  nepovinné pravidlo OZ má do deníku i do hodu zapojit.
- **Nosnost** bere engine z Tabulky nosnosti (`nosnost_podle_sily`, h1681), ne vzorec 360 + 30 × bonus z prototypu; **pohyblivost po naložení** se
  zaokrouhluje nahoru (b11149), prototyp zaokrouhloval jako obvykle.
- **ÚČ v záhlaví** = ÚČ zbraně (nejméně 0) + postihy; může tedy vyjít záporné (hod = 1k6+ + ÚČ + opravy), stejně jako v hodu.
- **Přesvědčení v záhlaví se nedělá — kniha ho do deníku zakazuje.** „Postava své přesvědčení zpravidla nikomu neprozrazuje … Přesvědčení se nezapisuje do
  Osobního deníku, aby ho nemohli omylem přečíst jiní hráči“ (h107, b718; podobně b710: povahu a cíle ostatním hráčům hned neříkej). Prototyp (rozhodnutí 2
  z komentářů) ho v záhlaví měl; platí kniha. Číselník `presvedceni` (pět druhů z h107) v DB je, `postavy` sloupec na přesvědčení nemají — kdyby ho uživatel chtěl
  někde soukromě (jen PJ / jen vlastník, mimo deník), je to jedna drobná změna struktury a rozhodnutí, kdo to smí vidět.
- **Chybí (potřebují DB nebo rozhodnutí):** šance na seslání a rezerva magů, přeškrtnutí základu vlastností při změně efektem (mechanika efektů
  vlastnosti zatím nemění).

**Krok 2 hotový (2026-10-27, ruční SQL: `postava_predmety.u_sebe`):** zbraně „u sebe“ a **vytasení / výměna zbraně stojí v boji akci** (rozhodnutí uživatele;
kniha h1606 b10619: v kole jde jeden úkon, „vyměnit zbraň“ je úkon, „vytasit meč a použít jej“ ne). Sloty „Zbraň 1–X“ z prototypu jsou v kódu jako příznak
**„u sebe“** u zbraně v inventáři (sloupec „U sebe“ v tabulce Boj) — pevné číslované sloty se nedělají, dokud kniha neřekne, kolik se jich na opasek/do pochev vejde.
Podrobnosti, API a chybějící pravidla: `docs/stav-projektu.md`, sekce Boj („Vytasení / výměna zbraně“).

**Krok 3a hotový (2026-10-28, bez ručního SQL): detail předmětu a hledání / filtry v inventáři.** Název v inventáři i v tabulce zbraní (Boj) je odkaz;
klik otevře plovoucí kartu „Detail položky“ (`#dn-detail`, vpravo dole, na telefonu spodní list) s hodnotami z katalogu (váha, cena přepočtená na zl / st / md,
u zbraní SZ, útočnost, OZ, délka, dostřel, druh, držení, třída, postih k iniciativě; u zbroje KZ; u lektvaru výrobce, příprava, kostky, efekty) a popisem —
**žádná prázdná karta, ukáže se až po výběru** (rozhodnutí 7 z komentářů k prototypu); zavře ji ✕, Esc nebo druhé klepnutí na tutéž položku, přežije obnovu
deníku. Data dává `hra/api/polozka_detail.php` (katalog je obsah pravidel, jen přihlášení). Inventář má hledání (bez ohledu na diakritiku) a chipy podle druhu
s počty (Vše / Zbraně / Zbroje / Vybavení / Suroviny / Artefakty / Lektvary — jen druhy, které postava má); filtr se drží i po překreslení a v rámci
záložky prohlížeče (`sessionStorage`), jen zobrazení, nic se neukládá do DB.

**Zbývá:** figurína a sloty (krok 3, DDL), hledání a filtry v inventáři,
Dovednosti (krok 5, DDL), Společníci (krok 6, DDL), připnutá kouzla.

## Rozhodnutí uživatele 2026-10-27 (po kroku 1) — vzhled ano, vymyšlená mechanika ne

- **Prototyp je jen první vzhled.** Design nezná pravidla a něco si přidává; **platí pravidla v knize**, ne prototyp. Co v něm není z knihy
  (OZ v OČ, vzorec nosnosti, fond profibodů, šance na seslání…), se neimplementuje bez ověření v `content/pravidla-*.html`.
- **OČ v záhlaví bez OZ zbraně** zůstává podle knihy (h1611: obrana zbraní je nepovinné pravidlo; odpověď na dotaz z 2026-10-27: „pravidla platí“).
- **Sloty „Zbraň 1–X“ nejsou ruka, ale výzbroj při těle:** které zbraně má postava připravené na opasku, v pochvě, v závěsu, v toulci nebo holstru a může je
  v boji vytasit nebo přezbrojit. **V ruce je aktivní vždy jen jedna.** Z toho vyjde nová (domácí) mechanika opasku, pochev, toulců a podobného — zatím
  nedefinovaná, dělá se až po rozhodnutí uživatele (krok 2 nebo 3).
- **Co o tom říká kniha (podklad pro tu mechaniku):** tabulka POCHVY (b14697, b14205…: jednoruční 1 st / 10 mn, obouruční 1,5 st / 15 mn, tyčové 80 md / 10 mn,
  super těžké 2 st / 20 mn, holster na vrhací zbraň každých 5 ks 1,5 st / 10 mn); „aby mohla postava pohodlně mít zbraně při sobě, potřebuje k nim pořídit
  pochvu či závěs“ (b14698; standardní pochva, závěs, bandalýr 2,5 zl, 10 mn); toulec na 20 ks 1,5 st / 7 mn, rozšířený 4×10 ks 6 st / 20 mn (b14202);
  **vyměnit zbraň je úkon kola** a nejde vytasit meč a použít ho v jednom kole (kapitola Čas v souboji, b10619); Erithen Rychlé tasení — „volně dostupná“ zbraň
  je z pochvy, holstru apod., ne z batohu ani z rukou (b7751, b7754); zloděj vytáhne malou lehkou vrhací zbraň z úkrytu (rukáv, bota, kapsa, opasek) a vrhne ji
  v jednom kole (b5565); Dikobraz: tasení vrhací zbraně nestojí akci (b9098). Chybí: kolik zbraní se vejde na opasek/do pochev, cena tasení v rozšířeném
  systému (1 akce?), co je „volně dostupné“ u obouručních na zádech.

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

**Pozor — dva vzorce níže v kódu platí jinak (opraveno podle knihy, uživatel 2026-10-27: „co nesedí s knihou a víš, že je špatně, uprav“):**
nosnost bere kód z Tabulky nosnosti (`nosnost_podle_sily`, h1681), ne `360 + 30 × bonus`; a OČ je KZ zbroje + bonus Obr (štít je **+1 k hodu na obranu**,
ne část OČ; OZ zbraně je nepovinné pravidlo h1611). Zbytek seznamu je podklad, ne zdroj pravdy.

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
