# Stav projektu Dračák VTT — orientační přehled

Tenhle soubor existuje, aby se v projektu dokázal rychle zorientovat
**jakýkoliv model/session, i bez historie konverzace** — co je hotové, kde
to je, co se právě dodělává a jaký je další krok. Není to design dokument
(ten je `docs/vtt-datovy-model-navrh-v1.md`, ale je to návrh z úplného
začátku VTT vrstvy a dost zastaral — neduplikovat info odtamtud sem,
spoléhat na tenhle soubor jako aktuální zdroj pravdy) ani seznam chyb v
přepisu pravidel (`docs/kontrolni-seznam-neuplnych-mist.md`).

**Pravidlo, podle kterého se tenhle soubor udržuje, je v `CLAUDE.md`** —
při každé netriviální práci (nová funkce, oprava dat, rozhodnutí, co něco
mění) se sem zapíše update ve stejném kroku, ne až "až bude čas".

## Pro novou session, co začíná tady

Přečti celý tenhle soubor, pak `CLAUDE.md` (hlavně pravidla o tom, že
zdroj pravdy o hře jsou pravidla v `content/`, ne repo/DB, a o
neodhadování mechaniky). **Fáze 1 a 2 ze sekce "Rozpracováno:
cestování..." jsou v kódu hotové**; **Fáze 1b (váha/kontejnery) je
hotová v kódu, stejně jako Fáze 2–5 (zvířata, povozy, kalkulačka cesty,
přepínače) — celé "cestování, naložení, únava" je tím hotové a nasazené,
žádné ruční SQL nečeká.** Další velký cíl je sekce "Plán: náhrada Owlbear
Rodeo" níž. Stav předchozí
konverzace je zapsaný přesně proto, aby ses nemusel/a ptát na věci, co
už byly rozhodnuté (viz "Architektonická rozhodnutí" a celá sekce
"Rozpracováno" níže — všechno v ní je buď přímo citovaný text pravidel,
nebo odsouhlasené uživatelem, ne můj odhad).

### Čeká na ruční spuštění přes phpMyAdmin (admin účet)

`migrate.php` běží pod DML-only účtem, takže DDL nikdy neprovede a **na
prvním takovém souboru deploy zastaví** (HTTP 500 v posledním kroku
`deploy.yml`; FTP část proběhne normálně, kód je nasazený). Všechno za
ním čeká, dokud se to neprovede ručně.

**Ruční DDL blok je v `docs/sql-k-rucnimu-spusteni.sql`** — jediný soubor, ve
kterém jsou všechny dosud neprovedené DDL příkazy; uživatel ho dostává jako
text ke zkopírování (viz pravidlo v `CLAUDE.md`). **Stav (2026-10-17): nic nečeká.** Blok „Iniciativa v2“ + „Zbraně a iniciativa“
uživatel provedl (kontrolní SELECT vyšel 1, 4, 3) po tom, co první „sql proběhlo“ nesedělo — deploy po předčasném přesunu `0093`
skončil na `1142 ALTER denied`, takže sloupce na serveru nebyly. **Poučení: migraci přesouvat do `database/migrations/` až podle
čísel z kontrolního SELECTu, ne podle slov.** Migrace `0091`–`0096` jsou v `database/migrations/`: sloupce efektů (`0091`) a data (`0092`),
sloupce iniciativy v2 (`0093`), efekt „Ztráta iniciativy“ (`0094`, `0097` — DML), sloupce zbraní (`0095`), jejich data (`0096`), chybějící zbraně (`0098`), opravy cen a Kůsy (`0099`) a držení kombinací (`0100`). Dosud provedené: tabulky `souboj_vyzva` (migrace `0088`, výzvy k hodu) a `nestvura_staty_sveta`
(migrace `0090`, malý deník nestvůry) jsou na produkci provedené, hody i deník nestvůry jsou zapnuté.
Bloky pro `0065`–`0081`, `DROP TABLE` tří mrtvých tabulek z 0008, `0084` (mapa),
`0085` (souboj, krok 1) i `0086` (souboj, krok 2b: `tokeny.hexu`, tabulky
`souboj_pohyb` se sloupcem `unava_bodu` a `tvary_tvoru`, efekt „Běh na krátké
vzdálenosti“) jsou na produkci provedené a `0082` (217 položek ceníku) a `0083`
proběhly samy přes `migrate.php` (stejně tak `0089` — řádky vícenásobných útoků do iniciativy a `0092` — data efektů). Soubor je prázdný, dokud nepřibude další CREATE/ALTER.

Kód je napsaný tak, že funguje i před ručními kroky (chybějící sloupce
se berou jako "není"), jen se nic z toho nedá ukládat.

---

## Co to je

Dračák VTT (`vtt.zvelebil.online`) — virtuální stůl pro Dračí doupě (DrD),
postavený přímo nad databází pravidel (ne nad obecným systémem jako
Roll20/Foundry). PHP bez frameworku + MariaDB, FTP deploy na WEDOS přes
GitHub Actions (`.github/workflows/deploy.yml`). Žádný real-time přes
WebSocket — jen krátký polling (1–2 s).

**Proč vůbec existuje databáze pravidel, ne jen text k přečtení:**
strukturovaná pole (`kostky`, `efekty` u kouzel/lektvarů/schopností) jsou
mechanický hák, co má engine za hry skutečně spustit — viz CLAUDE.md,
sekce "Proč DB vůbec existuje".

## Jak se v repu zorientovat

- **`CLAUDE.md`** — závazná pravidla repa (deploy, migrace, co nikdy
  necommitovat, disciplína ověřování herní mechaniky v pravidlech). Číst
  první, platí nade vším.
- **`docs/kontrolni-seznam-neuplnych-mist.md`** — co v přepisu pravidel
  chybí/je poškozené, po stranách konkrétní knihy (PPZ/PPP/PPE/Bestiář/PJ).
  Než se řekne "tahle tabulka v pravidlech není", zkontrolovat nejdřív
  tenhle seznam A `content/toc-*.html` (viz CLAUDE.md, pravidlo o
  nejistotě v mechanice).
- **`docs/vtt-datovy-model-navrh-v1.md`**, **`docs/db-model-navrh-v1.md`**
  — původní návrhy before-the-fact, historické, dost odlišné od toho, co
  se nakonec postavilo. Nečíst jako aktuální stav.
- **Tenhle soubor** — aktuální stav, after-the-fact.

### Lokální ověření na skutečné DB (funguje v cloud session)

