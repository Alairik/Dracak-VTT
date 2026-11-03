-- Měřítko světové mapy (plán C bod 14, pochod po mapě světa): kolik mil je 1000 px mapy. S ním měřidlo na mapě ukáže vzdálenost v mílích
-- a PJ ji jedním klikem pošle do kalkulačky cesty (hra/cesta.php).
--   mapy.mil_na_1000px — NULL = měřítko není zadané, měřidlo ukazuje jen sáhy jako dosud.
-- Samé DDL (ALTER ADD) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md.
-- Bez sloupce zůstává všechno jako dosud (pole měřítka se v nastavení gridu nenabízí).

ALTER TABLE mapy ADD COLUMN mil_na_1000px DECIMAL(8,2) NULL COMMENT 'měřítko mapy: kolik mil je 1000 px (NULL = nezadáno)';
