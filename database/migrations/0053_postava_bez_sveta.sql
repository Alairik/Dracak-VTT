-- Postava dosud šla založit jen rovnou uvnitř konkrétního světa
-- (hra/svet.php / hra/postava_nova.php?id=X), protože postavy.svet_id
-- bylo NOT NULL. Hráč ale potřebuje mít možnost připravit si postavu
-- (klidně i víc najednou, řádově desítky), aniž by hned věděl/a, do
-- jakého světa půjde — a ne každá postava nakonec do světa přiřazená
-- bude. Proto svet_id jde na NULL = "zatím bez světa", přiřadí se
-- později (viz hra/postavy_moje.php).
--
-- Všechny ostatní dotazy nad `postavy` už filtrují WHERE svet_id = ?
-- (mapa.php, api/token_pridat.php, api/loot.php...) — NULL řádek tam
-- přirozeně nikdy nezapadne, takže nepřiřazená postava se nikde
-- "necvakne" omylem do cizí mapy/světa.
--
-- ALTER MODIFY COLUMN = DDL, web účet (DML-only na Wedosu) ho neprovede,
-- musí se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log — viz CLAUDE.md.

ALTER TABLE postavy MODIFY COLUMN svet_id INT UNSIGNED NULL;
