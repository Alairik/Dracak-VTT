# Připravené migrace, které ještě NESMÍ do `database/migrations/`

Soubory tu čekají na potvrzení, že uživatel provedl ruční SQL (`docs/sql-k-rucnimu-spusteni.sql`). `migrate.php` tuhle složku
nečte, takže nic nespustí. Jakmile uživatel napíše, že SQL proběhlo:

1. přesunout (`git mv`) soubory odsud do `database/migrations/`,
2. vyprázdnit `docs/sql-k-rucnimu-spusteni.sql` (jen komentář „nic nečeká“),
3. pushnout na `main` — deploy je spustí přes `migrate.php` (DDL zrcadlo pod DML-only účtem jen „přeskočí“, protože sloupce
   už existují),
4. upravit `docs/stav-projektu.md` (sekce „Čeká na ruční spuštění“) a smazat tuhle složku.

- `0093_iniciativa_v2_ddl.sql` — zrcadlo ručního SQL iniciativy v2 (`kolo_ucastnik.utoku_za_2_kola`, `kolo_iniciativa.zavazek`,
  `zavazek_akci`, `zavazek_hotovo`, `akce_hotovo`). Samé ALTER, žádná data.

Ověřeno na kopii produkce: ruční SQL (admin) dvakrát po sobě, potom `migrate.php` pod DML-only účtem.
