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

**Nejdřív si přečti `docs/zadani-pro-novy-chat.md` (předávací dokument z 2026-11-06: pravidla komunikace s uživatelem, pasti lokálního prostředí, co se udělalo, co čeká).** Přečti celý tenhle soubor, pak `CLAUDE.md` (hlavně pravidla o tom, že
zdroj pravdy o hře jsou pravidla v `content/`, ne repo/DB, a o
neodhadování mechaniky). **Fáze 1 a 2 ze sekce "Rozpracováno:
cestování..." jsou v kódu hotové**; **Fáze 1b (váha/kontejnery) je
hotová v kódu, stejně jako Fáze 2–5 (zvířata, povozy, kalkulačka cesty,
přepínače) — celé "cestování, naložení, únava" je tím hotové a nasazené,
ruční SQL nečeká nic (poslední blok I — automatické seslání kouzla, migrace `0114` — uživatel provedl 2026-11-04, kontrola vyšla 1; Změny databáze → Spustit čekající změny uživatel spustil 2026-11-05).** Další velký cíl je sekce "Plán: náhrada Owlbear
Rodeo" níž. Stav předchozí
konverzace je zapsaný přesně proto, aby ses nemusel/a ptát na věci, co
už byly rozhodnuté (viz "Architektonická rozhodnutí" a celá sekce
"Rozpracováno" níže — všechno v ní je buď přímo citovaný text pravidel,
nebo odsouhlasené uživatelem, ne můj odhad).

### Panel tokenu: co se odvozuje z pravidel místo ručního zadávání (2026-11-09)

Zadání uživatele: *„pokud bestiář nemá infravidění, pak by tady být nemusel… pokud to potvora má, aplikuje se automaticky“*
a *„velikost by měla být automatická podle vlastnosti tokenu“* (+ potvrzeno, že velikost je navázaná na tvar a počet hexů).
Širší princip, který k tomu uživatel řekl: **zdroj pravdy jsou pravidla, DB je jen jejich přepis — když v DB něco chybí, je to chyba dat,
ne důvod nechávat ruční pole.**

**Hotovo:**
- **Infravidění nestvůr je v datech** (migrace `0116`, ruční SQL blok K): nový sloupec `nestvura_schopnosti.hodnota` (obecný — deník
  nestvůry na něm postaví i další schopnosti) a vazby podle bestiáře **s citací u každého řádku**: Zmije rohatá 10 sáhů (b18526),
  Haterie 30 (b18603), Vážka jeskynní 30 (b18710), nemrtví 30 (b19754, celá skupina Kostlivec–Upír); Bazilišek (b17822),
  Krysodlak zvířecí (b19305) a Skřet (b20514) infravidění mají, ale **kniha dosah neuvádí — nic se nevymýšlí**, dosah doplní PJ.
  Nová schopnost „Nerozezná ho infravidění“ pro Haterii, Hlen zelený (b18616), Mloka (b19690), Nevidění (b19921) a oživlé sochy (b95001).
- **Nový token nestvůry si to vezme sám** (`dracak_vtt_nestvura_smysly()` ve `vtt_nestvury.php`, volá `token_pridat.php`); PJ to může přepsat.
- **Velikost tokenu se odvodí z počtu hexů**: jeden hex = jeden sáh (h1622 b10798), takže tvor na N hexech je N sáhů široký;
  třída A0 („na jeden hex se vejde víc tvorů“) se kreslí na půl sáhu. Ručně nastavená velikost se dědí dál a odvození ji nepřebije.
- **„Vzhled a smysly“ je v panelu sbalený** (`<details>`), v hlavičce nese souhrn (svítí / zamčený / skrytý / hexy).
  **Světlo zůstalo mimo sbalenou sekci** — je to nejčastější úkon PJ na tmavé mapě.
- Test `tests/e2e/nestvura_smysly_api.js`.

- **Infravidění postav z rasy** (migrace `0117`, stejný ruční blok K): `rasa_schopnosti.hodnota`, trpaslík 20 sáhů.
  Citace: *„Trpaslík má infravidění s dosahem 20 sáhů; neproniká materiálem, kterým neproniká obyčejné světlo.“* (hrac **b790**).
  Prohledány všechny rasy proti textu pravidel — infravidění má z hratelných ras **jen trpaslík** (kroll má ultrasluch, hobit čich).
  Kouzlo Lovce „infravidění jako trpaslík na 50 sáhů“ (b7318) je dočasný účinek kouzla, ne dosah rasy.

**Moje chyba, ze které plyne poučení:** nejdřív jsem tvrdil, že dosah trpaslíkova infravidění v přepisu **chybí**, a zapsal to
do kontrolního seznamu jako „potřebuje foto“. Ve skutečnosti je přímo v b790 — moje první hledání větu utnulo v půlce
(špatný regulární výraz při vytahování textu z HTML). Oprava zapsaná i v `docs/kontrolni-seznam-neuplnych-mist.md`.
Platí tedy pravidlo z `CLAUDE.md` i naopak: **„v pravidlech to není“ se netvrdí po jednom hledání** — a hlavně se kvůli tomu
nemá odkládat mechanika (uživatel 2026-11-09: *„nesmysl neimplementovat mechaniku jen na neshodě čísel“*).

**Zbývá** (návrh v `docs/panel-tokenu-navrh.md`, schváleno uživatelem): rozpad panelu na vrstvu tokenu (nástroj PJ), vrstvu tvora
(kompaktní deník místo „deníku lite“) a vrstvu PJ uvnitř něj (zámek, efekty i mimo iniciativu, ±životy, kořist); světlo přesunout
k předmětu v inventáři („Zapálit / zhasnout“); deník nestvůry doplnit o životy, efekty a výbavu.

### Okno tvora místo postranního panelu — první krok hotový (2026-11-10)

Zadání uživatele: *„Panel tvora musí zmizet v tom, jak je. Ten postranní panel je prostě nešťastný. Po kliknutí na token
musí PJ vidět věci, co se mi hodí. Spíš float u příšery.“* Upřesnění: **odolnost a pohyblivost do okna nepatří**
(jsou v deníku), **efekty se vyhodnocují samy a tady jsou jen vidět** — nasazují se kouzlem, lektvarem nebo akcí jiného
tvora, stejně jako u postav hráčů.

**Hotovo:** klik PJ na nestvůru otevře **plovoucí okno u tokenu** (`assets/js/tvor_okno.js`, CSS `.tvor-okno`), ne postranní
panel. Okno jde táhnout za hlavičku, na telefonu sedí dole přes šířku. Obsah: jméno a druh, **životy s ±1/±5 a stavem
z pravidel** (bojeschopná / pod třetinou s postihem / vyřazená — počítá server, `dracak_vtt_bojeschopnost`), **útoky
s ÚČ a útočností**, obrana (OČ, OZ, velikost), **čím jde tvora zranit**, schopnosti i s hodnotou („Infravidění 30 sáhů“),
**co na něj působí** (efekty jen k vidění, s křížkem na sundání), sbalené „Po boji“ (zkušenost, poklady) a popis z bestiáře.
Dole tlačítko do postranního panelu, kde zůstal deník nestvůry, výbava a kořist.

**Největší nález: zranitelnosti hra nikdy neukazovala.** V databázi je 755 vazeb nestvůra ↔ způsob útoku, ale používal je
jen editor pravidel — PJ při hře neviděl, čím tvora vůbec jde zranit. Teď jsou v okně jako čipy, včetně násobitele zranění
(*„Za písmenem může být ještě číslo… Tímto číslem se vynásobí počet odebraných životů po úspěšném útoku, zaokrouhluj nahoru“*)
a připomínky pravidla, že **co v seznamu není, tvorovi neublíží**.

**Migrace `0118` (jen data, ruční SQL netřeba):** šest kódů zranitelnosti mělo prázdný název s poznámkou „TBD, ověřit proti
bestiáři“ — legenda v knize přitom je. Doplněno s citací: I = I. skupina kouzel (hraničářská), J = II. (útočná),
K = III. (ohnivá a mrazivá), L = IV. (fyzická), M = V. (psychická), N = VI. (hraničářská). Vazba na tabulku `skupiny_kouzel`
se **záměrně nedoplnila** — ta má čtyři řádky proti šesti číslovaným skupinám, spojovat by byl odhad.

Test `tests/e2e/tvor_okno_ui.js` (otevření, obsah, že odolnost a pohyblivost tam nejsou, zranitelnosti i s jménem skupin
kouzel, stav podle životů, efekt se objeví sám a jde sundat, hráči se okno neotevře).

**Zbývá z návrhu `docs/panel-tokenu-navrh.md`:** přesunout vzhled tokenu do lišty dole, u postav nahradit postranní panel
kompaktním deníkem, vrstva PJ uvnitř něj, světlo k předmětu v inventáři, deník nestvůry doplnit o životy a výbavu.

**Designové okno k připomínkám (2026-11-10):** https://claude.ai/artifact/ShFfKGCh8AUcW3jK3ncVnQ — plátno se čtyřmi
nákresy (návrh mimo boj, návrh v souboji, dnešní postranní panel pro srovnání, telefon 390 px). Uživatel do něj píše
připomínky komentářem přímo na místo v nákresu. Zdrojové soubory nákresů nejsou v repu (jsou v plátně samotném) —
repo drží jen tenhle odkaz. **Plátno edituje i uživatel**, takže před další úpravou se jeho soubory musí přečíst
(`Artifact action: "read"`), ne přepsat z lokální kopie — 2026-11-10 si tam sám přidal odkaz „Deník ↗“ do hlavičky.

#### Zapracované připomínky z plátna (2026-11-10, verze 2 návrhu i kódu)

Devět komentářů, všech devět vyřízeno v `assets/js/tvor_okno.js`, `assets/css/mapa.css`, `hra/api/nestvura_denik.php`
a `hra/api/hp_uprava.php`:

| Připomínka | Co se stalo |
|---|---|
| „tam kde jsou komenty, chtěl bych tooltip s otazníčkem v kulatém poli… používej to všude“ | Nový vzor `.tvor-hlp` + `.tvor-tip`: kolečko `?` za nadpisem sekce, bublina na nájezd i na klepnutí (telefon). **Zapsáno jako pravidlo do `CLAUDE.md`** — platí pro celou aplikaci. |
| 3× „zbytečný koment“ (mez vyřazení, „co v seznamu není“, „efekty nasazuje kouzlo…“) | Odstavce z okna zmizely, texty jsou v bublinách. Pole `zranitelnosti_pozn` v API zrušené (byla to tatáž věta podruhé). |
| „tady stačí písmena zranitelnosti a na mouseover vyjede, čím je to zranitelné, případně ikona“ | Čip je písmeno A–P (`kody_zranitelnosti.kod`) + ikona, celé jméno a popis v nájezdu a v `aria-label`. Násobek jako horní index. |
| „velikost nepatří mezi čísla obrany“ | Z Obrany ven, je v hlavičce u druhu tvora („nemrtvý · velikost B“). |
| „±životy mají být vedle ukazatele, stačí + a −, PJ může přepsat ručně, stejně tak hráči“ | Čtyři tlačítka → `−` a `+` po stranách pruhu; číslo je vstup, co jde přepsat. Nové `dracak_vtt_hp_nastav()` + parametr `nastavit` v `hp_uprava.php` (ořez a souběhy řeší DB stejně jako u delty, práva beze změny — PJ komukoli, hráč u své postavy). |
| „dovednosti bychom neměli zapomenout“ | `nestvura_denik.php` posílá i `zvlastni_schopnosti.druh`; nadpis se jmenuje podle toho, co v sekci opravdu je (Schopnosti / Dovednosti / Schopnosti a dovednosti). |
| odkaz „Deník ↗“, který si uživatel přidal do hlavičky nákresu | Převzatý do kódu; spodní tlačítko „Deník, výbava a kořist…“ bylo jeho dvojník, takže je venku (sekce `.tvor-paticka` zrušená). |

Test `tests/e2e/tvor_okno_ui.js` je rozšířený na všechno z toho (písmena a jejich jména, schovaná i otevřená bublina,
dvě tlačítka u pruhu, značka meze, ruční přepis včetně nesmyslného vstupu, velikost mimo obranu, odkaz v hlavičce).

### Rychlý deník místo postranního panelu u postav (2026-11-10)

Zadání uživatele: *„rychlý deník mám zkontrolovaný, přijde mi super. zaimplementuj.“* Tím se dodělala část B a C
návrhu `docs/panel-tokenu-navrh.md`.

**Co se změnilo:** klik na token postavy otevře **rychlý deník** (`hra/denik.php?embed=1` v plovoucím okně,
`assets/js/denik_dock.js`), ne postranní panel. Dřív se u postavy otevíral panel, a to jen když šlo o vlastní
token na tahu — na cizí postavu nebo mimo tah tedy klik nedělal vůbec nic.

* nestvůra → okno tvora u tokenu (beze změny),
* postava, na kterou má uživatel deník (PJ všechny ve světě, hráč své) → rychlý deník,
* ostatní → postranní panel jako dřív.
* Klik ctí režim okna („pevně tuhle postavu“ / „podle toho, kdo je na tahu“ se klikem nepřebíjí) — na to je
  v `denik_dock.js` zvlášť `otevriZTokenu()`, protože `otevriPro()` je výslovná volba z tlačítka a režim přebít má.

**Vrstva PJ uvnitř deníku (část C návrhu):** blok `#sekceEntita` z postranního panelu se při otevření deníku
**přesune** do `#denikPjObsah` v okně deníku a při zavření se vrátí zpět. Přesunem v DOM zůstanou v platnosti
všechny obsluhy, které na něm visí (iniciativa, rychlé efekty, aplikace efektu, kořist) — nevzniká druhá sada
ovládání, která by se s panelem rozcházela. Co deník ukazuje sám (životy, magenergie, únava, inventář), se ve
vrstvě skryje přes CSS `.denik-pj` — jinak by vedle sebe stály dvě sady týchž tlačítek, což je přesně ten
„deník lite“, kterému se vyhýbáme. Když má PJ otevřený i postranní panel, nástroje zůstanou tam (jeden blok
nemůže být na dvou místech).

**Postranní panel** (vzhled značky, zámek, smazání, dosah pohybu) se otevírá z kontextového menu nad tokenem,
položkou **„Nastavení tokenu…“** — a nově pro kterýkoli token, se kterým uživatel smí hýbat, ne jen pro vlastní
na tahu. Vedle je položka „Rychlý deník“.

Pozor při psaní testů: klik na postavu **neotevře** `#panel-spravovat`. Sady, které panel potřebují, si ho
otvírají přes kontextové menu (vzor `otevriPanel()` v `tests/e2e/denik_dock.js`, `efekty_ui.js`,
`noc_videni_api.js`, `dosah_pohybu_ui.js`) a předtím zavřou deník, aby pravé tlačítko trefilo token.

### „Hoď si“ a kostka pro celý stůl (2026-11-11)

Zadání uživatele a jeho připomínky k návrhu na plátně https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz
(deska „Hoď si — co vidí PJ“ a „co vidí hráč“). Postaveno přesně podle nich:

* **PJ** má v panelu tokenu postavy blok **Hoď si**: k6 · k10 · k100 · „Hoď si“. Nic jiného tam není —
  sekci „na co se hází“ uživatel zrušil jako duplicitní („past je k10, postřeh je implicitně k100“), stejně
  jako seznam posledních hodů a poznámku o víc tokenech naráz. U nestvůry se blok nezobrazí, té hází PJ sám.
* **Hráč** dostane nízké okno dole (ne dialog přes půl obrazovky): když PJ kostku určil, je v něm jen
  tlačítko *Hodit k10* a křížek; když ji nechal na hráči, vybírá si hráč z k6 / k10 / k100. Žádné hlášení
  vlastního hodu od stolu — to řeší hlas nebo chat.
* **Kostku vidí celý stůl**: vpravo dole, asi třetinu od hran, zatočí se, dopadne a zůstane na ní číslo.
  Tím to končí — **jestli hod prošel, řeší PJ**, aplikace nic nevyhodnocuje. Zvuk dopadu se dá vypnout
  (pamatuje si to prohlížeč).
* **Hází server** (`hra/api/kostka_hod.php`), ne prohlížeč: jinak by šlo v konzoli přepsat, co padlo.

Technicky: výzva je **jen událost** `hod_vyzva` ve `svet_udalosti` (`hra/api/hod_vyzva.php`), žádná nová
tabulka — hráčův prohlížeč ji uvidí při pollu a otevře okno. Odpovědí je obyčejný hod s `vyzva_id`, takže
výzva po hodu zanikne sama a nemůže „uvíznout“. Okno se otevře **vlastníkovi postavy**, ne každému, kdo ji
má v nabídce deníku — PJ vidí v deníku všechny postavy světa, takže by si jinak výzvu otevřel sám sobě;
u postavy bez vlastníka (NPC vedené PJ) okno dostane PJ.

Kód: `assets/js/kostky.js` (okno výzvy i kostka pro všechny), markup a styly v `hra/mapa.php` a
`assets/css/mapa.css`, tlačítka PJ v `#hodVyzvaBlok` uvnitř `#sekceEntita` (putuje tedy i do vrstvy PJ
v rychlém deníku). Test `tests/e2e/hod_vyzva_ui.js`.

**Zbytek plátna čeká na rozhodnutí** — uživatel zatím okomentoval i lištu, výběr tokenu, souboj a pin;
shrnutí jeho odpovědí je v komentářích na plátně.

### Lišta nástrojů po sekcích a házecí místo (2026-11-11)

Zadání: *„levé boční menu je masivní, asi bych ho potřeboval rozsekat na logické sekce“* + k návrhu
*„popisky se mi líbí, jen bych chtěl zachovat, že bude jen jeden panel nabídky“* a *„kostka mi přijde,
že by měla být floating sama o sobě někde bokem — takové vyskakovací okno, které vyvolá házecí místo,
dice tray třeba“*.

* **Pět sekcí** s drobným popiskem a oddělovací linkou: **Pohyb** (výběr, rámeček, ruka, krok zpět),
  **Souboj** (zahájit souboj / další na tahu, konec kola, panel tvorů), **Tvorové** (přidat token, správa
  tokenů, pin, mapa míst), **Mapa** (měřidlo, kreslení, zeď, mlha, grid), **Ostatní** (ping, log).
* **Jeden sloupec, nikdy dva.** Obecná `.toolbar` v `assets/css/organic.css` má `flex-wrap:wrap`, takže se
  lišta při větším počtu nástrojů **rozpadala na dva sloupce** — to byl kus té „masivnosti“. `mapa.css`
  teď má `flex-wrap:nowrap` a tlačítka 42 px; když se lišta nevejde, roluje se.
* **Popisek sekce je tlačítko**: klepnutím se sekce sbalí (ikony zmizí, popisek zešedne a dostane ▸) a
  prohlížeč si to pamatuje (`localStorage`, klíč `dracak_lista_sbaleno`). Na krátké obrazovce se tak lišta
  vejde bez rolování, místo aby se musela dělit na záložky. Na telefonu (vodorovná lišta dole) se popisky
  skrývají a sbalit nejde — tam se roluje do strany.
* **Kostky z lišty pryč**: plovoucí kolečko vpravo dole (nad ovládáním zoomu, u místa, kam kostky dopadají)
  otevře **házecí místo** — k4 · k6 · k8 · k10 · k12 · k20 · k100, pole na vlastní zápis (`2k6+2`, Enter
  hází) a přepínač zvuku dopadu. Starý popover `popover-kostky` i tlačítko `tb-kostky` jsou zrušené.
