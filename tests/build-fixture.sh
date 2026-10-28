#!/bin/bash
# Postaví testovací databázi z repa: pravidla (database/drd-db-full-v1.sql) → účty a role → migrace → testovací účty → testovací data.
# Jen pro LOKÁLNÍ MariaDB (root bez hesla, cloud session / vývojový počítač). Nikdy ne na ostrý hosting.
#
#   tests/build-fixture.sh [databáze] [--force] [--config]
#
#   databáze   výchozí „dracak“ (tak se jmenuje, kam míří tests/e2e/*.js; jiné jméno → proměnná DB=… při spuštění testů)
#   --force    smaže existující databázi stejného jména
#   --config   založí lokální config.php (jen když ještě není) s náhodným heslem DB uživatele — heslo se nikam nezapisuje kromě něj
#
# Testovací účty: test@local / test1234 (admin), hrac@local / hrac1234 (hráč). Hesla jsou jen lokální a hashe se generují při stavbě.
set -euo pipefail
DB=dracak
FORCE=0
CONFIG=0
for a in "$@"; do
  case "$a" in
    --force) FORCE=1 ;;
    --config) CONFIG=1 ;;
    *) DB="$a" ;;
  esac
done
ROOT=$(cd "$(dirname "$0")/.." && pwd)

if mysql -N -e "select 1 from information_schema.schemata where schema_name='$DB'" | grep -q 1 && [ "$FORCE" != 1 ]; then
  echo "Databáze $DB už existuje. Přidej --force, pokud ji smím smazat a postavit znovu." >&2
  exit 1
fi
mysql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
M="mysql --default-character-set=utf8mb4 $DB"

$M < "$ROOT/database/drd-db-full-v1.sql"
$M < "$ROOT/database/0002_ucty_a_role.sql"
for f in $(ls "$ROOT"/database/migrations/*.sql | sort); do
  $M < "$f" || { echo "Migrace $f selhala." >&2; exit 1; }
done

H_ADMIN=$(php -r 'echo password_hash("test1234", PASSWORD_DEFAULT);')
H_HRAC=$(php -r 'echo password_hash("hrac1234", PASSWORD_DEFAULT);')
$M -e "INSERT INTO ucty (id, email, heslo_hash, jmeno, role) VALUES (1, 'test@local', '$H_ADMIN', 'Tester', 'admin'), (3, 'hrac@local', '$H_HRAC', 'Hráč Test', 'hrac')"
$M < "$ROOT/tests/fixture/seed.sql"

# Obrázky map z testovacích dat (jednobarevné PNG, jen aby měla mapa podklad; v repu nejsou, viz .gitignore).
python3 "$ROOT/tests/fixture/make-images.py" "$ROOT/uploads/mapy"

if [ "$CONFIG" = 1 ] && [ ! -f "$ROOT/config.php" ]; then
  PASS=$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')
  mysql -e "CREATE USER IF NOT EXISTS '$DB'@'127.0.0.1' IDENTIFIED BY '$PASS'; ALTER USER '$DB'@'127.0.0.1' IDENTIFIED BY '$PASS'; GRANT ALL ON \`$DB\`.* TO '$DB'@'127.0.0.1'; FLUSH PRIVILEGES"
  cat > "$ROOT/config.php" <<PHP
<?php
return ['db_host' => '127.0.0.1', 'db_name' => '$DB', 'db_user' => '$DB', 'db_pass' => '$PASS'];
PHP
  echo "config.php založen (je v .gitignore)."
fi
echo "Databáze $DB je připravená: $(mysql -N $DB -e 'select count(*) from information_schema.tables where table_schema=database()') tabulek, účty test@local a hrac@local."
