-- Hodiny světa (docs/stav-projektu.md, plán C bod 17; rozhodnutí uživatele 2026-11-02 „pokračuj dalším bodem“): čas během dne a hoření světel.
--   svet.aktualni_smena    — směna dne 0–143 (1 směna ≈ 10 minut, b33; 24 hodin = 144 směn; 48 = 8:00). Den počítá dosavadní svet.aktualni_den_offset.
--   tokeny.svetlo_zbyva    — kolik směn světlo (nebo zóna tmy) ještě vydrží; NULL = bez limitu. Pochodeň 3 směny (b11183), lucerna 24 směn na láhev oleje (b11184),
--                            Temnota 3 směny (b1936). Posune-li PJ čas, zbývající směny se odečtou a na nule světlo zhasne.
-- Samé DDL (ALTER ADD) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md.
-- Bez sloupců zůstává všechno jako dosud (čas ve dni se nezobrazuje, světla hoří bez limitu).

ALTER TABLE svet ADD COLUMN aktualni_smena SMALLINT UNSIGNED NOT NULL DEFAULT 48 COMMENT 'směna dne 0–143 (1 směna = 10 minut, 48 = 8:00)';
ALTER TABLE tokeny ADD COLUMN svetlo_zbyva SMALLINT UNSIGNED NULL COMMENT 'kolik směn světlo ještě vydrží (NULL = bez limitu)';
