-- Dotažení lektvar_suroviny na zbylých 46 lektvarů bez vazby na
-- suroviny (z toho většina jsou jedy/protijedy/PPE lektvary doplněné
-- v 0030-0031, kde text má jasné "základ:", jen nikdy nebyl napojen).
--
-- Znovupoužity existující shodné suroviny (Voda čistá, Med, Červené
-- nebo bílé víno, Medvědí srst), zbytek založen nově. Vynecháno beze
-- změny: Léčivý obvaz (text sám říká "bez uvedeného základu"),
-- Wolfrikovy elixíry 1.-5. stupně (žádný základ v textu vůbec
-- nezmíněn, jen cena surovin) — nevymýšlet, co zdroj nejmenuje.
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Krollí ucho', 'surovina', 0.02, 'Základ Lektvaru netopýřího sluchu.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Krollí ucho');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Netopýří krev', 'surovina', 0.05, 'Základ Lektvaru netopýřího sluchu — z jednoho obyčejného netopýra, čerstvá nebo konzervovaná.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Netopýří krev');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Oko sovy', 'surovina', 0.02, 'Základ Lektvaru sovích očí.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Oko sovy');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Jelení lůj', 'surovina', 0.1, 'Základ Lektvaru sovích očí.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jelení lůj');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Zvířecí krev (Int 1+)', 'surovina', 0.1, 'Základ Lektvaru zvířecí řeči — jen z živočicha s inteligencí 1, ne 0. Používá se i pro protijed Upíří fujtajbl.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Zvířecí krev (Int 1+)');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Dřevěný popel', 'surovina', 0.05, 'Základ lektvaru Ohnivec.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Dřevěný popel');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Slizovec skalní (výtažek)', 'surovina', NULL, 'Základ Kyselinového lektvaru (útočný, homebrew h2056). Konkrétní cena/váha suroviny není v dostupném prameni uvedena.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Slizovec skalní (výtažek)');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Bezoár', 'surovina', 0.05, 'Základ Univerzálního protijedu.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Bezoár');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Destilovaná voda', 'surovina', 0.5, 'Základ Modré záře/Modrého svitu/Modré hlubiny (destilace magenergie).'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Destilovaná voda');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Led (surovina)', 'surovina', 1.0, 'Součást základu Modré hlubiny, spolu s destilovanou vodou.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Led (surovina)');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Bolehlav (rostlina)', 'surovina', 0.1, 'Základ jedu Bolehlav — alespoň 3 rostliny na dávku, 3 měsíce sušení.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Bolehlav (rostlina)');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Náprstník', 'surovina', 0.05, 'Základ jedu Digitalis — květy, 3 měsíce sušení.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Náprstník');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Pýchavka', 'surovina', 0.05, 'Základ jedu Dýmovka — houba průměru 3-10 coulů, cca měsíc sušení.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pýchavka');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Kyz arzénu', 'surovina', 0.2, 'Základ jedu Otrušík (arzenopyrit), používají i kováři.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kyz arzénu');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Ledek', 'surovina', 0.2, 'Základ jedu Puchčoud.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Ledek');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Šťáva z cibule', 'surovina', 0.05, 'Základ jedu Slzný plyn.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šťáva z cibule');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Mletá křída', 'surovina', 0.05, 'Základ jedu Svrbík.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Mletá křída');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Muchomůrka zelená', 'surovina', 0.05, 'Základ jedu Amanitin — jedovatá celá houba, sušením neztrácí účinnost.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Muchomůrka zelená');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Rulík zlomocný', 'surovina', 0.05, 'Základ jedu Belladona — nejvíc jedovaté plody, 3 měsíce sušení.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rulík zlomocný');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Bobulky z jmelí', 'surovina', 0.05, 'Základ jedu Moucha.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Bobulky z jmelí');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Mandle', 'surovina', 0.1, 'Základ jedu Dlouhý život.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Mandle');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Citronová šťáva', 'surovina', 0.05, 'Základ jedu Malé oko.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Citronová šťáva');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Pryskyřice', 'surovina', 0.1, 'Základ protijedu Eraruk — sbírá se z jehličnatých stromů.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pryskyřice');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Rajská šťáva', 'surovina', 0.05, 'Základ protijedu Melanina památka.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rajská šťáva');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Sůl (kuchyňská)', 'surovina', 0.1, 'Základ protijedu Šmolková sůl.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sůl (kuchyňská)');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Levandule', 'surovina', 0.05, 'Základ protijedu Bolehoj.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Levandule');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Mletá kostní moučka', 'surovina', 0.1, 'Základ protijedu Fosfořík.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Mletá kostní moučka');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Kočičí sádlo', 'surovina', 0.1, 'Základ protijedu Kočičí mast.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kočičí sádlo');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Syrovátka', 'surovina', 0.2, 'Základ protijedu Syrník.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Syrovátka');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Česnek', 'surovina', 0.05, 'Součást základu protijedu Upíří fujtajbl (spolu se zvířecí krví).'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Česnek');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Vaječný bílek', 'surovina', 0.05, 'Základ protijedu Vaječný led.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Vaječný bílek');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Škrobová moučka', 'surovina', 0.1, 'Základ protijedu Flekatec.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Škrobová moučka');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Kyselé mléko', 'surovina', 0.2, 'Základ protijedu Violík.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kyselé mléko');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Žabí vajíčka', 'surovina', 0.05, 'Základ protijedu Ježibabí rosol.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Žabí vajíčka');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Mletý vápenec', 'surovina', 0.1, 'Základ protijedu Rozvid.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Mletý vápenec');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Cukr', 'surovina', 0.1, 'Základ protijedu Sladký život.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cukr');
INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Tabák (dýmkové koření)', 'surovina', 0.1, 'Základ protijedu Zlatodým, eventuálně nahraditelný kadidlem.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Tabák (dýmkové koření)');

