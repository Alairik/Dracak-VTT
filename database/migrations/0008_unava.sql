-- Mechanika Únavy — tři číselníky. Samotná "Hranice únavy" (10 + oprava
-- za ODO) a "aktuální body únavy" jsou hodnoty KONKRÉTNÍ POSTAVY za hry,
-- ne pravidlo — ty podle db-model-navrh-v1.md patří do budoucí vrstvy
-- "Postava/instance postavy", ne sem. Sem patří jen samotná pravidla:
-- kolik co stojí, jaké jsou stupně a jak se únava odstraňuje.

CREATE TABLE IF NOT EXISTS narocnost_akci_unava (
    id             TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cinnost        VARCHAR(100) NOT NULL COMMENT 'např. "Boj (tři kola)", "Kouzlení (pokles many o 1/4)"',
    body_unavy     TINYINT UNSIGNED NOT NULL,
    ma_hvezdicku   BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'akce označené * — při zbroji bez dostatečné SIL +3 body navíc, při překročení nosnosti +1 bod za násobek'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO narocnost_akci_unava (cinnost, body_unavy, ma_hvezdicku) VALUES
('Boj (tři kola)', 2, TRUE),
('Chůze (1 hodina)', 1, TRUE),
('Jízda (1 hodina)', 1, TRUE),
('Plavání (1 směna)', 2, TRUE),
('Šplh (1 směna)', 1, TRUE),
('Kouzlení (pokles many o 1/4)', 2, FALSE),
('Praktické činnosti (1 hodina)', 2, FALSE);

CREATE TABLE IF NOT EXISTS stupne_unavy (
    id                TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev             VARCHAR(30) NOT NULL COMMENT 'Svěží / Unavený / Zcela vyčerpaný',
    prah_nasobek      DECIMAL(3,1) NOT NULL COMMENT 'násobek Hranice únavy, od kterého stupeň platí (1.0 = přesahuje hranici, 2.0 = přesahuje 2× hranici)',
    postih            SMALLINT NOT NULL COMMENT 'postih k hodům na úspěch akce/pasti/útok/obranu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Hranice únavy = 10 + oprava za ODO postavy (počítá se u konkrétní postavy, není tu).';

INSERT INTO stupne_unavy (nazev, prah_nasobek, postih) VALUES
('Svěží', 0.0, 0),
('Unavený', 1.0, -3),
('Zcela vyčerpaný', 2.0, -6);

CREATE TABLE IF NOT EXISTS odstraneni_unavy (
    id                  TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zpusob              VARCHAR(60) NOT NULL COMMENT 'Odpočinek / Spánek / Kouzla, lektvary',
    efekt               VARCHAR(100) NOT NULL,
    alternativni_efekt  VARCHAR(150) NULL COMMENT 'pro hraní bez počítání bodů únavy, jen podle stupňů'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO odstraneni_unavy (zpusob, efekt, alternativni_efekt) VALUES
('Odpočinek', '-1 bod únavy', 'Snížení únavy o polovinu stupně'),
('Spánek', '-10 bodů únavy', 'Snížení únavy o jeden celý stupeň'),
('Kouzla, lektvary', 'dle popisu', 'Dle uvážení PJe (obvykle stupeň)');
