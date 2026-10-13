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
- Tvorba podle pravidel — atributy jako "stupeň" (h104, ne bonus),
  nosnost/pohyblivost **zatím jen jako zobrazovací pole** (viz sekce
  "Rozpracováno" níže — tohle se teď řeší).
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

---

## Rozpracováno: cestování, pohyblivost, únava, naložení

**Stav: čistě výzkumná fáze, v kódu zatím NIC z tohohle není.** Tohle je
aktuálně otevřený úkol a nejspíš další věc, na které se bude pokračovat.

### Co je podložené přímo v `content/pravidla-hrac.html` (ne odhad)

- **Vzorec finální Pohyblivosti** (h1634): `rasový základ + bonus za OBR +
  2× bonus za SÍLU` (bonusy ze stávající `opravy_za_atribut`). Naložení
  (násobek nosnosti) snižuje na ¾/½/¼.
- **TABULKA RYCHLOSTI PŘI CHŮZI** a **PŘI BĚHU** — plná čísla, dodal
  uživatel foto (nebyly v HTML přepisu, jen slovní rozsah). Jednotka:
  body pohybu za hodinu (chůze) / za kolo (běh), podle bonusu za
  pohyblivost a tempa (procházka/chůze/spěch; poklus/běh/sprint).
- **TABULKA ÚNAVY** — plná čísla, dodal uživatel foto. 4 stupně
  (unavená/vyčerpaná/úplně vyčerpaná/vysílená), přesné postihy k
  síle/obratnosti/inteligenci/charismě u každého stupně.
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
- Tabulka rychlosti LODÍ a veslování — potvrzeno nedochovalo se (jiná
  kapitola, netýká se pozemního cestování).
- Nebyl ověřen zbytek kapitoly Dovednosti na další propojení (uživatel
  se ptal, jestli jsou zohledněné VŠECHNY dovednosti — zatím prošité jen
  tyhle 4 nejrelevantnější, ne celá kapitola řádek po řádku).

### Zjištěná chyba ve stávajících datech (čeká na opravu)
`rasy.pohyblivost_zaklad` (migrace 0007) je pravděpodobně špatně —
Člověk/Elf/Barbar mají shodně 30, ale ověřený příklad v textu počítá s
Barbarem jako se základem **12** (stejný vzorec chyby jako `nosnost_podle_sily`,
co opravila migrace 0055 — neověřeno proti textu při zavedení). Potřeba
dohledat/ověřit hodnoty pro všechny rasy před opravou.

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

### Další krok
Zatím nebyl odsouhlasený konkrétní implementační plán (na rozdíl od pinů/
poznámek, kde fáze byly rozepsané a schválené předem) — další krok je
nejspíš sepsat fázový plán stejným způsobem, jakým se postupovalo u pinů,
a nechat ho schválit, než se začne stavět kód.

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
