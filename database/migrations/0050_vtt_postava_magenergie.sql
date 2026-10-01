-- Zásoba magenergie postavy ve VTT. `kouzla.cena_magenergie` a
-- `povolani.pouziva_magenergii` už existují a jsou naplněné, ale
-- `postavy` dosud neměla kam uložit aktuální/maximální zásobu — engine
-- (hra/api/pouzij_predmet.php) proto při seslání kouzla nikdy nic
-- neodečítal.
--
-- Ověřeno v content/pravidla-hrac.html (ne odhadnuto, viz CLAUDE.md):
--   h297 "TABULKA KOUZELNÍKOVY MAGENERGIE" — "Množství magenergie závisí
--   na inteligenci a úrovni. Spotřebovaná magenergie se odečítá po
--   každém seslání kouzla a nikdy nesmí klesnout pod nulu. Nová
--   magenergie se získává jen po důkladném odpočinku."
--   h179 Hraničář — stejný tvar ("nikdy nesmí klesnout pod nulu a
--   nelze ji zvýšit nad tabulkovou hodnotu"), jen jiný způsob doplnění
--   (meditace/rozjímání místo spánku). Stejně tak h186 Druid, h200
--   Chodec, h700 Bard/Pamětník/Krotitel, h955 Lovec stínů, h1090
--   Novic/Erythen/Mystik, h1460 Tempest ("mana: jako čaroděj x5") —
--   všechno jeden a týž tvar: číselná zásoba podle úrovně (a
--   inteligence/charizmatu/...), čerpaná sesíláním kouzel ze seznamu,
--   doplňovaná nějakou formou odpočinku/aktivity. Přesný vzorec/tabulku
--   pro konkrétní povolání VTT nepočítá (stejně jako u max_hp) — číslo
--   zadává/upravuje PJ ručně, engine jen hlídá strop a odečítá spotřebu.
--
-- Alchymista (pouziva_magenergii=TRUE, h94/h275) záměrně VYNECHÁN z téhle
-- mechaniky: jeho "magenergie" je destilovaná z předmětů do vlastní
-- truhly (vlastní jednotky, hustota podle úrovně, max. 100 mincí
-- objemu) a slouží k VÝROBĚ alchymistických předmětů (lektvary/svitky/
-- trvalé předměty v tabulkách `lektvary`/`predmety`), ne k sesílání
-- kouzel ze seznamu (`kouzla`) — Alchymista a jeho obory Theurg/Pyrofor
-- nemají v datech žádný `seznamy_kouzel` záznam, takže níže popsaný
-- engine (vázaný na typ_polozky='kouzlo') se jich stejně nikdy netýká.
-- Dávat jim stejné "utrácecí" sloupce/UI jako kouzelníkovi by naznačovalo
-- stejnou mechaniku, kterou nemají — nechat na samostatné rozhodnutí,
-- až/pokud se bude stavět VTT podpora pro alchymistickou truhlu.
--
-- NULL = povolání magenergii nepoužívá / zatím nevyplněno — stejná
-- konvence jako nullable atributy v migraci 0040_vtt_postava_atributy.sql
-- a grid_typ v 0043_vtt_mapa_grid_typ.sql.
--
-- ALTER ADD COLUMN = DDL, web účet (DML-only na Wedosu) ho neprovede,
-- musí se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log — viz CLAUDE.md.

ALTER TABLE postavy
    ADD COLUMN aktualni_magenergie SMALLINT NULL AFTER max_hp,
    ADD COLUMN max_magenergie SMALLINT NULL AFTER aktualni_magenergie;

-- hra/api/pouzij_predmet.php loguje odečet magenergie jako součást
-- existující udalosti 'predmet_pouzit' (žádný nový typ tam netřeba), ale
-- PJ-upravitelné ±tlačítko v mapa.php (panel-spravovat, mimo seslání
-- kouzla) potřebuje vlastní typ, stejně jako má hp_zmena svoje tlačítko
-- — proto 'magenergie_zmena'. Plný výčet navazuje na poslední MODIFY v
-- 0049_vtt_krok_zpet.sql (21 hodnot), žádná stávající hodnota nemizí.
ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
  'token_presun','token_pridan','token_smazan',
  'kostka_hod','efekt_aplikovan','efekt_konci',
  'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
  'hp_zmena','ping','iniciativa_hozena','kolo_nove','tah_zmena',
  'predmet_loot','predmet_pouzit',
  'zed_pridana','zed_smazana','mapa_grid_zmena',
  'krok_zpet','magenergie_zmena'
) NOT NULL;
