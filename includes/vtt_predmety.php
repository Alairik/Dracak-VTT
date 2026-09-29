<?php
declare(strict_types=1);

// Sdílené pomůcky pro inventář/loot (viz database/migrations/0041_vtt_inventar_a_loot.sql).
// Samostatný soubor mimo includes/vtt.php záměrně — ten souběžně upravuje
// jiná práce, tohle ať se s ní nekříží.

require_once __DIR__ . '/vtt.php';

// Tabulky/sloupce podle typu položky — použito jak pro postava_* (typovaný
// inventář), tak pro nestvura_instance_vybava (polymorfní výjimka) a pro
// hledání efektů přes *_efekty join tabulky. Jedno místo, ať se nejmenuje
// pokaždé jinde.
const DRACAK_VTT_POLOZKA_TABULKY = [
    'predmet' => ['inventar' => 'postava_predmety', 'katalog' => 'predmety', 'fk' => 'predmet_id', 'efekty' => 'predmet_efekty'],
    'lektvar' => ['inventar' => 'postava_lektvary', 'katalog' => 'lektvary', 'fk' => 'lektvar_id', 'efekty' => 'lektvar_efekty'],
    'kouzlo'  => ['inventar' => 'postava_zna_kouzlo', 'katalog' => 'kouzla', 'fk' => 'kouzlo_id', 'efekty' => 'kouzlo_efekty'],
];

function dracak_vtt_polozka_config(string $typPolozky): ?array
{
    return DRACAK_VTT_POLOZKA_TABULKY[$typPolozky] ?? null;
}

// Normalizovaný inventář entity. Postava má persistentní inventář rozdělený
// do 3 typovaných tabulek (napříč světy); nestvura_instance jen lehkou
// výbavu jedné bojové instance v nestvura_instance_vybava (viz komentář
// v migraci 0041). Návrat je sjednocený tvar bez ohledu na zdroj:
// [{typ_polozky, polozka_id, nazev, mnozstvi, inventar_id}, ...]
function dracak_vtt_inventar(string $typEntity, int $entitaId): array
{
    $pdo = dracak_db();
    $vysledek = [];

    if ($typEntity === 'postava') {
        foreach (DRACAK_VTT_POLOZKA_TABULKY as $typPolozky => $cfg) {
            // Kouzlo se nezná v "kusech" — mnozstvi vždy 1, aby ho šlo
            // v UI vypsat stejně jako předměty/lektvary.
            $mnozstviSql = $typPolozky === 'kouzlo' ? '1' : 'i.mnozstvi';
            $stmt = $pdo->prepare(
                "SELECT i.id AS inventar_id, k.id AS polozka_id, k.nazev, $mnozstviSql AS mnozstvi
                 FROM {$cfg['inventar']} i JOIN {$cfg['katalog']} k ON k.id = i.{$cfg['fk']}
                 WHERE i.postava_id = ? ORDER BY k.nazev"
            );
            $stmt->execute([$entitaId]);
            foreach ($stmt->fetchAll() as $row) {
                $vysledek[] = [
                    'typ_polozky' => $typPolozky,
                    'polozka_id' => (int)$row['polozka_id'],
                    'nazev' => $row['nazev'],
                    'mnozstvi' => (int)$row['mnozstvi'],
                    'inventar_id' => (int)$row['inventar_id'],
                ];
            }
        }
        return $vysledek;
    }

    // nestvura_instance: jedna polymorfní tabulka, dohledej název podle
    // typ_polozky v samostatném dotazu na příslušný katalog (nejde JOINovat
    // tři různé tabulky napříč řádky jedním dotazem).
    $stmt = $pdo->prepare(
        'SELECT id, typ_polozky, polozka_id, mnozstvi FROM nestvura_instance_vybava WHERE nestvura_instance_id = ?'
    );
    $stmt->execute([$entitaId]);
    foreach ($stmt->fetchAll() as $row) {
        $cfg = dracak_vtt_polozka_config($row['typ_polozky']);
        if (!$cfg) {
            continue;
        }
        $nazevStmt = $pdo->prepare("SELECT nazev FROM {$cfg['katalog']} WHERE id = ?");
        $nazevStmt->execute([$row['polozka_id']]);
        $nazev = $nazevStmt->fetchColumn();
        if ($nazev === false) {
            continue;
        }
        $vysledek[] = [
            'typ_polozky' => $row['typ_polozky'],
            'polozka_id' => (int)$row['polozka_id'],
            'nazev' => $nazev,
            'mnozstvi' => (int)$row['mnozstvi'],
            'inventar_id' => (int)$row['id'],
        ];
    }
    return $vysledek;
}

