# Export a import oblasti — návrh (2026-11-09)

**Stav: NÁVRH, nic z toho není postavené. Do databáze se kvůli tomu zatím nesáhlo a sahat nemá (proč → sekce 8).**
Uživatel 2026-11-09: „připrav jen to nejnutnější a případně na to vytvoř MD, rozmyslel jsem se, to je velká úprava, která počká.“

## 1. K čemu to má být

Tři přání uživatele (2026-11-09), všechna vedou na jednu věc:

1. **Loď, přístav, portál do jiné dimenze** — družina někde projde a je „jinde“.
2. **Svět, co jde předat dál** — vzít svůj svět a přenést ho.
3. **Kus cizí tvorby** — někdo mi udělá quest pro novou oblast a já ho chci plynule napojit na svůj svět.

**Rozhodnutí uživatele 2026-11-09: světy se nenapojují, jeden svět to pojme.** Souhlas i z technické strany — přístav i portál do
jiné dimenze jsou z pohledu hráče totéž co dnešní přechod mezi mapami (pin s cílovou mapou, `includes/vtt_prechod.php`), jen na delší
vzdálenost. Dimenze je prostě oblast, na kterou vede přechod odjinud.

**Z toho plyne, co se staví:** ne „export světa“, ale **export a import OBLASTI** — vybraných map se vším, co na nich je, do souboru,
a import toho souboru do jiného (existujícího) světa. Kamarádův quest přijde jako nová oblast do mého světa, napojím ji pinem, hotovo.
Export celého světa je pak jen zvláštní případ: vyber všechny jeho mapy.

## 2. Proč to dnes nejde

Tři tvrdé překážky, všechny ověřené v kódu, ne odhad:

- **Záloha je celá databáze, ne svět.** `includes/zaloha.php` projde `SHOW TABLES` a vysype všechno. Na „vezmi můj svět a dej ho jinam“
  je nepoužitelná (přepsala by cílovou databázi i s cizími světy a účty).
- **Čísla záznamů jsou globální.** Každá tabulka má vlastní `AUTO_INCREMENT` napříč světy, takže kamarádova mapa č. 7 se pere s mojí
  mapou č. 7. Import proto nemůže být prosté nalití dat — musí **všechna čísla přečíslovat a opravit všechny odkazy mezi nimi**.
  Tohle je ta skutečná práce, ne formát souboru.
- **Přechod mezi světy je výslovně zakázaný.** `dracak_vtt_prechod_vstoupit()` hledá cílovou mapu přes `WHERE id = ? AND svet_id = ?`,
  jinak vrátí „Cílová mapa nenalezena“. (Po rozhodnutí z bodu 1 to ale zakázané zůstat může — není potřeba to měnit.)

Dobrá zpráva: **data jsou na to připravená líp, než se čekalo.** Všechno visí na `svet_id` nebo přes mapu a odkazy jsou skutečné cizí
klíče (`information_schema` je zná), takže se graf závislostí dá vygenerovat, ne hádat. Migrace `0115` (NPC je bytost světa) tomu navíc
pomohla: NPC už nevisí na mapě, ale na světě, takže oblast může nést i NPC, která zrovna nikde nestojí.

## 3. Co do oblasti patří a co ne

Tohle je jádro návrhu. Tabulky se dělí na čtyři druhy a s každým se nakládá jinak.

### A) Jde do exportu (vlastní obsah oblasti)

| Tabulka | Váže se na | Poznámka |
|---|---|---|
| `mapy` | svět | vybrané mapy = definice oblasti; `obrazek_cesta` ukazuje na soubor v `uploads/mapy/` |
| `zdi` | mapa | |
| `mapa_kresby` | mapa | |
| `mapa_body` (piny) | mapa + svět | **pozor:** `cilova_mapa_id` může vést ven z oblasti (viz 5c) |
| `tokeny` | mapa | jen tokeny NPC a vlastní (`obecny`); tokeny postav ne (viz C) |
| `nestvura_instance` | svět + mapa + lokace | včetně `jedinecny` a `data` (vlastní čísla NPC) |
| `nestvura_instance_vybava` | instance NPC | |
| `nestvura_staty_sveta` | svět + druh nestvůry | čísla druhu — bez nich by NPC po importu měla jiné hodnoty |
| `svet_obrazky` | svět | knihovna obrázků; soubor v `uploads/obrazky/` |
| `svet_poznamky` | svět (+ mapa, postava) | jen poznámky PJ bez vazby na postavu (viz 5d) |

### B) NEJDE do exportu — společný katalog pravidel

