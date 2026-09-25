# Zadání: redesign UI editoru pravidel (Dračák VTT)

Status: návrh zadání k předání designérovi / jiné Claude session zaměřené na
návrh rozhraní. **Tento dokument neobsahuje řešení** — jen požadavky, reálný
stav dat a kódu, a otevřené otázky, které má řešitel rozhodnout. Autor
zadání do návrhu samotného UI záměrně nezasahuje.

Sepsáno na základě prostudování repa `alairik/Dracak-VTT` (stav k
2026-09-25): `includes/entities.php`, `editor.php`, `admin.php`,
`includes/auth.php`, `includes/entity_crud.php`, `assets/css/style.css`,
`database/drd-db-schema-v1.sql`, `database/migrations/000{2,3,4,5,6,7,8}*.sql`,
`docs/db-model-navrh-v1.md`.

---

## 1. Cíl

Appka je editor pravidlové databáze pro Dračí hlídku (DRH, nad DrD). Dnes
existuje jen jeden obecný pohled "seznam + formulář" použitý shodně na
všechny entity — od 31řádkových Ras po 680řádková Kouzla. To při reálném
objemu dat prakticky nejde používat: dlouhé nescrollovatelné seznamy, žádné
hledání, žádné filtrování, a poloviny datového modelu (M:N vztahy) se
vůbec nedá dotknout jinak než přes phpMyAdmin.

Úkolem řešitele je navrhnout rozhraní, které:
- zůstane použitelné pro netechnického/středně technického majitele appky
  na běžném sdíleném PHP hostingu bez build kroku,
- umožní najít a upravit konkrétní záznam v tabulkách o stovkách řádků
  v řádu vteřin, ne scrollováním,
- doplní editaci M:N vztahů, která dnes chybí úplně,
- zachová všechno, co dnešní UI umí (viz sekce 5.1).

Návrh **vizuálního stylu** (barvy, typografie) může řešitel navrhnout nově
nebo navázat na současné tmavé schéma v `assets/css/style.css` — to je
na jeho uvážení, není to omezení tohoto zadání.

---

## 2. Kdo appku používá a jak

Tři role, uložené ve sloupci `ucty.role` (enum `admin`/`pj`/`hrac`),
vynucené v `includes/auth.php` a `editor.php`, ne jen v UI:

| Role | Vidí skupinu `obsah` (9 tabulek) | Vidí skupinu `bestiar` (bestiář, PJ poznámky) | Vidí skupinu `ciselniky` (14 tabulek) | Co smí editovat |
|---|---|---|---|---|
| **admin** | ano | ano | ano | úplně vše, + správa účtů (`admin.php`) |
| **pj** | ano | ano | ano | úplně vše (identické právo editace jako admin, jen bez správy účtů) |
| **hrac** | ano | **ne** (skupina se ve vykreslení sidebaru i v `editor.php` úplně vyfiltruje, `$visibleEntities`) | **ne** | jen ty tabulky ze skupiny `obsah`, které mu admin výslovně zaškrtl v `admin.php` (tabulka `ucet_opravneni`, per-uživatel, per-tabulka) |

Důležité vlastnosti dnešního modelu, se kterými musí redesign počítat:

- **Vynucení je table-level, ne row-level.** `dracak_can_edit()` v
  `includes/auth.php` řekne ano/ne pro *celou tabulku* danému uživateli.
  Když hráč smí editovat "Kouzla", smí mazat i cizí kouzla — v žádné
  tabulce obsahu není sloupec vlastníka/autora (`created_by` apod.) a
  `dracak_entity_save()`/`dracak_entity_delete()` žádnou takovou kontrolu
  neprovádí. (Komentář v `editor.php:43` zmiňuje "jen svoje vlastní
  záznamy", ale reálně implementováno není — buď jde o zastaralý komentář,
  nebo o budoucí požadavek. **Ověřit se zadavatelem**, jestli má redesign
  s per-řádkovým vlastnictvím počítat, nebo je table-level oprávnění
  dostačující i nadále.)
- Hráči lze povolit editaci **jen** tabulek ze skupiny `obsah`
  (`admin.php` staticky filtruje `$editableTables` na `group === 'obsah'`)
  — bestiář, PJ poznámky a číselníky nejdou hráči zpřístupnit ani omylem,
  to je záměrné bezpečnostní omezení a redesign ho musí zachovat.
  Skupina `obsah` má 9 tabulek: kouzla, zvláštní schopnosti a dovednosti,
  vybavení, lektvary, finty, povolání, rasy, efekty, pasti.
