-- Doplnění chybějícího řádku v povolani_zakladni_vlastnosti (migrace
-- 0052) — Kouzelník měl uloženou jen Inteligenci (14-19). Text h104
-- (b599 "Kouzelník – inteligence, charisma") slibuje dvojici
-- Inteligence+Charisma jako jeho ZÁKLADNÍ vlastnosti, ale samotná
-- TABULKA VLASTNOSTÍ PODLE POVOLÁNÍ (b625) měla u Charismatu "X" —
-- což je u Kouzelníka jediný případ nesouladu mezi textem a tabulkou
-- v celé h104 (u všech ostatních povolání text i tabulka sedí, viz
-- Zloděj/Alchymista/Hraničář/Válečník). Vypadalo to na chybu v
-- přepisu pravidel do HTML, ne na záměr — ověřeno přímo v originální
-- příručce (ne odhadem, viz CLAUDE.md): Kouzelník × Charisma = 13–18.
--
-- Migrace nikdy needituje starý soubor (viz CLAUDE.md) — 0052 zůstává
-- beze změny, tohle je samostatný doplňující INSERT.
--
-- INSERT IGNORE = stejná idempotence jako zbytek repa; povolani_id=1
-- (Kouzelník) a vlastnost_id=5 (Chr) jsou pevná data z pravidlové DB,
-- ne něco, co by se měnilo mezi prostředími.

INSERT IGNORE INTO povolani_zakladni_vlastnosti (povolani_id, vlastnost_id, stupen_od, stupen_do)
SELECT p.id, v.id, 13, 18
FROM povolani p, vlastnosti v
WHERE p.nazev = 'Kouzelník' AND v.kod = 'Chr';
