-- Povozy a spřežení — Fáze 3 plánu cestování (docs/stav-projektu.md).
--
-- Zdroje (vše ověřeno v content/pravidla-*.html, ne odhadem):
--   PPP "Povozy a spřežení" (pravidla-hrac.html h1639–h1641): typy, váhy,
--     konstrukční nosnost (násobek váhy), únava cestujících, Tabulka naložení
--     povozu; pravidlo "z nákladu se počítá jen čtvrtina, každé další zvíře
--     jen poloviční" (nosnost spřežení = 1 + 0,5 × (počet − 1) násobku nosnosti
--     zvířete, v zápřahu čtyřnásobek, viz Tabulka nosnosti spřežení).
--   PJ "Povozy" (pravidla-pj.html h2366): TABULKA ŘÍZENÍ (pravděpodobnost
--     úspěšného řízení při klusu a rychleji, snížení za každý bod náročnosti
--     nad 10) a seznam 8 modelů povozů (TABULKA CENY POVOZŮ).
--   Homebrew tabulka POVOZY (pravidla-hrac.html, tabulka Jezdečtí tvorové
--     h2057, Ceníky, upraveno 14. 5. 2025): druhé rozmezí váhy, únava u
--     žebřiňáku a valníku (PPP ji pro ně neuvádí), kapacita osob, max. krokem.
--
-- Typy z PPP (vůz/kočár/dostavník) a 8 modelů z h2057 se nekryjí jedna ku
-- jedné, ale NEJSOU si v cestě: těch 8 modelů je i v základních pravidlech PJ
-- (Tabulka ceny povozů, Tabulka řízení) — kategorie je jen jejich seskupení.
-- Váhy se v PPP a v h2057 mírně liší; obě rozmezí jsou uložena (vaha_* = PPP,
-- vaha_hb_* = h2057). Přednost má homebrew (rozhodnutí uživatele 2026-10-14:
-- je upravený vůči realitě) — kalkulačka cesty předvyplní h2057, PJ číslo přepíše.
--
-- Sloupce:
--   nosnost_nasobek_od/do   konstrukční nosnost = násobek vlastní váhy vozu
--   unava_za_body_zvirete   cestující dostane 1 bod únavy za tolik bodů únavy
--                           tažného zvířete (žebřiňák/valník: jen h2057)
--   klus                    'ne' (maringotka — jen krokem), 'jen_dobra_cesta'
--                           (vozy: klusem při náročnosti nejvýš 10), 'ano'
--                           (kočáry a dostavníky "poměrně bezpečně")
--   rizeni_*                % úspěchu řízení při klusu / pomalém cvalu /
--                           rychlém cvalu / trysku; rizeni_snizeni = o kolik
--                           % klesne za každý bod náročnosti nad 10
--   kapacita_osob, ridicu   h2057 (NULL = nákladní vůz bez udané kapacity)
--
-- povoz_nalozeni = TABULKA NALOŽENÍ POVOZU (h1641): kolik procent provozního
-- nákladu lze při dané náročnosti terénu naložit; narocnost_do je horní mez
-- ("více než 30 = nelze projet" je řádek 999 / 0 %).
--
-- CREATE TABLE = DDL, web účet (DML-only na Wedosu) ho neprovede — ručně přes
-- phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql); INSERT IGNORE níž je pak
-- idempotentní DML a migrate.php ho při dalším deployi provede sám.

