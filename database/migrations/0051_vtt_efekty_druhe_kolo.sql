-- VTT efekty — druhé kolo propojení kouzlo_efekty/lektvar_efekty/
-- schopnost_efekty, navazující na 0042 (první kolo) a 0046 (sjednocení
-- Omráčení -> Ochromení). Stejná metoda jako 0042/0022-0032: každý
-- záznam ručně ověřen proti skutečnému textu `popis`/pravidel
-- (content/pravidla-hrac.html), ne jen pattern-match na klíčové slovo —
-- viz CLAUDE.md "Nejistota v herní mechanice se neřeší odhadem".
--
-- Dvě skupiny propojení:
--
-- A) Kouzla s dalšími případy generických stavů, co 0042 zavedla
--    (Ochromení/Strach/Spánek) — 0042 výslovně sama říkala, že jde jen
--    o první průchod. Nová propojení:
--      Ochromení <- "chlad", "žár" (oba Elemancer/Šaman, h1435 —
--        "chlad": "osoba postižená třesem není schopná jakékoliv akce
--        a útok na ní je útok na nehybnou postavu"; "žár": past "Odl-7-
--        omráčení/nic", stejná mechanika, jen opačná teplotní podmínka)
--      Strach <- "Táhni potvoro!" (Krotitel, h926 — "je na celé kolo
--        vyděšený z krotitele a bude se snažit co nejrychleji dostat
--        z jeho přítomnosti"), "Plamenný horror" (Elemancer/Šaman,
--        h1434 — rulebook rovnou píše "efekt: strach/nic")
--      Spánek <- "Uspi okolí" (Chodec, h218 — "se do 2 směn odeberou
--        spát či usnou na místě"), "Ukolébavka" (Bard, h728 — "umožní
--        cílům kouzla usnout i v jinak nevhodných podmínkách"; na rozdíl
--        od ostatních je dobrovolné/souhlasné, ale výsledný mechanický
--        stav — spánek, obtížnější probuzení — je stejný)
--
--    VYNECHÁNO ZÁMĚRNĚ z týhle skupiny (stejná třída vyloučení jako
--    0042): "Zastrašení" (kouzlo Mága, h-řada u Zastrašení) — graduovaný
--    víceúrovňový efekt dle o kolik byl hod překročen (stejně jako
--    "Hněv lesa", co 0042 už vylučovala); "Noční můra" — větví se 40%
--    šancí na dvě různé mechaniky (bezhlavý útok / sebezraňování),
--    žádná z nich není čistý binární Strach; "Vize smrti" (Bard) —
--    popisovaná reakce (odmítnutí zapojit se do boje) je jinačí než
--    definice Strach (útěk + ÚČ/OČ postih-bonus), nenutí k útěku;
--    "Ochrom bleskem" — navzdory jménu je to iluzorní poškození
--    (mechanika "stínové zranění"), ne ochromení; "Osm bran" bod 7
--    ("trvalé ochromení") a "Hromový hlas" ("mohou být ochromeny") —
--    viz skupina B/poznámka níže, mají jiný/nejasný mechanický rámec.
--
-- B) Schopnosti, které už v datech MAJÍ plně hotový, jednoznačný
--    mechanický efekt napojený přes zvlastni_schopnosti.past_id ->
--    pasti.efekt_uspech_id/efekt_neuspech_id (18 takových schopností,
--    viz 0026/0033) — ale efekt samotný dosud nebyl zrcadlený do
--    schopnost_efekty, takže VTT engine (dracak_vtt_polozka_efekty(),
--    includes/vtt_predmety.php) si ho nemůže přímo přečíst. past_id
--    řetězec zůstává beze změny, tohle je jen doplnění chybějícího
--    zrcadlení — přesně to, co už 0042 udělala pro "Zastrašování" /
--    "Zastrašení (Válečník)" / "Děsivá přítomnost" (ty tři proto níž
--    NEJSOU znovu, napojily by se podruhé, navíc na jiný — užší —
--    efekt, než jaký zvolila 0042 záměrně pro rychlou volbu):
--      Změna v berserkra -> Berserk (nekontrolovaný vztek)  [h134]
--      Sražení a odhození -> Sražen na zem (bojovník)        [h144]
--      Odkopnutí          -> Odkopnut                        [h145]
--      Drapnutí           -> Chycen (drapnutí)                [h148/h173]
--      Líčení loveckých pastí -> Chycen v lovecké pasti       [h224]
--      Snímání kletby     -> Prokletí sejmuto (chodec)        [h228]
--      Drakomluva (Mág)   -> Drak splní žádost/rozkaz         [h466]
--      Zabití (Sicco)     -> Zabit ranou ze zálohy (Sicco)    [h639]
--      Rychlé tasení (Erythen) -> Zbraň vytasena násilím (Erythen) [h1114]
--      Osm bran (Erythen) -> Následek Brány (Osm bran)        [h1098]
--      Lichocení (Tulák)  -> Polichocen (Tulák)               [h698]
--
--    VYNECHÁNO ZÁMĚRNĚ z týhle skupiny, s důvodem (zbylých 7 z 18
--    schopností s past_id):
--      - "Zastrašení (Válečník)", "Děsivá přítomnost" — už napojeny
--        na generický efekt Strach (0042), druhé napojení na úzký
--        "Zastrašen (útěk)"/"Vyděšen přítomností trollobijce" by jen
--        zdvojilo stejný koncept u jedné schopnosti.
--      - "Podrobování (rozšíření)" -> "Podrobení odhaleno",
--        "Ovládnutí" -> "Ovládnutí odhaleno", "Šifrování" -> "Šifra
--        rozluštěna" — všechny tři mají cil='informace': jde o
--        informaci pro sesilatele (prozrazeno/rozluštěno), ne o stav
--        aplikovaný na cíl, který by měl engine "spustit" (žádný hod
--        kostkou, žádné životy, žádné trvání) — mimo smysl
--        kouzlo/schopnost_efekty dle CLAUDE.md.
--      - "Klíč" (Lupič), "Určení hodnoty pokladů" (Lupič) — jejich
--        past_id ukazuje na pasti řádek s efekt_uspech_id I
--        efekt_neuspech_id NULL/NULL, žádný efekt k napojení neexistuje.
--      - "Trollobijecký útok" (id 51) a "Líčení pastí" (pyrofor, id 80)
--        — řeší už 0033 (schopnost_pasti M:N), ale pořád platí původní
--        důvod z 0042 (Trollobijecký útok má 3 RŮZNÉ efekty dle typu
--        použité zbraně, zvolené až při použití — schopnost_efekty
--        nemá jak zachytit "který z nich", bez rizika zavádějícího
--        napojení na jen jeden z nich).
--
-- Vynechané/nejasné případy mimo výše uvedené (viz finální report):
-- "Duševní pouta" (+ 16. úroveň) a "Černý provaz" (Warlock) mají
-- podobný koncept "spoutání", ale KAŽDÉ jinou konkrétní mechaniku
-- (dvojnásobek akcí vs. nemožnost útoku, různé postihy k obraně) —
-- násilné sjednocení do jednoho efektu by bylo nepřesné; založení 2-3
-- nových úzkých efektů najednou bez konzultace je přesně to, před čím
-- varuje CLAUDE.md ("špatně navržený efekt je hůř opravitelný než
-- žádný") — ponecháno jako TODO pro budoucí samostatnou dávku.
--
-- Čisté INSERT IGNORE (DML) — nasadí se samo přes migrate.php.

-- ---------------------------------------------------------------------
-- A) Kouzla -> existující generické efekty (Ochromení/Strach/Spánek)
-- ---------------------------------------------------------------------