`nestvury` (208), `predmety` (518), `kouzla` (678), `lektvary` (88), `efekty` (126), `zvlastni_schopnosti` (342), `rasy`, `povolani`,
`pasti`, `finty`, číselníky… To je **přepis knihy a je ve všech instalacích stejný**. Export na ně jen **odkazuje jménem a číslem**;
import si je dohledá u sebe. Kdyby se exportovaly, soubor nabobtná a při importu by se katalog duplikoval.

**Riziko, se kterým je potřeba počítat:** když kamarád použije homebrew nestvůru, kterou já nemám, import ji nenajde. Řešení v sekci 6.

### C) NEJDE do exportu — patří hráčům, ne oblasti

`postavy` a všechno na nich (`postava_predmety`, `postava_lektvary`, `postava_kontejnery`, `kontejner_obsah`, `postava_zna_kouzlo`),
`ucty`, `svet_hraci`, `ucet_opravneni`. Oblast je **místo**, ne družina. Importovaná oblast se naplní mými postavami, ne cizími.

### D) NEJDE do exportu — provozní stav, zahodit

`mlha_valky` (mlha je per účet — cizí účty u mě neexistují), `svet_udalosti` (log pro polling), `kolo_stav`, `kolo_ucastnik`,
`kolo_iniciativa`, `souboj_vyzva`, `souboj_pohyb` (rozehraný souboj), `aktivni_efekty` (nasazené efekty), `svet_poznamka_precteno`,
`svet_poznamka_sdileni`. Importovaná oblast je čerstvá: nikdo v ní nic neodhalil a nikdo se v ní nepere.

## 4. Formát souboru

**ZIP**, protože oblast nese i obrázky map:

```
oblast.zip
  manifest.json     — verze formátu, jméno oblasti, datum, verze aplikace, seznam map
  data.json         — všechna data z 3A, čísla jako dočasná (viz 5a)
  uploads/mapy/*    — podklady map
  uploads/obrazky/* — obrázky tokenů z knihovny světa
```

Samotný JSON bez obrázků by znamenal, že importovaná mapa je prázdná plocha — nepoužitelné.

## 5. Čtyři věci, které to dělají těžkým

**a) Přečíslování.** Export nesmí vyvážet skutečná čísla, ale **dočasná** (`mapa:1`, `npc:3`). Import jde tabulky v pořadí závislostí
(mapy → zdi/kresby/piny/tokeny → NPC → výbava), každému záznamu přidělí nové skutečné číslo a **průběžně si drží překladovou tabulku**
staré → nové. Teprve pak doplní odkazy. Pořadí se dá odvodit z cizích klíčů, ručně se psát nemusí.

**b) Odkazy dovnitř oblasti.** `tokeny.entita_id` ukazuje podle `typ_entity` na tři různé tabulky (postava / NPC / nic) — klasický
polymorfní odkaz, který cizí klíč neuhlídá. Překlad musí větvit podle `typ_entity`.

**c) Odkazy ven z oblasti.** Pin s `cilova_mapa_id` mimo exportované mapy, NPC s `lokace_mapa_id` mimo oblast. Návrh: **při exportu takový
odkaz zahodit a zapsat do manifestu jako „visící přechod“**; po importu je PJ v jednom okně dopojí na svoje mapy. To je přesně ten okamžik,
kdy se cizí quest „napojuje“ na můj svět — a je dobře, že to dělá člověk, ne stroj.

**d) Poznámky navázané na postavu.** `svet_poznamky.postava_id` ukazuje na cizí postavu, která se neexportuje → takovou poznámku buď
vynechat, nebo vazbu zahodit. Návrh: vynechat, poznámka o cizí postavě je v cizím světě k ničemu.

## 5b. „Stačí dát každé zóně a NPC unikátní kód a vyexportovat to do CSV?“ (otázka uživatele 2026-11-09)

Napůl ano. Unikátní kód je potřeba, ale **sám o sobě problém nevyřeší**, a CSV je na to špatný nosič.

**Co unikátní kód (např. UUID) vyřeší:** poznání, že „tohle NPC už jsem jednou importoval“. Díky tomu jde import spustit dvakrát,
aniž by se obsah zdvojil, a jde poslat opravenou verzi oblasti („aktualizuj, co se změnilo“). To je užitečné a do formátu patří.

**Co nevyřeší:** databáze uvnitř sebe neodkazuje kódy, ale **čísly** (`tokeny.mapa_id` = 7, `nestvura_instance.lokace_mapa_id` = 7).
Těch čísel se zbavit nejde — jsou to primární klíče. Takže i s unikátními kódy musí import pořád **každému záznamu přidělit nové číslo
a přepsat všechny odkazy na něj** (sekce 5a). Kód to nenahradí, jen k tomu přidá stabilní jméno. Jinak řečeno: ta práce, kvůli které je
to na dny a ne na odpoledne, je přečíslování, a to zůstává tak jako tak.

