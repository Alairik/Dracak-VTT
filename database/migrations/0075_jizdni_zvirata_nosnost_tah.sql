-- Nosnost jízdního zvířete v tahu (v zápřahu) — doplněk k 0067.
--
-- Pravidla: tažný kůň "unese stejně jako klasický kůň, v tahu je však jeho
-- nosnost oproti klasickému koni o čtvrtinu vyšší, tj. 1 000 mn" (Bestiář
-- h2516, b-text pod "Tažný kůň"); PPP h1635 totéž ("nosnost o čtvrtinu vyšší
-- než klasický, 1000 mn v zápřahu"). Uživatel to potvrdil: na hřbetě 800 mn
-- (jako tabulka), v tahu 1000 mn. Nosnost na hřbetě (nosnost_mn) se proto
-- nemění a pro tah přibývá samostatný sloupec; NULL = v tahu stejná jako na
-- hřbetě. Samotný výpočet nosnosti spřežení (čtyřnásobek hodnoty "podle
-- Tabulky jezdeckých zvířat", PPP h1639–h1641) patří do Fáze 3 (povozy).
--
-- ALTER TABLE = DDL, web účet ho neprovede — ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql). Hodnota se plní až v 0076 (DML).

ALTER TABLE jizdni_zvirata
    ADD COLUMN nosnost_tah_mn SMALLINT UNSIGNED NULL
        COMMENT 'nosnost v tahu/zápřahu v mn; NULL = stejná jako nosnost_mn'
        AFTER nosnost_mn;
