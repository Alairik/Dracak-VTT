-- Zbraně z tabulek zbraní uživatele, které v katalogu chyběly (Nové Zbraně tváří v tvář, Nové Super těžké zbraně; b14664–b14827).
-- Samé DML (INSERT jen když zbraň daného jména ještě není). Navazuje na 0095 / 0096 (sloupce chvat, trida_zbrane, ini_postih).
--
-- Chyběly: Bastard a Válečné kladivo v obouruční variantě, Kůsa, Obouruční meč jako supertěžká jednoruční zbraň (SZ 6)
-- a kombinace dvou zbraní (Dvě dýky, Kord + dýka …). Kombinace se podle základní knihy (b10686 †) skládají ze dvou jednoručních
-- zbraní, jejichž díly lze používat i jednotlivě; cena a váha jsou součtem dílů — hodnoty jsou přepsané z tabulky tak, jak jsou
-- (u Dvou katan, Dvou dlouhých mečů a Dvou Schiavon i u Dvou boxerů vychází cena řádově jinak než součet dílů — viz
-- docs/kontrolni-seznam-neuplnych-mist.md, nechány podle knihy).
-- Druh zranění (kategorie_zbrane) kombinace = sjednocení druhů jejích dílů; Kůsa ho nemá (tabulka neuvádí).
-- Cena je v zlatých (1 zl = 10 st), váha v mincích, jako u ostatních zbraní. Postih k iniciativě −2 mají jen označené zbraně.


INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvě dýky', 'zbran', 'secna,bodna', 1, -1, 10, 0.20, 3, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 3, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvě dýky');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Hákové meče', 'zbran', 'secna,bodna', 1, 3, 32, 1.60, 3, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 3, délka: 1. Kombinace dvou zbraní, obě ruce. 15 % na odzbrojení protivníka.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Hákové meče');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Kord + dýka', 'zbran', 'secna,bodna', 2, 2, 20, 2.10, 4, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 4, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Kord + dýka');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Krátký meč a dýka', 'zbran', 'secna,bodna', 1, 2, 25, 1.70, 4, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 4, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Krátký meč a dýka');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Tesák a dýka', 'zbran', 'secna,bodna', 0, 2, 17, 0.90, 4, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 4, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Tesák a dýka');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Tesák + palcát', 'zbran', 'secna,bodna,tupa', 0, -2, 32, 1.60, 6, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 6, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Tesák + palcát');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Kord + krátký meč', 'zbran', 'secna,bodna', 1, 2, 35, 3.60, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Kord + krátký meč');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dva boxery', 'zbran', 'tupa', NULL, -3, 6, 0.10, 2, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 2, délka: 1. Kombinace dvou zbraní, obě ruce. Útočnost 2× Odol. (jako Boxer); útok ½ síly + ½ obratnosti zaokrouhleno dolů, jen na nekryté části těla.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dva boxery');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvakrát Tanto', 'zbran', 'secna,bodna', 2, -2, 14, 3.00, 4, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 4, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'lehka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvakrát Tanto');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Široký meč a tesák', 'zbran', 'secna,bodna', -2, 2, 42, 3.80, 6, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 6, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Široký meč a tesák');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Bastard (obouruční)', 'zbran', 'secna,bodna', 2, 1, 40, 6.00, 6, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 6, délka: 1.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Bastard (obouruční)');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Šavle + tesák', 'zbran', 'secna,bodna', 0, 2, 37, 4.30, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Šavle + tesák');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Sekera + tesák', 'zbran', 'secna,bodna', 2, -1, 42, 1.60, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Sekera + tesák');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Kůsa', 'zbran', NULL, 2, 1, 55, 1.70, 4, '1-2', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 4, délka: 1-2. Druh zranění (sečná / bodná / tupá) tabulka neuvádí.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Kůsa');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Široký meč a dýka', 'zbran', 'secna,bodna', -1, 2, 35, 3.10, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Široký meč a dýka');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Šavle + dýka', 'zbran', 'secna,bodna', 0, 0, 30, 3.60, 4, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 4, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Šavle + dýka');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvakrát Kama', 'zbran', 'secna', 3, -1, 30, 0.80, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvakrát Kama');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Tanto + wakizashi', 'zbran', 'secna,bodna', 1, 1, 28, 5.00, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Tanto + wakizashi');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dva palcáty', 'zbran', 'tupa', 1, 0, 40, 1.60, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dva palcáty');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvě sekery', 'zbran', 'secna', 3, -2, 60, 1.60, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvě sekery');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Kord + Wakizashi', 'zbran', 'secna,bodna', 2, 1, 36, 5.50, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Kord + Wakizashi');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Široký meč + sekera', 'zbran', 'secna,bodna', 2, 0, 60, 3.80, 5, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'stredni', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Široký meč + sekera');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dlouhý meč + palcát', 'zbran', 'secna,bodna,tupa', -1, -1, 65, 8.30, 8, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 8, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dlouhý meč + palcát');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvě šavle', 'zbran', 'secna,bodna', -2, 2, 50, 7.00, 8, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 8, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvě šavle');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Katana + Wakizashi', 'zbran', 'secna,bodna', 1, 1, 57, 9.50, 8, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 8, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Katana + Wakizashi');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Katana + Tanto', 'zbran', 'secna,bodna', 2, 1, 47, 7.50, 7, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 7, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Katana + Tanto');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dva Yatagany', 'zbran', 'secna,bodna', 0, 0, 90, 8.40, 8, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 8, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dva Yatagany');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dva Kropáče', 'zbran', 'tupa', 3, -2, 100, 9.00, 7, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 7, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dva Kropáče');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvě válečná kladiva', 'zbran', 'bodna', 2, -1, 100, 4.00, 7, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 7, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvě válečná kladiva');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvě Schiavony', 'zbran', 'secna,bodna', 1, 3, 80, 1.30, 6, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 6, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvě Schiavony');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Tesák + dlouhý meč', 'zbran', 'secna,bodna', -2, -1, 57, 8.30, 8, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 8, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Tesák + dlouhý meč');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Sekera + kladivo', 'zbran', 'secna,bodna', 4, -2, 80, 2.80, 6, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 6, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Sekera + kladivo');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Válečné kladivo (obouruční)', 'zbran', 'bodna', 3, 1, 80, 4.00, 5, '1-2', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 5, délka: 1-2. Postih −2 na iniciativu (hvězdička v tabulce). +3 k útočnosti proti plátům.', 'obourucni', 'tezka', -2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Válečné kladivo (obouruční)');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvakrát Labrys', 'zbran', 'secna', 2, -2, 100, 6.00, 8, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 8, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvakrát Labrys');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dva široké meče', 'zbran', 'secna,bodna', 0, 1, 60, 6.00, 7, '1', 'Homebrew ceník zbraní (h2058/h2060, zdroj: Zbraně, zbroje). Síla zbraně (SZ): 7, délka: 1. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'tezka', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dva široké meče');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Obouruční meč (super těžký jednoruční)', 'zbran', 'secna,bodna', 1, 0, 60, 7.00, 6, '1-2', 'Homebrew, super těžké zbraně (h2060). Síla zbraně (SZ): 6, délka: 1-2. Tyto zbraně mají -2 na iniciativu, není-li uvedeno jinak.', 'jednoruci', 'supertezka', -2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Obouruční meč (super těžký jednoruční)');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvě katany', 'zbran', 'secna,bodna', 2, 1, 80, 1.20, 9, '1', 'Homebrew, super těžké zbraně (h2060). Síla zbraně (SZ): 9, délka: 1. Tyto zbraně mají -2 na iniciativu, není-li uvedeno jinak. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'supertezka', -2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvě katany');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dva dlouhé meče', 'zbran', 'secna,bodna', -2, 1, 90, 1.50, 12, '1-2', 'Homebrew, super těžké zbraně (h2060). Síla zbraně (SZ): 12, délka: 1-2. Tyto zbraně mají -2 na iniciativu, není-li uvedeno jinak. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'supertezka', -2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dva dlouhé meče');

INSERT INTO predmety (nazev, typ, kategorie_zbrane, utocnost, oc, vaha, cena, sila_zbrane, delka_zbrane, popis, chvat, trida_zbrane, ini_postih)
SELECT 'Dvakrát warglaive', 'zbran', 'secna,bodna', 4, 2, 140, 6.70, 7, '1', 'Homebrew, super těžké zbraně (h2060). Síla zbraně (SZ): 7, délka: 1. Tyto zbraně mají -2 na iniciativu, není-li uvedeno jinak. Kombinace dvou zbraní, obě ruce.', 'obourucni', 'supertezka', -2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE typ = 'zbran' AND nazev = 'Dvakrát warglaive');
