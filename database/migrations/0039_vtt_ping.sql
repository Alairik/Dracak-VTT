-- Přidání 'ping' do svet_udalosti.typ pro ukazovací (pointer) nástroj na
-- mapě — efemérní vizualizace pro všechny hráče (kdo klikl a kam), nic
-- mechanického se z toho nepočítá, žádná nová tabulka.
--
-- ALTER MODIFY COLUMN na ENUM je jen rozšíření výčtu o novou hodnotu, ne
-- destruktivní změna — stejný vzor jako 0038_vtt_nestvury_efekty.sql pro
-- 'hp_zmena'. Je to ALTER, web účet (DML-only na Wedosu) ho neprovede —
-- MUSÍ se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log, viz CLAUDE.md. Po ručním spuštění ho migrate.php při
-- dalším deployi přeskočí (fallback detekuje, že enum už hodnotu má).

ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
    'token_presun','token_pridan','token_smazan',
    'kostka_hod','efekt_aplikovan','efekt_konci',
    'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
    'hp_zmena','ping'
) NOT NULL;
