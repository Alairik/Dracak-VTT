-- Piny na světové mapě (typ_mapy='svet') — každý pin je jedno místo
-- (město, vesnice, tajné místo...), volitelně 1:1 navázané na svou
-- vlastní zónovou mapu (cilova_mapa_id), kam se hraje konkrétní scéna
-- na tom místě. "Seznam NPC, co se tam můžou nacházet" se NEUKLÁDÁ
-- zvlášť — jsou to prostě tokeny (typ_entity='postava', vlastník PJ)
-- na té cílové mapě, viz includes/vtt_mapa_body.php
-- dracak_vtt_mapa_bod_npc() — stejná reprezentace NPC jako všude jinde
-- v enginu, žádná duplicitní datová struktura.
CREATE TABLE IF NOT EXISTS mapa_body (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL COMMENT 'světová mapa, na které je pin umístěný',
  cilova_mapa_id INT UNSIGNED DEFAULT NULL COMMENT 'zónová mapa tohohle místa (nepovinná — pin může být jen poznámka na mapě bez vlastní scény)',
  nazev VARCHAR(150) NOT NULL,
  typ ENUM('mesto','vesnice','tajne_misto','jine') NOT NULL DEFAULT 'jine',
  x INT NOT NULL,
  y INT NOT NULL,
  popis TEXT DEFAULT NULL,
  viditelny_hracum TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'nezjevená/tajná místa (typ=tajne_misto zejména) PJ nastaví na 0, dokud je družina neobjeví',
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY mapa_id (mapa_id),
  KEY cilova_mapa_id (cilova_mapa_id),
  CONSTRAINT fk_mapa_body_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_mapa_body_cil FOREIGN KEY (cilova_mapa_id) REFERENCES mapy (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "aktivni_mapa_zmena" v svet_udalosti.typ ENUM už existuje od migrace
-- 0037 (zavedeno dřív, nikdy použito) — teď ho konečně začíná
-- vyplňovat hra/api/aktivni_mapa_nastavit.php, žádná změna ENUM potřeba.
