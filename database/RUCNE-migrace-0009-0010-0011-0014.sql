-- ============================================================================
-- RUČNÍ NASAZENÍ — spustit JEDNOU v phpMyAdminu pod ADMIN účtem k databázi.
--
-- Web účet (ten, pod kterým běží scripts/migrate.php) má na Wedosu natvrdo
-- jen DML práva (SELECT/INSERT/UPDATE/DELETE) — CREATE/ALTER nikdy neprojde,
-- ani přes migrate.php. Tenhle soubor slučuje ALTER/CREATE kroky ze čtyř
-- migrací (0009, 0010, 0011, 0014), co proto migrate.php nikdy sám nespustí,
-- do jednoho kroku k ručnímu spuštění. Migrace 0012, 0013, 0015 (čisté
-- UPDATE/INSERT) se spouští samy přes migrate.php po každém deployi — ty
-- tímhle souborem NEŘEŠ.
--
-- Postup:
--   1. phpMyAdmin -> vaše databáze -> záložka SQL -> vlož celý tenhle soubor -> Go.
--   2. Pokud některý ALTER spadne na "Duplicate column" nebo "Table already
--      exists", ten konkrétní řádek už evidentně proběhl dřív — přeskoč ho
--      a pokračuj zbytkem (spusť soubor znovu bez těch řádků).
--   3. Na konci jsou 4 INSERT do migrace_log — MUSÍ proběhnout, jinak si
--      migrate.php bude při každém příštím deployi myslet, že tyhle 4
--      soubory ještě čekají na spuštění, zkusí je (ALTER/CREATE) spustit
--      pod web účtem, spadne na chybu oprávnění a shodí celý deploy.
-- ============================================================================


-- ---------- 0009_row_ownership.sql ----------
-- Row-level vlastnictví záznamů pro roli hráč. NULL u existujících řádků =
-- bez vlastníka (hráč bez table-level oprávnění je needituje, pj/admin ano).
ALTER TABLE kouzla ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE zvlastni_schopnosti ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE predmety ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE lektvary ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE finty ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE povolani ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE rasy ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE efekty ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE pasti ADD COLUMN created_by INT UNSIGNED NULL AFTER id;

ALTER TABLE kouzla ADD CONSTRAINT fk_kouzla_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE zvlastni_schopnosti ADD CONSTRAINT fk_schopnosti_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE predmety ADD CONSTRAINT fk_predmety_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE lektvary ADD CONSTRAINT fk_lektvary_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE finty ADD CONSTRAINT fk_finty_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE povolani ADD CONSTRAINT fk_povolani_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE rasy ADD CONSTRAINT fk_rasy_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE efekty ADD CONSTRAINT fk_efekty_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE pasti ADD CONSTRAINT fk_pasti_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);


-- ---------- 0010_typ_unavy_udrzovaci.sql ----------
-- Udržovací magenergie je samostatný boolean, nezávislý na typ_unavy
-- (vyčerpávající/nevyčerpávající zůstává beze změny — odpovídá stínové/
-- reálné únavě z pravidel). Sloupec typ_unavy se sám neupravuje.
ALTER TABLE kouzla ADD COLUMN udrzovaci BOOLEAN NOT NULL DEFAULT FALSE AFTER typ_unavy;


-- ---------- 0011_schopnost_vlastnost.sql ----------
-- Klíčová vlastnost dovednosti (Síla/Obratnost/Odolnost/...), FK na
-- vlastnosti — stejnou tabulku, kterou už používá povolani.primarni_vlastnost_id.
ALTER TABLE zvlastni_schopnosti ADD COLUMN vlastnost_id TINYINT UNSIGNED NULL AFTER druh;
ALTER TABLE zvlastni_schopnosti ADD CONSTRAINT fk_schopnosti_vlastnost FOREIGN KEY (vlastnost_id) REFERENCES vlastnosti(id);


-- ---------- 0014_schopnost_vysledky.sql ----------
-- Efekt dovednosti podle 4 stupňů výsledku testu (past), viz vysledky_testu.
-- kontext rozlišuje efekt v boji / mimo boj tam, kde se liší.
CREATE TABLE schopnost_vysledky (
    schopnost_id       INT UNSIGNED NOT NULL,
    vysledek_testu_id  TINYINT UNSIGNED NOT NULL,
    kontext            ENUM('obecny', 'boj', 'mimo_boj') NOT NULL DEFAULT 'obecny',
    efekt_text         TEXT NOT NULL,
    PRIMARY KEY (schopnost_id, vysledek_testu_id, kontext),
    FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti(id) ON DELETE CASCADE,
    FOREIGN KEY (vysledek_testu_id) REFERENCES vysledky_testu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- Označit všechny 4 jako hotové, ať je migrate.php nezkouší znovu ----------
INSERT INTO migrace_log (soubor) VALUES
    ('0009_row_ownership.sql'),
    ('0010_typ_unavy_udrzovaci.sql'),
    ('0011_schopnost_vlastnost.sql'),
    ('0014_schopnost_vysledky.sql');
