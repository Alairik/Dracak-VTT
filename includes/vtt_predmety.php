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

// Vytáhne číselný dosah v SÁZÍCH z volného textu kouzla.dosah/predmety.dosah
// ("5 sáhů", "1 sáh", "1,5 sáhu", "0", "10") — ALE JEN když je CELÝ
// (ořezaný) text přesně tenhle tvar: číslo (desetinná čárka i tečka —
// reálná data v migraci 0006 píšou čárku, "1,5 sáhu") + volitelně
// "sáh"/"sáhy"/"sáhů"/"sáhu", nic jiného.
//
// Ověřeno proti SKUTEČNÝM datům (SELECT DISTINCT dosah FROM kouzla, 699
// řádků reálného obsahu z database/drd-db-full-v1.sql — testovací DB
// dracak_test má jen pár fixture řádků, nereprezentativní): kromě čistých
// čísel/sáhů tam je spousta NEPARSOVATELNÝCH tvarů — rozsahy ("1-30
// sáhů", "60-600 sáhů", "17/34 sáhů"), vzorce se škálováním ("2 sáhy za
// úroveň kouzelníka", "10 sáhů + 1 sáh za každou úroveň kouzelníka"),
// jiné jednotky ("5 mil", "1 míle"), slovní dosahy ("dotek", "dotyk",
// "doslech", "dohled", "poloměr Charizmatu") a popisné věty ("musí mít
// vidět do očí", "viz níže"). Všechny tyhle NEPARSUJEME — vracíme null a
// kontrola dosahu se v hra/api/pouzij_predmet.php pro ně mlčky
// přeskočí (viz CLAUDE.md — "nehádat herní mechaniku", radši žádná
// kontrola než špatně uhodnuté číslo). "0" je přitom validní a
// smysluplné: h1612 v content/pravidla-hrac.html definuje "dosah... 0 =
// jen na sebe", takže se parsuje jako 0 sáhů (cíl jiný než uživatel pak
// logicky vždy spadne mimo dosah, přesně jak má).
function dracak_vtt_parsuj_dosah_sahy(?string $text): ?float
{
    if ($text === null) {
        return null;
    }
    $t = trim($text);
    if ($t === '') {
        return null;
    }
    if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:sáh[yůu]?)?$/u', $t, $m)) {
        return null;
    }
    return (float)str_replace(',', '.', $m[1]);
}

// Dosah POLOŽKY v sáhách, nebo null (lektvar nemá sloupec dosah vůbec —
// lektvary.dosah v DB neexistuje; nebo text není parsovatelný, viz výš).
// Střelné/vrhací zbraně: dostrel_efektivni/dostrel_maximalni jsou v DB
// už ČÍSELNÉ sloupce (SMALLINT, migrace 0006_zraneni_dosah_zbrani.sql),
// žádné parsování textu netřeba — berou se jako tvrdá hranice dosahu
// místo sloupce dosah (ten je podle schématu "dosah NA BLÍZKO", u
// střelných/vrhacích zbraní se nepoužívá). dostrel_maximalni (nad
// efektivní = postih -5 k útoku, viz komentář u sloupce) je přednější,
// protože je to skutečná fyzická hranice, kam zbraň vůbec dostřelí;
// postih za překročení efektivního dostřelu tahle kontrola neřeší (to
// je otázka úspěšnosti zásahu, ne legality použití).
//
// POZOR: v produkční DB (database/drd-db-full-v1.sql) i v testovací
// dracak_test jsou VŠECHNY řádky predmety.dosah/zraneni/
// dostrel_efektivni/dostrel_maximalni dnes NULL — migrace 0006 sloupce
// jen PŘIDALA, žádná další migrace je nenaplnila daty. Tahle funkce pro
// predmety proto v praxi dnes vždy vrátí null (kontrola se přeskočí) —
// až se zbraním dosah/dostřel doplní, začne fungovat bez další úpravy.
function dracak_vtt_polozka_dosah_sahy(string $typPolozky, array $katalog): ?float
{
    if ($typPolozky === 'kouzlo') {
        return dracak_vtt_parsuj_dosah_sahy($katalog['dosah'] ?? null);
    }
    if ($typPolozky === 'predmet') {
        if (!empty($katalog['dostrel_maximalni'])) {
            return (float)$katalog['dostrel_maximalni'];
        }
        if (!empty($katalog['dostrel_efektivni'])) {
            return (float)$katalog['dostrel_efektivni'];
        }
        return dracak_vtt_parsuj_dosah_sahy($katalog['dosah'] ?? null);
    }
    return null;
}

