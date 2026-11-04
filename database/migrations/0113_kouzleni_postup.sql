-- Postup kouzlení na štítku „Kouzlí“ (migrace 0112): které kouzlo se sesílá a kolik akcí kouzlení už proběhlo z kolika.
--
-- Pravidlo (content/pravidla-hrac.html, kapitola Kouzlení v souboji, b10842–b10847): kouzlo s vyvoláním N kol vyžaduje 2N po sobě jdoucích
-- akcí kouzlení; působit začíná po předposlední akci, poslední je „odpočinek“. Zásah kouzelníka před předposlední akcí kouzlo zruší
-- (magenergie propadne), po ní už ne. Rozhodnutí uživatele 2026-11-04: počet akcí zná hra z kouzla (údaj „vyvolání“), PJ ho jen nehlídá.
--
-- Tři nové sloupce u aktivních efektů; vyplněné jsou jen u štítku „Kouzlí“, kdy tvor sesílá konkrétní kouzlo.
-- ALTER = DDL: web účet (DML-only na Wedosu) ho neprovede, spouští se ručně v phpMyAdminu (docs/sql-k-rucnimu-spusteni.sql, blok H).
-- Kód funguje i bez něj (štítek „Kouzlí“ pak nepočítá akce a sundává ho PJ).

ALTER TABLE aktivni_efekty ADD COLUMN kouzlo_id INT UNSIGNED NULL COMMENT 'kouzlo, které tvor sesílá (štítek Kouzlí)';
ALTER TABLE aktivni_efekty ADD COLUMN akci_celkem TINYINT UNSIGNED NULL COMMENT 'kolik akcí kouzlení kouzlo vyžaduje (2 × vyvolání v kolech)';
ALTER TABLE aktivni_efekty ADD COLUMN akci_hotovo TINYINT UNSIGNED NULL COMMENT 'kolik akcí kouzlení už proběhlo';