**K „podmapám“:** mapy v databázi vnořené nejsou. Strom míst, který ukazuje „Mapa míst“ v liště, se skládá až za běhu z pinů
(`dracak_vtt_mapa_strom()` v `includes/vtt_mapa_body.php` čte `mapa_body.cilova_mapa_id`). Hierarchie je tedy jen síť odkazů mezi mapami —
což je přesně důvod, proč musí export řešit odkazy vedoucí ven z oblasti (5c), a proč se nedá vyvézt „mapa s podmapami“ jako jeden celek.

**Proč ne CSV:** oblast není jedna tabulka, ale zhruba deset provázaných (mapy, zdi, kresby, piny, tokeny, NPC, výbava NPC, čísla druhů,
obrázky, poznámky). CSV neumí vazby ani vnoření, takže by z toho byl zip deseti CSV souborů plus obrázky — tedy nemotornější JSON.
Navíc CSV nemá kam dát binární obrázky map a u českých textů s čárkami, uvozovkami a středníky se escapování pravidelně rozbíjí.
JSON tohle všechno řeší zadarmo a kód na něj už v projektu je. Jeden CSV export by dával smysl leda jako **přehled pro člověka**
(„vypiš mi všechna NPC ve světě do tabulky“) — ale to je jiná funkce než import a dá se udělat kdykoli samostatně.

## 6. Chybějící katalog u importu

Když soubor odkazuje na nestvůru/předmět/kouzlo, které u sebe nemám:

1. Import **nejdřív jen zkontroluje** a vypíše seznam, co chybí („Nestvůra Bažinný troll — není v bestiáři“).
2. PJ se rozhodne: **nahradit** (vyber jinou), nebo **vynechat** (NPC se neimportuje), nebo **import zrušit**.
3. Teprve po odsouhlasení se zapisuje.

Import bez téhle kontroly by tiše vyrobil NPC bez pravidel a projevilo by se to až v boji.

## 7. Kroky realizace (až na to přijde řada)

| # | Krok | Co v něm je |
|---|---|---|
| 1 | Export oblasti | výběr map, sesbírání dat podle 3A, dočasná čísla, ZIP ke stažení |
| 2 | Kontrola importu | přečíst ZIP, ověřit verzi formátu, vypsat chybějící katalog a visící přechody — **nic nezapisovat** |
| 3 | Vlastní import | přečíslování, zápis v pořadí závislostí, v jedné transakci, rozbalení obrázků |
| 4 | Dopojení | okno pro PJ: visící přechody → moje mapy |
| 5 | Testy | export → import do prázdného světa → kontrola, že sedí počty a odkazy; import souboru s chybějícím katalogem; opakovaný import téhož souboru |

**Odhad: několik dní práce**, ne odpoledne. Největší kus je krok 3 a jeho testy.

## 8. Proč se teď do databáze nesahá

Zvažovalo se přidat sloupce dopředu („ať je to připravené“). **Nedělat to**, ze tří důvodů:

- Databáze dnes **nic nechybí**: všechno visí na `svet_id` nebo přes mapu a odkazy jsou cizí klíče, ze kterých se pořadí importu odvodí samo.
- Jediné, co by export potřeboval navíc, je **stabilní identita napříč instalacemi** (aby se poznalo, že „moje“ a „tvoje“ nestvůra je tatáž).
  Řeší se ale **jménem z katalogu v souboru**, ne sloupcem v databázi — katalog je přepis knihy a je všude stejný.
- Sloupec přidaný „do zásoby“ pro funkci, která se bude stavět za měsíce, se skoro jistě trefí vedle. Až bude krok 1 hotový, bude jasné, co
  doopravdy chybí — a přidá se to jednou migrací, ne třemi.

**Jedna věc stojí za pozornost, až na to dojde:** `nestvura_instance.svet_id` a `lokace_mapa_id` (migrace 0115) jsou obyčejné sloupce
**bez cizího klíče**. Dnes to nevadí, protože aplikace mapy mazat neumí (`DELETE FROM mapy` není nikde v repu). Kdyby mazání map přibylo,
je potřeba buď cizí klíč s `ON DELETE SET NULL`, nebo to ošetřit v kódu — jinak by v zásobě zůstala NPC ukazující na mapu, která už není.

## 9. Co z toho plyne pro teď

Nic se nestaví. Až na to dojde řada (po zkušebním večeru a po MVP), začíná se krokem 1 ze sekce 7. Tenhle dokument je zadání.
