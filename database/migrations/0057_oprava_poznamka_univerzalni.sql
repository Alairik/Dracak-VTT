-- Migrace 0048 popsala řádky stupeň 23+ v opravy_za_atribut jako
-- "Nestvůry — nad rámec hráčské tabulky" — zavádějící popisek, ne
-- chyba v datech. Tabulka je mechanicky univerzální: dracak_vtt_obratnost_bonus()
-- (includes/vtt_iniciativa.php) dělá generické
-- "WHERE ? BETWEEN stupen_od AND stupen_do" bez ohledu na typ entity,
-- a hráčské formuláře (hra/postava.php, hra/postava_nova.php) atributy
-- stejně omezují jen na max="30", ne na 21 — hráč, co se kouzlem/
-- artefaktem/čímkoliv dostane nad stupeň 21, dostane STEJNÝ bonus jako
-- nestvůra se stejným stupněm.
--
-- "Nestvůry" v původním popisku jen vysvětlovalo, PROČ je tahle část
-- tabulky vůbec zdokumentovaná (základní hráčská TABULKA POSTIHŮ A
-- BONUSŮ, h105, jde jen do stupně 21 — pokračování 22+ je dotažené z
-- bestiáře, h2392, protože jen tam byl kdy vytištěné), ne že by platila
-- jen pro ně. Oprava jen popisku, čísla (oprava) beze změny.
--
-- Čisté DML (UPDATE), žádná FK na poznamka, projde automaticky přes
-- migrate.php i pod web účtem.

UPDATE opravy_za_atribut
SET poznamka = 'Nad rámec hráčské tabulky (h105) — platí stejně pro hráče i nestvůry'
WHERE stupen_od >= 23;
