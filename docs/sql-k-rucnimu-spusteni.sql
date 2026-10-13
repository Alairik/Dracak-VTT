-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Pořadí je důležité.
-- Ověřeno na kopii produkčního stavu (migrace 0001–0064) s DML-only účtem:
-- po tomhle bloku doběhne migrate.php v dalším deployi bez chyby.
-- Jestli některý příkaz spadne na "Duplicate column" / "already exists", ta část
-- už je provedená — stačí ji vynechat a spustit zbytek.

-- 1) 0065: únava postavy
ALTER TABLE postavy
    ADD COLUMN unava SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_magenergie;

-- 2) 0067: číselník jízdních zvířat (tabulka + 7 řádků)
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

-- 3) 0068: doprava postavy (musí po bodu 2)
ALTER TABLE postavy
    ADD COLUMN doprava_typ ENUM('chuze','zvire') NOT NULL DEFAULT 'chuze',
    ADD COLUMN doprava_zvire_id TINYINT UNSIGNED NULL,
    ADD CONSTRAINT fk_postavy_doprava_zvire FOREIGN KEY (doprava_zvire_id) REFERENCES jizdni_zvirata (id);

-- 4) 0070: pole pro Osobní deník (zkušenost, peníze, zbroj, štít, texty)
ALTER TABLE postavy
    ADD COLUMN zkusenost INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN penize_zl INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN penize_st INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN penize_md INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN velikost VARCHAR(2) NULL,
    ADD COLUMN zbroj_id INT UNSIGNED NULL,
    ADD COLUMN stit_id INT UNSIGNED NULL,
    ADD COLUMN drzena_zbran_id INT UNSIGNED NULL,
    ADD COLUMN puvod TEXT NULL,
    ADD COLUMN osobnost TEXT NULL,
    ADD CONSTRAINT fk_postavy_zbroj FOREIGN KEY (zbroj_id) REFERENCES predmety (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_postavy_stit FOREIGN KEY (stit_id) REFERENCES predmety (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_postavy_drzena_zbran FOREIGN KEY (drzena_zbran_id) REFERENCES predmety (id) ON DELETE SET NULL;

-- 5) 0071: síla a délka zbraně
ALTER TABLE predmety
    ADD COLUMN sila_zbrane TINYINT UNSIGNED NULL,
    ADD COLUMN delka_zbrane VARCHAR(10) NULL;
