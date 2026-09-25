-- Tři provázané univerzální mechaniky, které dřív nešly odvodit vůbec:
--
-- 1) Oprava za atribut — jak se ze stupně (hodnoty) atributu spočítá
--    bonus/postih k hodům. Bez tohohle nejde použít žádný atribut nikde.
-- 2) Nosnost podle Síly — kolik uneseš bez postihu; kolikanásobek
--    překročíš, o tolik čtvrtin klesá Pohyblivost a roste Únava.
-- 3) Pohyblivost — základ podle rasy (+ oprava za OBR, dopočítá appka).
--    U ras, kde jméno přesně sedí s pravidly (Trpaslík/Člověk/Elf/Barbar),
--    doplněno rovnou. Zbytek (Hobit/Kudůk/Kroll/homebrew) je NULL —
--    nechci hádat mapování jmen bez potvrzení.

CREATE TABLE IF NOT EXISTS opravy_za_atribut (
    id         TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stupen_od  TINYINT UNSIGNED NOT NULL,
    stupen_do  TINYINT UNSIGNED NOT NULL,
    oprava     TINYINT NOT NULL,
    poznamka   VARCHAR(100) NULL COMMENT 'např. "Naprosto zakrnělý", "Legendární úroveň"'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Nad 23 pokračuje stejná posloupnost: oprava roste o 1 za každé 2 stupně (formule floor((stupen-10)/2)), tabulka jde jen po 22-23.';

INSERT INTO opravy_za_atribut (stupen_od, stupen_do, oprava, poznamka) VALUES
(1, 1,   -5, 'Naprosto zakrnělý'),
(2, 3,   -4, 'Hluboce podprůměrný'),
(4, 5,   -3, 'Hluboce podprůměrný'),
(6, 7,   -2, 'Lehce podprůměrný'),
(8, 9,   -1, 'Lehce podprůměrný'),
(10, 11,  0, 'Průměrný'),
(12, 13,  1, 'Lehký nadprůměr (jeden z deseti)'),
(14, 15,  2, 'Lehký nadprůměr (jeden z deseti)'),
(16, 17,  3, 'Vysoký nadprůměr (jeden ze sta)'),
(18, 19,  4, 'Vysoký nadprůměr (jeden ze sta)'),
(20, 21,  5, 'Extrémní nadprůměr (jeden z tisíce)'),
(22, 23,  6, 'Legendární úroveň');

-- `id` navíc (i když by stačilo oprava_sil jako PK) — generický editor
-- v1 počítá s primárním klíčem `id` na každé tabulce, viz precedens
-- s vynechanou `velikosti`.
CREATE TABLE IF NOT EXISTS nosnost_podle_sily (
    id                   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    oprava_sil           TINYINT NOT NULL UNIQUE COMMENT 'oprava za SIL, -5 až +6, viz opravy_za_atribut',
    zakladni_nosnost_lb  DECIMAL(6,2) NOT NULL COMMENT 'lze krátkodobě uzvednout/nést až 4× tuto hodnotu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Za každý násobek překročení: Pohyblivost -1/4, +1 bod únavy na akci označenou *.';

INSERT INTO nosnost_podle_sily (oprava_sil, zakladni_nosnost_lb) VALUES
(-5, 7), (-4, 8), (-3, 9), (-2, 11), (-1, 13), (0, 15),
(1, 17), (2, 19), (3, 21), (4, 23), (5, 25), (6, 28);

ALTER TABLE rasy
    ADD COLUMN pohyblivost_zaklad SMALLINT NULL
        COMMENT 'základní Pohyblivost PŘED opravou za OBR (finální = tohle + oprava za OBR)';

UPDATE rasy SET pohyblivost_zaklad = 20 WHERE nazev = 'Trpaslík';
UPDATE rasy SET pohyblivost_zaklad = 30 WHERE nazev = 'Člověk';
UPDATE rasy SET pohyblivost_zaklad = 30 WHERE nazev = 'Elf';
UPDATE rasy SET pohyblivost_zaklad = 30 WHERE nazev = 'Barbar';
-- Hobit/Kudůk/Kroll a homebrew rasy záměrně nedoplněny — jména v pravidlové
-- tabulce (Půlčík/Gnóm/Obr) přesně nesedí na naše rasy, nechci hádat.