* **Kdo hází, hodí 3D kostkou** (`assets/js/kostky3d.js`) — a to i při odpovědi na „Hoď si“. Ostatní u stolu
  uvidí kartu s číslem (`assets/js/kostky.js`), protože dice-box 1.1.4 **neumí přehrát cizí hod na předem
  dané číslo**; kdo hodil 3D, kartu už nedostane (události si prohlížeč pamatuje v `vlastni3d`).

Kód: `hra/mapa.php` (`.tb-skup`, `#kostkyOtevrit`, `#kostkyTray`), `assets/css/mapa.css`,
`assets/js/mapa.js`. Test `tests/e2e/lista_skupiny_ui.js` (21 kontrol: sekce, jeden sloupec, sbalení i jeho
paměť po reloadu, házecí místo, hod v logu u hazeče a karta u ostatních).

### Výběr tvora s náhledy (2026-11-11)

Zadání: *„chtěl bych, aby měl výběr tokenů a přidání lepší UI. Představoval jsem si v seznamu, tak jak je
kompaktní teď, aby tam byl náhledový obrázek potvory a pak její název.“* K návrhu pak upřesnil: řádky
**snížit**, náhled má **přesahovat** bublinu řádku, *„není to třída, je to velikost — stačí napsat B“*,
*„kolik zabírá je nesmysl, jedinečný zbytečná informace“*, *„je potřeba žvt X“*, *„úč může být vícero,
vypsat jich všechny za nějaký rozdělovač“*.

* Seznam `assets/js/tvor_vyber.js` nahradil holý `<select>` **na dvou místech**: v popoveru „Přidat token“
  a v liště správy tokenů (tam vyjede nad polem hledání jako našeptávač a vybraný druh se do pole zapíše).
  Skrytý `<select>` v obou případech zůstal — drží vybrané id, takže pokládání tokenu i výroba NPC jedou beze změny.
* Řádek: **náhled** (iniciála na barvě nestvůry, `#4a2b2b` jako token na mapě) + jméno + štítky
  **velikost · žvt · OČ · ÚČ**. Útoků bývá v knize víc (oddělené středníkem) — jsou všechny v jednom štítku
  oddělené „|“, celé znění v nájezdu myši; co se nevejde, se vytrácí doprava.
* **Tvar náhledu se řídí mřížkou** — na hexové mapě šestiúhelník, na čtvercové kolečko, tedy to, co po
  položení opravdu uvidíš. Obrázek tvora bestiář needviduje (žádný sloupec `nestvury` ho nemá), takže
  náhled je iniciála; až budou tokeny s obrázky, může se sem doplnit.
* Čísla do prohlížeče posílá `hra/mapa.php` v `window.MAPA.bestiar` (`zivotaschopnost`, `uc`, `oc`,
  `velikost`) — texty z knihy jsou volné, dělí je až prohlížeč.

Test `tests/e2e/tvor_vyber_ui.js` (20 kontrol, mimo jiné že v řádku NENÍ slovo „třída“, „zabírá“ ani
„jedinečný“ a že tvar náhledu sedí s mřížkou) a rozšířený `tests/e2e/token_bar_ui.js`.

### Piny: cesta zpátky, mapa odsud, velikost (2026-11-11)

Tři věci ze zadání uživatele k pinům:

1. **Družina se už neobjeví uprostřed mapy.** Při vstupu pinem do nové zóny hledal
   `includes/vtt_prechod.php` pin, který vede zpátky, a když žádný nebyl, postavil družinu doprostřed —
   přesně to uživateli vadilo. Teď se takový pin **založí sám** (`Zpět: <jméno mapy, odkud se přišlo>`,
   typ „přechod“, viditelný hráčům) a družina stojí u něj. PJ pak na nové mapě dostane nízkou hlášku
   (`#zpatecniPin`) se dvěma volbami — *„Pin nedělej“* (`hra/api/pin_zpatecni.php`, akce `zrusit`) a
   *„Posunout jinam“* (akce `posunout` — klepne na mapu a pin se i s družinou přemístí; volná pole kolem
   počítá server toutéž logikou jako při vstupu). To odpovídá jeho rozhodnutí: *„měl by se zakládat
   automaticky, ten prompt by měl jít zrušit, aby pin nevznikl“* + *„mělo by se zeptat přesun družiny na
   pin? a buď dám ano, nebo vyberu jinam“*. Že se pin založil, pozná klient z `zpatecni_pin` v odpovědi
   `pin_vstoupit.php`; mezi stránkami si to předá přes `sessionStorage`.
2. **Mapa rovnou z okna pinu** (`hra/api/mapa_z_pinu.php`, tlačítko „Nahrát mapu odsud…“ v obou oknech
   pinu). Obrázek → nová zónová mapa pojmenovaná podle pinu → pin na ni ukazuje. U existujícího pinu se
   napojí hned v databázi, u nového se předvybere v „Cílová mapa“ a napojí se při uložení. Mřížku si PJ
   doladí na nové mapě jako vždycky. Dřív se musela mapa založit ve správě světa a pin napojit zvlášť.
3. **Velikost značek míst** — posuvník v „Nastavení gridu“ (50–200 %). Piny se na mapě s mřížkou
   zvětšují a zmenšují spolu s mapou (to bylo hotové dřív); tímhle se doladí poměr k hexům a tokenům.
   Je to nastavení vzhledu, drží ho `localStorage` pro každou mapu zvlášť — **žádný nový sloupec
   v databázi**, takže to nečeká na ruční SQL.

Testy: rozšířený `tests/e2e/pin_vstoupit_api.js` (automatický zpáteční pin, zrušení, posunutí i s družinou,
hláška v prohlížeči) a nový `tests/e2e/pin_mapa_ui.js` (mapa z pinu, práva, velikost značek).

### Souboj — nový průběh, kroky 1–7 a telefon (2026-11-13, hotovo)

Uživatel po kroku 0: *„dodělej to celé“*. Postavený je celý schválený návrh (plátno níž). **Bez změny databáze.**

**Jak se teď souboj ovládá** (`assets/js/souboj.js`, styly `.boj-*`, `.bk-*` v `assets/css/mapa.css`):

* **Pruh pořadí nahoře** (`#bojPoradi`): kolo a půlka, tvorové v pořadí s tečkami akcí, zvýrazněný ten, kdo je na tahu;
  klepnutí na tvora na něj posune mapu. **Hráč vidí pořadí jen u družiny** — u nepřátel jen „nepřátelé ×N“
  (kolik jich na mapě vidí) a konkrétního nepřítele až ve chvíli, kdy hraje. Iniciativa a úmysly nestvůr zůstávají
  tajné, jak to bylo. PJ má v pruhu nabídku **„PJ ▾“**: Přeskočit tah, Upravit akce (−1 / +1 / ×2 / ½ / zrušit slib),
  Začít bez čekání, Kdo bojuje…, Ukončit souboj.
* **Jedna karta dole** (`#bojKarta`) říká, co se po mně právě chce. Výzvy k hodu (`.vyzva-panel`) jsou na stejném
  místě a karta se pod nimi schová — dole je vždy jen jedna věc.
* **Postranní panel souboj neřídí** a při zahájení se už sám neotvírá (`raid_panel.js`); zůstal jako přehled tvorů.

**Krok 1 + 2 — ohlášení akce a iniciativa jsou jeden krok.** Hráč: čtyři dlaždice (Útok / Střelba / Kouzlo / Pohyb)
a tři malé (Čekám / Utíkám / Něco jiného), pod nimi složení hodu štítky („k6 · Kouzlení · celkem +1“) a „Hodit kostkou“
(nebo „Hodil jsem u stolu…“). **Cíl se neohlašuje**, pole „útoků za 2 kola“ hráč nemá (bonus povolání se počítá sám).
Střelba je zamčená, když postava nemá střelnou ani vrhací zbraň, Kouzlo, když nezná žádné kouzlo. PJ: řádek na skupinu
(stejné nestvůry = jeden hod), nestvůra bez ohlášené akce útočí, „Hodit za všechny“; postava hráče je u PJ jen
„čeká se na hráče“ s „Hodit za něj…“. **Fáze PJ neodklikává:** `souboj_hod.php` s příznakem `auto` jde hodit už ve fázi
úmyslů a po hodu posledního účastníka začnou tahy samy; `umysl_nastavit.php` dovolí hráči měnit akci, dokud nehodil.
**Od 2. kola je akce z minula předvybraná** (`dracak_vtt_souboj_nove_kolo` už úmysl nemaže) — stačí hodit.
Náhled složení hodu posílá server (`ini_nahled` u účastníka, `dracak_vtt_souboj_ini_nahled`).
*Rozdíl proti knize, o kterém uživatel ví:* kniha říká „vyjádřit se postupně“ a pak házet; tady může hráč akci změnit
i poté, co hodil někdo jiný. Úmysly a hody nestvůr hráč nevidí, takže z toho výhodu nemá.

**Krok 3 — tah.** Klepnutí na nepřítele = cesta k němu + útok zbraní v ruce, na volné místo = přesun, do vlastního
těla = otočení (logika z `docs/navrh-tahu-v2.md` beze změny). Karta ukáže jen plán („Krok a útok → Goblin · 1 akce ·
Cep · 1 hex“) a jedno tlačítko pojmenované podle toho, co udělá (Útok / Jít / Otočit); jemné úpravy (↩ bod, ↶ ↷,
bez útoku, z místa) jsou malé ikony pod tím. Dál **„v ruce: X ▾“** (nabídka zbraní u sebe; výměna stojí akci a je to
u každé položky napsané předem), **Kouzlo**, **Jiné ▾** (čekání, obrana, obrana a krok, jiná činnost, držet čelo)
a **Konec tahu**. Když PJ sleduje tah postavy hráče, má jen malou kartu s „Hrát za něj“.
*Mapa bez hexů:* karta řekne, že tu pohyb a útok vyhodnotit nejde, a v „Jiné“ zůstává prosté odečtení akce
(rozhodnutí uživatele: souboj na mapě bez hexů nebude; starý seznam 14 tlačítek je pryč).

**Krok 3b — kouzlo.** „Kouzlo“ → dlaždice kouzel postavy s cenou v mazích a počtem akcí (nedostupná zašedlá
„nemáš dost“), → cíl klepnutím na mapě nebo „Na sebe“ → „Seslat“. Na mapě se kreslí **dosah kouzla, jen když ho kniha
udává číslem** (`dosah_sahy`); u kouzel se slovním dosahem kruh není — nehádám ho. Dosah i výhled dál hlídá server
a chybu vypíše na kartě. Kouzlo „ihned“ se místo seslání **přidá k další akci** (štítek na kartě tahu). Trvání a cena,
kterou hra z knihy nepozná, jsou pod „Upřesnit trvání a cenu“; PJ má navíc „Kouzlit bez seznamu“ pro nestvůry.
Rozdělané kouzlo na víc akcí: „Kouzlit dál“ / „Přerušit“.

**Kroky 4 a 5 — hody.** Karta výzvy má nadpis, co se po mně chce („Hoď na útok“, „Goblin na tebe útočí“), **číslo je
vidět rovnou** („4 ÚČ Cep“, opravy jako štítky) a jedno tlačítko „Hodit kostkou“. Hráč nic nevyplňuje. Obrana: dvě
velké volby **„Bráním se · zbraní · stojí akci“ / „Nebráním se · jen zbrojí · −3“** a hází se v obou případech
(to už pravidla v kódu dělala, jen to teď karta říká nahlas). PJ má úpravy čísel pod „Upravit čísla…“ — rozbalí se
samy, když nějaké číslo chybí. Nestvůry brání PJ. Odmítnutý hod se vypíše na kartě (dřív jen v logu).

**Krok 6 — výsledek** je krátká hláška pod pruhem pořadí, zmizí sama (hráč 4,5 s, PJ 9 s), nic se nezavírá;
animace z 2026-11-11 zůstává.

**Telefon:** karta sedí nad lištou nástrojů, dlaždice ve dvou sloupcích; lupa a tlačítko „kdo je na tahu“ se za
souboje schovají (jinak ležely přes „Konec tahu“), házecí místo se stěhuje nahoru vedle pruhu.

**Co z nákresu není přesně tak:** (1) hráč nevidí v pruhu nepřátele jednotlivě s otazníkem v pořadí — viděl by
tím jejich iniciativu, kterou má mít tajnou; (2) dosah kouzla jen u kouzel s číselným dosahem; (3) kouzla „ihned“
k obraně mají v kartě obrany pořád starší blok s rozbalovacími poli (ukazuje se jen tomu, kdo takové kouzlo zná).

**Testy:** nový `souboj_karta_ui.js` (48 kontrol), přepsané `souboj_ui.js` (celý průchod PJ + hráč, dvě kola),
`hex_tah_ui.js`, `kouzleni_ui.js`, `kouzleni_plan_ui.js`, `zbrane_mapa.js`.

### Souboj, krok 0 — nové okno „Zahájit souboj“ (2026-11-12, hotovo)

První postavený krok schváleného návrhu (plátno níž). Uživatel: *„schváleno, začni stavět krok 0“*.

* **Okno** (`hra/mapa.php` `#modalSouboj`, `assets/js/souboj.js` `otevritZahajeni` / `prekresliSouhrn`, styly `.sz-*`):
  nahoře souhrn stran („Družina · 2: Hrun, Sindor“, „Nepřátelé · 3: Goblin ×3“), seznam tvorů se zaškrtávátky je
  sbalený pod **„Upravit, kdo bojuje…“** (při úpravě účastníků za boje je rozbalený rovnou). Souhrn i stav voleb se
  odvozují ze seznamu, nic se nedrží dvakrát.
* **Překvapení** = čtyři tlačítka (Nikdo / Družina / Nepřátelé / Obě strany) místo rozbalovacího pole.
* **„Kdo má zbraň v ruce?“** je samostatná otázka, nezávislá na překvapení. Výchozí: všichni. Tlačítko strany
  přepne všechny její postavy, jednotlivce jde doladit v seznamu. Volba se ukazuje jen u strany, kde je aspoň
  jedna postava — **nestvůry zbraň netasí** (útočí tím, co mají v bestiáři), takže by tlačítko nic nedělalo.
* **Server** (`hra/api/souboj_start.php`, nový volitelný údaj `zbran` u účastníka): kdo má prázdné ruce a má zbraň
  mít, vytasí **bez akce** zbraň, kterou má **u sebe** (nejvyšší ÚČ tváří v tvář; z batohu nic); kdo už drží, drží
  dál; odškrtnutý zbraň zasune k tělu. Bez údaje se nemění nic (starší volání). Co se stalo, dostane **jen PJ**
  v odpovědi a vypíše se mu do logu — do události se to nepíše, hráči by viděli výzbroj cizích postav.
* **Pravidla:** domácí pravidlo zapsané do knihy jako homebrew — `content/pravidla-hrac.html` **b93093–b93094**
  (sekce h1606, hned za b10619, které říká, že vytasení stojí akci).
* Bez změny databáze. Testy: nový `tests/e2e/souboj_start_ui.js` (30 kontrol), upravený `souboj_ui.js`.

**Další kroky:** hotové, viz „Souboj — nový průběh, kroky 1–7 a telefon“ výš.

### Malá karta postavy: jeden klik = karta, dvojklik = deník (2026-11-12)

Zadání uživatele: *„když kliknu na token, rovnou se mi ukazuje full deník. to bych chtěl, aby se vyvolalo buď na
dvouklik nebo až na kliknutí tlačítka deník. na jednoklik bych chtěl jen tiny deník, kde bude jen info životy,
mana, uč oč… zrušit dialog buď křížkem nebo klikem mimo.“* Mění rozhodnutí z 2026-11-10 (klik = rychlý deník).

* **Jeden klik na postavu** → malá karta u tokenu (`assets/js/postava_mini.js`, styly `.pmini` v `mapa.css`):
  jméno, povolání a úroveň, pruh životů, pruh magenergie (jen kdo ji má), **ÚČ se zbraní v ruce** (bez zbraně
  „–, beze zbraně“) a **OČ**. Jen ke čtení. Čísla dává `hra/api/postava_mini.php` z `dracak_vtt_souboj_bojovnik`,
  tedy totéž, s čím počítá souboj. Smí PJ a vlastník postavy (cizí 403).
* **Plný (rychlý) deník** → dvojklik na token (dva kliky bez tažení do 400 ms, `jeDvojklikTokenu` v `mapa.js`),
  tlačítko „Deník“ v kartě, nebo „Rychlý deník“ v kontextovém menu. Na telefonu spolehlivě tlačítkem.
* **Zavření karty**: křížek, klik mimo, Esc. Klik na jiný token ji přepne / zavře.
* **Je-li okno deníku už otevřené**, klik ho jen přepne na kliknutou postavu jako dřív (režim „podle výběru“) —
  karta by vedle něj byla dvojmo.
* Okno tvora u nestvůr (PJ) se nemění.

Testy: nový `tests/e2e/postava_mini_ui.js` (30 kontrol), upravené `denik_dock.js` a `efekty_ui.js` (deník se v nich
otevírá dvojklikem). Bez změny databáze.

### Souboj — nový průběh, návrh (schválen 2026-11-12; postaven celý)

Zadání uživatele: *„potřebuju zrevidovat souboj. přijde mi to strašně složitý… musí to fungovat úplně samo,
respektive aby to udělalo jen výzvy k hodům, o zbytek se postarají animace… jen nahlásím jakou akci budu dělat,
podle toho se mi přičte iniciativa. potom už jen vybírám cíl a kouzlo.“* a *„pojďme udělat krok za krokem vzhled
souboje, ať nefabulujeme“*.

**Plátno s návrhem:** https://claude.ai/artifact/3ojke53iFM7Utts6MgXiX6 — deset nákresů (0 zahájení PJ, 1 co budu
dělat, 2 hod na iniciativu, 3 můj tah, 3b kouzlo, 4 hod na útok, 5 obrana, 6 výsledek, 7 pohled PJ, telefon).
Zdroj nákresů je v plátně, ne v repu; uživatel do něj píše připomínky — před úpravou ho přečíst (`Artifact read`).

**Co návrh mění proti dnešku** (dnešek = `assets/js/souboj.js`, postranní panel se třemi záložkami fází):

* Postranní panel Souboj mizí. Zůstávají tři stálá místa: **pořadí tahů nahoře**, **jedna karta dole** (co se po
  mně právě chce), **mapa** (výběr cíle).
* **Ohlášení akce** = jedno klepnutí na dlaždici (Útok / Střelba / Kouzlo / Pohyb + Čekám / Utíkám / Něco jiného),
  u každé je vidět změna iniciativy. **Cíl se při ohlášení nevybírá** a pole „útoků za 2 kola“ mizí (bere se
  z postavy). Od 2. kola je předvybrané „stejně jako minule“.
* **Iniciativa**: jedna výzva s jedním tlačítkem, bonusy jsou vidět jako štítky a přičtou se samy. Kolo začne
  samo, jakmile hodí poslední (dnes tlačítka PJ „Otevřít hody“ a „Začít kolo“).
* **Tah**: klepnu na nepřítele = útok zbraní, kterou držím (bez zbraně = beze zbraně), klepnu na místo = přesun;
  potvrzení jedním tlačítkem u cíle. Čelo se natočí samo k cíli. Karta s výpisem „Otočení celkem 0° z 270°“ mizí.
* **Kouzlo**: vyberu kouzlo ze seznamu svých (s cenou v mazích, nedostupná zašedlá), pak klepnu na cíl v dosahu.
  Dnešní ruční pole „kol / magy / akcí“ mizí.
