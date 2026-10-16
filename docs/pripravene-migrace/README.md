# Připravené migrace, které ještě NESMÍ do `database/migrations/`

Soubory tu čekají na potvrzení, že uživatel provedl ruční SQL (`docs/sql-k-rucnimu-spusteni.sql`). `migrate.php` tuhle složku
nečte, takže nic nespustí. Jakmile uživatel napíše, že SQL proběhlo:

1. přesunout (`git mv`) `0091_*.sql` a `0092_*.sql` do `database/migrations/`,
2. vyprázdnit `docs/sql-k-rucnimu-spusteni.sql` (jen komentář „nic nečeká“),
3. pushnout na `main` — deploy je spustí přes `migrate.php` (0091 pod DML-only účtem jen „přeskočí“, protože sloupce a tabulka
   už existují; 0092 je čisté DML a proběhne sama),
4. upravit `docs/stav-projektu.md` (sekce „Čeká na ruční spuštění“ a „Efekty“) a smazat tuhle složku.

Proč dvě fáze: kdyby `0092` (data efektů) dorazila na produkci dřív než sloupce, spadla by a `migrate.php` by zastavil
všechny migrace za ní.

- `0091_efekty_mechanika_ddl.sql` — zrcadlo ručního SQL (sloupce efektů, tabulka `efekt_slozky`, sloupce nasazených efektů).
- `0092_efekty_mechanika_data.sql` — stav a složky mechaniky efektů, nové efekty (Rychlost – kouzlo, Protoplazma, Zastrašen –
  totální úspěch), vazby kouzel Rychlost a Protoplazma, úklid 6 chybných vazeb kouzlo → Ochromení.

Ověřeno na kopii produkce s DML-only účtem: ruční SQL (admin), potom `migrate.php` → 0091 přeskočena, 0092 provedena.
