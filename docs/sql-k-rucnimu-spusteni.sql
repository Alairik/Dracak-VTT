-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Předchozí bloky (migrace 0065–0085) jsou na produkci provedené.
-- Na konci je kontrola: má vyjít  sloupec_hexu = 1, pravidla_pohybu = 3, tvary = 4, efekt_behu = 1.
--
-- 0086: souboj, krok 2b — tvorové na více hexech (zvířecí C = 2, D = 3, E = 5),
-- pohyb Krok / Běh / Sprint a otočení při kroku až o 270° (domácí pravidla).

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN hexu TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''kolik hexů tvor zabírá: 1, 2, 3, 5'' AFTER velikost_sahu',
  'SELECT ''0086 tokeny.hexu už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'hexu');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS souboj_pohyb (
  kod VARCHAR(12) NOT NULL,
  nazev VARCHAR(40) NOT NULL,
  hexu TINYINT UNSIGNED NOT NULL COMMENT 'o kolik hexů smí tvor jednou akcí',
  jen_pri_umyslu VARCHAR(16) NULL COMMENT 'NULL = kdykoliv, jinak úmysl, při kterém smí (např. pohyb)',
  bonus_dovednost TINYINT NOT NULL DEFAULT 0 COMMENT 'o kolik hexů víc s dovedností Běh na krátké vzdálenosti',
  otoceni_max SMALLINT UNSIGNED NOT NULL DEFAULT 270 COMMENT 'o kolik stupňů se smí tvor při pohybu otočit',
  zdroj VARCHAR(255) NULL,
  PRIMARY KEY (kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tvary_tvoru (
  hexu TINYINT UNSIGNED NOT NULL COMMENT 'kolik hexů tvor zabírá',
  popis VARCHAR(255) NOT NULL,
  typ_otaceni VARCHAR(10) NOT NULL COMMENT 'smer (čelem k jedné ze šesti stran) / hrana (čelo je hrana horních hexů)',
  utok VARCHAR(10) NOT NULL COMMENT 'predni3 (3 hexy před pohledovým hexem) / okoli (hexy sousedící s pohledovými)',
  bunky TEXT NOT NULL COMMENT 'JSON [[q,r],…] vůči otočnému hexu při orientaci 0',
  predni TEXT NOT NULL COMMENT 'JSON [[q,r],…] pohledové hexy při orientaci 0',
  zdroj VARCHAR(255) NULL,
  PRIMARY KEY (hexu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO souboj_pohyb (kod, nazev, hexu, jen_pri_umyslu, bonus_dovednost, otoceni_max, zdroj) VALUES ('krok', 'Krok', 1, NULL, 0, 270, 'Kniha h1623: krok = přesun na sousední hex; HB: otočení při kroku až o 270°');
INSERT IGNORE INTO souboj_pohyb (kod, nazev, hexu, jen_pri_umyslu, bonus_dovednost, otoceni_max, zdroj) VALUES ('beh', 'Běh', 2, 'pohyb', 1, 270, 'Kniha h1625: při úmyslu Pohyb se krok smí protáhnout na dva hexy (běh); HB: +1 hex za dovednost Běh na krátké vzdálenosti (h1698)');
INSERT IGNORE INTO souboj_pohyb (kod, nazev, hexu, jen_pri_umyslu, bonus_dovednost, otoceni_max, zdroj) VALUES ('sprint', 'Sprint', 3, 'pohyb', 1, 270, 'HB (domácí pravidlo): sprint 3 hexy při úmyslu Pohyb; +1 hex za dovednost Běh na krátké vzdálenosti (h1698)');

INSERT IGNORE INTO tvary_tvoru (hexu, popis, typ_otaceni, utok, bunky, predni, zdroj) VALUES (1, 'Tvor na jednom hexu; čelem k jedné ze šesti stran.', 'smer', 'predni3', '[[0,0]]', '[[0,0]]', 'HB (domácí pravidlo): tvorové na více hexech, h1623');
INSERT IGNORE INTO tvary_tvoru (hexu, popis, typ_otaceni, utok, bunky, predni, zdroj) VALUES (2, 'Zvířecí C: dva hexy za sebou; otáčí se kolem zadního hexu, hlava je před ním.', 'smer', 'predni3', '[[0,0],[1,0]]', '[[1,0]]', 'HB (domácí pravidlo): tvorové na více hexech, h1623');
INSERT IGNORE INTO tvary_tvoru (hexu, popis, typ_otaceni, utok, bunky, predni, zdroj) VALUES (3, 'D: trojúhelník, nahoře dva hexy a pod nimi otočný hex; čelo je hrana obou horních hexů.', 'hrana', 'okoli', '[[0,0],[0,-1],[1,-1]]', '[[0,-1],[1,-1]]', 'HB (domácí pravidlo): tvorové na více hexech, h1623');
INSERT IGNORE INTO tvary_tvoru (hexu, popis, typ_otaceni, utok, bunky, predni, zdroj) VALUES (5, 'E: nahoře dva hexy, pod nimi řada tří; otáčí se kolem prostředního hexu; čelo je hrana obou horních hexů.', 'hrana', 'okoli', '[[-1,0],[0,0],[1,0],[0,-1],[1,-1]]', '[[0,-1],[1,-1]]', 'HB (domácí pravidlo): tvorové na více hexech, h1623');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Běh na krátké vzdálenosti', 'buff', 'pohyb (běh, sprint)', '+1 hex při běhu a sprintu v boji', 'jednorazove',
       '3 kola (do dalšího ověření dovednosti)', 'Další ověření dovednosti (v boji 1 za 3 kola) neuspěje, nebo konec boje.', 0,
       'Dovednost Běh na krátké vzdálenosti (síla, střední; h1698): při úspěchu +1 sáh k rychlosti pohybu pro běh a sprint při boji, ověřuje se v boji jednou za 3 kola. Tohle je stav „dovednost zabrala“ — PJ ho po úspěšném ověření nasadí postavě na 3 kola a souboj pak sám přidá +1 hex k běhu a sprintu.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Běh na krátké vzdálenosti');

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'hexu') AS sloupec_hexu,
  (SELECT COUNT(*) FROM souboj_pohyb) AS pravidla_pohybu,
  (SELECT COUNT(*) FROM tvary_tvoru) AS tvary,
  (SELECT COUNT(*) FROM efekty WHERE nazev = 'Běh na krátké vzdálenosti') AS efekt_behu;
