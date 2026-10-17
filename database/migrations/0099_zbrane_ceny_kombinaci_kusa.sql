-- Opravy zbraní doplněných v 0098 (samé DML). Rozhodnutí uživatele 2026-10-17.
--
-- 1) Ceny čtyř kombinací v tabulce zbraní nesedí na součet dílů (b10686 †: cena a váha kombinace je součtem dílů; váhy sedí).
--    Vypadalo to na posunutou desetinnou čárku; uživatel potvrdil přepis na součet dílů:
--    Dvě katany 2 × Katana 6,00 zl = 12,00 zl (v tabulce 1,20), Dva dlouhé meče 2 × 7,50 = 15,00 zl (1,50),
--    Dvě Schiavony 2 × 6,50 = 13,00 zl (1,30), Dva boxery 2 × 0,50 = 1,00 zl (v tabulce 1 st).
--    Podmínka na původní cenu: ruční úpravu ceny v katalogu to nepřepíše.
UPDATE predmety SET cena = 12.00 WHERE typ = 'zbran' AND nazev = 'Dvě katany' AND cena = 1.20;
UPDATE predmety SET cena = 15.00 WHERE typ = 'zbran' AND nazev = 'Dva dlouhé meče' AND cena = 1.50;
UPDATE predmety SET cena = 13.00 WHERE typ = 'zbran' AND nazev = 'Dvě Schiavony' AND cena = 1.30;
UPDATE predmety SET cena = 1.00 WHERE typ = 'zbran' AND nazev = 'Dva boxery' AND cena = 0.10;
-- 2) Kůsa je sečná zbraň (tabulka druh zranění neuvádí; doplnil uživatel).
UPDATE predmety SET kategorie_zbrane = 'secna' WHERE typ = 'zbran' AND nazev = 'Kůsa' AND kategorie_zbrane IS NULL;