* **Hody**: výzva „Hoď na útok / na obranu“ s jedním tlačítkem, čísla z deníku, hráč nic nevyplňuje. Úprava čísel
  zůstává jen PJ a je schovaná.
* **Výsledek** nemá kartu k zavírání — číslo nad tokenem, pruh životů, krátká hláška, další na tahu sám.
* **Nestvůry** hraje PJ stejnými kartami jako hráč (původní návrh „hází samy“ uživatel odložil na další verzi).
* **Zbraně na začátku boje**: výchozí je, že zbraň z deníku mají v ruce všichni; PJ odklepne, koho boj zastihl beze zbraně.
  *Pozor — to je domácí pravidlo uživatele, kniha říká, že vytasení stojí akci (h1606); až se bude stavět, zapsat
  ho do pravidel jako homebrew.*

**Rozhodnuto uživatelem 2026-11-12:** (1) **nestvůry hraje PJ** — ohlásí, hází i brání za ně; plná automatika
„až bude VTT hotové“. (2) **Obrana: vždy hráč** — volí „bráním se / nebráním se“ a hází v obou případech.
(3) **Mapa bez hexů v souboji existovat nebude** — seznam 14 tlačítek (`AKCE_PROSTE` v `souboj.js`) při stavbě zmizí.
**Zapracované komentáře z plátna:** v pořadí tahů hráč vidí jen viditelné nepřátele; u hodu je útočné / obranné
číslo vidět rovnou; výměna zbraně v tahu = přezbrojení za akci (nabídka to říká předem); **překvapení a „zbraň
v ruce“ jsou dvě oddělené otázky** — výchozí je, že tasili všichni včetně překvapených (družina to tak hraje:
„v jakési bonusové neviditelné akci všichni vytasili“), PJ jen odklepne stranu / jednotlivce zastižené beze zbraně.
**Plátno schváleno 2026-11-12**, staví se po krocích odshora. **Další krok po schválení:** stavět po krocích odshora
(0 → 1 → 2 …), server se z velké části nemění — jde hlavně o `assets/js/souboj.js` a markup v `hra/mapa.php`.

### Animace souboje (2026-11-11)

Zadání uživatele a jeho upřesnění k návrhu (deska „Souboj — kdo je na tahu a animace“):

* **Pohyb** se přehrává plynule: otočka → přesun → dotočení, **0,7 s na celou animaci** („zkusme 0,7,
  uvidíme“). Animuje se jen CIZÍ pohyb — vlastní tažení myší je přímé.
* **Kdo je na tahu** se hlásí krátkým pruhem nahoře. Mapa se **nikdy necentruje sama** („na mobilu by
  to bylo peklo“); místo toho je vpravo dole tlačítko se jménem tvora, které na něj mapu posune.
* **Útok**: zblízka máchnutí u cíle, na dálku letící střela. Pozná se to ze **vzdálenosti tokenů**
  (do 1,5 sáhu zblízka), protože pravidla znají jen „útok“ — žádný zvláštní druh akce pro střelbu.
  Útočník se nadechne, zasažený cukne.
* **Kouzlo** letí barvou svého **oboru magie** (`obory_magie`, osm barev v `BARVY_OBORU`). Uživatel
  uvažoval o barvách podle povolání a sám si odpověděl, že to nesedí („chodcova kouzla nebudou vždy
  zelená“); obor magie je jediné dělení, které pravidla nabízejí a které u Ohnivé koule i u psychického
  kouzla dopadne správně. **Pozn.: „stínová“ barva chybí proto, že pravidla obor „stínová magie“ nemají** —
  kdyby ji chtěl, je to rozhodnutí o datech, ne o kódu. Kvůli animaci posílá `kouzleni_stav` navíc
  `obor_id` a `cil_token_id` (`includes/vtt_kouzleni.php`, `dracak_vtt_kouzlo_obor()`).
* **Vypínání**: zaškrtávátko „Animace souboje zapnuté“ v administraci světa (sloupec `svet.animace`,
  migrace **0120**, ruční SQL **blok M**). Dokud sloupec v databázi není, animace běží a přepínač je
  neaktivní — nic se tím nerozbije.
* **Log**: plný vidí jen PJ. Hráči `hra/api/udalosti.php` z události `souboj_vysledek` vystřihne čísla
  (útok, obrana, zranění, životy) a přidá `jen_shrnuti` — hráč se dozví, kdo na koho a jestli trefil.
  V databázi zůstává událost celá, takže PJ ji vidí i zpětně. *(Životy na tokenu nestvůry tím dotčené
  nejsou — jestli má hráč vidět i ty, je samostatné rozhodnutí.)*

Kód: `assets/js/souboj_animace.js`, napojení v `assets/js/mapa.js`, styly v `assets/css/mapa.css`,
markup v `hra/mapa.php` (`#bojAnimace`, `#bojOznam`, `#bojCentr`). Testy `tests/e2e/souboj_animace_ui.js`
(15 kontrol včetně vypnutých animací) a rozšířený `tests/e2e/zranitelnost_boj_api.js` (log hráče bez čísel).

### Vzhled tokenů a pinů podle mřížky (2026-11-11)

Čtyři stížnosti uživatele z 2026-11-11, všechny opravené:

* **Rámeček tokenu kopíruje mřížku** — kolečko na čtvercové mapě, šestiúhelník na hexové. Řídí to třída
  `mrizka-hex` / `mrizka-ctverec` na `<body>` (`oznacitMrizku()` v `assets/js/mapa.js`), takže to CSS vyřeší
  jednou pro všechny tokeny. Na hexu `clip-path` ořízne i rámeček, proto obrys dělá podložka `::before`
  o 2 px větší.
* **Token je menší než buňka** (0,76 místo 0,82 násobku na hexu), aby se sousedé nelepili k sobě.
* **Tvor na víc hexech je jeden slitý tvar** — hexy se vyplní bez obrysu a obtáhne se jen vnější hranice
  (hrana, za kterou další hex té bytosti není). Dřív měl každý hex vlastní obrys, takže drak vypadal jako
  poskládaná kolečka, a kolečko s iniciálou přes celou bytost z něj dělalo „obří kruh“. Kolečko je teď jen
  malý štítek se jménem uprostřed (`--dp`), životy a efekty se drží jeho, a šipka směru u takového tvora
  zmizela úplně — čelo je vyznačené přímo na obrysu těla.
* **Pin se na mapě s mřížkou zvětšuje a zmenšuje s mapou** (`body.pin-v-mape`, velikost `--pin-d` z buňky),
  aby zůstal v poměru k tokenům. Na mapě bez mřížky (svět) si drží stálou velikost na obrazovce jako dosud.

### Okno deníku neschovává token, na který se kliklo (2026-11-11)

Stížnost: „kliknutí na token hráče se mi otevře tak, že není vidět samotný token.“ Okno teď při otevření
z tokenu uhne k tomu okraji obrazovky, který je od tokenu dál (`uhniTokenu()` v `assets/js/denik_dock.js`) —
ne jen „vedle tokenu“, aby klik na souseda okno nezachytil. A když vpravo stojí otevřený postranní panel,
okno se pod něj neschová (`uhniPanelu()`, volá se i z `otevritSpravovat`).

### Světlo se zapaluje u předmětu, ne ručně na tokenu (2026-11-11)

Část D návrhu `docs/panel-tokenu-navrh.md`. Dosud PJ klikal „Pochodeň (6 sáhů)“ v panelu tokenu, přestože
postava pochodeň fyzicky nesla v inventáři — dvě místa pro jednu věc a dalo se na to zapomenout.

Zdroj světla se pozná podle **kategorie vybavení „Svícení“** v katalogu (ne podle názvu — ten se v homebrew
liší): pochodeň 6 sáhů / 3 směny, lucerna 8 sáhů / 24 směn. Olej a křesadlo samy nesvítí, akci nedostanou.
V inventáři v deníku je u nich tlačítko **Zapálit / Zhasnout** (`hra/api/svetlo_predmet.php`), které rovnou
nastaví světlo na tokenu postavy; deník si při načtení přečte, jestli už něco hoří, takže tlačítko ukazuje
správný stav i po obnovení stránky. Hořící předmět je v inventáři žlutě. Práva jsou stejná jako u životů:
vlastník postavy nebo PJ světa.

Ruční nastavení světla na tokenu **zůstává** — pro nestvůry, obecné tokeny (ohniště, svítící socha, kouzlo
Tma) a opravy.

Pozn. k číslům: ceník u pochodně uvádí „2 směny“, aplikace počítá 3 (hodnota tam byla už před tím, než se
světlo přesunulo k předmětu). Rozpor je zapsaný v `docs/kontrolni-seznam-neuplnych-mist.md`; mechanika se
kvůli neshodě jednoho čísla neodkládá (pravidlo uživatele), PJ může na tokenu hodnotu přepsat.

Test `tests/e2e/svetlo_predmet_ui.js` (tlačítko jen u toho, co svítí; zapálení i zhasnutí se propíše do
tokenu; stav přežije načtení stránky; hráč smí svou postavu, cizí ne; předmět, který postava nemá, server
odmítne).

### Zranitelnost nestvůry konečně počítá i boj (2026-11-10)

Data o tom, čím jde nestvůru zranit, jsou v databázi od začátku (1559 vazeb `nestvura_zranitelnosti`) a od
2026-11-10 je vidí PJ v okně tvora. **Boj s nimi ale nepočítal vůbec.** Teď ano:

* Fyzický útok patří vždy do skupiny **B „obyčejná zbraň“** — podle b17731 sem kniha řadí i ránu beze zbraně,
  pád z výšky a pařáty, takže každý útok, který hra umí vyhodnotit, je B. (b17748 ještě upozorňuje, že
  zranitelnost nestvůry není totéž co kategorie jejího vlastního útoku.)
* Má-li nestvůra u B **násobek** („B ½“, „B 2“ — v datech 103 takových vazeb), zranění se jím vynásobí
  a **zaokrouhlí nahoru** (b17753). Nikdy neklesne pod 1, dokud zásah prošel obranou.
* **Nemá-li nestvůra B vůbec**, zranění se *nenuluje samo* a PJ dostane varování — hra totiž sama nepozná,
  že útočník drží kouzelnou nebo stříbrnou zbraň.
* **Blok L (připravený, čeká na ruční SQL):** předmět dostane pole „skupina útoku proti nestvůrám“
  (`predmety.zranitelnost_skupina`, prázdné = B). PJ ho u konkrétní zbraně vybere v editoru vybavení a boj
  pak bere skupinu podle držené zbraně (`dracak_vtt_utok_skupina()`), ne natvrdo B. Dokud sloupec není,
  nic se nemění (příznak `zbran_skupina` v `dracak_vtt_tokeny_funkce()`). Kdo který kus katalogu označí za
  kouzelný, zůstává na PJ — kniha to píše v popisu konkrétních kusů, ne jako parametr, takže se to nehádá.
* Nestvůra bez vyplněných zranitelností se chová přesně jako dřív — žádná změna, žádné hlášení.
* Výsledek jde **jen PJ**: z veřejné události se vyndává (b17728 — „Nesdělují hráčům hned na začátku boje,
  čím je nestvůra nezranitelná“).

Kód: `dracak_vtt_nestvura_zranitelnost()`, `dracak_vtt_zranitelnost_nasobek()`,
`dracak_vtt_zranitelnost_uprav()` v `includes/vtt_nestvury.php`; použití v `includes/vtt_souboj_utok.php`;
zobrazení v kartě výsledku `assets/js/souboj.js`. Test `tests/e2e/zranitelnost_boj_api.js` (půlení i zdvojení
se zaokrouhlením nahoru, chybějící B jako varování bez nulování, nestvůra bez dat beze změny, hráč se nic
nedozví) — na konci si vrátí zranitelnosti zkoušené nestvůry do původního stavu.

### Tabulka událostí se konečně zkracuje (2026-11-10)

`svet_udalosti` je deník všeho, co se ve světě stalo (hod, pohyb, efekt, tik kola) — a od začátku se jen
plnila, nic ji nikdy nezkracovalo. Při pravidelném hraní to roste donekonečna a na polling jde čím dál víc dat.

Úklid je v `dracak_vtt_udalosti_uklid()` (`includes/vtt.php`) a spouští se **opportunisticky přímo při zápisu
události**, průměrně jednou za 300 zápisů, nejvýš 200 řádků naráz — cron na tohle hosting nemá a zdržovat
souboj velkým mazáním nejde. Pravidla: ve světě vždy zůstane posledních **5000** událostí a **nic mladšího
než 2 dny**, strop má přednost před stářím. Čísla vycházejí z nejhlubšího zpětného čtení v kódu — 200 událostí
mapy od posledního `souboj_start` (`includes/vtt_zbrane_u_sebe.php`) a 80 posledních `akce_provedena`
(`includes/vtt_souboj_utok.php`) — takže na co se v souboji kouká, se nikdy nesmaže.

Test `tests/unit/udalosti_uklid.php` (celý v transakci, na konci rollback): smaže se přesně přebytek nad
stropem a nic víc, čerstvé události se nemažou ani nad stropem, cizí svět ani svět pod stropem se nedotkne,
prázdný svět nespadne.

### Jak daleko jde zautomatizovat kouzla a schopnosti — rozbor (2026-11-10)

Otázka uživatele: *„zkontroluj, jestli můžeš kouzla a schopnosti ještě více zautomatizovat. mnoho jich
automatických není.“* Rozbor nad celou databází:

| Co | Celkem | Má navázaný efekt |
|---|---|---|
| Kouzla | 699 | 138 |
| Zvláštní schopnosti a dovednosti | 349 | 19 |
| Lektvary | 91 | 78 |

Efekty samotné: 13 `auto` (boj vyhodnotí sám), 8 `cast`, 63 `pj` (jen štítek), 35 `past`, 7 `ceka`.

**Kde automatizace NENÍ a proč.** Engine strhne životy jen tehdy, když je k položce navázaný efekt
„Poškození“ / „Léčení“; kostky v tabulce `kouzla` samy o sobě nedělají nic. Takových kouzel — kostky bez
efektu — je **95**. Prošel jsem je jedno po druhém a navázat „Poškození“ šlo jen u **dvou** (migrace 0119,
Řetězový blesk b9717 a Zmrzlá země b5380). U zbytku kostky neznamenají „zranění cíle“:

* škálují podle velikosti cíle (Sloup ohně: A0 = 1k6 … E = 6k6),
* platí pro okolí, ne pro cíl (Zvukový výboj: 1k4 kolemstojícím, 1k10+5 cíli),
* jsou bonusem zbraně nebo útoku (Ledové kopí, Rampouch, Ledová tvorba-zbraň, Rychlý úder),
* zraňují útočníka, ne cíl (Ledová zbroj, Fénixův plášť),
* zraní **nebo** vyléčí podle povahy cíle (Paprsek, Vlna moci, Napuštění mocí, Svatyně, Pramen),
* mají hod proti pasti (Živá bomba, Chlad hvězd, Smrtící déšť, Tornádo, Zemětřesení),
* působí každé kolo, ne jednorázově (Požár, regenerace),
* jsou kostky vyvolané bytosti, ne kouzla (Stínový válečník, Stínový střelec),
* nebo nejsou zranění vůbec (Melenina krása = hod 1k6 na šanci, Zabij = součet životaschopnosti,
  Ochrana múz = snížení zranění).

Navázat jim „Poškození“ plošně by byl odhad mechaniky — přesně to, co `CLAUDE.md` zakazuje a co se hůř opravuje
než chybějící efekt.

**Čím je automatizace zablokovaná doopravdy.** Nechybí data (katalog efektů má u každého vyplněné `cil`,
`hodnota_vzorec`, `trvani` a často i přesnou citaci) — chybí engine. Pro zbývající efekty by byly potřeba
tyhle druhy složek (`efekt_slozky.druh`), které zatím neexistují:

1. **procentní odolnost vůči druhu zranění** (7 efektů: pět drahokamů, Chladné vody, Pouštní vítr) —
   vyžaduje, aby útok vůbec věděl, jakého je druhu (oheň / chlad / jed / bodné / drtivé). To dnes nikde není
   a je to největší chybějící kus.
2. **plošná změna přijatého nebo způsobeného zranění** (Aura Ochrany −1, Aura Bolesti +1, Nehmotnost Jadeitu
   −1k6) — menší zásah do stejného místa v kódu.
3. **bonus k vlastnosti** (Obří síla +5 Síla, Polichocen +1/+2, Wolfrikův elixír) — vlastnosti se dnes do
   bojových čísel nepropisují, takže samotná složka by nestačila.
4. **dosah světla** (Aura Světla) — token už světlo umí, chybí jen propojení z efektu.
5. **změna třídy velikosti** (Zmenšení).

Zbytek `pj` efektů je naopak **správně** neautomatický: „Drak splní žádost“, „Šifra rozluštěna“, „Klidná mysl“
a podobné nemají co počítat.

**Co se udělalo hned:** efekt u tvora už neukazuje jen název. Vedle jména je značka ⚙ / ◐ / ✋ / ❓ (kolik
z efektu vyhodnotí hra sama) a pod ním řádka, co efekt dělá — `efekty.cil` + `efekty.hodnota_vzorec` plus
rozpis složek (`dracak_vtt_efekt_ucinek_text()` v `includes/vtt_efekty.php`, zobrazení v
`assets/js/tvor_okno.js`). U efektů se značkou ✋ je to jediné, podle čeho se PJ u stolu rozhodne, takže to
nesmí být schované jen v nájezdu myší.

**Další krok, až na to bude čas:** bod 1 (druh zranění u útoku) — odemkne sedm hotových efektů naráz a je
předpokladem pro zranitelnosti nestvůr, které dnes okno tvora jen ukazuje, ale boj s nimi nepočítá.

### Stránka Změny databáze umí ukázat i data (2026-11-10)

Uživatel se zeptal „nevím, jestli je infra v DB, koukni tam“ — a neměl se kam podívat: blok K (migrace 0116/0117)
jsem přidal **bez řádků v kontrolní tabulce**, takže `nestvura_schopnosti.hodnota` ani `rasa_schopnosti.hodnota`
na stránce nebyly. Stejná chyba jako u bloku J, jen o úroveň výš.

Opraveno a rozšířeno: kromě sloupců má stránka nově **„Co z pravidel je v datech“** — očekávané číslo proti
skutečnému. U migrace, která přidá sloupec a pak ho naplní, je totiž ruční SQL jen půlka práce; obsah doplní až
„Spustit čekající změny“, a to z tabulky sloupců poznat nejde. Teď tam je: 16 nestvůr s infraviděním,
11 nestvůr, které infravidění nerozezná, a trpaslíkových 20 sáhů. `admin_migrace_ui` kontroluje i tenhle přehled
včetně toho, že po smazání řádku v datech ukáže ✗ (hlídač musí umět říct obě odpovědi).

Mimochodem se při tom našla past z `CLAUDE.md` v samotném testu: `admin_migrace_ui` volal `mysql` bez
`--default-character-set=utf8mb4`, takže dotaz s „Infravidění“ tiše nezasáhl nic. Doplněno.

### Export a import oblasti — HOTOVO (2026-11-09)

Zadání uživatele: *„vytvoř export import pro nové části mapy. Mělo by to vzít PIN/mapu a vše úrovňové pod ní.“*
Postaveno podle návrhu `docs/export-import-oblasti-navrh.md` (ten zůstává jako popis rozhodnutí a pastí).

**Co to umí:**
- **Oblast = mapa a všechno pod ní.** Hierarchie map v databázi není — skládá se z pinů (`mapa_body.cilova_mapa_id`),
  stejně jako „Mapa míst“ v liště. Sestup proto jde po pinech dopředu; mapa, kterou už máme, se nepřidá znovu,
  takže zpáteční pin oblast nerozšíří. Strop 200 map jako pojistka proti zacyklení.
