-- Migrace 0007 postavila `opravy_za_atribut` nad stupeň 10 na vzorci
-- floor((stupeň-10)/2), ne na skutečném textu pravidel — od stupně 12
-- byla tabulka systematicky o 1 bod bonusu vepředu a navíc si vymyslela
-- pokračování nad stupeň 21 (22-23→+6), které v hráčské tabulce vůbec
-- není.
--
-- Ověřeno přímo v obou zdrojích (ne odhadem, viz CLAUDE.md):
-- - content/pravidla-hrac.html, h105 "TABULKA POSTIHŮ A BONUSŮ" — platí
--   pro postavy, stupeň vlastnosti 1-21: 10-12→0, 13-14→+1, 15-16→+2,
--   17-18→+3, 19-20→+4, 21→+5. Nižší stupně (1-9) se shodují se starou
--   tabulkou beze změny, proto se nedotýkají.
-- - content/pravidla-bestiar.html, h2392 "TABULKA BONUSŮ" — výslovné
--   pokračování nad 21 pro nestvůry: 22→+5, 23-24→+6, 25-26→+7,
--   27-28→+8, 29-30→+9, 31-32→+10.
--
-- Žádná FK neukazuje na opravy_za_atribut.id (ověřeno grepem) — jen se
-- čte přes "WHERE ? BETWEEN stupen_od AND stupen_do"
-- (includes/vtt_iniciativa.php), takže DELETE+INSERT starých/nových
-- bucketů je bezpečné a navíc samo o sobě idempotentní (druhé spuštění
-- smaže a rovnou znovu vloží identické řádky).
--
-- Živý dopad: dracak_vtt_obratnost_bonus() v includes/vtt_iniciativa.php
-- (bonus Obratnosti při remíze v iniciativě) tuhle tabulku už používá —
-- postavy se stupněm Obratnosti 12/14/16/18/20 dostávaly o 1 víc, než
-- měly. nosnost_podle_sily (taky migrace 0007) končí na +6 a zůstává
-- nedotčená — i předtím nepokrývala stupně nad 23, to není tahle
-- migrace nijak nezhoršuje, jen nerozšiřuje.
--
-- Samé DELETE/INSERT = čisté DML, projde i pod web účtem automaticky
-- přes migrate.php, žádný ruční krok navíc (stejný vzor jako
-- 0046_vtt_sjednotit_ochromeni_omraceni.sql).

DELETE FROM opravy_za_atribut WHERE stupen_od >= 10;

INSERT INTO opravy_za_atribut (stupen_od, stupen_do, oprava, poznamka) VALUES
(10, 12, 0,  'Průměrný'),
(13, 14, 1,  'Lehký nadprůměr (jeden z deseti)'),
(15, 16, 2,  'Lehký nadprůměr (jeden z deseti)'),
(17, 18, 3,  'Vysoký nadprůměr (jeden ze sta)'),
(19, 20, 4,  'Vysoký nadprůměr (jeden ze sta)'),
(21, 22, 5,  'Extrémní nadprůměr (jeden z tisíce)'),
(23, 24, 6,  'Nestvůry — nad rámec hráčské tabulky'),
(25, 26, 7,  'Nestvůry — nad rámec hráčské tabulky'),
(27, 28, 8,  'Nestvůry — nad rámec hráčské tabulky'),
(29, 30, 9,  'Nestvůry — nad rámec hráčské tabulky'),
(31, 32, 10, 'Nestvůry — nad rámec hráčské tabulky');
