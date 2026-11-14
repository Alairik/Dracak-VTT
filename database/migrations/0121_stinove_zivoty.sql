-- 0121 — Stínové životy (druhý typ životů)
--
-- Pravidla: rána beze zbraně (pěst, kop, jen hlavicí) a úder naplocho působí
-- jen STÍNOVÉ zranění (hrac Rvačka h1654 b10988: „Útoky vedené beze zbraně nebo
-- jen její hlavicí způsobují jen stínové zranění“; nebráněná postava s 4+ životy
-- dostává půl stínově / půl fyzicky — pj h2346 b17394). Stínové zranění tvora
-- NEZABÍJÍ a po boji se samo zahojí (rozhodnutí uživatele 2026-11-14), ale do
-- vyřazení (omráčení) se počítá spolu s fyzickým: tvor je vyřazený, když
-- aktualni_hp − stin_hp klesne na mez vyřazení.
--
-- stin_hp = kolik stínového zranění má tvor právě teď natrpěno (0 = žádné).
-- Číslo roste zraněním, srovná se na 0 po skončení souboje. Vede se zvlášť na
-- postavě i na instanci nestvůry, stejně jako aktualni_hp/max_hp (0037, 0038).
--
-- Jen ALTER ADD COLUMN — nic se nemaže. Pod web (DML-only) účtem GRANT na ALTER
-- není, takže tahle migrace se musí spustit RUČNĚ v phpMyAdminu pod admin účtem
-- (viz docs/sql-k-rucnimu-spusteni.sql). Stránka Změny databáze ALTER ADD pozná
-- jako „už hotovo“ a zapíše do migrace_log.

ALTER TABLE postavy
    ADD COLUMN stin_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_hp;

ALTER TABLE nestvura_instance
    ADD COLUMN stin_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_hp;
