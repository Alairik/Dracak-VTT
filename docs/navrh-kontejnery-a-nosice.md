# Návrh: kontejnery (vaky, truhly…) a nosiče nákladu

Podklad k rozhodnutí z `docs/stav-projektu.md` (Fáze 1b). Nic z toho zatím není
v kódu — čeká se na odsouhlasení modelu.

## Proč to vůbec stojí

Inventář postavy je dnes tabulka `postava_predmety` s jedním řádkem na dvojici
(postava, předmět) a počtem kusů (`mnozstvi`). Hlídá to unikátní index
`postava_predmet (postava_id, predmet_id)`.

To stačí na "má 3 dýky". Nestačí to na "má dva vaky a v každém jiné věci",
protože oba vaky jsou stejný předmět → jeden řádek s `mnozstvi = 2`, a řádek
nemá kam napsat, co je v kterém vaku. Totéž pro "meč je v truhle na voze, druhý
meč nosí postava". Aby šlo říct, **kde** věc je, je potřeba model, který jednu
fyzickou věc od druhé rozliší.

Dvě cesty:

### Varianta A — upravit stávající tabulku (potřebuje tvůj souhlas s DROP)
- Zrušit unikátní index `postava_predmet` (`DROP INDEX`, destruktivní příkaz,
  `migrate.php` ho odmítne, smí ho jen schválit uživatel a provést ručně).
- Přidat do `postava_predmety` sloupec `kontejner_id` (odkaz na jiný řádek téže
  tabulky = "tahle věc je v tom vaku").
- Každý fyzický vak je vlastní řádek. Váha se sčítá po stromu.
- Riziko: sahá do tabulky, na kterou se dnes váží deník, družina, kalkulačka
  cesty a API inventáře — všechno by se muselo upravit najednou.

### Varianta B — nové tabulky vedle, bez DROP (doporučuju)
- **Nic se neruší ani nepřepisuje.** Stávající inventář zůstává pro volné věci.
- Nová tabulka `postava_kontejnery`: jedna řádka = jeden fyzický vak/truhla
  (`postava_id`, `predmet_id` = typ kontejneru, volitelný `nazev`, `nosic`).
- Nová tabulka `kontejner_obsah`: (`kontejner_id`, `predmet_id`, `mnozstvi`) =
  co v něm je. Věc přesunutá do vaku se ubere z volného inventáře a přidá sem.
- Váha postavy = volné věci + vlastní váha kontejnerů + obsah kontejnerů.
- `nosic` říká, kdo kontejner nese: `postava` (výchozí) nebo `zvire` (zvíře,
  na kterém postava jede — `postavy.doprava_*` už existuje). Náklad na zvířeti se
  pak do kalkulačky cesty přičte zvířeti místo postavě (dnes tam zvíře dostává
  jen váhu jezdce a ručně zadaný náklad). Povoz se trvale nevlastní — jeho
  náklad se dál zadává při cestě.
- Postup: 2× `CREATE TABLE` (ruční SQL, jako vždy kopírovatelný blok) + 1×
  `ALTER TABLE predmety ADD kapacita_mn` + data kontejnerů z ceníku.

## Data kontejnerů (ceník h2055, ověřeno v pravidlech)

Kontejnery mají v ceníku **kapacitu v mn a vlastní váhu**: torna kožená 800/30
mn, vaky (malý 140/9 … nákladní velký 2000/80), sedlové vaky (250/12, 500/24,
750/36), soudky a sud, truhly (malá/střední/velká dřevěná i železná: kapacita
200/500/1500 mn, váha 60–500 mn), trakař 2000/750, toulec, pochvy…
Do `predmety` se zatím nic z toho nenačetlo (DB má jen zbraně, zbroje, suroviny,
artefakty) a `predmety.typ` nemá hodnotu pro běžné vybavení — musel by se rozšířit
ENUM (`migrate.php` umí ověřit přidání hodnoty).

## Co potřebuju od tebe

1. **A, nebo B?** (B je moje doporučení — nevyžaduje DROP a nic nerozbije.)
2. **Import ceníku:** jen kontejnery (doporučuju začít tím), nebo celý ceník?
3. **Kapacita:** tvrdý limit (nejde přidat nad kapacitu), nebo jen varování, které
   PJ přepíše?
