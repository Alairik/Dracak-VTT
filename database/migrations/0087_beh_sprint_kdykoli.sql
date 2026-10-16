-- VTT — souboj, plánování tahu (docs/navrh-tahu-v2.md): běh a sprint smí tvor kdykoli
-- (kromě únavy), ne jen při úmyslu „Pohyb“ — rozhodnutí uživatele 2026-10-16.
-- Čistě DML, web účet to provede sám přes migrate.php (žádné ruční SQL).

UPDATE souboj_pohyb SET jen_pri_umyslu = NULL WHERE kod IN ('beh', 'sprint');
