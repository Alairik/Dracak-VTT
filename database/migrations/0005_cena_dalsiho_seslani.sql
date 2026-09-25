-- U vícenásobných kouzel (viz vicenasobne z 0003) je běžné, že první
-- seslání stojí víc many než každé další — např. "Modré blesky": 3 magy
-- za první blesk, 2 magy za každý další. cena_magenergie zůstává cenou
-- prvního/jediného seslání, tohle je cena za KAŽDÉ DALŠÍ.
-- Potvrzeno v datech: desítky kouzel mají tenhle vzorec zapsaný jen jako
-- volný text v cena_magenergie/popis (např. "3 magy první, 2 magy každý
-- další") — u nových/upravovaných záznamů půjde zapsat rovnou sem.

ALTER TABLE kouzla
    ADD COLUMN cena_dalsi_seslani VARCHAR(50) NULL
        COMMENT 'cena many za každé další seslání u vícenásobných kouzel, pokud se liší od cena_magenergie (cena prvního seslání)'
        AFTER cena_magenergie;
