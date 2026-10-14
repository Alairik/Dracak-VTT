<?php
declare(strict_types=1);

// Kontejnery postavy (vaky, truhly, soudky, trakař…) — Fáze 1b plánu
// (docs/stav-projektu.md), tabulky postava_kontejnery a kontejner_obsah
// (migrace 0081), kapacita a vlastní váha jsou na předmětu (predmety.kapacita_mn,
// predmety.vaha; migrace 0080 a ceník 0082). Model "vedle": volný inventář
// zůstává v postava_predmety; předmět vložený do kontejneru z něj zmizí a je
// v kontejner_obsah.
//
// Přeplnění kapacity se jen HLÁSÍ — kdo s předmětem manipuluje (hráč i PJ),
// dostane upozornění a může ho potvrdit (rozhodnutí uživatele 2026-10-14).
// Váha: vlastní váha kontejneru + jeho obsah. Kontejner na zvířeti (nosic =
// 'zvire') nese zvíře, na kterém postava jede, ne ona sama.
//
// Funkce tady nekontrolují oprávnění — to dělá volající API
// (hra/api/postava_kontejner.php, stejné právo jako u inventáře).

// Jsou tabulky kontejnerů na DB (migrace 0081)? Bez nich se kontejnery
// přeskakují a vše funguje jako dřív.
function dracak_vtt_kontejnery_dostupne(PDO $pdo): bool
{
    static $dostupne = null;
    if ($dostupne === null) {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('postava_kontejnery', 'kontejner_obsah')"
        );
        $dostupne = (int)$stmt->fetchColumn() === 2;
    }
    return $dostupne;
}

// Kontejnery postavy s obsahem a vahami. Každý prvek:
// ['id', 'predmet_id', 'nazev' (typ z katalogu), 'vlastni_nazev'|null, 'nosic',
//  'vaha_vlastni' (float|null), 'kapacita_mn' (float|null),
//  'obsah' => [['predmet_id','nazev','mnozstvi','vaha_ks'|null,'vaha_celkem'|null], …],
//  'obsah_vaha', 'bez_vahy' => [názvy], 'vaha_celkem', 'volne' (float|null),
//  'plne' (bool: obsah nad kapacitou)]
function dracak_vtt_kontejnery_postavy(PDO $pdo, int $postavaId): array
{
    if (!dracak_vtt_kontejnery_dostupne($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT k.id, k.predmet_id, k.nazev AS vlastni_nazev, k.nosic, p.nazev, p.vaha, p.kapacita_mn
         FROM postava_kontejnery k JOIN predmety p ON p.id = k.predmet_id
         WHERE k.postava_id = ? ORDER BY p.nazev, k.id'
    );
    $stmt->execute([$postavaId]);
    $vysledek = [];
    $obsahStmt = $pdo->prepare(
        'SELECT o.predmet_id, p.nazev, p.vaha, o.mnozstvi
         FROM kontejner_obsah o JOIN predmety p ON p.id = o.predmet_id
         WHERE o.kontejner_id = ? ORDER BY p.nazev'
    );
    foreach ($stmt->fetchAll() as $k) {
        $obsahStmt->execute([(int)$k['id']]);
        $obsah = [];
        $obsahVaha = 0.0;
        $bezVahy = [];
        foreach ($obsahStmt->fetchAll() as $o) {
            $vahaKs = $o['vaha'] !== null ? (float)$o['vaha'] : null;
            if ($vahaKs === null) {
                $bezVahy[] = $o['nazev'];
            } else {
                $obsahVaha += $vahaKs * (int)$o['mnozstvi'];
            }
            $obsah[] = [
                'predmet_id' => (int)$o['predmet_id'], 'nazev' => $o['nazev'], 'mnozstvi' => (int)$o['mnozstvi'],
                'vaha_ks' => $vahaKs, 'vaha_celkem' => $vahaKs !== null ? $vahaKs * (int)$o['mnozstvi'] : null,
            ];
        }
        $vlastni = $k['vaha'] !== null ? (float)$k['vaha'] : null;
        $kapacita = $k['kapacita_mn'] !== null ? (float)$k['kapacita_mn'] : null;
        if ($vlastni === null) {
            $bezVahy[] = $k['nazev'];
        }
        $vysledek[] = [
            'id' => (int)$k['id'], 'predmet_id' => (int)$k['predmet_id'], 'nazev' => $k['nazev'],
            'vlastni_nazev' => $k['vlastni_nazev'], 'nosic' => $k['nosic'],
            'vaha_vlastni' => $vlastni, 'kapacita_mn' => $kapacita,
            'obsah' => $obsah, 'obsah_vaha' => $obsahVaha, 'bez_vahy' => $bezVahy,
            'vaha_celkem' => ($vlastni ?? 0.0) + $obsahVaha,
            'volne' => $kapacita !== null ? $kapacita - $obsahVaha : null,
            'plne' => $kapacita !== null && $obsahVaha > $kapacita + 0.005,
        ];
    }
    return $vysledek;
}

// Váha kontejnerů postavy rozdělená podle nosiče: ['postava' => mn,
// 'zvire' => mn, 'bez_vahy' => [názvy]].
function dracak_vtt_kontejnery_vahy(PDO $pdo, int $postavaId): array
{
    $vysledek = ['postava' => 0.0, 'zvire' => 0.0, 'bez_vahy' => []];
    foreach (dracak_vtt_kontejnery_postavy($pdo, $postavaId) as $k) {
        $vysledek[$k['nosic'] === 'zvire' ? 'zvire' : 'postava'] += $k['vaha_celkem'];
        foreach ($k['bez_vahy'] as $n) {
            $vysledek['bez_vahy'][] = $n;
        }
    }
    return $vysledek;
}

// Pomocné: kontejner patří postavě?
function dracak_vtt_kontejner_radek(PDO $pdo, int $postavaId, int $kontejnerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT k.*, p.nazev AS typ_nazev, p.kapacita_mn FROM postava_kontejnery k JOIN predmety p ON p.id = k.predmet_id
         WHERE k.id = ? AND k.postava_id = ?'
    );
    $stmt->execute([$kontejnerId, $postavaId]);
    return $stmt->fetch() ?: null;
}

