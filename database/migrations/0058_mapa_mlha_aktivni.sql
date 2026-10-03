-- PJ potřebuje mlhu vypnout pro mapy, kde nedává smysl (město, sociální
-- scéna) — dosud byla mlha pro hráče vždycky aktivní bez možnosti
-- vypnutí. DEFAULT 1 = zachovává dosavadní chování pro všechny
-- existující mapy (mlha aktivní), nic se nezmění, dokud ji PJ sám
-- nevypne přes nové UI (popover "Mlha" v hra/mapa.php).
--
-- ALTER ADD COLUMN = DDL, web účet (DML-only na Wedosu) ho neprovede,
-- musí se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log — viz CLAUDE.md.

ALTER TABLE mapy
    ADD COLUMN mlha_aktivni TINYINT(1) NOT NULL DEFAULT 1 AFTER grid_typ;
