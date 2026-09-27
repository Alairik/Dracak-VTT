# Návrh datového modelu VTT vrstvy — entity a pole (draft v1)

Konsolidace celé diskuze o VTT (real-time přes polling, automatizace pravidel,
LoS pro souboj, worldbuilding pro PJ). Navazuje na `docs/db-model-navrh-v1.md`
— tahle vrstva na pravidlovou databázi jen odkazuje přes FK, nic z ní
neduplikuje ani nemění. Pracovní podklad, ne finální schéma.

---

## Rozhodnutí, která už platí

- **Postava existuje v jednom světě, ne napříč víc světy.** `postavy.svet_id`
  je NOT NULL, žádná M:N vrstva instancí navíc.
- **Real-time = krátký polling (1–2 s), ne WebSocket.** Sdílený PHP hosting
  neumožňuje persistentní spojení ani vlastní port. Architektura: stavové
  tabulky (UPDATE, aktuální stav) + append-only log `svet_udalosti` (jen
  INSERT, klient se ptá na delty od posledního viděného ID).
- **LoS pro souboj ≠ dynamické osvětlení.** Boolean průsečík paprsku se zdmi
  (levné, v1) je jiný problém než plný výpočet viditelnostního polygonu a
  jeho vykreslení (drahé, v2 — zatím nenavrhováno).
- **Odhalení PJ poznámky/bodu na mapě je ruční akce PJ**, ne odvozený stav z
  mlhy války. Prostorové "kam token dohlédne" a informační "co skupina ví"
  jsou dva nezávislé mechanismy.
- **Míra automatizace je sada nezávislých přepínačů na úrovni světa**, ne
  jeden globální switch — různé stoly chtějí jinou kombinaci.
- **3D kostky (`assets/js/vendor/dice-box`) jsou čistě vizualizace** nad
  událostí `kostka_hod` — nepřidávají žádnou tabulku navíc.
- **Krok zpět se řeší přes event log, ne přes samostatný mechanismus** —
  proto payload stavových událostí musí nést i hodnotu *před* změnou, ne jen
  po ní.

## Hierarchie

```
Svět (kampaň, majitel = PJ)
 ├─ Mapy (N: přesně jedna nemusí být "světová", zbytek "zóna"/bitevní)
 │   ├─ Zdi (LoS/pohyb)
 │   ├─ Body na mapě (piny, jen smysluplné na světové mapě, ale technicky
 │   │   nevázané na typ)
 │   ├─ Tokeny (postavy i instance nestvůr, umístěné na téhle mapě)
 │   └─ Mlha války (per hráč, per mapa)
 ├─ Postavy (patří světu, ne mapě — token je jen jejich průmět na mapu)
 ├─ Poznámky (nezávislé na konkrétní mapě — frakce, NPC, lore bez souřadnice)
 └─ Události (jeden log pro celý svět, volitelně vázaný na mapu)
```

---

## Entity

### Svět (`svet`)
- `id`, `nazev`, `popis`
- `pj_ucet_id` — FK `ucty.id`, majitel/vypravěč
- `aktivni_mapa_id` — FK `mapy.id`, NULL — co se teď zobrazuje hráčům;
  přepnutí je zápis sem + event `aktivni_mapa_zmena`, klienti se přepnou na
  dalším pollu (viz mechanismy níže)
- `posledni_shrnuti` — text, "kde jsme skončili"
- `pripraveno_priste` — text, co má PJ nachystané — obě pole přímo na
  světě, ať je PJ nemusí hledat mezi poznámkami
- `auto_hod_kostkou`, `auto_aplikace_efektu`, `auto_vyhodnoceni_pasti`,
  `auto_zranitelnost` — BOOL, výchozí `0` (ruční režim); UI může nabídnout
  přednastavené profily (Ruční/Asistovaný/Automatický), ale ukládá se to
  vždy jako tahle čtveřice
- `vytvoreno_at`

*Pozn. k migraci:* `aktivni_mapa_id` ukazuje na `mapy`, `mapy.svet_id`
ukazuje zpátky na `svet` — cyklická vazba, `aktivni_mapa_id` proto přidat až
druhou `ALTER TABLE` migrací, ne v tom samém `CREATE TABLE`.

