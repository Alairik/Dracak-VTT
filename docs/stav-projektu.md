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
částečně hotová, Fáze 3 (povozy), 4 (kalkulačka cesty) a 5 (přepínače)
jsou v kódu, zbývá dokončení 1b (kontejnery) — čeká na rozhodnutí
uživatele** — viz níž. Stav předchozí
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
kterém jsou všechny dosud neprovedené DDL příkazy v pořadí; uživatel ho
dostává jako text ke zkopírování (viz pravidlo v `CLAUDE.md`). **Stav:
čeká blok pro `0074` (tabulky `rychlost_chuze`, `rychlost_behu`), `0075`
(`jizdni_zvirata.nosnost_tah_mn`), `0077` (3 přepínače `svet.auto_*`),
`0078` (`postavy.pohyblivost_rucne`) a `0079` (tabulky `povozy`,
`povoz_nalozeni`)** — ověřeno na kopii produkčního stavu
(dvakrát za sebou, pak `migrate.php` pod DML-only účtem: 0074–0076 doběhly).
Do spuštění deploy v posledním kroku skončí HTTP 500 (FTP část proběhne,
kód je nasazený a bez tabulek funguje v degradovaném režimu). Předchozí
blok (`0065`–`0071`) je na produkci hotový. `DROP TABLE stupne_unavy;` (stará
nepoužívaná tabulka z 0008) uživatel zatím nechtěl/neprovedl — jako
migrace jít nemůže (`migrate.php` odmítne DROP).

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

**Stav: Fáze 1, 2, 4 a 5 jsou v kódu (DDL k nim je provedené až na
0074/0075/0077/0078/0079, viz "Čeká na ruční spuštění"), Fáze 1b
(kontejnery) čekají na rozhodnutí.** Tohle je aktuálně otevřený úkol a nejspíš další věc, na
které se bude pokračovat.

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

Fáze 1, 2, 3, 4 a 5 jsou v kódu (viz níž); zbývá Fáze 1b (kontejnery).
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
  zápřahu — to je moje čtení, kniha to doslova neříká; k potvrzení.
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
  předvyplní PPP a PJ číslo přepíše. Únava cestujících u žebřiňáku/valníku
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

## Čeká na rozhodnutí / podklady od uživatele

- **Model kontejnerů a nosičů (Fáze 1b) — rozhodnutí A/B:** podrobně a
  srozumitelně v `docs/navrh-kontejnery-a-nosice.md`. Stručně: A = zrušit
  unikátní index `postava_predmet` (DROP INDEX, schvaluje uživatel) a
  upravit stávající inventář; B (doporučeno) = nové tabulky vedle,
  nic se neruší.
- Rozsah importu ceníku h2055 (doporučeno: nejdřív jen kontejnery) a
  kapacita kontejneru jako tvrdý limit s přepsáním PJ (doporučeno).
- Povozy: váhy vozů se v PPP a v homebrew h2057 liší (obě rozmezí jsou v
  DB; kalkulačka předvyplní PPP) — potvrdit, které má být výchozí.
- **`DROP TABLE` tří mrtvých tabulek z migrace 0008:** `stupne_unavy`,
  `narocnost_akci_unava`, `odstraneni_unavy`. Všechny jsou jiný model
  únavy než v pravidlech (hranice 10 + odolnost, "spánek −10 bodů",
  "boj tři kola = 2 body"…; pravidla: h1634 — vytrvalost = odolnost,
  1 směna boje = 1 bod, spánek odstraní tolik bodů, kolik je odolnost)
  a nic je nepoužívá; z editoru jsou pryč. DROP smí jen schválit
  uživatel, pak se přidá do `docs/sql-k-rucnimu-spusteni.sql`.
- **Vliv únavy na nosnost:** únava snižuje bonus za sílu, ale tabulka
  nosnosti zná jen bonus −5…+5 a kniha o vlivu na nosnost mlčí — nosnost
  se proto z únavy nepočítá (viz "Co je podložené"). Potvrdit/upřesnit.
- Tažný kůň: 1000 mn beru jako základ "v tahu" (4× v zápřahu = 4000);
  potvrdit čtení (viz Fáze 2).
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
