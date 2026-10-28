-- Doplnění druhu zbraně (`kategorie_zbrane`) u zbraní, kde ho kniha jednoznačně určuje. Samé DML, jde spustit opakovaně
-- (mění jen zbraně bez vyplněného druhu).
--
--  * Vertolův luk, Arbalest — jsou v tabulce STŘELECKÉ ZBRANĚ (PPZ, zbraně) → střelná.
--  * Kouzelný luk — „základ: dlouhý luk“ (h275 b2635) → střelná.
--  * Válečná sekera a Trojzubec (super těžké vrhací varianty) — název katalogu říká vrhací → vrhací (Dikobraz a Rychlé tasení se
--    na ně uplatní stejně jako na oštěp).
--  * Hvězdice-bumerang — „základ: hvězdice“, vrací se k majiteli (Nemagické – Zbraně) → vrhací, jako Hvězdice.
--
-- Záměrně NEdoplněno (kniha druh jasně neříká, zapsáno v docs/kontrolni-seznam-neuplnych-mist.md): Fuuma shuriken (v tabulce
-- TVÁŘÍ V TVÁŘ, ale s dostřelem 3/6/9), Atlatl (příslušenství násobící dostřel, zařazené mezi střelecké), Řetězový meč, Khakkhara
-- a Hůl „Minami no mori“ (tváří v tvář, ale druh poškození — sečná / bodná / tupá — kniha neuvádí), Rachejtle 1.–8. úrovně.

UPDATE predmety SET kategorie_zbrane = 'strelna'
WHERE typ = 'zbran' AND (kategorie_zbrane IS NULL OR kategorie_zbrane = '')
  AND nazev IN ('Vertolův luk', 'Arbalest', 'Kouzelný luk');

UPDATE predmety SET kategorie_zbrane = 'vrhaci'
WHERE typ = 'zbran' AND (kategorie_zbrane IS NULL OR kategorie_zbrane = '')
  AND nazev IN ('Válečná sekera (super těžká vrhací)', 'Trojzubec (super těžký vrhací)', 'Hvězdice-bumerang');