-- Ochromení <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Ochromení')
FROM kouzla k
WHERE k.nazev IN ('chlad', 'žár');

-- Strach <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Strach')
FROM kouzla k
WHERE k.nazev IN ('Táhni potvoro!', 'Plamenný horror');

-- Spánek <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Spánek')
FROM kouzla k
WHERE k.nazev IN ('Uspi okolí', 'Ukolébavka');

-- ---------------------------------------------------------------------
-- B) Schopnosti -> jejich vlastní, už existující bespoke efekty
--    (zrcadlení past_id -> pasti -> efekt_uspech_id/efekt_neuspech_id
--    řetězce do schopnost_efekty, stejný princip jako u kouzel
--    "Usni"/"Kouzlo spánku"/"Zadrž osobu" v baseline importu)
-- ---------------------------------------------------------------------

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Berserk (nekontrolovaný vztek)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Změna v berserkra';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Sražen na zem (bojovník)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Sražení a odhození';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Odkopnut')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Odkopnutí';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Chycen (drapnutí)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Drapnutí';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Chycen v lovecké pasti')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Líčení loveckých pastí';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Prokletí sejmuto (chodec)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Snímání kletby';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Drak splní žádost/rozkaz')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Drakomluva' AND z.druh = 'schopnost';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Zabit ranou ze zálohy (Sicco)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Zabití';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Zbraň vytasena násilím (Erythen)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Rychlé tasení';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Následek Brány (Osm bran)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Osm bran';

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Polichocen (Tulák)')
FROM zvlastni_schopnosti z WHERE z.nazev = 'Lichocení';
