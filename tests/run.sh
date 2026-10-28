#!/bin/bash
# Spustí testy proti lokální MariaDB a vestavěnému PHP serveru. Před každou sadou vrátí testovací data do výchozího stavu
# (tests/fixture/seed.sql), takže pořadí sad nehraje roli.
#
#   tests/run.sh                 všechny sady
#   tests/run.sh souboj_api ...  jen vybrané (jména souborů z tests/e2e bez .js, nebo unit/<jméno>)
#
# Předpoklady viz tests/README.md. Proměnné: DB (výchozí dracak), BASE (výchozí http://127.0.0.1:8099), OUT (screenshoty),
# DOCROOT (kořen webu pro php -S, výchozí repo; jiný jen při zkoušce na kopii s vlastním config.php).
set -uo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
export DB=${DB:-dracak}
export BASE=${BASE:-http://127.0.0.1:8099}
export OUT=${OUT:-/tmp/dracak-tests}
DOCROOT=${DOCROOT:-$ROOT}
mkdir -p "$OUT"
PORT=${BASE##*:}

E2E_VYCHOZI="ini_v2_api souboj_api efekty_api efekty_kolo efekty_kouzla efekty_ui tasit_zdarma_api zbrane_api zbrane_ini_api zbrane_ui zbrane_mapa hex_api hex_multi_api review2_api vyzva_api denik_v2 denik_dock inv_ui e2e_hra druzina_hrac druzina_pj log_ui zaloha_ui souboj_ui hex_tah_ui"
UNIT_VYCHOZI="log_unit migrace_unit zaloha_test"

# Stav před jednotlivými sadami navíc k resetu (co sada očekává od postavy, ale seed to nemá).
predem() {
  case "$1" in
    vyzva_api) echo "update postavy set drzena_zbran_id=2 where id=2" ;;
  esac
}

if ! curl -s -o /dev/null "$BASE/index.php"; then
  (cd "$DOCROOT" && PHP_CLI_SERVER_WORKERS=4 nohup php -S "127.0.0.1:$PORT" -t . >"$OUT/php-server.log" 2>&1 &)
  sleep 1
fi

SUITES=("$@")
if [ ${#SUITES[@]} -eq 0 ]; then
  for s in $UNIT_VYCHOZI; do SUITES+=("unit/$s"); done
  for s in $E2E_VYCHOZI; do SUITES+=("$s"); done
fi

selhalo=()
for s in "${SUITES[@]}"; do
  mysql --default-character-set=utf8mb4 "$DB" < "$ROOT/tests/fixture/seed.sql"
  sql=$(predem "$s"); [ -n "$sql" ] && mysql --default-character-set=utf8mb4 "$DB" -e "$sql"
  if [[ "$s" == unit/* ]]; then
    vystup=$(cd "$ROOT" && timeout 300 php "tests/unit/${s#unit/}.php" 2>&1); kod=$?
  else
    vystup=$(cd "$ROOT/tests/e2e" && timeout 400 node "$s.js" 2>&1); kod=$?
  fi
  if [ $kod -ne 0 ] || echo "$vystup" | grep -qE '^(CHYBA|SELHALO|PÁD|FAIL)|CHYB: [1-9]'; then
    echo "SELHALO  $s (kód $kod)"
    echo "$vystup" | grep -E '^(CHYBA|SELHALO|PÁD|FAIL)|CHYB: [1-9]' | head -8 | sed 's/^/           /'
    selhalo+=("$s")
  else
    echo "ok       $s"
  fi
done
echo
if [ ${#selhalo[@]} -eq 0 ]; then echo "VŠE PROŠLO (${#SUITES[@]} sad)"; else echo "SELHALO ${#selhalo[@]} z ${#SUITES[@]}: ${selhalo[*]}"; exit 1; fi
