-- Zbraně: držení, třída a postih k iniciativě — data (DML). Navazuje na ruční SQL 0095 (sloupce predmety.chvat, trida_zbrane,
-- ini_postih), tedy až po něm.
--
-- Zdroj: tabulky zbraní uživatele (Nové Zbraně tváří v tvář, Nové Střelecké zbraně a střelivo, Nové Super těžké zbraně;
-- v knize b14664–b14827, „upraveno 22. 6. 2024“). Homebrew má přednost před základními pravidly (CLAUDE.md), proto platí
-- hvězdičky z nové tabulky (b14664: „tyto zbraně mají −2 na iniciativu“) a ne ‡ ze základní tabulky (b10672 Válečná sekera ‡).
--   chvat        jednoruci / obourucni (jen zbraně pro boj tváří v tvář; střelné a vrhací NULL)
--   trida_zbrane lehka / stredni / tezka / supertezka (střelné luky, kuše a prak třídu nemají)
--   ini_postih   postih k iniciativě při boji touto zbraní (NULL = žádný): −2 těžké obouruční zbraně označené * a všechny
--                supertěžké kromě Řetězového meče („neztrácí ini −2“); −3 Těžká kuše (Tabulka bonusů a postihů, 0045)
-- Kombinace dvou zbraní z tabulky (Dvě dýky, Kord + dýka…) v katalogu zatím nejsou, stejně jako Bastard a Válečné kladivo
-- v obouruční variantě a Obouruční meč jako supertěžká jednoruční zbraň (SZ 6) — viz docs/kontrolni-seznam-neuplnych-mist.md.

-- Lehké jednoruční (tabulka zbraní tváří v tvář)
UPDATE predmety SET chvat = 'jednoruci', trida_zbrane = 'lehka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Dýka', 'Tesák', 'Krátký meč', 'Obušek', 'Palcát', 'Kord', 'Stiletto', 'Bič', 'Nunčaky', 'Hákový meč', 'Tanto', 'Boxer');

-- Střední jednoruční
UPDATE predmety SET chvat = 'jednoruci', trida_zbrane = 'stredni', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Šavle', 'Široký meč', 'Těžký tesák', 'Rapír', 'Sekera (jednoruční)', 'Kyj', 'Kosa - Kama', 'Bulava', 'Krátké kopí', 'Wakizashi');

-- Těžké jednoruční
UPDATE predmety SET chvat = 'jednoruci', trida_zbrane = 'tezka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Kropáč', 'Bastard (jednoruční)', 'Dlouhý meč', 'Válečné kladivo (jednoruční)', 'Schiavona', 'Yatagan', 'Katana (jednoruční)', 'Řemdih (jednoruční)', 'Labrys', 'Jezdecké kopí');

-- Lehké obouruční
UPDATE predmety SET chvat = 'obourucni', trida_zbrane = 'lehka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Hůl okovaná');

-- Střední obouruční
UPDATE predmety SET chvat = 'obourucni', trida_zbrane = 'stredni', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Vidle', 'Kopí', 'Válečná sekera (obouruční)', 'Katana (obouruční)');

-- Těžké obouruční označené * („tyto zbraně mají −2 na iniciativu“)
UPDATE predmety SET chvat = 'obourucni', trida_zbrane = 'tezka', ini_postih = -2
WHERE typ = 'zbran' AND nazev IN ('Cep', 'Halapartna', 'Sudlice', 'Obouruční meč', 'Těžký kyj', 'Trojzubec', 'Píka');

-- Těžké obouruční bez hvězdičky
UPDATE predmety SET chvat = 'obourucni', trida_zbrane = 'tezka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Nodachi');

-- Supertěžké jednoruční („všechny tyto zbraně mají −2 na iniciativu“)
UPDATE predmety SET chvat = 'jednoruci', trida_zbrane = 'supertezka', ini_postih = -2
WHERE typ = 'zbran' AND nazev IN ('Válečná sekera (super těžká jednoruční)', 'Těžký kyj (super těžký jednoruční)', 'Trojzubec (super těžký jednoruční)', 'Warglaive');

-- Řetězový meč — výslovně „neztrácí ini −2“
UPDATE predmety SET chvat = 'jednoruci', trida_zbrane = 'supertezka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Řetězový meč');

-- Supertěžké obouruční
UPDATE predmety SET chvat = 'obourucni', trida_zbrane = 'supertezka', ini_postih = -2
WHERE typ = 'zbran' AND nazev IN ('Odachi', 'Obří kyj', 'Minojská sekera', 'Flamberg', 'Válečný totem', 'Yubari', 'Válečná kosa', 'Reinhardtovo kladivo');

-- Supertěžké střelné a vrhací
UPDATE predmety SET chvat = NULL, trida_zbrane = 'supertezka', ini_postih = -2
WHERE typ = 'zbran' AND nazev IN ('Vertolův luk', 'Arbalest', 'Válečná sekera (super těžká vrhací)', 'Fuuma shuriken', 'Trojzubec (super těžký vrhací)');

-- Těžká kuše (řádek „Těžká kuše“ Tabulky bonusů a postihů k iniciativě, −3)
UPDATE predmety SET chvat = NULL, trida_zbrane = NULL, ini_postih = -3
WHERE typ = 'zbran' AND nazev IN ('Těžká kuše');

-- Lehké vrhací
UPDATE predmety SET chvat = NULL, trida_zbrane = 'lehka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Dýka (vrhací)', 'Hvězdice', 'Kámen (vrhací)', 'Flakónek (vrhací)', 'Pochodeň (vrhací)', 'Jehlice (vrhací)');

-- Střední vrhací
UPDATE predmety SET chvat = NULL, trida_zbrane = 'stredni', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Olovo (vrhací)', 'Ocelové koule', 'Kopí (vrhací)', 'Oštěp', 'Harpuna', 'Sekera (vrhací)', 'Vidle (vrhací)', 'Vrhací sekera', 'Atlatl - vrhač');

-- Těžké vrhací
UPDATE predmety SET chvat = NULL, trida_zbrane = 'tezka', ini_postih = NULL
WHERE typ = 'zbran' AND nazev IN ('Labrys (vrhací)');

-- Popis Warglaive tvrdil, že postih −2 nemá; tabulka (supertěžké jednoruční, „všechny tyto zbraně mají −2“) říká opak
-- („neztrácí −2 k ini“ patří Řetězovému meči).
UPDATE predmety SET popis = REPLACE(popis, 'Neztrácí postih -2 k iniciativě jako ostatní super těžké zbraně.', 'Má postih -2 k iniciativě jako ostatní super těžké zbraně.')
WHERE typ = 'zbran' AND nazev = 'Warglaive';
