-- Oprava chyby z 0018: Čich je schopnost Hobita, ne Kudůka.
--
-- Kniha (h8) říká výslovně: "Ze sedmi rozličných ras si můžeš vybrat...
-- hobitem, kudůkem, trpaslíkem, elfem, člověkem, barbarem nebo krollem."
-- Hobit je samostatná, sedmá core rasa — NENÍ nahrazená Kudůkem, jak jsem
-- se mylně domníval v 0018. V `rasy` ale úplně chyběl (seed
-- drd-db-full-v1.sql ho měl jako úplně první INSERT, řádek se ale zjevně
-- někdy ztratil). Napravuji: ruším chybný přesun na Kudůka a zakládám
-- chybějící řádek Hobit se vším, co k němu podle seedu a pravidel patří
-- (bonusy k vlastnostem, jazyk, schopnost Čich). Popis je narativní text
-- z content/pravidla-hrac.html (h9), stejně jako u ostatních ras v 0017.
--
-- Čisté DELETE/INSERT (DML) — mělo by se nasadit samo přes migrate.php.

DELETE FROM rasa_schopnosti
WHERE rasa_id = (SELECT id FROM rasy WHERE nazev = 'Kudůk')
  AND schopnost_id = (SELECT id FROM zvlastni_schopnosti WHERE nazev = 'Čich');

INSERT INTO rasy (nazev, rodic_rasa_id, popis) VALUES
('Hobit', NULL,
'výška: 70–120 coulů, váha: 800–1200 mn (40–60 kg), třída velikosti: A0

Hobiti jsou malým, veselým národem. Jsou ještě menší než trpaslíci, chlupatí a jaksi "dobrácky kulaťoučí". Žijí zpravidla v úrodných údolích, kde si ve stráních budují komfortní nory a doupata.

Rozhodně nejsou nijak zvlášť silní, ale zato jsou velmi obratní a povětšinou inteligentní. Dobrodružství příliš nevyhledávají, protože rádi pohodlí, dobré jídlo a silné pití. Jestliže se však jednou přece jen vydají na cesty, patří k tem nejlepším zlodějům, jaké je vidět.

Hobiti mají také zvláštní psychickou schopnost, díky které dokážou "vycítit" na dálku většinu živých tvorů. Této zvláštní vlastnosti říkají mezi sebou "čich", a přestože jejich pohodlnickým životem již dlouho neaplikovali, dokáží díky ní lokalizovat živou bytost, dokonce i když je od nich vzdálena, například železnou stěnou.

Přestože hobiti nejsou zbabělí, jen neradi přistupují na boj tváří v tvář a raději používají meče nebo halapartny.');

INSERT INTO rasa_bonusy_vlastnosti (rasa_id, vlastnost_id, modifikator)
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

INSERT INTO rasa_jazyky (rasa_id, jazyk_id)
SELECT r.id, j.id FROM rasy r, jazyky j WHERE r.nazev = 'Hobit' AND j.nazev = 'Obecná mluva';

INSERT INTO rasa_schopnosti (rasa_id, schopnost_id)
SELECT r.id, s.id FROM rasy r, zvlastni_schopnosti s WHERE r.nazev = 'Hobit' AND s.nazev = 'Čich';
