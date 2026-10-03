-- nosnost_podle_sily (migrace 0007) měla úplně špatná data — sloupec
-- zakladni_nosnost_lb (pojmenovaný, jako by šlo o libry) obsahoval
-- čísla 7–28, ale DrD měří náklad v mincích (mn), ne v librách, a
-- skutečná TABULKA NOSNOSTI (content/pravidla-hrac.html h1681,
-- b11121–b11132, přímo ověřeno v textu, ne odhadem) dává:
--   bonus za sílu −5→210 mn, −4→240, −3→270, −2→300, −1→330, 0→360,
--   +1→390, +2→420, +3→450, +4→480, +5→510 mn
-- — čistě lineárně, +30 mn za každý +1 bonusu. Migrace 0007 na rozdíl
-- od pozdějších migrací v repu nemá žádnou citaci zdroje — tahle
-- tabulka se zjevně nikdy neověřila proti textu. Tohle je oprava
-- základních dat, ne jen rozšíření.
--
-- Text (h1681) tabulku vůbec netáhne nad bonus +5 — to je strop i pro
-- hráčskou "TABULKA POSTIHŮ A BONUSŮ" (h105, stupeň 1–21). Bonus +6 až
-- +10 (nestvůry se stupněm vlastnosti nad 21, viz migrace 0048 a
-- content/pravidla-bestiar.html h2392 "TABULKA BONUSŮ") nemá v žádném
-- dostupném zdroji vlastní nosnostní tabulku — DOPOČÍTÁNO stejným
-- vzorcem jako zbytek řady (360 + 30×bonus), protože krok +30 mn/+1 je
-- bezvýjimečný napříč všech 11 oficiálních řádků. Je to mechanické
-- prodloužení stejného vzorce, ne odhad od nuly, ale pokud se najde
-- explicitní zdroj pro vysokou sílu nestvůr, řádky +6..+10 jsou první
-- na přepsání.
--
-- Sloupec zůstává zakladni_nosnost_lb (přejmenování je ALTER mimo
-- CREATE/ALTER ADD/INSERT, mimo rozsah migrací, viz CLAUDE.md), i když
-- "lb" je zavádějící — hodnota je ve skutečnosti mn.
--
-- Žádná FK na nosnost_podle_sily.id (čistě referenční číselník v
-- editoru, viz migrace 0045) — DELETE+INSERT bezpečné, čisté DML,
-- projde automaticky přes migrate.php i pod web účtem.

DELETE FROM nosnost_podle_sily;

INSERT INTO nosnost_podle_sily (oprava_sil, zakladni_nosnost_lb) VALUES
(-5, 210), (-4, 240), (-3, 270), (-2, 300), (-1, 330), (0, 360),
(1, 390), (2, 420), (3, 450), (4, 480), (5, 510),
(6, 540), (7, 570), (8, 600), (9, 630), (10, 660);
