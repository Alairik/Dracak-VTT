<?php
declare(strict_types=1);

// Společná pravidla pro přesun tokenu (jeden token i skupina najednou):
// kdo smí, zámek, pořadí tahů, zdi. hra/api/token_presun.php a
// token_presun_davka.php se tak nemůžou rozejít.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_mlha.php';

// $token je řádek tokeny + svet_id + rozměry/grid mapy (viz dotazy v API).
// Vrací null, když je přesun v pořádku, jinak [http kód, zpráva].
function dracak_vtt_token_kontrola_presunu(PDO $pdo, array $user, array $token, int $noveX, int $noveY): ?array
{
    if (!dracak_vtt_can_move_token($user, $token)) {
        return [403, 'Tímhle tokenem hýbat nesmíš.'];
    }
    $jePj = dracak_vtt_je_pj_sveta($user, (int)$token['svet_id']);
    // Zamčený token (PJ ho zamkl proti posunu) hráč pohnout nesmí; PJ si ho
    // musí nejdřív odemknout.
    if (!empty($token['zamceno']) && !$jePj) {
        return [403, 'Token je zamčený.'];
    }
    // Pořadí tahů (dracak_vtt_je_na_tahu): probíhá-li na téhle mapě boj,
    // smí token hýbat jen hráč, jehož token je zrovna aktivní.
    if (!dracak_vtt_je_na_tahu($user, (int)$token['mapa_id'], (int)$token['id'])) {
        return [403, 'Teď nejsi na tahu — tímhle tokenem teď hýbat nesmíš.'];
    }
    // Zeď blokuje pohyb jen hráčům — PJ/admin smí token na zeď i přes ni
    // položit záměrně (postava odhozená do zdi, vylézání apod.).
    if (!$jePj && dracak_vtt_pohyb_prochazi_zdi($pdo, (int)$token['mapa_id'], (float)$token['x'], (float)$token['y'], (float)$noveX, (float)$noveY)) {
        return [403, 'Cesta vede přes zeď, kterou nejde projít.'];
    }
    return null;
}

// Mlha války se odhaluje kolem VLASTNÍKA postavy, ne kolem toho, kdo akci
// provedl — PJ může posunout hráčův token za něj, ale odhaluje se pořád
// hráčova vlastní mlha. Nestvůry a vlastní tokeny hráčům vidění nepřidávají.
function dracak_vtt_token_odhal_mlhu(PDO $pdo, array $token, int $x, int $y): void
{
    if ($token['typ_entity'] !== 'postava') {
        return;
    }
    $q = $pdo->prepare('SELECT vlastnik_ucet_id FROM postavy WHERE id = ?');
    $q->execute([(int)$token['entita_id']]);
    $vlastnik = $q->fetchColumn();
    if ($vlastnik === false) {
        return;
    }
    dracak_vtt_mlha_odhal_kolem_bodu($pdo, [
        'id' => (int)$token['mapa_id'],
        'sirka_px' => $token['sirka_px'],
        'vyska_px' => $token['vyska_px'],
        'grid_velikost_px' => $token['grid_velikost_px'],
        'grid_typ' => $token['grid_typ'],
        'grid_posun_x' => $token['grid_posun_x'],
        'grid_posun_y' => $token['grid_posun_y'],
    ], (int)$vlastnik, (float)$x, (float)$y);
}

// Další pořadové jméno pro stejnou věc na mapě: první "Goblin", pak "Goblin 2",
// "Goblin 3"… Bere nejvyšší číslo mezi existujícími (smazaný kus nevyrobí
// duplicitní jméno). $existujici = jména už použitá na mapě.
function dracak_vtt_dalsi_nazev(array $existujici, string $zaklad): string
{
    $nejvyssi = 0;
    foreach ($existujici as $nazev) {
        $nazev = (string)$nazev;
        if ($nazev === $zaklad) {
            $nejvyssi = max($nejvyssi, 1);
        } elseif (preg_match('/^' . preg_quote($zaklad, '/') . ' (\d+)$/u', $nazev, $m)) {
            $nejvyssi = max($nejvyssi, (int)$m[1]);
        }
    }
    return $nejvyssi === 0 ? $zaklad : $zaklad . ' ' . ($nejvyssi + 1);
}

function dracak_vtt_dalsi_nazev_instance(PDO $pdo, int $mapaId, int $nestvuraId, string $zaklad): string
{
    $q = $pdo->prepare('SELECT nazev_instance FROM nestvura_instance WHERE mapa_id = ? AND nestvura_id = ?');
    $q->execute([$mapaId, $nestvuraId]);
    return dracak_vtt_dalsi_nazev($q->fetchAll(PDO::FETCH_COLUMN), $zaklad);
}