- **Vyváží se** z informace o pinu („Vyvézt oblast…“, jen PJ) nebo přímo `oblast_export.php?mapa_id=`.
  Výsledek je **ZIP**: `manifest.json`, `data.json` a podklady map i obrázky tokenů v `uploads/`.
  `?nahled=1` vrátí jen souhrn, nic nestahuje.
- **Veze:** mapy, zdi, kresby, piny, tokeny nestvůr a vlastní tokeny, NPC i s výbavou, vlastními čísly a místem,
  čísla druhů nestvůr pro ten svět, obrázky a poznámky PJ navázané na ty mapy.
  **Neveze:** katalog pravidel (odkazuje se **jménem** — je všude stejný), postavy a hráče (oblast je místo, ne družina),
  provozní stav (mlha, log událostí, rozehraný souboj).
- **Import je dvoufázový** (`hra/svet_administrace.php` → „Oblasti ze souboru“): nejdřív **„Co je v souboru?“** ukáže,
  co přibude, co **v katalogu chybí** a která místa vedla pryč z oblasti; teprve pak jde **„Přidat do světa“**.
  Výměna souboru po kontrole povolení zase shodí. Bez téhle kontroly by import tiše vyrobil NPC bez pravidel
  a projevilo by se to až v boji.
- **Přečíslování:** soubor nese dočasná id (`mapa:1`, `npc:3`), zápis jde v pořadí závislostí a překladová tabulka
  drží staré → nové; celý zápis je v jedné transakci. Druhý import téhož souboru přidá **další kopii**, nic nepřepíše.
- **Bezpečnost:** ze ZIPu se berou jen cesty `uploads/mapy/…` a `uploads/obrazky/…` (žádné `../`), soubor musí projít
  jako obrázek (`getimagesize`), strop 16 MB na soubor; ukládá se pod novým náhodným jménem (`import_*.png`).
- Kód: `includes/vtt_oblast.php`, `hra/api/oblast_export.php`, `hra/api/oblast_import.php`,
  `assets/js/oblast_import.js`. Test `tests/e2e/oblast_api.js` (export z mapy i z pinu, práva, ZIP, kontrola,
  zápis, přečíslování pinů, přeskočení NPC s chybějícím druhem, druhý import, celý tok v rozhraní).
- **Žádná změna databáze** — import i export jedou nad tím, co už je.

**Doplněno po dotazech uživatele (2026-11-09):**
- **NPC postavená jako POSTAVA se vezou** (hostinský s plnou kartou, kapitán stráže). Poznají se stejně jako všude
  jinde v aplikaci: vlastníkem je **PJ světa** (nebo nikdo) → `strana = 'pj'` ve `vtt_mapa_stav.php`. Postavy hráčů
  se nevezou — družina patří hráčům, ne místu. Veze se karta (vlastnosti, životy, magy, peníze, zbroj/štít/zbraň
  jménem z katalogu), inventář, lektvary, kouzla i kontejnery s obsahem. Po importu je vlastníkem **PJ cílového
  světa** (sloupec `postavy.vlastnik_ucet_id` je NOT NULL, prázdný být nemůže).
- **Vývoz celého světa**: `hra/svet_administrace.php` → „Vyvézt celý svět…“ (`oblast_export.php?cely_svet=1&svet_id=`).
  Vezme **všechny** mapy světa, i ty, na které žádný pin nevede — ty by sestup po pinech vynechal.
- **„Svět -1“ za portálem funguje bez další práce**: mapa typu „světová mapa“ může být ve světě víc než jedna
  (`dracak_vtt_mapa_strom` bere jako kořen každou) a pin z ní nebo na ni je obyčejný přechod. Jiná dimenze je tedy
  jen další mapa — vyveze se jako každá jiná oblast a dá se přidat jinam.

**Zbývá, až na to přijde řada:** dopojení visících přechodů kliknutím (dnes se jen vypíšou a PJ pin upraví ručně),
výběr, co se má vzít (dnes vždy všechno pod mapou, nebo celý svět).

### Export a import oblasti — původní návrh (2026-11-09, teď už postavené výš)

Uživatel chce do budoucna: loď/přístav/portál do jiné dimenze, předatelný svět a možnost vzít **kus cizí tvorby** (quest pro novou oblast)
a napojit ho na svůj svět. **Rozhodl 2026-11-09, že se světy nenapojují — jeden svět to pojme** (souhlas i technicky: dimenze je jen
oblast, na kterou vede přechod). Pak se rozhodl, že je to velká úprava, která počká, a chtěl jen dokument.

**Návrh je v `docs/export-import-oblasti-navrh.md`** (co do oblasti patří a co ne — katalog pravidel se nevyváží, postavy a hráči taky ne,
provozní stav se zahodí; formát ZIP s obrázky; čtyři těžká místa — přečíslování, polymorfní `tokeny.entita_id`, odkazy ven z oblasti,
chybějící katalog u importu; pět kroků realizace, odhad několik dní).

**Do databáze se kvůli tomu záměrně nesáhlo** — dnes nic nechybí, odkazy jsou cizí klíče a sloupec přidaný „do zásoby“ by se skoro jistě
trefil vedle. Poznámka na později: `nestvura_instance.svet_id` a `lokace_mapa_id` (0115) jsou bez cizího klíče; dnes to nevadí, protože
aplikace mapy mazat neumí (`DELETE FROM mapy` není nikde v repu), ale s mazáním map by to bylo potřeba ošetřit.

### NPC jako bytost světa a správa tokenů — HOTOVO v kódu (2026-11-09, čeká na ruční SQL blok J)

Rozhodnutí uživatele 2026-11-09: **dvě úrovně NPC** (jedinečné s vlastními čísly × běžný kus se čísly druhu) a **postav to hned**.
Zadání ke správě tokenů (jeho slova): lišta **dole uprostřed** jako v Owlbear Rodeo, ne vlevo („tam je nástrojů až až“), se záložkami
„nový token | nevyužité | naposledy použité“; NPC se dá **navázat na místo** (pak se ukáže ve správě u té lokace) nebo vytvořit **bez místa**
jako přípravu na mapy, které ještě nevznikly; výroba **dávkou** („10× goblin“) se zaškrtávátkem „každý jedinečný“ a pak **drag and drop** na mapu;
**vyplněný deník dělá z kusu unikát**.

**Hotovo a otestované (migrace `0115`, ruční SQL blok J — bez něj se lišta nezobrazí a tokeny se přidávají jako dřív):**
- `nestvura_instance` má `svet_id` (NPC žije ve světě), `mapa_id` smí být prázdné (= v zásobě), `lokace_mapa_id` (místo, kam patří),
  `jedinecny` a `data` (vlastní čísla). Pozor na rozdíl: `mapa_id` = kde token **právě stojí**, `lokace_mapa_id` = kam NPC **patří**.
- `includes/vtt_npc.php` + `hra/api/npc_zasoba.php`, `npc_vyrobit.php`, `npc_smazat.php`, `npc_lokace.php`; strop dávky 50 kusů.
- Lišta `assets/js/token_bar.js` + `#tokenBar` v `hra/mapa.php` + CSS `.token-bar` (jen PJ). Čtyři záložky: Nový token / Nevyužité (filtr
  vše – z téhle lokace – bez místa) / Tato lokace (i to, co tam stojí) / Naposledy. Pokládá se **tažením prstem i myší** (Pointer Events,
  ne HTML5 drag&drop, ten na dotyku nefunguje), klepnutí chip „nabije“ a položí ho klepnutí do mapy.
- **Sundání tokenu NPC nemaže** — vrátí ho do zásoby i s jménem, čísly a výbavou (smazat nadobro jde křížkem v liště).
- NPC bez místa položené na mapu k té lokaci **přibude**; NPC navázané jinam si svoje místo nechá (je na návštěvě).
- Číslování kusů (Goblin, Goblin 2…) je nově **v rámci světa**, dřív v rámci mapy — zásoba je světová, jinak by na mapě skončili dva „Goblin 2“.
- Deník nestvůry: `jen_tento` uloží čísla jen tomu kusu a tím ho udělá jedinečným; u kusu, který už jedinečný je, se vždy ukládá jen jemu
  (jinak by úprava jeho čísel přepsala celý druh). Souboj bere u jedinečného NPC jeho vlastní čísla.
- `includes/migrace.php` nově pozná `MODIFY … NULL` (uvolnění sloupce) jako hotovou klauzuli — bez toho by se stránka Změny databáze
  na 0115 zastavila i po ručním provedení. ENUM/SET se dál řeší podle hodnot výčtu.
- Testy: `tests/e2e/npc_zasoba_api.js` (44 kontrol: práva, meze, místo, pokládání ze zásoby, návrat do zásoby, dvě úrovně NPC)
  a `token_bar_ui.js` (28 kontrol: záložky, výroba, filtry, tažení myší i prstem na tabletu, klepnutí, mazání, hráč lištu nemá).
  Ověřeno i **bez ručního SQL** (lišta se nezobrazí, `npc_vyrobit` vrací 409, starý způsob přidání nestvůry funguje).

**Ruční SQL blok J uživatel spustil 2026-11-09.** Stránka Změny databáze → „Co aplikace v databázi vidí“ u všech čtyř sloupců
hlásila ✗, ale **byla to chyba té stránky, ne databáze**: `admin_migrace.php` si seznam sloupců tahal z pevného výčtu tabulek
(`aktivni_efekty, mapy, tokeny, svet`), takže řádky pro `nestvura_instance` (0115) nemohly nikdy vyjít jinak než „chybí“.
Opraveno — seznam tabulek se bere přímo z `$ruckySql`, takže se to při přidání dalšího řádku nemůže znovu rozejít.
**Poučení (zapsáno i v `CLAUDE.md`): než se znovu podezírá uživatel nebo phpMyAdmin, ověř nejdřív kontrolní stránku samotnou** —
tady poslala uživatele dvakrát zbytečně spouštět SQL, které už dávno proběhlo.
Blok J je tím pádem proveden a `docs/sql-k-rucnimu-spusteni.sql` je prázdný. Nasazení kódu proběhlo (FTP Deploy běh 280, `9b3a650`).
Blok byl při té příležitosti přepsaný tak, aby začínal kontrolou J0 (v jaké databázi běžím, je tu ta tabulka) a ve špatné databázi
se sám přeskočil — to zůstává jako vzor pro další bloky.
Z cloud session na produkční web ani do jeho databáze nevidím (proxy blokuje `vtt.zvelebil.online`, WEDOS nepouští MySQL zvenčí).

**Nice to have (zadání uživatele 2026-11-09, nestavět teď):** základní interagovatelné tokeny — truhla, mrtvola apod. Dnes existují „vlastní
tokeny“ (typ `obecny`: NPC bez statistik, překážka, sud), ale jsou to jen značky bez obsahu a bez interakce. Truhla s obsahem by se dala
postavit nad už existující `kontejner_obsah` / `loot.php`.

### NPC ve světě, zátěž dat a loading — zjištění a návrh (2026-11-09, čeká na rozhodnutí uživatele)

Uživatel se ptal (2026-11-09), kam ukládat NPC a „deník bestií“, jestli stovky NPC na desítkách map nebudou problém na data a loading, a chtěl **tabulku všech NPC
ve světě** (včetně nepoužitých) v okně pro PJ vedle poznámek a měst. Dál do budoucna: svět reagující na akce hráčů a generátor výbavy/dovedností NPC podle druhu,
úrovně a bohatosti (sám to řadí až za MVP).

**Změřeno na lokální DB (ne na hostingu), 100 NPC na jedné mapě 2400×1800 s gridem 70 px:**
- `mapa_stav.php`: medián 13 ms, max 23 ms, odpověď **48 kB** (PJ) / 44 kB (hráč) — proti 3 tokenům je to hlavně nárůst objemu odpovědi, ne času.
- Tmavá mapa s mlhou a pochodní (scénář „město v noci“): `mlha_stav.php` medián 7 ms, max 9 ms pro hráče. Vidění po buňkách tedy při 100 tokenech **není** úzké hrdlo.
- Velikost dat: řádek tokenu i instance nestvůry jsou desítky bajtů. 50 map × 100 NPC ≈ 5 000 řádků, tedy **pod 1 MB** — zanedbatelné proti obrázkům map.
- Polling (`udalosti.php`) má index `(svet_id, id)`, takže ho velikost tabulky událostí nezpomaluje.

**Závěr k zátěži:** počet map nehraje roli — `mapa_stav.php` i `mlha_stav.php` čtou tokeny vždy jen pro jednu mapu (`WHERE mapa_id = ?`), neaktivní mapy se nenačítají
vůbec. Limit není počet map ani celkový počet NPC, ale **počet tokenů na jedné otevřené mapě**. Jediné číslo, které stojí za pozdější hlídání: těch 48 kB se stahuje
po každé události (pohyb tokenu), takže v městě se stovkou NPC a šesti hráči to na mobilních datech bude znát. Řešit až kdyby to v reálné hře drhlo (např. posílat
jen změněné tokeny) — ne teď.

**Dvě skutečné mezery, které se přitom našly:**
1. **NPC nemůže existovat bez mapy.** `nestvura_instance.mapa_id` je NOT NULL s cizím klíčem na `mapy`, takže NPC vznikne až položením tokenu na mapu. Požadovaná
   „tabulka všech NPC ve světě včetně nepoužitých“ dnes nemá kam.
2. **Deník nestvůry je na druh, ne na jedince.** `nestvura_staty_sveta` má klíč `(svet_id, nestvura_id)` — úprava čísel Goblina platí pro všechny gobliny ve světě.
   Jedinečné NPC s vlastními čísly (hostinský se jménem) dnes nejde udělat.

**Navržená cesta — uživatel ji 2026-11-09 schválil a je postavená, viz sekce výš:** NPC se stane bytostí **světa**, ne mapy — `nestvura_instance` dostane `svet_id` a
`mapa_id` smí být prázdné („existuje ve světě, není nikde postavený“); token na mapě na instanci jen ukazuje. Tím vznikne tabulka všech NPC ve světě, NPC jde
připravit dopředu bez mapy, jedinec může mít vlastní čísla (deník na instanci místo na druh), „NPC zůstane na mapě, když družina odejde“ platí dál (to funguje už
dnes — tokeny jsou vázané na `mapa_id`) a existující výbava (`nestvura_instance_vybava`), životy i loot fungují bez úprav. Je to zároveň předpoklad pro budoucí
generátor výbavy — ten musí mít kam generovaného NPC uložit. Cena: ruční SQL (`ALTER ADD svet_id` + `MODIFY mapa_id` na nullable, tedy phpMyAdmin pod admin účtem)
a úpravy asi na 23 místech v kódu, která dnes instanci dohledávají přes `mapa_id`.

**Rozhodnuto uživatelem 2026-11-09: dvě úrovně NPC.** (Otázka zněla:) jestli mají být NPC dvě úrovně (jedineční se jménem a vlastními čísly × bezejmenná masa „Městan“ sdílející
čísla druhu), nebo jedna. Doporučení: dvě úrovně, protože město s 50 měšťany nemá cenu ladit po jednom.

**Vyřazeno ze plánu (rozhodnutí uživatele 2026-11-09):** duplikace map („knihovna map“, blok C bod 16) — každá mapa a zóna má být unikátní, kopírování je k ničemu.
Řazení scén zůstává jako pohodlnost po MVP.

**Nalezeno mimochodem, operační riziko:** `svet_udalosti` (log událostí pro polling) se **nikdy nemaže** — žádné `DELETE` v celém repu. Roste s každým pohybem
tokenu navždy. Polling to nezpomaluje (index), ale záloha a velikost DB na hostingu ano. Opravit mazáním záznamů starších než N dní, až bude po zkušebním večeru.

### MVP před zkušebním večerem — přehled po frontách (2026-11-09)

**Zadání uživatele (2026-11-09):** nejdřív funkční MVP na všech frontách, teprve potom jemné ladění. Do dokončení MVP se nestaví leštění stínů, výkonové
optimalizace, drobné nálezy z revize ani kosmetika. **Definice MVP** = cíl ze sekce „Plán: náhrada Owlbear Rodeo“: VTT, které jde nasadit do ostré hry
(mapy, tokeny, souboj, deník, svět/cesta) — „začít testovat v živé hře, ne dokonalost“. MVP je hotové, až proběhne zkušební večer (`docs/zkusebni-scenar-prvni-hra.md`)
a nic z toho, co na něm drhne, nebrání hře.

Základ: plná regrese 2026-11-09 na čerstvě postavené DB prošla 60 z 61 sad; `unit/log_unit` padá jen kvůli pevné cestě `/home/user/Dracak-VTT/` v testu (repo je teď
v `/home/user/dracak-vtt`), kód je v pořádku. Značení: **(a)** funguje a má test, **(b)** funguje, ale neověřeno v reálné hře / na telefonu či tabletu, **(c)** chybí.
Zařazení vychází z kódu, testů a scénáře, ne z ověření na zařízení — to jsem v cloudu udělat nemohl.

**Doplněno 2026-11-09:** `log_unit` opraven (cesta z `__DIR__`). Nový test `dotyk_ui` (tažení tokenu, posun a pinch skutečnými dotykovými událostmi, telefon i tablet) prošel — zmenšuje (b) u mapy, ale skutečný tablet nenahrazuje. Měření `poll_zatez.js` (6 současných přihlášení, 40 s, PJ přesouvá token a mění životy): `udalosti.php` 165× a `mlha_stav.php` 136×, medián 4–5 ms, p95 ≤ 9 ms, žádné 5xx. Je to lokální PHP bez sítě a bez hostingových limitů, takže říká jen, že server s 6 hráči nezahlcuje; skutečná odezva WEDOSu se ukáže až na zkušebním večeru.

| Fronta | (a) funguje, pokryto testem | (b) neověřeno v reálné hře | (c) chybí |
|---|---|---|---|
| **Mapa a tokeny** | kamera, tokeny (vzhled, otáčení, výběr, kontextové menu), mlha, zdi, dveře, světlo a tma, piny, ping, kresby, raid panel, nahrávání obrázků (`hex_*`, `dvere_api`, `videni_api`, `svetlo_*_ui`, `raid_panel_ui`, `upload_api`, `xss_ui`) | tažení prstem, pinch, spodní lišta na skutečném tabletu / telefonu (test jen emuluje okno), polling při 6 hráčích, stíny na velké mapě v ostré hře | nic, co by blokovalo. Mimo MVP: zamčené a tajné dveře, hráč otevírá dveře, ikony efektů na tokenu |
| **Souboj** | iniciativa v2, akce a pohyb, útok a obrana, zbraně a vytasení, efekty, kouzlení (včetně „ihned“), boj ve tmě s postihy, souběhy (`souboj_api`, `ini_*`, `vyzva_api`, `zbrane_*`, `efekty_*`, `kouzleni_*`, `soubehy_api`) | celý souboj s živou družinou a hráčem na druhém zařízení; kouzlení je z 4.–6. 11., ještě nikdy ne v reálné hře | Boj beze zbraně: tabulky (str. 68–70) se v přepisu nedochovaly. **Neblokuje** — bez držené zbraně hra vezme první zbraň s ÚČ, jinak číslo doplní PJ; viz kontrolní seznam. Ztráta iniciativy ve tmě je jen postih (rozhodnutí čeká) |
| **Deník postavy** | založení postavy, inventář z katalogu, zbroj a štít, přepočet OČ / ÚČ / naložení, lektvary, kouzla, odpočinek a únava, dock na mapě (`denik_v2`, `denik_dock`, `inv_ui`) | deník na telefonu, doba načtení při velkém inventáři | figurína a sloty, Dovednosti, Společníci, připnutá kouzla (čekají na rozhodnutí uživatele). Deník nestvůry **před bojem** (čeká na jeho design); za boje funguje malý deník v panelu tokenu. Nic z toho první večer neblokuje |
| **Svět a cesta** | světy, pozvání a oprávnění, poznámky, piny, přechod mezi lokacemi, hodiny světa, pochod po mapě světa, kalkulačka cesty, povozy, jezdecká zvířata (`e2e_hra`, `druzina_*`, `poznamky_ui`, `pin_vstoupit_api`, `hodiny_sveta_api`, `pochod_cesta_ui`, `cesta_*`) | cesta s kočárem v ostré hře, propojení cesty s časem světa | automatické přenesení družiny po trase (jde ručně); odpočinek a spánek neposouvají čas světa. Neblokuje |
| **Administrace a provoz** | účty a role, Změny databáze, Kontrola nasazení, chybový log, záloha, kontrola zabezpečení, fuzzery (`admin_migrace_ui`, `log_ui`, `zaloha_ui`, `csp_api`, `fuzz_*`) | **obnova zálohy do prázdné DB na hostingu** (lokálně testovaná, na WEDOSu ne), Content-Security-Policy je zatím jen v režimu hlášení | nic. Subdoména `vtt` je kosmetika |