- Bestiář a PJ poznámky (skupina `bestiar`) jsou **vždy** skryté hráči
  (`hidden_from_players`), nezávisle na jakémkoli nastavení oprávnění.
- Číselníky (skupina `ciselniky`, 14 tabulek referenčních dat jako opravy
  za atribut, nosnost podle síly, stupně únavy, obory magie...) vidí a
  edituje jen admin/PJ, hráč o nich neví vůbec.
- V administraci účtů (`admin.php`) admin zakládá účty, mění role a
  spravuje per-hráč checklist povolených tabulek — to zůstává mimo scope
  tohoto redesignu (samostatná stránka), ale redesign editoru by měl
  zůstat vizuálně/navigačně konzistentní s tím, co se z admin.php dělá.

---

## 3. Aktuální stav UI — co existuje a kde to bolí

Vykresluje `editor.php` (jediný soubor, ~210 řádků), styl `assets/css/style.css`.

**Layout:** postranní panel (240px) se třemi skupinami podle `group` v
`entities.php` (Obsah pravidel / Bestiář a PJ / Číselníky), pod nimi
odkaz na `mapa.html` a (adminovi) na `admin.php`. Hlavní panel má buď
seznam záznamů, nebo formulář.

**Seznam záznamů** (`akce=seznam`, výchozí): jedna dlouhá `<div>` na
záznam — tučný název, řádek "stat badges" (`summary_fields` z
`entities.php`, např. u kouzla "Lv. 3 · Mana 5 magů · Dosah 10 sáhů..."),
ořezaný popis na 160 znaků, tlačítka Upravit/Smazat. Řadí se jen podle
pevně daného `order_by` (většinou `nazev`), **žádné jiné řazení,
stránkování, hledání ani filtrování neexistuje** — `dracak_entity_list()`
v `entity_crud.php` je doslova `SELECT * FROM table ORDER BY x`. U 680
kouzel nebo 342 schopností to znamená stovky karet pod sebou.

**Formulář** (`akce=novy`/`edit`): pole rozdělená na `quick` (vidět
rovnou) a zbytek pod `<details>Zobrazit všechna pole</details>`. Typy
polí: text, number, textarea, checkbox, select (pevný seznam options),
select_fk (jedna FK vazba 1:N na jinou tabulku, `<select>` se všemi
řádky té tabulky — u velkých ref. tabulek to může být dlouhý dropdown).
Žádná validace kromě HTML `required`, žádné inline nápovědy k formátu.

**Co v UI chybí úplně:**
- Hledání (textové, přes název/popis).
- Filtrování podle libovolného sloupce (úroveň, typ, povolání...).
- Stránkování / lazy loading u velkých tabulek.
- Editace M:N vztahů (viz sekce 4.3) — 18 vazebních tabulek v DB se
  dnes vůbec nedotkne přes UI.
- Hromadné akce (žádné batch operace, ani přehled "kolik toho je").
- Řazení měnitelné uživatelem (jen pevný `order_by`).
- Potvrzení uložení / undo u smazání kromě JS `confirm()`.

---

## 4. Přesný datový inventář

### 4.1 Editovatelné entity dnes v `includes/entities.php` (25 klíčů)

Skupina **obsah** (9 tabulek, hráči selektivně editovatelné):

