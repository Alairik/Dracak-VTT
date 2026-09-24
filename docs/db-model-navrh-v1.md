# Návrh databáze pravidel DrD + domácí pravidla — entity a pole (draft v1)

Konsolidovaný zápis toho, na čem jsme se zatím shodli. Slouží jako pracovní podklad, ne finální schéma — budeme na něm postupně stavět.

## Rozhodnutí, která už platí

- **Jedna sdílená databáze.** Core/homebrew se nerozlišuje jako gating pole u obsahu.
- **Zažité kódy zůstávají viditelné.** Kódy zranitelnosti (A–P) a oborů magie (ČP/EU/EO/IL/MA/VI/PS/PO) se v UI vždy zobrazují spolu s českým názvem (kód + název pohromadě), nikdy jen kód samotný.
- **4stupňová škála výsledku testu:** `fatální_neúspěch / neúspěch / úspěch / fatální_úspěch` — obecný enum použitelný na Dovednosti, Schopnosti i Pasti.
- **Přístup/role řešit odděleně od obsahu.** Kdo smí co upravovat (PJ vlastní kouzla, hráč návrh rasy) se řeší přes roli + kampaň/stůl (scope), ne přes pole na entitě obsahu.
- **Postava/instance postavy a runtime stav boje (Aktivní efekt) jsou samostatná vrstva** nad touto databází pravidel — zde je nenavrhujeme, jen na ně necháváme háček (`efekt_id`, `past_id` apod.).

---

## Entity

### Rasa
- `id`, `název`, `popis`
- `bonusy_vlastnosti` — M:N vazba na Vlastnost s hodnotou modifikátoru (ne prosté pole, rasa jich má víc)
- `povolené_povolání` — M:N na Povolání
- `rasové_schopnosti` — M:N na Zvláštní schopnost
- `rodič_rasa` — self-reference (rasové varianty/klany, např. Kroll — domácí rozšíření)
- `jazyky` — M:N na Jazyk

### Povolání
- `id`, `název`, `typ` (základní / větev)
- `rodič_povolání` — self-reference (Bojovník a Šermíř jsou větve Válečníka)
- `odemyká_se_od_úrovně` — pro větve
- `používá_magenergii` (bool)
- `primární_vlastnost`
- `SUM_základ`, `SOM_základ`, `ZSM_základ` — mentální souboj, tam kde relevantní
- `zkušenostní_tabulka_id` — FK na Tabulka zkušeností (homebrew varianty se liší povolání od povolání)

### Zvláštní schopnost / Dovednost
- `id`, `název`, `popis`
- `povolání` — M:N (schopnost může sdílet víc povolání)
- `úroveň_od`
- `vyžaduje` — self-reference, prerekvizita (např. navazující stupně "Vícenásobné útoky")
- `mechanika` — strukturovaně: kostka, bonus, cíl
- `past_id` — FK na Past, pokud schopnost vyžaduje záchranný hod
- `výsledek_škála` — odkaz na 4stupňový enum, pokud relevantní
- `efekty` — M:N na Efekt, které schopnost způsobuje

### Finta
- `id`, `název`, `typ` (Ú / O / kombinovaná)
- `bonus_iniciativa`, `počet_akcí`
- `požadovaná_zbraň`, `požadovaný_protivník`
- `sekvence_akcí` — pole objektů: pořadí, typ, OČ_modifikátor, ÚČ_modifikátor
- `prerekvizity` — M:N na jiné Finty
- `poznámky`

### Kouzlo
- `id`, `název`
- `seznam_kouzel_id` — FK na Seznam kouzel (umožňuje proklik "kouzla mága PPP" apod.)
- `obory_magie` — **M:N** na Obor magie (pozor: některá kouzla mají obory kombinované, např. "Oko" = ČP+PO)
- `úroveň_kouzla`, `cena_magenergie`, `dosah`, `doba_seslání`, `doba_trvání`
- `typ_únavy` — vyčerpávající / nevyčerpávající / udržovací / zaostření vůle
- `past_id` — pokud kouzlo vyžaduje záchranný hod cíle
- `efekty` — M:N na Efekt
- `popis`

### Seznam kouzel
- `id`, `název` (např. "Kouzla mága PPP", "Kouzla hraničáře")
- `povolání_id`
- `popis`

### Obor magie
- `id`, `kód` (ČP/EU/EO/IL/MA/VI/PS/PO — zůstává viditelný)
- `název` (časoprostorová magie, energetická útočná magie, ...)
- `popis` — delší popis z pravidel (sekce "Vymýšlení nových kouzel")

