-- Strukturované kostky/bonusy pro entity, co dávají zranění/léčení atd.
-- Řeší třeba: "kouzlo léčí přesně 2k6+2" (pocet_kostek=2, typ_kostky='k6',
-- pevny_bonus=2), "Úder zraňuje za 3k10" (pocet_kostek=3, typ_kostky='k10'),
-- nebo "modré blesky, lze seslat víckrát, každý dá 1k6" (pocet_kostek=1,
-- typ_kostky='k6', vicenasobne=TRUE — celkový počet kostek = kolikrát se
-- to seslalo × pocet_kostek, to už je na vyhodnocení při hraní/na mapě,
-- databáze drží jen kostku "na jedno seslání" + příznak, že jde násobit).
--
-- Spustit AŽ PO importu drd-db-schema-v1.sql/drd-db-full-v1.sql.

ALTER TABLE kouzla
    ADD COLUMN pocet_kostek TINYINT UNSIGNED NULL COMMENT 'počet kostek, např. 2 u "2k6"' AFTER cena_magenergie,
    ADD COLUMN typ_kostky ENUM('k3','k4','k6','k8','k10','k12','k20','k100') NULL AFTER pocet_kostek,
    ADD COLUMN pevny_bonus SMALLINT NULL COMMENT 'pevný bonus navíc, např. +2 u "2k6+2"' AFTER typ_kostky,
    ADD COLUMN vicenasobne BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'lze seslat vícekrát za akci/kolo, každé seslání přidává dalších pocet_kostek×typ_kostky (např. modré blesky)' AFTER pevny_bonus;

ALTER TABLE zvlastni_schopnosti
    ADD COLUMN pocet_kostek TINYINT UNSIGNED NULL AFTER mechanika,
    ADD COLUMN typ_kostky ENUM('k3','k4','k6','k8','k10','k12','k20','k100') NULL AFTER pocet_kostek,
    ADD COLUMN pevny_bonus SMALLINT NULL AFTER typ_kostky,
    ADD COLUMN vicenasobne BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'lze provést vícekrát, škáluje počet kostek' AFTER pevny_bonus;

ALTER TABLE lektvary
    ADD COLUMN pocet_kostek TINYINT UNSIGNED NULL AFTER doba_pripravy,
    ADD COLUMN typ_kostky ENUM('k3','k4','k6','k8','k10','k12','k20','k100') NULL AFTER pocet_kostek,
    ADD COLUMN pevny_bonus SMALLINT NULL AFTER typ_kostky,
    ADD COLUMN vicenasobne BOOLEAN NOT NULL DEFAULT FALSE AFTER pevny_bonus;

ALTER TABLE finty
    ADD COLUMN pocet_kostek TINYINT UNSIGNED NULL AFTER pocet_akci,
    ADD COLUMN typ_kostky ENUM('k3','k4','k6','k8','k10','k12','k20','k100') NULL AFTER pocet_kostek,
    ADD COLUMN pevny_bonus SMALLINT NULL AFTER typ_kostky,
    ADD COLUMN vicenasobne BOOLEAN NOT NULL DEFAULT FALSE AFTER pevny_bonus;
