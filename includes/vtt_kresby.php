<?php
declare(strict_types=1);

// Kreslení na mapu (čáry, šipky, obdélníky, kruhy, kužele, text) — tabulka
// mapa_kresby (migrace 0084, vzniká ručním SQL). Kresby vidí všichni, kdo
// mapu otevřou; PJ je smí založit i skryté (jen pro sebe).

require_once __DIR__ . '/vtt.php';

const DRACAK_VTT_KRESBY_TYPY = ['cara', 'sipka', 'obdelnik', 'kruh', 'kuzel', 'text'];
const DRACAK_VTT_KRESBY_NA_MAPU = 600;

function dracak_vtt_kresby_tabulka_existuje(): bool
{
    static $existuje = null;
    if ($existuje === null) {
        try {
            $existuje = (int)dracak_db()->query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "mapa_kresby"'
            )->fetchColumn() > 0;
        } catch (Throwable $e) {
            $existuje = false;
        }
    }
    return $existuje;
}

// Řádek z DB → tvar pro klienta.
function dracak_vtt_kresba_pro_klienta(array $r, int $ucetId, bool $jePj): array
{
    $data = json_decode((string)$r['data'], true) ?: [];
    return [
        'id' => (int)$r['id'],
        'typ' => (string)$r['typ'],
        'b' => $data['b'] ?? [],
        't' => $data['t'] ?? null,
        'barva' => (string)$r['barva'],
        'sirka' => (int)$r['sirka'],
        'viditelne_hracum' => (bool)$r['viditelne_hracum'],
        'moje' => (int)($r['ucet_id'] ?? 0) === $ucetId,
        'smi_smazat' => $jePj || (int)($r['ucet_id'] ?? 0) === $ucetId,
    ];
}

function dracak_vtt_kresby_pro_mapu(PDO $pdo, int $mapaId, int $ucetId, bool $jePj): array
{
    if (!dracak_vtt_kresby_tabulka_existuje()) {
        return [];
    }
    $q = $pdo->prepare('SELECT * FROM mapa_kresby WHERE mapa_id = ?' . ($jePj ? '' : ' AND (viditelne_hracum = 1 OR ucet_id = ?)') . ' ORDER BY id');
    $q->execute($jePj ? [$mapaId] : [$mapaId, $ucetId]);
    return array_map(fn($r) => dracak_vtt_kresba_pro_klienta($r, $ucetId, $jePj), $q->fetchAll());
}

// Ověří a očistí vstup od klienta. Vrací ['typ','data','barva','sirka'] nebo ['chyba' => text].
function dracak_vtt_kresba_over(array $vstup): array
{
    $typ = (string)($vstup['typ'] ?? '');
    if (!in_array($typ, DRACAK_VTT_KRESBY_TYPY, true)) {
        return ['chyba' => 'Neznámý druh kresby.'];
    }
    $body = $vstup['b'] ?? null;
    if (!is_array($body) || !$body) {
        return ['chyba' => 'Kresba nemá žádné body.'];
    }
    $max = $typ === 'cara' ? 500 : ($typ === 'text' ? 1 : 2);
    $min = $typ === 'cara' ? 2 : ($typ === 'text' ? 1 : 2);
    if (count($body) < $min || count($body) > $max) {
        return ['chyba' => 'Kresba má špatný počet bodů.'];
    }
    $cista = [];
    foreach ($body as $b) {
        if (!is_array($b) || count($b) !== 2 || !is_numeric($b[0]) || !is_numeric($b[1])) {
            return ['chyba' => 'Kresba má neplatné souřadnice.'];
        }
        $x = (float)$b[0];
        $y = (float)$b[1];
        if (abs($x) > 100000 || abs($y) > 100000) {
            return ['chyba' => 'Souřadnice mimo mapu.'];
        }
        $cista[] = [round($x, 1), round($y, 1)];
    }
    $data = ['b' => $cista];
    if ($typ === 'text') {
        $t = trim((string)($vstup['t'] ?? ''));
        if ($t === '' || mb_strlen($t) > 200) {
            return ['chyba' => 'Text musí mít 1 až 200 znaků.'];
        }
        $data['t'] = $t;
    }
    $barva = (string)($vstup['barva'] ?? '#ffd166');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $barva)) {
        return ['chyba' => 'Barva musí být ve tvaru #rrggbb.'];
    }
    $sirka = max(1, min(40, (int)($vstup['sirka'] ?? 4)));
    return ['typ' => $typ, 'data' => $data, 'barva' => strtolower($barva), 'sirka' => $sirka];
}
