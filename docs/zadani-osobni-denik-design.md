# Zadání: Osobní deník postavy (Dračák VTT) — pro Claude Design

Status: zadání k předání designérovi / Claude Design. Stejný princip jako
`docs/zadani-redesign-ui.md`: **dokument neobsahuje řešení**, jen cíl,
obsah, reálný stav dat a kódu, omezení a otevřené otázky. Vzhled, rozložení
a interakce navrhuje řešitel.

Podklady: pravidla v `content/pravidla-hrac.html` (kap. "Osobní deník",
h1690; boj h1609–h1612; pohyb h1634/h1681; únava h1634; postřeh h1683;
tvorba postavy h104–h110), reálný datový model (`database/migrations/`),
současné UI (`hra/postava.php`, `hra/svet.php`, `hra/mapa.php`,
`assets/css/organic.css`) a rešerše online (kap. 10).

---

## 0. Prompt k vložení do Claude Design

> Navrhni vzhled **Osobního deníku postavy** pro webovou aplikaci Dračák VTT
> (virtuální stůl pro hru na hrdiny Dračí doupě / Dračí hlídka). Deník je
> hlavní obrazovka hráče: tráví na ní většinu herního času (sezení trvá 2–4
> hodiny), potřebuje z ní za hry **rychle číst** (útočné a obranné číslo,
> životy, magenergie, únava, naložení, pohyblivost) a **rychle zapisovat**
> (±životy, ±magy, body únavy, vybavení, peníze), a mezi sezeními ji
> **spravovat** (inventář s vaky a truhlami, kouzla, schopnosti, zkušenost,
> poznámky, vzhled a příběh postavy). Má to být ultimátní, detailní, ale
> přehledný přehled postavy — inspirovaný papírovým Osobním deníkem z
> pravidel (a jeho tisknutelnou verzí), ne kopií běžného webového formuláře.
>
> Přečti celé přiložené zadání. Navazuj na existující vizuální jazyk
> (teplé "organic" schéma, písmo Playfair Display, tokeny v kap. 7). Aplikace
> je PHP bez frameworku a bez build kroku, takže výstup potřebuju jako
> **statické HTML + CSS (+ minimum vanilla JS) prototypy nad existujícími
> CSS proměnnými**, plus popis komponent a stavů. Navrhni: (1) desktop
> 1440 px — hlavní verzi, (2) tablet na šířku 1024 px, (3) telefon jako
> sekundární, (4) tiskovou A4 verzi klasického deníku. Pro každou zónu
> rozliš informace **stabilní** (jméno, rasa, vlastnosti…) od **volatilních**
> (životy, únava, naložení…) a volatilní dej do trvale viditelné zóny.
> Počítej s tím, že u části polí zatím nejsou data (kap. 3, sloupec
> "Data") — navrhni jejich prázdné stavy, ne jejich vynechání. Na konci
> zodpověz otevřené otázky z kap. 9.

---

## 1. Kontext produktu

**Dračák VTT** (`vtt.zvelebil.online`) je virtuální stůl pro Dračí doupě
(česká hra na hrdiny, nástupce Dračí hlídka), postavený přímo nad databází
pravidel. Hráči a Pán jeskyně (PJ) se přihlásí, PJ vede svět s mapami a
tokeny, hráči mají postavy. Žádný real-time přes WebSocket, jen krátký
polling (1–2 s) — deník proto nemusí být "živý jako chat", ale změny od
jiného klienta (PJ strhne životy) se musí během pár sekund objevit.

Technicky: PHP bez frameworku + MariaDB, sdílený hosting (WEDOS),
nasazení přes FTP, **žádný build krok** (žádný npm, žádný bundler, žádný
React). Vanilla JS, jeden CSS soubor s proměnnými (`assets/css/organic.css`).
Jazyk rozhraní česky, texty pravidel česky.

**Kdo deník používá**

| Role | Co s deníkem dělá |
|---|---|
| Hráč | Čte a zapisuje svou postavu. Deník cizí postavy dnes otevřít nemůže; přehled ostatních postav světa (rasa, povolání, životy, atributy, inventář) vidí v kartách na nástěnce světa — zda má být část cizího deníku čitelná pro ostatní hráče, je otevřená otázka (kap. 9). |
| PJ světa | Vidí a může upravit deník kterékoli postavy ve svém světě (typicky: přidělí zkušenost, strhne životy, vloží předmět, nasadí efekt). |
| Admin | Totéž co PJ, napříč světy. |

Postava může existovat **bez světa** (hráč si ji připraví předem), takže
deník musí fungovat i mimo kontext světa/mapy.