**Co blokuje MVP a čeká na uživatele** (jen věci, které jde udělat jedině jeho rukou):
1. **Zkušební večer** — PJ na počítači, hráč na tabletu / telefonu, zhruba hodina podle scénáře. Bez toho není u žádné fronty (b) ověřeno a nevíme, co ve skutečnosti drhne.
2. **Maximální magenergie u kouzlících postav** — v deníku postavy (PJ). Dokud není nastavená, postava kouzlí zadarmo, hra to jen hlásí do logu. Na zkušebním večeru by kouzlení bez magů nic neukázalo.
3. **Obnova zálohy** na hostingu (`docs/zalohy.md`) — jednou vyzkoušet, než se do hry vloží data, o která by byla škoda přijít.
4. *(Neblokuje první večer, ale potřebuji to později)* **foto stran 68–70 z knihy** (boj beze zbraně: citlivá místa, posun podle velikosti, tabulky boje beze zbraně a vícenásobných útoků), **design deníku nestvůry před bojem**, **rozhodnutí o ztrátě iniciativy ve tmě**.

**Nejmenší sada kroků k zkušebnímu večeru:**
1. (Claude) opravit pevnou cestu v `tests/unit/log_unit.php`, aby regrese byla celá zelená.
2. (Uživatel) v administraci zkontrolovat Změny databáze (má být „Všechno je aplikované“) a Kontrolu nasazení (všechny soubory sedí).
3. (Uživatel) nastavit maximální magenergii postavám, které budou kouzlit; vymazat chybový log.
4. (Uživatel) projít scénář kroky 1–20 a ke každému zapsat prošlo / drhne / nejde; krok 20 (kouzlení) je od 2026-11-09 ve scénáři.
5. (Uživatel) po hře poslat Claudovi nejnovější záznamy z chybového logu a seznam „drhne / nejde“.
6. (Claude) opravit jen to, co ve hře skutečně drhlo; až potom se vrátit k leštění (stíny, výkon, nálezy z revize).

### Další kroky bez rozhodnutí uživatele (kandidáty pro další sezení)

Stav k 2026-10-31: vše z dosavadního plánu (bod A–D, deník v2 kroky 1–3a, deploy, migrace z administrace, log, záloha, testy, fuzzery) je hotové a nasazené; 31 sad v `tests/` prochází
na DB postavené od nuly. Co lze dělat **bez** ptaní (vždy s testy, `tests/run.sh`):
- **Další oblasti fuzzingu:** (nahrávání obrázků je od 2026-10-31 pokryté testem `upload_api.js`; limit rozměrů je teď `dracak_vtt_obrazek_max_bodu()` podle `memory_limit`, strop 16 Mbodů — dřív 40 Mbodů, což by na hostingu spadlo na paměti; souběžné požadavky na životy, magenergii, lektvary a souboj pokrývá `soubehy_api.js`, JSON s hloubkou 100 000 a tělem 200 MB server odmítne čistě bez záznamu v logu). Zbývá: souběh zbylých míst, kde se čte a zapisuje stav (`efekt_pridat`, mlha, `rozbalit` kontejner), a zátěžový test pollingu při 6 hráčích.
- **Bezpečnost** (uložený XSS do všech uživatelských textů je od 2026-10-31 pokrytý testem `tests/e2e/xss_ui.js`, bez nálezu): přepnutí Content-Security-Policy z hlášení na vynucování (etapa 1 hotová 2026-10-31, viz Bezpečnost; čeká na pár dní bez hlášení v logu), kontrola, že každý
  výpis uživatelského textu je escapovaný (grep + test s `<script>` v názvech světa, postavy, tokenu, pinu, poznámky), `Secure`/`SameSite` cookie za proxy.
- **Funkce ze starších plánů, které NEJSOU hotové** (úplný soupis 2026-11-01 — dřív tu byla jen údržba a bezpečnost; funkční kroky z plánů A–E a fází cestování/deníku zůstávaly rozeseté v textu níž):
  - *Plán náhrady Owlbear — blok B:* (9) dosah pohybu mimo boj — **hotový (2026-11-03, viz „Dosah pohybu“ níž)**; (12) čitelné stavy tokenů — **uživatel 2026-11-02 pruh životů na samotném tokenu zatím nechce, chtěl panel s boxy (raid panel) — hotový, viz „Raid panel“ níž**; ikony efektů a únava/přetížení přímo na tokenu zatím ne.
  - *Blok C — svět a cesta:* (14) pochod po mapě světa — **v1 hotová (2026-11-03, viz „Pochod po mapě světa“ níž)**: měřítko mapy → měřidlo v mílích → kalkulačka cesty → čas světa a únava postavám; **trasa po více bodech hotová 2026-11-03** (Shift při táhnutí měřidla naváže další úsek na konec předchozího, odkaz do kalkulačky nese součet; trasa zůstane 25 s), zbývá automatické přenesení družiny po trase; (15) přechod mezi lokacemi — **hotový (2026-11-02, viz „Přechod mezi lokacemi“ níž)**; (16) knihovna map (duplikace, pořadí scén); (17) hodiny světa — **čas ve dni a hoření světel hotové (2026-11-02, viz „Hodiny světa“ níž)**, s cestou propojené (zápis cesty posune čas), s odpočinkem a spánkem zatím ne.
  - *Blok D:* (22) tablet a telefon na skutečném zařízení a (23) zkušební hra podle `docs/zkusebni-scenar-prvni-hra.md` — čeká na uživatele.
  - *Blok E — vidění a světlo:* základ je hotový (dveře, světlo, tma, infravidění). Zbývá: **zamčené a tajné dveře** (uživatel je bude potřebovat později), ~~hladký polygonový stín místo buněk~~ **hotový 2026-11-04 (světlo slábne plynule, zdi vrhají stín s polostínem, viz `docs/videni-a-svetlo-navrh.md` fáze 6; stín zatím jen od zdí; téhož dne přibylo slábnutí dohořívajícího světla a přepínač „Ukázat světlo a stíny“ pro PJ; 2026-11-05: kruh světla / zóny tmy se při tažení tokenu hýbe s ním v reálném čase a stíny se po položení přepočtou hned, výpočet stínů zrychlen 10–20× pro města se stovkami zdí)**, automatická ztráta iniciativy ve tmě, Lovcův Příkrov stínů, hráčem otevíratelné dveře (zatím jen PJ). Hotové: zóny Tma a Temnota, pět stupňů tmy a noční vidění.
  - *Boj:* „obrana a krok“ (kniha neříká, kdy se krok udělá — potřebuje rozhodnutí), útok na kouzlícího tvora (neviditelný tvor hotový, viz níž), čtyřstupňová škála hodu na dovednost (fatální neúspěch / totální úspěch — v přepisu knihy chybí, potřeba foto).
  - *Nestvůry:* deník nestvůry **před bojem** (zadání uživatele 2026-10-20; design navrhne uživatel), kořist, výbava z katalogu s dopočtem ÚČ/OČ ze zbraně a zbroje, inteligence/charisma/pohyblivost/vytrvalost jako hratelná data, kouzla nestvůr, automatický bonus k iniciativě podle „N× za kolo“.
  - *Cestování:* jezdecká zvířata **hotová (2026-11-03, migrace 0111: 43 řádků z h2057, DML)** — letecké a pozemní řádky se vedou jako samostatná zvířata se jménem podle knihy, „Ujede“ se nikde nepočítá, kalkulačka bere pohyblivost, nosnost a vytrvalost; zbývá Pegas/Gryf (označení „na zemi“ vypadá na špatném řádku, viz kontrolní seznam) a let jako pohyb; povozy: **hod kočího a dovednost Kočí hotové 2026-11-03** (kalkulačka cesty: šance úspěchu klusem se upravuje výsledkem Kočí, ukáže počet směn a šanci na cestu bez nehody a tlačítkem „Hodit řízení za celou cestu“ hodí k% za každou směnu; cval a tryskem se nepočítají, protože kalkulačka zná jen tempa chůze), zbývá otevřených kočárů a více povozů v jedné cestě; vnořené kontejnery; povoz není trvalá entita.
  - *Deník:* figurína a sloty, Dovednosti, Společníci, připnutá kouzla, historie změn postavy, deník cizí postavy pro ostatní hráče, přehled únavy a naložení také v panelu tokenu na mapě, ověření zbytku kapitoly Dovednosti na další propojení.
  - *Data z knihy:* v `docs/kontrolni-seznam-neuplnych-mist.md` je 288 nezaškrtnutých položek (chybějící tabulky boje beze zbraně, vícenásobných útoků, bonusů k iniciativě ze str. 78, poškozené pasáže) — potřebují foto nebo korekturu.
- **Kvalita:** projít poslední větší změny revizí (`/code-review`): omezení přihlášení, chybový log, záloha, kontrola nasazení, fuzzery.
- **Provoz:** `docs/zalohy.md` ověřit obnovu do prázdné databáze na hostingu (uživatel), měřit polling na skutečných zařízeních (uživatel), případně 304 / ETag u `udalosti.php`.
- **Deník v2:** zbývá figurína a sloty, Dovednosti, Společníci, připnutá kouzla — všechno potřebuje rozhodnutí o pravidlech nebo hromadná data (viz `docs/prototypy/denik-v2-predani.md`).

**Čeká na rozhodnutí uživatele (neřešit samo; 2026-11-04 řekl „zbylé body jen zatím zapiš“):** (0) **postava bez nastavené magenergie kouzlí zdarma** — hra do logu napíše upozornění (2026-11-05), nastavení maxima magenergie je na PJ v deníku postavy; (1) ostatní značky Zlatého tetování mimo Dikobraza (mechanika?) — doporučení: začít Gepardem (dvojnásobná rychlost pohybu a rozběhu) a Kobrou (2 okamžité akce, smí přerušit cizí kolo); Pegas/Gryf: označení „na zemi“ vypadá na špatném řádku (ověřit v tištěné knize, viz kontrolní seznam); (2) druh zbraně u 13 položek
(`docs/kontrolni-seznam-neuplnych-mist.md`: Fuuma shuriken, Atlatl, Řetězový meč, Khakkhara, Hůl „Minami no mori“, Rachejtle); (3) které kroky deníku v2 (figurína, Dovednosti, Společníci) a s jakými pravidly;
(4) nastavení subdomény `vtt` u WEDOSu na složku `vtt` (hezká adresa bez `/vtt/`);
(5) ~~kouzlení v souboji~~ — **rozhodnuto 2026-11-04, hotovo včetně počítání akcí podle vyvolání (viz „Kouzlení v souboji“ níž)**; kouzlo se sesílá samo (etapa 1) a kouzla „ihned“ se vyhodnocují při jiné akci nebo v obraně (etapa 2) — hotovo a nasazené, ruční SQL blok I i Změny databáze uživatel provedl 2026-11-05.
(6) **boj ve tmě — automatická ztráta iniciativy** (h1613 b10738: útočník bez zpráv o protivníkovi vždy ztrácí iniciativu a nemůže útočit v kole, kdy na něj protivník nezaútočí) — dnes se řeší jen postih −5 / −4 / −2; kniha mluví o dvojici útočník–konkrétní protivník, engine pořadí tahů po dvojicích nezná;
(7) **Lovcův Příkrov stínů** (h1003 b7357–b7359): zóna, kde zdroje světla zhasnou a Lovec vidí „jako by tam tma nebyla“ — kniha říká „přirozená tma“, ale nerozlišuje, jestli v ní funguje infravidění ostatních a jestli ji vidí i spolubojovníci; nejbližší hotový stavební kámen je zóna Temnota.

### Čeká na ruční spuštění přes phpMyAdmin (admin účet)

Migrace běží pod DML-only účtem (od 2026-10-27 ze stránky Změny databáze v administraci, dřív `scripts/migrate.php`), takže DDL nikdy neprovede a **na
prvním takovém souboru se zastaví** (stránka hlásí, že potřebuje ruční SQL; kód je nasazený). Všechno za
ním čeká, dokud se to neprovede ručně.

**Ruční DDL blok je v `docs/sql-k-rucnimu-spusteni.sql`** — jediný soubor, ve
kterém jsou všechny dosud neprovedené DDL příkazy; uživatel ho dostává jako
text ke zkopírování (viz pravidlo v `CLAUDE.md`). **Stránka Změny databáze (2026-11-04) má rozbalovací „Co aplikace v databázi vidí“: název databáze, kterou web používá, a ✓ / „✗ chybí“ u sloupců z ručního SQL (0107–0113) — po ručním SQL ověř tam, ne jen číslem z phpMyAdminu (číslo může být z jiné databáze).** **Stav (2026-11-04): blok I — automatické seslání kouzla (migrace `0114`, jeden sloupec `aktivni_efekty.kouzleni_data`) uživatel provedl, kontrola vyšla 1; stránka Změny databáze 0114 přeskočí a zapíše.** Dřív: nic nečekalo — blok H (postup kouzlení, migrace `0113`) uživatel provedl a ověřil na stránce Změny databáze (všech osm sloupců ✓). Poučení: kontrolní SELECT v phpMyAdminu vyšel dvakrát 0, i když sloupce v databázi webu byly — číslo z phpMyAdminu může být z jiné databáze, proto se ruční SQL ověřuje tabulkou „Co aplikace v databázi vidí“.** Dřív: blok G (měřítko světové mapy, migrace `0110`) uživatel provedl, kontrolní SELECT vyšel 1.** Dřív: blok F — hodiny světa (migrace `0109`, `svet.aktualni_smena` a `tokeny.svetlo_zbyva`) uživatel provedl, kontrola vyšla 1, 1; ověřeno na kopii produkčního stavu (bez něj stránka Změny databáze správně řekne, že potřebuje ruční SQL, blok jde spustit opakovaně i po částečném provedení a po něm stránka 0109 pod DML-only účtem přeskočí a zapíše). Dřív: blok E — noční vidění (migrace `0108`, jediný `ALTER TABLE tokeny ADD COLUMN videni_noc_sahy`) uživatel provedl, kontrola vyšla 1; ověřeno na kopii produkčního stavu (bez něj stránka Změny databáze správně řekne, že potřebuje ruční SQL, blok jde spustit opakovaně a po něm stránka 0108 pod DML-only účtem přeskočí a zapíše). Bloky A–D (migrace `0104` přenos výsledku iniciativy ≤ 0, `0105` poznámky světa, `0106` dveře, `0107` vidění a světlo) uživatel provedl, kontrola vyšla 1, 3, 1, 1, 1, 2, 5; ověřeno na kopii produkčního stavu čistě, opakovaně i po částečném provedení a stránka Změny databáze po něm pod DML-only účtem doběhne; kód funguje i bez něj.** Dřívější stav (2026-10-27): nic nečekalo. Blok „Zbraně u sebe“ (`0101`, ALTER) i „Vytasení bez akce“ (`0102`, vkládání řádků; do ručního SQL šel, protože migrace z GitHubu nejely — viz „Deploy po 22. 10. a migrace z administrace“ níž) uživatel provedl, kontroly vyšly 1 a 2, 3, 1. Další migrace s daty se pouští tlačítkem v administraci. Blok „Iniciativa v2“ + „Zbraně a iniciativa“
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

**Regresní testy jsou v repu: `tests/`** (od 2026-10-28; dřív žily jen v pracovní složce session a po jejím zániku by se ztratily). `tests/build-fixture.sh`
postaví z repa celou testovací databázi (pravidla + migrace + účty + testovací data), `tests/run.sh` pustí všechny sady a před každou vrátí data do výchozího
stavu (pořadí nehraje roli); podrobnosti a seznam sad: `tests/README.md`. Nasazení je ignoruje (`tests/**` v `exclude`). Náhodná procházka soubojem (`tests/e2e/fuzz_souboj.js`) 2026-10-28 našla a nechala opravit pád „Další tah“ u tvora s 0 akcemi (`GREATEST(0, x - 1)` na sloupci bez znaménka). Náhodné vstupy do všech endpointů (`tests/e2e/fuzz_api.js`, 2026-10-29, ~2 600 požadavků na seed, 11 seedů) našly a nechaly opravit: obří souřadnice tokenu / pinu (`dracak_vtt_souradnice()` je ořízne, sloupce jsou INT), obří velikost a posun mřížky (SMALLINT), dlouhý název pinu (VARCHAR 150), **nekonečnou smyčku v mlze** při obdélníkovém výběru přes „celý svět“ (smyčka je teď omezená na rozsah mapy; hrozilo zablokování PHP procesu na 30 s) a varování „Array to string conversion“ u pole místo textu (`dracak_text()` v `includes/db.php` na 60+ místech v API i stránkách). Stejně pro stránky (`fuzz_stranky.js`: bez nálezu) a formuláře (`fuzz_formulare.js`, 2026-10-29): úprava postavy s úrovní / rasou / povoláním / životy mimo rozsah nebo s neexistující rasou a nový svět s neplatným datem nebo názvem delším než 150 znaků končily chybou serveru — teď se hodnoty ořežou, rasa a povolání se ověří v číselníku a neplatné datum se nahradí dnešním. **Před pushem změny v souboji, iniciativě,
efektech, zbraních nebo deníku pusť aspoň příslušné sady** (`tests/run.sh souboj_api ini_v2_api …`), u změn základu (`includes/db.php`, `vtt.php`, auth) všechno.

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

### Bezpečnost (audit 2026-10-22, reakce na NÚKIB „php2shell“)
- **PHP samotné je největší riziko, ne kód:** CVE-2026-109618/109619
  (RCE bez přihlášení), opraveno v 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12.
  Produkce běžela 21. 10. na 8.2.33, 22. 10. dopoledne už WEDOS sám dal
  opravenou 8.2.35 a uživatel pak přepnul na **PHP 8.4 — ověřeno
  22. 10. 2026 v logu deploye: 8.4.27 (opravená verze), smoke test i
  security checks zelené.** Verzi přepíná jen uživatel v administraci
  WEDOSu; aktuální verzi dřív ukazoval první řádek výstupu `migrate.php`
  v logu deploye; teď ji zjistíš v administraci WEDOSu. Kód běží na 8.4 (lokálně lint jen na 8.3); 8.5
  netestováno.
