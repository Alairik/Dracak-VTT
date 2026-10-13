-- Oprava rasy.pohyblivost_zaklad (migrace 0007 ji naplnila bez zdroje).
--
-- 0007 dala Trpaslíkovi 20 a Člověku/Elfovi/Barbarovi 30 a Hobita/Kudůka/
-- Krolla nechala NULL s odůvodněním, že jména v pravidlech nesedí na naše
-- rasy ("Půlčík/Gnóm/Obr"). Skutečný text pravidel ale tabulku se
-- správnými jmény i hodnotami má (ověřeno přímo v content/pravidla-hrac.html,
-- ne odhadem, viz CLAUDE.md):
--
--   h1681 "TABULKA POHYBLIVOSTI RAS" (b11140–b11146), shodně s příkladem
--   Hruna v h1634 (b10866) — "Barbar ... 12 + (−1) + 2×(+4) = 19":
--     hobit 10, kudůk 9, trpaslík 8, elf 12, člověk 11, barbar 12, kroll 11
--   Vzorec (h1681 b11147, h1634 b10865): základní pohyblivost = tabulková
--   hodnota rasy + bonus za obratnost + 2× bonus za sílu. Sloupec drží
--   JEN tu tabulkovou hodnotu rasy (komentář sloupce z 0007 "finální =
--   tohle + oprava za OBR" je zastaralý — bez 2× SIL; komentář se tu
--   nemění, MODIFY COLUMN je mimo CREATE/ALTER ADD/INSERT).
--
--   Homebrew rasy (h36–h89) mají vlastní řádek "Pohyblivost:" ve svém
--   bloku "Vlastnosti", vždy buď odkazem na core rasu s číslem, nebo
--   číslem:
--     Barnové  (h37, b172)  "barbar (12)"    -> 12
--     Caelové  (h42, b201)  "Barbar (12)"    -> 12
--     Furan    (h47, b235)  "Kroll (11)"     -> 11
--     Gnollové (h51, b280)  "člověk 11"      -> 11
--     Kobold   (h56, b307)  "Barbar (12)"    -> 12
--     Magiliw  (h61, b334)  "13"             -> 13
--     Naro´shai(h66, b364)  "Barbar (12)"    -> 12
--     Skaven   (h72, b398)  "Barbar (12)"    -> 12
--     Tauren   (h77, b424)  "Barbar (12)"    -> 12
--     Tiefling (h86, b463)  "člověk (11)"    -> 11
--   (Caelové mají navíc vlastní pravidla pro let/plachtění — to je jiná
--   mechanika než pozemní pohyblivost a tahle migrace se jí netýká.)
--
-- Trpasličí klany (Otho, Menwar, ...) a "Kroll — domácí rozšíření" vlastní
-- řádek v textu nemají (jsou to děti core ras přes rodic_rasa_id, stejně
-- jako u rasa_bonusy_vlastnosti) — hodnotu NEduplikuju sem, dědí ji za
-- běhu helper dracak_vtt_rasovy_zaklad_pohyblivosti() od rodiče.
--
-- Samé UPDATE (čisté DML), idempotentní — projde automaticky přes
-- migrate.php i pod web účtem. UPDATE na jméno, které v DB není (např.
-- Hobit na instanci, kam se 0019 nedostala), je no-op, ne chyba.

UPDATE rasy SET pohyblivost_zaklad = 10 WHERE nazev = 'Hobit';
UPDATE rasy SET pohyblivost_zaklad = 9  WHERE nazev = 'Kudůk';
UPDATE rasy SET pohyblivost_zaklad = 8  WHERE nazev = 'Trpaslík';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Elf';
UPDATE rasy SET pohyblivost_zaklad = 11 WHERE nazev = 'Člověk';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Barbar';
UPDATE rasy SET pohyblivost_zaklad = 11 WHERE nazev = 'Kroll';

UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Barnové';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Caelové';
UPDATE rasy SET pohyblivost_zaklad = 11 WHERE nazev = 'Furan';
UPDATE rasy SET pohyblivost_zaklad = 11 WHERE nazev = 'Gnollové';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Kobold';
UPDATE rasy SET pohyblivost_zaklad = 13 WHERE nazev = 'Magiliw';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Naro´shai';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Skaven';
UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Tauren';
UPDATE rasy SET pohyblivost_zaklad = 11 WHERE nazev = 'Tiefling';
