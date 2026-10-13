-- Smazání nedoložených vah z migrací 0035 a 0036.
--
-- 0035 (suroviny pro Chodcova "kouzla pocestných") a 0036 (suroviny k
-- lektvarům) založily 42 surovin s `vaha` 0,01–1,0 — bez citace zdroje
-- a bez opory v textu pravidel. Ověřeno hledáním každého názvu v
-- content/pravidla-hrac.html, pravidla-pj.html a pravidla-bestiar.html:
-- u žádné z těch surovin pravidla váhu neuvádějí (jediné "zásahy" byly
-- nesouvisející shody názvu, např. Bezoár jako surovina na obojek
-- nebo rasa Kroll). Hodnoty nejsou ani cena (ta je v pravidlech u
-- surovin v zl/st a nesedí, např. mletá křída 3 st vs. 0,05).
--
-- Podle pravidla v CLAUDE.md (co nemá oporu v pravidlech, tam nemá být)
-- se váha vrací na NULL = "neznámá". Automatický součet nákladu
-- (dracak_vtt_postava_naklad_mn) pak takovou položku hlásí jako
-- "bez uvedené váhy" (neúplný součet) místo aby počítal s vymyšleným
-- číslem. Doplní se, až bude váha v knize.
--
-- Maže se jen tam, kde se jméno I hodnota shodují s tím, co migrace
-- vložila — kdyby někdo mezitím váhu opravil ručně podle knihy, zůstane.
--
-- Čisté UPDATE (DML) — projde automaticky přes migrate.php.

UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Svíce z pravého včelího vosku' AND vaha = 0.20;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Pouštní písek' AND vaha = 0.10;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Medvědí srst' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Oblázek z teplých moří' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Dubový list (darovaný druidem)' AND vaha = 0.01;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Křemen (na rozdělání ohně)' AND vaha = 0.30;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Krollí ucho' AND vaha = 0.02;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Netopýří krev' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Oko sovy' AND vaha = 0.02;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Jelení lůj' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Zvířecí krev (Int 1+)' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Dřevěný popel' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Bezoár' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Destilovaná voda' AND vaha = 0.5;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Led (surovina)' AND vaha = 1.0;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Bolehlav (rostlina)' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Náprstník' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Pýchavka' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Kyz arzénu' AND vaha = 0.2;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Ledek' AND vaha = 0.2;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Šťáva z cibule' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Mletá křída' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Muchomůrka zelená' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Rulík zlomocný' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Bobulky z jmelí' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Mandle' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Citronová šťáva' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Pryskyřice' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Rajská šťáva' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Sůl (kuchyňská)' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Levandule' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Mletá kostní moučka' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Kočičí sádlo' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Syrovátka' AND vaha = 0.2;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Česnek' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Vaječný bílek' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Škrobová moučka' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Kyselé mléko' AND vaha = 0.2;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Žabí vajíčka' AND vaha = 0.05;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Mletý vápenec' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Cukr' AND vaha = 0.1;
UPDATE predmety SET vaha = NULL WHERE typ = 'surovina' AND nazev = 'Tabák (dýmkové koření)' AND vaha = 0.1;
