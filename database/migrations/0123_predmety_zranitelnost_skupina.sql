-- 0123 — Skupina útoku (materiál) zbraně kvůli zranitelnosti nestvůr
--
-- Doplněk k zranitelnostem nestvůr (bestiář b17729–b17745). Fyzický útok hra dosud brala vždy jako „B obyčejná
-- zbraň“, protože katalog předmětů kouzelnou (C) ani stříbrnou (D) zbraň nijak neoznačuje. Tenhle sloupec to
-- umožní: PJ u konkrétní zbraně vybere materiál (obyčejná B / stříbrná D / kouzelná C / posvěcená E / ohnivá G /
-- jedovatá H) a boj si skupinu vezme z držené zbraně (dracak_vtt_utok_skupina). Prázdné = neurčeno (bere se B,
-- ale nevynucuje se nula, jen varuje) — chování jako dřív.
--
-- POZOR: tenhle sloupec už byl na produkci přidán ručně 2026-11-11 (blok L). Tahle migrace ho jen doplňuje do
-- číslovaných migrací, aby ho měla i čistá databáze a testovací fixture. Na produkci sloupec existuje, takže
-- stránka Změny databáze migraci 0123 pozná jako „už hotovo“ (ADD COLUMN se ověřuje podle existence sloupce) a
-- jen ji odškrtne — žádný nový ruční SQL není potřeba.
--
-- Jen ALTER ADD COLUMN — nic se nemaže.

ALTER TABLE predmety
    ADD COLUMN zranitelnost_skupina CHAR(1) NULL COMMENT 'skupina útoku podle bestiáře (b17729-b17745); prázdné = B obyčejná zbraň';
