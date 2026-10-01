-- Přidání 'krok_zpet' do svet_udalosti.typ — hra/api/krok_zpet.php (viz
-- docs/vtt-datovy-model-navrh-v1.md, sekce "Mechanismy" → "Krok zpět")
-- loguje tenhle typ jako NOVOU událost dokumentující revert poslední
-- vratné stavové události na mapě. Žádná nová tabulka, žádné nové
-- sloupce — svet_udalosti zůstává append-only, krok zpět = další INSERT,
-- nikdy UPDATE/DELETE existujícího řádku.
--
-- ALTER MODIFY COLUMN na ENUM je jen rozšíření výčtu o novou hodnotu, ne
-- destruktivní změna — stejný vzor jako 0038/0039/0041/0044/0047. Je to
-- ALTER, web účet (DML-only na Wedosu) ho neprovede — MUSÍ se spustit
-- ručně přes phpMyAdmin (admin účet) a zapsat do migrace_log, viz
-- CLAUDE.md. Po ručním spuštění ho migrate.php při dalším deployi
-- přeskočí (fallback detekuje, že enum už hodnotu má).
--
-- Plný výčet navazuje na poslední MODIFY v 0047_vtt_zdi.sql (20 hodnot),
-- žádná stávající hodnota nemizí.

ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
    'token_presun','token_pridan','token_smazan',
    'kostka_hod','efekt_aplikovan','efekt_konci',
    'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
    'hp_zmena','ping','iniciativa_hozena','kolo_nove','tah_zmena',
    'predmet_loot','predmet_pouzit',
    'zed_pridana','zed_smazana','mapa_grid_zmena',
    'krok_zpet'
) NOT NULL;
