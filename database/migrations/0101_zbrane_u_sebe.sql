-- Zbraně u sebe (Deník v2, krok 2) — struktura. Jeden přidaný sloupec, nic se nemění ani nemaže.
-- postava_predmety.u_sebe: 1 = zbraň má postava při těle (opasek, pochva, závěs, toulec) a v boji ji může vytasit,
-- 0 = je v batohu. Výchozí 1 (kniha neříká, kolik se zbraní vejde na opasek). Viz includes/vtt_zbrane_u_sebe.php;
-- vytasení v boji stojí akci (h1606, b10619).
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, bylo spuštěno ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql, kontrolní SELECT vyšel 1, 2026-10-27); migrate.php sloupec pozná a přeskočí.

ALTER TABLE postava_predmety ADD COLUMN u_sebe TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'zbraň při těle (opasek, pochva, závěs, toulec): 1 = v boji ji jde vytasit, 0 = v batohu';
