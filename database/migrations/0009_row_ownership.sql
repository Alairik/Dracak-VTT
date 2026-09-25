-- Row-level vlastnictví záznamů pro roli hráč (redesign editoru — viz
-- docs/zadani-redesign-ui.md, rozhodnutí #5 v design handoffu). NULL u
-- existujících řádků = bez vlastníka, hráč je bez table-level oprávnění
-- k úpravě neuvidí editovatelné, ale pj/admin je vidí a edituje jako dřív.
ALTER TABLE kouzla ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE zvlastni_schopnosti ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE predmety ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE lektvary ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE finty ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE povolani ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE rasy ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE efekty ADD COLUMN created_by INT UNSIGNED NULL AFTER id;
ALTER TABLE pasti ADD COLUMN created_by INT UNSIGNED NULL AFTER id;

ALTER TABLE kouzla ADD CONSTRAINT fk_kouzla_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE zvlastni_schopnosti ADD CONSTRAINT fk_schopnosti_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE predmety ADD CONSTRAINT fk_predmety_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE lektvary ADD CONSTRAINT fk_lektvary_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE finty ADD CONSTRAINT fk_finty_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE povolani ADD CONSTRAINT fk_povolani_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE rasy ADD CONSTRAINT fk_rasy_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE efekty ADD CONSTRAINT fk_efekty_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
ALTER TABLE pasti ADD CONSTRAINT fk_pasti_created_by FOREIGN KEY (created_by) REFERENCES ucty(id);
