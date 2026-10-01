-- Tvorba postavy — rozsahy vlastností pro ruční/nahozený hod (h104
-- "Tvorba postavy", TABULKA VLASTNOSTÍ PODLE POVOLÁNÍ a TABULKA
-- VLASTNOSTÍ PODLE RASY, content/pravidla-hrac.html). Dvě nové tabulky,
-- obě jen CREATE + INSERT (žádný DROP/destruktivní ALTER), viz CLAUDE.md.
--
-- Mechanika (h104, ověřeno přímo v textu, ne odhadem):
--  - Postava má 5 vlastností. Pro DVĚ "základní" vlastnosti svého
--    povolání (viz TABULKA VLASTNOSTÍ PODLE POVOLÁNÍ, platí pro člověka)
--    se vezme tenhle rozsah a opraví o rasovou korekci z
--    rasa_bonusy_vlastnosti.modifikator (text "+1"/"-2", přičte se k OBĚMA
--    mezím) — to dělá appka za běhu, `povolani_zakladni_vlastnosti` tady
--    ukládá jen neopravený (lidský) rozsah z tabulky.
--  - Pro ZBYLÉ 3 vlastnosti se vezme přímo finální rozsah z TABULKY
--    VLASTNOSTÍ PODLE RASY (`rasa_rozsahy_vlastnosti`), beze změny.
--  - Hod přes rozsah: h2 "Kostky" (šířka rozsahu 5/10/15 -> 1k6/2k6/3k6,
--    bonus = dolní mez - počet kostek) — implementováno v
--    includes/vtt_postava.php, ne tady.
--
-- Rozsah: jen `povolani.typ='zakladni'` (core i homebrew — "homebrew" u
-- domácích povolání NEZNAMENÁ vedlejší/volitelné, viz CLAUDE.md poslední
-- odstavec; obě core i homebrew základní povolání mají přesně stejnou
-- váhu v nabídce). `vetev` (obory od 6. úrovně) se netýká tvorby postavy
-- na 1. úrovni, proto tu žádné řádky nemá.
--
-- OTEVŘENÁ OTÁZKA (zapsáno do reportu, NEVYMYŠLENO): b600 ("Základními
-- vlastnostmi jednotlivých povolání jsou: ... Kouzelník – inteligence,
-- charisma") slibuje kouzelníkovi DVĚ základní vlastnosti, ale samotná
-- TABULKA VLASTNOSTÍ PODLE POVOLÁNÍ (b625) má u kouzelníka v sloupci
-- charisma jen "X" (žádný rozsah) — na rozdíl od všech ostatních 11
-- povolání, kde se úvodní výčet a tabulka shodují přesně. Vypadá to na
-- chybějící/ztracenou hodnotu ve zdroji (sken/sloučení), ne na záměr.
-- Vkládáme proto za Kouzelníka jen potvrzený řádek (Inteligence 14-19);
-- Charisma řádek záměrně chybí, dokud se nenajde/nepotvrdí skutečné
-- číslo — appka (includes/vtt_postava.php) to ošetřuje jako běžný
-- chybějící pár (fallback na rasový rozsah, stejně jako každá jiná
-- vlastnost označená v tabulce "X").

CREATE TABLE IF NOT EXISTS povolani_zakladni_vlastnosti (
    povolani_id   SMALLINT UNSIGNED NOT NULL,
    vlastnost_id  TINYINT UNSIGNED NOT NULL,
    stupen_od     TINYINT UNSIGNED NOT NULL,
    stupen_do     TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (povolani_id, vlastnost_id),
    FOREIGN KEY (povolani_id) REFERENCES povolani(id) ON DELETE CASCADE,
    FOREIGN KEY (vlastnost_id) REFERENCES vlastnosti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'h104 TABULKA VLASTNOSTÍ PODLE POVOLÁNÍ — neopravený (lidský) rozsah 2 "základních" vlastností pro typ=zakladni. Rasovou korekci (rasa_bonusy_vlastnosti.modifikator) aplikuje appka za běhu.';

CREATE TABLE IF NOT EXISTS rasa_rozsahy_vlastnosti (
    rasa_id       SMALLINT UNSIGNED NOT NULL,
    vlastnost_id  TINYINT UNSIGNED NOT NULL,
    stupen_od     TINYINT UNSIGNED NOT NULL,
    stupen_do     TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (rasa_id, vlastnost_id),
    FOREIGN KEY (rasa_id) REFERENCES rasy(id) ON DELETE CASCADE,
    FOREIGN KEY (vlastnost_id) REFERENCES vlastnosti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'h104 TABULKA VLASTNOSTÍ PODLE RASY — finální rozsah všech 5 vlastností podle rasy, beze změny. Pro 3 "nezákladní" vlastnosti povolání se použije přímo.';

-- =====================================================================
-- A) povolani_zakladni_vlastnosti — 2 řádky na každé typ='zakladni'
--    povolání (23 řádků: 11×2 + Kouzelník×1, viz otevřená otázka výš).
-- =====================================================================

INSERT IGNORE INTO povolani_zakladni_vlastnosti (povolani_id, vlastnost_id, stupen_od, stupen_do)
SELECT p.id, v.id, x.stupen_od, x.stupen_do
FROM povolani p
JOIN vlastnosti v ON 1=1
JOIN (
    SELECT 'Válečník' AS povolani, 'Sil' AS vl, 13 AS stupen_od, 18 AS stupen_do
    UNION ALL SELECT 'Válečník','Odl',13,18

    UNION ALL SELECT 'Hraničář','Sil',11,16
    UNION ALL SELECT 'Hraničář','Int',12,17

    UNION ALL SELECT 'Alchymista','Obr',13,18
    UNION ALL SELECT 'Alchymista','Odl',12,17

    -- Kouzelník: jen Inteligence potvrzená tabulkou (b625). Charisma viz
    -- otevřená otázka v hlavičce souboru — záměrně NEVKLÁDÁNO.
    UNION ALL SELECT 'Kouzelník','Int',14,19

    UNION ALL SELECT 'Zloděj','Obr',14,19
    UNION ALL SELECT 'Zloděj','Chr',12,17

    UNION ALL SELECT 'Tulák','Obr',12,17
    UNION ALL SELECT 'Tulák','Chr',13,18

    UNION ALL SELECT 'Stopař stínů','Sil',12,17
    UNION ALL SELECT 'Stopař stínů','Int',12,17

    UNION ALL SELECT 'Novic','Obr',13,18
    UNION ALL SELECT 'Novic','Int',14,19

    UNION ALL SELECT 'Panoš','Sil',13,18
    UNION ALL SELECT 'Panoš','Chr',13,18

    UNION ALL SELECT 'Střelec','Sil',11,16
    UNION ALL SELECT 'Střelec','Obr',12,17

    UNION ALL SELECT 'Divoch','Sil',12,17
    UNION ALL SELECT 'Divoch','Obr',12,17

    UNION ALL SELECT 'Šaman','Int',14,19
    UNION ALL SELECT 'Šaman','Chr',13,18
) x ON x.povolani = p.nazev AND x.vl = v.kod
WHERE p.typ = 'zakladni';

-- =====================================================================
-- B) rasa_rozsahy_vlastnosti — 5 řádků pro KAŽDOU rasu, přímo z h104
--    TABULKY VLASTNOSTÍ PODLE RASY pro 17 "hlavních" ras (core i
--    homebrew). Rasové varianty (trpasličí klany, "Kroll — domácí
--    rozšíření") v h104 vlastní řádek nemají — dědí rozsah rodičovské
--    rasy stejně jako rasa_bonusy_vlastnosti (viz komentář u té
--    tabulky v drd-db-full-v1.sql).
-- =====================================================================

INSERT IGNORE INTO rasa_rozsahy_vlastnosti (rasa_id, vlastnost_id, stupen_od, stupen_do)
SELECT r.id, v.id, x.stupen_od, x.stupen_do
FROM rasy r
JOIN vlastnosti v ON 1=1
JOIN (
    SELECT 'Hobit' AS rasa, 'Sil' AS vl, 3 AS stupen_od, 8 AS stupen_do
    UNION ALL SELECT 'Hobit','Obr',11,16
    UNION ALL SELECT 'Hobit','Odl',8,13
    UNION ALL SELECT 'Hobit','Int',10,15
    UNION ALL SELECT 'Hobit','Chr',8,18

    UNION ALL SELECT 'Kudůk','Sil',5,10
    UNION ALL SELECT 'Kudůk','Obr',10,15
    UNION ALL SELECT 'Kudůk','Odl',10,15
    UNION ALL SELECT 'Kudůk','Int',9,14
    UNION ALL SELECT 'Kudůk','Chr',7,12

    UNION ALL SELECT 'Trpaslík','Sil',7,12
    UNION ALL SELECT 'Trpaslík','Obr',7,12
    UNION ALL SELECT 'Trpaslík','Odl',12,17
    UNION ALL SELECT 'Trpaslík','Int',8,13
    UNION ALL SELECT 'Trpaslík','Chr',7,12

    UNION ALL SELECT 'Elf','Sil',6,11
    UNION ALL SELECT 'Elf','Obr',10,15
    UNION ALL SELECT 'Elf','Odl',6,11
    UNION ALL SELECT 'Elf','Int',12,17
    UNION ALL SELECT 'Elf','Chr',8,18

    UNION ALL SELECT 'Člověk','Sil',6,16
    UNION ALL SELECT 'Člověk','Obr',9,14
    UNION ALL SELECT 'Člověk','Odl',9,14
    UNION ALL SELECT 'Člověk','Int',10,15
    UNION ALL SELECT 'Člověk','Chr',2,17

    UNION ALL SELECT 'Barbar','Sil',10,15
    UNION ALL SELECT 'Barbar','Obr',8,13
    UNION ALL SELECT 'Barbar','Odl',11,16
    UNION ALL SELECT 'Barbar','Int',6,11
    UNION ALL SELECT 'Barbar','Chr',1,16

    UNION ALL SELECT 'Kroll','Sil',11,16
    UNION ALL SELECT 'Kroll','Obr',5,10
    UNION ALL SELECT 'Kroll','Odl',13,18
    UNION ALL SELECT 'Kroll','Int',2,7
    UNION ALL SELECT 'Kroll','Chr',1,11

    UNION ALL SELECT 'Barnové','Sil',5,15
    UNION ALL SELECT 'Barnové','Obr',8,13
    UNION ALL SELECT 'Barnové','Odl',10,15
    UNION ALL SELECT 'Barnové','Int',6,16
    UNION ALL SELECT 'Barnové','Chr',6,16

    UNION ALL SELECT 'Caelové','Sil',3,8
    UNION ALL SELECT 'Caelové','Obr',12,17
    UNION ALL SELECT 'Caelové','Odl',7,12
    UNION ALL SELECT 'Caelové','Int',10,15
    UNION ALL SELECT 'Caelové','Chr',7,17

    UNION ALL SELECT 'Furan','Sil',13,18
    UNION ALL SELECT 'Furan','Obr',5,10
    UNION ALL SELECT 'Furan','Odl',13,18
    UNION ALL SELECT 'Furan','Int',5,15
    UNION ALL SELECT 'Furan','Chr',1,11

    UNION ALL SELECT 'Gnollové','Sil',6,16
    UNION ALL SELECT 'Gnollové','Obr',9,14
    UNION ALL SELECT 'Gnollové','Odl',10,15
    UNION ALL SELECT 'Gnollové','Int',8,13
    UNION ALL SELECT 'Gnollové','Chr',3,18

    UNION ALL SELECT 'Kobold','Sil',7,12
    UNION ALL SELECT 'Kobold','Obr',8,13
    UNION ALL SELECT 'Kobold','Odl',11,16
    UNION ALL SELECT 'Kobold','Int',6,11
    UNION ALL SELECT 'Kobold','Chr',5,10

    UNION ALL SELECT 'Magiliw','Sil',3,8
    UNION ALL SELECT 'Magiliw','Obr',12,17
    UNION ALL SELECT 'Magiliw','Odl',5,10
    UNION ALL SELECT 'Magiliw','Int',10,15
    UNION ALL SELECT 'Magiliw','Chr',13,18

    UNION ALL SELECT 'Naro''shai','Sil',6,11
    UNION ALL SELECT 'Naro''shai','Obr',10,15
    UNION ALL SELECT 'Naro''shai','Odl',6,11
    UNION ALL SELECT 'Naro''shai','Int',11,16
    UNION ALL SELECT 'Naro''shai','Chr',7,17

    UNION ALL SELECT 'Skaven','Sil',5,10
    UNION ALL SELECT 'Skaven','Obr',11,16
    UNION ALL SELECT 'Skaven','Odl',8,13
    UNION ALL SELECT 'Skaven','Int',11,16
    UNION ALL SELECT 'Skaven','Chr',6,16

    UNION ALL SELECT 'Tauren','Sil',11,16
    UNION ALL SELECT 'Tauren','Obr',8,13
    UNION ALL SELECT 'Tauren','Odl',12,17
    UNION ALL SELECT 'Tauren','Int',10,15
    UNION ALL SELECT 'Tauren','Chr',2,12

    UNION ALL SELECT 'Tiefling','Sil',5,15
    UNION ALL SELECT 'Tiefling','Obr',10,15
    UNION ALL SELECT 'Tiefling','Odl',6,11
    UNION ALL SELECT 'Tiefling','Int',8,13
    UNION ALL SELECT 'Tiefling','Chr',8,18
) x ON x.rasa = r.nazev AND x.vl = v.kod;

-- Rasové varianty (trpasličí klany, "Kroll — domácí rozšíření") dědí
-- rozsah rodičovské rasy — žádný vlastní řádek v h104.
INSERT IGNORE INTO rasa_rozsahy_vlastnosti (rasa_id, vlastnost_id, stupen_od, stupen_do)
SELECT r.id, rodic.vlastnost_id, rodic.stupen_od, rodic.stupen_do
FROM rasy r
JOIN rasa_rozsahy_vlastnosti rodic ON rodic.rasa_id = r.rodic_rasa_id
WHERE r.rodic_rasa_id IS NOT NULL;
