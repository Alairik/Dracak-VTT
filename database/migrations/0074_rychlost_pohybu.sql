-- Tabulky rychlosti pohybu — Fáze 4 plánu cestování (docs/stav-projektu.md).
--
-- Zdroj (ověřeno proti textu pravidel, ne odhadem):
--   bonus -5 až +5  — TABULKA RYCHLOSTI PŘI CHŮZI a PŘI BĚHU, PPP kap. Pohyb
--                     pod širým nebem (content/pravidla-hrac.html h1634,
--                     přepsáno z fotografie knihy, uživatel 2026-10).
--   bonus +6 až +10 — tytéž tabulky pro vyšší bonusy v Bestiáři (h2393,
--                     b17771 a b17778). Řady na sebe navazují beze zbytku
--                     (chůze +3/+5/+7, běh +4/+6/+9 na jeden bod bonusu).
-- Mimo tyto řádky (bonus pod -5 nebo nad +10) pravidla žádnou hodnotu
-- neuvádějí — kód pak vrací "mimo tabulku", nic se nedomýšlí.
--
-- Jednotka: body chůze za HODINU (rychlost_chuze), body běhu za KOLO
-- (rychlost_behu). Kolik mil to je, určí náročnost povrchu (PJ pravidla,
-- Tabulka povrchů h2366): mil/hod = body / náročnost.
--
-- CREATE TABLE = DDL, web účet (DML-only na Wedosu) ho neprovede — ručně přes
-- phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql); INSERT IGNORE níž je pak
-- idempotentní DML a migrate.php ho při dalším deployi provede sám.

CREATE TABLE IF NOT EXISTS rychlost_chuze (
    id                  TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bonus_pohyblivosti  TINYINT NOT NULL UNIQUE,
    prochazka           SMALLINT UNSIGNED NOT NULL COMMENT 'bodů chůze za hodinu při procházce',
    chuze               SMALLINT UNSIGNED NOT NULL COMMENT 'bodů chůze za hodinu při chůzi',
    spech               SMALLINT UNSIGNED NOT NULL COMMENT 'bodů chůze za hodinu při spěchu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA RYCHLOSTI PŘI CHŮZI, PPP h1634 (bonus -5..+5) + Bestiář h2393 (+6..+10).';

INSERT IGNORE INTO rychlost_chuze (bonus_pohyblivosti, prochazka, chuze, spech) VALUES
(-5, 12, 20, 28),
(-4, 15, 25, 35),
(-3, 18, 30, 42),
(-2, 21, 35, 49),
(-1, 24, 40, 56),
(0, 27, 45, 63),
(1, 30, 50, 70),
(2, 33, 55, 77),
(3, 36, 60, 84),
(4, 39, 65, 91),
(5, 42, 70, 98),
(6, 45, 75, 105),
(7, 48, 80, 112),
(8, 51, 85, 119),
(9, 54, 90, 126),
(10, 57, 95, 133);

CREATE TABLE IF NOT EXISTS rychlost_behu (
    id                  TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bonus_pohyblivosti  TINYINT NOT NULL UNIQUE,
    poklus              SMALLINT UNSIGNED NOT NULL COMMENT 'bodů běhu za kolo při poklusu',
    beh                 SMALLINT UNSIGNED NOT NULL COMMENT 'bodů běhu za kolo při běhu',
    sprint              SMALLINT UNSIGNED NOT NULL COMMENT 'bodů běhu za kolo při sprintu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA RYCHLOSTI PŘI BĚHU, PPP h1634 (bonus -5..+5) + Bestiář h2393 (+6..+10).';

INSERT IGNORE INTO rychlost_behu (bonus_pohyblivosti, poklus, beh, sprint) VALUES
(-5, 16, 24, 48),
(-4, 20, 30, 57),
(-3, 24, 36, 66),
(-2, 28, 42, 75),
(-1, 32, 48, 84),
(0, 36, 54, 93),
(1, 40, 60, 102),
(2, 44, 66, 111),
(3, 48, 72, 120),
(4, 52, 78, 129),
(5, 56, 84, 138),
(6, 60, 90, 147),
(7, 64, 96, 156),
(8, 68, 102, 165),
(9, 72, 108, 174),
(10, 76, 114, 183);