function dracak_vtt_kontejner_popisek(array $radek): string
{
    return ($radek['nazev'] ?? '') !== '' && $radek['nazev'] !== null
        ? $radek['nazev'] . ' (' . $radek['typ_nazev'] . ')'
        : (string)$radek['typ_nazev'];
}

// Přidá postavě nový kontejner z katalogu (předmět musí mít kapacitu).
// Vrací id nového kontejneru, nebo null, když předmět není kontejner.
function dracak_vtt_kontejner_pridat(PDO $pdo, int $postavaId, int $predmetId, ?string $nazev = null): ?int
{
    $stmt = $pdo->prepare('SELECT kapacita_mn FROM predmety WHERE id = ?');
    $stmt->execute([$predmetId]);
    $kap = $stmt->fetchColumn();
    if ($kap === false || $kap === null) {
        return null;
    }
    $nazev = $nazev !== null ? trim($nazev) : null;
    $pdo->prepare('INSERT INTO postava_kontejnery (postava_id, predmet_id, nazev) VALUES (?, ?, ?)')
        ->execute([$postavaId, $predmetId, $nazev !== '' ? $nazev : null]);
    return (int)$pdo->lastInsertId();
}

// Předmět s kapacitou ležící ve volném inventáři (např. ho přidal PJ) se
// "rozbalí": ze všech kusů se stanou kontejnery (nejvýš 20 najednou).
function dracak_vtt_kontejner_rozbalit(PDO $pdo, int $postavaId, int $predmetId): array
{
    $stmt = $pdo->prepare(
        'SELECT pp.mnozstvi, p.kapacita_mn FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
         WHERE pp.postava_id = ? AND pp.predmet_id = ?'
    );
    $stmt->execute([$postavaId, $predmetId]);
    $r = $stmt->fetch();
    if (!$r || $r['kapacita_mn'] === null) {
        return ['ok' => false, 'chyba' => 'Tohle není kontejner.'];
    }
    $kusu = min((int)$r['mnozstvi'], 20);
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < $kusu; $i++) {
            dracak_vtt_kontejner_pridat($pdo, $postavaId, $predmetId);
        }
        if ($kusu >= (int)$r['mnozstvi']) {
            $pdo->prepare('DELETE FROM postava_predmety WHERE postava_id = ? AND predmet_id = ?')->execute([$postavaId, $predmetId]);
        } else {
            $pdo->prepare('UPDATE postava_predmety SET mnozstvi = mnozstvi - ? WHERE postava_id = ? AND predmet_id = ?')->execute([$kusu, $postavaId, $predmetId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true];
}

// Vloží $mnozstvi kusů předmětu z VOLNÉHO inventáře postavy do kontejneru.
// Vrací ['ok' => true, 'upozorneni' => text|null] po provedení; když by se
// kapacita překročila a volající nepotvrdil ($potvrdit = false), nic se
// neprovede a vrátí se ['ok' => false, 'vyzaduje_potvrzeni' => true,
// 'upozorneni' => text]. Chyby: ['ok' => false, 'chyba' => text].
function dracak_vtt_kontejner_vloz(PDO $pdo, int $postavaId, int $kontejnerId, int $predmetId, int $mnozstvi, bool $potvrdit): array
{
    $mnozstvi = max(1, min(1000, $mnozstvi));
    $k = dracak_vtt_kontejner_radek($pdo, $postavaId, $kontejnerId);
    if (!$k) {
        return ['ok' => false, 'chyba' => 'Kontejner nenalezen.'];
    }
    $stmt = $pdo->prepare(
        'SELECT pp.mnozstvi, p.nazev, p.vaha, p.kapacita_mn FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
         WHERE pp.postava_id = ? AND pp.predmet_id = ?'
    );
    $stmt->execute([$postavaId, $predmetId]);
    $volny = $stmt->fetch();
    if (!$volny || (int)$volny['mnozstvi'] < $mnozstvi) {
        return ['ok' => false, 'chyba' => 'Tolik kusů ve volném inventáři postava nemá.'];
    }
    if ($volny['kapacita_mn'] !== null) {
        return ['ok' => false, 'chyba' => 'Kontejner se do jiného kontejneru vložit nedá.'];
    }

    $obsah = 0.0;
    $stmt = $pdo->prepare(
        'SELECT p.vaha, o.mnozstvi FROM kontejner_obsah o JOIN predmety p ON p.id = o.predmet_id WHERE o.kontejner_id = ?'
    );
    $stmt->execute([$kontejnerId]);
    foreach ($stmt->fetchAll() as $o) {
        $obsah += $o['vaha'] !== null ? (float)$o['vaha'] * (int)$o['mnozstvi'] : 0.0;
    }
    $pribude = $volny['vaha'] !== null ? (float)$volny['vaha'] * $mnozstvi : 0.0;
    $upozorneni = null;
    if ($k['kapacita_mn'] !== null && $obsah + $pribude > (float)$k['kapacita_mn'] + 0.005) {
        $upozorneni = dracak_vtt_kontejner_popisek($k) . ' má kapacitu ' . rtrim(rtrim(number_format((float)$k['kapacita_mn'], 1, ',', ''), '0'), ',')
            . ' mn, s touhle věcí by v něm bylo ' . rtrim(rtrim(number_format($obsah + $pribude, 1, ',', ''), '0'), ',') . ' mn.';
        if (!$potvrdit) {
            return ['ok' => false, 'vyzaduje_potvrzeni' => true, 'upozorneni' => $upozorneni . ' Vložit přesto?'];
        }
    }
    if ($volny['vaha'] === null) {
        $upozorneni = ($upozorneni ? $upozorneni . ' ' : '') . $volny['nazev'] . ' nemá v katalogu váhu — do váhy kontejneru se nepočítá.';
    }

    $pdo->beginTransaction();
    try {
        if ((int)$volny['mnozstvi'] === $mnozstvi) {
            $pdo->prepare('DELETE FROM postava_predmety WHERE postava_id = ? AND predmet_id = ?')->execute([$postavaId, $predmetId]);
            // Věc, co už není ve volném inventáři, se nemůže nosit ani držet.
            foreach (['zbroj_id', 'stit_id', 'drzena_zbran_id'] as $sloupec) {
                if (dracak_vtt_kontejnery_ma_sloupec($pdo, 'postavy', $sloupec)) {
                    $pdo->prepare("UPDATE postavy SET $sloupec = NULL WHERE id = ? AND $sloupec = ?")->execute([$postavaId, $predmetId]);
                }
            }
        } else {
            $pdo->prepare('UPDATE postava_predmety SET mnozstvi = mnozstvi - ? WHERE postava_id = ? AND predmet_id = ?')
                ->execute([$mnozstvi, $postavaId, $predmetId]);
        }
        $pdo->prepare(
            'INSERT INTO kontejner_obsah (kontejner_id, predmet_id, mnozstvi) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE mnozstvi = LEAST(65000, mnozstvi + VALUES(mnozstvi))'
        )->execute([$kontejnerId, $predmetId, $mnozstvi]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'upozorneni' => $upozorneni];
}

// Vrátí $mnozstvi kusů (null = všechny) z kontejneru do volného inventáře.
function dracak_vtt_kontejner_vyndej(PDO $pdo, int $postavaId, int $kontejnerId, int $predmetId, ?int $mnozstvi): array
{
    if (!dracak_vtt_kontejner_radek($pdo, $postavaId, $kontejnerId)) {
        return ['ok' => false, 'chyba' => 'Kontejner nenalezen.'];
    }
    $stmt = $pdo->prepare('SELECT mnozstvi FROM kontejner_obsah WHERE kontejner_id = ? AND predmet_id = ?');
    $stmt->execute([$kontejnerId, $predmetId]);
    $kusu = $stmt->fetchColumn();
    if ($kusu === false) {
        return ['ok' => false, 'chyba' => 'Tuhle věc v kontejneru nemáš.'];
    }
    $kusu = (int)$kusu;
    $vyndat = $mnozstvi === null ? $kusu : max(1, min($kusu, $mnozstvi));
    $pdo->beginTransaction();
    try {
        if ($vyndat >= $kusu) {
            $pdo->prepare('DELETE FROM kontejner_obsah WHERE kontejner_id = ? AND predmet_id = ?')->execute([$kontejnerId, $predmetId]);
        } else {
            $pdo->prepare('UPDATE kontejner_obsah SET mnozstvi = mnozstvi - ? WHERE kontejner_id = ? AND predmet_id = ?')
                ->execute([$vyndat, $kontejnerId, $predmetId]);
        }
        $pdo->prepare(
            'INSERT INTO postava_predmety (postava_id, predmet_id, mnozstvi) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE mnozstvi = LEAST(65000, mnozstvi + VALUES(mnozstvi))'
        )->execute([$postavaId, $predmetId, $vyndat]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true];
}

// Odebere kontejner; jeho obsah se vrátí do volného inventáře.
function dracak_vtt_kontejner_odebrat(PDO $pdo, int $postavaId, int $kontejnerId): array
{
    if (!dracak_vtt_kontejner_radek($pdo, $postavaId, $kontejnerId)) {
        return ['ok' => false, 'chyba' => 'Kontejner nenalezen.'];
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO postava_predmety (postava_id, predmet_id, mnozstvi)
             SELECT ?, o.predmet_id, o.mnozstvi FROM kontejner_obsah o WHERE o.kontejner_id = ?
             ON DUPLICATE KEY UPDATE mnozstvi = LEAST(65000, postava_predmety.mnozstvi + VALUES(mnozstvi))'
        )->execute([$postavaId, $kontejnerId]);
        $pdo->prepare('DELETE FROM kontejner_obsah WHERE kontejner_id = ?')->execute([$kontejnerId]);
        $pdo->prepare('DELETE FROM postava_kontejnery WHERE id = ? AND postava_id = ?')->execute([$kontejnerId, $postavaId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true];
}

// Kdo kontejner nese: 'postava', nebo 'zvire' (jen když postava jede na zvířeti).
function dracak_vtt_kontejner_nosic(PDO $pdo, array $postava, int $kontejnerId, string $nosic): array
{
    if (!in_array($nosic, ['postava', 'zvire'], true)) {
        return ['ok' => false, 'chyba' => 'Neznámý nosič.'];
    }
    if (!dracak_vtt_kontejner_radek($pdo, (int)$postava['id'], $kontejnerId)) {
        return ['ok' => false, 'chyba' => 'Kontejner nenalezen.'];
    }
    if ($nosic === 'zvire') {
        $jede = ($postava['doprava_typ'] ?? null) === 'zvire' && !empty($postava['doprava_zvire_id']);
        if (!$jede) {
            return ['ok' => false, 'chyba' => 'Postava nejede na zvířeti — nejdřív v nastavení postavy vyber dopravu.'];
        }
    }
    $pdo->prepare('UPDATE postava_kontejnery SET nosic = ? WHERE id = ?')->execute([$nosic, $kontejnerId]);
    return ['ok' => true];
}

function dracak_vtt_kontejner_prejmenovat(PDO $pdo, int $postavaId, int $kontejnerId, string $nazev): array
{
    if (!dracak_vtt_kontejner_radek($pdo, $postavaId, $kontejnerId)) {
        return ['ok' => false, 'chyba' => 'Kontejner nenalezen.'];
    }
    $nazev = trim($nazev);
    $pdo->prepare('UPDATE postava_kontejnery SET nazev = ? WHERE id = ?')
        ->execute([$nazev !== '' ? mb_substr($nazev, 0, 80) : null, $kontejnerId]);
    return ['ok' => true];
}

function dracak_vtt_kontejnery_ma_sloupec(PDO $pdo, string $tabulka, string $sloupec): bool
{
    static $cache = [];
    $klic = $tabulka . '.' . $sloupec;
    if (!array_key_exists($klic, $cache)) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$tabulka, $sloupec]);
        $cache[$klic] = (int)$stmt->fetchColumn() > 0;
    }
    return $cache[$klic];
}
