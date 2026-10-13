-- Síla zbraně (SZ) a délka jako samostatná čísla u zbraní.
--
-- Útočné číslo je podle pravidel SZ + bonus za sílu (h1610), ale SZ je v
-- katalogu predmety dosud jen ve volném textu `popis` ("Síla zbraně (SZ):
-- 3, délka: 1."), takže ho engine ani deník nemohl použít. Hodnoty doplní
-- 0072 z toho textu; zbraně bez SZ v textu zůstávají NULL (nevymýšlí se).
--
-- ALTER = DDL, ruční spuštění přes phpMyAdmin (admin účet), pak ho
-- migrate.php při dalším deployi sám zapíše do migrace_log.

ALTER TABLE predmety
    ADD COLUMN sila_zbrane TINYINT UNSIGNED NULL,
    ADD COLUMN delka_zbrane VARCHAR(10) NULL;
