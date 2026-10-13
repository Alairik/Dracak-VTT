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
částečně hotová, další je Fáze 3 (povozy)** — viz níž. Stav předchozí
konverzace je zapsaný přesně proto, aby ses nemusel/a ptát na věci, co
už byly rozhodnuté (viz "Architektonická rozhodnutí" a celá sekce
"Rozpracováno" níže — všechno v ní je buď přímo citovaný text pravidel,
nebo odsouhlasené uživatelem, ne můj odhad).

### Čeká na ruční spuštění přes phpMyAdmin (admin účet)

`migrate.php` běží pod DML-only účtem, takže DDL nikdy neprovede a **na
prvním takovém souboru deploy zastaví** (HTTP 500 v posledním kroku
`deploy.yml`; FTP část proběhne normálně, kód je nasazený). Všechno za
ním čeká, dokud se to neprovede ručně. Pořadí:

1. `0065_postava_unava.sql` — `ALTER TABLE postavy ADD COLUMN unava`.
   (Aktuálně zastavuje deploy; 0064 už proběhla sama.)
2. `0067_jizdni_zvirata.sql` — **jen příkaz `CREATE TABLE`**; `INSERT
   IGNORE` pod ním doběhne sám při dalším deployi.
3. `0068_postava_doprava.sql` — `ALTER TABLE postavy ADD ...` (až po
   bodu 2, je tam cizí klíč).
4. `DROP TABLE stupne_unavy;` — bez souboru (migrace s DROP se nespustí);
   tabulka je z editoru pryč a nic ji nepoužívá, viz Fáze 1.

`0066_nulovat_nedolozene_vahy_surovin.sql` je čisté UPDATE a proběhne
automaticky, jakmile je odblokovaný bod 1. Kód je napsaný tak, že
funguje i před ručními kroky (chybějící sloupce `unava`/`doprava_*` se
berou jako "není"), jen se nic z toho nedá ukládat.

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
- Iniciativa + pořadí tahů (`includes/vtt_iniciativa.php`), vynucení
  pořadí (`dracak_vtt_je_na_tahu`).
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

**Stav: Fáze 1 hotová v kódu (čeká na ruční ALTER 0065), Fáze 2–5 zatím
v kódu nejsou.** Tohle je aktuálně otevřený úkol a nejspíš další věc, na
které se bude pokračovat.

### Co je podložené přímo v `content/pravidla-hrac.html` (ne odhad)

- **Vzorec finální Pohyblivosti** (h1634): `rasový základ + bonus za OBR +
  2× bonus za SÍLU` (bonusy ze stávající `opravy_za_atribut`). Naložení
  (násobek nosnosti) snižuje na ¾/½/¼.
- **TABULKA RYCHLOSTI PŘI CHŮZI** a **PŘI BĚHU** — jednotka: body pohybu
  za hodinu (chůze) / za kolo (běh), podle bonusu za pohyblivost a tempa
  (procházka/chůze/spěch; poklus/běh/sprint). **POZOR: plná čísla v repu
  NEJSOU.** Uživatel je dodal foto v dřívější konverzaci, ale do
  `content/` se nikdy nepřepsala — h1634 b10868 pořád říká, že se
  nedochovala, a b10867 uvádí jen rozsahy (bonus −5 až +5 →
  12–42/20–70/28–98 bodů za hodinu a 16–56/24–84/48–138 bodů za kolo).
  Před Fází 4 je potřeba foto dodat znovu a přepsat do
  `content/pravidla-hrac.html` (stejně jako Tabulka lodí v a0915fc).
- **TABULKA ÚNAVY** — stejný případ: 4 stupně (unavená/vyčerpaná/úplně
  vyčerpaná/vysílená) s prahy 1×/2×/3×/4× vytrvalosti (= Odolnost) jsou
  v h1634 b10870, ale **plná tabulka postihů k síle/obratnosti/
  inteligenci/charismě u každého stupně v repu není** (jen slovní
  shrnutí). Fáze 1 proto počítá stupeň a ukazuje doslovný text shrnutí,
  žádné strukturované postihy.
- **Náročnost terénu — NALEZENO, ne chybí**: sloupec "náročnost" v
  TABULCE NALOŽENÍ POVOZU (h1641) je explicitně **ve stejných
  jednotkách** ("v bodech pohybu, viz 'Rychlost a body pohybu' výše") —
  tohle JE ta hledaná tabulka, jen schovaná pod jinou hlavičkou:
  rovina/velmi dobrá cesta=10, mírné zvlnění=12, pahorkatina=15,
  vrchovina=20, hory=30, velehory=>30. Potvrzeno zpětně i příkladem
  "hustý les = 20 bodů" z h1634 (sedí na vrchovinu).
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

Fáze 1 a 2 jsou v kódu (viz níž), Fáze 3–5 ne. Pokračovat Fází 3, v
pořadí — každá staví na předchozí.

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
  - **Strukturované postihy za únavu chybí.** Plná Tabulka únavy (foto
    od uživatele) v repu není — v `content/` je jen slovní shrnutí
    h1634 b10870, které cíl postihu uvádí výslovně jen u "unavená"
    (síla/obratnost) a int/chr jen u "úplně vyčerpaná". Helper proto
    vrací stupeň + doslovný text shrnutí, žádná čísla k atributům.
    Než je engine bude aplikovat automaticky, je potřeba tabulku
    přepsat do `content/pravidla-hrac.html` (foto znovu).
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

