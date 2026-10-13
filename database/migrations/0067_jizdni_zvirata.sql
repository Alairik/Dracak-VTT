-- Číselník jízdních zvířat — Fáze 2 plánu cestování (docs/stav-projektu.md).
--
-- Zdroj (ověřeno v content/pravidla-hrac.html, ne odhadem): "TABULKA
-- JEZDECKÝCH ZVÍŘAT" h1635 (PPP) — 7 zvířat. Čísla se do jednoho
-- shodují s nezávislou homebrew tabulkou "Jezdečtí tvorové" h2057
-- (Ceníky), kde jsou navíc ceny a další ~40 tvorů (jiná zvířata, letci
-- se samostatnými řádky "na zemi", povozy) — ty tu NEJSOU: pro rozšíření
-- je potřeba nejdřív rozhodnout, jak se v modelu značí letecké/pozemní
-- varianty a co přesně znamená sloupec "Ujede (číslo : 10)" a poznámky
-- * / ** v h2057, které tabulka nevysvětluje.
--
-- Sloupce:
--   pohyblivost       "žádné naložení" (h2057 hlavička sloupce) — tedy
--                     nenaložené zvíře; naložení ji snižuje stejně jako
--                     u postav (Tabulka naložení, h1681)
--   nosnost_mn        nosnost v mn
--   vytrvalost        body únavy, které zvíře snese, než je unavené
--   ujede_mil         "ujede (mil)" z h1635
--   cas_na_10_mil_min "čas na 10 mil" z h1635, tam ve formátu h:mm
--                     (2:00, 1:30, 1:45) — uloženo v minutách
--   popis             doslovný text z odstavce "Jezdecká zvířata" (h1635)
--
-- Tažný kůň: tabulka (h1635 i h2057) má nosnost 800 mn, stejně jako
-- klasický kůň, ale slovní popis hned nad ní říká "nosnost o čtvrtinu
-- vyšší než klasický, 1000 mn v zápřahu". Uloženo je to, co říkají obě
-- tabulky (800); rozpor ve zdrojovém textu zůstává otevřený (ověřit proti
-- fyzické knize).
--
-- CREATE TABLE = DDL, web účet (DML-only na Wedosu) ho neprovede. Postup:
-- ručně spustit jen příkaz CREATE TABLE přes phpMyAdmin (admin účet);
-- INSERT IGNORE níž je pak idempotentní DML a migrate.php ho při dalším
-- deployi provede sám (fallback na CREATE TABLE IF NOT EXISTS ověří přes
-- SELECT, že tabulka existuje). Nebo spustit celý soubor ručně a zapsat
-- do migrace_log.

CREATE TABLE IF NOT EXISTS jizdni_zvirata (
    id                 TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev              VARCHAR(50) NOT NULL UNIQUE,
    pohyblivost        TINYINT UNSIGNED NOT NULL COMMENT 'nenaložené zvíře (h2057: "žádné naložení")',
    nosnost_mn         SMALLINT UNSIGNED NOT NULL,
    vytrvalost         TINYINT UNSIGNED NOT NULL COMMENT 'body únavy do "unavené" (jako vytrvalost postavy)',
    ujede_mil          SMALLINT UNSIGNED NOT NULL,
    cas_na_10_mil_min  SMALLINT UNSIGNED NOT NULL COMMENT 'v pravidlech h:mm, tady v minutách',
    popis              TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA JEZDECKÝCH ZVÍŘAT, PPP h1635; rozšíření viz homebrew h2057.';

INSERT IGNORE INTO jizdni_zvirata (nazev, pohyblivost, nosnost_mn, vytrvalost, ujede_mil, cas_na_10_mil_min, popis) VALUES
('osel',          8, 400, 10, 28, 120, 'levný, odolný, netrpí závratí, nenese postavy B+, svéhlavý'),
('mezek',        12, 600, 12, 38,  90, 'kříženec oslice a koně, jako osel, nenese C+'),
('mula',         21, 700, 17, 53,  90, 'kříženec kobyly a osla, houževnatá, netrpí závratí, vysoká inteligence, nese i lehčí C'),
('pony',         14, 700, 12, 29, 120, 'malý, vytrvalý, snáší extrémní podmínky, nenese postavy C+'),
('tažný kůň',    18, 800, 15, 42, 105, 'těžká práce, nosnost o čtvrtinu vyšší než klasický, 1000 mn v zápřahu'),
('klasický kůň', 18, 800, 14, 39, 105, 'běžný, krokem, nízká inteligence'),
('jezdecký kůň', 21, 750, 16, 50,  90, 'klus/cval, dobrodruzi');
