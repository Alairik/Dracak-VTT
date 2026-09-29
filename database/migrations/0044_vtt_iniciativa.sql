-- VTT — rozšířený (hex) soubojový systém: hod na iniciativu, počet akcí a
-- pořadí tahů v aktuálním kole na mapě (content/pravidla-hrac.html,
-- h1619-h1625, zejména h1622 "Hod na iniciativu" a h1623 "Akce", str. 77-78).
-- Netýká se ZÁKLADNÍHO soubojového systému (h1619) — ten iniciativu počítá
-- jinak (jen "kdo hodí víc na 1k6, je první") a tyhle dvě tabulky vůbec
-- nepoužívá.
--
-- kolo_stav — jeden řádek na mapu, drží číslo aktuálního kola a kdo je
-- právě na tahu (NULL = nikdo, buď se ještě nehodilo, nebo je kolo čerstvě
-- začaté a čeká se na první dalsi_tah.php).
--
-- kolo_iniciativa — jeden řádek na (mapa, token) = aktuální kolo. Podle
-- h1622 ("Na začátku kola si každý účastník souboje hodí 1k6 na iniciativu")
-- se hází NA ZAČÁTKU KAŽDÉHO KOLA, ne jednou za celý souboj — proto se
-- tahle tabulka při konci kola maže (viz hra/api/dalsi_tah.php) a
-- UNIQUE KEY (mapa_id, token_id) drží vždy jen aktuální kolo, ne historii.
--
-- hod = surový 1k6, modifikator = ruční číslo (Tabulka bonusů a postihů k
-- iniciativě, str. 78, není nikde přepsaná — jen nečitelný obrázek v
-- zdroji, viz docs/kontrolni-seznam-neuplnych-mist.md — dokud se nenajde
-- lepší sken, modifikátor zadává hráč/PJ ručně, viz
-- hra/api/iniciativa_hod.php), vysledek = hod + modifikator. akce_celkem
-- se dopočítá z vysledek přes TABULKU INICIATIVY A AKCÍ (h1623), viz
-- includes/vtt_iniciativa.php — akce_zbyvajici se pak v průběhu kola
-- odečítá (hra/api/dalsi_tah.php).
--
-- Samé CREATE TABLE = čistá DDL, web účet (DML-only na Wedosu) ji nikdy
-- neprovede a migrate.php na ní shodí deploy krok (očekávané, ne chyba).
-- MUSÍ se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log, viz CLAUDE.md.

CREATE TABLE IF NOT EXISTS kolo_stav (
  mapa_id INT UNSIGNED NOT NULL,
  cislo_kola INT UNSIGNED NOT NULL DEFAULT 1,
  aktivni_token_id INT UNSIGNED NULL,
  PRIMARY KEY (mapa_id),
  CONSTRAINT fk_kolo_stav_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_kolo_stav_token FOREIGN KEY (aktivni_token_id) REFERENCES tokeny (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kolo_iniciativa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  token_id INT UNSIGNED NOT NULL,
  hod TINYINT NOT NULL,
  modifikator TINYINT NOT NULL DEFAULT 0,
  vysledek TINYINT NOT NULL,
  akce_celkem TINYINT UNSIGNED NOT NULL,
  akce_zbyvajici TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY mapa_token (mapa_id, token_id),
  CONSTRAINT fk_kolo_iniciativa_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_kolo_iniciativa_token FOREIGN KEY (token_id) REFERENCES tokeny (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rozšíření svet_udalosti.typ o tři nové hodnoty pro tuhle vrstvu —
-- 'iniciativa_hozena' (jednotlivý hod), 'kolo_nove' (konec kola, přechod
-- na další), 'tah_zmena' (posun aktivniho_token_id). Vychází z výčtu, jak
-- vypadal po database/migrations/0039_vtt_ping.sql (12 hodnot končících
-- 'hp_zmena','ping') — přesně zadáno v úkolu, protože 0041 (souběžná
-- práce na inventáři/lootu) v tomhle izolovaném worktree není vidět;
-- integrace obou ALTER na produkci se řeší mimo tenhle soubor. Jde o
-- rozšíření množiny povolených hodnot, ne o destruktivní změnu.

ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
    'token_presun','token_pridan','token_smazan',
    'kostka_hod','efekt_aplikovan','efekt_konci',
    'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
    'hp_zmena','ping',
    'iniciativa_hozena','kolo_nove','tah_zmena'
) NOT NULL;
