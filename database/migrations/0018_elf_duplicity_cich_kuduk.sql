-- Úklid duplicitních záznamů "Elf" + oprava rasové schopnosti Čich.
--
-- V rasy byly 3 řádky "Elf" (id 1, 4, 34), ale kniha i seed
-- (drd-db-full-v1.sql) definují jen jeden — id 4 (má správný popis,
-- pohyblivost i bonusy k vlastnostem/jazyky odpovídající seedu).
-- Id 1 a 34 jsou duplicity bez popisu — 34 má created_by vyplněné (vzniklo
-- omylem přes editor při testování), 1 je nejspíš starý testovací zbytek.
-- Potvrzeno v konverzaci — smazat oboje, nechat jen id 4.
--
-- Obě duplicity měly navíc mylně napojenou rasovou schopnost "Čich".
--
-- DODATEČNÁ OPRAVA (viz 0019): tenhle soubor se nikdy úspěšně nespustil
-- na produkci (celá dávka se zasekla dřív, na 0009 kvůli ALTER právům).
-- Při čistém otestování proti nepoškozené DB ze seedu se ukázalo, že
-- domněnka "Hobit v rasy chybí, nahradil ho Kudůk" byla založená na mé
-- LOKÁLNÍ testovací DB, která je sama neúplná/poškozená (podobně jako
-- těch 50 prázdných kouzel, co jsme řešili dřív) — seed
-- (drd-db-full-v1.sql) zakládá Hobita jako úplně první rasu a rovnou mu
-- dává Čich, přesně jak mají pravidla (h9/h112). Níže je tedy guard:
-- Čich se přesune na Kudůka, JEN pokud Hobit (nebo cokoli jiného) Čich
-- už nemá — na skutečné (nepoškozené) produkci by tahle podmínka měla
-- vždy selhat a řádek se nevloží vůbec, protože Hobit už Čich mít bude.
--
-- ON DELETE CASCADE na rasa_schopnosti/rasa_bonusy_vlastnosti/rasa_jazyky/
-- rasa_povolani smaže napojená data u id 1 a 34 automaticky (pokud tam
-- vůbec jsou — DELETE má guard na nazev='Elf', takže na nepoškozené DB,
-- kde id 1/34 nejsou Elf, se nic nesmaže).
--
-- Čisté DELETE/INSERT (DML) — mělo by se nasadit samo přes migrate.php.

DELETE FROM rasy WHERE id IN (1, 34) AND nazev = 'Elf';

INSERT IGNORE INTO rasa_schopnosti (rasa_id, schopnost_id)
SELECT r.id, s.id FROM rasy r, zvlastni_schopnosti s
WHERE r.nazev = 'Kudůk' AND s.nazev = 'Čich'
AND NOT EXISTS (
    SELECT 1 FROM rasa_schopnosti rs2 WHERE rs2.schopnost_id = s.id
);
