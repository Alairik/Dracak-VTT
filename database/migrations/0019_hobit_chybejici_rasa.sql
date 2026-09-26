-- Oprava chyby z 0018: Čich je schopnost Hobita, ne Kudůka.
--
-- Kniha (h8) říká výslovně: "Ze sedmi rozličných ras si můžeš vybrat...
-- hobitem, kudůkem, trpaslíkem, elfem, člověkem, barbarem nebo krollem."
-- Hobit je samostatná, sedmá core rasa — NENÍ nahrazená Kudůkem, jak jsem
-- se mylně domníval v 0018.
--
-- DODATEČNÁ OPRAVA: tenhle soubor (ani 0018) se nikdy úspěšně nespustil
-- na produkci (celá dávka se zasekla dřív, na 0009 kvůli ALTER právům) —
-- ověřoval jsem to jen proti své lokální testovací DB, kde Hobit
-- skutečně chyběl. Při testu proti čerstvě naimportované DB ze seedu
-- (drd-db-full-v1.sql) se ale ukázalo, že seed Hobita zakládá rovnou
-- jako úplně první rasu i se vším příslušenstvím — moje lokální DB byla
-- v tomhle sama poškozená/neúplná, ne produkce. Nemám na produkci přímý
-- přístup, takže nemůžu s jistotou vědět, jestli je na tom stejně jako
-- moje testovací DB, nebo jako čistý seed — proto je celý soubor napsaný
-- tak, aby byl bezpečný v obou případech (idempotentní): pokud Hobit už
-- existuje, nic dalšího se nezaloží ani nepřepíše; pokud chybí, založí
-- se přesně jak bylo zamýšleno.

DELETE FROM rasa_schopnosti
WHERE rasa_id = (SELECT id FROM rasy WHERE nazev = 'Kudůk')
  AND schopnost_id = (SELECT id FROM zvlastni_schopnosti WHERE nazev = 'Čich');

INSERT INTO rasy (nazev, rodic_rasa_id, popis)
SELECT 'Hobit', NULL,
'výška: 70–120 coulů, váha: 800–1200 mn (40–60 kg), třída velikosti: A0

Hobiti jsou malým, veselým národem. Jsou ještě menší než trpaslíci, chlupatí a jaksi "dobrácky kulaťoučí". Žijí zpravidla v úrodných údolích, kde si ve stráních budují komfortní nory a doupata.

Rozhodně nejsou nijak zvlášť silní, ale zato jsou velmi obratní a povětšinou inteligentní. Dobrodružství příliš nevyhledávají, protože rádi pohodlí, dobré jídlo a silné pití. Jestliže se však jednou přece jen vydají na cesty, patří k tem nejlepším zlodějům, jaké je vidět.

Hobiti mají také zvláštní psychickou schopnost, díky které dokážou "vycítit" na dálku většinu živých tvorů. Této zvláštní vlastnosti říkají mezi sebou "čich", a přestože jejich pohodlnickým životem již dlouho neaplikovali, dokáží díky ní lokalizovat živou bytost, dokonce i když je od nich vzdálena, například železnou stěnou.

Přestože hobiti nejsou zbabělí, jen neradi přistupují na boj tváří v tvář a raději používají meče nebo halapartny.'
WHERE NOT EXISTS (SELECT 1 FROM rasy WHERE nazev = 'Hobit');

INSERT IGNORE INTO rasa_bonusy_vlastnosti (rasa_id, vlastnost_id, modifikator)
SELECT r.id, v.id, m.modifikator
FROM rasy r
JOIN vlastnosti v
CROSS JOIN (
    SELECT 'Síla' AS vl, '-5' AS modifikator
    UNION ALL SELECT 'Obratnost', '+2'
    UNION ALL SELECT 'Odolnost', '0'
    UNION ALL SELECT 'Inteligence', '-2'
    UNION ALL SELECT 'Charisma', '+3'
) m ON m.vl = v.nazev
WHERE r.nazev = 'Hobit';

INSERT IGNORE INTO rasa_jazyky (rasa_id, jazyk_id)
SELECT r.id, j.id FROM rasy r, jazyky j WHERE r.nazev = 'Hobit' AND j.nazev = 'Obecná mluva';

INSERT IGNORE INTO rasa_schopnosti (rasa_id, schopnost_id)
SELECT r.id, s.id FROM rasy r, zvlastni_schopnosti s WHERE r.nazev = 'Hobit' AND s.nazev = 'Čich';