-- Napojení lektvar_id -> predmet (podle jména, existující i nově založené)

INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 36, id, NULL FROM predmety WHERE nazev = 'Krollí ucho';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 36, id, 'cca z 1 netopýra' FROM predmety WHERE nazev = 'Netopýří krev';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 38, id, NULL FROM predmety WHERE nazev = 'Oko sovy';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 38, id, NULL FROM predmety WHERE nazev = 'Jelení lůj';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 39, id, NULL FROM predmety WHERE nazev = 'Zvířecí krev (Int 1+)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 40, id, NULL FROM predmety WHERE nazev = 'Dřevěný popel';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 33, id, NULL FROM predmety WHERE nazev = 'Slizovec skalní (výtažek)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 42, id, NULL FROM predmety WHERE nazev = 'Bezoár';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 44, id, NULL FROM predmety WHERE nazev = 'Destilovaná voda';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 45, id, NULL FROM predmety WHERE nazev = 'Destilovaná voda';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 46, id, NULL FROM predmety WHERE nazev = 'Destilovaná voda';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 46, id, NULL FROM predmety WHERE nazev = 'Led (surovina)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 58, id, '3 rostliny' FROM predmety WHERE nazev = 'Bolehlav (rostlina)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 59, id, NULL FROM predmety WHERE nazev = 'Náprstník';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 60, id, NULL FROM predmety WHERE nazev = 'Pýchavka';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 61, id, 'cca 10 mincí horniny' FROM predmety WHERE nazev = 'Kyz arzénu';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 62, id, '5 st' FROM predmety WHERE nazev = 'Ledek';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 63, id, '1 měďák' FROM predmety WHERE nazev = 'Šťáva z cibule';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 64, id, '3 st' FROM predmety WHERE nazev = 'Mletá křída';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 65, id, NULL FROM predmety WHERE nazev = 'Muchomůrka zelená';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 66, id, NULL FROM predmety WHERE nazev = 'Rulík zlomocný';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 68, id, NULL FROM predmety WHERE nazev = 'Bobulky z jmelí';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 69, id, NULL FROM predmety WHERE nazev = 'Mandle';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 70, id, NULL FROM predmety WHERE nazev = 'Citronová šťáva';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 72, id, NULL FROM predmety WHERE nazev = 'Pryskyřice';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 73, id, NULL FROM predmety WHERE nazev = 'Rajská šťáva';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 74, id, NULL FROM predmety WHERE nazev = 'Sůl (kuchyňská)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 76, id, '1 st' FROM predmety WHERE nazev = 'Levandule';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 77, id, '3 měďáky' FROM predmety WHERE nazev = 'Mletá kostní moučka';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 78, id, NULL FROM predmety WHERE nazev = 'Kočičí sádlo';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 80, id, NULL FROM predmety WHERE nazev = 'Syrovátka';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 81, id, 'krev teplokrevného živočicha' FROM predmety WHERE nazev = 'Zvířecí krev (Int 1+)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 81, id, 'cca 4 stroužky' FROM predmety WHERE nazev = 'Česnek';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 82, id, NULL FROM predmety WHERE nazev = 'Vaječný bílek';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 83, id, NULL FROM predmety WHERE nazev = 'Škrobová moučka';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 84, id, NULL FROM predmety WHERE nazev = 'Kyselé mléko';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 85, id, NULL FROM predmety WHERE nazev = 'Žabí vajíčka';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 86, id, NULL FROM predmety WHERE nazev = 'Mletý vápenec';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 87, id, NULL FROM predmety WHERE nazev = 'Cukr';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 88, id, 'eventuálně kadidlo' FROM predmety WHERE nazev = 'Tabák (dýmkové koření)';
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 59, 197, 'voda' FROM predmety WHERE id = 197;
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 67, 217, NULL FROM predmety WHERE id = 217;
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 79, 217, NULL FROM predmety WHERE id = 217;
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 71, 201, NULL FROM predmety WHERE id = 201;
INSERT IGNORE INTO lektvar_suroviny (lektvar_id, predmet_id, mnozstvi)
SELECT 75, 201, NULL FROM predmety WHERE id = 201;
