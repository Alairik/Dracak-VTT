-- Dvě věci, co v datech chybí, i když jsou v pravidlech běžné:
--
-- 1) Rozsah — kolik cílů/jak velká oblast (odlišné od Dosahu = vzdálenost).
--    Je to jedno z pěti základních polí "SPECIFIKACE KOUZEL" v pravidlech
--    (Mana/Dosah/Rozsah/Trvání/Vyvolání), ale v DB schématu chybělo.
--
-- 2) Max. počet seslání/použití — u vícenásobných efektů (modré blesky)
--    se často omezuje, kolikrát za kolo/souboj/den je lze použít. V reálných
--    datech to zatím je jen jako volný text uvnitř popisu (např. "Četnost:
--    1 tvor 3x denně"), proto i tady jde o volný text, ne číslo + enum —
--    přesná fráze se hodně liší kouzlo od kouzla.
--
-- Existující řádky se NEPŘEPISUJÍ — tohle jen přidává prázdné sloupce,
-- doplnění hodnot ze stávajícího volného textu v popisu je samostatná
-- (velká, ruční) práce, viz docs/kontrolni-seznam-neuplnych-mist.md.

ALTER TABLE kouzla
    ADD COLUMN rozsah VARCHAR(150) NULL COMMENT 'počet cílů / oblast, odlišné od dosahu (vzdálenosti)' AFTER dosah,
    ADD COLUMN max_pouziti VARCHAR(100) NULL COMMENT 'např. "3x denně", "1x za souboj" — volný text, fráze se hodně liší' AFTER vicenasobne;

ALTER TABLE zvlastni_schopnosti
    ADD COLUMN max_pouziti VARCHAR(100) NULL COMMENT 'např. "3x denně" — volný text' AFTER vicenasobne;

ALTER TABLE lektvary
    ADD COLUMN max_pouziti VARCHAR(100) NULL AFTER vicenasobne;

ALTER TABLE finty
    ADD COLUMN max_pouziti VARCHAR(100) NULL AFTER vicenasobne;
