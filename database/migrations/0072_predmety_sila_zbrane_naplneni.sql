-- Naplnění predmety.sila_zbrane / delka_zbrane (sloupce z 0071) ze textu
-- `popis`, kde je SZ doslova uvedena: "Síla zbraně (SZ): N, délka: X."
-- (homebrew ceníky zbraní h2058/h2060 — stejné hodnoty jako PPP Tabulka
-- zbraní pro boj tváří v tvář, h1609: dýka 2, tesák 3, krátký meč 4,
-- široký meč 5, meč bastard 6, dlouhý meč 7 …). Mechanická extrakce, ne
-- odhad; 87 zbraní. Zbraně, jejichž popis SZ neuvádí (kouzelné a
-- artefaktové zbraně, rachejtle, Řetězový meč…), zůstávají NULL.
--
-- Idempotentní (jen tam, kde je SZ ještě NULL) — čisté UPDATE (DML),
-- proběhne po ručním spuštění 0071 automaticky přes migrate.php.

UPDATE predmety SET sila_zbrane = 2, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Dýka' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Tesák' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Krátký meč' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Obušek' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Palcát' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Kord' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 1, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Stiletto' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '2-5' WHERE typ = 'zbran' AND nazev = 'Bič' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Nunčaky' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 2, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Hákový meč' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Tanto' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 1, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Boxer' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Šavle' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Široký meč' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Těžký tesák' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Rapír' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Sekera (jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Kyj' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Kosa - Kama' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Bulava' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Krátké kopí' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Wakizashi' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Kropáč' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Bastard (jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Dlouhý meč' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Válečné kladivo (jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Schiavona' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Yatagan' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Katana (jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Řemdih (jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Labrys' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7, delka_zbrane = '2-3' WHERE typ = 'zbran' AND nazev = 'Jezdecké kopí' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Hůl okovaná' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Vidle' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Kopí' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Válečná sekera (obouruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Katana (obouruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Cep' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Halapartna' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Sudlice' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Obouruční meč' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Těžký kyj' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Trojzubec' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '2-4' WHERE typ = 'zbran' AND nazev = 'Píka' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Nodachi' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Válečná kosa' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Válečná sekera (super těžká jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Těžký kyj (super těžký jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Trojzubec (super těžký jednoruční)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Warglaive' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7, delka_zbrane = '1-3' WHERE typ = 'zbran' AND nazev = 'Odachi' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 8, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Obří kyj' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Minojská sekera' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 8, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Flamberg' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4, delka_zbrane = '1' WHERE typ = 'zbran' AND nazev = 'Válečný totem' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 2, delka_zbrane = '1-5' WHERE typ = 'zbran' AND nazev = 'Yubari' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 8, delka_zbrane = '1-2' WHERE typ = 'zbran' AND nazev = 'Reinhardtovo kladivo' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Krátký luk' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5 WHERE typ = 'zbran' AND nazev = 'Dlouhý luk' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5 WHERE typ = 'zbran' AND nazev = 'Yumi' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5 WHERE typ = 'zbran' AND nazev = 'Válečný luk' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Lehká kuše' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6 WHERE typ = 'zbran' AND nazev = 'Těžká kuše' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 6 WHERE typ = 'zbran' AND nazev = 'Skrytá kuše' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Prak' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5 WHERE typ = 'zbran' AND nazev = 'Prak na tyči' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 8 WHERE typ = 'zbran' AND nazev = 'Vertolův luk' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Arbalest' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 2 WHERE typ = 'zbran' AND nazev = 'Dýka (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3 WHERE typ = 'zbran' AND nazev = 'Hvězdice' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 1 WHERE typ = 'zbran' AND nazev = 'Kámen (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 0 WHERE typ = 'zbran' AND nazev = 'Flakónek (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 0 WHERE typ = 'zbran' AND nazev = 'Pochodeň (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 1 WHERE typ = 'zbran' AND nazev = 'Jehlice (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 1 WHERE typ = 'zbran' AND nazev = 'Olovo (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 2 WHERE typ = 'zbran' AND nazev = 'Ocelové koule' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5 WHERE typ = 'zbran' AND nazev = 'Kopí (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 5 WHERE typ = 'zbran' AND nazev = 'Oštěp' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Harpuna' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 3 WHERE typ = 'zbran' AND nazev = 'Sekera (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Vidle (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 4 WHERE typ = 'zbran' AND nazev = 'Vrhací sekera' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 0 WHERE typ = 'zbran' AND nazev = 'Atlatl - vrhač' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7 WHERE typ = 'zbran' AND nazev = 'Labrys (vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 8 WHERE typ = 'zbran' AND nazev = 'Válečná sekera (super těžká vrhací)' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 8 WHERE typ = 'zbran' AND nazev = 'Fuuma shuriken' AND sila_zbrane IS NULL;
UPDATE predmety SET sila_zbrane = 7 WHERE typ = 'zbran' AND nazev = 'Trojzubec (super těžký vrhací)' AND sila_zbrane IS NULL;