### Svět ↔ Hráči (`svet_hraci`)
- `svet_id`, `ucet_id` — kdo má k tomuhle světu přístup (M:N)
- PK `(svet_id, ucet_id)`

### Postava (`postavy`)
- `id`, `svet_id` FK NOT NULL, `vlastnik_ucet_id` FK `ucty.id`
- `nazev`, `rasa_id` FK `rasy.id`, `povolani_id` FK `povolani.id` (obojí
  pravidlová DB)
- `uroven`, `aktualni_hp`, `max_hp`
- `poznamky` — volný text, hráčovy vlastní (ne PJ worldbuilding, to je
  `svet_poznamky`)
- `vytvoreno_at`

Pozice postavy se neřeší tady — to dává `tokeny` (postava může mít token na
světové mapě = "kde teď skupina je", i na bitevní mapě = "kde stojí v boji";
je to průmět, ne vlastnost postavy).

### Postava ↔ Předmět (`postava_predmety`)
- `id`, `postava_id` FK, `predmet_id` FK `predmety.id` (pravidlová DB),
  `mnozstvi` — stejný vzor jako `lektvar_suroviny` v pravidlové DB

### Mapa (`mapy`)
- `id`, `svet_id` FK
- `nazev`, `typ_mapy` ENUM(`svet`,`zona`) — přepínač z požadavku
- `obrazek_cesta` — nahraný soubor
- `sirka_px`, `vyska_px`
- `grid_velikost_px` NULL (NULL = bez gridu — typicky světová mapa),
  `grid_posun_x`, `grid_posun_y` (zarovnání gridu vůči obrázku)
- `vytvoreno_at`

### Zeď (`zdi`)
- `id`, `mapa_id` FK
- `x1`, `y1`, `x2`, `y2` — segment v px
- `blokuje_pohyb` BOOL DEFAULT 1, `blokuje_vystrel` BOOL DEFAULT 1 —
  oddělené, protože ne každá zeď musí blokovat obojí stejně (okno vs. plot)

### Mlha války (`mlha_valky`)
- `id`, `mapa_id` FK, `ucet_id` FK
- `bitmapa` MEDIUMBLOB — odkrytá oblast jako maska, přepisuje se (UPDATE),
  není to log
- `aktualizovano_at`
- UNIQUE `(mapa_id, ucet_id)` — per hráč, per mapa, perzistentní mezi
  připojeními

### Bod na mapě (`mapa_body`)
- `id`, `mapa_id` FK
- `x`, `y`, `nazev`, `typ` (město/vesnice/nebezpečí/quest/jiné — jen
  vizuální ikona)
- `poznamka_id` — FK `svet_poznamky.id`, NULL (bod nemusí mít připojenou
  poznámku, může být čistě orientační)
- `viditelny_hracum` BOOL DEFAULT 0 — **ruční přepínač PJ**, nic
  automatického
- `vytvoreno_at`

### Poznámka o světě (`svet_poznamky`)
- `id`, `svet_id` FK
- `nazev`, `typ` ENUM(`mesto`,`vesnice`,`frakce`,`npc`,`udalost`,`obecne`)
- `obsah` TEXT
- `vytvoreno_at`, `upraveno_at`

Záměrně bez povinné vazby na `mapa_body` — frakce nebo vztah mezi NPC nemá
souřadnici a nemělo by se k ní nutit.

### Token (`tokeny`)
- `id`, `mapa_id` FK
- `typ_entity` ENUM(`postava`,`nestvura_instance`), `entita_id` —
  polymorfní, bez DB-level FK (řeší aplikační vrstva)
- `x`, `y`, `z_poradi`
- `viditelny_hracum` BOOL DEFAULT 1
- `vytvoreno_at`

Vlastnictví (kdo smí hýbat) se neduplikuje na tokenu — u `postava` se
odvozuje z `postavy.vlastnik_ucet_id`, u `nestvura_instance` smí hýbat jen
PJ/admin.

