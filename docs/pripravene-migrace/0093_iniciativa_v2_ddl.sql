-- Iniciativa v2 — struktura. Samé přidávání sloupců, nic se nemění ani nemaže.
-- kolo_ucastnik.utoku_za_2_kola: kolik útoků za 2 kola tvor umí (bonus k iniciativě 3 × (počet − 2), Tabulka vícenásobných útoků).
-- kolo_iniciativa.zavazek / zavazek_akci / zavazek_hotovo / akce_hotovo: úkon, za který tvor dostal bonus k iniciativě
-- (kouzlení, vícenásobné útoky), kolik akcí mu musí věnovat a kolik už věnoval (h1622: první akce kola a aspoň polovina akcí).
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md.

ALTER TABLE kolo_ucastnik ADD COLUMN utoku_za_2_kola TINYINT UNSIGNED NULL COMMENT 'kolik útoků za 2 kola tvor umí (NULL = 2, bez bonusu)';
ALTER TABLE kolo_iniciativa ADD COLUMN zavazek VARCHAR(10) NULL COMMENT 'kouzleni / utok / strelba — úkon, za který tvor dostal bonus k iniciativě';
ALTER TABLE kolo_iniciativa ADD COLUMN zavazek_akci TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'kolik akcí kola musí slíbenému úkonu věnovat (aspoň polovina)';
ALTER TABLE kolo_iniciativa ADD COLUMN zavazek_hotovo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'kolik akcí už slíbenému úkonu věnoval';
ALTER TABLE kolo_iniciativa ADD COLUMN akce_hotovo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'kolik vlastních akcí v kole už odehrál';