- **Hotovo v kódu:** session cookie `HttpOnly` + `SameSite=Lax` + `Secure`
  (po HTTPS) + `use_strict_mode`; **ochrana proti CSRF** v
  `dracak_csrf_guard()` (`includes/auth.php`, běží při každém načtení
  auth.php): POST s cizím `Origin` / `Sec-Fetch-Site` / `Referer` → 403,
  bez tokenů a bez zásahu do JS; kořenový `.htaccess` zavírá dotfiles a
  `config.php` a posílá hlavičky (X-Frame-Options, CSP `frame-ancestors`,
  nosniff, Referrer-Policy, HSTS 180 dní, bez X-Powered-By); `migrate.php`
  bere token jen z hlavičky (ne z `?token=`); `admin.php` nevypisuje text
  DB chyby; minimální délka hesla 12 znaků (existující účty se nemění).
- **Deploy po 22. 10. a migrace z administrace (rozhodnutí uživatele 2026-10-27, varianta A):** z GitHub Actions (runner v USA) vrací doména na všechny stránky
  aplikace HTTP 404 (`/` a `/robots.txt` 200) — nejspíš blokace po NÚKIB 22. 10., příčina na straně hostingu; FTP nahrání funguje a kód je na serveru. Proto
  deploy od 2026-10-27 **jen nahrává soubory přes FTP a generuje `config.php`**; kroky, které volaly web z GitHubu (migrace přes `scripts/migrate.php` s tokenem,
  smoke test, kontrola zabezpečení), jsou pryč a `scripts/migrate.php` i `migrate_token` / secret `MIGRATE_TOKEN` zrušené (FTP deploy smaže soubor ze serveru sám).
  **Změny databáze se pouští z administrace:** `admin_migrace.php` („Změny databáze“ v postranním menu, dlaždice na dashboardu), jen role admin, logika
  `includes/migrace.php` (přenesená ze `scripts/migrate.php` beze změny pravidel): spouští jen soubory `NNNN_popis.sql` ze `database/migrations/`, popořadě, jednou
  (evidence `migrace_log`), zastaví se na první chybě, soubor se zakázaným příkazem (DROP TABLE/COLUMN/DATABASE, TRUNCATE) nespustí vůbec, `CREATE`/`ALTER`,
  které už ručně proběhly (sloupec / index / cizí klíč / tabulka existuje), přeskočí a zapíše; jiné DDL spadne na 1142 a stránka řekne, že potřebuje ruční SQL.
  Spuštění je POST s tokenem v relaci (navíc k obraně proti cizímu webu v `auth.php`) a přesměrování (obnovení stránky nespustí nic znovu), souběžné spuštění
  hlídá `GET_LOCK`. Bez tabulky `migrace_log` ukáže hotový příkaz k ručnímu založení. **Po pushi s novou migrací klikni v administraci na „Spustit čekající
  změny“.** **Nahrazení HTTP kontrol — „Kontrola nasazení“ (2026-10-28):** `deploy.yml` před FTP spustí `.github/scripts/manifest.php` (seznam
  nahrávaných souborů z gitu minus `exclude` z `deploy.yml`, SHA-256, commit, čas → `deploy-manifest.json`, zavřený v `.htaccess`); `admin_nasazeni.php`
  (jen admin, menu / dlaždice / editor) ho na serveru porovná s diskem: **chybí** (FTP nenahrálo — na tomhle hostingu se to už stalo), **jiný obsah**
  (nahráno částečně / změněno na serveru), **navíc** (zbytek po starém nasazení, `setup-admin.php`, cizí kód), **spustitelné soubory v `uploads/`**
  (tam smí být jen obrázky). Tlačítko „Zkontrolovat“ si zevnitř serveru vyžádá vlastní adresy, které musí být zavřené (config.php, manifest, sync state,
  includes, content, uploads, migrace) a bezpečnostní hlavičky z `index.php` (X-Frame-Options, nosniff, HSTS, bez X-Powered-By); když se server na vlastní
  doménu nedovolá, napíše „nelze ověřit“. **Zbývá ručně:** CSRF (POST z cizího Originu → 403, dřív kontrolovalo deploy). Pozor: FTP-Deploy nahrává jen
  změněné soubory, chybějící soubor tedy další deploy sám nedoplní — drobná změna v tom souboru, nebo ruční FTP. Otestováno lokálně (manifest ze skutečného repa,
  čistý stav, chybějící / změněný / cizí soubor, uploads, bez / poškozený manifest, zavřené cesty proti routeru napodobujícímu `.htaccess`); na ostrém hostingu
  poběží až po prvním nasazení s touto změnou (do té doby „na serveru není manifest“).
  **Po nezávislém code review (2026-10-28) opraveno:** (1) rozdělení SQL už neořezává víceřádkové texty, jejichž řádek začíná `--` (komentář jen mimo řetězec; na všech
  101 migracích dává stejné příkazy jako dřív); (2) zakázané příkazy se hledají po příkazech a bez textu v řetězcích — věta s „truncate“ soubor neshodí a `ALTER … DROP`
  (i bez slova COLUMN), DROP INDEX / FOREIGN KEY / VIEW se zastaví; (3) „už hotovo“ se uzná jen při odepřeném oprávnění (1142 / 1044) a u ALTER s víc klauzulemi musí být
  hotová každá; `ADD INDEX` / `ADD CONSTRAINT` se už nepletou se sloupcem; (4) kontrola zavřených cest nejdřív ověří, že server vlastní doménu vidí (`robots.txt` = 200),
  jinak by hosting odpovídající 404 na všechno vyrobil falešné „zavřeno“; hostitel jen z `Host` a IP / localhost jen při vývoji z loopbacku (SSRF); (5) sken serveru
  přeskočí nečitelné složky a logy hostingu; (6) počet využití „jednou za kolo“ se čte po stránkách od startu souboje, ne z pevného okna; (7) `manifest.php` ukončí blok
  `exclude` podle odsazení a na nepodporovanou syntaxi vzoru (`!`, `{}`, `[]`) deploy zastaví; (8) `zbran_vytasit.php` nemá předčasnou kontrolu akcí (bezplatné vytasení akci nepotřebuje).
- **Chybový log (2026-10-28, plán #20; bez ručního SQL):** `includes/log.php` zapisuje do `logs/aplikace.log` (jeden řádek JSON na záznam; složka má vlastní
  `.htaccess` „Require all denied“, `logs/*` je v `.gitignore` kromě `.htaccess`; kdyby deploy `.htaccess` nenahrál, appka ho při prvním zápisu založí sama).
  Co se zapisuje: neošetřené výjimky a chyby DB (`set_exception_handler` v `includes/db.php`, teď i s `soubor:řádek` a zkrácenou stopou — dřív jen text), varování PHP
  (`E_WARNING`, `E_USER_*`; vypnuté nebo potlačené `@` ne; PHP dál píše i do vlastního logu), fatální chyby (shutdown handler) a **chyby JavaScriptu z prohlížečů**
  (`assets/js/chyby.js` → `hra/api/klient_chyba.php`; načítá se v hlavičce všech stránek z `dracak_vtt_page_start` a v `hra/mapa.php`; nehlásí rozšíření, cizí
  skripty, prázdné „Script error.“ ani nenačtené obrázky, nejvýš 5 hlášení na načtení stránky a 60 na relaci, jen přihlášení, vstup zkrácený a jen jako JSON).
  Do adres se nezapisují tajné hodnoty (`token`, `heslo`, `pozvanka`, `kod`, `secret` → `***`). Jeden požadavek zapíše nejvýš 20 záznamů, log se nad 1 MB ořízne na
  poslední půlku. **Čte ho `admin_log.php`** (jen admin; menu, dlaždice na dashboardu, editor): nejnovější nahoře, stejné chyby sloučené („×N“), filtr podle druhu,
  detail (požadavek, stránka, prohlížeč, stopa) po rozkliknutí, jméno účtu, „Vymazat log“ (POST s tokenem v relaci). Kontrola nasazení teď hlídá i `logs/`
  (spustitelný soubor tam = nález; `logs/aplikace.log` se mezi „navíc“ nehlásí) a zkouší zvenku `/logs/aplikace.log`. Testy: `log_unit.php` (zápis, čtení, ořez,
  maskování, limity), `log_ui.js` (JS chyba → log → admin stránka → smazání, hráč 403, injekce řádku, XSS), obslužné funkce přes CLI (výjimka, DB, varování, fatální).