| Tabulka (DB) | Popisek v UI | Odhad. počet řádků | Klíčová pole pro filtrování/hledání |
|---|---:|---:|---|
| `kouzla` | Kouzla | ~680 | `uroven_kouzla` (int), `typ_unavy` (enum 4 hodnoty), `seznam_kouzel_id` (FK → seznamy_kouzel), `nazev`/`popis` (fulltext), `cena_magenergie` (text), `dosah`/`rozsah`/`doba_trvani` (volný text) |
| `zvlastni_schopnosti` | Schopnosti a dovednosti | ~342 | `druh` (enum: schopnost/dovednost), `uroven_od` (int), `vyzaduje_id` (self-FK, prerekvizita), `nazev`/`popis` (fulltext) |
| `predmety` | Vybavení | ~219 | `typ` (enum: zbran/zbroj/surovina/artefakt), `kategorie_zbrane` (SET: sečná/bodná/tupá/drtivá/vrhací/střelná — víceh­odnotové!), `typ_pro_vyrazeni_dveri` (enum), `cena`/`vaha` (rozsah), `nazev`/`popis` (fulltext) |
| `lektvary` | Lektvary a elixíry | ~88 | `vyrobce_povolani_id` (FK → povolani), `cena` (rozsah), `nazev`/`popis` (fulltext) |
| `finty` | Finty | ? (menší, desítky) | `typ` (enum: utocna/obranna/kombinovana), `pozadovana_zbran` (text) |
| `povolani` | Povolání | ~41 | `typ` (enum: zakladni/vetev), `rodic_povolani_id` (self-FK), `pouziva_magenergii` (bool) |
| `rasy` | Rasy | ~31 | `rodic_rasa_id` (self-FK, klany/varianty) |
| `efekty` | Efekty (buff/debuff...) | menší, desítky | `typ` (enum 8 hodnot: buff/debuff/dot/hot/modifikator/imunita/zranitelnost/odolnost), `mechanika_aplikace` (enum 4 hodnoty) |
| `pasti` | Pasti (záchranné hody) | menší | `vlastnosti` (text, např. "Sil+Odl"), FK na efekt úspěchu/neúspěchu |

Skupina **bestiar** (2 tabulky, vždy skryté hráči, jen admin/PJ):

| Tabulka | Popisek | Odhad. počet řádků | Klíčová pole |
|---|---:|---:|---|
| `nestvury` | Příšery (bestiář) | ~208 | `velikost` (volný text, měl by odpovídat kódu A0–E z `velikosti`), `zkusenost` (int, rozsah), `prostredi` (text), `presvedceni_id` (FK), `ochoceni` (text) |
| `pj_poznamky` | PJ pravidla a poznámky | malé, desítky | jen `nazev` + `obsah` (fulltext) |

Skupina **ciselniky** (14 tabulek, jen admin/PJ, malé — jednotky až
nízké desítky řádků každá): `vlastnosti`, `opravy_za_atribut`,
`nosnost_podle_sily`, `narocnost_akci_unava`, `stupne_unavy`,
`odstraneni_unavy`, `vysledky_testu`, `obory_magie`, `skupiny_kouzel`,
`kody_zranitelnosti`, `jazyky`, `presvedceni`, `zkusenostni_tabulky`,
`seznamy_kouzel`. Tyhle nemají v `entities.php` ručně psaná pole —
`entity_crud.php`/`dracak_auto_fields()` je odvodí ze `DESCRIBE tabulka`
za běhu. Nemají typicky potřebu filtrování (jsou malé), ale potřebují
dobré řazení a jasné popisky sloupců (dnes label = název sloupce v DB,
tj. syrové `stupen_od` místo "Stupeň od").

### 4.2 Sdílené "kostky" pole (5 polí, 4 tabulky)

