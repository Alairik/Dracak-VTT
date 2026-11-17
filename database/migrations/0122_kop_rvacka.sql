-- 0122 — Kop (Tabulka rvačky, druhý sloupec „síla nohy“)
--
-- Pravidla (hrac Rvačka h1654 b10987): kop lze použít v pěstním souboji, při zahájení rvačky i při boji zbraní,
-- i na nehumanoidní nestvůru. Útočné číslo = síla nohy (A 3, B 5, C 7) + bonus za sílu, útočnost +3. Kopu lze
-- využít jen JEDNOU ZA KOLO (respektive za 4 akce) a v nejbližší následující akci útok nebo obrana (nejpozději
-- v následujícím kole) má útočník postih −4 (bojovník, šermíř, lupič nebo chodec jen −2). Kop je útok beze zbraně,
-- takže působí jen stínové zranění (b10988) — proto navazuje na migraci 0121 (stin_hp).
--
-- Stav kopu se vede na řádku účastníka souboje (kolo_iniciativa), který se maže koncem souboje sám:
--   kop_kolo   = číslo kola, ve kterém tvor naposledy kopl (0 = ještě nekopl) — hlídá „jen jednou za kolo“.
--   kop_postih = kolik má tvor srazit z nejbližšího útoku/obrany za minulý kop (0 = nic, jinak 4 nebo 2).
--
-- Jen ALTER ADD COLUMN — nic se nemaže. Pod web (DML-only) účtem GRANT na ALTER není, takže se tahle migrace
-- musí spustit RUČNĚ v phpMyAdminu pod admin účtem (viz docs/sql-k-rucnimu-spusteni.sql). Stránka Změny databáze
-- ALTER ADD pozná jako „už hotovo“ a zapíše do migrace_log.

ALTER TABLE kolo_iniciativa
    ADD COLUMN kop_kolo SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER akce_hotovo,
    ADD COLUMN kop_postih TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER kop_kolo;
