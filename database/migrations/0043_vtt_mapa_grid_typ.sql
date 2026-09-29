-- Typ gridu kreslený přes mapu (čtverec/hex) — doplňuje grid_velikost_px
-- atd. z migrace 0037, které dosud nikde nebyly nastavovány ani
-- vykreslovány. Čistě metadata mapy (volí PJ při vytvoření/úpravě mapy),
-- ne per-hráč preference — proto sloupec na mapy, ne jinde.
--
-- Samé ALTER ADD COLUMN = DDL, web účet (DML-only na Wedosu) ji nikdy
-- neprovede a migrate.php na ní shodí deploy krok (očekávané, ne chyba).
-- MUSÍ se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log — viz CLAUDE.md.

ALTER TABLE mapy
  ADD COLUMN grid_typ ENUM('ctverec','hex') NOT NULL DEFAULT 'ctverec' AFTER grid_velikost_px;
