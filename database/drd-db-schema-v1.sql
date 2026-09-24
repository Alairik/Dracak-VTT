-- =====================================================================
-- Pravidla DrD + domácí pravidla — databázový model (v1)
-- Cílový engine: MySQL / MariaDB (běžný PHP webhosting, import přes phpMyAdmin)
-- Znaková sada: utf8mb4 (plná podpora české diakritiky)
--
-- STAV OBSAHU:
--   - Referenční/enum tabulky (velikosti, obory magie, skupiny kouzel,
--     škála výsledků testu, potvrzené kódy zranitelnosti) JSOU naplněné —
--     jde o data ověřená přímo v textu pravidel.
--   - Obsahové tabulky (kouzla, nestvůry, rasy, povolání, schopnosti,
--     finty, předměty, lektvary) jsou PRÁZDNÉ nebo mají jen 1-2 řádky
--     jasně označené jako DEMO — je v nich spousta nejistot podle
--     kontrolního seznamu neúplných míst a nechci do "finální" databáze
--     zapsat neověřený obsah jako hotový fakt.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. ZÁKLADNÍ ENUMY / ČÍSELNÍKY
-- =====================================================================

CREATE TABLE vlastnosti (
    id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kod           VARCHAR(10)  NOT NULL UNIQUE COMMENT 'Sil, Obr, Odl, Int, Chr, Roz, Žvt...',
    nazev         VARCHAR(50)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vlastnosti (kod, nazev) VALUES
('Sil','Síla'), ('Obr','Obratnost'), ('Odl','Odolnost'),
('Int','Inteligence'), ('Chr','Charisma'), ('Roz','Rozdíl úrovní'), ('Zvt','Životaschopnost / 5');

CREATE TABLE vysledky_testu (
    id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kod     VARCHAR(30) NOT NULL UNIQUE COMMENT 'fatalni_neuspech / neuspech / uspech / fatalni_uspech',
    nazev   VARCHAR(50) NOT NULL,
    poradi  TINYINT UNSIGNED NOT NULL COMMENT 'ordinální pořadí pro řazení a porovnávání'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vysledky_testu (kod, nazev, poradi) VALUES
('fatalni_neuspech', 'Fatální neúspěch', 1),
('neuspech',         'Neúspěch',         2),
('uspech',           'Úspěch',           3),
('fatalni_uspech',   'Fatální úspěch',   4);

CREATE TABLE velikosti (
    kod         VARCHAR(2) PRIMARY KEY COMMENT 'A0, A, B, C, D, E',
    nazev       VARCHAR(100) NOT NULL,
    popis       TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO velikosti (kod, nazev, popis) VALUES
('A0', 'Drobný tvor', 'Do cca 25 coulů (např. krysa). Na jeden hex se vejde víc tvorů této velikosti.'),
('A',  'Malý tvor',   'Do 1,5 sáhu (např. trpaslík a menší).'),
('B',  'Střední tvor','Do 2 sáhů (např. člověk). Do třídy se počítá i stavba těla, ne jen výška.'),
('C',  'Velký tvor',  'Do 3 sáhů (např. obr).'),
('D',  'Obrovský tvor','Do 20 sáhů (větší než obr, menší než drak).'),
('E',  'Kolosální tvor','Drak a větší.');

CREATE TABLE velikost_modifikatory (
    velikost_utocnik  VARCHAR(2) NOT NULL,
    velikost_obrance  VARCHAR(2) NOT NULL,
    modifikator_utoku SMALLINT NOT NULL COMMENT 'Bonus/postih k hodu na útok. Obrana se samostatně neopravuje.',
    PRIMARY KEY (velikost_utocnik, velikost_obrance),
    FOREIGN KEY (velikost_utocnik) REFERENCES velikosti(kod),
    FOREIGN KEY (velikost_obrance) REFERENCES velikosti(kod)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TBD: kompletní 6×6 matice hodnot -7..+4 z sekce "Útok" (core h1610) — je potřeba dosadit přesná čísla ze zdroje, tady je jen struktura.';

CREATE TABLE obory_magie (
    id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kod     VARCHAR(3)  NOT NULL UNIQUE COMMENT 'Zažitá zkratka, MUSÍ zůstat viditelná v UI (ČP/EU/EO/IL/MA/VI/PS/PO)',
    nazev   VARCHAR(60) NOT NULL,
    popis   TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO obory_magie (kod, nazev, popis) VALUES
('ČP', 'časoprostorová magie', 'Kouzla spojená s tokem času, jeho změnami a přesuny v čase a prostoru.'),
('EU', 'energetická útočná magie', NULL),
('EO', 'energetická ochranná magie', NULL),
('IL', 'iluzionistická magie', NULL),
('MA', 'materiální magie', 'Nejbohatší obor magie — drobná domácí kouzla i razantní metamorfózy.'),
('VI', 'vitální magie', NULL),
('PS', 'psychická magie', NULL),
('PO', 'magie poznávání', NULL);

CREATE TABLE skupiny_kouzel (
    id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev   VARCHAR(60) NOT NULL UNIQUE COMMENT 'Kouzla fyzická / psychická / útočná / ochranná (PJ pravidla, h2258)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO skupiny_kouzel (nazev) VALUES
('Kouzla fyzická'), ('Kouzla psychická'), ('Kouzla útočná'), ('Kouzla ochranná');

CREATE TABLE skupina_kouzel_obor_magie (
    skupina_id  TINYINT UNSIGNED NOT NULL,
    obor_id     TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (skupina_id, obor_id),
    FOREIGN KEY (skupina_id) REFERENCES skupiny_kouzel(id) ON DELETE CASCADE,
    FOREIGN KEY (obor_id) REFERENCES obory_magie(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kouzla fyzická = časoprostorová + materiální; psychická = vitální + psychická;
-- útočná = energetická útočná; ochranná = energetická ochranná (PJ h2258)
INSERT INTO skupina_kouzel_obor_magie (skupina_id, obor_id)
SELECT s.id, o.id FROM skupiny_kouzel s, obory_magie o WHERE
   (s.nazev='Kouzla fyzická'   AND o.kod IN ('ČP','MA'))
OR (s.nazev='Kouzla psychická' AND o.kod IN ('VI','PS'))
OR (s.nazev='Kouzla útočná'    AND o.kod = 'EU')
OR (s.nazev='Kouzla ochranná'  AND o.kod = 'EO');

CREATE TABLE kody_zranitelnosti (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kod             CHAR(1)     NOT NULL UNIQUE COMMENT 'Zažité písmeno A-P, MUSÍ zůstat viditelné v UI',
    nazev           VARCHAR(80) NULL COMMENT 'České jméno pro UI. NULL = zatím neověřeno, viz popis.',
    skupina_kouzel_id TINYINT UNSIGNED NULL,
    popis           TEXT,
    FOREIGN KEY (skupina_kouzel_id) REFERENCES skupiny_kouzel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kody_zranitelnosti (kod, nazev, popis) VALUES
('A', 'Zápas',            'Boj beze zbraně.'),
('B', 'Obyčejná zbraň',   'Včetně přirozených zbraní jako drápy/kousnutí.'),
('C', 'Kouzelná zbraň',   'C+ = kouzelná zbraň + útočný démon.'),
('D', 'Stříbrná zbraň',   'D+ = stříbrná nebo ušlechtilý kov.'),
('E', 'Svěcená voda',     NULL),
('F', 'Vlčí mor',         NULL),
('G', 'Oheň / mráz',      'Ohnivá hlína, rachejtle, hořící olej i mráz spadají pod stejný kód.'),
('H', 'Jed / kyselina',   'Včetně alchymistických lektvarů. H+ = jen magické.'),
('O', 'Podrobování',      NULL),
('P', 'Mentální útok',    'P+ = nestvůra sama útočí mentálně.');

-- Kódy I,J,K,L,M,N (skupiny kouzel) NEJSOU zapsané — dva různé průzkumy pravidel
-- si u nich protiřečily (Roman I-IV vs. abecední I-N) a nechci hádat špatné
-- mapování do "finální" databáze. Řádky založené jako placeholder k doplnění
-- až po ověření proti konkrétnímu bestiářovému heslu (viz kontrolní seznam).
INSERT INTO kody_zranitelnosti (kod, nazev, popis) VALUES
('I', NULL, 'TBD — pravděpodobně skupina kouzel, ověřit proti bestiáři'),
('J', NULL, 'TBD — pravděpodobně skupina kouzel, ověřit proti bestiáři'),
('K', NULL, 'TBD — pravděpodobně skupina kouzel, ověřit proti bestiáři'),
('L', NULL, 'TBD — pravděpodobně skupina kouzel, ověřit proti bestiáři'),
('M', NULL, 'TBD — pravděpodobně skupina kouzel, ověřit proti bestiáři'),
('N', NULL, 'TBD — pravděpodobně skupina kouzel, ověřit proti bestiáři');

CREATE TABLE jazyky (
    id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev   VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presvedceni (
    id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev   VARCHAR(60) NOT NULL UNIQUE,
    popis   TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. EFEKTY A PASTI (reusable mechanika napříč obsahem)
-- =====================================================================

CREATE TABLE efekty (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev               VARCHAR(100) NOT NULL,
    typ                 ENUM('buff','debuff','dot','hot','modifikator','imunita','zranitelnost','odolnost') NOT NULL,
    cil                 VARCHAR(100) COMMENT 'životy / OČ / ÚČ / konkrétní vlastnost / akceschopnost...',
    hodnota_vzorec      VARCHAR(100) COMMENT 'např. +2, -1k6, ×2',
    mechanika_aplikace  ENUM('jednorazove','na_zacatku_kola','na_konci_kola','pri_zasahu') NOT NULL DEFAULT 'jednorazove',
    trvani              VARCHAR(100) COMMENT 'počet kol / do konce boje / permanentní...',
    podminka_ukonceni   VARCHAR(255),
    stackovatelne       BOOLEAN NOT NULL DEFAULT FALSE,
    max_stack           SMALLINT UNSIGNED NULL,
    ikona               VARCHAR(100) COMMENT 'název/cesta ikony pro UI',
    barva               VARCHAR(20)  COMMENT 'barva badge/severita pro UI',
    tooltip_text        TEXT         COMMENT 'text pro mouseover v boji'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pasti (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vlastnosti          VARCHAR(255) NOT NULL COMMENT 'zápis dle pravidel, např. "Odl" nebo "Sil+Odl" nebo "Roz.Char"',
    obtiznost           VARCHAR(255) NOT NULL COMMENT 'nebezpečnost / obtížnost, může být číslo i delší vzorec/popis',
    efekt_uspech_id     INT UNSIGNED NULL COMMENT 'efekt při úspěchu obránce (často NULL = nic)',
    efekt_neuspech_id   INT UNSIGNED NULL COMMENT 'efekt při neúspěchu obránce',
    pouziva_4stupnovou_skalu BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'TRUE = past rozlišuje fatální výsledky (vysledky_testu)',
    poznamka            TEXT,
    FOREIGN KEY (efekt_uspech_id) REFERENCES efekty(id) ON DELETE SET NULL,
    FOREIGN KEY (efekt_neuspech_id) REFERENCES efekty(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. POVOLÁNÍ, RASY
-- =====================================================================

CREATE TABLE zkusenostni_tabulky (
    id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev   VARCHAR(100) NOT NULL COMMENT 'homebrew varianty se liší povolání od povolání'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE zkusenostni_urovne (
    tabulka_id          SMALLINT UNSIGNED NOT NULL,
    uroven              TINYINT UNSIGNED NOT NULL,
    potrebne_zkusenosti INT UNSIGNED NOT NULL,
    cena_za_vycvik      INT UNSIGNED NULL,
    PRIMARY KEY (tabulka_id, uroven),
    FOREIGN KEY (tabulka_id) REFERENCES zkusenostni_tabulky(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE povolani (
    id                   SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev                VARCHAR(60) NOT NULL,
    typ                  ENUM('zakladni','vetev') NOT NULL DEFAULT 'zakladni',
    rodic_povolani_id    SMALLINT UNSIGNED NULL COMMENT 'např. Bojovník/Šermíř jsou větve Válečníka',
    odemyka_se_od_urovne TINYINT UNSIGNED NULL,
    pouziva_magenergii   BOOLEAN NOT NULL DEFAULT FALSE,
    primarni_vlastnost_id TINYINT UNSIGNED NULL,
    sum_zaklad           TINYINT UNSIGNED NULL COMMENT 'mentální souboj — základní síla mysli',
    som_zaklad           TINYINT UNSIGNED NULL COMMENT 'mentální souboj — základní obrana mysli',
    zsm_zaklad           TINYINT UNSIGNED NULL,
    zkusenostni_tabulka_id SMALLINT UNSIGNED NULL,
    popis                TEXT,
    FOREIGN KEY (rodic_povolani_id) REFERENCES povolani(id),
    FOREIGN KEY (primarni_vlastnost_id) REFERENCES vlastnosti(id),
    FOREIGN KEY (zkusenostni_tabulka_id) REFERENCES zkusenostni_tabulky(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE povolani_jazyky (
    povolani_id SMALLINT UNSIGNED NOT NULL,
    jazyk_id    SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (povolani_id, jazyk_id),
    FOREIGN KEY (povolani_id) REFERENCES povolani(id) ON DELETE CASCADE,
    FOREIGN KEY (jazyk_id) REFERENCES jazyky(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rasy (
    id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev         VARCHAR(60) NOT NULL,
    rodic_rasa_id SMALLINT UNSIGNED NULL COMMENT 'rasové varianty/klany (např. Kroll — domácí rozšíření)',
    popis         TEXT,
    FOREIGN KEY (rodic_rasa_id) REFERENCES rasy(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rasa_bonusy_vlastnosti (
    rasa_id      SMALLINT UNSIGNED NOT NULL,
    vlastnost_id TINYINT UNSIGNED NOT NULL,
    modifikator  VARCHAR(20) NOT NULL,
    PRIMARY KEY (rasa_id, vlastnost_id),
    FOREIGN KEY (rasa_id) REFERENCES rasy(id) ON DELETE CASCADE,
    FOREIGN KEY (vlastnost_id) REFERENCES vlastnosti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rasa_povolani (
    rasa_id     SMALLINT UNSIGNED NOT NULL,
    povolani_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (rasa_id, povolani_id),
    FOREIGN KEY (rasa_id) REFERENCES rasy(id) ON DELETE CASCADE,
    FOREIGN KEY (povolani_id) REFERENCES povolani(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rasa_jazyky (
    rasa_id  SMALLINT UNSIGNED NOT NULL,
    jazyk_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (rasa_id, jazyk_id),
    FOREIGN KEY (rasa_id) REFERENCES rasy(id) ON DELETE CASCADE,
    FOREIGN KEY (jazyk_id) REFERENCES jazyky(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. SCHOPNOSTI / DOVEDNOSTI / FINTY
-- =====================================================================

CREATE TABLE zvlastni_schopnosti (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev           VARCHAR(100) NOT NULL,
    druh            ENUM('schopnost','dovednost') NOT NULL DEFAULT 'schopnost',
    popis           TEXT,
    uroven_od       TINYINT UNSIGNED NULL,
    vyzaduje_id     INT UNSIGNED NULL COMMENT 'self-reference — prerekvizita (navazující stupně)',
    mechanika       VARCHAR(255) COMMENT 'strukturovaný zápis kostky/bonusu/cíle',
    past_id         INT UNSIGNED NULL,
    vysledek_skala  BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (vyzaduje_id) REFERENCES zvlastni_schopnosti(id),
    FOREIGN KEY (past_id) REFERENCES pasti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schopnost_povolani (
    schopnost_id INT UNSIGNED NOT NULL,
    povolani_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (schopnost_id, povolani_id),
    FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti(id) ON DELETE CASCADE,
    FOREIGN KEY (povolani_id) REFERENCES povolani(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schopnost_efekty (
    schopnost_id INT UNSIGNED NOT NULL,
    efekt_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (schopnost_id, efekt_id),
    FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti(id) ON DELETE CASCADE,
    FOREIGN KEY (efekt_id) REFERENCES efekty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rasa_schopnosti (
    rasa_id      SMALLINT UNSIGNED NOT NULL,
    schopnost_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (rasa_id, schopnost_id),
    FOREIGN KEY (rasa_id) REFERENCES rasy(id) ON DELETE CASCADE,
    FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finty (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev                 VARCHAR(100) NOT NULL,
    typ                   ENUM('utocna','obranna','kombinovana') NOT NULL,
    bonus_iniciativa      SMALLINT NULL,
    pocet_akci            TINYINT UNSIGNED NULL,
    pozadovana_zbran      VARCHAR(100),
    pozadovany_protivnik  VARCHAR(100),
    poznamky              TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finta_akce (
    finta_id        INT UNSIGNED NOT NULL,
    poradi          TINYINT UNSIGNED NOT NULL,
    typ             ENUM('U','O') NOT NULL COMMENT 'Ú = útočná, O = obranná akce v sekvenci',
    oc_modifikator  SMALLINT NULL,
    uc_modifikator  SMALLINT NULL,
    PRIMARY KEY (finta_id, poradi),
    FOREIGN KEY (finta_id) REFERENCES finty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finta_prerekvizity (
    finta_id          INT UNSIGNED NOT NULL,
    vyzaduje_finta_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (finta_id, vyzaduje_finta_id),
    FOREIGN KEY (finta_id) REFERENCES finty(id) ON DELETE CASCADE,
    FOREIGN KEY (vyzaduje_finta_id) REFERENCES finty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. KOUZLA
-- =====================================================================

CREATE TABLE seznamy_kouzel (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev       VARCHAR(100) NOT NULL COMMENT 'např. "Kouzla mága PPP", "Kouzla hraničáře"',
    povolani_id SMALLINT UNSIGNED NULL,
    popis       TEXT,
    FOREIGN KEY (povolani_id) REFERENCES povolani(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kouzla (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev             VARCHAR(100) NOT NULL,
    seznam_kouzel_id  SMALLINT UNSIGNED NULL,
    uroven_kouzla     TINYINT UNSIGNED NULL,
    cena_magenergie   VARCHAR(30),
    dosah             VARCHAR(50),
    doba_seslani      VARCHAR(50),
    doba_trvani       VARCHAR(50),
    typ_unavy         ENUM('vycerpavajici','nevycerpavajici','udrzovaci','zaostreni_vule') NULL,
    past_id           INT UNSIGNED NULL,
    popis             TEXT,
    FOREIGN KEY (seznam_kouzel_id) REFERENCES seznamy_kouzel(id),
    FOREIGN KEY (past_id) REFERENCES pasti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kouzlo_obor_magie (
    kouzlo_id INT UNSIGNED NOT NULL,
    obor_id   TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (kouzlo_id, obor_id),
    FOREIGN KEY (kouzlo_id) REFERENCES kouzla(id) ON DELETE CASCADE,
    FOREIGN KEY (obor_id) REFERENCES obory_magie(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'M:N záměrně — některá kouzla mají kombinovaný obor, např. "Oko" = ČP+PO';

CREATE TABLE kouzlo_efekty (
    kouzlo_id INT UNSIGNED NOT NULL,
    efekt_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (kouzlo_id, efekt_id),
    FOREIGN KEY (kouzlo_id) REFERENCES kouzla(id) ON DELETE CASCADE,
    FOREIGN KEY (efekt_id) REFERENCES efekty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. PŘEDMĚTY / LEKTVARY
-- =====================================================================

CREATE TABLE predmety (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev                     VARCHAR(100) NOT NULL,
    typ                       ENUM('zbran','zbroj','surovina','artefakt') NOT NULL,
    kategorie_zbrane          SET('secna','bodna','tupa') NULL,
    typ_pro_vyrazeni_dveri    ENUM('ostre_bodne','ostre_drtive','tupe_drtive') NULL
        COMMENT 'samostatné pole — neshoduje se 1:1 s kategorií výše (sekera je "ostrá drtivá")',
    uc                        SMALLINT NULL COMMENT 'ÚČ — u zbraní/improvizovaných zbraní typu nástroj, kniha, hůl',
    utocnost                  SMALLINT NULL,
    oc                        SMALLINT NULL COMMENT 'OČ modifikátor',
    vaha                      DECIMAL(8,2) NULL,
    cena                      DECIMAL(10,2) NULL,
    popis                     TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE predmet_efekty (
    predmet_id INT UNSIGNED NOT NULL,
    efekt_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (predmet_id, efekt_id),
    FOREIGN KEY (predmet_id) REFERENCES predmety(id) ON DELETE CASCADE,
    FOREIGN KEY (efekt_id) REFERENCES efekty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lektvary (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev              VARCHAR(100) NOT NULL,
    vyrobce_povolani_id SMALLINT UNSIGNED NULL,
    past_id            INT UNSIGNED NULL,
    doba_pripravy      VARCHAR(50),
    cena               DECIMAL(10,2),
    popis              TEXT,
    FOREIGN KEY (vyrobce_povolani_id) REFERENCES povolani(id),
    FOREIGN KEY (past_id) REFERENCES pasti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lektvar_efekty (
    lektvar_id INT UNSIGNED NOT NULL,
    efekt_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (lektvar_id, efekt_id),
    FOREIGN KEY (lektvar_id) REFERENCES lektvary(id) ON DELETE CASCADE,
    FOREIGN KEY (efekt_id) REFERENCES efekty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lektvar_suroviny (
    lektvar_id INT UNSIGNED NOT NULL,
    predmet_id INT UNSIGNED NOT NULL,
    mnozstvi   VARCHAR(30) NULL,
    PRIMARY KEY (lektvar_id, predmet_id),
    FOREIGN KEY (lektvar_id) REFERENCES lektvary(id) ON DELETE CASCADE,
    FOREIGN KEY (predmet_id) REFERENCES predmety(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. NESTVŮRY (BESTIÁŘ)
-- =====================================================================

CREATE TABLE nestvury (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev                 VARCHAR(100) NOT NULL,
    zivotaschopnost       VARCHAR(255),
    uc                    VARCHAR(255),
    oc                    VARCHAR(255),
    odolnost              VARCHAR(255) COMMENT 'magické nestvůry: počítej jako 21 (nelze unavit/otrávit/omráčit)',
    velikost              VARCHAR(150) COMMENT 'kód z `velikosti`, ale ukládáno jako text kvůli rozsahům typu "B–C" i delším slovním popisům',
    bojovnost             VARCHAR(255),
    pohyblivost           VARCHAR(255),
    vytrvalost            VARCHAR(255),
    manevrovaci_schopnost VARCHAR(255),
    inteligence           VARCHAR(255),
    charisma              VARCHAR(255),
    zsm                   VARCHAR(255),
    sum                   VARCHAR(255) COMMENT 'mentální souboj, pokud relevantní',
    som                   VARCHAR(255),
    presvedceni_id        TINYINT UNSIGNED NULL,
    poklady               VARCHAR(100),
    zkusenost             INT UNSIGNED NULL,
    ochoceni              VARCHAR(100),
    prostredi             VARCHAR(255),
    popis                 TEXT,
    FOREIGN KEY (presvedceni_id) REFERENCES presvedceni(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nestvura_zranitelnosti (
    nestvura_id         INT UNSIGNED NOT NULL,
    kod_zranitelnosti_id TINYINT UNSIGNED NOT NULL,
    modifikator         VARCHAR(10) NULL COMMENT 'např. 1/2, 1/4, 2 — NULL = běžná zranitelnost bez násobiče',
    PRIMARY KEY (nestvura_id, kod_zranitelnosti_id),
    FOREIGN KEY (nestvura_id) REFERENCES nestvury(id) ON DELETE CASCADE,
    FOREIGN KEY (kod_zranitelnosti_id) REFERENCES kody_zranitelnosti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Tohle je zdroj pro mouseover tooltip zranitelnosti v boji v UI.';

CREATE TABLE nestvura_schopnosti (
    nestvura_id  INT UNSIGNED NOT NULL,
    schopnost_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (nestvura_id, schopnost_id),
    FOREIGN KEY (nestvura_id) REFERENCES nestvury(id) ON DELETE CASCADE,
    FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. DEMO DATA — jen pro ověření, že schéma funguje. Smazat/nahradit
--    ostrým obsahem po doplnění dat z kontrolního seznamu.
-- =====================================================================

INSERT INTO povolani (nazev, typ, pouziva_magenergii, popis) VALUES
('Kouzelník', 'zakladni', TRUE, 'DEMO řádek k ověření schématu.');

INSERT INTO seznamy_kouzel (nazev, povolani_id, popis)
SELECT 'Kouzla mága PPP', id, 'DEMO — seznam k naplnění' FROM povolani WHERE nazev='Kouzelník';

INSERT INTO kouzla (nazev, seznam_kouzel_id, cena_magenergie, dosah, doba_seslani, doba_trvani, popis)
SELECT 'Bábelská rybka', id, '5 magů', '0', '1 kolo', '3 směny',
       'Kouzelník rozumí jazyku libovolné myslící bytosti (int > 1). DEMO řádek, ověřeno h330.'
FROM seznamy_kouzel WHERE nazev='Kouzla mága PPP';

INSERT INTO kouzlo_obor_magie (kouzlo_id, obor_id)
SELECT k.id, o.id FROM kouzla k, obory_magie o WHERE k.nazev='Bábelská rybka' AND o.kod='PO';

INSERT INTO predmety (nazev, typ, uc, utocnost, oc, popis) VALUES
('Khakkhara', 'zbran', 7, 1, 2, 'DEMO — mnišská hůl, formát ÚČ/útočnost/OČ potvrzený uživatelem.');

SET FOREIGN_KEY_CHECKS = 1;
