-- Dovednosti/schopnosti dostávají klíčovou vlastnost (Síla/Obratnost/
-- Odolnost/...), podle které jde v editoru filtrovat. FK na `vlastnosti`
-- (stejná tabulka, kterou už používá povolani.primarni_vlastnost_id).
ALTER TABLE zvlastni_schopnosti ADD COLUMN vlastnost_id TINYINT UNSIGNED NULL AFTER druh;
ALTER TABLE zvlastni_schopnosti ADD CONSTRAINT fk_schopnosti_vlastnost FOREIGN KEY (vlastnost_id) REFERENCES vlastnosti(id);