**Fáze 1b — Váha, kontejnery a nosiče (rozhodnuto uživatelem, ČÁSTEČNĚ HOTOVO)**
Rozhodnutí: váha se musí počítat automaticky (hotovo pro plochý
inventář postavy); jednotky sjednotit na mn (seed už je mn — viz výš);
**zvířata a povozy mohou mít vlastní náklad, pokud na nich existují
kontejnery** (vaky, truhly apod.).
- Co dává text pravidel (vše ověřeno v `content/pravidla-hrac.html`):
  - Hmotnost = mince `mn`, ≈ 50 g, mince ze všech kovů váží stejně,
    **peníze se tedy váží (1 mince = 1 mn)** (h3).
  - Ceník h2055 ("Nový ceník", homebrew): sloupce název | kapacita/
    poznámka | cena | **váha**; kontejnery mají **kapacitu v mn a
    vlastní váhu** — torna kožená 800/30 mn, vaky (malý 140/9 … nákladní
    velký 2000/80), sedlové vaky (250/12, 500/24, 750/36), soudky
    (5 l … 50 l, sud 225 l), truhly (malá/střední/velká dřevěná i
    železná: kapacita 200/500/1500 mn, váha 60–500 mn), trakař 2000/750,
    toulec, pochvy… Dále oblečení, stany, deky, provazy, jídlo atd. —
    **nic z toho není v `predmety`** (DB má jen zbraně, zbroje,
    suroviny, artefakty).
  - Nosnost zvířat: Tabulka jezdeckých zvířat (h1635). **Do nákladu
    na koních se započítává i váha jezdce** (h1681 b11168).
  - Povozy: do váhy nákladu z hlediska zvířete se počítá i váha
    povozu a posádky (h1640); "procento nákladu" podle terénu (h1641).
- Stav dat: `predmety.vaha` je NULL u části řádků (např. Pavéza, Voda,
  Ocet…), **lektvary váhu nemají vůbec** (jen občas v textu `popis`,
  např. "Váha: 5mn"), ceníkové vybavení v DB není. Hodnoty `vaha` ve
  migracích 0035/0036 (42 surovin pro Chodce a lektvary, 0,01–1,0) neměly
  žádný zdroj v pravidlech (ověřeno hledáním názvů ve všech třech
  souborech) — **migrace 0066 je vrací na NULL**, položky se v součtu
  nákladu hlásí jako "bez uvedené váhy". Doplnit, až bude váha v knize.
- Zbývá: (1) import ceníku h2055 do DB — `predmety.typ` ENUM nemá
  hodnotu pro běžné vybavení a chybí sloupec kapacity (DDL, ruční
  spuštění); (2) váha lektvarů; (3) model kontejnerů — předmět uložený
  "v" kontejneru (self-FK v inventáři), nosič = postava / zvíře /
  povoz, součet váhy po stromu; (4) peníze na postavě; (5) váha
  jezdce do nákladu zvířete. Dělat po částech, až po odsouhlasení
  návrhu modelu.

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
- Otevřené: **tažný kůň** má v obou tabulkách nosnost 800 mn, ale slovní
  popis nad tabulkou říká "o čtvrtinu vyšší než klasický, 1000 mn v
  zápřahu" — uloženo to, co říkají tabulky, ověřit proti knize.
  Rozšíření na ostatní tvory z h2057 (~40 řádků: válečný kůň, vůl,
  velbloud, letci s řádky "na zemi", ceny…) čeká na rozhodnutí, jak
  značit letecké/pozemní varianty a co znamená sloupec "Ujede (číslo :
  10)" a poznámky `*`/`**`, které tabulka nevysvětluje.
- Zatím se z dopravy nic nepočítá (rychlost družiny, únava zvířete,
  váha jezdce v nákladu zvířete) — to je Fáze 4 / 1b.

**Fáze 3 — Povozy**
- Žádná trvalá entita "svět vlastní povoz" — typ vozu + zápřah se volí
  ad-hoc přímo při cestování, stejně jednoduché jako dnešní "zadej dny".
- **Zdroje už v repu jsou** (zjištěno při Fázi 2): PPP h1639–h1641 (typy,
  nosnost spřežení, naložení podle terénu) + homebrew tabulka POVOZY v
  h2057: žebřiňák, valník, krytý vůz, maringotka, malý/velký kočár,
  malý/velký dostavník — cena ve stříbru, váha vozu (mn), nosnost
  (násobek váhy vozu), únava řidiče (1 bod za N bodů zvířete), kapacita
  osob. Pozor: typy v PPP (vůz/kočár/dostavník) a v h2057 (8 modelů) se
  nekryjí 1:1 — před stavbou číselníku rozhodnout, ze které tabulky se
  bere, nebo ji slít.

**Fáze 4 — Jádro: hodinová simulace cestování**
- U "Cestovat sem" PJ zadá vzdálenost (míle) a vybere terén (6
  kategorií z Tabulky náročnosti terénu/naložení povozu).
- Simulace po hodinách: tempo družiny = nejpomalejší člen (pravidlo
  h2355), body/hodinu podle terénu a tempa, akumuluje únavu, zastaví se
  při nutnosti odpočinku NEBO dojetí cíle.
- V1 = jedna etapa (do prvního odpočinku), ne plně automatický
  multi-day běh — PJ dostane výsledek a rozhodne, co dál. Žádná "kolik
  hodin denně se chodí" konstanta (ta v textu není) — proto hodinová
  smyčka, ne denní paušál.

**Fáze 5 — Administrace světa**
- `auto_naklad`/`auto_pohyblivost`/`auto_unava` přepínače (stejný vzor
  jako 4 stávající v `svet_administrace.php`) + možnost hráč/PJ zadá
  pohyblivost ručně místo výpočtu (zjednodušení).

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
