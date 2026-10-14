-- Běžné vybavení z ceníku (Nový ceník, content/pravidla-hrac.html h2055) —
-- Fáze 1b plánu (docs/stav-projektu.md): předměty dostávají typ "vybaveni",
-- skupinu ceníku (Vaky, Oblečení, Alkohol…) a kapacitu v mn u věcí, do
-- kterých se dá něco vložit (vaky, truhly, soudky, trakař…).
--
-- Rozšíření ENUM `typ` je čistě přídavné (nic se nemaže ani nepřejmenovává).
-- ALTER TABLE = DDL, web účet ho neprovede — ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql). Samotné řádky ceníku přidává až 0082
-- (DML) a proběhnou po tomhle kroku automaticky.
--
-- Jeden příkaz na sloupec: fallback v migrate.php ověřuje jen první ADD COLUMN
-- příkazu.

ALTER TABLE predmety MODIFY COLUMN typ ENUM('zbran','zbroj','surovina','artefakt','vybaveni') NOT NULL;
ALTER TABLE predmety ADD COLUMN kategorie_vybaveni VARCHAR(40) NULL COMMENT 'skupina v ceníku (Vaky, Oblečení, Alkohol…), jen typ vybaveni' AFTER typ;
ALTER TABLE predmety ADD COLUMN kapacita_mn DECIMAL(8,2) NULL COMMENT 'kolik mn se do předmětu vejde (vak, truhla, soudek…); NULL = není kontejner' AFTER vaha;
