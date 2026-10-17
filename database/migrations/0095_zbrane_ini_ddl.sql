-- Zbraně: držení, třída a postih k iniciativě — struktura. Samé přidávání sloupců, nic se nemění ani nemaže.
-- predmety.chvat: jednoruci / obourucni (zbraně pro boj tváří v tvář); predmety.trida_zbrane: lehka / stredni / tezka / supertezka;
-- predmety.ini_postih: postih k iniciativě při boji touto zbraní (−2 těžké obouruční a supertěžké, −3 těžká kuše; NULL = žádný).
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Data zbraní jsou v 0096.

ALTER TABLE predmety ADD COLUMN chvat VARCHAR(12) NULL COMMENT 'jednoruci / obourucni — zbraň pro boj tváří v tvář';
ALTER TABLE predmety ADD COLUMN trida_zbrane VARCHAR(12) NULL COMMENT 'lehka / stredni / tezka / supertezka';
ALTER TABLE predmety ADD COLUMN ini_postih TINYINT NULL COMMENT 'postih k iniciativě při boji touto zbraní (NULL = žádný)';