// Vzdálenost dvou tokenů NA STEJNÉ MAPĚ v sáhách — stejný princip jako
// drawRuler() v hra/mapa.php ("1 buňka gridu = 1 sáh", content/pravidla-
// hrac.html h1621: "pro souboj platí, že jeden hex vždy odpovídá jednomu
// sáhu"). Vrací null, když to nejde spočítat (ne chyba, jen "nelze
// ověřit"): mapa bez nastaveného gridu (grid_velikost_px <= 0 — stejně
// jako ruler bez gridu zůstává v syrových px, protože bez gridu není
// měřítko px->sáh) nebo některá z entit na týhle mapě token nemá.
function dracak_vtt_vzdalenost_tokenu_sahy(PDO $pdo, int $mapaId, string $typA, int $entitaA, string $typB, int $entitaB): ?float
{
    $stmt = $pdo->prepare('SELECT grid_velikost_px, grid_typ FROM mapy WHERE id = ?');
    $stmt->execute([$mapaId]);
    $mapa = $stmt->fetch();
    $pxSah = $mapa ? dracak_vtt_px_na_sah($mapa) : null;
    if ($pxSah === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT x, y FROM tokeny WHERE mapa_id = ? AND typ_entity = ? AND entita_id = ? LIMIT 1');
    $stmt->execute([$mapaId, $typA, $entitaA]);
    $tokenA = $stmt->fetch();
    $stmt->execute([$mapaId, $typB, $entitaB]);
    $tokenB = $stmt->fetch();
    if (!$tokenA || !$tokenB) {
        return null;
    }
    $distPx = hypot((int)$tokenB['x'] - (int)$tokenA['x'], (int)$tokenB['y'] - (int)$tokenA['y']);
    return $distPx / $pxSah;
}

// LoS (blokuje_vystrel) mezi dvěma tokeny na týž mapě — viz
// dracak_vtt_los_blokovana_zdi() v includes/vtt.php. Vrací null, když to
// nejde ověřit (některá entita na týhle mapě token nemá), stejná
// konvence jako dracak_vtt_vzdalenost_tokenu_sahy() výš.
function dracak_vtt_los_blokovana(PDO $pdo, int $mapaId, string $typA, int $entitaA, string $typB, int $entitaB): ?bool
{
    $stmt = $pdo->prepare('SELECT x, y FROM tokeny WHERE mapa_id = ? AND typ_entity = ? AND entita_id = ? LIMIT 1');
    $stmt->execute([$mapaId, $typA, $entitaA]);
    $tokenA = $stmt->fetch();
    $stmt->execute([$mapaId, $typB, $entitaB]);
    $tokenB = $stmt->fetch();
    if (!$tokenA || !$tokenB) {
        return null;
    }
    return dracak_vtt_los_blokovana_zdi($pdo, $mapaId, (float)$tokenA['x'], (float)$tokenA['y'], (float)$tokenB['x'], (float)$tokenB['y']);
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

// Magenergie (viz database/migrations/0050_vtt_postava_magenergie.sql)
// existuje JEN na postavy, nestvury instance ji vůbec nemají ve
// schématu — nestvury nemagenergii nemají (viz zadání). max_magenergie
// NULL = povolání magenergii nepoužívá (nebo zatím nevyplněno), 0 se v
// praxi nevyskytuje (PJ by nulu nezadal), ale pro jistotu se chová
// stejně jako NULL — "žádná zásoba", žádná kontrola/odečet.
function dracak_vtt_ma_magenergii(array $postava): bool
{
    return $postava['max_magenergie'] !== null && (int)$postava['max_magenergie'] > 0;
}

// Cena kouzla v magech. kouzla.cena_magenergie je volný text (VARCHAR,
// viz drd-db-schema-v1.sql) — "5 magů", "3 magy první, 2 magy každý
// další", "Životaschopnost × 2", "viz níže" apod., ne čisté číslo.
// Migrace 0005_cena_dalsiho_seslani.sql ale stanovila, že sloupec
// znamená "cenu prvního/jediného seslání" — pro jedno použití předmětu
// (hra/api/pouzij_predmet.php) je to přesně to číslo, co chceme.
// Použij proto stejnou konvenci jako dracak_vtt_prvni_cislo() jinde v
// enginu (oc/uc u nestvur): vytáhni první číslo z textu. U kouzel bez
// žádného čísla (vzorec závislý na vlastnostech cíle, odkaz na jiné
// kouzlo/tabulku) vrací null — cenu nejde spolehlivě určit automaticky,
// engine takovou spotřebu NEHÁDÁ (viz CLAUDE.md), jen ji nevynucuje a
// nechá PJ doladit magenergii ručně přes ±tlačítko.
function dracak_vtt_kouzlo_cena_magenergie(?string $cenaMagenergie): ?int
{
    if ($cenaMagenergie === null || trim($cenaMagenergie) === '') {
        return null;
    }
    $cislo = dracak_vtt_prvni_cislo($cenaMagenergie);
    return $cislo > 0 ? $cislo : null;
}

// Odečte cenu kouzla z postava.aktualni_magenergie, ořezáno na 0..max
// (h297/h179: "nikdy nesmí klesnout pod nulu a nelze ji zvýšit nad
// tabulkovou hodnotu"). Volá se jen když dracak_vtt_ma_magenergii() je
// true a cena je známá — volající musí ověřit dostatek PŘED zavoláním
// (viz dracak_vtt_kontrola_magenergie), tohle jen zapisuje výsledek.
// Vrací [nova_magenergie, max_magenergie].
function dracak_vtt_odecti_magenergii(array $postava, int $cena): array
{
    $max = (int)$postava['max_magenergie'];
    $nova = max(0, min($max, (int)$postava['aktualni_magenergie'] - $cena));
    $upd = dracak_db()->prepare('UPDATE postavy SET aktualni_magenergie = ? WHERE id = ?');
    $upd->execute([$nova, (int)$postava['id']]);
    return [$nova, $max];
}