`kouzla`, `zvlastni_schopnosti`, `lektvary`, `finty` mají shodnou
pětici polí zavedenou migrací `0003_kostky_a_bonusy.sql`:
`pocet_kostek` (int), `typ_kostky` (enum k3/k4/k6/k8/k10/k12/k20/k100),
`pevny_bonus` (int, může být záporný), `vicenasobne` (bool — "lze
seslat/provést vícekrát"), `max_pouziti` (text, "3x denně" apod.).
V seznamu se dnes skládají do jednoho čitelného řetězce funkcí
`dracak_format_kostky()` (např. "2k6+2 (lze víckrát, max 3x denně)").
**Tohle chování — kostky NEUKAZOVAT jako čtyři rozházená pole, ale
jako jeden formátovaný zápis — redesign musí zachovat** i ve formuláři,
ne jen v seznamu.

### 4.3 M:N vztahy — dnes needitovatelné přes UI vůbec

Toto je největší funkční mezera dnešního UI. V DB existuje minimálně
18 vazebních M:N tabulek, žádná nemá zastoupení v `entities.php`:

| Vazba | Tabulka | Poznámka |
|---|---|---|
| Kouzlo ↔ Obor magie | `kouzlo_obor_magie` | **záměrně M:N** — některá kouzla mají kombinovaný obor (např. "Oko" = ČP+PO), viz komentář ve schématu |
| Kouzlo ↔ Efekt | `kouzlo_efekty` | |
| Schopnost ↔ Povolání | `schopnost_povolani` | schopnost může sdílet víc povolání |
| Schopnost ↔ Efekt | `schopnost_efekty` | |
| Rasa ↔ Schopnost | `rasa_schopnosti` | rasové schopnosti |
| Rasa ↔ Povolání | `rasa_povolani` | povolené povolání pro rasu |
| Rasa ↔ Jazyk | `rasa_jazyky` | |
| Rasa ↔ Vlastnost | `rasa_bonusy_vlastnosti` | **M:N s hodnotou navíc** — modifikátor (např. "+2") na dvojici rasa+vlastnost, ne jen prosté propojení |
| Povolání ↔ Jazyk | `povolani_jazyky` | |
| Předmět ↔ Efekt | `predmet_efekty` | např. efekty drahokamů |
| Lektvar ↔ Efekt | `lektvar_efekty` | |
| Lektvar ↔ Předmět (suroviny) | `lektvar_suroviny` | **M:N s hodnotou navíc** — `mnozstvi` (text) na dvojici lektvar+surovina |
| Finta ↔ Finta (prerekvizity) | `finta_prerekvizity` | self-referenční M:N |
| Nestvůra ↔ Kód zranitelnosti | `nestvura_zranitelnosti` | **M:N s hodnotou navíc** — `modifikator` (1/2, 1/4, 2× ...) na dvojici nestvůra+kód; zdroj pro budoucí bojový tooltip |
| Nestvůra ↔ Schopnost | `nestvura_schopnosti` | |
| Skupina kouzel ↔ Obor magie | `skupina_kouzel_obor_magie` | menší číselníková vazba |

Tři z nich (`rasa_bonusy_vlastnosti`, `lektvar_suroviny`,
`nestvura_zranitelnosti`) nejsou čisté M:N, ale **M:N s dodatečnou
hodnotou na vazbě** (modifikátor/množství) — z UX pohledu to není jen
"vyber víc položek", ale "vyber položku a k ní zadej číslo/text", což je
striktně náročnější interakční vzor než prostý multi-select.

Mimo M:N stojí za zmínku i `finta_akce` (sekvence akcí finty: pořadí,
typ Ú/O, OČ/ÚČ modifikátor — 1:N s vlastním pořadím, ne prosté řádky)
a `zkusenostni_urovne` (1:N zkušenostní tabulka → úrovně).

### 4.4 Tabulky mimo editor záměrně (dnes jen přes phpMyAdmin)

`velikosti` (PK je `kod`, ne `id` — generický editor v1 to nepodporuje),
`velikost_modifikatory` (6×6 matice, žádný smysluplný "seznam
záznamů" pohled), `zkusenostni_urovne`, a všech 18 M:N tabulek výše.
Redesign by měl vyjasnit, zda i tyhle mají dostat plnohodnotné UI, nebo
zůstávají v gesci phpMyAdminu (viz otevřené otázky).

### 4.5 Poznámka k úplnosti dat

Databázová data se průběžně doplňují z ručního přepisu skenované
předlohy pravidel (`docs/kontrolni-seznam-neuplnych-mist.md` eviduje
desítky míst s neúplnými/nejistými hodnotami). To pro redesign UI
znamená: řada polí u konkrétních záznamů bude legitimně prázdná/NULL i
v produkčních datech (ne jen u nových záznamů) — návrh nemá předpokládat,
že "prázdné pole = chyba", zvlášť u bestiáře a homebrew obsahu.

---

## 5. Požadavky na výsledné rozhraní

### 5.1 Musí zůstat zachováno (funkční parita s dnešním stavem)

- **Rychlá šablona pro nový záznam** — rozlišení "pár polí vidět hned"
  vs. "zbytek schovaný, ale editovatelný" (dnes `quick`/`advanced` +
  `<details>`). Nejde smazat ani zplošit do jednoho dlouhého formuláře.
- **Skrytí bestiáře/PJ poznámek/číselníků hráči** — úplně, ne jen
  vizuálně schované, ale nedostupné (jak dnes řeší `$visibleEntities`
  filtr a `dracak_can_edit`).
- **Table-level editační oprávnění hráče** podle `ucet_opravneni`,
  nastavitelná adminem — princip "admin odškrtává, které typy záznamů
  smí konkrétní hráč sám editovat" musí zůstat funkční a srozumitelný.
- **Formátovaný zápis kostek** (`2k6+2`, "lze seslat vícekrát...") v
  seznamu i understandable editaci ve formuláři — ne čtyři/pět
  syrových polí vedle sebe bez kontextu.
- **Skládaný "stat badge" řádek** v seznamu (dnešní `summary_fields`) —
  myšlenka rychlého přehledu klíčových čísel bez nutnosti otevřít
  detail (Lv./Mana/Dosah u kouzla, Živ./ÚČ/OČ u nestvůry...) — redesign
  ji může vizuálně přepracovat, ale nesmí přijít o hodnotu, kterou dnes
  dává: vidět nejdůležitější čísla bez prokliku.
- **CRUD nad všemi 25 dnešními entitami** minimálně ve stejném rozsahu
  polí, jaký mají dnes (žádné pole se neztrácí).
- Mazání se současným potvrzením (dnes JS `confirm()`) — úroveň jistoty
  proti omylu se nesmí snížit.
- Odkaz do `mapa.html` a (pro adminy) do `admin.php` v navigaci.

### 5.2 Nová funkčnost: hledání a filtrování

Musí existovat způsob, jak ve velké tabulce (680 kouzel, 342
schopností, 219 kusů vybavení, 208 nestvůr) najít konkrétní záznam bez
scrollování celého seznamu. Konkrétně, na základě reálných sloupců
(viz tabulka v sekci 4.1), řešitel má navrhnout minimálně:

- **Textové hledání** v `nazev` (+ ideálně `popis`) napříč všemi
  entitami s textovým obsahem — jde o nejčastější use-case ("najdi
  kouzlo/příšeru/předmět podle jména").
- **Kouzla**: filtr podle `uroven_kouzla`, `typ_unavy`, `seznam_kouzel_id`
  (= kouzla konkrétního povolání/seznamu, "kouzla mága PPP" apod.).
- **Schopnosti/dovednosti**: filtr podle `druh` (schopnost vs.
  dovednost) a `uroven_od`.
- **Vybavení**: filtr podle `typ` (zbraň/zbroj/surovina/artefakt) a
  `kategorie_zbrane` (pozor, jde o víceh­odnotový sloupec typu SET).
- **Lektvary**: filtr podle vyrábějícího povolání.
- **Bestiář**: filtr/hledání podle `prostredi`, případně rozsahu
  `zkusenost` — s vědomím, že `velikost` je dnes volný text, ne čistý
  enum (viz 4.1), takže přesné filtrování po velikosti nemusí být
  spolehlivé bez úpravy dat.
- Filtry se musí dát **kombinovat s textovým hledáním zároveň**
  (hráč typicky hledá "kouzla 3. úrovně obsahující slovo oheň").

Otevřené je (viz sekce 6), zda filtrování běží nad daty už staženými do
prohlížeče (JS filtr), nebo se dotazuje serveru (PHP/SQL) — obojí je
legitimní volba s jinými kompromisy, řešitel má rozhodnout a zdůvodnit.

### 5.3 Nová funkčnost: editace M:N vztahů

Řešitel musí navrhnout způsob, jak v editačním formuláři dané entity
přiřazovat/odebírat související záznamy z jiné tabulky (viz kompletní
seznam v sekci 4.3), včetně tří vazeb, které navíc nesou hodnotu na
vazbě samotné (modifikátor u rasa↔vlastnost a nestvůra↔zranitelnost,
množství u lektvar↔surovina). Otevřené otázky k tomuto bodu jsou v
sekci 6 — zadání záměrně nepředepisuje multi-select/tag input/jiný vzor.

### 5.4 Navigace mezi entitami

Dnešní tři skupiny v postranním panelu (Obsah pravidel / Bestiář a PJ /
Číselníky) fungují jako hrubé členění 25 entit. Řešitel má posoudit,
zda tohle členění (a jeho pořadí/vizuální váha) při zachování
bezpečnostního rozlišení z bodu 2 stačí, nebo je pro reálné používání
(admin/PJ přepíná mezi entitami velmi často) potřeba jiná navigace —
viz otevřená otázka v sekci 6.

### 5.5 Technická a nefunkční omezení

- **Žádný build krok.** Nasazení je čistý FTP upload obsahu repa přes
  GitHub Action (`.github/workflows/deploy.yml`) na sdílený hosting
  WEDOS. Cokoli, co vyžaduje `npm install`/bundler/transpilaci, je mimo
  hranice — HTML/CSS/JS musí být rovnou spustitelné soubory v repu.
  Menší vanilla JS (bez frameworku, bez balíčkovacího kroku), případně
  JS knihovna vložená jako statický soubor přímo do repa (ne přes CDN
  závislý na buildu), je v pořádku, pokud zůstane realisticky
  udržovatelná pro netechnického/středně technického majitele appky.
- **PHP + MySQL/MariaDB, bez frameworku.** Backend je čisté PHP
  (žádné Laravel/Symfony apod.), PDO nad MySQL. Návrh má s touhle
  realitou počítat (server-side řešení = čisté PHP šablony/skripty).
- **Schéma DB se mění jen přes číslované migrace** v
  `database/migrations/NNNN_popis.sql`, nikdy úpravou starého souboru.
  Po pushi na `main` je spouští automaticky `scripts/migrate.php` na
  produkci (poslední krok `deploy.yml`, viz `CLAUDE.md`) — přímé
  připojení GitHub Actions k produkční MySQL zvenčí není na tomhle
  hostingu možné, takže migrační skript běží až na serveru. Migrace smí
  obsahovat jen `CREATE`/`ALTER ADD`/`INSERT`, nikdy `DROP` ani jinak
  destruktivní příkaz bez výslovného schválení — `migrate.php` to sám
  vynucuje. Pokud návrh UI vyžaduje nové sloupce/tabulky (např. pro M:N
  editaci, řazení, vlastnictví záznamů), musí to být formulováno jako
  taková migrace.
- Appka běží **v prohlížeči na desktopu i mobilu** — dnešní CSS grid
  layout (`.editor-shell`) je pevný dvousloupcový, bez media queries;
  posoudit, nakolik je mobilní použití reálně důležité (PJ typicky
  appku používá i za stolem na telefonu/tabletu při hraní?) — otevřené
  v sekci 6.
- Autentizace zůstává jednoduchá session-based (`includes/auth.php`,
  PHP nativní session) — není v scope měnit.
- Cílový uživatel (majitel appky) umí HTML, není senior vývojář —
  návrh (a případná implementační dokumentace k němu) by měl počítat s
  tím, že složitost údržby je reálné omezení, ne jen nice-to-have.

---

## 6. Otevřené otázky k rozhodnutí řešitelem

Tohle zadání záměrně neurčuje odpovědi — jsou to rozhodnutí, která má
udělat ten, kdo dostane návrh na starosti, a zdůvodnit je:

1. **Jak editovat M:N vztahy v UI?** Multi-select `<select multiple>`,
   tag/chip input s hledáním, checkbox seznam, samostatná "přiřazovací"
   podstránka na entitu, něco jiného? A jak konkrétně ty tři vazby s
   hodnotou navíc (rasa↔vlastnost modifikátor, nestvůra↔zranitelnost
   modifikátor, lektvar↔surovina množství) — potřebují jiný vzor než
   čisté M:N?
2. **Server-side vs. client-side filtrování/hledání?** Stáhnout celou
   tabulku (u kouzel 680 řádků) do prohlížeče a filtrovat/hledat v JS,
   nebo posílat dotazy na server (formulář/AJAX) a filtrovat SQL
   dotazem? Kompromisy: rychlost interakce vs. objem přenášených dat
   vs. složitost implementace na sdíleném PHP hostingu.
3. **Jak řešit navigaci mezi 25 entitami (~45 DB tabulkami celkem,
   počítaje M:N)?** Zůstat u tří skupin v sidebaru jako dnes, nebo
   zavést jinou strukturu (např. vyhledávání entit, oblíbené/poslední
   použité, jinak seskupené kategorie)? Má smysl rozlišovat "velké"
   entity (kouzla, bestiář, schopnosti, vybavení) vizuálně od "malých"
   číselníků, aby uživatel hned věděl, kde čekat scrollování/filtry a
   kde ne?
4. **Stránkování, "load more", nekonečný scroll, nebo úplně jiný vzor
   seznamu** (např. tabulka s řazitelnými sloupci místo karet)? Karty
   se stat-badges dnes fungují dobře pro čitelnost jednoho záznamu, ale
   špatně pro srovnávání víc záznamů najednou (např. porovnat 5 kouzel
   3. úrovně vedle sebe) — má redesign nabídnout i "tabulkový" pohled?
5. **Row-level vlastnictví záznamů u hráčů** — implementovat (viz nález
   v sekci 2 o nedokončeném "jen svoje vlastní záznamy"), nebo zůstat u
   table-level oprávnění a nález jen zdokumentovat jako vědomé
   rozhodnutí?
6. **Mají číselníky (14 malých tabulek) dostat vlastní odlišné UI**
   (jsou to typicky jen desítky řádků, editace je spíš výjimečná), nebo
   stejný vzor jako velké obsahové tabulky, jen v menším?
7. **Mají se do UI dostat i tabulky dnes záměrně vynechané** —
   `velikosti` (PK `kod`), `velikost_modifikatory` (6×6 matice),
   `zkusenostni_urovne` (per-úroveň řádky), a všech 18 M:N tabulek jako
   samostatné entity, nebo výhradně jako součást editace mateřské
   entity (bod 1)? Dává některým z nich smysl vlastní "maticový" pohled
   místo seznamu?
8. **Formátovaný zápis "kostek"** (5 sdílených polí, sekce 4.2) — má se
   editovat pořád jako pět oddělených polí formuláře (dnešní stav), nebo
   navrhnout kompaktnější vstup (např. jedno textové pole s parsováním
   zápisu typu "2k6+2", validované/rozpadané zpět do sloupců)?
9. **Responzivita / mobilní použití** — je reálný požadavek, že PJ
   appku otevírá na telefonu/tabletu během hraní, nebo je to čistě
   desktopová administrace prováděná mimo herní sezení? Odpověď mění
   prioritu mobilního layoutu.
10. **Má redesign zahrnout i vizuální styl** (barvy, typografie), nebo
    se má držet současného tmavého schématu v `assets/css/style.css` a
    řešit čistě informační architekturu a interakce?
11. **Hromadné akce** (např. smazat/přesunout víc záznamů najednou,
    exportovat filtrovaný výsledek) — potřeba, nebo mimo scope v1
    redesignu?

---

## 7. Mimo rozsah tohoto zadání

Pro jasnost, co řešitel **neřeší**:

- Autentizace/session mechanismus (zůstává, jak je).
- Struktura rolí admin/pj/hrac jako taková (může navrhnout úpravy UI
  správy rolí v `admin.php`, ale ne nový model rolí).
- Vrstva "postava/instance postavy" a runtime stav boje (aktivní
  efekty za hry) — podle `docs/db-model-navrh-v1.md` jde o budoucí,
  samostatnou vrstvu nad touhle databází pravidel, tady se nenavrhuje.
- Doplňování/oprava samotného obsahu pravidel (kouzla, bestiář...) —
  to je datová práce podle `docs/kontrolni-seznam-neuplnych-mist.md`,
  ne UI práce.
- `mapa.html` (mapa světa) — samostatná, nesouvisející stránka.
- Nasazovací pipeline a FTP deploy proces.

---

## 8. Podklady

Zdrojové soubory, ze kterých tohle zadání vychází a které si má
řešitel před návrhem sám znovu projít (definice polí a chování se
můžou mezitím posunout):

- `includes/entities.php` — registr entit, polí, skupin, summary_fields.
- `editor.php` — současné vykreslení seznamu i formuláře.
- `admin.php` — správa účtů a per-hráč oprávnění.
- `includes/auth.php`, `includes/entity_crud.php` — vynucení práv a CRUD.
- `assets/css/style.css` — současný vizuální styl.
- `database/drd-db-schema-v1.sql` + `database/migrations/*.sql` —
  přesná DB schémata, typy sloupců, FK, M:N tabulky.
- `docs/db-model-navrh-v1.md` — původní návrhový dokument datového
  modelu a vztahů mezi entitami.
- `docs/kontrolni-seznam-neuplnych-mist.md` — kontext o neúplnosti dat
  (proč prázdná pole nejsou nutně chyba).