CREATE TABLE IF NOT EXISTS povozy (
    id                       TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev                    VARCHAR(40) NOT NULL UNIQUE,
    kategorie                ENUM('vuz','kocar','dostavnik') NOT NULL,
    vaha_od_mn               SMALLINT UNSIGNED NOT NULL COMMENT 'váha prázdného vozu, PPP',
    vaha_do_mn               SMALLINT UNSIGNED NOT NULL,
    vaha_hb_od_mn            SMALLINT UNSIGNED NULL COMMENT 'váha prázdného vozu, homebrew h2057',
    vaha_hb_do_mn            SMALLINT UNSIGNED NULL,
    nosnost_nasobek_od       DECIMAL(3,1) NOT NULL COMMENT 'konstrukční nosnost = násobek váhy vozu',
    nosnost_nasobek_do       DECIMAL(3,1) NOT NULL,
    unava_za_body_zvirete    TINYINT UNSIGNED NOT NULL COMMENT 'cestující: 1 bod únavy za tolik bodů únavy zvířete',
    klus                     ENUM('ne','jen_dobra_cesta','ano') NOT NULL,
    rizeni_klus              TINYINT UNSIGNED NOT NULL,
    rizeni_pomaly_cval       TINYINT UNSIGNED NOT NULL,
    rizeni_rychly_cval       TINYINT UNSIGNED NOT NULL,
    rizeni_trysk             TINYINT UNSIGNED NOT NULL,
    rizeni_snizeni           TINYINT UNSIGNED NOT NULL COMMENT '% za každý bod náročnosti nad 10',
    kapacita_osob            TINYINT UNSIGNED NULL,
    ridicu                   TINYINT UNSIGNED NULL,
    popis                    TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Povozy: PPP h1639–h1641, PJ Tabulka řízení h2366, homebrew POVOZY h2057.';

INSERT IGNORE INTO povozy (nazev, kategorie, vaha_od_mn, vaha_do_mn, vaha_hb_od_mn, vaha_hb_do_mn, nosnost_nasobek_od, nosnost_nasobek_do, unava_za_body_zvirete, klus, rizeni_klus, rizeni_pomaly_cval, rizeni_rychly_cval, rizeni_trysk, rizeni_snizeni, kapacita_osob, ridicu, popis) VALUES
('žebřiňák',        'vuz',       4000, 6000, 3000,  6000, 1.0, 1.0, 4, 'jen_dobra_cesta', 70, 40, 30, 20, 1, NULL, NULL, 'otevřený vůz pro velkoobjemový náklad na kratší vzdálenosti; zapřahují se obvykle dvě zvířata, u nejmenších i poník, mezek či osel'),
('valník',          'vuz',       4500, 7000, 3500,  7000, 1.5, 1.5, 4, 'jen_dobra_cesta', 80, 50, 30, 30, 1, NULL, NULL, 'plochý otevřený vůz (pytle, sudy, dřevo, písek, kámen); zapřahuje se pár zvířat'),
('krytý vůz',       'vuz',       5000, 8000, 4500,  8000, 2.0, 3.0, 6, 'jen_dobra_cesta', 80, 50, 40, 30, 1, NULL, NULL, 'klasický nákladní vůz s plachtou pro dálkovou dopravu; lehký pár zvířat, těžký vícespřeží; nejbytelnější pojmou trojnásobek váhy'),
('maringotka',      'vuz',       6000, 8000, 6000, 10000, 1.5, 2.0, 8, 'ne',              50, 20, 10,  0, 3, NULL, NULL, 'obyvatelný domek na kolech; jen krokem; výjimečně dvojnásobek váhy'),
('malý kočár',      'kocar',     4000, 4700, 3500,  4500, 1.5, 2.0, 10, 'ano',            99, 80, 70, 40, 2, 2,    1,    'dvě osoby a kočí; krytý, otevřený váží zhruba polovinu; jeden pár jezdeckých koní'),
('velký kočár',     'kocar',     4500, 5600, 5000,  8000, 1.5, 2.0, 10, 'ano',            99, 90, 80, 50, 4, 4,    2,    'čtyři osoby uvnitř a dvě venku (z nich kočí); krytý, otevřený váží zhruba polovinu; dva páry jezdeckých koní'),
('malý dostavník',  'dostavnik', 6000, 7000, 6000,  8000, 2.0, 2.0, 8, 'ano',             99, 80, 60, 30, 3, 5,    1,    'pět osob uvnitř a kočí; zavazadla obecně nejvýš 400 mn na osobu; dva páry koní (v nouzi pár po dobré cestě)'),
('velký dostavník', 'dostavnik', 7000, 8000, 8500, 10000, 2.0, 2.0, 8, 'ano',             99, 80, 70, 40, 5, 9,    2,    'sedm až devět cestujících (dva venku); zavazadla jako u malého; tři páry koní');

CREATE TABLE IF NOT EXISTS povoz_nalozeni (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    narocnost_do SMALLINT UNSIGNED NOT NULL UNIQUE COMMENT 'horní mez náročnosti terénu (bodů pohybu na míli)',
    procento     TINYINT UNSIGNED NOT NULL COMMENT 'kolik % provozního nákladu (posádka + náklad, bez váhy vozu) lze naložit',
    teren        VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA NALOŽENÍ POVOZU, PPP h1641.';

INSERT IGNORE INTO povoz_nalozeni (narocnost_do, procento, teren) VALUES
(10,  100, 'rovina, velmi dobrá cesta'),
(12,   90, 'mírné zvlnění, méně kvalitní cesta'),
(15,   80, 'pahorkatina, nekvalitní cesta'),
(20,   65, 'vrchovina, pevný terén mimo cestu'),
(30,   30, 'hory, velmi těžko sjízdný terén'),
(999,   0, 'velehory (více než 30) — nelze projet');
