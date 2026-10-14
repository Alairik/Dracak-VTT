-- Kontejnery a nosiče nákladu — Fáze 1b plánu (docs/stav-projektu.md).
-- Model "vedle", nic existujícího se nemění ani neruší (rozhodnuto 2026-10-14):
--
--   postava_kontejnery  jeden řádek = jeden FYZICKÝ vak/truhla/soudek postavy
--                       (dva stejné vaky = dva řádky, každý s vlastním
--                       obsahem). predmet_id = typ z katalogu (kapacita a
--                       vlastní váha jsou na předmětu: kapacita_mn, vaha).
--                       nosic: kdo to nese — 'postava' (výchozí), nebo
--                       'zvire' (zvíře, na kterém postava jede, postavy.doprava_*;
--                       jeho náklad se pak počítá zvířeti, ne postavě).
--   kontejner_obsah    co je v kontejneru (předmět + počet kusů). Předmět v
--                       kontejneru už není ve volném inventáři (postava_predmety).
--
-- Váha postavy = volné předměty + vlastní váha kontejnerů + jejich obsah +
-- peníze; kontejnery na zvířeti nesou zvíře (spolu s jezdcem, h1681 b11168).
-- Přeplnění kapacity se jen hlásí, nic nebrání (rozhodnutí uživatele).
--
-- CREATE TABLE = DDL, web účet ho neprovede — ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql).

CREATE TABLE IF NOT EXISTS postava_kontejnery (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    postava_id  INT UNSIGNED NOT NULL,
    predmet_id  INT UNSIGNED NOT NULL,
    nazev       VARCHAR(80) NULL COMMENT 'vlastní popisek, např. "vak s jídlem"',
    nosic       ENUM('postava','zvire') NOT NULL DEFAULT 'postava',
    vytvoreno   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_postava_kontejnery_postava (postava_id),
    KEY idx_postava_kontejnery_predmet (predmet_id),
    CONSTRAINT fk_postava_kontejnery_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE CASCADE,
    CONSTRAINT fk_postava_kontejnery_predmet FOREIGN KEY (predmet_id) REFERENCES predmety (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kontejner_obsah (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontejner_id INT UNSIGNED NOT NULL,
    predmet_id   INT UNSIGNED NOT NULL,
    mnozstvi     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_kontejner_obsah (kontejner_id, predmet_id),
    KEY idx_kontejner_obsah_predmet (predmet_id),
    CONSTRAINT fk_kontejner_obsah_kontejner FOREIGN KEY (kontejner_id) REFERENCES postava_kontejnery (id) ON DELETE CASCADE,
    CONSTRAINT fk_kontejner_obsah_predmet FOREIGN KEY (predmet_id) REFERENCES predmety (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
