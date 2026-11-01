-- Poznámky světa — rozšíření (plán z docs/stav-projektu.md, rozhodnutí uživatele 2026-11-01):
--   stitek      — pevná sada quest / npc / loot / lore / misto (NULL = bez štítku; sada je v includes/vtt_poznamky.php, nový štítek DDL nepotřebuje)
--   postava_id  — vazba poznámky na postavu nebo NPC ve světě (smaže-li se postava, vazba se jen zruší)
--   upraveno    — kdy autor poznámku naposledy upravil (NULL = nikdy; editace přepisuje text na místě, bez historie)
--   svet_poznamka_precteno — kdo už poznámku viděl (odznak nepřečtených u světa; po úpravě nebo novém sdílení se poznámka zase jeví jako nová)
--
-- Samé DDL (ALTER ADD, CREATE TABLE IF NOT EXISTS) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Bez těchto změn zůstávají poznámky ve stávající podobě (jen text, místo a sdílení).

ALTER TABLE svet_poznamky ADD COLUMN stitek VARCHAR(12) NULL COMMENT 'quest / npc / loot / lore / misto — NULL = bez štítku';
ALTER TABLE svet_poznamky ADD COLUMN postava_id INT UNSIGNED NULL COMMENT 'vazba na postavu nebo NPC ve světě';
ALTER TABLE svet_poznamky ADD COLUMN upraveno TIMESTAMP NULL DEFAULT NULL COMMENT 'kdy autor poznámku naposledy upravil (NULL = nikdy)';
ALTER TABLE svet_poznamky ADD KEY postava_id (postava_id);
ALTER TABLE svet_poznamky ADD CONSTRAINT fk_svet_poznamky_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS svet_poznamka_precteno (
    poznamka_id INT UNSIGNED NOT NULL,
    ucet_id INT UNSIGNED NOT NULL,
    precteno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (poznamka_id, ucet_id),
    KEY ucet_id (ucet_id),
    CONSTRAINT fk_precteno_poznamka FOREIGN KEY (poznamka_id) REFERENCES svet_poznamky (id) ON DELETE CASCADE,
    CONSTRAINT fk_precteno_ucet FOREIGN KEY (ucet_id) REFERENCES ucty (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
