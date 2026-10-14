-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně.
-- Předchozí bloky (migrace 0065–0079) jsou na produkci provedené.

-- 1) Smazání tří starých, nepoužívaných tabulek o únavě z migrace 0008. Neodpovídají
--    pravidlům (jiný model únavy) a nic je nečte. Schváleno uživatelem 2026-10-14.
DROP TABLE IF EXISTS stupne_unavy;
DROP TABLE IF EXISTS narocnost_akci_unava;
DROP TABLE IF EXISTS odstraneni_unavy;

-- Kontrola: má vyjít  zbylo = 0
SELECT COUNT(*) AS zbylo FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('stupne_unavy', 'narocnost_akci_unava', 'odstraneni_unavy');
