-- Iniciativa — doplnění řádků „Tabulka vícenásobných útoků“ (content/pravidla-bestiar.html, h2392, b17714 a dál).
--
-- V rozšířeném soubojovém systému se vícenásobné útoky řeší bonusem k iniciativě (víc akcí, každý útok = 1 akce):
-- 3 za 2 kola +3, 2 za kolo +6, 5 za 2 kola +9, 3 za kolo +12, 7 za 2 kola +15, 4 za kolo +18.
-- V tabulce iniciativa_bonusy (migrace 0045) byly jen první dva řádky; zbylé čtyři se přidávají, ať se dají zaškrtnout
-- v okně hodu na iniciativu. Jen INSERT, jde spustit opakovaně (řádek, který už existuje, se nepřidá znovu).

INSERT INTO iniciativa_bonusy (popis, bonus)
SELECT t.popis, t.bonus FROM (
  SELECT '5 útoků za 2 kola' AS popis, 9 AS bonus
  UNION ALL SELECT '3 útoky za 1 kolo', 12
  UNION ALL SELECT '7 útoků za 2 kola', 15
  UNION ALL SELECT '4 útoky za 1 kolo', 18
) t
WHERE NOT EXISTS (SELECT 1 FROM iniciativa_bonusy b WHERE b.popis = t.popis);