Testy nad zjednodušenými tabulkami (SQLite) přehlédly chybu v 0064
(Naro'shai — viz 0069), proto: **migrace i UI ověřuj proti skutečné
MariaDB.** Postup, který v session fungoval:
1. `apt-get install -y mariadb-server`, spustit `mysqld_safe --user=mysql &`.
2. `mariadb -uroot --default-character-set=utf8mb4` — **bez tohoto
   přepínače se UTF-8 v importu rozbije** a `WHERE nazev = 'Trpaslík'`
   tiše nezasáhne nic. Import: `database/drd-db-full-v1.sql`, pak
   `database/0002_ucty_a_role.sql`, pak `database/migrations/*.sql` po
   řadě (root má DDL, takže projdou všechny).
3. Lokální `config.php` (je v `.gitignore`) s DB uživatelem na
   `127.0.0.1`; testovací účet přes `password_hash()` do `ucty`.
4. `php -S 127.0.0.1:8099 -t .` a Playwright (`/opt/node22/lib/node_modules/playwright`,
   Chromium `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`,
   `--no-sandbox`) — přihlásit přes `index.php`, screenshot stránky.

---

## Hotovo — podle oblastí

### Účty, role, oprávnění
- `ucty.role` (admin/pj/hrac) je dnes jen globální override pro
  **admin** a kosmetický default pro pj/hrac — **žádnou** autorizační váhu
  uvnitř konkrétního světa nemá.
- **Oprávnění PJ je per-svět**: `dracak_vtt_je_pj_sveta($user, $svetId)`
  (`includes/vtt.php`) — porovnává `svet.pj_ucet_id`, admin je vždycky
  true, kontrolováno VŽDY jako první (kvůli bugu "admin ztrácí override
  když je svetId 0/neplatné", co se objevil a opravil 3×). Hráč může mít
  vlastní svět jako PJ.
- `includes/auth.php` — samostatná, NEZÁVISLÁ osa: kdo smí editovat
  *obsah pravidlové knihy* (`dracak_can_edit*`), nemá nic společného s VTT
  oprávněním ve světě.

### VTT základ
- Svět → Mapy (`typ_mapy` ENUM `svet`/`zona`) → Tokeny (postava/nestvůra).
- Polling: `hra/api/stav.php` (snapshot), `hra/api/udalosti.php` (delta od
  posledního ID z `svet_udalosti`).
- Grid (čtverec/hex), zdi (`blokuje_pohyb`/`blokuje_vystrel` odděleně),
  měřítko px→sáh (`dracak_vtt_px_na_sah`).

### Boj
- **Souboj s fázemi (blok B, krok 1; hotový a zapnutý na produkci, ruční SQL 0085 proběhlo):**
  PJ klikne na „Zahájit souboj“ (meče v liště) a vybere účastníky, jejich strany
  (družina / nepřátelé) a překvapení (−6 k iniciativě v 1. kole, řádek
  „Překvapení“ z `iniciativa_bonusy`). Kolo má tři fáze (`kolo_stav.faze`):
  **úmysly** (každý ohlásí, co zamýšlí; úmysl „kouzlení“ přidá automaticky bonus
  +1) → **iniciativa** (u každého tlačítko „Hodit“ → 3D kostka, hodnota z kostky
  je hod a posílá se na server; nebo ruční zápis z fyzické kostky; PJ hází jedním
  hodem za nestvůry stejného druhu; únava, naložení, překvapení a kouzlení se
  přičtou samy) → **tahy** ve dvou půlkách kola (HB, b10819): v 1. půlce každý
  podle iniciativy odehraje větší půlku akcí, ve 2. zbytek; kvóta tahu se počítá
  z aktuálního stavu (`dracak_vtt_souboj_kvota`), takže Rychlost (zdvojí zbývající
  akce) a protoplazma (sníží na polovinu) fungují přes PJ tlačítka ×2 / ½ v řádku
  tvora. Po 2. půlce kolo končí samo (odpočet efektů, číslo kola +1, fáze úmyslů
  znovu; hází se na začátku každého kola, h1622). Tvor s výsledkem ≤ 0 nehraje.
  **Hráč nevidí iniciativu ani úmysly nestvůr a postav ovládaných PJ** (ani v
  pořadí, ani v logu: události nesou `skryta` a `udalosti.php` je hráčům
  nepošle); iniciativu postav hráčů vidí všichni. Účastník přidaný uprostřed
  kola nemá akce — akce plynou z iniciativy, která se hází na začátku kola — takže
  v panelu stojí v seznamu „Nastoupí v příštím kole“ a hází až v dalším kole
  (rozhodnutí uživatele, 2026-10-15; `souboj_hod.php` hod ve fázi tahů odmítne).
  V tomhle kroku „Provést akci“ jen odečte akci a zapíše ji do logu — pohyb po
  hexech, útok a obrana přijdou v krocích 2–3.
  Kód: `includes/vtt_souboj.php`, `hra/api/souboj_start|souboj_ucastnici|
  souboj_faze|souboj_hod|umysl_nastavit|tah_akce|tah_konec|akce_uprava.php`,
  klient `assets/js/souboj.js`. Bez ručního SQL zůstává dnešní jednoduchá
  iniciativa (starý panel, `iniciativa_hod.php`, `dalsi_tah.php`).
- **Klikací tah (blok B, „tah v2“, etapa 1; hotovo 2026-10-16, návrh `docs/navrh-tahu-v2.md`):**
  lišta akcí (Krok, Běh, Sprint, Otočka, Útok…) **je pryč**. Plán tahu začne sám, jakmile je tvor
  na tahu (Esc / „Zrušit“ ho do dalšího tahu vypne, „Plánovat tah“ zapne), a hráč/PJ jen klikne:
  **na hexy** (body cesty A, B, C…), **na nepřítele** (systém sám naplánuje cestu k hexu, odkud
  dosáhne, a útok), **do vlastního těla** nebo ↶ ↷ (čelo na konci). Systém z toho pozná akci a cenu
  (`druhPlanu()` v `assets/js/souboj.js`): 1 hex Krok, 2 Běh, 3 Sprint (4 s dovedností), s útokem
  **Krok, Běh i Sprint a útok = vždy 1 akce** (nové druhy `beh_utok`, `sprint_utok`; oprava
  uživatele 2026-10-16), bez pohybu Útok / Otočka a útok / Otočka. Plán ukazuje „Sprint a útok · 1
  akce (v tomhle tahu zbývá N)“; „Bez útoku“ nechá jen pohyb, „Bez pohybu“ zaútočí z místa. Potvrzení: plovoucí ✓ u konce cesty,
  „Potvrdit“, Enter, nebo dvojklik (na posledním bodu / cíli útoku). Zbylé akce (Čekání, Obrana,
  Obrana a krok, Jiná činnost, Kouzlení) jsou v menu „Další akce…“; čtvercová mapa zůstává na
  prostém seznamu akcí. **Běh a Sprint jsou kdykoli** (úmysl Pohyb je nehlídá — migrace 0087),
  únava je omezuje (unavená postava max. běh, vyčerpaná jen krok). Plán se průběžně přepočítává
  (dosah, obsazené hexy). **Omezení:** cestu k nepříteli hledá jen nejkratšími trasami (bez
  objížděk).
- **Hody a výsledek (blok B, „tah v2“, etapa 2; hotovo a na produkci 2026-10-16, návrh `docs/navrh-tahu-v2.md`):**
  **výzva k hodu** = čekající hod viditelný pro všechny u stolu (karta dole uprostřed mapy,
  `assets/js/souboj.js`; stav v tabulce `souboj_vyzva` z ručního SQL — bez ní zůstává chování z etapy 1,
  útok se jen zapíše). Hody se nikdy nehází samy: tlačítko „Hodit 1k6+“ (3D kostka, po šestce se
  přehazuje a sčítá) nebo „Zapsat vlastní hod“ (součet z fyzické kostky); server hlídá řetěz šestek
  (`dracak_vtt_hody_k6plus_over`) a čísla počítá sám. **Útok → obrana → výsledek:** potvrzený útok
  (i „sprint a útok“) odehraje pohyb a založí výzvu „Útok“ — **tah se přeruší** (nejde další akce
  ani konec tahu, dokud se hod nedohraje nebo ho PJ nezruší). ÚČ: postava z deníku (vybraná zbraň —
  držená, jinak první s ÚČ), nestvůra z textu bestiáře (`dracak_vtt_nestvura_uc`: číslo za „=“, za lomítkem
  útočnost; víc útoků v zápisu = výběr „zbraň / útok“; vzorec bez výsledku se nehádá a PJ číslo doplní).
  Opravy server přidá sám: méně než 1/3 životů (bojeschopnost), −1 velké naložení, **rozdíl velikostí**
  (tabulka h1610 b10702–b10708, `DRACAK_VTT_ROZDIL_VELIKOSTI`; u nejasné velikosti nestvůry „B až C“ jen
  upozornění a PJ opraví ručně), **rozběh +1** (HB, dvě akce sprintu za sebou a útok — z událostí
  `akce_provedena`, `dracak_vtt_souboj_sprinty_v_rade`). **PJ smí před hodem změnit** základ (ÚČ/OČ
  nestvůry), útočnost a „opravu (PJ)“ (−30 až +30) — hráči u postav tu opravu vidí; **staty nestvůr (ÚČ, OČ,
  opravy, útočnost, životy skrytého tvora) hráč nevidí** (jako bestiář), u hodu nestvůry má jen hody a celkový
  součet. Po hodu útoku vznikne výzva
  **„Obrana“** pro napadeného: „Bránit se (stojí akci)“ (obrana zbraně OZ, bez zbraně −3; z boku nebo zezadu
  navíc −4) nebo „Nebránit se (−3)“ (z boku nebo zezadu dohromady −6) — h1611 a h1623; bránit se akcí smí jen
  ten, kdo má ještě akci (PJ smí vždy), a obrana akcí mu jednu akci vezme. Strana útoku se pozná z čela
  obránce (`dracak_vtt_utok_strana`). Volí hráč napadené postavy, nebo PJ za něj (bez časovače). Po hodu
  obrany server vyhodnotí: zásah = útok ≥ obrana, zranění = rozdíl + útočnost zbraně (aspoň 1), odečte
  životy (`postavy.aktualni_hp` / `nestvura_instance.aktualni_hp`, událost `hp_zmena`, nejníž na 0),
  zapíše `souboj_vysledek` a tah útočníka pokračuje (`dracak_vtt_souboj_posun`). **Vyřazený tvor**
  (životy na mezi vyřazení nebo pod ní, `dracak_vtt_souboj_vyrazen`) **tah přeskočí** a hráč za něj nemůže
  jednat. PJ vidí navíc „PJ: zrušit hod a jít dál“ (nikdo neutrpí zranění, akce útočníka zůstává utracená).
  **Čtvrtý hex sprintu bez dovednosti Běh na krátké vzdálenosti:** plán o čtyřech hexech se smí naplánovat;
  potvrzení cestu ověří, ale neprovede — založí výzvu **„Hod proti pasti“** (1k10 + bonus za sílu ≥ obtížnost,
  h1686/h1698, skill `zvlastni_schopnosti` 229); PJ ji vidí a nastaví obtížnost (hráč ji nevidí; číslo
  „střední“ kniha v textu nepřevádí, takže se předvyplní z posledního hodu stejné obtížnosti ve světě).
  Úspěch → efekt „Běh na krátké vzdálenosti“ (3 kola, efekt 120) a uložený pohyb se provede; neúspěch → nic se
  nepohne a akce se neutratí. Kód: `includes/vtt_souboj_utok.php` (výzvy, ÚČ/OČ, zranění),
  `includes/vtt_souboj_past.php`, `includes/vtt_souboj_akce.php` (provedení akce, společné pro `tah_akce.php`
  a pokračování po hodu proti pasti), `hra/api/vyzva_uprava|vyzva_hod|vyzva_zrus.php`. **Hráči se v etapě 1
  nedalo útočit klikem na nestvůru** (strana nestvůry nebyla v seznamu účastníků, který je pro hráče tajný) —
  opraveno: `kolo.strany` nese stranu všech tokenů viditelných na mapě.
  **Omezení / další kroky (viz návrh):** OČ a ÚČ nestvůr, které se z textu nespočítají (třeba Goblin: „+1 + síla
  zbraně“), doplňuje PJ před každým hodem — to vyřeší až malý deník nestvůry (krok 4); postava bez držené
  zbraně útočí první zbraní s ÚČ (výběr zbraně přijde v etapě 4); neřeší se „obrana a krok“ (kniha neříká, kdy
  se krok udělá — čeká na rozhodnutí), útok na vyřazeného / neviditelného / kouzlícího (nehybný obránce se řeší jen z efektů — Spánek, Ochromení), čtyřstupňová škála hodu na dovednost (fatální
  úspěch / neúspěch — v přepisu pravidel chybí). **Vícenásobné útoky se v rozšířeném systému řeší bonusem
  k iniciativě** (Tabulka vícenásobných útoků v pravidlech PJ — bestiář; každý útok i obrana = jedna akce), takže
  v útoku nic zvláštního nechybí; kniha jen dodává, že nestvůra s víc druhy útoku je střídá v uvedeném pořadí.
- **Malý deník nestvůry (blok B, krok 4 – základ; hotovo a na produkci 2026-10-16):**
  klik PJ na nestvůru otevře panel tokenu, nahoře je **Deník nestvůry**: přehled z bestiáře (velikost, odolnost,
  životaschopnost, pohyblivost, inteligence, charisma, bojovnost, text ÚČ a OČ, schopnosti, životy a mez vyřazení)
  a **čísla pro boj** — útoky (název · ÚČ · útočnost), OČ, OZ, velikost (A0–E), odolnost. Předvyplní se z textu
  bestiáře (`includes/vtt_nestvury.php`: číslo za „=“, za lomítkem útočnost; u goblina nespočítá nic, u vlka dvě útoky
  a OČ 4), pole, která se spočítat nedala, jsou žlutá. „Uložit pro všechny <druh>“ uloží čísla pro **druh nestvůry
  v tomhle světě** (ne pro jednu instanci), „Vrátit podle bestiáře“ je smaže. Hody je berou samy (`dracak_vtt_souboj_bojovnik`),
  takže PJ je nepíše u každého hodu; nestvůra s víc útoky je střídá v uvedeném pořadí (drápy, tlama, drápy… — PJ-kniha, bestiář).
  Jen PJ (bestiář je tajný), endpoint `hra/api/nestvura_denik.php`. Bez tabulky je panel jen přehled s vypnutým uložením.
  **Zbývá:** kořist, vybavení nestvůry z katalogu (ÚČ/OČ dopočtem ze zbraně a zbroje), inteligence / charisma /
  pohyblivost / vytrvalost jako hratelná data, kouzla nestvůr, automatický bonus k iniciativě podle „N× za kolo“.
- **Iniciativa v2 a útok na vyřazeného (blok B, krok 6; v kódu hotové a otestované, zapne se ručním SQL, viz „Čeká na ruční spuštění“):**
  uživatel při zkoušce zjistil, že jde zaškrtnout víc řádků Tabulky vícenásobných útoků najednou („3 za 2 kola“ i „2 za kolo“ = +9)
  a bonus za úkon se dá uplatnit bez ohlášení. Teď (h1622): **hráč nejdřív ohlásí úmysl** (kouzlení / útok na blízko / střelba…)
  a při Útoku nebo Střelbě zadá **jedno pole „útoků za 2 kola“** (2–8; zůstává mezi koly; bonus je řádek tabulky, tj. 3 × (počet − 2):
  +3 … +18). Bez ohlášeného úmyslu se vícenásobné útoky nezapočítají a ručně zaškrtnuté řádky tabulky se v dialogu hodu
  vůbec nenabízejí (server je ignoruje). Dialog hodu říká, co se přičte samo (únava, naložení, překvapení, kouzlení +1,
  vícenásobné útoky z úmyslu) a k čemu se hráč zavazuje. **Slib úkonu** (`kolo_iniciativa.zavazek`): kdo dostal kladný bonus za
  kouzlení nebo vícenásobné útoky, má mu věnovat **první vlastní akci kola a aspoň polovinu akcí kola** (polovina = akce při
  hodu / 2 nahoru). **Nic se nezakazuje** (rozhodnutí uživatele 2026-10-17, u první akce potvrzeno téhož dne): porušení se trestá
  **−3 k iniciativě v příštím kole** (řádek „Automatická ztráta iniciativy v základním systému“). Dvě místa, jedno pravidlo:
  (1) první akce kola, která není slíbený úkon, potrestá hned `dracak_vtt_souboj_proved_akci` (po zápisu akce, před posunem tahu);
  (2) na konci kola (`dracak_vtt_souboj_nove_kolo`) se před smazáním `kolo_iniciativa` zjistí, komu `zavazek_hotovo < zavazek_akci`
  (`dracak_vtt_iniciativa_nesplneny_slib`). Trest = efekt **„Ztráta iniciativy (nesplněný slib)“** (`dracak_vtt_iniciativa_ztrata_zaloz`,
  migrace `0094` + `0097`, složka `ini_radek`): trvá 1 kolo, ale **kolo nasazení se nepočítá** (jako u kouzel), takže se nasadí v kole
  porušení a drží celé příští kolo. Jednou za kolo (dvojí porušení netrestá −6); kdo má efekt z minulého kola, dostane ho znovu na
  další. Je vidět v panelu efektů tokenu i v logu („první akce kola měla být kouzlení, byla čekání“, „nesplněný slib: kouzlení 1/2
  akcí“). **PJ výjimka:** „Zrušit slib“ u řádku tvora v pořadí tahů (před akcí), nebo efekt sundat v panelu efektů. Netrestá se
  vyřazený, spící ani ochromený tvor (jen u konce kola). **Obrana** se počítá jako bojová akce (bestiář: každý útok i obrana = jedna
  akce), takže plní slíbený útok, ale do „první akce kola“ se nepočítá (je vynucená, děje se před vlastním tahem); střelba zatím
  vlastní akci nemá, splní ji útok. Počítadla jsou ve sloupcích (`zavazek_hotovo`, `akce_hotovo`), nuluje je nový hod iniciativy.
  Pořadí tahů ukazuje „slib: kouzlení 0/2“.
  **Postih za zbraň v ruce** (tabulky zbraní uživatele, b14664–b14827; ruční SQL 0095, data 0096): zbraně mají `chvat`
  (jednoruční / obouruční), `trida_zbrane` (lehká … supertěžká) a `ini_postih` (−2 těžké obouruční zbraně označené * a všechny
  supertěžké kromě Řetězového meče, −3 těžká kuše). Postava s takovou zbraní v ruce (`postavy.drzena_zbran_id`) ho dostane **sama**
  při hodu iniciativy, když ohlásila Útok / Střelbu nebo žádný úmysl (kouzlení, pohyb, čekání a útěk postih nemají); ruční
  zaškrtávátka „Obouruční zbraň“ a „Těžká kuše“ jsou u postav zamčená, u nestvůr (bez údajů o zbrani) zůstávají. Výjimky povolání
  (`DRACAK_VTT_INI_POSTIH_VYJIMKY` v `vtt_souboj.php`): Bojovník od 6. úrovně (každá zbraň pro boj tváří v tvář), Šermíř od 8. úrovně
  (obouruční meč), Lovec stínů od 7. úrovně (každá zbraň tváří v tvář), Lučištník – Vertolův luk, Lupič a Erythen – Fuuma shuriken,
  Mechanik – Arbalest, Divoch – Těžký kyj (super těžký) / Obří kyj / Válečný totem. Log říká důvod („Cep (těžká obouruční zbraň): postih −2
  zrušen (Bojovník od 6. úrovně)“). Supertěžké zbraně (poznámky pod tabulkou, potvrzeno 2026-10-17): Bojovník nemá postih u těch, které nejsou podobné meči, Šermíř u těch,
  které jsou (`DRACAK_VTT_MECE_SUPERTEZKE`: Odachi, Flamberg, Warglaive, Obouruční meč supertěžký, Dvě katany, Dva dlouhé meče, Dvakrát
  warglaive), obojí bez omezení úrovní. **Katalog doplněn** migrací `0098` o 39 zbraní z tabulek (Bastard a Válečné kladivo obouruční, Kůsa,
  Obouruční meč supertěžký jednoruční a kombinace dvou zbraní); ceny čtyř kombinací, které v tabulce nesedí na součet dílů, jsou přepsané na součet a Kůsa je sečná (migrace `0099`, na přání uživatele). **Bonusy Bojovníka podle úrovně a zbraně** (h139 b985–b993, `DRACAK_VTT_INI_BONUSY_POVOLANI`): +3 tváří v tvář od 5. úrovně,
  jednokusá obouruční zbraň +6 / +9 / +12 (10. / 16. / 27. úroveň), jednoruční zbraň a kombinace +6 / +9 (15. / 28.), střelné a vrhací zbraně +3 / +6
  (12. / 24.; těžká kuše ne). Nesčítají se (jsou to řádky Tabulky vícenásobných útoků), přičtou se samy při úmyslu Útok / Střelba se zbraní
  v ruce, nahrazují pole „útoků za 2 kola“ (klient ukáže „+9 povolání“ místo pole) a zavazují ke slibu úkonu. Držení zbraně: `chvat` =
  `jednoruci` / `obourucni` (jen jednokusá zbraň v obou rukách) / `kombinace` (dvě zbraně, počítá se jako jednoruční; migrace `0100`).
  **Nerozhodnuto:** těžká kuše u bojovníka (kniha neříká úroveň), Mistr zbraně, bonusy ostatních povolání — viz
  `docs/kontrolni-seznam-neuplnych-mist.md`. **Útok na vyřazeného** (h1613 b10736–b10737): tvor na mezi vyřazení nebo pod ní se brání jako nehybný (místo bonusu za
  obratnost −5, −3 bez zbraně, bez štítu, bez oprav za velikost a zezadu), bránit se zbraní nesmí a **zranění se násobí
  úrovní** (postava nejvýš ×9, nestvůra životaschopností; u „3 + 4“ násobek doplní PJ na kartě obrany). Stejná cesta jako u
  spícího (efekt Spánek). Nepokryto: kouzlící tvor jako nehybný.
- **Efekty s opravdovou mechanikou (blok B, krok 5; hotové a na produkci 2026-10-17, ruční SQL 0091 proběhlo):**
  dřív byl efekt jen text (název, popis, „+2 Iniciativa“ jako věta) a boj ho ignoroval — lektvar Rychlosti na postavě
  nedělal nic. Teď má každý efekt v DB `mechanika_stav` (`auto` boj vyhodnotí všechno sám · `cast` čísla a akce boj, zbytek
  PJ · `pj` jen štítek s popisem · `past` navazuje na pasti a jedy · `ceka` v knize chybí údaj), výchozí `trvani_kol`,
  `kolo_se_nepocita` (kolo seslání se do trvání nepočítá, h1626 b10848; stejně i u lektvaru Rychlosti — rozhodnutí uživatele 2026-10-17), `opakovani` (`secti` = opakované seslání trvání sčítá,
  účinek ne — Rychlost, Protoplazma) a **složky v tabulce `efekt_slozky`** (co přesně dělá): `akce_zdvojit`, `akce_pulit`,
  `akce_zmena`, `ini_radek` (řádek Tabulky bonusů a postihů k iniciativě: Kouzlo Rychlost +6, Kouzlo Protoplazma −3, v dalších
  kolech), `ini_bonus`, `uc_bonus`, `oc_bonus`, `beh_hexu`, `nemuze_jednat`, `nemuze_branit`, `nehybny` (h1613), `zraneni_x_uroven` (spící jako vyřazený: zranění útoku na něj se násobí jeho
  úrovní, postava nejvýš ×9, nestvůra životaschopností — h1613 b10736–b10737, rozhodnutí uživatele 2026-10-17; u nestvůry se
  složeným údajem „3 + 4“ násobek PJ doplní na kartě obrany) a `tik_zivoty`
  (kostky na začátku každého kola). Kód: **`includes/vtt_efekty.php`** (jediné místo, které zapisuje do `aktivni_efekty`:
  `dracak_vtt_efekt_aplikuj`; souhrn mechaniky tvora `dracak_vtt_efekty_souhrn`; odpočet trvání; tiky), napojení v
  `vtt_souboj.php` (iniciativa: efektové bonusy se přičtou samy a ručně zaškrtnutý stejný řádek se nezapočítá podruhé;
  `nemuze_jednat` = 0 akcí a přeskočený tah), `vtt_souboj_utok.php` (ÚČ/OČ z efektů; **nehybný obránce**: místo bonusu za
  obratnost −5, −3 bez zbraně, bez štítu a bez oprav za velikost a zezadu; tvor, který se nemůže bránit, nemá volbu „bránit se“
  ani pro PJ), `vtt_souboj_pohyb.php` (+hex k běhu ze složky `beh_hexu`). Vstupy: `efekt_pridat.php` (PJ; prázdné „kol“ =
  výchozí trvání, 0 = bez limitu), `efekt_odebrat.php` (nové, ✕ v panelu tokenu), `pouzij_predmet.php` (kouzlo / lektvar;
  Rychlost kouzlem se zeptá na počet kol, než se utratí magenergie), hod proti pasti u 4. hexu sprintu. Změna akcí při nasazení
  (Rychlost ×2, Protoplazma ½ dolů, −2 akce) se zapíše do události, takže „krok zpět“ ji vrátí. **Kouzlo s hodem proti pasti**
  (Protoplazma, spánek, ochromení…) stavový efekt samo nenasadí — odpověď řekne „cíl se brání hodem proti pasti (…)“ a PJ efekt
  po neúspěchu cíle přidá v panelu tokenu (hody cílů proti pasti zatím nemají vlastní výzvu). **Tiky** (krvácení, regenerace):
  na začátku kola vznikne tvoru výzva „tik“ — tlačítko → 3D kostka, nebo hod z vlastní kostky; tik tah nezdržuje a PJ ho
  smí zrušit. UI: panel tokenu ukazuje aktivní efekty (zbývá kol, co boj dělá, ✕ sundat), nabídka efektů má značky ⚙ ◐ ✋ ❓
  a výchozí trvání, odznak na tokenu má tooltip, deník postavy ukazuje, co efekt dělá.
  **Co je zpracované (17 efektů):** Rychlost (lektvar, kouzlo), Protoplazma, Spánek, Ochromení, Ochromení Černou zhoubou,
  Krvácení a Ochromení trollobijce, Sražen na zem, Zastrašen (útěk, i totální úspěch), Berserk (+6 iniciativa),
  Regenerace (lektvar, Prasiolit), Běh na krátké vzdálenosti. Ostatních ~100 zůstává `pj` / `past` / `ceka` — kniha u nich
  nemá jasný mechanický dopad v boji (aury, drahokamy, vlastnosti, informace), nebo ten dopad závisí na tom, co VTT nezná
  (chování, podmínky); seznam s důvody je v `docs/kontrolni-seznam-neuplnych-mist.md`. Bez ručního SQL beze změny
  (efekty jsou štítky, jen Běh se pozná podle názvu). Testy: `efekty_api`, `efekty_kouzla`, `efekty_ui`, `efekty_deg` (+ celé
  regresní sady souboje a hexů), ověřeno i na kopii produkce s DML-only účtem a `migrate.php`.
- **Akce na hexové mapě (blok B, krok 2a; hotový, bez ručního SQL):** lišta akcí
  u tvora na tahu (Krok, Otočka, Útok, Krok a útok, Otočka a útok, Obrana, Obrana a
  krok, Čekání, Jiná činnost, Kouzlení). Akce s mapovou částí se plánují klikem na
  mapě: **Krok** = zeleně označené volné sousední hexy (při úmyslu „Pohyb“ až o dva
  hexy, h1625) a otočení až o 120° (klik do cílového hexu směrem, kam se má tvor
  dívat; bez kliku zůstane čelem jak byl); **Otočka** = klik do vlastního hexu směrem,
  kam se otočí (na stejný směr ne); **Útok** = oranžově 3 hexy před tvorem (h1623),
  klik na tvora v nich zapíše cíl (hod a zranění přijdou v kroku 3); u spojení se cíl
  počítá z hexu a čela PO kroku/otočce. Potvrzení pošle akci na server
  (`tah_akce.php`), který pravidla ověří sám (`includes/vtt_souboj_pohyb.php`):
  sousedství a volný hex (stojí-li tam tvor, krok odmítne; vlastní „obecné“ tokeny
  jsou jen značky a nepřekáží), zeď (hráčům blokuje stejně jako při tažení, PJ smí
  přes), otočení ≤ 120° při kroku, cíl útoku jen v těch 3 hexech. Token se přesune na
  střed hexu, čelo se zapíše jako násobek 60° a mlha se odhalí kolem postavy.
  **V souboji na hexové mapě hráč tokenem netáhne ani ho volně neotáčí** (jen PJ);
  hýbe se akcemi. Na čtvercové mapě (bez hexového gridu) akce zatím jen odečtou
  jednu akci a zapíšou se do logu. Tři hexy před tvorem na tahu vidí na mapě všichni.
  Geometrie: `includes/vtt_hexy.php` a `assets/js/hexy.js` (stejná matematika,
  shoda ověřena porovnáním výstupů na mřížce buněk; směry čelem 0–5 po 60°, od
  východu po směru hodinových ručiček).
- **Tvorové na více hexech, Běh a Sprint (blok B, krok 2b; hotový a na produkci, ruční
  SQL 0086 proběhlo; rozhodnutí uživatele 2026-10-16):**
  - **Tvary** (tabulka `tvary_tvoru`, výchozí hodnoty v `includes/vtt_souboj_pravidla.php`):
    1 hex (A0, A, B a humanoidní C), 2 hexy (zvířecí C: otáčí se kolem **zadního** hexu,
    hlava je před ním), 3 hexy (D: trojúhelník, **spodní** hex je otočný) a 5 hexů
    (E: nahoře dva hexy, pod nimi řada tří, otočný je prostřední). Čelo tvarů D a E je
    **hrana obou horních hexů**; zvířecí C a jednohexoví hledí na jednu ze šesti stran.
    Velikost F neexistuje (potvrzeno), v DB ani v nabídce není. Počet hexů se nastavuje
    u tokenu (**PJ: panel tokenu → „Zabírá hexů“**; `token_uprava.php` klíč `hexu`);
    nestvůra přidaná na mapu ho dědí od posledního kusu téhož druhu, jinak podle třídy
    velikosti z bestiáře (D = 3, E = 5), zvířecí C (2 hexy) musí PJ přepnout ručně.
  - **Model v kódu:** `tokeny.x/y` = střed otočného hexu, `tokeny.smer` = úhel čela
    (u tvarů s čelní hranou úhel středu čelní hrany: 270° = nahoru, každá další
    orientace +60°), tvar = osové offsety vůči otočnému hexu při orientaci 0.
    Geometrie je v PHP (`includes/vtt_hexy.php`) i JS (`assets/js/hexy.js`), shoda ověřena.
  - **Pohyb** (tabulka `souboj_pohyb`): Krok 1 hex, **Běh 2 hexy a Sprint 3 hexy** (jen při
    úmyslu „Pohyb“ — v knize h1625 smí při úmyslu Pohyb krok o dva hexy; sprint = HB),
    **+1 hex k běhu i sprintu s dovedností Běh na krátké vzdálenosti** (h1698; PJ ji po
    úspěšném ověření nasadí postavě jako efekt „Běh na krátké vzdálenosti“ na 3 kola,
    souboj ji pozná podle efektu), **otočení v rámci pohybu celkem až 270° za akci** (HB;
    kniha 120°; rozhodnutí 2026-10-16): tvor se smí otáčet po 60° před pohybem, mezi hexy i
    po nich (např. otočka, dva hexy, otočka), součet všech otočení nejvýš 4 kroky (240°).
    Server i klient hledají přípustnou cestu jako „stavy“ (poloha, čelo, spotřeba otáčení):
    `dracak_vtt_hex_dosazitelne_stavy()` / `hexy.dosazitelneStavy()` (shoda ověřena na
    150 náhodných případech). Celé tělo postupuje hex po hexu přes volné hexy (zeď zastaví
    hráče, ne PJ), v každém mezistavu musí být tělo na volných hexech. U jednohexového tvora
    rozpočet nikdy nevadí (čisté otočení je nejvýš 180°), omezuje větší těla mezi překážkami.
    Otočka (samostatná akce) = otočení kolem otočného hexu o libovolný úhel.
  - **Únava:** každé použití **sprintu přidá postavě 1 bod únavy** (`souboj_pohyb.unava_bodu`,
    `postavy.unava`; nestvůry zatím únavu nemají). Podle tabulky únavy (h1634) **unavená
    postava (stupeň 1+) nesmí sprintovat a vyčerpaná (2+) ani běhat** — server to hráči
    zamítne (PJ smí rozhodnout jinak), klient tlačítko zamkne (`kolo.aktivni_zakazy`).
    Platí jen u postav s vyplněnou odolností.
  - **Útok:** jednohexový a zvířecí C (2 hexy) útočí na 3 hexy před hlavou; D a E na
    **všechny hexy sousedící s oběma horními (čelními) hexy** mimo vlastní tělo. Cíl je
    v dosahu, stojí-li v dosahu kterýkoli hex jeho těla.
  - **Mapa:** tělo tvora se kreslí průsvitnými hexy kolem otočného hexu a žlutou čarou na
    čelní hraně (SVG uvnitř elementu tokenu, `assets/js/mapa.js` `nakreslitTelo`). Rotační
    úchyt tokenu D/E se přichytává na 30° + 60°.
  - **Plánování cesty (2c, 2026-10-16):** u Kroku, Běhu a Sprintu hráč/PJ **klikáním zadává
    body cesty A, B, C (s dovedností až D)** — každý bod je hex hned vedle předchozího, vzdálenější
    hex se doplní nejkratší trasou (z nejkratších vybere tu s nejmenším otáčením). Tvor se po
    cestě **sám natáčí ve směru kroku** (u D/E ke dvěma čelům nejblíž současnému), otočení se
    počítá do rozpočtu 270° (ukazuje „Otočení celkem: 120° z 270°“); přepínač **„Držet čelo“**
    (pamatuje si ho prohlížeč) vypne natáčení — couvání, úkroky. Konečné čelo po posledním bodu
    jde změnit klikem do těla nebo ↶ ↷ (stojí otočení). **Dvojklik na posledním bodu** nebo
    „Potvrdit“ pohyb provede, **„↩ Bod“ / Backspace** vrátí poslední bod, **Enter** potvrdí.
    Klient posílá `cesta: [{col,row,smer}]` + `smer` (čelo na konci); server ověří stejnou funkcí
    (`dracak_vtt_hex_over_cestu` = `hexy.overCestu`, shoda ověřena na 400 náhodných případech):
    sousedství bodů, volná těla v každé poloze i při otáčení na místě, zeď (hráče zastaví),
    dosah akce a rozpočet otočení. Starší tvar požadavku `cil` + `smer` (server si cestu najde
    sám, `dracak_vtt_hex_dosazitelne_stavy`) zůstává pro API a testy. Šipky čela a náhled těla
    na konci cesty se kreslí jako dřív.
  - **Kniha** (`/pravidla.php`): HB bloky b93073–b93078 (krok a otáčení celkem 270° za akci, běh,
    sprint + bod únavy, dovednost, Pohyb), b93079–b93087 (tvary, otočný hex, čelo, útok) a PJ b94004 + přepsaný b17388
    (velikosti hexů).
  - **Co ještě chybí v kroku 2:** dosah dlouhých zbraní (spojí se s krokem 3 — bere se ze
    zbraně v ruce), pravidlo A0 (tvorové velikosti A0 smí sdílet hex; zatím se neuplatňuje,
    protože tokeny velikost nenesou), „Útěk“, omezení běhu/sprintu naložením,
    únava nestvůr (až s deníkem nestvůry), Rozený sprinter (trollobijec, b1507) a větší tvary z knihy (nosorožec 1×4, slon 2×5,
    drak).
- **3D kostky (`assets/js/kostky3d.js`):** dřív se kostka ukázala v rohu 300×150 px
  a její čísla s výsledkem v logu nesouhlasila (výsledek losoval server). Teď
  plátno vyplní okno a běžný hod kostkami (`kostka_hod.php`) bere čísla z kostek
  (server hlídá jen počet a rozsah); bez 3D kostek hodí server.
- Iniciativa + pořadí tahů (`includes/vtt_iniciativa.php`), vynucení
  pořadí (`dracak_vtt_je_na_tahu`). **Počet akcí z iniciativy je HOMEBREW, po
  třech** (`ceil(výsledek / 3)`: 1–3 = 1 akce, 4–6 = 2, 7–9 = 3, 10–12 = 4 …;
  rozhodnutí 2026-10-14) — kniha PPZ měla 1–6 = 2, 7–12 = 4; tabulka v
  `content/pravidla-hrac.html` (h1623) je přepsaná a označená jako HB, příklady
  v knize (iniciativa 3 → dvě akce) HB neodpovídají a jsou v seznamu ke korektuře.
  **Kolo se dělí na dvě poloviny (HB, b10819):** 1. polovina = větší půlka akcí
  každého tvora podle iniciativy, 2. polovina = zbytek (Rychlost/protoplazma mění
  počet zbývajících akcí). Smrt/vyřazení: jen mez vyřazení — vyřazená postava je
  nehratelná, o smrti po boji rozhoduje PJ. Velikost F neexistuje. Detaily:
  `docs/navrh-souboje-v1.md`.
- Mlha války — per hráč/mapa, respektuje grid buňky + LoS, PJ má
  obdélníkový výběr, hromadné odhalení/zatažení, náhled "jak to vidí
  hráč" (průsvitný jen v PJ náhledu, hráčova vlastní mlha je neprůhledná).
- Krok zpět (10 kroků) přes `svet_udalosti` payload (nese hodnotu PŘED i
  PO).
- Aktivní efekty, magenergie, inventář/loot z mrtvých nestvůr, rychlá
  volba efektů.
- Dostřel zbraní — **3 pásma** (malý/střední/velký, +1/0/−1 k útoku,
  h1615), ne 2 jako původně (migrace 0059).

### Postava
- Tvorba podle pravidel — atributy jako "stupeň" (h104, ne bonus).
- Pohyblivost a únava na kartě postavy (`hra/postava.php`, jen ke čtení)
  — viz "Fáze 1" v sekci "Rozpracováno" níže.
- Postava nezávislá na světě dokud není přiřazená (`postavy.svet_id`
  nullable), hráč si může připravit víc postav předem.

### Svět — dashboard, kalendář, administrace
- `hra/svet.php` — redesign na širokou plochu (dvě verze: `d0b815e`
  vlastní session, `009f97e` druhá nezávislá session/Opus 5.5 — pozor,
  může se stát že jiná session pushne přímo na main bez koordinace,
  stalo se jednou v týhle konverzaci).
- `svet.pocatecni_datum` + `svet.aktualni_den_offset` — herní kalendář,
  posun **výhradně ruční** (`hra/svet_administrace.php`) — automatický
  odvoz z pohybu by potřeboval přesně tu tabulku rychlosti, co se teď řeší
  (viz níže), proto se nezavádělo, dokud nebyla podložená.
- Administrace světa — automatizační přepínače (`auto_hod_kostkou`,
  `auto_aplikace_efektu`, `auto_vyhodnoceni_pasti`, `auto_zranitelnost`),
  posun data, úprava data založení.

### Poznámky (`svet_poznamky`)
- Kdokoli s přístupem do světa píše, autor vybírá KONKRÉTNÍ příjemce (ne
  "všem" přepínačem) — PJ světa NENÍ automatický příjemce, jen skutečný
  `role='admin'` vidí vše vždy (bezpečnostní dohled, potvrzeno podruhé v
  konverzaci, že tohle pravidlo platí beze změny).
- Nese `den_pri_vytvoreni` (snapshot herního data) + volitelně `mapa_id`.
- **Rozpracováno / naplánováno, ještě nepostaveno:** editace (bez
  historie — přepis na místě), vazba na konkrétní NPC/postavu
  (`postava_id`), tagy (quest/npc/loot/lore/misto, pevná sada s
  auto-návrhem), cascade zrušení sdílení při odebrání hráče ze světa,
  odznak nepřečtených, admin audit stránka. Nic z tohohle zatím není v
  kódu — jen domluvený plán (viz konverzace o Fázi 3/4).

### Piny (`mapa_body`)
- Místo na mapě, volitelně 1:1 vede na vlastní zónovou mapu
  (`cilova_mapa_id`). **Od migrace 0062 jdou zakládat na LIBOVOLNÉ mapě**
  (dřív jen na světové) — umožňuje vnořenou hierarchii svět→město→
  budova→místnost→sklep, libovolně hluboko.
- `svet_id` přímo na pinu (migrace 0063) — konzistentní s poznámkami,
  umožňuje kontrolu oprávnění bez JOINu přes `mapy`.
- 8 typů (mesto/vesnice/tajne_misto/budova/mistnost/sklep/prechod/jine),
  každý s vlastní vendorovanou ikonou.
- Editace (`hra/api/mapa_bod_upravit.php`) — název/typ/cíl/popis/
  viditelnost, NE pozice (x/y) ani mapa_id (ta se nepřesouvá).
- Breadcrumb (`dracak_vtt_mapa_cesta`) + postranní strom celého světa s
  hledáním (`dracak_vtt_mapa_strom`, `tb-strom` v `hra/mapa.php`) —
  **záměrně žádný `UNIQUE` na `cilova_mapa_id`** (jedna mapa smí mít dva
  piny, co na ni vedou — dva vchody do stejného sklepení je legitimní).
- "Cestovat sem" (`hra/api/pin_cestovat.php`) — **zatím jen ad-hoc PJ
  zadá počet dní ručně**, žádný dopočet podle pohyblivosti. To se teď
  staví, viz další sekce.
- NPC "co se tam mohou nacházet" = tokeny na cílové mapě vlastněné
  PJ/adminem — žádná zvláštní datová struktura navíc, reuse existujícího
  systému postav/tokenů.
- **Editace pozice** — v `popover-pin-upravit` (`hra/mapa.php`) jde
  marker přetáhnout na nové místo, dokud je formulář otevřený
  (`pinEditujiId` stav + `.pin-editing` CSS třída). Ukládá se spolu s
  ostatními poli až kliknutím na "Uložit změny", ne okamžitě na mouseup.
- **Nahrání nového obrázku existující mapy** — tužka v rámečku náhledu
  na `hra/svet.php` (světová mapa i zóny, jen PJ/admin), akce
  `upravit_obrazek_mapy`. Přepočítá `sirka_px`/`vyska_px` z nového
  souboru (nemusí sedět rozměr se starým). Grid se NEpřepočítá
  automaticky — PJ ho po nahrání zkontroluje ručně přes `tb-grid`. Mlha
  války hráčů se při nesedícím rozměru bezpečně vynuluje (existující
  kontrola v `dracak_vtt_mlha_nacti`), nepadá.

---

## Rozpracováno: cestování, pohyblivost, únava, naložení

**Stav (2026-10-14): všechny fáze jsou v kódu i na produkci (1, 1b, 2, 3, 4, 5);
žádné ruční DDL nečeká.** Zbývají jen drobnosti uvedené u jednotlivých fází.

### Co je podložené přímo v `content/pravidla-hrac.html` (ne odhad)

- **Vzorec finální Pohyblivosti** (h1634): `rasový základ + bonus za OBR +
  2× bonus za SÍLU` (bonusy ze stávající `opravy_za_atribut`). Naložení
  (násobek nosnosti) snižuje na ¾/½/¼.
- **TABULKA RYCHLOSTI PŘI CHŮZI** a **PŘI BĚHU** — jednotka: body pohybu
  za hodinu (chůze) / za kolo (běh), podle bonusu za pohyblivost a tempa
  (procházka/chůze/spěch; poklus/běh/sprint). **Hotovo (2026-10-13):**
  uživatel dodal foto, přepsáno do `content/pravidla-hrac.html` (h1634) a
  do DB (`rychlost_chuze`, `rychlost_behu`, migrace 0074; bonus −5…+5 z
  fota, +6…+10 z Bestiáře h2393). Ověřeno křížově: řady na sebe navazují
  (chůze +3/+5/+7, běh +4/+6/+9 na bod bonusu) a zjednodušené tabulky
  PPZ "Cestování pěšky/na koních" (h1681, mil za den) z nich vycházejí
  přesně (např. spěch 7 h po cestě = 7 × 70/10 = 49 mil = bonus +1;
  jezdecký kůň 750 mn: 8 h × 98/10 = 78 mil). Pozor na terminologii:
  "procházka (14 h)" v PPZ tabulkách odpovídá PPP **chůzi**, ne procházce.
- **TABULKA ÚNAVY** — **hotovo (2026-10-13):** přepsána z fota do
  `content/pravidla-hrac.html` (h1634) a do kódu jako strukturovaná
  konstanta `DRACAK_VTT_UNAVA_STUPNE` (postih k bonusu za sílu/obratnost,
  stupeň INT/CHA, zákazy, plazení, omdlévání). Starý souhrn b10870 byl
  neúplný (chyběl postih inteligence u "vyčerpaná" a "poklus smí") —
  opraveno. Engine ji **aplikuje**: pohyblivost (ověřeno příkladem Hruna
  b10871: unavený 19 → 16/+2), ÚČ, OČ, postřeh (INT), v deníku přeškrtnuté
  hodnoty "bez únavy" a varování při sesílání. **Záměrně neaplikováno:**
  nosnost (tabulka nosnosti zná jen bonus −5…+5, kniha o vlivu únavy
  na nosnost mlčí) — otevřená otázka pro uživatele.
- **Náročnost terénu — NALEZENO, ne chybí:** plná sada je v **pravidlech PJ
  h2366** (`content/pravidla-pj.html`): TABULKA POVRCHŮ (dobrá cesta 10,
  les 13–17, hustý les 15–25, bažina 30–80… — rozsahy, PJ volí), TABULKA
  TERÉNŮ (násobek: rovina/mírné zvlnění 1×, pahorkatina 1,5×, vrchovina
  2×, hory 3–4×, velehory 5×+) a TABULKA OSTATNÍCH VLIVŮ (zima, vedro,
  tma, mlha, déšť, bouře, hluboký sníh… — také násobky/rozsahy).
  Výsledná náročnost = povrch × terén × vliv; bodů/míli. Číselně sedí i
  sloupec "náročnost" v TABULCE NALOŽENÍ POVOZU (h1641: rovina 10,
  mírné zvlnění 12, pahorkatina 15, vrchovina 20, hory 30) a PPZ tabulky
  cestování (cesta 10, savana ~15, les ~20, hory ~40). Kniha výslovně říká,
  že čísla jsou orientační a volí je PJ.
- **TABULKA JEZDECKÝCH ZVÍŘAT** (h1635) — 7 zvířat (osel/mezek/mula/pony/
  tažný kůň/klasický kůň/jezdecký kůň), každé s pohyblivostí, nosností,
  vytrvalostí, "ujede (mil)" a "čas na 10 mil".
- **Povozy** (h1638–1644) — typy (vůz/kočár/dostavník), nosnost spřežení,
  přesný vzorec únavy cestujícího podle typu vozu (např. kočár: 1 bod
  únavy cestujícího za 10 bodů únavy koně).
- **Dovednosti, co přímo zasahují do naložení/pohyblivosti/únavy**
  (kapitola Dovednosti, h1692+ — existuje celá, nenechat se zmást
  jednou útržkovitou větou "mimo rozsah" u jiné pasáže, viz CLAUDE.md):
  - **Nosič** (h1752, odolnost) — úspěch = naložení o stupeň nižší.
  - **Nošení velkých břemen** (h1703, síla) — až 175 % nosnosti, jen
    rychlostí procházka, 3 body únavy/hodinu.
  - **Jízda na koni** (h1721, obratnost) — úspěch = žádná extra únava
    jezdci; neúspěch = +1 únava za 2 body únavy zvířete.
  - **Kočí** (h1724, obratnost) — ovlivňuje % šance nehody s povozem.
- **Pravidlo "družina = rychlost nejpomalejšího"** (pravidla-pj.html,
  h2355).

### Co chybí / zůstává otevřené
- Tabulka rychlosti lodí, veslování, bouří i Tabulka lodí — **dodané
  foto uživatelem 2026-10, doplněno přímo do
  `content/pravidla-hrac.html`** (h1646/h1651, nahrazeny původní "(Pozn.:
  ... nedochovalo se)" poznámky skutečnými tabulkami). Netýká se
  pozemního cestování, šlo jen o vedlejší nález při hledání tabulky
  terénu — zmíněno tu jen pro úplnost evidence, žádná VTT mechanika
  s loděmi zatím nevzniká.
- Nebyl ověřen zbytek kapitoly Dovednosti na další propojení (uživatel
  se ptal, jestli jsou zohledněné VŠECHNY dovednosti — zatím prošité jen
  tyhle 4 nejrelevantnější, ne celá kapitola řádek po řádku).

### ~~Zjištěná chyba ve stávajících datech~~ — OPRAVENO (migrace 0064)
`rasy.pohyblivost_zaklad` (migrace 0007) byla celá špatně (Trpaslík 20,
Člověk/Elf/Barbar 30, ostatní NULL). Skutečná **TABULKA POHYBLIVOSTI RAS**
je v h1681 (b11140–b11146) a hodnoty se shodují s příkladem Hruna v
h1634: hobit 10, kudůk 9, trpaslík 8, elf 12, člověk 11, barbar 12,
kroll 11. Homebrew rasy mají vlastní řádek "Pohyblivost:" ve svém bloku
"Vlastnosti" (Barnové/Caelové/Kobold/Naro´shai/Skaven/Tauren 12,
Furan/Gnollové/Tiefling 11, Magiliw 13) — citace b-id jsou v hlavičce
`0064_oprava_rasy_pohyblivost_zaklad.sql`. Trpasličí klany a "Kroll —
domácí rozšíření" vlastní hodnotu nemají, dědí ji za běhu od rodiče
(`rodic_rasa_id`) v `dracak_vtt_rasovy_zaklad_pohyblivosti()`.

### Architektonická rozhodnutí z konverzace (čekají na potvrzení/stavbu)
- **Zvíře vs. povoz mají jinou kardinalitu** — zvíře 1:1 (vlastnost
  postavy), povoz skupinově (víc lidí najednou). Potvrzeno uživatelem.
- **Jízda na cizím/vypůjčeném/ukradeném zvířeti nemusí jít přes
  inventář** — stačí vybrat z číselníku zvířat přímo na world mapě;
  vlastní zvíře v inventáři se nabídne jako první/doporučená volba, ale
  není to vynucené vlastnictví.
- Nový číselník `jizdni_zvirata` (7 řádků z tabulky výše) — zatím
  nezaložený.
- Potřeba nová pole/tabulka pro "na čem postava zrovna cestuje" (chůze
  vs. konkrétní zvíře) — umístění (inventář vs. přímo na mapě světa)
  zatím nerozhodnuté, viz otevřené otázky v konverzaci.
- Nové automatizační přepínače v administraci světa — `auto_naklad`,
  `auto_pohyblivost`, `auto_unava` (stejný vzor jako 4 existující
  přepínače), s možností hráč/PJ nastavit pohyblivost ručně jako
  zjednodušení místo plného výpočtu.
- **Výpočet musí být hodinová simulace, ne jeden vzorec na celý den** —
  únava se kumuluje postupně (1 h chůze = 1 bod) a nutí ke zpomalení/
  odpočinku; jediný denní paušál by to přeskočil a navíc by potřeboval
  nepodloženou konstantu "kolik hodin denně se pochoduje", která nikde v
  textu není.

### Další krok — SCHVÁLENÝ fázový plán (2026-10, čeká na realizaci)

Všechny fáze (1, 1b, 2, 3, 4, 5) jsou v kódu (viz níž).
Fáze 4 se dělala dřív než 3, protože ji odblokovaly fotky tabulek.

**Fáze 1 — Oprava dat + živá Pohyblivost/Únava na postavě — HOTOVO V KÓDU**
- `0064_oprava_rasy_pohyblivost_zaklad.sql` (čisté UPDATE, proběhne sama
  přes `migrate.php`) — opraveno pro 16 z 17 ras s hodnotou v textu, viz
  výš. **Naro'shai** zasáhla až `0069` (v DB se jmenuje s rovným
  apostrofem, 0064 hledala typografický).
- `0065_postava_unava.sql` — `postavy.unava SMALLINT UNSIGNED NOT NULL
  DEFAULT 0`. **DDL: MUSÍ se ručně spustit přes phpMyAdmin (admin účet)**;
  `migrate.php` ho pak při dalším deployi sám zapíše do `migrace_log`
  (pozná, že sloupec už existuje). Do té doby kód funguje (chybějící
  sloupec = únava 0), jen není co ukládat.
- `includes/vtt_pohyblivost.php`: `dracak_vtt_postava_pohyblivost()`
  (rasa + bonus OBR + 2× bonus SIL, volitelně náklad -> Tabulka
  naložení ¾/½/¼ se zaokrouhlením nahoru, ověřeno na celém příkladu
  Hruna z h1634: 19 → 5 → 15 → 19), `dracak_vtt_postava_unava_stupen()`
  (body vs. vytrvalost = Odolnost, prahy 1×/2×/3×/4× "nad").
  `postavy.pohyblivost` se neukládá (odvozená hodnota).
- UI: `hra/postava.php`, blok "Pohyb a únava", jen ke čtení.
- **Odchylky od původního plánu a otevřené body (rozhodnout):**
  - **Náklad z inventáře se počítá automaticky**
    (`dracak_vtt_postava_naklad_mn()`: `postava_predmety` ×
    `predmety.vaha`, desetinné mn; zobrazeno na `hra/postava.php`).
    První verze jsem napsal s tvrzením, že jednotky `vaha` nejsou
    ověřené — **to bylo špatně**: seed (`drd-db-full-v1.sql`, konvence
    nad INSERTem predmety) výslovně říká, že `predmety.vaha` je v mn,
    a ceník v pravidlech (h2055) je ve stejných jednotkách; 1 mn ≈ 50 g
    (h3 "Míry a váhy"). Skutečné mezery jsou jiné — viz "Fáze 1b".
    Položka bez váhy se nepočítá jako 0, ale hlásí se jako "neúplný
    součet".
  - ~~Strukturované postihy za únavu chybí~~ — **vyřešeno 2026-10-13**
    (foto Tabulky únavy přepsáno do `content/` i do kódu, engine postihy
    aplikuje; viz "Co je podložené" výš).
  - **Stínová únava** (h1634 b10869) se nerozlišuje — jeden součet bodů.
  - **Stará tabulka `stupne_unavy` (migrace 0008) nesedí s pravidly a
    nic ji nepoužívá** (model "hranice 10 + oprava za ODO", 3 stupně,
    jeden postih −3/−6; v textu pravidel ani "hranice únavy" nenajdu).
    Stejný druh chyby jako 0007. Rozhodnuto (uživatel): co nemá oporu v
    pravidlech a nic to nepoužívá, nemusí tam být — vyřazena z editoru
    (`entities.php`). **Na DB se musí ručně dropnout** přes phpMyAdmin
    (admin): `DROP TABLE stupne_unavy;` — jako migrace to nejde
    (`migrate.php` soubor s DROP odmítne a web účet ho ani nemá).
    Sesterské tabulky z téže migrace, `narocnost_akci_unava` a
    `odstraneni_unavy`, vypadají na stejný starý model (např. "Spánek
    −10 bodů únavy" proti h1634 "zmizí tolik bodů, kolik je odolnost") —
    zatím nezkontrolované proti pravidlům, nesahat dokud se neověří.
  - Tabulka výcviků (h1603, b10603): "základní pohyblivost postavy se
    zvýší o 1" — bonus k pohyblivosti z výcviku se nepočítá.

**Fáze 1b — Váha, kontejnery a nosiče — HOTOVO V KÓDU (2026-10-14, čeká na ruční DDL 0080/0081)**
Rozhodnutí uživatele: váha se počítá automaticky; zvířata a povozy mohou
mít vlastní náklad v kontejnerech; **do hry celý ceník (úplně vše)**;
přeplnění kontejneru se jen **hlásí** všem (hráč i PJ), nic nebrání.
Technickou volbu modelu udělal Claude: model "vedle", bez DROP (návrh a
důvody v `docs/navrh-kontejnery-a-nosice.md`).
- `0080_predmety_vybaveni_sloupce.sql` (DDL): `predmety.typ` +hodnota
  `vybaveni`, `kategorie_vybaveni`, `kapacita_mn`. `0081_kontejnery.sql`
  (DDL): `postava_kontejnery` (jeden řádek = jeden fyzický vak/truhla,
  `nosic` postava|zvire) a `kontejner_obsah`. `0082_cenik_vybaveni.sql`
  (DML, automaticky po DDL): **všech 217 položek "Nový ceník" (h2055)** —
  vaky, pochvy, nádoby, služby, svícení, sedla, oblečení (×3 velikosti A/B/C),
  stany, sítě, truhlice, deky, provazy, psací potřeby, nářadí, jídelní
  náčiní, nástroje pro řemesla, alkohol, potraviny. Cena v zl (1 zl = 10 st
  = 100 md), váha v mn; 21 kontejnerů má `kapacita_mn` (vaky, torna, soudky,
  sud, truhly, trakař). Zvláštnosti zdroje jsou v hlavičce migrace a
  NEOPRAVOVÁNY (soudek 10 l váží 200 mn, střední nákladní vak je levnější než
  malý, "lst" u zimních bot).
- `includes/vtt_kontejnery.php` + `hra/api/postava_kontejner.php`: přidat
  (přes katalog v deníku se vak/truhla stane kontejnerem), vložit, vyndat,
  odebrat (obsah zpět do inventáře), přejmenovat, nosič, "použít jako
  kontejner" u vaku ve volném inventáři. Přeplnění: server vrátí upozornění,
  klient po potvrzení pošle znovu.
- Váha: `dracak_vtt_postava_naklad_mn()` počítá volné věci + kontejnery
  (vlastní váha + obsah) + peníze; kontejner na zvířeti (`nosic = zvire`,
  jen pokud postava jede) jde do `naklad_zvire_mn` a kalkulace cesty ho přičte
  zvířeti (spolu s jezdcem). UI: karta "Kontejnery" v sekci Vybavení deníku,
  tisková verze vypisuje obsah s umístěním. Editor: typ `vybaveni`, nová pole.
- **Zbývá / neřeší:** lektvary nemají váhu (kniha ji neuvádí); vnořené
  kontejnery; povoz není trvalá entita (jeho náklad se zadává při cestě);
  ceníky h2058–h2061 (zbraně, zbroje) už v DB jsou; h2057 jezdecká zvířata
  jsou jen částečně (7 zvířat).

**Fáze 2 — Jízdní zvířata — HOTOVO V KÓDU (čeká na ruční DDL výše)**
- `0067_jizdni_zvirata.sql`: tabulka `jizdni_zvirata` se 7 řádky z
  TABULKY JEZDECKÝCH ZVÍŘAT (h1635); čísla ověřená proti nezávislé
  homebrew tabulce "Jezdečtí tvorové" (h2057), shodují se do jednoho.
  `čas na 10 mil` uložen v minutách (v pravidlech h:mm). Zobrazuje se i
  v editoru (číselník "Jízdní zvířata").
- `0068_postava_doprava.sql`: `postavy.doprava_typ` ENUM(chuze/zvire) +
  `doprava_zvire_id` FK, **bez vynuceného vlastnictví**. Konzistenci
  dvojice drží `hra/postava.php`, ne DB (typ se odvozuje z toho, jestli
  je vybrané platné zvíře). Pozn.: ENUM je vůči FK redundantní (NULL v
  FK = chůze); nechal jsem ho podle schváleného plánu, dá se později
  zahodit nebo využít pro další druh dopravy.
- `includes/vtt_pohyblivost.php`: `dracak_vtt_jizdni_zvirata()`,
  `dracak_vtt_postava_doprava()`.
- UI: výběr "Doprava" v `hra/postava.php` (ne přímo na sedadle v
  `svet.php` — sedadlo je odkaz `<a>`, select uvnitř by byl neplatný
  HTML a layout jsem neměl jak vizuálně ověřit); ve `svet.php` na kartě
  postavy řádek "Doprava". Obojí se zobrazí jen když sloupce na DB
  existují.
- **Tažný kůň — vyřešeno (uživatel potvrdil 2026-10-13):** na hřbetě
  unese 800 mn (jako klasický kůň, Bestiář h2516: "unese stejně jako
  klasický kůň, v tahu je však jeho nosnost o čtvrtinu vyšší, tj. 1 000
  mn"), v tahu 1000 mn. Uloženo ve sloupci `jizdni_zvirata.nosnost_tah_mn`
  (migrace 0075 DDL + 0076 DML; NULL = v tahu stejná jako na hřbetě).
  Pozor pro Fázi 3: zápřah má vlastní pravidlo — nosnost v zápřahu je
  čtyřnásobek nosnosti z Tabulky jezdeckých zvířat (klasický kůň 4 × 800 =
  3 200, jezdecký 4 × 750 = 3 000; Tabulka nosnosti spřežení, h1639–h1641).
  1000 mn tažného koně beru jako tu základní hodnotu "v tahu" (tj. 4 ×
  1000 = 4 000 v zápřahu = 3 200 × 1,25), ne jako konečnou nosnost
  zápřahu — moje čtení, kniha to doslova neříká; **uživatel potvrdil 2026-10-14**
  (základ 1000, v zápřahu 4000).
  Rozšíření na ostatní tvory z h2057 (~40 řádků: válečný kůň, vůl,
  velbloud, letci s řádky "na zemi", ceny…) čeká na rozhodnutí, jak
  značit letecké/pozemní varianty a co znamená sloupec "Ujede (číslo :
  10)" a poznámky `*`/`**`, které tabulka nevysvětluje.
- Zatím se z dopravy nic nepočítá (rychlost družiny, únava zvířete,
  váha jezdce v nákladu zvířete) — to je Fáze 4 / 1b.

**Fáze 3 — Povozy — HOTOVO V KÓDU (2026-10-13, čeká na ruční DDL 0079)**
- Dříve otevřená otázka "PPP typy vs. h2057 — sloučit?" se vyřešila
  zjištěním: **8 modelů** (žebřiňák, valník, krytý vůz, maringotka, malý/velký
  kočár, malý/velký dostavník) je i v základních pravidlech PJ (Tabulka ceny
  povozů a Tabulka řízení, h2366); PPP typy vůz/kočár/dostavník jsou jen
  jejich seskupení. Váhy se v PPP a v homebrew h2057 mírně liší — `povozy`
  drží obě rozmezí (`vaha_*` = PPP, `vaha_hb_*` = h2057), kalkulačka
  předvyplní homebrew (**rozhodnuto uživatelem 2026-10-14: homebrew má
  přednost, je upravený vůči realitě**) a PJ číslo přepíše. Únava cestujících u žebřiňáku/valníku
  (1 bod za 4 body zvířete) je jen v h2057, ostatní v PPP.
- `0079_povozy.sql` (DDL + INSERT IGNORE): `povozy` (kategorie, váhy,
  násobek nosnosti, únava cestujících, `klus` ne/jen_dobra_cesta/ano, Tabulka
  řízení, kapacita osob) a `povoz_nalozeni` (Tabulka naložení povozu: ≤10
  100 %, ≤12 90 %, ≤15 80 %, ≤20 65 %, ≤30 30 %, nad 30 nelze projet).
- `includes/vtt_cestovani.php`: `dracak_vtt_povoz_sprezeni()` (čtvrtina váhy
  proti nosnosti spřežení = nosnost zvířete × (1 + 0,5 × (počet − 1)), v
  tahu `nosnost_tah_mn`), `dracak_vtt_povoz_procento_nakladu()`,
  `dracak_vtt_povoz_rizeni()` (% úspěchu kočího klusem, −N % za každý bod
  náročnosti nad 10) a povoz v `dracak_vtt_cesta_vypocet()` (cestující na
  povoze, bezpečný náklad, rychlost spřežení, omezení tempa: vozy nejvýš
  krokem, klusem jen při náročnosti ≤10, maringotka nikdy).
- **Ověřeno proti knize:** Tabulka nosnosti spřežení přesně (klasický kůň
  3 200/12 800, pár 4 800/19 200, trojka 6 400/25 600, 2 páry 8 000/32 000,
  3 páry 11 200/44 800; jezdecký kůň 3 000/12 000 … 10 500/42 000); příklad
  krytého vozu 6 000 mn v pahorkatině (80 % z 12 000 = 9 600, celkem
  15 600); příklad řízení z PJ (krytý vůz, klus, náročnost 12 = 78 %).
- UI: karta "Povoz" v `hra/cesta.php` (typ, váha, násobek, náklad, tažné
  zvíře, spřežení), sloupec "Na povoze" u postav, souhrn povozu ve výsledku.
- **Neřeší:** nehody (hod kočího k% za započatou směnu je jen zobrazená
  pravděpodobnost), dovednost Kočí, otevřené kočáry (polovina váhy — PJ
  zadá váhu), vícenásobné povozy v jedné cestě, váha nákladu jako položky
  povozu (čeká na model kontejnerů, Fáze 1b).

**Fáze 4 — Jádro: hodinová simulace cestování — HOTOVO V KÓDU (v1, 2026-10-13)**
- Odblokováno fotkami tabulek (viz "Co je podložené"). Zdroj pravidel:
  PPP "Pohyb pod širým nebem" (h1634/h1635) + PJ "Náročnost cesty" (h2366).
- `includes/vtt_cestovani.php`: čistá simulace `dracak_vtt_cesta_simulace()`
  (souvislý čas; rychlost družiny = nejpomalejší, h2355; přepočet při každé
  změně stupně únavy někoho z pěších, protože únava ubírá bonus za
  sílu/obratnost a tím zpomaluje; úsek končí v dosažení cíle nebo těsně
  před zvoleným stupněm únavy, který by někdo (i zvíře) překročil) a
  `dracak_vtt_cesta_vypocet()` (sestaví pěší, jezdce + zvíře a doprovodná
  zvířata s nákladem; zvíře nese váhu jezdce + jeho náklad, pohyblivost po
  naložení stejnou tabulkou, jezdec dostane ⅓ bodů únavy zvířete).
  Únava za hodinu: procházka 0,5, chůze 1, spěch 3 (b10869). Konstanty
  povrchů/terénů/vlivů z PJ h2366 jsou jen nápověda ve formuláři.
- **Ověřeno proti knize** (viz skript v historii konverzace, výsledky
  zapsané tady): zjednodušené tabulky PPZ "Cestování na koních" (h1681)
  vychází přesně — jezdecký kůň 750/1500/2250 mn: spěch 8 h po cestě
  78/62/50 mil, "procházka 16 h" (= PPP chůze) 112/88/72; terény
  savana/les/hory = náročnost 15/20/40 dávají 52/39/20; pěšky (pohyblivost
  14 = bonus +1): spěch 7 h 49 mil, chůze 14 h 70 mil, hory 12. Kniha
  v PJ h2355 sama říká, že tabulky PPZ odpovídají pohyblivosti 14 a
  odolnosti 14. PPZ tabulky ignorují zpomalení únavou, simulace ne —
  proto spěch 7 h vyjde o něco méně než 49 mil.
- `hra/api/cesta_vypocet.php` (jen PJ/admin světa; volitelně `zapsat` =
  zapíše postavám přepočtenou únavu v celých bodech, `floor`) a
  `hra/cesta.php` (+ `assets/js/cesta.js`, `assets/css/cesta.css`):
  vzdálenost, povrch × terén × vlivy (předvyplněný střed rozsahu, číslo
  přepíše PJ), tempo (procházka/chůze/spěch), stupeň únavy, kde se úsek
  zastaví, výběr postav, váha jezdce (ručně — pravidla ji nemají jako
  jedno číslo, rasa má rozsah) a doprovodná zvířata. Odkazy: "Cesta" na
  `svet.php` a `druzina.php` (jen PJ).
- **Iniciativa (2026-10-14, na pokyn uživatele):** Tabulka bonusů a postihů
  k iniciativě (h1622, v DB `iniciativa_bonusy` z 0045 — od uživatele z fota)
  má řádky Únava −1, Vyčerpání −2, Úplné vyčerpání −3, Mírné naložení −1,
  Střední −3, Velké −5. U postavy se teď přičítají SAMY podle jejího stavu
  (`dracak_vtt_iniciativa_auto_bonusy()`, `hra/api/iniciativa_hod.php`),
  pokud PJ nevypnul `auto_unava` / `auto_naklad`; ruční zaškrtnutí těchto
  řádků se u postavy ignoruje (nepočítat dvakrát), v panelu na mapě jsou
  zamčené. Nestvůry zůstávají ruční. Vysílená postava (omdlévá) a náklad
  nad 4× nosnosti: jen upozornění, tabulka pro ně řádek nemá. Prověřeno
  hledáním "únav" a "naložen" ve všech třech knihách: jiný vliv únavy na
  hodnoty postavy v pravidlech není (nosnost se z únavy nemění — kniha o tom
  mlčí; zuřivost Šamana a kouzla Brána odpočinku/ignorování postihů tohle
  potvrzují jen jako "postihy z únavy a naložení" obecně).
- **Oprava mimochodem:** 3D kostky na mapě (`hra/mapa.php`) měly špatnou
  cestu k souborům (`assetPath` relativní → URL "…cz../assets/…" ve workeru),
  opraveno na absolutní cestu; ověřeno, že chyba z konzole zmizela.
- **Spánek (2026-10-13):** v deníku tlačítko "Prospat N h" (`dracak_vtt_spanek()`,
  akce `spanek` v `postava_stav.php`): h1634 b10872 — hodin = 8 − polovina
  bonusu za odolnost (nahoru; příklad knihy odolnost 21 → 5 h sedí), zmizí
  tolik bodů únavy, kolik je odolnost. Zaokrouhlení u záporného bonusu kniha
  neříká (beru nahoru); "poloviční spánek" a limit 2× denně se nehlídají.
- **Záměrně v1 NENÍ:** poklus/běh/sprint (počítají se na kola), polovina
  únavy koní při cvalu, odpočinek bez spánku, posun světového
  času (svět eviduje jen celé dny: `aktualni_den_offset`), povozy (Fáze 3),
  vliv únavy na rychlost zvířat (kniha neříká), vliv dovedností Nosič /
  Jízda na koni / Kočí.

**Fáze 5 — Administrace světa — HOTOVO V KÓDU (2026-10-13, čeká na ruční DDL 0077/0078)**
- `0077_svet_auto_cestovani.sql` (DDL, 3× ALTER `svet`): `auto_naklad`,
  `auto_pohyblivost`, `auto_unava`, **zapnuté ve výchozím stavu** (na rozdíl
  od 4 starších `auto_*` přepínačů, které zatím jen ukládají nastavení,
  tyhle engine opravdu čte). `0078_postava_pohyblivost_rucne.sql` (DDL):
  `postavy.pohyblivost_rucne` (okamžitá pohyblivost zadaná ručně).
- `dracak_vtt_svet_cestovani_nastaveni()` + `dracak_vtt_postava_pohyblivost()`
  (obálka nad `..._vypocet`) v `includes/vtt_pohyblivost.php`: vypnutý náklad
  = bez naložení; vypnutá únava = bez postihů (pohyblivost, ÚČ, OČ, INT/CHA
  v deníku i kalkulačka cesty); vypnutá pohyblivost = platí ruční hodnota
  (v kalkulačce chyba, když není zadaná). Bez sloupců na DB se bere všechno
  zapnuto.
- UI: `hra/svet_administrace.php` sekce "Pohyb, naložení a únava";
  `hra/denik.php` ruční pole pohyblivosti (akce `pohyblivost_rucne` v
  `hra/api/postava_stav.php`), upozornění u únavy a naložení.
- Ověřeno na MariaDB: všechny režimy (auto, bez únavy, bez nákladu, ruční
  pohyblivost) + degradovaný režim bez sloupců.

---

## Osobní deník postavy (první verze hotová, čeká na návrh od Claude Design)

Požadavek uživatele: "ultimátní detailní přehled postavy, hráči tam budou
trávit spoustu času". Podklady: **`docs/zadani-osobni-denik-design.md`**
(zadání/prompt pro Claude Design — obsah z pravidel h1690, stav dat,
scénáře, požadavky; rešerše online) a referenční snímky v
`docs/zadani-osobni-denik-img/`. Vzhled bude navrhovat Claude Design; do
té doby je v kódu první, funkčně úplná verze (vlastní návrh vycházející z
rešerše: stabilní vs. volatilní info, trvale viditelný stavový pás).

Soubory:
- **`hra/denik.php`** — hlavní obrazovka (stavový pás: životy, magenergie,
  únava, naložení, pohyblivost, OČ/ÚČ; sekce Přehled / Boj / Schopnosti a
  kouzla / Vybavení / Postava). Odkazuje se na něj ze sedadla u stolu i z
  karet postav ve `svet.php` a z `postavy_moje.php`. Po zápisech i
  pollingem (5 s) se překresluje jako fragment (`?fragment=1`).
  `assets/css/denik.css`, `assets/js/denik.js`.
- **`hra/api/postava_stav.php`** — rychlé zápisy (±životy, ±magy, ±únava,
  zkušenost [jen PJ], peníze, zbroj/štít/držená zbraň, texty, velikost);
  životy a magenergie se logují do `svet_udalosti` jako na mapě.
- **`hra/api/hod_postavy.php`** — hod na útok / střelbu / obranu z deníku:
  server sám spočítá základ (ÚČ/OČ) a opravy (méně než 1/3 životů, velké
  naložení −1, štít +1 k obraně) a hodí **1k6+** (kostka s opakováním po
  šestce, kap. Kostky b58 — `dracak_vtt_hod_k6_plus()`); rozdíl velikostí,
  dostřel a úkryt zadá hráč do "ruční opravy". Loguje se jako `kostka_hod`.
- Z deníku jde také **seslat kouzlo / vypít lektvar** (existující
  `hra/api/pouzij_predmet.php`, tedy včetně odečtu magenergie, efektů a
  pořadí tahů) s výběrem **cíle** (jiná postava světa / nestvůra na aktivní
  mapě); zraňující kouzlo "na sebe" žádá potvrzení. Kostky schopnosti: tlačítko
  Hodit (`kostka_hod.php`, jen ve světě).
- **Správa inventáře z deníku** (dřív mohl předmět dát jen PJ podle ID ve
  `svet.php`, odebrat nikdo): hledání v katalogu (`hra/api/katalog_hledat.php`)
  a přidání jedním klikem, ± kus, odebrat, "zapomenout" kouzlo — akce
  `inventar_*` v `postava_stav.php` (vlastník i PJ/admin; odebraná zbroj/
  štít/zbraň se zároveň přestane nosit/držet).
- **`hra/druzina.php`** — přehled všech postav světa v jedné tabulce (životy,
  magy, únava, naložení, pohyblivost po naložení, OČ, ÚČ držené zbraně,
  doprava, efekty) s překreslením po 5 s; dole **tempo družiny = nejnižší
  pohyblivost** (pravidla-pj h2355: "Družina se pohybuje rychlostí
  nejpomalejší postavy" — základ pro Fázi 4). Odkaz v hlavičce `svet.php`.
  **Jen pro PJ** pod tabulkou sekce „Pro PJ — co únava a naložení právě mění“
  (`dracak_vtt_stav_pro_pj()` v `includes/vtt_denik.php`): u každé postavy,
  kterou únava/naložení ovlivňuje, vypíše stupeň a důsledky (bonusy SIL/OBR,
  zákazy, iniciativa) a připomene dovednosti, jejichž obtížnost určuje PJ
  (Plavání — past +stupeň únavy +stupeň naložení; Skok o tyči — ne při velkém
  naložení; Tichý pohyb). Hráč tuhle sekci nevidí (ověřeno v prohlížeči).
  Zbývá totéž v panelu tokenu na mapě (viz plán „náhrada Owlbear Rodeo“).
- **`hra/denik_tisk.php`** — tisková A4 verze papírového deníku podle
  kolonek h1690 (předvyplněná, `?id=`) a prázdný formulář (bez `id`).
- **`includes/vtt_denik.php`** — výpočty podle pravidel (bonusy, bojeschopnost,
  ÚČ tváří v tvář i střelecký + rodová zbraň, OČ, postřeh, velikost, hranice
  naložení ×1–×4, zkušenost, peníze, schopnosti/kouzla/jazyky/efekty).
  Ověřeno na skutečné MariaDB proti příkladům z pravidel (Sindor h1690,
  Krwell/Kram h1610–h1612, Emanuel h1610, Fergo h1683): `Sindor` v deníku
  vychází přesně jako v knize (pohyblivost 10, 8/5/3, nosnost 330/660/990/
  1320, OČ 1, dýka ÚČ 1 / střelecký 2 / OZ −2 / dostřel 5/9/12).
- Migrace: `0070` (postavy: zkušenost, penize_zl/st/md, velikost, zbroj_id,
  stit_id, drzena_zbran_id, puvod, osobnost) a `0071` (predmety:
  sila_zbrane, delka_zbrane) jsou DDL = v `docs/sql-k-rucnimu-spusteni.sql`;
  DML `0072` (SZ ze textu popisu, 87 zbraní) a `0073` (kategorie
  střelná/vrhací podle h2059) proběhnou samy. **Deník funguje i před ručním
  SQL** (chybějící pole ukáže jako "čeká na migraci"), ověřeno na DB ve
  stavu migrace 0064.
- Peníze se počítají do nákladu (1 mince = 1 mn, h3).

Známé mezery: velikost z rasy jen pro 7 základních ras (+ klany přes
rodiče) — homebrew rasy mají v textu rozsah velikosti (proto
`postavy.velikost` ručně); rodová zbraň (+1 ÚČ) je v kódu jako konstanta
pro 7 základních ras (h1609) i 10 homebrew (řádek "rodová zbraň:" v jejich
blocích), ne v DB; OČ počítá jen tělovou
zbroj + štít +1 (jak se kombinují rukavice/přilbice/boty z homebrew
ceníku, pravidla v repu neříkají); vrhací varianta zbraně se páruje
jménem ("Dýka" -> "Dýka (vrhací)"); kouzla/schopnosti jsou jen ke čtení
(vybírají se z celého katalogu bez kontroly seznamu kouzel povolání — pravidla učení kouzel se neověřují); chybí
finty, přesvědčení, erb/portrét, kontejnery a nosiče nákladu (Fáze 1b),
historie změn postavy; deník cizí postavy ostatním hráčům zatím není
přístupný.

---

## Plán: náhrada Owlbear Rodeo (zadáno 2026-10-14; blok A rozpracován)

**Cíl uživatele:** VTT, které může v ostrém provozu nahradit Owlbear Rodeo —
správa tokenů, mapy, souboj, otáčení tokenů, volitelně pochod po mapě světa a
přechody mezi lokacemi. Cílem je začít testovat v živé hře, ne „dokonalost“.

**Hotovo — blok A, krok 1 (2026-10-14): kamera a obnova bez přenačtení.**
- `assets/js/mapa.js` + `assets/css/mapa.css` (dřív inline v `hra/mapa.php`);
  PHP jen předá data v `window.MAPA`. Skript je statický soubor s `?v=filemtime`.
- **Kamera:** zoom (kolečko, +/−/0, tlačítka vpravo dole, pinch) a posun
  (tah prázdného místa, pravé/prostřední tlačítko, mezerník, nástroj „ruka“,
  dva prsty) přes CSS `transform` na `#mapWrap`. Všechny nástroje počítají
  v souřadnicích mapy přes `kamera.naMapu()`. Pohled se pamatuje v
  `sessionStorage`; při otevření se zaměří na družinu.
- **Pointer Events** (myš, dotyk, pero jednotně): tah tokenu prstem, měřidlo,
  zeď, mlha, ping, pin fungují i na tabletu. Na malé obrazovce je lišta
  nástrojů dole.
- **Bez `location.reload()`:** `includes/vtt_mapa_stav.php` (stav mapy pro
  uživatele: tokeny, efekty, pořadí tahů, volné postavy) → `hra/api/mapa_stav.php`;
  klient po událostech (`token_pridan`, `kolo_nove`, `tah_zmena`…) načte stav a
  překreslí tokeny a panel iniciativy na místě. Inventář tokenu se načítá až při
  kliknutí (`hra/api/entita_inventar.php`, jen PJ a vlastník) — dřív byl v HTML
  všech tokenů (i cizích).
- **Souřadnice tokenu = střed** (dřív levý horní roh 40px čtverečku); migrace
  `0083` (UPDATE +20) je DML a proběhne sama. Token se přizpůsobí gridu (≈ 0,82
  sáhu), HP/efekty pod ním; `.face` (šipka směru) čeká na ruční SQL pro `smer`.
- Polling je adaptivní (1,5 s při aktivitě, 2,5–4 s v klidu, 10 s ve skryté
  záložce); mlha se obnovuje po pohybu tokenů a jinak každý pátý dotaz.
- Mimochodem: opraven stored XSS (jména postav/nestvůr se vkládala do
  `innerHTML` bez escapování) a nečitelná tlačítka v tmavých panelech.

**Hotovo — blok A, krok 2 (2026-10-14): vzhled tokenů** (v kódu hotové, zapne se
po ručním SQL z `docs/sql-k-rucnimu-spusteni.sql`; do té doby se ovládání schová
a mapa funguje jako dřív — ověřeno na kopii DB bez SQL).
- **Otočení** tokenu čelem k jedné ze 6 stran hexu (60°; čtverec 45°, bez gridu 15°):
  úchyt u vybraného tokenu, tlačítka v panelu, klávesy Q/E; šipka směru na okraji.
  Důvod: v rozšířeném soubojovém systému musí být každý tvor obrácen čelem k jedné
  ze šesti stran hexu (h1621/b10799). Hráč smí otáčet jen vlastní nezamčený token
  a jen když je na tahu (bez aktivního souboje, tj. bez hozené iniciativy na mapě,
  smí hráči chodit i otáčet tokeny volně — rozhodnutí uživatele).
- **Velikost** (½–4 sáhy), **barva** (11 barev), **zámek** polohy, **skrýt před
  hráči** (PJ), **přejmenování** (obecné tokeny a nestvůry), **obrázek** z
  knihovny světa (`svet_obrazky`, nahrání zmenšuje na 256 px, `hra/obrazek.php`
  hlídá přístup ke světu). Nový token dědí vzhled posledního tokenu téže postavy /
  téhož druhu nestvůry ve světě.
- **Vlastní tokeny** (typ `obecny`): NPC bez statistik, překážka, sud… — jen PJ.
- `hra/api/token_uprava.php` (oprávnění po polích, událost `token_zmena` s
  hodnotami „před“ → vrací ji „Krok zpět“), `token_pridat.php` (obecný token,
  dědění vzhledu), `token_presun.php` (zámek), `obrazek_nahrat.php`,
  `obrazky_seznam.php`.
- **Skrytý token neprosákne hráčům ani přes polling** (`hra/api/udalosti.php`
  filtruje pohyb, změny a životy skrytých tokenů).
- PJ vidí v panelu tokenu postavy sekci „Únava a naložení“ (jako na stránce družiny).
- **Ruční SQL (migrace 0084)**: sloupce `tokeny.smer, velikost_sahu, barva, zamceno,
  nazev, obrazek_id`, typ `obecny`, tabulky `svet_obrazky` a `mapa_kresby`,
  `svet_udalosti.typ` → VARCHAR(40) (další druhy událostí už DDL nevyžadují).
  **Provedeno na produkci (2026-10-14, kontrola 6/1/1/2)**; soubor migrace
  `0084_mapa_tokeny_vzhled_obrazky_kresby.sql` je v repu a `migrate.php` ho pod
  DML-only účtem projde (nový fallback pro `MODIFY ... VARCHAR(n)`).

**Hotovo — blok A, krok 3 (2026-10-14): výběr více tokenů, kontextové menu.**
- Výběr: klik, Shift/Ctrl+klik (přidat/odebrat), **rámeček** (Shift+tažení, nebo
  nástroj „Výběr rámečkem“, který funguje i prstem), Ctrl+A, Esc.
- **Společný tah**: tažení jednoho z vybraných posune celou skupinu
  (`hra/api/token_presun_davka.php`, jedna událost `tokeny_presun`, „Krok zpět“
  vrátí všechny najednou; pravidla pohybu sdílí `includes/vtt_tokeny.php` s
  jedním tokenem).
- **Kontextové menu** (pravé tlačítko / dlouhé stisknutí prstem): otevřít panel,
  otočit, zamknout, skrýt, do popředí/pozadí, kopírovat (`hra/api/token_duplikovat.php`),
  smazat; u prázdného místa ping, vybrat vše, celá mapa. Zkratky: Q/E, L, H,
  Ctrl+D, Delete, Esc, V.
- **Číslování nestvůr** (Goblin, Goblin 2, …) při pokládání i kopírování; na
  tokenu „G2“.
- Před ručním SQL zůstávají k dispozici jen kopírování a mazání; společný tah se
  posílá po jednom tokenu.

**Hotovo — blok A, krok 4 (2026-10-14): kreslení a měřidlo v hexech.**
- **Kreslení** (nástroj s tužkou; zapne se po ručním SQL — tabulka `mapa_kresby`):
  čára, šipka, obdélník, kruh, kužel (60°), text; barva, tloušťka, „jen pro PJ“
  (hráči skrytou kresbu nedostanou ani přes polling). Ostatním se kresba objeví
  přes polling (`kresba_pridana`/`kresba_smazana`); smazat jde jednotlivě pravým
  tlačítkem / dlouhým stisknutím, „moje“ nebo (PJ) všechny.
  `includes/vtt_kresby.php`, `hra/api/kresba_pridat.php`, `kresba_smazat.php`;
  kresby jsou i ve stavu mapy (`mapa_stav.php`).
- **Měřidlo na hexech** počítá počet hexů od středu buňky ke středu buňky (jeden hex =
  jeden sáh, h1621) a zvýrazní obě buňky; na čtverci a bez gridu zůstává přímá
  vzdálenost. Kruh, kužel a šipka při kreslení ukazují dosah stejným způsobem.
  Kruh/kužel je jen vizuální pomůcka — které hexy plošné kouzlo zasáhne, hra
  nepočítá.
- **Ukončit souboj** (`hra/api/souboj_konec.php`, tlačítko v panelu pořadí tahů, jen
  PJ): smaže `kolo_stav` a pořadí. Dřív souboj skončit nešel — po prvním hodu
  iniciativy by hráči zůstali navždy vázaní na pořadí tahů.
- Tokeny mají vrstvy nad kresbami a zdmi a pod mlhou (`z-index` 10–90).

**Blok A je hotový a zapnutý na produkci** (ruční SQL 0084 proběhlo). Blok B
(souboj s pravidly) je odložený na později, uživatel jde blok A testovat v ostré hře.

**Stav mapy před změnou (audit `hra/mapa.php` 2026-10-14, historické):** funguje grid (čtverec/hex), zdi
(blokují hráče), mlha války (per hráč), měřidlo, ping, piny s vnořenými mapami,
iniciativa a pořadí tahů, krok zpět, kostky 3D, HP/magenergie/efekty na tokenu,
PJ přepíná aktivní mapu. **Chybí / drhne:**
- mapa se zobrazí v původní velikosti, **žádný zoom ani posun**; pohyb tokenů
  je jen `mousedown` (myš), **žádný dotyk**;
- token = jen `postava` nebo `nestvura_instance`, na mapě jen dvoupísmenný
  „puck“; **žádné otočení, velikost, portrét, obecný token** (NPC bez statů,
  předmět, překážka), výběr více tokenů, zamknutí, vrstvy, pravé tlačítko,
  klávesové zkratky, kreslení;
- synchronizace = polling `api/udalosti.php` každých 1,5 s; při 10 typech
  událostí (`token_pridan`, `token_smazan`, `efekt_*`, `iniciativa_hozena`,
  `kolo_nove`, `tah_zmena`, `predmet_*`, `krok_zpet`) se **celá stránka
  přenačte** (`location.reload()`) — zmizí otevřené panely, vybraný token;
- `svet_udalosti.typ` je **ENUM** → každý nový druh události (otočení,
  kreslení…) by chtěl ruční ALTER. Řešení: jednou ručně změnit na VARCHAR,
  pak už nové typy další DDL nepotřebují;
- „Cestovat sem“ (`api/pin_cestovat.php`) jen přičte ručně zadané dny;
  s kalkulačkou cesty (`includes/vtt_cestovani.php`) není propojené a pin
  nepřenese družinu na cílovou mapu;
- token se hýbe libovolně — pohyblivost postavy (po naložení a únavě) se na
  mapě nepoužívá, i když ji hra umí spočítat.

**Úkoly (S ≈ hodiny, M ≈ půl dne, L ≈ den a víc):**

*A. Plynulá mapa (bez toho se nedá hrát)*
1. ✔ Zoom + posun + „přizpůsobit na obrazovku“, jednotné Pointer Events (myš i
   dotyk, pinch) [M]
2. ✔ Aktualizace bez `location.reload()` — přidání/smazání tokenu, efekty,
   iniciativa, kolo/tah překreslit na místě [M–L]
3. ✔ Token: otočení (směr), velikost, portrét, barva týmu, popisek [M]
   (velikost se volí ručně; podle `velikosti` z pravidel se nenastavuje — kolik
   hexů tvor zabírá, kniha neuvádí tabulkou)
4. Obecné tokeny (NPC bez statů, předmět, překážka, značka) [M]
5. ✔ Výběr více tokenů, hromadný pohyb, zamknout, vrstvy, duplikovat, Delete,
   kontextové menu, klávesové zkratky [M]
6. ✔ Kreslení na mapu (čára, šipka, tvar, text; sdílené/jen PJ) [M]
7. ✔ Měřidlo v hexech/sázích a šablony plošných kouzel (kruh, kužel) [S–M]
8. ✔ (v ručním SQL 0084) Jednorázově změnit `svet_udalosti.typ` ENUM → VARCHAR [S]

*B. Souboj s pravidly (tím se Owlbearu vyrovná i předčí)* — **rozjednáno: popis průběhu
souboje podle pravidel a návrh oken je v `docs/navrh-souboje-v1.md`, rozhodnutí
uživatele (hody tlačítkem + 3D kostka, skrytá iniciativa nestvůr, deník nestvůr,
hexy podle velikosti, iniciativa po třech a kolo ve dvou půlkách = HB) jsou tam
zapsaná. Krok 1 (zahájení, úmysly, iniciativa, půlky kola) a 2a (akce na hexové mapě)
jsou hotové a na produkci, stejně tak 2b (tvorové na více hexech, Běh, Sprint; ruční SQL 0086
proběhlo) a 2c (plánování cesty po bodech); krok 3 (útok, obrana, zranění, hod proti pasti) je hotový
v kódu i na produkci jako „tah v2, etapa 2“ (ruční SQL `souboj_vyzva` proběhlo); další kroky 4–5 viz „Navržené pořadí
stavby“ v návrhu.**
9. Dosah pohybu v tahu z pohyblivosti postavy (po naložení a únavě), spotřeba
   bodů pohybu, varování při překročení, zákaz sprintu při únavě [M–L]
10. ✔ (tah v2, etapa 2, na produkci) Útok z tokenu: cíl → ÚČ vs OČ → hod →
    zranění → životy [L]
11. ✔ (krok 1) Skupina nestvůr: jeden úmysl a jeden hod iniciativy za stejné
    nestvůry; tahy ale mají zatím každá zvlášť [M]
12. Čitelné stavy na tokenu: pruh životů, ikony efektů, únava/přetížení (PJ)
    [S]
13. ✔ PJ-info o únavě a naložení v panelu tokenu (už je na `druzina.php`) [S]

*C. Svět a cesta*
14. Pochod po mapě světa: měřítko mapy (km/px), PJ vyznačí trasu → výpočet z
    kalkulačky cesty (povrch, terén, tempo, zvíře/povoz) → čas + únava se
    zapíšou postavám [L]
15. Přechod mezi lokacemi: pin → „Vstoupit“ přenese tokeny družiny na vstupní
    bod cílové mapy a přepne aktivní mapu všem [M]
16. Knihovna map: duplikace, velké obrázky (automatické zmenšení, limit
    hostingu), pořadí scén [M]
17. Jedny „hodiny světa“ (den/hodina) propojené s cestou, odpočinkem a
    spánkem [M]

*D. Příprava na ostré hraní*
18. Ověřit celý tok pozvání: PJ vytvoří svět → pozve hráče → hráč založí
    postavu → vidí jen své [M]
19. Zálohy databáze i `uploads/` na WEDOSu + ověřený postup obnovy [S]
20. Chybový log čitelný pro admina (aby se chyby z ostré hry nemusely
    popisovat z paměti) [S]
21. Zátěž polling: adaptivní interval (rychle v boji, pomalu v klidu, stop při
    skryté záložce), spočítat počet dotazů/min pro 6 hráčů [S–M]
22. Rozvržení na tabletu/telefonu (panely, toolbar) [M]
23. Zkušební scénář „první ostrá hra“ (cca 12 kroků) a jeho projití na dvou
    zařízeních [S]

*E. Vidění, světlo a stíny (přidáno 2026-10-14; zatím jen seznam, nic nestaví)*
24. **Stíny za zdmi** — plný viditelnostní polygon z tokenu (zatemnění všeho, co
    zdi zakrývají). Dnes je jen „levné v1“: mlha války odhaluje kolem postavy
    buňky v okruhu 8 sáhů, pokud k nim vede volný paprsek přes zdi
    (`includes/vtt_mlha.php`, `dracak_vtt_los_blokovana_zdi` v `includes/vtt.php`).
    Plný polygon („v2“) je zmíněný jen v komentáři v `includes/vtt_mlha.php`;
    v tomhle plánu dosud chyběl. [L]
25. **Dveře** — zeď s přepínačem otevřeno/zavřeno (měnilo by `blokuje_pohyb`
    a `blokuje_vystrel`; zmíněno v otevřených otázkách
    `docs/vtt-datovy-model-navrh-v1.md`). [M]
26. **Zdroje světla** na mapě (pochodeň, lucerna, kouzlo, slunce) s dosahem. Kniha
    má **Tabulku světel** (h286/b3166: svíčka +1 … sluneční světlo +9), ze které
    se dají odvodit úrovně osvětlení. [L]
27. **Vidění ve tmě, které se pohybuje s postavou** (nápad uživatele): chová se
    jako mlha války, ale „bublina“ vidění jde s tokenem postavy, která ve tmě
    vidí — rasou nebo kouzlem. V pravidlech: trpasličí **infravidění** (v mlze
    jen desetinový dosah b5663, vyřazuje ho Dokonalá tma b3601) a lupičovo
    **noční vidění** (potřebuje aspoň trochu světla, v absolutní tmě nepomáhá,
    h524/b5163). Před stavbou je potřeba z knihy dohledat dosahy v sázích
    (nehádat). Navazuje na 24 a 26. [L]

**Doporučené pořadí:** nejdřív blok A (1–3, 8), pak krátký zkušební
večer, teprve potom B a C podle toho, co v praxi vadí nejvíc.

---

## Čeká na rozhodnutí / podklady od uživatele

- **Vliv únavy na nosnost:** v knize není (hledáno 2026-10-14), takže se
  nepočítá. Uživatel to vzal na vědomí; kdyby se něco našlo, doplnit.
- **Texty přepsané v `pravidla_texty`:** pravidla.php zobrazuje text z
  `content/*.html`, ale uživatelská úprava přes tužku (tabulka
  `pravidla_texty`, klíč = id bloku) má přednost. Odstavec b10870 (únava)
  jsem v souboru přepsal pod stejným id — pokud ho někdo na produkci
  upravil tužkou, uvidí se pořád ta úprava, ne nový text.

---

## Standing pravidla relevantní pro orientaci (plné znění v CLAUDE.md)

- Nejistota v herní mechanice se neřeší odhadem — nikdy. Žádá-li se
  "odhad, nebo přesně", odpověď je vždy "přesně", i když je to pomalejší.
- "Nenašel jsem to" se netvrdí po jednom hledání — ověřit přes
  `content/toc-*.html` a zkontrolovat křížové odkazy do navazujících
  kapitol, než se něco prohlásí za chybějící.
- Migrace jen CREATE/ALTER ADD/INSERT, nikdy DROP bez schválení; nová
  migrace = nový soubor; CREATE/ALTER vyžaduje ruční spuštění přes
  phpMyAdmin (DML-only web účet).
- "Homebrew" v komentářích = plnohodnotná součást pravidel, ne vedlejší
  obsah.
