-- Doplnění fyzických komponent pro Chodcova "kouzla pocestných" (h?,
-- kapitola u kouzla "Rozdělej oheň") — jediný jasně ohraničený blok
-- kouzel s konkrétní spotřebovávanou/nošenou fyzickou pomůckou.
-- Založeny chybějící suroviny v predmety (žádná z nich tam dosud
-- nebyla, ověřeno), napojeno přes novou kouzlo_suroviny (0034).
--
-- Kouzla bez fyzické pomůcky (Hlídka, Maskování, Tichá chůze — "Pomůcky:
-- žádné") a s libovolnou/neurčitou pomůckou (Matení pachů, Matení stop
-- — "Pomůcky: různé") záměrně nedostávají žádnou vazbu — vynucovat
-- konkrétní předmět tam, kde ho text sám nejmenuje, by bylo vymýšlení.
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php, ALE
-- až po ručním spuštění 0034 (CREATE TABLE kouzlo_suroviny).

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Svíce z pravého včelího vosku', 'surovina', 0.20, 'Součást pomůcek ke kouzlu Ochrana před bouří — nesmí být náhražka, chodec ji při seslání rozsvítí. Lze koupit.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Svíce z pravého včelího vosku');

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Pouštní písek', 'surovina', 0.10, 'Součást pomůcek ke kouzlům Ochrana před bouří a Ochrana před deštěm — hrstka/špetka se při seslání vyhodí do vzduchu. Chodec si ho musí opatřit v poušti nebo dostat darem.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pouštní písek');

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Medvědí srst', 'surovina', 0.05, 'Součást pomůcek ke kouzlu Ochrana před vlky — trocha srsti z medvěda, kterého chodec sám ulovil nebo přemluvil, aby mu ji poskytl.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Medvědí srst');

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Oblázek z teplých moří', 'surovina', 0.05, 'Součást pomůcek ke kouzlu Ochrana před zimou — z teplých pláží jižních moří, chodec ho terči vtiskne do dlaně.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Oblázek z teplých moří');

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Dubový list (darovaný druidem)', 'surovina', 0.01, 'Součást pomůcek ke kouzlu Přivolej druida — chodec ho při seslání rozmnoží mezi prsty. Jednorázový, opakovaně nepoužitelný, musí ho darovat druid.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Dubový list (darovaný druidem)');

INSERT INTO predmety (nazev, typ, vaha, popis)
SELECT 'Křemen (na rozdělání ohně)', 'surovina', 0.30, 'Součást pomůcek ke kouzlu Rozdělej oheň — alternativa k suchému dřevu, otloukáním se rozdělá oheň. Použitelný opakovaně.'
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Křemen (na rozdělání ohně)');

-- Napojení jednotlivých kouzel na jejich pomůcky
INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 322, id, '1 ks' FROM predmety WHERE nazev = 'Svíce z pravého včelího vosku';
INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 322, id, 'hrstička' FROM predmety WHERE nazev = 'Pouštní písek';

INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 323, id, 'špetka' FROM predmety WHERE nazev = 'Pouštní písek';

INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 324, id, 'trocha' FROM predmety WHERE nazev = 'Medvědí srst';

INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 325, id, '1 ks' FROM predmety WHERE nazev = 'Oblázek z teplých moří';

INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 326, id, '1 ks (spotřebuje se)' FROM predmety WHERE nazev = 'Dubový list (darovaný druidem)';

INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 327, id, '2 ks (nebo křemeny, opakovaně použitelné)' FROM predmety WHERE id = 209;
INSERT IGNORE INTO kouzlo_suroviny (kouzlo_id, predmet_id, mnozstvi)
SELECT 327, id, '2 ks (nebo dřeva, opakovaně použitelné)' FROM predmety WHERE nazev = 'Křemen (na rozdělání ohně)';
