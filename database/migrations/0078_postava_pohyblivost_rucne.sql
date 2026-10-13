-- Ručně zadaná (okamžitá) pohyblivost postavy — Fáze 5 plánu cestování.
-- Platí jen ve světě s vypnutým svet.auto_pohyblivost (0077): PJ/hráč zadá
-- pohyblivost místo výpočtu "rasa + obratnost + 2× síla"; hodnota se bere
-- jako aktuální (už po naložení a únavě). NULL = nezadáno.
--
-- ALTER TABLE = DDL, web účet ho neprovede — ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql).

ALTER TABLE postavy ADD COLUMN pohyblivost_rucne SMALLINT NULL AFTER doprava_zvire_id;
