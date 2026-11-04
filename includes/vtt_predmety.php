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
// Střelné/vrhací zbraně: dostřel má podle pravidel TŘI pásma (malý/
// střední/velký, h1615 "Střelecký souboj") — malý/střední/velký jsou
// v DB číselné sloupce (SMALLINT, migrace 0059_dostrel_tri_pasma.sql),
// žádné parsování textu netřeba. Berou se jako tvrdá hranice dosahu
// místo sloupce dosah (ten je podle schématu "dosah NA BLÍZKO", u
// střelných/vrhacích zbraní se nepoužívá). dostrel_velky (nejvzdálenější
// pásmo) je přednější, protože je to skutečná fyzická hranice, kam
// zbraň vůbec dostřelí ("dál než je velký dostřel zbraně nelze
// střílet", h1615) — tahle kontrola neřeší postih za střelbu ve
// středním/velkém pásmu (+1/0/-1 k útoku dle pásma), to je otázka
// úspěšnosti zásahu, ne legality použití, a čeká na automatizované
// vyhodnocení útoku (které v enginu zatím vůbec neexistuje).
function dracak_vtt_polozka_dosah_sahy(string $typPolozky, array $katalog): ?float
{
    if ($typPolozky === 'kouzlo') {
        return dracak_vtt_parsuj_dosah_sahy($katalog['dosah'] ?? null);
    }
    if ($typPolozky === 'predmet') {
        if (!empty($katalog['dostrel_velky'])) {
            return (float)$katalog['dostrel_velky'];
        }
        if (!empty($katalog['dostrel_stredni'])) {
            return (float)$katalog['dostrel_stredni'];
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
    // Jeden UPDATE v DB (dracak_vtt_hp_zmen): souběžné změny životů se neztratí.
    return dracak_vtt_hp_zmen(dracak_db(), $typEntity, (int)$entity['id'], $delta) ?? [0, (int)$entity['max_hp']];
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
    return dracak_vtt_magenergie_zmen(dracak_db(), (int)$postava['id'], -$cena) ?? [0, (int)$postava['max_magenergie']];
}

// Účinek použité položky (lektvar, předmět, kouzlo): kostky z vlastního záznamu položky, poškození / léčení cíle, stavové efekty (nebo seznam čekající na past),
// odečtení magenergie sesilateli a událost „predmet_pouzit“. Společné pro hra/api/pouzij_predmet.php (ruční použití z deníku) a pro souboj (kouzlení, kouzla „ihned“
// připojená k akci — includes/vtt_kouzleni.php). Volá se UVNITŘ transakce volajícího; ověření oprávnění, dosahu a dostatku magenergie dělá volající.
// $c: typEntity, entitaId, entity (sesilatel), typPolozky, polozkaId, katalog, cilTypEntity, cilEntitaId, cilEntity, svetMapa (svet_id, mapa_id), cilSvetMapa,
// pozadovanaMapaId, zbyvaKolZadano, zbyvaKolVstup, magenergieCena (null = nestrhává se). Vrací ['udalost_id', 'kostka', 'hp', 'efekty', 'ceka_na_past', 'magenergie'].
function dracak_vtt_polozka_pouzij_ucinek(PDO $pdo, array $user, array $c): array
{
    $typEntity = $c['typEntity'];
    $entitaId = $c['entitaId'];
    $entity = $c['entity'];
    $typPolozky = $c['typPolozky'];
    $polozkaId = $c['polozkaId'];
    $katalog = $c['katalog'];
    $cilTypEntity = $c['cilTypEntity'];
    $cilEntitaId = $c['cilEntitaId'];
    $cilEntity = $c['cilEntity'];
    $svetMapa = $c['svetMapa'];
    $cilSvetMapa = $c['cilSvetMapa'];
    $pozadovanaMapaId = $c['pozadovanaMapaId'];
    $zbyvaKolZadano = $c['zbyvaKolZadano'];
    $zbyvaKolVstup = $c['zbyvaKolVstup'];
    $magenergieCena = $c['magenergieCena'];

    // Kostky "na jedno použití" bere engine VŽDY z vlastního záznamu
    // položky (pocet_kostek/typ_kostky/pevny_bonus), ne z efektu — viz
    // includes/vtt_predmety.php.
    $kostka = null;
    if (!empty($katalog['pocet_kostek']) && !empty($katalog['typ_kostky'])) {
        $typKostkyInt = dracak_vtt_typ_kostky_na_int($katalog['typ_kostky']);
        $kostka = dracak_vtt_hod_kostkou((int)$katalog['pocet_kostek'], $typKostkyInt, (int)($katalog['pevny_bonus'] ?? 0));
    }

    $efekty = dracak_vtt_polozka_efekty($typPolozky, $polozkaId);
    $hpVysledek = null;
    $aplikovaneEfekty = [];
    $cekajiNaPast = [];
    $efektMapaId = $pozadovanaMapaId ?? $svetMapa['mapa_id'] ?? $cilSvetMapa['mapa_id'];
    // Kouzlo s hodem proti pasti (cíl si hází): stavový efekt se sám nenasadí, dokud PJ nerozhodne podle hodu cíle.
    $pastKouzla = $typPolozky === 'kouzlo' ? dracak_vtt_kouzlo_past_text($katalog) : null;
    // Kostky položky (lektvar Regenerace „1k6+5 za kolo“) si nasazený efekt pamatuje pro tik každé kolo.
    $kostkyZdroje = null;
    if (!empty($katalog['pocet_kostek']) && !empty($katalog['typ_kostky'])) {
        $kostkyZdroje = dracak_vtt_kostky_text((int)$katalog['pocet_kostek'], (string)$katalog['typ_kostky'], (int)($katalog['pevny_bonus'] ?? 0));
    }
    $akceZmenenyUcastnik = false;
    foreach ($efekty as $efekt) {
        $znamenko = dracak_vtt_hp_znamenko($efekt);
        if ($znamenko !== null) {
            // Poškození/Léčení: jednorázový přímý zásah do života, žádný
            // trvalý záznam v aktivni_efekty (mechanika_aplikace je
            // 'jednorazove', trvani 'okamžité' — není co odpočítávat).
            if ($kostka === null) {
                // Efekt existuje, ale položka nemá definované kostky — není
                // z čeho spočítat kolik životů strhnout/přidat. Nehádat,
                // efekt se přeskočí (viz CLAUDE.md o nehádání mechaniky).
                continue;
            }
            $delta = $znamenko * $kostka['celkem'];
            [$noveHp, $maxHp] = dracak_vtt_aplikuj_hp_deltu($cilTypEntity, $cilEntity, $delta);
            $cilEntity['aktualni_hp'] = $noveHp;
            $hpVysledek = ['delta' => $delta, 'nove_hp' => $noveHp, 'max_hp' => $maxHp];
            $aplikovaneEfekty[] = ['efekt_id' => (int)$efekt['id'], 'nazev' => $efekt['nazev'], 'hp_delta' => $delta];
        } elseif ($pastKouzla !== null) {
            $cekajiNaPast[] = ['efekt_id' => (int)$efekt['id'], 'nazev' => $efekt['nazev'], 'past' => $pastKouzla];
        } else {
            // Běžný stavový efekt (buff/debuff/dot/hot/modifikator/imunita/
            // zranitelnost/odolnost jiný než Poškození/Léčení) — nasadí se
            // společnou funkcí (includes/vtt_efekty.php): výchozí trvání
            // efektu nebo trvání ze sesílání, okamžité změny akcí, obnovení
            // trvání, když efekt na cíli už je.
            $volby = ['kostky' => $kostkyZdroje];
            if ($zbyvaKolZadano) {
                $volby['zbyva_kol'] = $zbyvaKolVstup;
            }
            $v = dracak_vtt_efekt_aplikuj($pdo, $efektMapaId !== null ? (int)$efektMapaId : null, $cilTypEntity, $cilEntitaId, (int)$efekt['id'], $volby);
            $aplikovaneEfekty[] = [
                'efekt_id' => (int)$efekt['id'],
                'nazev' => $efekt['nazev'],
                'aktivni_efekt_id' => $v['aktivni_efekt_id'],
                'zbyva_kol' => $v['zbyva_kol'],
                'zbyva_pred' => $v['zbyva_pred'],
                'obnoveno' => $v['obnoveno'],
                'stav' => $v['stav'],
                'shrnuti' => $v['shrnuti'],
                'akce' => $v['akce'],
                'upozorneni' => $v['upozorneni'],
            ];
            if ($v['akce'] !== null) {
                $akceZmenenyUcastnik = true;
            }
        }
    }
    if ($akceZmenenyUcastnik && $efektMapaId !== null) {
        dracak_vtt_efekt_posun_po_nasazeni($pdo, (int)$svetMapa['svet_id'], (int)$efektMapaId, (int)$user['id']);
    }

    // Magenergie se strhává seslateli ($entity/$typEntity), ne cíli kouzla
    // — platí ten, kdo kouzlo seslal, bez ohledu na to, na koho dopadá.
    $magenergieVysledek = null;
    if ($magenergieCena !== null) {
        [$novaMagenergie, $maxMagenergie] = dracak_vtt_odecti_magenergii($entity, $magenergieCena);
        $magenergieVysledek = ['cena' => $magenergieCena, 'nova_magenergie' => $novaMagenergie, 'max_magenergie' => $maxMagenergie];
    }

    $logMapaId = $pozadovanaMapaId ?? $svetMapa['mapa_id'] ?? $cilSvetMapa['mapa_id'];
    $eventId = dracak_vtt_log_event($svetMapa['svet_id'], $logMapaId, 'predmet_pouzit', [
        'typ_entity' => $typEntity,
        'entita_id' => $entitaId,
        'typ_polozky' => $typPolozky,
        'polozka_id' => $polozkaId,
        'polozka_nazev' => $katalog['nazev'],
        'cil_typ_entity' => $cilTypEntity,
        'cil_entita_id' => $cilEntitaId,
        'kostka' => $kostka,
        'hp' => $hpVysledek,
        'efekty' => $aplikovaneEfekty,
        'ceka_na_past' => $cekajiNaPast,
        'magenergie' => $magenergieVysledek,
        'pouzil' => $user['jmeno'],
    ], (int)$user['id']);

    return [
        'udalost_id' => $eventId,
        'kostka' => $kostka,
        'hp' => $hpVysledek,
        'efekty' => $aplikovaneEfekty,
        'ceka_na_past' => $cekajiNaPast,
        'magenergie' => $magenergieVysledek,
    ];
}