// Katalogový záznam položky (predmety/lektvary/kouzla), i s kostkami pokud
// je má (pocet_kostek/typ_kostky/pevny_bonus — kouzla i lektvary, ne
// predmety, viz migrace 0003_kostky_a_bonusy.sql).
function dracak_vtt_polozka_katalog(string $typPolozky, int $polozkaId): ?array
{
    $cfg = dracak_vtt_polozka_config($typPolozky);
    if (!$cfg) {
        return null;
    }
    $stmt = dracak_db()->prepare("SELECT * FROM {$cfg['katalog']} WHERE id = ?");
    $stmt->execute([$polozkaId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Efekty napojené na položku přes *_efekty join tabulku (predmet_efekty/
// lektvar_efekty/kouzlo_efekty). Prázdné pole = položka je čistě popisná,
// nic se automaticky neaplikuje (viz CLAUDE.md).
function dracak_vtt_polozka_efekty(string $typPolozky, int $polozkaId): array
{
    $cfg = dracak_vtt_polozka_config($typPolozky);
    if (!$cfg) {
        return [];
    }
    $stmt = dracak_db()->prepare(
        "SELECT e.* FROM {$cfg['efekty']} je JOIN efekty e ON e.id = je.efekt_id WHERE je.{$cfg['fk']} = ?"
    );
    $stmt->execute([$polozkaId]);
    return $stmt->fetchAll();
}

// typ_kostky v katalogu je ENUM('k3',...,'k100') jako text — dracak_vtt_hod_kostkou()
// čeká typ kostky jako int. Vytáhne číslo z "k6" -> 6.
function dracak_vtt_typ_kostky_na_int(?string $typKostky): int
{
    return $typKostky !== null ? (int)ltrim($typKostky, 'k') : 0;
}

// Podle konvence zavedené v database/migrations/0022_kouzla_efekty_davka1.sql:
// obecné, znovupoužitelné efekty "Poškození" (cil='životy') a "Léčení"
// (cil='životy') jsou jediné dva efekty, co mechanicky strhávají/přidávají
// život — konkrétní kostky bere engine vždy z vlastního záznamu položky
// (pocet_kostek/typ_kostky/pevny_bonus), NIKDY z efekt.hodnota_vzorec (to
// je jen volný popisný text pro člověka, ne strukturovaná data). Vrací
// +1/-1 jako znaménko delty života, nebo null když efekt není o životech.
function dracak_vtt_hp_znamenko(array $efekt): ?int
{
    if (($efekt['cil'] ?? '') !== 'životy') {
        return null;
    }
    if ($efekt['nazev'] === 'Poškození') {
        return -1;
    }
    if ($efekt['nazev'] === 'Léčení') {
        return 1;
    }
    return null;
}

// Odvodí svet_id (+ mapa_id, pokud existuje) pro entitu — postava ho má
// přímo ve sloupci, nestvura_instance jen mapa_id a svet_id se dotáhne přes
// mapy. Používá se v pouzij_predmet.php/loot.php pro kontrolu přístupu
// (dracak_vtt_svet_access) a zápis do logu (dracak_vtt_log_event).
function dracak_vtt_entity_svet_mapa(string $typEntity, array $entity): array
{
    if ($typEntity === 'postava') {
        return ['svet_id' => (int)$entity['svet_id'], 'mapa_id' => null];
    }
    $stmt = dracak_db()->prepare('SELECT svet_id FROM mapy WHERE id = ?');
    $stmt->execute([(int)$entity['mapa_id']]);
    $svetId = $stmt->fetchColumn();
    return ['svet_id' => $svetId !== false ? (int)$svetId : 0, 'mapa_id' => (int)$entity['mapa_id']];
}

// Uloží novou hodnotu aktualni_hp entity se stejným ořezem 0..max_hp jako
// hra/api/hp_uprava.php (max_hp = 0 znamená "neznámé", tam se neořezává
// nahoru). Vrací [nove_hp, max_hp].
function dracak_vtt_aplikuj_hp_deltu(string $typEntity, array $entity, int $delta): array
{
    $maxHp = (int)$entity['max_hp'];
    $noveHp = max(0, (int)$entity['aktualni_hp'] + $delta);
    if ($maxHp > 0) {
        $noveHp = min($maxHp, $noveHp);
    }
    $table = $typEntity === 'nestvura_instance' ? 'nestvura_instance' : 'postavy';
    $upd = dracak_db()->prepare("UPDATE $table SET aktualni_hp = ? WHERE id = ?");
    $upd->execute([$noveHp, (int)$entity['id']]);
    return [$noveHp, $maxHp];
}
