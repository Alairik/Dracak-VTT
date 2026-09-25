-- Doplnění kostek u dovedností/schopností podle popisu (stejný přístup
-- jako migrace 0012 u kouzel) — jen případy, kde je zápis kostky přímo
-- v textu ("1k6", "2k10"...), žádný převod z rozsahu (u schopností
-- byl rozsahový převod nespolehlivý, viz vyřazené případy v konverzaci —
-- např. 'Líčení pastí' popisuje víc různých pastí najednou, ne jednu
-- vlastní kostku dovednosti). Trojitá pojistka: id + nazev + IS NULL.

UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k10', pevny_bonus=0, vicenasobne=0 WHERE id=4 AND nazev='Přírůstek životů (Válečník)' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k8', pevny_bonus=0, vicenasobne=0 WHERE id=32 AND nazev='Vržení' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=45 AND nazev='Zuřivý boj' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=51 AND nazev='Trollobijecký útok' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=2, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=61 AND nazev='Odvracení zvířat' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k10', pevny_bonus=0, vicenasobne=0 WHERE id=66 AND nazev='Posílení kouzel (hvozd)' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=5, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=80 AND nazev='Zardoušení (pes)' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=91 AND nazev='Podrobování' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=92 AND nazev='Mentální souboj' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=10, typ_kostky='k10', pevny_bonus=5, vicenasobne=0 WHERE id=171 AND nazev='Jesle' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k4', pevny_bonus=0, vicenasobne=0 WHERE id=179 AND nazev='Kapacitní drahokamy' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=181 AND nazev='Posílený metabolismus' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=186 AND nazev='Demonikon (Stopař)' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=194 AND nazev='Trollí metabolizmus' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=4, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=200 AND nazev='Zocelení skrze přesvědčení' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=204 AND nazev='Doplnění víry (Erythen)' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=209 AND nazev='Osm bran' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=238 AND nazev='Silný hod' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=240 AND nazev='Skok o tyči' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k10', pevny_bonus=0, vicenasobne=0 WHERE id=253 AND nazev='Jízda na lyžích' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=260 AND nazev='Ošetřování zvířat' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=2, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=262 AND nazev='Rybaření' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k4', pevny_bonus=0, vicenasobne=0 WHERE id=265 AND nazev='Střelba do dálky' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=272 AND nazev='Udržování ohně' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k10', pevny_bonus=0, vicenasobne=0 WHERE id=277 AND nazev='Držení pozice' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k6', pevny_bonus=0, vicenasobne=0 WHERE id=285 AND nazev='Plivání ohně' AND pocet_kostek IS NULL;
UPDATE zvlastni_schopnosti SET pocet_kostek=1, typ_kostky='k4', pevny_bonus=0, vicenasobne=0 WHERE id=305 AND nazev='Maskování v přírodě, improvizované úkryty' AND pocet_kostek IS NULL;
