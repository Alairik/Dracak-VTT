-- Migrace 0042 omylem založila generický stavový efekt "nemůže jednat"
-- pod jménem "Omráčení". Skutečný text pravidel (content/pravidla-hrac.html,
-- desítky výskytů "ochromení"/"ochromen", např. h1626 "Odstraň ochromení")
-- používá jako obecný název stavu "Ochromení" — "Omráčení" v pravidlech
-- označuje konkrétní bojový manévr/fintu (Úder hlavicí, h1274-1275), ne
-- obecný stav. Tahle migrace to sjednocuje na "Ochromení" bez ohledu na
-- to, v jakém pořadí migrace/ruční importy na produkci proběhly:
--
-- - Existuje jen "Omráčení" (typický stav po 0042): přejmenuje se na
--   "Ochromení", žádné jiné odkazy se neřeší (id zůstává stejné).
-- - Existují OBĚ (0042 proběhla A ZÁROVEŇ byla dřív ručně spuštěná
--   nemigrovaná část database/drd-db-full-v1.sql, co "Ochromení" založila
--   jako první): všechny odkazy (kouzlo_efekty/lektvar_efekty/
--   schopnost_efekty/aktivni_efekty) se přesměrují na "Ochromení" a
--   duplicitní "Omráčení" se smaže.
-- - Existuje jen "Ochromení" (0042 ještě neproběhla vůbec): nic z tohohle
--   se nespustí (podmínky níž nenajdou žádné "Omráčení"), jen se na konci
--   doplní rychla_volba=1, kdyby ho starší data neměla.
--
-- Samé UPDATE/INSERT IGNORE/DELETE = čisté DML, projde i pod web účtem
-- automaticky přes migrate.php, žádný ruční krok navíc (na rozdíl od 0045).
-- INSERT...SELECT s podmínkou na stejnou tabulku je zabalený do odvozené
-- tabulky (`FROM (...) x`) — MySQL/MariaDB nedovolí přímo selectovat
-- z tabulky, do které se zároveň insertuje.

UPDATE efekty
SET nazev = 'Ochromení'
WHERE nazev = 'Omráčení'
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM efekty WHERE nazev = 'Ochromení') x);

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT x.kouzlo_id, x.novy_efekt_id FROM (
  SELECT ke.kouzlo_id, (SELECT id FROM efekty WHERE nazev = 'Ochromení') AS novy_efekt_id
  FROM kouzlo_efekty ke
  WHERE ke.efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení')
) x;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT x.lektvar_id, x.novy_efekt_id FROM (
  SELECT le.lektvar_id, (SELECT id FROM efekty WHERE nazev = 'Ochromení') AS novy_efekt_id
  FROM lektvar_efekty le
  WHERE le.efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení')
) x;

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT x.schopnost_id, x.novy_efekt_id FROM (
  SELECT se.schopnost_id, (SELECT id FROM efekty WHERE nazev = 'Ochromení') AS novy_efekt_id
  FROM schopnost_efekty se
  WHERE se.efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení')
) x;

INSERT IGNORE INTO aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol)
SELECT x.typ_entity, x.entita_id, x.novy_efekt_id, x.zbyva_kol FROM (
  SELECT ae.typ_entity, ae.entita_id, (SELECT id FROM efekty WHERE nazev = 'Ochromení') AS novy_efekt_id, ae.zbyva_kol
  FROM aktivni_efekty ae
  WHERE ae.efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení')
) x;

DELETE FROM kouzlo_efekty WHERE efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení');
DELETE FROM lektvar_efekty WHERE efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení');
DELETE FROM schopnost_efekty WHERE efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení');
DELETE FROM aktivni_efekty WHERE efekt_id = (SELECT id FROM efekty WHERE nazev = 'Omráčení');
DELETE FROM efekty WHERE nazev = 'Omráčení';

UPDATE efekty SET rychla_volba = 1 WHERE nazev = 'Ochromení';
