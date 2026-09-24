-- Účty, role a PJ poznámky — nad databází pravidel z drd-db-full-v1.sql.
-- Spustit AŽ PO importu drd-db-schema-v1.sql (nebo full-v1.sql) v phpMyAdminu.

CREATE TABLE ucty (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL UNIQUE,
    heslo_hash  VARCHAR(255) NOT NULL,
    jmeno       VARCHAR(100) NOT NULL,
    role        ENUM('admin', 'pj', 'hrac') NOT NULL DEFAULT 'hrac',
    vytvoreno   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Které tabulky (podle klíčů v includes/entities.php) smí hráč sám
-- zakládat/editovat. PJ a admin mají vždy přístup ke všemu, tahle
-- tabulka se pro ně nepoužívá.
CREATE TABLE ucet_opravneni (
    ucet_id  INT UNSIGNED NOT NULL,
    tabulka  VARCHAR(64) NOT NULL,
    PRIMARY KEY (ucet_id, tabulka),
    FOREIGN KEY (ucet_id) REFERENCES ucty(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PJ pravidla / poznámky — nová entita, ve schématu pravidel dosud
-- neexistovala. Vždy skrytá hráčům (řeší se v PHP, ne v DB).
CREATE TABLE pj_poznamky (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev      VARCHAR(150) NOT NULL,
    obsah      TEXT,
    vytvoreno  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
