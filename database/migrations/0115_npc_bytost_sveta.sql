-- 0115 — NPC je bytost světa, ne mapy (zásoba tokenů)
--
-- Proč: dnes nestvůra/NPC vznikne až položením tokenu na mapu (nestvura_instance.mapa_id je povinné), takže
-- nejde připravit NPC do zásoby dopředu ani mít seznam všech NPC ve světě. Deník nestvůry je navíc na DRUH
-- (nestvura_staty_sveta má klíč svet_id + nestvura_id), takže úprava čísel Goblina platí všem goblinům ve světě
-- a jedinečné NPC s vlastními čísly nejde udělat. Zadání uživatele 2026-11-09: dvě úrovně NPC (jedinečné s vlastními
-- čísly × bezejmenná masa sdílející čísla druhu) a správa tokenů s výrobou do zásoby a pokládáním tažením na mapu.
--
-- Co se mění:
--  * nestvura_instance.svet_id — ke kterému světu NPC patří (dřív se zjišťoval přes mapu);
--  * nestvura_instance.mapa_id smí být prázdné = NPC existuje ve světě, ale nestojí na žádné mapě (zásoba);
--  * nestvura_instance.jedinecny — 1 = jedinečné NPC s vlastními čísly, 0 = běžný kus, čísla bere z druhu;
--  * nestvura_instance.data — vlastní čísla pro boj jedinečného NPC (stejný JSON jako nestvura_staty_sveta.data);
--  * nestvura_instance.lokace_mapa_id — místo, kam NPC patří (hostinský v tomhle městě): ve správě tokenů se ukáže
--    u téhle lokace. Prázdné = bez místa, zásoba pro mapy, které ještě nevznikly (zadání uživatele 2026-11-09).
--    Pozor: je to něco jiného než mapa_id (kde token PRÁVĚ stojí) — NPC navázané na město tam může, ale nemusí stát.
--
-- POZOR: ALTER pod web účtem (DML-only) neprojde — jde o ruční SQL (blok J v docs/sql-k-rucnimu-spusteni.sql).
-- Stránka Změny databáze tyhle příkazy po ručním provedení pozná (ADD COLUMN i MODIFY ... NULL) a přeskočí je.

ALTER TABLE nestvura_instance ADD COLUMN svet_id INT UNSIGNED NULL COMMENT 'svět, kterému NPC patří (NPC žije ve světě, mapa je jen místo, kde právě stojí)';
ALTER TABLE nestvura_instance MODIFY COLUMN mapa_id INT UNSIGNED NULL COMMENT 'mapa, na které NPC stojí; prázdné = v zásobě, nikde nepoložené';
ALTER TABLE nestvura_instance ADD COLUMN jedinecny TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = jedinečné NPC s vlastními čísly, 0 = běžný kus se čísly druhu';
ALTER TABLE nestvura_instance ADD COLUMN data MEDIUMTEXT NULL COMMENT 'vlastní čísla pro boj jedinečného NPC (JSON jako nestvura_staty_sveta.data)';
ALTER TABLE nestvura_instance ADD COLUMN lokace_mapa_id INT UNSIGNED NULL COMMENT 'místo, kam NPC patří (hostinský v tomhle městě); prázdné = bez místa, do zásoby pro budoucí mapy';
ALTER TABLE nestvura_instance ADD INDEX idx_ni_svet (svet_id, nestvura_id);
ALTER TABLE nestvura_instance ADD INDEX idx_ni_lokace (lokace_mapa_id);

-- Dosavadní instance patří do světa své mapy a na to místo, kde stojí.
UPDATE nestvura_instance ni JOIN mapy m ON m.id = ni.mapa_id SET ni.svet_id = m.svet_id WHERE ni.svet_id IS NULL;
UPDATE nestvura_instance SET lokace_mapa_id = mapa_id WHERE lokace_mapa_id IS NULL AND mapa_id IS NOT NULL;
