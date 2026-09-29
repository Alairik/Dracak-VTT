-- Základní atributy postavy podle pravidel (content/pravidla-hrac.html,
-- h104: Síla, Obratnost, Odolnost, Inteligence, Charisma) — VTT postavy
-- je dosud vůbec neměly uložené, jen rasa/povolání/úroveň/HP. Bez nich
-- nejde spočítat iniciativu (tiebreak na Obratnost), ÚČ/OČ ani nic
-- dalšího, co se na atribut odkazuje.
--
-- Hodnota je "stupeň" atributu (1-23+, viz příklad v pravidlech "Síla 16,
-- Obratnost 8..."), ne přímo bonus — bonus/postih se dopočítá přes
-- existující `opravy_za_atribut` (migrace 0007), stejně jako všude jinde
-- v pravidlové DB. NULL = nevyplněno (starší postavy založené předtím).
--
-- ALTER ADD COLUMN — web účet (DML-only na Wedosu) ho neprovede, musí se
-- spustit ručně přes phpMyAdmin (admin účet) a zapsat do migrace_log,
-- viz CLAUDE.md.

ALTER TABLE postavy
    ADD COLUMN sila TINYINT UNSIGNED DEFAULT NULL AFTER uroven,
    ADD COLUMN obratnost TINYINT UNSIGNED DEFAULT NULL AFTER sila,
    ADD COLUMN odolnost TINYINT UNSIGNED DEFAULT NULL AFTER obratnost,
    ADD COLUMN inteligence TINYINT UNSIGNED DEFAULT NULL AFTER odolnost,
    ADD COLUMN charisma TINYINT UNSIGNED DEFAULT NULL AFTER inteligence;
