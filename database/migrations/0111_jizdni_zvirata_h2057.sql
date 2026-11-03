-- Jezdecká zvířata z homebrew tabulky „Jezdečtí tvorové“ (h2057, Ceníky, upraveno 14. 5. 2025) — doplnění
-- k 7 zvířatům z 0067. Jen INSERT IGNORE (DML), běží ze stránky Změny databáze bez ručního SQL.
--
-- Zdroj: content/pravidla-hrac.html, h2057; přepsáno řádek po řádku, nic neodhadováno:
--   pohyblivost = sloupec Pohyblivost (hlavička: „žádné naložení“), nosnost_mn = Nosnost, vytrvalost = Vytrvalost (body únavy),
--   ujede_mil = sloupec Ujede (hlavička: „číslo : 10“; u prvních sedmi zvířat je to „ujede (mil)“ z h1635, takže stejně).
-- Cena ve stříbru a poznámky z tabulky jsou v textu popisu (cena kalkulačku neovlivňuje).
-- Hvězdičky u Ujede (osel 28*, mezek 38*, válečný kůň 38**) tabulka nevysvětluje — hodnoty zůstaly podle knihy,
-- nevysvětlené hvězdičky jsou v docs/kontrolni-seznam-neuplnych-mist.md.
--
-- cas_na_10_mil_min: h2057 „čas na 10 mil“ nemá; sloupec je NOT NULL, proto 0 = „kniha neuvádí“ (nikde se nepočítá).
-- NEJSOU zahrnuti: Draci (pohyblivost „bestiář“, nosnost „3K - 25K“ — řídí bestiář) a Mořský kůň (vytrvalost a Ujede „-“,
-- sloupec vytrvalosti je povinný). Jména řádků s letem a zemí („Pegas 1“ / „Pegas 2 (na zemi)“…) jsou přesně podle knihy:
-- u Pegase a Gryfa má řádek „na zemi“ Ujede 288 a 360 (let?), kdežto řádek bez označení 57 — vypadá to zaměněné, ověřit v tištěné knize.

INSERT IGNORE INTO jizdni_zvirata (nazev, pohyblivost, nosnost_mn, vytrvalost, ujede_mil, cas_na_10_mil_min, popis) VALUES
('válečný kůň',      19, 1000, 18,  38, 0, 'Cena 500–1000 stříbrných (h2057). Ujede 38** — hvězdička v knize nevysvětlená.'),
('obří kůň',         18, 1400, 16,  45, 0, 'Cena 300–7000 stříbrných (h2057).'),
('ušlechtilý kůň',   21,  650, 18,  57, 0, 'Cena desetitisíce stříbrných (h2057).'),
('vůl',               8, 1000, 16,  48, 0, 'Cena 20–80 stříbrných (h2057).'),
('velbloud',         12, 1500, 24,  59, 0, 'Cena 150–800 stříbrných (h2057).'),
('nosorožec',        16, 1800, 20,  56, 0, 'Cena 200–700 stříbrných (h2057).'),
('slon',             15, 2500, 20,  49, 0, 'Cena 800–3000 stříbrných (h2057).'),
('jednorožec',       20,  500, 17,  54, 0, 'Neprodejný (h2057).'),
('jelen',            16,  400, 13,  30, 0, 'Cena 50–250 stříbrných (h2057).'),
('los',              14,  600, 17,  42, 0, 'Cena 50–350 stříbrných (h2057).'),
('kanec',            14,  700, 13,  32, 0, 'Cena 50–300 stříbrných (h2057).'),
('vlk',              17,  300, 18,  50, 0, 'Cena 50–100 stříbrných (h2057).'),
('lítý vlk',         16,  400, 18,  50, 0, 'Neprodejný (h2057).'),
('pterodaktyl',      15,  600, 18, 648, 0, 'Neprodejný (h2057). Letec — Ujede platí pro let; kalkulačka cesty počítá jen pohyb po zemi podle pohyblivosti.'),
('varan',             6, 2000, 22,  39, 0, 'Cena 1000–5000 stříbrných (h2057).'),
('pegas 1',          21, 1000, 18,  57, 0, 'Cena 3000–15000 stříbrných (h2057). Řádek 1 z dvojice Pegas 1 / Pegas 2 (na zemi) — viz poznámka v hlavičce migrace.'),
('pegas 2 (na zemi)', 15,  600,  8, 288, 0, 'V knize označeno „na zemi“; prodává se pouze neinteligentní (h2057). Ujede 288 vypadá na let — ověřit v knize.'),
('gryf 1',           20, 2000, 18,  57, 0, 'Cena 5000–20000 stříbrných (h2057). Řádek 1 z dvojice Gryf 1 / Gryf 2 (na zemi).'),
('gryf 2 (na zemi)', 15, 1000, 10, 360, 0, 'V knize označeno „na zemi“; prodává se pouze neinteligentní (h2057). Ujede 360 vypadá na let — ověřit v knize.'),
('orel královský',   25,  600, 14, 706, 0, 'Neprodejný (h2057). Letec — Ujede platí pro let.'),
('orel stříbrný',    20,  400, 10, 432, 0, 'Cena 2000–10000 stříbrných (h2057). Letec — Ujede platí pro let.'),
('korunorožec',      18,  800, 14,  39, 0, 'Cena 400–800 stříbrných (h2057).'),
('talbuk',           21,  500, 16,  45, 0, 'Cena 300–700 stříbrných (h2057). Snese velké zimy.'),
('sněžný vlk obří',  18,  450, 16,  50, 0, 'Cena 1500–3000 stříbrných (h2057).'),
('dren/nauglir',     20,  900, 17,  40, 0, 'Cena 400–1000 stříbrných (h2057).'),
('orel rudý',        18,  350, 11, 305, 0, 'Cena 1800–8000 stříbrných (h2057). Letec — Ujede platí pro let.'),
('medvěd',           12,  900, 18,  29, 0, 'Neprodejný (h2057).'),
('tygr',             21,  450, 16,  40, 0, 'Neprodejný (h2057).'),
('sob',              16,  480, 19,  38, 0, 'Cena 70–200 stříbrných (h2057).'),
('liška obří',       20,  350, 16,  42, 0, 'Neprodejná (h2057).'),
('štír',             21,  300,  9,  60, 0, 'Neprodejný (h2057).'),
('sova obří 1',      17,  450, 17, 280, 0, 'Neprodejná (h2057). Pouze v noci. Letec — Ujede platí pro let.'),
('sova obří 2 (na zemi)', 5, 200,  6,  10, 0, 'Neprodejná (h2057). Pohyb po zemi.'),
('skarabeus 1',      14,  450,  8,  40, 0, 'Cena 3500–7000 stříbrných (h2057).'),
('skarabeus 2 (na zemi)', 10, 750, 19, 42, 0, 'Cena 3500–7000 stříbrných (h2057). Pohyb po zemi.'),
('ibex - beran',     17, 1000, 18,  40, 0, 'Cena 750–1300 stříbrných (h2057).');