### Skupina kouzel (pro účely zranitelnosti)
- `id`, `název` (Kouzla fyzická / psychická / útočná / ochranná ...)
- `obory_magie` — M:N na Obor magie (skupina = kombinace 1–2 oborů)

### Lektvar
- `id`, `název`, `výrobce_povolání_id`
- `efekty` — M:N na Efekt
- `past_id` — pokud relevantní
- `doba_přípravy`, `cena`
- `suroviny` — M:N na Předmět

### Předmět / Vybavení
- `id`, `název`, `typ` (zbraň / zbroj / surovina / artefakt)
- `kategorie_zbraně` — sečná / bodná / tupá (u zbraní, může být kombinace)
- `typ_pro_vyrážení_dveří` — ostré bodné / ostré drtivé / tupé drtivé (samostatné pole — neshoduje se 1:1 s kategorií výše, sekera je "ostrá drtivá")
- `ÚČ`, `útočnost`, `OČ` — bojové parametry u zbraní/improvizovaných zbraní (nástroje, knihy, hole apod.), zapsané v pravidlech jako trojice "číslo ±číslo ±číslo" (např. Khakkhara: ÚČ 7, útočnost +1, OČ +2) — rozděleno na tři pole, ne jeden text
- `váha`, `cena`
- `efekty` — M:N na Efekt (např. efekty drahokamů)

### Nestvůra (Bestiář)
- `id`, `název`
- statblok: `životaschopnost`, `ÚČ`, `OČ`, `odolnost`, `velikost`, `bojovnost`, `pohyblivost`, `vytrvalost`, `manévrovací_schopnost`, `inteligence`, `charisma`, `ZSM`, `přesvědčení`, `poklady`, `zkušenost`, `ochočení`
- `SUM`, `SOM` — mentální souboj, pokud relevantní
- `zranitelnosti` — M:N na Kód zranitelnosti + `modifikátor` (½ / ¼ / 2× ...)
- `schopnosti` — M:N na Zvláštní schopnost
- `prostředí`

### Kód zranitelnosti
- `id`, `kód` (A–P — zůstává viditelný)
- `název` (Obyčejná zbraň, Kouzelná zbraň, Stříbrná zbraň, Svěcená voda, Mráz/oheň, Jed/kyselina, ...)
- `skupina_kouzel_id` — FK, pro kódy odkazující na skupiny kouzel (I–N)
- `popis`

### Efekt
- `id`, `název`, `typ` (buff / debuff / DoT / HoT / modifikátor / imunita / zranitelnost / odolnost)
- `cíl` (životy / OČ / ÚČ / konkrétní vlastnost / akceschopnost ...)
- `hodnota_vzorec`
- `mechanika_aplikace` (jednorázově / na začátku kola / na konci kola / při zásahu)
- `trvání`, `podmínka_ukončení`
- `stackovatelnost` (ano/ne, max)
- `ikona`, `barva`, `tooltip_text` — pro UI mouseover v boji

### Past (saving throw)
- `id`, `vlastnosti` (Sil/Obr/Odl/Int/Chr/Roz/Žvt, může být kombinace)
- `obtížnost_nebezpečnost`
- `výsledek_úspěch` → efekt_id nebo nic
- `výsledek_neúspěch` → efekt_id
- `škála_výsledku` — použít 4stupňovou škálu, pokud past rozlišuje fatální výsledky

### Velikost
- `id`, `kód` (A0/A/B/C/D/E), `popis_rozsah`

### Tabulka velikostních modifikátorů
- `velikost_útočník`, `velikost_obránce` → `modifikátor_útoku` (6×6 matice, obrana se neopravuje zvlášť)

### Jazyk
- `id`, `název`
- `rasy` — M:N, `povolání` — M:N

### Přesvědčení
- `id`, `název`, `popis` (alignment systém)

### Tabulka naložení
- `síla` → `práh_mírné` / `práh_střední` / `práh_velké`, dopad na pohyblivost a ÚČ/OČ

### Tabulka zkušeností
- `povolání_id` (nebo obecná), `úroveň` → `potřebné_zkušenostní_body` (+ homebrew varianty "cena za výcvik" per povolání)

---

## Otevřené otázky / TBD (řeší se zvlášť)

- Kompletní obsah tabulek pro úrovně 16.–36. u vícero schopností — viz kontrolní seznam neúplných míst
- Kompletní legenda a mechanika všech ~30 fint (zatím máme strukturu pole, ne plný obsah)
- Ověřit, zda je zapotřebí rozlišovat "kategorie zbraně" (sečná/bodná/tupá) šířeji, nebo stačí propojení přes Kód zranitelnosti B (obyčejná zbraň)
