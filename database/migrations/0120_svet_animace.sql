-- 0120 — Přepínač animací souboje v nastavení světa
--
-- Proč: animace pohybu, útoků a kouzel na mapě (assets/js/souboj_animace.js) mají jít vypnout —
-- rozhodnutí uživatele 2026-11-11 k návrhu („animace může být asi na volbu, řekl bych do nastavení
-- světa s tím“). Patří ke světu, ne k prohlížeči: PJ to rozhodne za celý stůl.
--
-- Výchozí 1 = zapnuto, takže se po přidání sloupce nic nezmění. Dokud sloupec v databázi není,
-- kód se chová, jako by byl 1 (hra/mapa.php si existenci sloupce ověřuje).
--
-- POZOR: ALTER web účet (DML-only) nespustí — patří do docs/sql-k-rucnimu-spusteni.sql, blok M.

ALTER TABLE svet ADD COLUMN animace TINYINT(1) NOT NULL DEFAULT 1
  COMMENT 'animace souboje na mapě (pohyb, útok, kouzlo); 0 = vypnuté';
