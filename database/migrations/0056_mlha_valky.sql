-- Mlha války (viz docs/vtt-datovy-model-navrh-v1.md, sekce "Mlha války")
-- — per hráč, per mapa odhalená oblast, přepisuje se (UPDATE/INSERT),
-- není to log jako svet_udalosti.
--
-- Formát bitmapy (otevřená otázka ponechaná v návrhovém dokumentu,
-- teď rozhodnuto při implementaci): 1 byte na buňku (0x00 nevidět/
-- 0x01 odhaleno) v rastru sirka_bunky×sirka_bunky px přes celou mapu,
-- řádkově (idx = radek*sloupcu + sloupec). 1 byte/buňka místo bit-
-- packingu kvůli jednoduchosti kódu (PHP string offset = přímý byte
-- index) — i velká mapa (4000×4000 px, buňka 32px) dá jen ~15 000
-- bitů jako sloupcu×radku bajtů, řádově desítky kB, daleko pod limitem
-- MEDIUMBLOB (16 MB).
--
-- sloupcu/radku se ukládají spolu s bitmapou (ne jen dopočítávají za
-- běhu), protože bitmapa bez nich nejde rozparsovat zpátky na 2D
-- rastr, a mapa.obrazek_cesta/rozměry se časem může přenahrát jiným
-- obrázkem jiné velikosti — PHP vrstva (includes/vtt_mlha.php) při
-- neshodě rozměrů mlhu pro tenhle účet prostě založí znovu od nuly,
-- nezkouší starý rastr migrovat na nový rozměr.
--
-- Samé CREATE TABLE = čistá DDL, web účet (DML-only na Wedosu) ji
-- nikdy neprovede, musí se spustit ručně přes phpMyAdmin (admin účet)
-- a zapsat do migrace_log — viz CLAUDE.md.

CREATE TABLE IF NOT EXISTS mlha_valky (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  ucet_id INT UNSIGNED NOT NULL,
  bitmapa MEDIUMBLOB NOT NULL,
  sirka_bunky SMALLINT UNSIGNED NOT NULL,
  sloupcu SMALLINT UNSIGNED NOT NULL,
  radku SMALLINT UNSIGNED NOT NULL,
  aktualizovano TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY mapa_ucet (mapa_id, ucet_id),
  CONSTRAINT fk_mlha_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_mlha_ucet FOREIGN KEY (ucet_id) REFERENCES ucty (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
