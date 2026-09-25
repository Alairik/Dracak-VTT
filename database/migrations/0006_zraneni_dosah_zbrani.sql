-- Tabulky zbraní v pravidlech mají sloupce Útočnost/Zranění/Obrana/Dosah
-- (na blízko) nebo Útočnost/Zranění/Obrana/Dostřel (na dálku, dvě čísla:
-- efektivní/maximální). V predmety byly zavedené jen utocnost a oc
-- (Obrana) — "zranění", tedy nejdůležitější bojové číslo na zbrani,
-- chybělo úplně. `uc` byl omylem převzatý z JINÉ trojice (ÚČ u
-- improvizovaných zbraní v PPP), nekryje se s core tabulkou zbraní —
-- necháváno, jen okomentováno, ať se to příště neplete znovu.
--
-- kategorie_zbrane: pravidla používají "Drtivá", ne "tupá" (SET rozšířen,
-- 'tupa' necháno pro zpětnou kompatibilitu se staršími řádky). Vrhací a
-- střelné zbraně mají "Typ" zapsaný stejně jako sečná/bodná/drtivá
-- (viz TABULKA STŘELNÝCH A VRHACÍCH ZBRANÍ), proto přidáno i sem.

ALTER TABLE predmety
    MODIFY COLUMN uc SMALLINT NULL COMMENT 'JEN pro improvizované zbraně (PPP) — ÚČ z trojice ÚČ/útočnost/OČ. Není součástí core tabulky zbraní (ta má Útočnost/Zranění/Obranu), nepoužívej u běžných zbraní.',
    ADD COLUMN zraneni SMALLINT NULL COMMENT 'Zranění — kolik životů zbraň při zásahu strhne' AFTER utocnost,
    ADD COLUMN dosah VARCHAR(30) NULL COMMENT 'dosah na blízko, např. "1,5 sáhu" nebo "3 sáhy"' AFTER oc,
    ADD COLUMN dostrel_efektivni SMALLINT NULL COMMENT 'efektivní dostřel u střelných/vrhacích zbraní' AFTER dosah,
    ADD COLUMN dostrel_maximalni SMALLINT NULL COMMENT 'maximální dostřel (nad efektivní = postih -5 k útoku)' AFTER dostrel_efektivni,
    ADD COLUMN sil_pozadavek VARCHAR(30) NULL COMMENT 'např. "bez omezení", "SIL 0 a vyšší", "SIL +2 a vyšší"' AFTER dostrel_maximalni,
    MODIFY COLUMN kategorie_zbrane SET('secna', 'bodna', 'tupa', 'drtiva', 'vrhaci', 'strelna') NULL COMMENT 'pravidla používají "drtivá", ne "tupá" — tupa jen kvůli starším řádkům';