---

## 2. Cíl a hlavní scénáře

Cíl: jedna obrazovka (s rozumnou vnitřní navigací), na které hráč vidí **vše,
co o své postavě potřebuje vědět** (citace pravidel: "průvodce postavy po
jeskyni, v němž je zaneseno vše, co o své postavě potřebuješ vědět; při hře
ho musíš mít stále po ruce"). Digitální verze má navíc počítat odvozené
hodnoty (bonusy, ÚČ, OČ, nosnost, pohyblivost, stupeň únavy), které si
hráč na papíře počítá ručně.

Scénáře, podle kterých se má návrh posuzovat:

1. **Můj tah v boji (nejvyšší priorita, desítky sekund).** Co mám za
   útočné číslo se zbraní, kterou držím? Jaké mám obranné číslo? Kolik mám
   životů a magů? Jsem unavený? Mám postih (naložení, málo životů)? Jaká
   kouzla/finty/schopnosti můžu použít a co stojí? → **0 až 1 klik od
   všeho z tohoto seznamu.**
2. **Zapsat změnu.** −3 životy, −2 magy, +1 bod únavy, vypil jsem lektvar,
   vzal jsem poklad, utratil jsem 5 zlatých. → rychlé ± ovládání přímo u
   hodnoty, bez otevírání formuláře.
3. **Cesta / průzkum.** Kolik unesu? Jsem naložený? Jaká je pohyblivost?
   Na čem jedu? Jak jsem unavený po dnešním pochodu? Jaký mám postřeh?
4. **Mezi sezeními.** Správa vybavení a pokladu (včetně toho, co je ve
   kterém vaku/truhle/na kterém zvířeti), nákup, zkušenost a přestup na
   další úroveň, naučení schopností a kouzel, psaní poznámek, rozvoj
   osobnosti (povaha, zvyky, sny, původ).
5. **Studium.** Přečíst si, co přesně moje schopnost/kouzlo/finta dělá
   (popis, mechanika, kostky, past, max. použití).
6. **PJ prohlíží cizí postavu.** Rychlý přehled "co ten hráč má a v jakém je
   stavu", případně úprava.
7. **Tisk.** Pravidla doporučují "psát do něj tužkou a předem si ho
   okopírovat". Chci tiskovou A4 verzi klasického deníku (viz 6.5).

---

## 3. Obsah deníku — kompletní inventář

Sloupec **Data**: ✔ = v DB a v kódu už je; ◐ = v DB je, ale není strukturované
nebo není napojené; ✖ = zatím nikde není (doplní se v dalším kroku, navrhni
prázdný stav a místo pro hodnotu). Sloupec **Typ**: **V** = vstup od hráče,
**S** = stavový údaj (mění se za hry), **O** = odvozená hodnota (počítá
se z jiných), **K** = z katalogu pravidel.

Kolonky Osobního deníku podle pravidel (h1690) jsou označeny **[deník]**.

### 3.1 Identita

| Pole | Typ | Data | Poznámka |
|---|---|---|---|
| Jméno **[deník]** | V | ✔ | "podle vlastní úvahy, mělo by odpovídat rase" |
| Rasa a povolání (+ větev) **[deník]** | V | ✔ | 31 ras (7 základních, trpasličí klany, homebrew), 41 povolání se větvemi |
| Úroveň | S | ✔ | |
| Erb **[deník]** (volitelné) | V | ✖ | obrázek/symbol |
| Portrét / vzhled postavy | V | ✖ | volný obrázek + popis |
| Velikost **[deník]** | O | ◐ | A (hobit/kudůk/trpaslík), B (elf/barbar/člověk), C (kroll); v DB je číselník `velikosti` (A0–E), vazba rasa→velikost zatím jen v textu rasy |
| Přesvědčení | V | ◐ | číselník `presvedceni` existuje, na postavě není |
| Jazyky | O/V | ◐ | z rasy a povolání (`rasa_jazyky`, `povolani_jazyky`), na postavě není |
| Původ, povahové rysy (~4), zvyky, sny a cíle | V | ✖ | pravidla h107: hráč si je vymyslí; "ostatním hráčům hned neříkej" → **soukromá** sekce |
| Hráč, svět, datum založení | O | ✔ | |

### 3.2 Vlastnosti

Pět vlastností (**síla, obratnost, odolnost, inteligence, charisma**), u
každé **stupeň** (hráčská škála 1–21) a z něj **bonus/postih**
(Tabulka postihů a bonusů: 10–12 → 0, 13–14 → +1, … 21 → +5; nižší stupně
do −5). Tabulka je v DB (`opravy_za_atribut`). Postava má navíc **rasový
rozsah** každé vlastnosti (`rasa_rozsahy_vlastnosti`) — může být užitečné
ukázat "kde v rozsahu jsem". Data: ✔ (stupně i bonusy dopočítatelné).

### 3.3 Stavové hodnoty (volatilní — trvale viditelná zóna)

| Pole | Typ | Data | Pravidla / poznámka |
|---|---|---|---|
| Životy aktuální / maximum **[deník]** | S | ✔ | Max. = základ povolání + bonus za odolnost (+ růst za úroveň). Původní deník: **svislý sloupec čísel 1–50 s posunovanou značkou** — zvaž digitální obdobu. Po 8h spánku +2 životy. |
| Mez vyřazení | O | ◐ | Tabulka bojeschopnosti podle stupně odolnosti: 1–5 → 1/4 max. životů, 6–11 → 1/6, 12–16 → 1/8, 17–21 → 1 život (min. 1). Pod mezí je postava vyřazena z boje. |
| Postih za málo životů | O | ◐ | Méně než 1/3 max. životů → postih k útoku i obraně: odolnost 1–5 → −3, 6–11 → −2, 12–16 → −1, 17–21 → 0. |
| Magenergie aktuální / maximum **[deník]** | S | ✔ | jen povolání, co magenergii používají (jinak pole není) |
| Únava: body / vytrvalost | S | ✔ | vytrvalost = odolnost; nad ni *unavená*, nad 2× *vyčerpaná*, nad 3× *úplně vyčerpaná*, nad 4× *vysílená* (omdlévá) |
| Stupeň únavy + jeho důsledky | O | ◐ | unavená: −1 k síle/obratnosti, žádný sprint; vyčerpaná: −2, bez běhu/sprintu/kouzel/meditace; úplně vyčerpaná: −3, inteligence −4, charisma −2, jen plazení; vysílená: omdlévá. **Plná tabulka postihů v datech zatím není** → ukaž stupeň + text, ne vypočtené postihy |
| Aktivní efekty (buff/debuff, zbývá kol) | S | ✔ | tabulka `aktivni_efekty` + katalog `efekty` |
| Naložení: stupeň | O | ◐ | žádné / mírné / střední / velké podle násobku nosnosti; velké naložení = −1 k útoku i obraně |
| Pohyblivost (základní, po naložení, bonus) **[deník]** | O | ✔ | základ rasy + bonus za obratnost + 2× bonus za sílu; naložení snižuje na ¾ / ½ / ¼ |
| Doprava | S | ✔ | pěšky nebo jízdní zvíře (7 zvířat s pohyblivostí, nosností, vytrvalostí) |

### 3.4 Boj

| Pole | Typ | Data | Pravidla |
|---|---|---|---|
| Útočné číslo (ÚČ) tváří v tvář **[deník]** | O | ◐ | **ÚČ = síla zbraně (SZ) + bonus za sílu (+1 rodová zbraň), nikdy < 0.** SZ je v katalogu jen v textu `popis` zbraně, ne jako číslo → ◐ |
| Útočnost zbraně, obrana zbraně (OZ), délka | K | ◐ | útočnost a OZ jsou v DB (`predmety.utocnost`, `.oc`), délka a SZ jen v textu |
| Střelecký boj: ÚČ, dostřely (3 pásma: malý/střední/velký, +1/0/−1 k útoku) **[deník]** | O | ◐ | bez OZ; útok tváří v tvář na střelce má postih −2 k obraně |
| Obranné číslo (OČ) **[deník]** | O | ◐ | **OČ = kvalita zbroje (KZ) + bonus za obratnost, nikdy < 0; štít +1.** Aktuálně nošená zbroj/štít se na postavě neeviduje → ◐ |
| Kvalita zbroje (KZ) | K | ◐ | |
| Iniciativa (bonusy a postihy podle situace) | O | ✔ | tabulka bonusů; v mapě se zaškrtávají, co platí; počet akcí z hodu (0 až 10 akcí) |
| Postih za naložení, za málo životů | O | ◐ | viz 3.3 |
| Rodová zbraň rasy | K | ✔ | hobit lehká kuše, kudůk sekera, trpaslík válečná sekera, elf dlouhý luk, člověk široký meč, barbar meč bastard, kroll těžký kyj |
| Finty (naučené) | K/S | ◐ | katalog `finty` je, "které postava zná" na postavě není |

### 3.5 Schopnosti, kouzla

| Pole | Typ | Data | Poznámka |
|---|---|---|---|
| Zvláštní schopnosti a dovednosti **[deník]** | K | ◐ | katalog ~340 (schopnost/dovednost, úroveň od, prerekvizita, kostky, past, max. použití); rasové (`rasa_schopnosti`) a povolání (`schopnost_povolani`) vazby jsou, **"které schopnosti postava reálně má / jak je využila dnes" na postavě není** |
| Kouzla **[deník]** | K | ✔ | `postava_zna_kouzlo`; katalog ~700 kouzel (úroveň, cena magenergie, dosah, rozsah, doba sesílání, trvání, typ únavy, kostky, popis) |
| Využití za den ("3× denně") | S | ✖ | `max_pouziti` je jen text; čítač použití za den není |
| Postřeh (na objekty a na mechanismy) **[deník]** | O | ◐ | objekty = inteligence % (+5 % kroll/barbar/trpaslík), mechanismy = ⌊inteligence/2⌋ % (+5 % elf/hobit/kudůk) |

### 3.6 Vybavení, poklad, peníze

| Pole | Typ | Data | Pravidla / poznámka |
|---|---|---|---|
| Vybavení **[deník]** — název, **umístění**, **váha** | S | ◐ | dnes: seznam předmětů s množstvím, bez umístění; váha z katalogu `predmety.vaha` (v mincích, mn), u části položek chybí |
| Kontejnery (vak, torna, truhla, soudek…) a co je v nich | S | ✖ | pravidla (ceník h2055) mají kontejnery s **kapacitou v mn a vlastní váhou** (torna kožená 800/30 mn, velký vak 360/15, truhly 200–1500 mn…). Rozhodnuto: zvíře i povoz mohou mít vlastní náklad, pokud na nich jsou kontejnery |
| Nosič nákladu | S | ✖ | postava / jízdní zvíře / povoz — každý má vlastní nosnost; váha jezdce se počítá do nákladu zvířete |
| Poklad **[deník]** — cena, umístění, váha; Celkem | S | ✖ | |
| Peníze **[deník]** — **zvlášť zl / st / md** | S | ✖ | 1 zl = 10 st = 100 md; **každá mince váží 1 mn** (20 mincí ≈ 1 kg) → peníze se počítají do nákladu |
| Lektvary | S | ✔ | `postava_lektvary`, katalog ~88 |
| Nosnost **[deník]** | O | ✔ | Tabulka nosnosti podle bonusu za sílu: −5 → 210 mn, … 0 → 360, +4 → 480, +5 → 510 (každé +1 = +30 mn). Hranice naložení = násobky: ×1 žádné, ×2 mírné, ×3 střední, ×4 velké (příklad v pravidlech: 330 / 660 / 990 / 1320 mn) |
| Základní náklad / Celkový náklad **[deník]** | O | ◐ | základní = vybavení; celkový = základní + poklad. Dnes se počítá jen ze zbraní/zbrojí/surovin, u položek bez váhy se hlásí "neúplný součet" |
| Naložení **[deník]** | O | ✔ | viz 3.3 |

### 3.7 Zkušenost a postup

| Pole | Typ | Data | Poznámka |
|---|---|---|---|
| Zkušenost (zt) **[deník]** | S | ✖ | na počátku 0; přiděluje **PJ** (hráč jen čte); pět zdrojů: schopnosti, dobré nápady/překážky, nestvůry, hraní postavy, prémie |
| Hranice další úrovně a cena výcviku | K/O | ◐ | tabulka zkušeností per povolání (`zkusenostni_urovne`: zt + cena ve zlatých); postup není automatický — výcvik, 2 měsíce bez dobrodružství, platba |

### 3.8 Poznámky a historie

| Pole | Typ | Data | Poznámka |
|---|---|---|---|
| Poznámky **[deník]** | V | ✔ (jedno textové pole) | dnes jen jedno pole `poznamky` |
| Sdílené poznámky světa | V | ✔ | `svet_poznamky` (autor vybírá konkrétní příjemce) — jiná obrazovka, ale může se do deníku odkazovat |
| Historie změn postavy (životy, magy, předměty) | S | ◐ | existuje log událostí světa; per-postava přehled zatím není |

---

## 4. Chování a stavy

- **Editace vs. čtení.** Hráč edituje svou postavu, PJ/admin kteroukoli
  ve svém světě. Pole **zkušenost** přiděluje jen PJ. Soukromé sekce
  (osobnost, "co ostatním neříkám") nevidí ostatní hráči.
- **Rychlé ovládání** u životů, magů, únavy, peněz: ±1 / ±5 (v mapovém
  panelu už existuje −5/−1/+1/+5), případně zadání čísla. Okamžitě se
  přepočítají odvozené hodnoty (stupeň únavy, postih za málo životů…).
- **Neúplná data se mají přiznat, ne skrýt.** Odvozená hodnota, která se
  opírá o chybějící vstup (rasa bez pohyblivosti, součet nákladu bez váhy
  u některých položek, neznámý atribut), se zobrazí s **jasným
  příznakem neúplnosti** a ukáže, co chybí — nikdy ne tiše špatné číslo.
- **Odvození na vyžádání.** Zobrazuj **výsledné číslo** a odvození (z
  čeho se spočítalo) až na požádání (tooltip/popover/rozklik). Rešerše
  (kap. 10) ukazuje, že vypisovat u každého čísla všechny zdroje bonusů
  deník zahltí.
- **Stavy, které musí mít vizuální podobu:** životy pod 1/3 (postih), pod
  mezí vyřazení; únava po jednotlivých stupních; naložení mírné/střední/
  velké/nad 4× nosnosti (neunese); aktivní efekty s odpočtem kol; postava
  bez magenergie; postava bez světa; prázdné sekce (žádné kouzlo, žádný
  předmět); nepřítomná data (kap. 3 ✖).
- **Živé změny od jiného klienta** (PJ strhne životy): hodnota se změní do
  pár sekund; zvaž nenápadné zvýraznění změny.

---

## 5. Požadavky na UX

1. **Stabilní vs. volatilní.** Informace, které se za hry nemění (jméno,
   rasa, vlastnosti, popis), nemusí být neustále na očích; informace, které
   se mění nebo se čtou opakovaně (životy, magy, únava, naložení, ÚČ/OČ,
   aktivní efekty), patří do **trvale viditelné zóny** (např. horní pás
   nebo boční sloupec), nezávisle na tom, na jaké záložce/sekci hráč je.
2. **Hustota bez chaosu.** Je to nástroj pro hodiny práce. Chci vysokou
   informační hustotu srovnatelnou s papírovým deníkem, ale se silnou
   hierarchií (velikost, váha písma, seskupení, mezery), ne plochou "zeď
   políček".
3. **Navigace uvnitř deníku.** Navrhni, jak se pohybovat mezi zónami
   (záložky, kotvy v jednom dlouhém scrollu, postranní obsah, "tray" se
   akcemi — viz kap. 10). Preferuji, aby šlo mít **otevřený přehled bez
   nutnosti přepínat záložky** pro scénář 1 (boj).
4. **Akce a čtení oddělit.** Seznam toho, co mohu *použít* (kouzla,
   finty, schopnosti, lektvary, zbraně) se čte jinak než referenční popisy.
5. **Zařízení.** Desktop 1440 px je hlavní; tablet na šířku (deník při
   hře leží na stole vedle klávesnice) je druhý nejdůležitější; telefon
   sekundární (rychlý náhled stavu, ±životy). Dotykové cíle ≥ 44 px u
   rychlých ovládacích prvků.
6. **Tisk.** Samostatná tisková verze A4 (jedna až dvě strany) v duchu
   klasického deníku — kolonky z h1690, prázdná políčka k vyplnění
   tužkou, svislý sloupec životů 1–50 a magů, tabulka zbraní s ÚČ/
   útočností/OZ/dostřely, vybavení s umístěním a váhou, peníze zl/st/md,
   Celkem, základní/celkový náklad, hranice naložení, zkušenost. Funguje
   i jako **prázdný formulář** (bez dat postavy).
7. **Přístupnost.** Kontrast textu min. WCAG AA; stav nikdy jen barvou
   (únava, naložení, životy mají i text/ikonu/tvar); ovladatelnost
   klávesnicí; srozumitelné popisky pro čtečky u ± tlačítek.
8. **Čeština a pravidlová terminologie.** Názvy kolonek dodržuj podle
   pravidel (ÚČ, OČ, OZ, KZ, SZ, mn, zl/st/md, zt, směna/kolo, sáh/coul,
   magy). Dlouhé české slovní tvary (např. "úplně vyčerpaná") musí
   vyhovět i v úzkých sloupcích.

---

## 6. Technická omezení

- **Bez build kroku.** Výstup = HTML + CSS + vanilla JS. Žádný framework,
  žádná závislost na npm (CDN knihovny jen výjimečně a zdůvodněné).
- **Jeden font:** Playfair Display (výslovné přání zadavatele) — tučnost,
  velikost a styl (kurzíva, kapitálky) jsou nástroje hierarchie; číslice
  v Playfair jsou **old-style (proporční, "text figures")**, zvaž
  `font-variant-numeric: lining-nums tabular-nums` u tabulek a čísel.
- **Tokeny.** Barvy, rádiusy, stíny, mezery z `organic.css` (kap. 7). Nové
  tokeny jen jako doplněk, s důvodem.
- **Ikony.** Aplikace má vlastní sadu inline SVG ikon (`dracak_icon()`); nové
  ikony navrhni jako jednoduché SVG.
- **Výkon.** Sdílený hosting, stovky položek katalogu (kouzla ~700, schopnosti
  ~340): dlouhé seznamy musí jít filtrovat/hledat a nesmí se vykreslit
  všechny najednou naivně.
- **Tisk** přes `@media print` ze stejné stránky, bez generování PDF na
  serveru.

---

## 7. Existující vizuální jazyk (navázat)

Referenční snímky: `docs/zadani-osobni-denik-img/01-svet-dashboard.png`
(nástěnka světa — typický vzhled karet, pill tlačítek, "stůl") a
`02-postava-dnes.png` (dnešní editační formulář postavy — **to je to, co
má deník nahradit**, ne vzor).

Z `assets/css/organic.css`:

- **Neutrály (pergamen):** `--color-bg #f5ead8`, `--color-surface #fffaf1`,
  `--color-text #201e1d`, rampa `--color-neutral-100…900` (`#f5ead8` →
  `#2c2621`).
- **Akcent (měď/terakota):** `--color-accent #c67139`, rampa 100–900.
- **Akcent 2 (mechová zeleň):** `--color-accent-2 #7a8a5e`, rampa 100–900
  (dnes se používá např. pro ukazatel životů).
- **Nebezpečí:** `--color-danger #a3402a`, `--color-danger-100 #f6ddd5`.
- **Tvar:** `--radius-sm 8px`, `--radius-md 16px`, `--radius-lg 28px` (velmi
  zaoblené karty), stíny `--shadow-sm/md/lg`, mezery `--space-1…8`.
- **Komponenty, které existují:** `.card` (+ `.elev-sm/md/lg`), `.btn`
  (`-primary`, `-secondary`, `-ghost`, `-danger`, `-icon`), `.field` +
  `.input`, `.tag`/`.tag-accent`, `.chip-stepper` (číslo s ±), `.seg`
  (segmentový přepínač), `.table`, `.rec-card`/`.rec-grid` (karta se
  dvojicemi klíč–hodnota), `.empty-state`, `.note` (jemná poznámka),
  pill tlačítka v hlavičkách. V mapovém panelu už existují ovladače ±5/±1
  pro životy a magenergii a výběr bonusů iniciativy.
- **Tón:** teplý, "pergamenový", organický; ne tmavé fantasy kýč, ne
  sterilní SaaS. Dnešní aplikace je světlá.

---

## 8. Co očekávám od řešitele (výstupy)

1. **Hlavní návrh deníku — desktop 1440 px:** rozložení zón, trvale viditelná
   zóna stavu, navigace uvnitř deníku, hierarchie. Pro scénář 1 (boj) i
   scénář 4 (správa).
2. **Tablet na šířku 1024 px** a **telefon** (kde se rozložení mění a co se
   schová/přesune).
3. **Tisková A4 verze** klasického deníku (kap. 5.6), včetně prázdného
   formuláře.
4. **Katalog komponent:** životoměr/magoměr (vč. varianty "sloupec 1–50"
   z papírového deníku), stepper ±, stav únavy, ukazatel naložení (pásmo
   s hranicemi ×1/×2/×3/×4), karta zbraně (ÚČ/útočnost/OZ/dostřely), řádek
   kouzla/schopnosti/finty, řádek inventáře s umístěním a váhou, strom
   kontejnerů (postava → vak → předměty; zvíře → sedlový vak…), měna
   zl/st/md, tabulka vlastností (stupeň + bonus), štítek neúplných dat,
   zvýraznění změny od jiného klienta.
5. **Stavy** z kap. 4 (včetně prázdných a neúplných).
6. **Mikrointerakce**: rychlý zápis ±, rozbalení odvození čísla, použití
   kouzla/lektvaru, přesun předmětu do kontejneru (drag & drop nebo
   alternativa pro dotyk).
7. **Případné nové tokeny / úpravy tokenů** s důvodem.
8. Prototyp jako **statické HTML+CSS** nad `organic.css` (ať se dá rovnou
   převést do PHP šablony), plus stručný popis, co je statické a co
   vyžaduje JS.

---

## 9. Otevřené otázky pro řešitele

1. **Jedna obrazovka, nebo záložky?** Kde je hranice mezi "vždy na očích" a
   "o klik dál"?
2. **Metafora.** Držet se vzhledu papírového deníku (kolonky, sloupec
   životů) a "okolo" doplnit digitální funkce, nebo vzít jeho **obsah** a
   dát mu moderní digitální formu? (Tisková verze má být papírová v každém
   případě.)
3. **Zbraně a boj.** Hráč typicky drží jednu až dvě zbraně — jak ukázat ÚČ
   "pro zbraň, kterou právě držím" a snadno přepnout? (Dnes se nošená
   zbraň/zbroj/štít neeviduje.)
4. **Kouzla a schopnosti** ve stovkách: seznam "moje" vs. katalog, filtry
   (úroveň, obor, cena magů), oblíbené/připnuté.
5. **Inventář s kontejnery:** strom, plochý seznam se štítkem umístění,
   nebo "obrázek postavy a zvířete s batohy"? Jak ukázat kapacitu kontejneru
   a celkový náklad na každém nosiči?
6. **Peníze** — zl/st/md se mají zobrazit odděleně (pravidla), ale váží se
   (1 mn každá). Jak to ukázat, aby to nebyla jen další tabulka?
7. **Neúplná data** (kap. 4) — jednotný vizuální jazyk pro "tahle hodnota
   není úplná / chybí vstup".
8. **Co se po změně od PJ zvýrazňuje** a jak dlouho?
9. **Co smí vidět ostatní hráči** z deníku cizí postavy (dnes vidí atributy a inventář; soukromé osobní texty a poznámky ne)?
10. Je potřeba samostatný **režim "boj"** (zploštělý, jen to nejdůležitější),
   nebo stačí dobrá hierarchie jednoho pohledu?

---

## 10. Rešerše (online) — co z ní plyne

Pozn.: přímé stahování stránek z prostředí, ve kterém zadání vzniklo, bylo
zablokováno síťovou politikou; závěry vychází z výsledků vyhledávání
(úryvky), ne z plného čtení stránek. Berte je jako inspiraci, ne jako
doložená fakta.

- **Dračí hlídka (nástupce DrD)** má stejný koncept — *Osobní deník* jako
  evidence postavy (životy základní/aktuální, magenergie, únava, naložení,
  pohyblivost, vybavení zapsané při nákupu); obecný prázdný deník je
  součástí pravidel a ke stažení na webu hry, existují i předvyplněné
  deníky ukázkových postav. Roll20 má kompendium Dračí hlídky
  (`roll20.net/compendium/dračíhlídka`, sekce "Herní postava", "Tvorba
  postavy", "Příprava před hrou").
- **Stabilní vs. volatilní informace.** Esej *Visual Design as Metaphor: The
  Evolution of a Character Sheet* (analoggamestudies.org) rozlišuje stabilní
  údaje (role, vlastnosti) a volatilní údaje, na které se hráč odkazuje
  neustále; stabilní se mohou přesunout do pozadí. → základ návrhu v kap. 5.1.
- **Přiznat změněné hodnoty, nevypisovat zdroje.** Designérský test sheetu
  (nodiceleftbehind.substack.com) zjistil, že zapisovat každý bonus a jeho
  původ zahltí prostor; řešením jsou **drobné značky u upravených hodnot** a
  odvození jinde. → kap. 4 "Odvození na vyžádání".
- **Obsah a váha sheetu odráží, co systém považuje za důležité** (Wikipedia
  *Character sheet*): D&D dává váhu boji, Call of Cthulhu dovednostem. U
  Dračího doupěte je to boj + zdroje (životy, magy, únava) + **náklad a
  pohyb** (celá kapitola o naložení a únavě) — a to by se mělo projevit v
  hierarchii.
- **D&D Beyond** (komunitní diskuse): web verze je ceněna za hustotu, která
  se blíží papírovému sheetu; mobilní aplikace je kritizována jako jen
  zmenšené rozhraní nevhodné pro tablet; oficiálně deklarovaný cíl byl
  sheet připomínající papír a přizpůsobitelný tomu, co hráč chce vidět. →
  tablet na šířku je plnohodnotné zařízení (kap. 5.5).
- **Foundry VTT moduly** (CRUX, Action Pack, Tidy 5e Sheet, GG Sheet
  Export): opakující se vzor je **boční "tray" s akcemi**
  (oblíbené/vybavené/schopnosti/kouzla/předměty), aby hráč nemusel přepínat
  záložky; a **jednoduchý "single-scroll" přehled celé postavy** s exportem
  do PDF/HTML. → kap. 5.3.
- **Pathbuilder 2e** (Pathfinder): zkušení hráči doporučují tablet/notebook
  před telefonem pro hru; přepínání mezi dialogy a záložkami je typická
  stížnost. → rychlé akce nesmějí vyžadovat zavírání dialogů.

Zdroje (vyhledávání):
- https://roll20.net/compendium/dračíhlídka/HERNÍ POSTAVA
- https://analoggamestudies.org/2014/12/visual-design-as-metaphor-the-evolution-of-a-character-sheet/
- https://nodiceleftbehind.substack.com/p/rpg-design-notes-3-character-sheet
- https://en.wikipedia.org/wiki/Character_sheet
- https://rpgnarco.com/designing-the-character-sheet-of-the-future/
- https://foundryvtt.com/packages/crux , https://foundryvtt.com/packages/action-pack/ ,
  https://foundryvtt.com/packages/gg-sheet-export
- https://www.dndbeyond.com/forums/d-d-beyond-general/d-d-beyond-feedback/177402-mobile-app-on-tablet
- https://apps.apple.com/tm/app/pathbuilder-2e/id6740821064

---

## 11. Co už existuje (první funkční iterace)

Než vznikl tenhle návrh, postavil jsem funkčně úplnou první verzi, aby šly
ověřit výpočty a data proti pravidlům. **Není to závazný vzor vzhledu** —
slouží jako přehled chování, které návrh musí pokrýt (a jako výchozí bod,
co jde zlepšit). Snímky: `docs/zadani-osobni-denik-img/03-denik-prvni-verze.png`
(deník, desktop), `04-tisk-prvni-verze.png` (tisková A4 verze, předvyplněná
Sindor z příkladu v pravidlech), `05-druzina-prvni-verze.png` (přehled družiny
pro PJ).

- **Trvale viditelný stavový pás** (sticky): jméno/rasa/povolání/úroveň, velké
  **OČ** a **ÚČ** držené zbraně, měřidla Životy (± 1/5), Magenergie (± 1/5),
  Únava (± 1/5, stupeň štítkem), Naložení (pás s hranicemi ×1/×2/×3,
  štítek "neúplný součet"), Pohyblivost; pod tím navigace po sekcích.
- **Sekce:** Přehled (vlastnosti jako dlaždice stupeň + bonus, postřeh,
  zkušenost, rasa/doprava/jazyky, aktivní efekty) · Boj (OČ rozepsané
  KZ + obratnost, výběr zbroje a štítu z inventáře, stav v boji, tabulka
  zbraní s ÚČ tváří v tvář i střeleckým, dostřely, výběr držené zbraně) ·
  Schopnosti a kouzla (rozbalovací položky) · Vybavení (inventář s váhami,
  hledání v katalogu, peníze zl/st/md, náklad) · Postava (původ, osobnost,
  poznámky).
- **Akce přímo z deníku:** hod na útok / střelbu / obranu (1k6+ s
  opakováním po šestce + ÚČ/OČ + opravy, výsledek jako toast s rozpisem),
  seslat kouzlo a vypít lektvar s výběrem cíle, hod kostek schopnosti,
  přidání/odebrání předmětů a kouzel.
- **Chování:** rychlé zápisy bez přenačtení stránky, automatické obnovení
  každých 5 s (nepřepisuje to, co hráč právě píše), neúplná data se
  přiznávají ("čeká na migraci", "bez váhy").
- **Přehled družiny** (PJ): tabulka všech postav světa se stavem a tempem
  družiny (nejnižší pohyblivost).

Co v první verzi **chybí a návrh má řešit**: kontejnery (vaky/truhly) a
nosiče nákladu (zvíře/povoz), finty, přesvědčení, erb/portrét, historie
změn, režim "boj", doladění pro tablet/telefon, tisk jednou stranou.

---

## 12. Poznámka k datům (pro orientaci, ne pro designéra)

Co z kap. 3 se bude **doplňovat do datového modelu** souběžně s deníkem
(sleduje se v `docs/stav-projektu.md`): zkušenost na postavě, peníze
(zl/st/md), nošená zbraň/zbroj/štít, naučené schopnosti/finty/jazyky,
přesvědčení, kontejnery a umístění předmětů, nosiče (zvíře/povoz), SZ jako
číslo u zbraní, portrét/erb, soukromé osobní texty. Návrh by měl být
použitelný i před jejich dokončením (prázdné stavy).