- **Záloha databáze (2026-10-28, plán #19; bez ručního SQL):** `admin_zaloha.php` (jen admin, POST s tokenem v relaci, GET jen ukáže stránku) stáhne `.sql` s celou
  databází: `includes/zaloha.php` — struktura (`SHOW CREATE TABLE` → `CREATE TABLE IF NOT EXISTS`) a data všech tabulek (stránkování podle primárního klíče, binární
  sloupce jako `0x…`, hodnoty přes `PDO::quote`), **bez DROP** (omylem pustěná na ostrou DB nic nesmaže, jen spadne na duplicitě). Stačí SELECT, tedy i DML-only
  účet webu. Konec souboru nese řádek „Konec zálohy: N tabulek, M řádků“ (bez něj je neúplná; při chybě uprostřed stahování se připíše `-- CHYBA`). Ověřeno: obnova
  do prázdné DB (88 tabulek, shodné počty i `CHECKSUM TABLE`), BLOB a diakritika, druhý import nic nepřepíše, dump pod DML-only účtem; stránka (stažení, 403 bez
  tokenu / pro hráče). Neověřeno na ostrém hostingu: limit phpMyAdminu na velikost importu. Návod: `docs/zalohy.md`. Společně s tím je menu administrace v jednom
  souboru (`includes/admin_menu.php`), nová stránka = jeden řádek v `DRACAK_ADMIN_POLOZKY`.
- **Incident 2026-10-29: `vtt.zvelebil.online` vydává hlavní web místo aplikace (hosting, ne kód).** Uživatel hlásil přesměrování na `zvelebil.online`, `/index.php`
  dával „Not Found“. Diagnostika z GitHubu (ruční workflow `.github/workflows/ftp-diag.yml`, jen výpis názvů, nic nenahrává; spouští se z Actions → „FTP Diag“ → Run):
  na FTP leží celá aplikace a dnešní nasazení (09:44) proběhlo v pořádku, ale doména vydává **jiné soubory**: úvodní stránka má stejný otisk a velikost (33 765 B,
  `last-modified` 22. 10. 2026 12:47) jako `zvelebil.online`, `robots.txt` má 69 B (náš 26 B) a v odpovědi chybí naše hlavičky z `.htaccess` (HSTS, CSP). Subdoména tedy
  míří do jiné složky než ta, kam nasazuje FTP (od 22. 10., kdy se přepínalo PHP a hosting zasahoval kvůli NÚKIB — vysvětluje i dřívější 404 z GitHubu). FTP účet je zamčený
  ve své složce (`../` vrací totéž). **Značkový soubor** nahraný do kořene FTP je dostupný na `https://zvelebil.online/vtt/…` (200), na `vtt.zvelebil.online` ani v kořeni
  `zvelebil.online` ne (404): kořen FTP je tedy složka `vtt` uvnitř hlavního webu a subdoména `vtt` ukazuje do kořene hlavního webu místo do ní. Oprava je v administraci
  hostingu: u subdomény `vtt.zvelebil.online` nastavit cílovou složku na `vtt`. **Dokud se to nespraví, aplikace běží na `https://vtt.zvelebil.online/vtt/` i `https://zvelebil.online/vtt/`**
  (ověřeno z GitHubu: přihlášení 200, `config.php` / `includes/` / `logs/` / manifest 403, API 401, naše hlavičky a session cookie na místě). Aplikace nemá nikde absolutní cesty od kořene
  domény, v podsložce funguje beze změny. **Kontrola nasazení** si základní cestu bere ze složky, ve které leží (`dirname(SCRIPT_NAME)`), a navíc porovná `robots.txt` z domény s nahraným:
  když doména vydává jiný obsah, napíše to místo falešného „zavřeno“ (`tests/unit/nasazeni_unit.php`).
  Po opravě: `/index.php` má ukázat přihlášení, pak v administraci „Kontrola nasazení“ → „Zkontrolovat“.
- **Omezení pokusů o přihlášení (2026-10-31, bez DDL):** `includes/auth.php` — šest nepovedených přihlášení na jeden e-mail z jedné IP adresy (nebo 30 z jedné adresy celkem) za 15 minut
  zablokuje další pokusy z té adresy (i se správným heslem) až do vypršení nejstaršího selhání; `index.php` a `pravidla.php` ukážou „Příliš mnoho nepovedených pokusů, zkus to za N min“
  (HTTP 429). Blokuje se jen chybující adresa, takže cizí člověk nezamkne cizí účet. Počítadla jsou malé soubory v `logs/login/` (zavřená `.htaccess`, bez tabulky, kterou by web účet
  nesměl založit); chybějící nebo nezapisovatelná složka omezení tiše vypne, přihlášení kvůli tomu nepřestane fungovat. IP se bere z `REMOTE_ADDR` (ne z hlaviček, které posílá klient).
  Neexistující e-mail se ověřuje proti fiktivnímu hashi, ať doba odpovědi neprozradí, jestli účet existuje. Testy: `tests/unit/login_unit.php`, `tests/e2e/login_ui.js`.
  Při zkoušení přes `tests/run.sh` se `logs/login/` před každou sadou maže (fuzzery formulářů z jedné adresy nasbírají limit hned).
- **Content-Security-Policy, etapa 1 — jen hlásí (2026-10-31, bez DDL):** `includes/csp.php` posílá hlavičku `Content-Security-Policy-Report-Only` na všech stránkách (načítá ji
  `includes/db.php`, ne v CLI): skripty jen z vlastní domény nebo s jednorázovým `nonce`, žádné inline `on*` atributy, `object-src 'none'`, `base-uri`/`form-action` jen vlastní
  doména; styly zůstávají s `'unsafe-inline'` (stovky `style=""` v šablonách, riziko je proti skriptům malé) a písma z Google Fonts. Prohlížeč porušení nepřeruší, jen pošle hlášení
  na `csp-report.php` (jen POST, tělo ≤ 4 kB, max. 60 hlášení za minutu), které je zapíše do chybového logu jako druh „Porušení CSP“ (`admin_log.php`). Každý inline `<script>` má
  `nonce="<?= dracak_csp_nonce() ?>"`; inline `onclick`/`onsubmit`/`onchange` jsou nahrazené atributy `data-potvrdit`, `data-odeslat-pri-zmene`, `data-tisk`, `data-akce`
  a delegovanou obsluhou v `assets/js/obecne.js` (načítá ho `dracak_vtt_page_start`) a ve skriptu `editor.php`. **Při té příležitosti opraveno:** `dracakAddTag` v editoru
  vkládal text z `<option>` do `innerHTML` bez escapování (DOM-XSS přes název záznamu číselníku) — teď `esc()`. **Přepnutí na vynucování** = jediná konstanta
  `DRACAK_CSP_REZIM` v `includes/csp.php` (`'report'` → `'vynutit'`), ale až poté, co po nasazení pár dní v administraci „Chybový log“ nebude žádné „Porušení CSP“ od skutečných
  hráčů (rozšíření prohlížeče a přeložené stránky hlášení generují také; ignorovat to, co má `blocked-uri` mimo naši doménu). **Pravidlo do budoucna:** nový inline skript musí mít
  nonce, nový ovládací prvek nesmí mít `on*` atribut. Testy: `tests/unit/login_unit.php` se tím nemění; `tests/e2e/editor_ui.js` a opice (`monkey_ui.js`) končí chybou, když se
  v logu objeví druh `csp`.
- **Souběžné úpravy (2026-10-31, bez DDL):** test `tests/e2e/soubehy_api.js` (osm samostatných přihlášení naráz) ukázal, že úprava životů, magenergie, únavy a zkušenosti četla hodnotu, přepočítala ji v PHP
  a zapsala zpět — dva současné zásahy (PJ strhne život a hráč se zrovna léčí, deník a mapa na dvou zařízeních) daly jen jeden (60 zásahů −1 ⇒ zbylo o 11 životů víc). Teď je změna jeden `UPDATE` s ořezem
  v DB (`dracak_vtt_hp_zmen`, `dracak_vtt_magenergie_zmen`, `dracak_vtt_cital_zmen` v `includes/vtt.php`; používá je `hp_uprava`, `magenergie_uprava`, `postava_stav`, použití předmětu a tiky efektů).
  Dále: poslední kus lektvaru se při použití čte se zámkem řádku (`pouzij_predmet.php`; dva současné doušky dřív prošly oba), předání kořisti PJ (`loot.php`) taky, vložení do vaku a vyndání z něj (`vtt_kontejnery.php`; na starém kódu se jeden kus při osmi současných vloženích rozmnožil na pět), změna počtu kusů v deníku
  (`postava_stav.php`, `inventar_zmena`) běží v transakci se zámkem. Souboj (tahy, akce) měl transakce a zámky už dřív a test to potvrdil. Obří změna (`delta` ±10⁹) se ořízne na rozsah sloupce místo chyby DB.
- **Kdo to hlídá:** zavřené cesty a hlavičky ověří stránka „Kontrola nasazení“
  (`admin_nasazeni.php`, tlačítko „Zkontrolovat“); dřívější krok `deploy.yml`
  „Post-deploy security checks“ je od 2026-10-27 pryč (hosting odmítá HTTP z
  GitHubu). CSRF (POST s cizím Origin = 403) se kontroluje jen ručně.
  „Zavřeno“ = hosting direktivy z `.htaccess` uplatnil, červená položka =
  direktiva se neuplatnila, ne chyba aplikace.
- **Prošlo auditem bez nálezu:** SQL (prepared statements, jediná
  konkatenace je `(int)`), nahrávání obrázků (whitelist přes
  `getimagesize`, náhodné jméno, přípona z MIME, složka zavřená, výdej
  přes PHP po kontrole světa), sanitizace HTML (`dracak_sanitize_html`),
  `innerHTML` v `assets/js` (vždy `esc()`, barvy kreseb ověřuje server).
- **Vědomě otevřené:** omezení počtu pokusů o přihlášení (chce tabulku →
  ruční SQL; u pár známých účtů nízké riziko); GitHub Actions jsou
  pinované na tag (`@v4`), ne na SHA; endpointy `hra/api/*` jsem
  neprošel řádek po řádku, jen vzorek (přístup ke světu se kontroluje
  přes `dracak_vtt_svet_access` / `dracak_vtt_souboj_zacatek`).

### VTT základ
- Svět → Mapy (`typ_mapy` ENUM `svet`/`zona`) → Tokeny (postava/nestvůra).
- Polling: `hra/api/stav.php` (snapshot), `hra/api/udalosti.php` (delta od
  posledního ID z `svet_udalosti`).
- Grid (čtverec/hex), zdi (`blokuje_pohyb`/`blokuje_vystrel` odděleně),
  měřítko px→sáh (`dracak_vtt_px_na_sah`).

### Boj
- **Vytasení / výměna zbraně stojí akci a zbraně „u sebe“ (2026-10-27, rozhodnutí uživatele: „vytasení stojí akci, ano“; ruční SQL: sloupec `postava_predmety.u_sebe`,
  `docs/sql-k-rucnimu-spusteni.sql`):** kniha — v jednom kole jde provést jeden úkon, „například švihnout mečem, seslat kouzlo nebo vyměnit zbraň“, a nejde
  „vytasit meč a použít jej“ (h1606, b10619); zbraně při sobě postava nosí v pochvě či závěsu (h2058, b14698). **V ruce je vždy jen jedna** (`postavy.drzena_zbran_id`).
  Mimo boj se zbraň mění zdarma; **v boji je to akce tahu `vytasit`** (`DRACAK_VTT_AKCE_DRUHY`, `dracak_vtt_souboj_proved_akci`): odečte akci z kvóty půlky,
  zapíše `akce_provedena` s `zbran_nazev` do logu, tah jde dál, a protože nesplní slíbený úkon, první akce kola = vytasení se trestá stejně jako každá jiná
  (−3 v příštím kole, h1622). Hráč smí jen na vlastním tahu a jen zbraň **u sebe** (`u_sebe = 1`: opasek, pochva, závěs, toulec; výchozí pro všechny zbraně,
  protože kniha neříká, kolik se jich na opasek vejde); zbraň z ruky se zasune k tělu sama (`u_sebe = 1`). PJ smí zbraň změnit i bez akce (`bez_akce`, zatržítko
  „bez akce (PJ)“ v deníku). V boji hráč nemění výbavu (`zbran_u_sebe`) ani nevybírá zbraň přes `vyber` (422). „V boji“ = postava má token na mapě světa,
  kde běží souboj, a je jeho účastník (`dracak_vtt_zbrane_boj_kontext`, `includes/vtt_zbrane_u_sebe.php`). **API:** `hra/api/zbran_vytasit.php` {postava_id, zbran_id, bez_akce?}
  (deník), akce `vytasit` v `hra/api/tah_akce.php` {zbran_id} (stejná logika), `postava_stav.php` akce `zbran_u_sebe`. **UI:** deník → Boj: pruh „Jsi v boji“
  (zbývající akce), sloupec „V ruce“ (štítek / tlačítko Vytasit, zakázané s důvodem), sloupec „U sebe“ (zatržítko, v boji zamčené), klepnutí na ÚČ v záhlaví
  vytasí další použitelnou zbraň (v boji s potvrzením); panel Souboj → „Další akce…“ → „Vytasit zbraň…“ otevře okno deníku na zbraních. Bez ručního SQL
  vše funguje, jen se nerozlišuje „u sebe“ (sloupec je od 2026-10-27 na produkci, kontrolní SELECT vyšel 1; migrace `0101`).
  **Vytasení bez akce — výjimky z pravidla jsou efekty v DB (migrace `0102`, 2026-10-27, rozhodnutí uživatele „přidej to do DB jako efekt“):** složka efektu
  `tasit_zdarma` (parametr = druhy zbraní `tvt` / `vrhaci`, hodnota = kolikrát za kolo, prázdná = bez omezení) a složka `pasivni` (efekt platí trvale, dokud má postava
  schopnost, ke které je připojený přes `schopnost_efekty`). **Erythen — Rychlé tasení** (h1094, b7751–b7755): efekt „Rychlé tasení (Erythen)“ je připojený ke schopnosti
  Rychlé tasení (6. úroveň) a je pasivní → každý Erythen od 6. úrovně vytasí **jednou za kolo** bez akce zbraň tváří v tvář (dvě zbraně = jedna „kombinace“ katalogu)
  nebo vrhací zbraň (jeden limit pro oboje); využití se počítá z logu (`akce_provedena.zdarma_efekt` ve stejném kole). **Dikobraz** (Zlaté tetování Velkého lovce, h1411,
  b9098): efekt „Dikobraz (Zlaté tetování)“ (1 kolo, rychlá volba) nasadí PJ jako každý efekt; vrhací zbraň se pak vytasí bez akce bez omezení počtu. Bezplatné vytasení
  neubere akci, není „akce kola“ (neposune tah, nespustí trest za slib úkonu, nepřeruší řadu sprintů) a zbraň musí být „u sebe“ (volně dostupná: pochva, holster —
  b7754). Druh zbraně dává `dracak_vtt_zbran_druh()` z `kategorie_zbrane` (střelné a zbraně bez kategorie se nepočítají; migrace `0103` doplnila druh u 6 zbraní, 13 jich zůstává prázdných — viz `docs/kontrolni-seznam-neuplnych-mist.md`). V deníku (Boj) pruh „Bez akce: …, zbývá 1×“ a
  tlačítko „Vytasit (bez akce)“. **Zůstává na PJ** (efekty jsou ve stavu „cast“): Erythenova vržená zbraň +2 ÚČ, cizí zbraň v dosahu 1 (past Obr–7–Obr), dvojnásobné dostřely
  Dikobraza; ostatní značky Zlatého tetování (Slon, Kobra…) nejsou v DB. **Chybí (potřebuje pravidlo):** kolik zbraní se vejde na opasek / do pochev, cena pochev v nosnosti,
  toulec a šípy jako omezení střelby.
- **Deník postavy v plovoucím okně na mapě (2026-10-19, okno přestavěno 2026-10-27; bez ručního SQL):** v boji se výbava, zbraň v ruce, životy, kouzla…
  upravují ve stejném deníku jako na stránce postavy (`hra/denik.php`), ale **přímo na mapě, bez proklikávání**. **Vstup je v detailu tokenu** (panel Spravovat,
  tlačítko „Otevřít deník“ `#spravovatDenik` nahoře pod jménem; rozhodnutí uživatele 2026-10-20 — v levé liště nástrojů tlačítko není; zobrazí se u tokenu
  postavy, kterou smí uživatel otevřít, přebije i režim „pevně“, na telefonu detail po otevření schová). Pod ním jsou **skoky rovnou na sekci deníku**
  (Boj / Kouzla / Výbava → `#boj`, `#schopnosti`, `#vybaveni`) a v Inventáři detailu u postavy tlačítko „Upravit v deníku“ (Výbava; Inventář se u postavy
  ukazuje vždy, i prázdný); skok srovná sekci pod hlavičku deníku, jen když se ta v okně drží nahoře. V horní liště mapy je odkaz **Pravidla ↗**
  (`../pravidla.php`, nová záložka; co kdo uvidí, hlídá `pravidla.php` podle role).
  **Okno (`#denikDock`, `assets/js/denik_dock.js`, rozhodnutí uživatele 2026-10-27: „floating, není nutné to mít fix, ale oboje“):** deník v rámečku
  (`denik.php?id=…&embed=1` — bez odkazů, které by z mapy odvedly pryč; celé funkce deníku včetně hledání v katalogu, kontejnerů, hodů a sesílání zůstávají)
  **plave přes mapu** — mapu nezužuje. Přesouvá se tažením za hlavičku, velikost se mění za okraje a rohy (min. 340 × 240 px), jde **sbalit na hlavičku**
  a **roztáhnout na celou obrazovku** (overlay přes mapu, lištu i panel tokenu; „Zpět do okna“ vrátí poslední polohu, ✕ zavře). Okno zůstane vždy svisle
  na obrazovce a aspoň 200 px z něj vodorovně, ať jde chytit; leží nad lištami (z-index 201), pod postranními panely (202) — panel tokenu okno překrývá,
  ne naopak. Na telefonu (pod 760 px) je spodní list, který jde taky na celou obrazovku. Poloha, velikost, sbalení, celá obrazovka, otevřenost, režim sledování
  a postava se pamatují v prohlížeči (`dracak_denik_dock`). Okno ukazuje postavu **podle vybraného tokenu** (výchozí), **podle toho, kdo je na tahu**, nebo
  **pevně** jednu (ruční výběr v seznamu přepne na „pevně“); nestvůra deník postavy nemá, takže výběr nestvůry postavu nemění. PJ otevře deník kterékoli
  postavy světa, hráč jen svých (server to hlídá znovu, cizí deník dá 403). Deník a mapa si dávají vědět přes `postMessage` (`denik_zmena` → mapa hned
  obnoví stav, `denik_obnov` → deník se obnoví po zásahu na mapě; obnova se nepřeskočí, jen když zrovna píšeš). Zavřené okno nic nenačítá ani neobnovuje.
  Test: `denik_dock.js`. **Skoky z mapy (od deníku v2):** okno volá `window.dracakDenik.ukazSekci(id)` z `assets/js/denik.js` — deník přepne záložku a srovná sekci pod
  hlavičku; id: `boj`, `kouzla` (bez kouzel → Schopnosti), `schopnosti`, `vybaveni`, `prehled`, `obrana`, `pohyb`, `efekty`, `postava`, `kontejnery`,
  `penize`. Neznámé id → `false`, nic se nestane.
  **Nepokryto:** nestvůry (výbava nestvůry se dál spravuje v panelu tokenu a v malém deníku nestvůry). **Plán — zjednodušený postavový deník nestvůry
  (zadání uživatele 2026-10-20):** nestvůry mají mít podobný deník, ale ne pro úpravy za boje, nýbrž **před bojem** (příprava výbavy a čísel); stejný vstup —
  tlačítko v detailu tokenu nestvůry (`#spravovatDenik` se u nestvůry teď záměrně skrývá). Uživatel navrhuje design sám, implementace čeká na něj.
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
- **Přenos výsledku iniciativy ≤ 0 do dalšího kola (2026-11-01, migrace `0104`, čeká na ruční SQL; rozhodnutí uživatele: „hází si znovu, ale postih je podle předchozí iniciativy“, h1622 b10805):**
  výsledek ≤ 0 na konci kola se uloží účastníkovi (`kolo_ucastnik.ini_prenos`, `dracak_vtt_iniciativa_prenos_uloz` před smazáním `kolo_iniciativa`, jak v `dracak_vtt_souboj_nove_kolo`, tak ve starší
  cestě `dalsi_tah.php`); v dalším kole tvor hází znovu 1k6 a **místo celého obvyklého modifikátoru** (tabulka bonusů, držená zbraň, únava a naložení, efekty, bonus povolání) se přičte
  tohle číslo (např. Arpag s obouruční zbraní −2 hodí 1 → −1 a 0 akcí; příště hodí 5 a přičte −1 místo −2 → 4 → 2 akce). Ruční úprava PJ se přičte dál, „nemůže jednat“ (efekt) platí dál, slib
  úkonu se při přenosu nevyhlašuje. Kdo skončí na 1 a víc nebo nehodil, nic nepřenáší. Popis v logu hodu: „Výsledek z minulého kola (−1) místo obvyklých postihů“. Test: `tests/e2e/ini_prenos_api.js`.
  Bez sloupce se nepřenáší (dosavadní chování).
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
  (12. / 24.; těžká kuše 0 / +3). Nesčítají se (jsou to řádky Tabulky vícenásobných útoků), přičtou se samy při úmyslu Útok / Střelba se zbraní
  v ruce, nahrazují pole „útoků za 2 kola“ (klient ukáže „+9 povolání“ místo pole) a zavazují ke slibu úkonu. Držení zbraně: `chvat` =
  `jednoruci` / `obourucni` (jen jednokusá zbraň v obou rukách) / `kombinace` (dvě zbraně, počítá se jako jednoruční; migrace `0100`).
  **Těžká kuše** jde u bojovníka o stupeň pozadu (potvrdil uživatel): od 12. úrovně bez postihu = 0, od 24. úrovně +3; stejně **Šermíř** u střelných a vrhacích zbraní (b1084: 10. úroveň +3, 20. +6, těžká kuše 0 → +3; „jednou za kolo“ = bez postihu); jeho zbraně tváří v tvář zůstávají na ručním poli (texty si odporují). Další povolání a zbraně, které bonusy vynechávají kvůli vlastnímu postihu, se doplní stejnou dvojicí záznamů. **Nerozhodnuto:** Mistr zbraně, bonusy ostatních povolání — viz
  `docs/kontrolni-seznam-neuplnych-mist.md`. **Útok na vyřazeného** (h1613 b10736–b10737): tvor na mezi vyřazení nebo pod ní se brání jako nehybný (místo bonusu za
  obratnost −5, −3 bez zbraně, bez štítu, bez oprav za velikost a zezadu), bránit se zbraní nesmí a **zranění se násobí
  úrovní** (postava nejvýš ×9, nestvůra životaschopností; u „3 + 4“ násobek doplní PJ na kartě obrany). Stejná cesta jako u
  spícího (efekt Spánek). **Kouzlící tvor jako nehybný — hotovo 2026-11-04**, viz „Kouzlení v souboji“ níž.
- **Kouzlení v souboji (2026-11-04, migrace `0112` DML + `0113` DDL = ruční SQL blok H).** Pravidlo (kapitola Kouzlení, b10842–b10847): kouzlo s vyvoláním N kol vyžaduje
  2N po sobě jdoucích akcí kouzlení (dokončit smí i v dalším kole); kdo kouzlí, je pro útočníky nehybný (útok na nehybného: v **hodu na obranu** místo bonusu za obratnost −5,
  −3 bez zbraně, bez štítu — obratnost jako vlastnost se nemění); působit začíná po **předposlední** akci, poslední je odpočinek; zraněn aspoň za 1 život **před
  předposlední akcí** → kouzlo se nezdaří a magenergie propadne, po ní kouzlo uspěje (kouzlo s koncentrací by se přerušilo); přerušit kouzlo stojí jednu akci kouzlení.
  **Rozhodnutí uživatele 2026-11-04:** štítek-efekt **„Kouzlí“** (`includes/vtt_kouzleni.php`), a po jeho připomínce „počet akcí je u každého kouzla daný“ **počítá hra akce sama**:
  akce kouzlení nese `kouzlo_id` (výběr z kouzel postavy, `hra/api/kouzla_tvora.php`), počet akcí = 2 × kola z údaje `kouzla.doba_seslani` (`dracak_vtt_kouzlo_akce`: „N kolo/kola/kol“,
  „1 akce“; **směny a vzorce „za každých…“ se nepočítají** — pravidla pro ně v rozšířeném systému mlčí, takže u nich a u nestvůr zadá PJ počet ručně nebo štítek sundá; **vyvolání „ihned“ = 0 akcí, rozhodnutí uživatele 2026-11-04: kouzlo „ihned“ nestojí žádnou akci a sesílá se při jiné akci (pohyb, útok, obrana) z deníku — jako akce kouzlení se odmítne**). Postup
  (`aktivni_efekty.kouzlo_id`, `akci_celkem`, `akci_hotovo`, migrace 0113) hlídá hra: po předposlední akci událost „začíná působit“, po poslední štítek sama sundá, zásah před předposlední
  akcí kouzlo zruší (štítek zmizí, log napíše, kolik magenergie propadlo — cena kouzel je často vzorec, proto ji hra nestrhává ani nevrací), zásah po ní kouzlo nechá projít; jiná akce
  než kouzlení štítek sundá („kouzlení přerušeno jinou akcí“), výslovné přerušení je tlačítko „Přerušit“ (stojí akci), konec souboje štítek sundá. Kouzlo na 1 akci se sešle hned
  a štítek nevznikne. Hráč smí vybrat jen kouzlo, které jeho postava zná; ruční počet akcí smí zadat jen PJ. Bez sloupců z bloku H funguje starší chování (štítek sundává PJ,
  výběr kouzla se nenabízí). **Kouzlo se sesílá samo (rozhodnutí uživatele 2026-11-04: „proč by se to mělo dělat ručně“; migrace `0114`, ruční SQL blok I):** akce kouzlení nese kromě kouzla i **cíl** (`cil_token_id`, výchozí sám), **trvání** v kolech (`zbyva_kol`, hra se zeptá, když ho kouzlo vyžaduje) a případně **magy** (`magy`, jinak první číslo z ceny kouzla). Při zahájení hra ověří cíl (výhled, dosah kouzla), trvání a dostatek magenergie a **magy strhne hned** (propadnou i při přerušení nebo zásahu); údaje uloží ke štítku (`aktivni_efekty.kouzleni_data`, JSON). Ve chvíli, kdy kouzlo začíná působit (**po předposlední akci**; kouzlo na jednu akci hned), hra sama zavolá stejný kód jako „Použít“ v deníku (`dracak_vtt_polozka_pouzij_ucinek` v `includes/vtt_predmety.php`, vytažený z `pouzij_predmet.php`): hodí kostky z kouzla, zraní nebo uzdraví cíl, nasadí stavové efekty; kouzla s hodem proti pasti zůstávají v seznamu „čeká na past“ jako dosud (to rozhoduje PJ). Poslední akce kouzlení už nic nesesílá, jen štítek sundá. Bez sloupce z bloku I funguje kouzlení po staru (počítá akce, účinek zadá hráč z deníku). Test: `tests/e2e/kouzleni_api.js`.
  **Kouzla „ihned“ (etapa 2, rozhodnutí uživatele 2026-11-04: „vyhodnocovat právě tehdy… jde více ihned kouzel najednou k jedné akci“):** kouzlo s vyvoláním „ihned“ (0 akcí) se **připojí k jiné akci** —
  `tah_akce` (pohyb, útok, čekání… cokoli kromě kouzlení) přijme pole `ihned: [{kouzlo_id, cil_token_id?, magy?, kola?}]` (nejvýš `DRACAK_VTT_IHNED_MAX` = 5) a v obraně totéž pole přijme
  `vyzva_uprava` (jen ovládající hráč obránce nebo PJ; sešle se **před hodem na obranu** a přepočítá opravy obrany — efekty, nehybnost, štít: `dracak_vtt_obrana_obnov_efekty`).
  Server (`dracak_vtt_kouzli_ihned_over`) nejdřív ověří **celý seznam** (kouzlo musí mít „ihned“, hráč smí jen kouzla své postavy, cíl a dosah, trvání, **součet magů za všechna kouzla**) a teprve
  pak zapíše akci a kouzla provede (`dracak_vtt_kouzli_ihned_proved`: zaplatí magy, použije stejný kód jako „Použít“, událost `kouzleni_stav` stav „ihned“). Nic nestojí akci navíc a nevzniká
  štítek „Kouzlí“. PJ smí sesílat za kteréhokoli tvora. Funguje i bez bloku I (kouzlo „ihned“ nepotřebuje sloupce `kouzleni_data`). Klient: v menu akce „Kouzla ihned…“ (fronta se připojí k další akci),
  v kartě obrany blok „Seslat teď“. Test: oddíl K v `tests/e2e/kouzleni_api.js`.
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
  **Po code review (3. kolo, 2026-10-28) opraveno:** (1) tvor má nejvýš jednu čekající výzvu „tik“ na efekt — nové kolo zruší nevyřízené tiky z minulého
  (`stav = zruseno`) a duplicity podle `aktivni_id` vynechá; (2) tik efektu, který mezitím zmizel (PJ ho sundal, vypršel), se nehodí — `vyzva_hod.php` ho zruší
  s hláškou místo toho, aby strhl životy za efekt, který tvor nemá, a `dracak_vtt_tik_vyhodnot` při chybějícím tvoru spadne s výjimkou; (3) znovunasazený
  efekt (Rychlost, která už běží) obnoví i `nasazeno_kolo`, takže „poslední aplikace“ platí od nového kola; (4) veřejná událost `kolo_nove` už neprozradí skrytou
  nestvůru v seznamu `ztrata_iniciativy` (trest jí vidí PJ v efektech); (5) pořadí iniciativy bere popisek přejmenovaného tokenu; (6) `vyzva_hod.php` bez běžícího
  souboje vrátí srozumitelné 422. Regresní test `review2_api.js`.
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
- **Rozšíření hotové v kódu 2026-11-01 (migrace `0105`, čeká na ruční SQL — bez něj fungují poznámky po staru):**
  - **Štítky** `quest / npc / loot / lore / misto` (Úkol, NPC, Kořist, Příběh, Místo; pevná sada v `DRACAK_VTT_POZNAMKY_STITKY` v `includes/vtt_poznamky.php`, nový štítek DDL nepotřebuje).
    Výchozí volba „Automaticky podle textu“: klíčová slova v textu (bez ohledu na diakritiku; pořadí Úkol, Kořist, NPC, Příběh, Místo), jinak vazba na postavu = NPC, vazba na místo = Místo,
    jinak bez štítku. Ve výpisu je filtr podle štítku (`?stitek=`) s počty.
  - **Vazba na postavu / NPC** (`postava_id`, jen z téhož světa; smaže-li se postava, vazba se zruší, poznámka zůstane).
  - **Úprava** na místě bez historie (`upravit_poznamku`): text, místo, štítek, postava a sdílení; smí **jen autor** (ani admin nepřepisuje cizí slova; smazat cizí poznámku admin smí dál).
    Zapisuje čas úpravy („upraveno“). Příjemci sdílení se teď ověřují proti členům světa (dřív šlo poslat id libovolného účtu).
  - **Odznak nepřečtených:** tabulka `svet_poznamka_precteno`; „nové“ = nasdílené poznámky, které účet ještě neotevřel (vlastní a admin-dohled se nepočítají). Počet je v seznamu světů
    (`hra/svety.php`) a u nadpisu Poznámky ve světě, nová poznámka má štítek „Nové“. Po úpravě textu a po novém přidání do sdílení se poznámka zase ukáže jako nová.
  - **Odebrání hráče ze světa** (tlačítko „Odebrat“ v dialogu Hráči ve světě; PJ/admin): hráč ztratí přístup, poznámky světa, které mu někdo nasdílel, se mu přestanou ukazovat
    (`dracak_vtt_poznamky_zrus_sdileni_hrace`). Jeho postavy ve světě a jeho vlastní poznámky zůstávají; PJ světa odebrat nejde. (Odebírání hráčů dřív v aplikaci vůbec nebylo.)
  - **Dohled admina:** `admin_poznamky.php` (menu administrace „Poznámky světů“) — všechny poznámky všech světů i nenasdílené, filtr podle světa, autora a štítku; jen ke čtení.
  Test: `tests/e2e/poznamky_ui.js`.

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
- **Vzhled pinu na mapě** (`assets/css/mapa.css`, `.mapa-bod-marker`) —
  ikona je Lucide `stroke="currentColor"` a tlačítko barvu nenastavovalo,
  takže byla černá a na tmavé mapě (kobka) neviditelná. Teď tmavý kruh
  (`#181614`) se světlým prstencem (`#e8c9a8`) a světlou ikonou (`#eee`):
  čitelné na libovolném podkladu. Bod pinu je spodní okraj kruhu (dřív
  spodní okraj ikony). Pin skrytý hráčům má dál `opacity:.6` (inline z
  `hra/mapa.php` a `mapa.js`), beze změny. V design systému (Claude
  artifact „Dračák VTT“) jsou k tomu hotové assety `Pins` (kapky) a
  `Icons-halo`; v repu zatím jen tenhle CSS, assety se nepoužívají.
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
  Rozšíření na ostatní tvory z h2057 (válečný kůň, vůl, velbloud, letci
  s řádky "na zemi", ceny…) **hotové 2026-11-03 (migrace 0111, 36 zvířat,
  jen INSERT IGNORE)**: každý řádek tabulky je samostatné zvíře se jménem
  podle knihy (`pegas 1`, `pegas 2 (na zemi)`), cena a poznámky jsou v
  `popis`, `cas_na_10_mil_min` = 0 („kniha neuvádí“, sloupec se nepočítá).
  Sloupec „Ujede“ se v kalkulačce nepoužívá (cesta jde přes body za hodinu
  z pohyblivosti), takže jeho nevysvětlený význam a hvězdičky `*`/`**`
  nic neblokují — zapsáno v kontrolním seznamu. Draci (řídí bestiář) a
  mořský kůň (bez vytrvalosti) vynechaní. Test: `cesta_zvirata_api.js`.
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

## Osobní deník postavy (v2 se staví po krocích; kroky 1 a 2 hotové 2026-10-27)

**Stav 2026-10-27:** návrh od Claude Design je hotový jako funkční prototyp
(vzorová postava Ronan, Warlock 13) a uložený v repu:
**`docs/prototypy/denik-v2.dc.html`** (zdroj) a
**`docs/prototypy/denik-v2-predani.md`** (rozhodnutí uživatele, vzorce,
otevřené otázky, návrh postupu stavby). **Krok 1 stavby je v kódu** (záhlaví, měřidla, záložky — podrobně a s odchylkami od
prototypu v části „Stav stavby“ předávacího dokumentu); popis v1 níže platí pro
data, API a funkce, vzhled je už v2. **Krok 2 (zbraně u sebe, vytasení za akci) je hotový** — viz sekce Boj výše. **Krok 3a (2026-10-28): detail předmětu (`hra/api/polozka_detail.php`, plovoucí karta po kliknutí na název) a hledání + filtry podle druhu v inventáři** — viz `docs/prototypy/denik-v2-predani.md`. Další kroky: figurína
a sloty, dovednosti, společníci (část z nich potřebuje ruční SQL). Otevřené otázky k pravidlům a datům
(Elixír, únava 30, váha, Torna, „Vyřazení 34“, postřeh, základ šance na
seslání) jsou v předávacím dokumentu.

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
4. ✔ Obecné tokeny (NPC bez statů, předmět, překážka, značka) [M] — v kódu hotové (`typ_entity = 'obecny'`), seznam jen zaostal
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
15. ✔ (2026-11-02, bez ručního SQL) Přechod mezi lokacemi: pin → „Vstoupit s družinou“ přenese tokeny družiny na vstupní
    bod cílové mapy a přepne aktivní mapu všem [M]
16. Knihovna map: duplikace, velké obrázky (automatické zmenšení, limit
    hostingu), pořadí scén [M]
17. ✔ (v1, 2026-11-02: čas ve dni a hoření světel; zbývá propojení s cestou, odpočinkem a spánkem) Jedny „hodiny světa“ (den/hodina) propojené s cestou, odpočinkem a
    spánkem [M]

*D. Příprava na ostré hraní*
18. ✔ (2026-10-28, automatický průchod: účty → svět → pozvání → wizard postavy → co kdo vidí, 23 kontrol) Ověřit celý tok pozvání: PJ vytvoří svět → pozve hráče → hráč založí
    postavu → vidí jen své [M]
19. ✔ (2026-10-28) Zálohy databáze i `uploads/` na WEDOSu + ověřený postup obnovy [S] — `admin_zaloha.php` (stažení celé DB jedním kliknutím), obrázky
    z `uploads/` jen ručně přes FTP; postup a co je ověřené: `docs/zalohy.md`
20. ✔ (2026-10-28) Chybový log čitelný pro admina (aby se chyby z ostré hry nemusely
    popisovat z paměti) [S] — `admin_log.php`, viz „Chybový log“ v sekci Bezpečnost
21. ✔ (2026-10-28) Zátěž polling: adaptivní interval (rychle v boji, pomalu v klidu, stop při
    skryté záložce), spočítat počet dotazů/min pro 6 hráčů [S–M] — adaptivní interval už byl (1,5 s do 30 s po poslední akci, pak 2,5 s, po 2 min 4 s, skrytá
    záložka 10 s). **Změřeno** (general log MariaDB + Playwright, hráč na otevřené mapě, rychlý režim): ~41 požadavků `udalosti.php` + ~9 `mlha_stav.php` za minutu,
    tj. ~49 HTTP požadavků, ~51 spojení do DB a po úpravě **~156 dotazů za minutu na hráče** (4 dotazy na polling: účet ze session, PJ světa, členství ve světě,
    události). Dřív ~206 — `dracak_vtt_je_pj_sveta()` se v jednom požadavku ptala na `svet.pj_ucet_id` dvakrát, teď si ho pamatuje (zakladatel světa se za provozu
    nemění). **Šest hráčů v rychlém režimu = ~300 požadavků a ~940 dotazů za minutu (5 požadavků / 16 dotazů za vteřinu)**, v klidu (4 s) zhruba třetina; pro
    MariaDB je to zanedbatelné, limit hostingu na počet souběžných PHP procesů se ukáže až v ostré hře (případné 503 / 508 od hostingu aplikace nezachytí — to je vidět jen v logu
    hostingu a hráči jako výpadek aktualizací). Další úspora by šla jen sloučením dotazů nebo delším intervalem; nedělá se, dokud se problém neprojeví.
    **Dodatek 2026-11-03 (zápis cesty):** když po zápisu únavy selže posun času světa (vlastní transakce), `cesta_vypocet.php` už nevrací 500 — opakované kliknutí by únavu připočetlo podruhé; vrátí `cas_chyba`, kalkulačka ji ukáže a PJ posune čas ručně.
    **Dodatek 2026-11-03 (vidění):** výpočet vidění při pollingu (`dracak_vtt_videni_skryte_tokeny`, jednou za poll, který nese události mapy s viděním) změřen na zatěžové mapě
    (4000×3000 px, buňka 50 px, 300 zdí, 9 pochodní, divák s nočním viděním 30 sáhů): **~26 ms na volání** (CPU, bez sítě). Šest hráčů v rychlém režimu = pod 0,1 s CPU za vteřinu; není co optimalizovat.
22. Rozvržení na tabletu/telefonu (panely, toolbar) [M] — **kontrola 2026-11-03 po raid panelu a hodinách světa:** na telefonu se horní lišta s hodinami PJ ořezávala a raid panel (pruh přes šířku) se kvůli pořadí pravidel ve stylu nezobrazoval jako pruh a překrýval lištu — opraveno (`mobil_lista_ui.js`); stejně tak stránka pravidel na telefonu (dlouhé adresy zdrojů a štítky v řádku vlastností natahovaly stránku na 612 px, teď se zalamují). Přehled všech stránek (hra, administrace, pravidla) na 390 a 820 px: bez vodorovného přetékání. **Dřívější kontrola 2026-10-28** (Playwright, telefon 390×844, tablet 820×1180 a 1180×820, hráč i PJ, mapa a
    deník): žádné vodorovné přetékání stránky, panely a lišta nástrojů se vejdou, deník na telefonu má záhlaví statické a záložky pod ním; skutečné zařízení a
    dotyková gesta (pinch, dlouhý stisk) dál čekají na zkoušku uživatele (scénář `docs/zkusebni-scenar-prvni-hra.md`)
23. Zkušební scénář „první ostrá hra“ (cca 12 kroků) a jeho projití na dvou
    zařízeních [S] — **scénář napsaný: `docs/zkusebni-scenar-prvni-hra.md`** (2026-10-28), projití na dvou zařízeních čeká na uživatele

*E. Vidění, světlo a stíny (přidáno 2026-10-14; zatím jen seznam, nic nestaví)*
24. ✔ (v1, 2026-11-02) **Stíny za zdmi** — živé vidění po buňkách gridu (ne plný polygon): hráč vidí osvětlené buňky, kam vede volný paprsek přes zdi a zavřené dveře; viz
    níže „Vidění a světlo“ a `docs/videni-a-svetlo-navrh.md`. Hladký polygonový stín místo kostiček zůstává možným vylepšením. [L]
25. ✔ **Dveře** (2026-11-02, migrace `0106`) — zeď s příznakem `dvere` a stavem `otevrene`; otevřené dveře neblokují pohyb, výhled ani střelu
    (`dracak_vtt_zdi_bez_otevrenych_dveri` v `includes/vtt.php`, použito v pohybu hráče, LoS i v hexové cestě souboje). Zdi kreslí PJ zaškrtnutím „Dveře“ v nástroji Zeď;
    klik PJ na dveře (nástroj Výběr) je otevře/zavře (`hra/api/zed_dvere.php`, událost `zed_zmena` — skrytým dveřím se hráčům nic neprozradí). **Hráči dveře otevírat nesmí
    (rozhodnutí uživatele 2026-11-02: jen PJ).** **Zamčené a tajné dveře zatím ne — uživatel je bude potřebovat později, zatím nejsou potřeba (25b, na seznamu).** Test: `tests/e2e/dvere_api.js`. [M]
26. ✔ (2026-11-02, migrace `0107`) **Zdroje světla** — světlo je vlastnost tokenu (`tokeny.svetlo_sahy`, `svetlo_typ`; PJ je nastaví v panelu tokenu: pochodeň 6, lucerna 8 / 4 / 2,
    hůl jako pochodeň / lucerna, vlastní dosah 0,5–60 sáhů; zóny Tma a Temnota viz níže); pochodeň na zdi = obecný token se světlem (může být skrytý). Čísla z knihy (h1685 b11183–b11185, b4601). Kouzlo Světlo (2 magy za sáh)
    zatím jen ručně jako vlastní dosah. PJ vidí na mapě přerušovaný kruh dosahu každého světla. [L]
27. ✔ (2026-11-02) **Vidění ve tmě, které se pohybuje s postavou** — infravidění (`tokeny.videni_infra_sahy`, PJ nastaví: trpaslík 20, lovec 50) ukáže tvory s teplou krví v dosahu a na přímý výhled
    (ne mapu, ne zdí; `studeny` je vynechá). **Noční vidění: viz „Stav mapy Noc a noční vidění“ níž.** [L]

*Vidění a světlo — stav (2026-11-02, rozhodnutí uživatele: „ve tmě vidí hráč jen to, co právě osvětlí družina“):* mapa má **Osvětlení: Světlo / Tma** (popover „Mlha války“, jen PJ; výchozí
Světlo = dosavadní chování beze změny). Na mapě **Tma** (a se zapnutou mlhou a gridem) server (`includes/vtt_videni.php`) počítá po buňkách: osvětlené buňky (v dosahu světla, volný paprsek) ∩ buňky, kam
vede volný paprsek od hráčových tokenů; co hráč viděl, zůstává ztlumeně jako paměť (tabulka `mlha_valky`), co nikdy neviděl, je černé. **Server hráči neposílá tokeny, které nevidí** (seznam tokenů
i události pohybu, změn, životů a příchodu v `hra/api/udalosti.php`); družina (postavy hráčů) se vidí vždy, PJ vidí vše. Světlo, infravidění, dveře a osvětlení nastavuje jen PJ.
**Boj ve tmě (2026-11-02, bez DDL):** na mapě Tma má hráčova postava, která protivníka nevidí (podle jejího vidění — světlo družiny, infravidění), postih **−5** k hodu na útok (útočník) nebo
na obranu (obránce); PJ v okně výzvy přepne „vidí / nevidí −5 / tuší, kde stojí −4 / noční vidění −2“ (h1613 b10738, lupič h630 b5663). Zasáhne-li útočník s tímto postihem, zranění se počítá, jako by ho neměl
(`dracak_vtt_utok_vyhodnot`, v odpovědi `tma_bonus`). Za nestvůry a NPC se postih sám nenavrhuje (kniha neříká, kdo vidí ve tmě) — PJ ho nastaví ručně. **Ztrátu iniciativy a „útok jen v kole, kdy na něj
protivník zaútočí“ (b10738) automaticky neřešíme** — okno na to upozorní, hlídá to PJ. Test: `tests/e2e/boj_ve_tme_api.js`.
**Zóny kouzel Tma a Temnota (2026-11-02, bez DDL a bez ručního SQL):** zóna je token (obvykle obecný, skrytý před hráči) s `svetlo_typ` = `tma` / `temnota` a poloměrem v `svetlo_sahy`
— stejné dva sloupce jako světlo, jen se z nich v `dracak_vtt_videni_zdroje()` vynechají a čtou se zvlášť v `dracak_vtt_videni_zony()`. PJ je nastaví v panelu tokenu (Vzhled → Světlo → skupina
„Zóna tmy“): **Tma (Obscurum)** zadá poloměr ručně (4 magy za sáh poloměru → magy ÷ 4, h318 b3600), **Temnota druida** má pevných 300 sáhů (h193 b1936); obě se ukládají se „skrýt před hráči“.
Pravidla ve výpočtu: **Tma** — buňky uvnitř se nikdy neosvětlí, zdroj světla uvnitř nesvítí vůbec, infravidění nefunguje, když v zóně stojí pozorovatel nebo cíl (b3601). **Temnota** — světlo je v ní vidět
jen na 30 coulů = 0,3 sáhu (b1938; 1 sáh = 100 coulů, `content/pravidla-hrac.html` řádek 37), tedy v praxi jen buňka, na které zdroj stojí; infravidění má desetinový dosah, když v zóně stojí pozorovatel
nebo cíl (b1939). Zóna sama nesvítí. Test: `tests/e2e/zony_tmy_api.js`. **Neřešíme (rozhodnutí PJ):** zákaz ohnivých a světelných kouzel v Temnotě (b1938), úvaha o tom, že Tma by měla stínit i paprsky
procházející zónou (zóna působí jen na buňky a zdroje uvnitř), Tma za 6 magů — „šero jako od malé pochodně, infravidění funguje“ (b3601): kniha dosah malé pochodně nemá, proto se zóna nezakládá a PJ
dá na střed obyčejné světlo s vlastním dosahem. Lovcův **Příkrov stínů** (h1003 b7357–b7359, 5 magů za 60 sáhů, uhasí zdroje světla, Lovec v něm vidí) zatím není — vyžaduje výjimku „vidí ten, kdo ji vyvolal“.
**Stav mapy „Noc“ a noční vidění (2026-11-02, rozhodnutí uživatele: „stav noc chci, klidně až 5 stavů tmy/světla“; ruční SQL: migrace `0108`, sloupec `tokeny.videni_noc_sahy`):**
osvětlení mapy má stavy v konstantě `DRACAK_VTT_OSVETLENI` (`includes/vtt_videni.php`; rozhodnutí uživatele 2026-11-03 „tmu na 5“ — **stupnice tmy z Lektvaru sovích očí, h3243 b3244–b3249**): **Světlo** (dosavadní mlha), **Pološero** (soumrak, úsvit — jako den),
a **pět stupňů tmy** od nejsvětlejšího: **Řídká tma** (měsíc v úplňku), **Tma** (slabě svítí hvězdy; klíč `noc`), **Hustá tma** (bezvětrná noc, hustý les) a **Naprostá tma** (podzemí, kouzelná tma; klíč `tma`). Ve tmě hráč vidí jen osvětlené místo; **tvor s nočním viděním**
(PJ mu v panelu tokenu zadá dosah v sázích — kniha číslo nemá) vidí všechny buňky do dosahu, kam vede volný paprsek (ne přes zeď a zavřené dveře), **v řídké tmě, tmě i husté tmě; v naprosté tmě noční vidění nepomáhá** (h630 b5663: „s absolutní tmou v podzemí či v kouzlem vytvořené tmě mu noční vidění
nepomáhá“) a nepomáhá ani uvnitř zón Tma a Temnota (zevnitř ani dovnitř). Boj: lupič (hráčova postava) s nočním viděním má ve stupních, kde noční vidění funguje, proti cíli, kterého nevidí, postih **−2** místo −5 (`'nocni'` v `dracak_vtt_videni_tma_volba`), cíl v dosahu vidí (žádný postih).
**Kniha u žádného stupně neříká, jak daleko se vidí bez světla** — stupně se liší tím, co z knihy plyne (kde funguje noční vidění); postihy lektvaru sovích očí (hustá tma −1 k boji, tma 0, řídká tma ostře, pološero −1; svíčka −1, lucerna −2…) engine nepočítá (lektvar sám v enginu není). Test: `tests/e2e/noc_videni_api.js` + rozšířený `boj_ve_tme_api.js`.
Úplně zbývá: sdílené vidění družiny mezi hráči (dnes každý hráč vidí svými tokeny a světla družiny
osvětlují všem), souboj panel (pořadí iniciativy) skrytou nestvůru ve tmě pořád ukáže, pokud ji PJ označil jako viditelnou.

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