### Instance nestvůry (`nestvura_instance`)
- `id`, `mapa_id` FK
- `nestvura_id` — FK `nestvury.id` (pravidlová DB, šablona/statblok)
- `nazev_instance` — např. "Goblin #2", pro rozlišení víc kopií stejné
  příšery ve stejném střetu
- `aktualni_hp`
- `vytvoreno_at`

### Aktivní efekt (`aktivni_efekty`)
- `id`
- `typ_entity` ENUM(`postava`,`nestvura_instance`), `entita_id` —
  polymorfní, stejný vzor jako u tokenu
- `efekt_id` — FK `efekty.id` (pravidlová DB)
- `zbyva_kol` INT NULL
- `vytvoreno_at`

Váže se na entitu, ne na token — efekt musí přežít i zavření/znovuotevření
scény, token je jen dočasné zobrazení.

### Události světa (`svet_udalosti`)
- `id` BIGINT AUTO_INCREMENT PK
- `svet_id` FK
- `mapa_id` FK NULL — NULL u událostí nevázaných na konkrétní mapu (chat)
- `typ` ENUM(`token_presun`,`token_pridan`,`token_smazan`,`kostka_hod`,
  `efekt_aplikovan`,`efekt_konci`,`chat`,`mapa_bod_pridan`,
  `mapa_bod_odhalen`,`aktivni_mapa_zmena`)
- `payload` JSON — u `token_presun` musí obsahovat **i původní** x/y, ne jen
  nové (kvůli kroku zpět)
- `ucet_id` FK
- `vytvoreno_at`
- INDEX `(svet_id, id)`

---

## Mechanismy (nejsou tabulky, ale postup)

- **Krok zpět (10 kroků):** vezmi posledních N stavových událostí
  (`token_presun` apod.) v `svet_udalosti` pro danou mapu a aplikuj v
  obráceném pořadí hodnotu "před". Funguje jen díky tomu, že payload nese
  obě hodnoty — bez toho by šlo jen dopředu.
- **Polling:** `GET stav.php?svet_id=X` — počáteční snapshot (mapy, tokeny,
  aktivní efekty, mlha války daného hráče) + `posledni_udalost_id`.
  `GET udalosti.php?svet_id=X&od=Y` — delta z logu, voláno z JS každou
  1–2 s.
- **Přepnutí aktivní mapy:** `UPDATE svet SET aktivni_mapa_id=?` + INSERT
  eventu `aktivni_mapa_zmena` ve stejné transakci — klienti se přepnou na
  dalším pollu automaticky, není potřeba zvlášť "žádost o přepnutí".
- **Automatizace:** čtveřice bool sloupců na `svet` řídí, jestli daný
  endpoint (hod kostkou / aplikace efektu / vyhodnocení pasti /
  zranitelnost) rovnou zapíše výsledek, nebo ho jen navrhne a čeká na
  potvrzení PJ.

## Vztah k pravidlové databázi

Tahle vrstva pouze **odkazuje** (FK) na tabulky spravované v druhém tasku —
nic z nich sem nekopíruje: `ucty`, `rasy`, `povolani`, `kouzla`,
`schopnosti` (`zvlastni_schopnosti`), `predmety`, `lektvary`, `efekty`,
`pasti`, `nestvury`, `kody_zranitelnosti`.

## Otevřené otázky / TBD

- Hex grid vedle čtvercového, nebo stačí čtvercový v1?
- Dveře jako speciální stav zdi (otevřeno/zavřeno mění `blokuje_pohyb` za
  běhu), nebo mimo rozsah v1?
- Může instance nestvůry přežít mezi mapami (recidivující boss), nebo je to
  vždy nová instance na mapu? Zatím model počítá s druhou variantou.
- Přesný formát/rozlišení `mlha_valky.bitmapa` — doladit až s konkrétní
  frontend implementací (kolik px na buňku, jak kódovat masku kompaktně).
- Čištění `svet_udalosti` (poroste neomezeně) — runtime úklid, ne migrace,
  ale potřeba rozhodnout retenci (kolik dní/událostí držet).
