-- Pole postavy pro Osobní deník (docs/zadani-osobni-denik-design.md,
-- kolonky z content/pravidla-hrac.html h1690 "Osobní deník"):
--
--   zkusenost              Zkušenost (zt) — "na počátku nulová" (h1690);
--                          přiděluje PJ (h109). Přestup na vyšší úroveň
--                          podle zkusenostni_urovne není automatický (h110).
--   penize_zl/_st/_md      Peníze "zvlášť zl/st/md" (h1690). Zvlášť, protože
--                          každá mince váží 1 mn bez ohledu na kov (h3
--                          "Míry a váhy") — počet mincí = váha, 1 zl = 10 st
--                          = 100 md je jen cena.
--   velikost               Velikost A0–E (h1690: A hobit/kudůk/trpaslík,
--                          B elf/barbar/člověk, C kroll). Na postavě, ne na
--                          rase: homebrew rasy mají v textu rozsah
--                          ("Velikost B-C", "A-B"), takže jedna hodnota na
--                          rasu by byla vymyšlená. NULL = dopočítá se z rasy
--                          tam, kde ji pravidla jednoznačně určují.
--   zbroj_id, stit_id      Co postava nosí na těle / v ruce (předmět z
--                          katalogu). Deník z toho počítá obranné číslo
--                          (h1611: OČ = KZ zbroje + bonus za obratnost;
--                          "v šatech s KZ 1", h1682).
--   drzena_zbran_id        Zbraň, kterou postava právě drží — pro ni ukazuje
--                          deník zvýrazněné ÚČ (h1610).
--   puvod, osobnost        Soukromé texty (h107: původ, povahové rysy,
--                          zvyky, sny a cíle — "ostatním hráčům hned
--                          neříkej").
--
-- Cizí klíče na predmety s ON DELETE SET NULL — smazání předmětu z katalogu
-- nesmí zablokovat ani zničit postavu.
--
-- ALTER = DDL, web účet (DML-only na Wedosu) ho neprovede, musí se spustit
-- ručně přes phpMyAdmin (admin účet); migrate.php ho pak při dalším
-- deployi sám zapíše do migrace_log (pozná, že sloupec už existuje). Kód
-- funguje i před spuštěním (chybějící sloupce = pole se nezobrazí).

ALTER TABLE postavy
    ADD COLUMN zkusenost INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN penize_zl INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN penize_st INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN penize_md INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN velikost VARCHAR(2) NULL,
    ADD COLUMN zbroj_id INT UNSIGNED NULL,
    ADD COLUMN stit_id INT UNSIGNED NULL,
    ADD COLUMN drzena_zbran_id INT UNSIGNED NULL,
    ADD COLUMN puvod TEXT NULL,
    ADD COLUMN osobnost TEXT NULL,
    ADD CONSTRAINT fk_postavy_zbroj FOREIGN KEY (zbroj_id) REFERENCES predmety (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_postavy_stit FOREIGN KEY (stit_id) REFERENCES predmety (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_postavy_drzena_zbran FOREIGN KEY (drzena_zbran_id) REFERENCES predmety (id) ON DELETE SET NULL;
