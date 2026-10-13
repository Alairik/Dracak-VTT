-- Přepínače automatiky pohybu na úrovni světa — Fáze 5 plánu cestování
-- (docs/stav-projektu.md), stejný vzor jako auto_hod_kostkou & spol. (0037).
-- Na rozdíl od nich jsou ZAPNUTÉ ve výchozím stavu (DEFAULT 1): engine tyhle
-- věci už dnes počítá sám (deník, družina, kalkulačka cesty), přepínač dává
-- PJ možnost je vypnout a vrátit se k ručnímu rozhodování u stolu.
--
--   auto_naklad        náklad z inventáře (+ peníze) se počítá do naložení;
--                      vypnuto = naložení se nevyhodnocuje (bez postihu)
--   auto_pohyblivost   pohyblivost se počítá z rasy, obratnosti a síly;
--                      vypnuto = platí hodnota zadaná ručně u postavy
--                      (postavy.pohyblivost_rucne, 0078)
--   auto_unava         únava snižuje bonus za sílu/obratnost a stupeň
--                      inteligence/charismy (Tabulka únavy, h1634); vypnuto =
--                      únava se jen eviduje, postihy aplikuje PJ ručně
--
-- ALTER TABLE = DDL, web účet ho neprovede — ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql). Jeden příkaz na sloupec: fallback v
-- migrate.php ověřuje jen první ADD COLUMN příkazu.

ALTER TABLE svet ADD COLUMN auto_naklad TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_zranitelnost;
ALTER TABLE svet ADD COLUMN auto_pohyblivost TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_naklad;
ALTER TABLE svet ADD COLUMN auto_unava TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_pohyblivost;
