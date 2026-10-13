-- Body únavy postavy ve VTT. Na rozdíl od pohyblivosti (vždy odvoditelná
-- z rasy + OBR + SIL + nákladu, proto se NEUKLÁDÁ — uložená hodnota by
-- se snadno rozešla se zdrojem) je únava nahromaděný stav v čase, stejně
-- jako aktualni_hp/aktualni_magenergie: nejde ji dopočítat zpětně z
-- ničeho jiného, musí se držet na postavě.
--
-- Stupeň únavy (unavená / vyčerpaná / úplně vyčerpaná / vysílená) se
-- naopak počítá za běhu z `unava` a Odolnosti (vytrvalost = odolnost,
-- content/pravidla-hrac.html h1634 b10869–b10870) — viz
-- dracak_vtt_postava_unava_stupen() v includes/vtt_pohyblivost.php.
--
-- Jeden součet bodů; "stínová" únava (h1634 b10869 — poklus, běh,
-- sprint, boj, psychická práce; po směně nečinnosti mizí na čtvrtinu)
-- se zatím nerozlišuje. NOT NULL DEFAULT 0 = postava bez únavy (na rozdíl
-- od nullable atributů/magenergie 0 tu je platná a výchozí hodnota, ne
-- "nevyplněno").
--
-- ALTER ADD COLUMN = DDL, web účet (DML-only na Wedosu) ho neprovede,
-- musí se spustit ručně přes phpMyAdmin (admin účet). migrate.php pak
-- soubor při dalším deployi sám zapíše do migrace_log (rozpozná, že
-- sloupec už existuje) — viz CLAUDE.md.

ALTER TABLE postavy
    ADD COLUMN unava SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_magenergie;
