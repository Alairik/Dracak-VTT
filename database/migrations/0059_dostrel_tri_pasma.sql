-- Dostřel u střelných/vrhacích zbraní má podle pravidel TŘI pásma, ne
-- dvě (h1615, Střelecký souboj, str. 74): "U každé zbraně je uveden
-- její malý, střední a největší dostřel. Při malé vzdálenosti (≤ malý
-- dostřel) přičti k hodu na útok 1. Při střední vzdálenosti (> malý,
-- ≤ střední) žádná oprava. Při velké vzdálenosti (> střední, ≤ velký)
-- odečti 1. Dál než je velký dostřel zbraně nelze střílet."
--
-- Migrace 0006_zraneni_dosah_zbrani.sql zavedla jen dva sloupce
-- (dostrel_efektivni/dostrel_maximalni) s komentářem, který si vymyslel
-- postih "-5 k útoku" — v pravidlech nikde není, správně je to +1/0/-1
-- podle pásma. Přejmenováno na dostrel_stredni/dostrel_velky (ta dvě
-- pásma, co migrace 0006 vlastně myslela, i když jinak pojmenovaná) a
-- přidán dostrel_maly coby třetí pásmo. Bezpečné přejmenovat beze
-- ztráty dat — podle includes/vtt_predmety.php jsou tyhle sloupce ve
-- všech řádcích predmety (produkce i testovací DB) dodnes NULL.
ALTER TABLE predmety
    ADD COLUMN dostrel_maly SMALLINT NULL COMMENT 'nejbližší pásmo dostřelu (≤ dostrel_maly) — v tomhle pásmu +1 k hodu na útok (h1615)' AFTER dosah,
    CHANGE COLUMN dostrel_efektivni dostrel_stredni SMALLINT NULL COMMENT 'střední pásmo dostřelu (> dostrel_maly, ≤ dostrel_stredni) — bez opravy k útoku (h1615)',
    CHANGE COLUMN dostrel_maximalni dostrel_velky SMALLINT NULL COMMENT 'největší pásmo dostřelu (> dostrel_stredni, ≤ dostrel_velky) — -1 k útoku; dál už zbraň vůbec nedostřelí (h1615)';

-- Data: doslovný přepis z TABULKA ZBRANÍ PRO STŘELECKÝ SOUBOJ (h1615,
-- core) a Nové střelecké zbraně a střelivo / Vrhací zbraně (h2059,
-- homebrew — CLAUDE.md: homebrew = plnohodnotná součást pravidel, ne
-- bokem). Číslo u každé zbraně dole je přímo z popisu toho řádku
-- ("Dostřel malý/střední/velký: X/Y/Z sáhů"), žádný dopočet.
--
-- 'Atlatl - vrhač' vynechán záměrně — jeho "dostřel x2/x2/x2" není
-- číslo, je to násobič dostřelu OŠTĚPŮ/HARPUN (viz jeho popis), nemá
-- vlastní pásma k nastavení.
UPDATE predmety SET dostrel_maly = 10, dostrel_stredni = 20, dostrel_velky = 30 WHERE nazev = 'Krátký luk';
UPDATE predmety SET dostrel_maly = 15, dostrel_stredni = 30, dostrel_velky = 50 WHERE nazev = 'Dlouhý luk';
UPDATE predmety SET dostrel_maly = 15, dostrel_stredni = 30, dostrel_velky = 50 WHERE nazev = 'Yumi';
UPDATE predmety SET dostrel_maly = 20, dostrel_stredni = 45, dostrel_velky = 70 WHERE nazev = 'Válečný luk';
UPDATE predmety SET dostrel_maly = 15, dostrel_stredni = 27, dostrel_velky = 40 WHERE nazev = 'Lehká kuše';
UPDATE predmety SET dostrel_maly = 19, dostrel_stredni = 35, dostrel_velky = 55 WHERE nazev = 'Těžká kuše';
UPDATE predmety SET dostrel_maly = 1, dostrel_stredni = 3, dostrel_velky = 5 WHERE nazev = 'Skrytá kuše';
UPDATE predmety SET dostrel_maly = 9, dostrel_stredni = 16, dostrel_velky = 25 WHERE nazev = 'Prak';
UPDATE predmety SET dostrel_maly = 12, dostrel_stredni = 20, dostrel_velky = 35 WHERE nazev = 'Prak na tyči';
UPDATE predmety SET dostrel_maly = 20, dostrel_stredni = 40, dostrel_velky = 60 WHERE nazev = 'Vertolův luk';
UPDATE predmety SET dostrel_maly = 20, dostrel_stredni = 40, dostrel_velky = 80 WHERE nazev = 'Arbalest';
UPDATE predmety SET dostrel_maly = 5, dostrel_stredni = 9, dostrel_velky = 12 WHERE nazev = 'Dýka (vrhací)';
UPDATE predmety SET dostrel_maly = 3, dostrel_stredni = 7, dostrel_velky = 11 WHERE nazev = 'Hvězdice';
UPDATE predmety SET dostrel_maly = 7, dostrel_stredni = 11, dostrel_velky = 15 WHERE nazev = 'Kámen (vrhací)';
UPDATE predmety SET dostrel_maly = 5, dostrel_stredni = 9, dostrel_velky = 13 WHERE nazev = 'Flakónek (vrhací)';
UPDATE predmety SET dostrel_maly = 4, dostrel_stredni = 7, dostrel_velky = 10 WHERE nazev = 'Pochodeň (vrhací)';
UPDATE predmety SET dostrel_maly = 1, dostrel_stredni = 2, dostrel_velky = 3 WHERE nazev = 'Jehlice (vrhací)';
UPDATE predmety SET dostrel_maly = 6, dostrel_stredni = 10, dostrel_velky = 14 WHERE nazev = 'Olovo (vrhací)';
UPDATE predmety SET dostrel_maly = 7, dostrel_stredni = 11, dostrel_velky = 15 WHERE nazev = 'Ocelové koule';
UPDATE predmety SET dostrel_maly = 5, dostrel_stredni = 10, dostrel_velky = 15 WHERE nazev = 'Kopí (vrhací)';
UPDATE predmety SET dostrel_maly = 10, dostrel_stredni = 20, dostrel_velky = 30 WHERE nazev = 'Oštěp';
UPDATE predmety SET dostrel_maly = 5, dostrel_stredni = 15, dostrel_velky = 20 WHERE nazev = 'Harpuna';
UPDATE predmety SET dostrel_maly = 4, dostrel_stredni = 8, dostrel_velky = 12 WHERE nazev = 'Sekera (vrhací)';
UPDATE predmety SET dostrel_maly = 3, dostrel_stredni = 5, dostrel_velky = 7 WHERE nazev = 'Vidle (vrhací)';
UPDATE predmety SET dostrel_maly = 5, dostrel_stredni = 10, dostrel_velky = 14 WHERE nazev = 'Vrhací sekera';
UPDATE predmety SET dostrel_maly = 4, dostrel_stredni = 7, dostrel_velky = 11 WHERE nazev = 'Labrys (vrhací)';
UPDATE predmety SET dostrel_maly = 2, dostrel_stredni = 4, dostrel_velky = 6 WHERE nazev = 'Válečná sekera (super těžká vrhací)';
UPDATE predmety SET dostrel_maly = 3, dostrel_stredni = 6, dostrel_velky = 9 WHERE nazev = 'Fuuma shuriken';
UPDATE predmety SET dostrel_maly = 7, dostrel_stredni = 11, dostrel_velky = 15 WHERE nazev = 'Trojzubec (super těžký vrhací)';
