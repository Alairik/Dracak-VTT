-- Údaje o sesílaném kouzlu na štítku „Kouzlí“ (migrace 0112, 0113): cíl, trvání a zda už se účinek vyhodnotil — aby hra mohla kouzlo seslat sama v okamžiku,
-- kdy začíná působit (po předposlední akci kouzlení, b10847), a hráč ho nemusel zadávat ručně z deníku.
--
-- Jeden sloupec s krátkým JSON (cíl, počet kol trvání, příznak „účinek proběhl“) u aktivních efektů; vyplněný je jen u štítku „Kouzlí“.
-- ALTER = DDL: web účet (DML-only na Wedosu) ho neprovede, spouští se ručně v phpMyAdminu (docs/sql-k-rucnimu-spusteni.sql, blok I).
-- Kód funguje i bez něj (kouzlení se pak počítá a ohlásí jako dřív, účinek se zadává z deníku).

ALTER TABLE aktivni_efekty ADD COLUMN kouzleni_data VARCHAR(255) NULL COMMENT 'sesílané kouzlo: cíl, trvání, účinek proběhl (JSON, štítek Kouzlí)';
