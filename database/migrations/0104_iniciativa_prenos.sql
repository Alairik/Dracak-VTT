-- Přenos výsledku iniciativy ≤ 0 do dalšího kola (h1622 b10805, h1623 b10810 — rozhodnutí uživatele 2026-11-01).
-- Kniha: „Pokud je výsledek hodu 0 a méně, postava může zamýšlenou činnost provést až v dalším kole; v něm si bude znovu házet na
-- iniciativu, ale místo obvyklých postihů použije výsledek z minulého kola.“ Při přechodu do nového kola se výsledek ≤ 0 uloží
-- k účastníkovi souboje (kolo_ucastnik.ini_prenos) a při dalším hodu nahradí obvyklý modifikátor; ostatním se vynuluje.
--
-- Samé DDL (ALTER ADD) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Bez sloupce se výsledek ≤ 0 nepřenáší (dosavadní chování).

ALTER TABLE kolo_ucastnik ADD COLUMN ini_prenos TINYINT NULL COMMENT 'výsledek iniciativy ≤ 0 z minulého kola — v tomhle kole nahrazuje obvyklý modifikátor (h1622 b10805)';
