-- 0119 — Dvě kouzla, která hra uměla spočítat, ale nespouštěla: Řetězový blesk a Zmrzlá země
--
-- Proč: engine kouzel (includes/vtt_predmety.php, dracak_vtt_polozka_pouzij_ucinek) hodí kostkami kouzla
-- a strhne životy JEN tehdy, když je ke kouzlu navázaný efekt „Poškození“ (nebo „Léčení“). Kostky
-- v tabulce kouzla samy o sobě nic neudělají. Těchto dvou kouzel se to týkalo: mají kostky, nemají past
-- (cíl si nehází) a v pravidlech je zranění bezpodmínečné — takže je hra od teď strhne sama.
--
-- Citace (content/pravidla-hrac.html):
--   b9717 — Řetězový blesk: „Tempest vyšle blesk na zvolený cíl max 5 sáhů … tento blesk dá 4k6 zranění bleskem“
--           (přeskakování na další cíle se slábnoucím zraněním zůstává na PJ — hra zatím neumí řetězit cíle,
--            automaticky se vyhodnotí jen první zásah, což je ta část bez jakékoli podmínky).
--   b5380 — Zmrzlá země: „každý kdo v okamžiku zamrzání stojí na zasažené ploše je zraněn za 1k4 životů ledem“
--           (uklouznutí za 1k8 později je hod na šanci, ten zůstává na PJ).
--
-- Proč jen tahle dvě a ne všech 95 kouzel s kostkami bez efektu: u zbytku kostky neznamenají „zranění cíle“
-- (Sloup ohně škáluje podle velikosti cíle, Zvukový výboj má jiné kostky pro cíl a pro okolí, Ledové kopí je
-- bonus zbraně, Paprsek zraní NEBO léčí podle povahy cíle, Živá bomba a Chlad hvězd mají hod proti pasti…).
-- Navázat jim „Poškození“ by bylo hádání mechaniky — viz CLAUDE.md. Rozbor je v docs/stav-projektu.md.
--
-- Jen INSERT, žádné DDL: stránka Změny databáze to spustí sama pod web účtem.

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, e.id
  FROM kouzla k
  JOIN efekty e ON e.nazev = 'Poškození'
 WHERE k.nazev IN ('Řetězový blesk', 'Zmrzlá země')
   AND k.pocet_kostek IS NOT NULL
   AND k.typ_kostky IS NOT NULL;
