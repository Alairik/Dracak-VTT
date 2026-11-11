<?php
declare(strict_types=1);

// Zapálit / zhasnout zdroj světla, který má postava v inventáři (pochodeň, lucerna).
//
// Proč: světlo se dosud nastavovalo ručně na tokenu, i když postava pochodeň fyzicky nesla — dvě místa
// pro jednu věc a PJ na to musel myslet (návrh docs/panel-tokenu-navrh.md, část D). Teď je akce u předmětu
// v deníku a token se rozsvítí sám; ruční nastavení na tokenu zůstává pro nestvůry, obecné tokeny a opravy.
//
// Vstup: {postava_id, predmet_id, zapnout: true|false}
// Právo: vlastník postavy nebo PJ světa (stejné pravidlo jako u životů, dracak_vtt_can_edit_hp).

require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_denik.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$postavaId = (int)($data['postava_id'] ?? 0);
$predmetId = (int)($data['predmet_id'] ?? 0);
$zapnout = !empty($data['zapnout']);

function svetlo_predmet_chyba(int $kod, string $text): void
{
    http_response_code($kod);
    echo json_encode(['error' => $text]);
    exit;
}

$pdo = dracak_db();
$postava = dracak_vtt_entity_row('postava', $postavaId);
if (!$postava) {
    svetlo_predmet_chyba(404, 'Postava nenalezena.');
}
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    svetlo_predmet_chyba(403, 'Tuhle postavu upravovat nesmíš.');
}

// Předmět musí postava opravdu mít — jinak by šlo rozsvítit pochodeň, kterou nikdo nenese.
$q = $pdo->prepare('SELECT p.nazev, p.kategorie_vybaveni FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
                     WHERE pp.postava_id = ? AND pp.predmet_id = ? AND pp.mnozstvi > 0');
$q->execute([$postavaId, $predmetId]);
$predmet = $q->fetch();
if (!$predmet) {
    svetlo_predmet_chyba(404, 'Tenhle předmět postava u sebe nemá.');
}
$svetlo = dracak_vtt_predmet_svetlo((string)$predmet['nazev'], $predmet['kategorie_vybaveni'] ?? null);
if ($svetlo === null) {
    svetlo_predmet_chyba(422, 'Tímhle předmětem se svítit nedá.');
}

// Token postavy: svítí se tam, kde postava stojí. Když token nikde není, není co rozsvítit.
$q = $pdo->prepare('SELECT t.id, t.mapa_id FROM tokeny t WHERE t.typ_entity = "postava" AND t.entita_id = ? ORDER BY t.id DESC LIMIT 1');
$q->execute([$postavaId]);
$token = $q->fetch();
if (!$token) {
    svetlo_predmet_chyba(409, 'Postava nemá na žádné mapě token, takže není co rozsvítit.');
}

$mamZbyva = dracak_vtt_tokeny_funkce()['svetlo_zbyva'] ?? false;
$sloupce = $zapnout
    ? ['svetlo_sahy' => $svetlo['sahy'], 'svetlo_typ' => $svetlo['typ']]
    : ['svetlo_sahy' => null, 'svetlo_typ' => null];
if ($mamZbyva) {
    $sloupce['svetlo_zbyva'] = $zapnout ? $svetlo['smeny'] : null;
}
$sql = 'UPDATE tokeny SET ' . implode(', ', array_map(static fn($s) => $s . ' = ?', array_keys($sloupce))) . ' WHERE id = ?';
$st = $pdo->prepare($sql);
$st->execute([...array_values($sloupce), (int)$token['id']]);

$q = $pdo->prepare('SELECT svet_id FROM mapy WHERE id = ?');
$q->execute([(int)$token['mapa_id']]);
$svetId = (int)($q->fetchColumn() ?: 0);
if ($svetId > 0) {
    dracak_vtt_log_event($svetId, (int)$token['mapa_id'], 'token_uprava', [
        'token_id' => (int)$token['id'],
        'zmeny' => ['svetlo' => $sloupce['svetlo_sahy'], 'svetlo_typ' => $sloupce['svetlo_typ']]
            + ($mamZbyva ? ['zbyva' => $sloupce['svetlo_zbyva']] : []),
        'duvod' => ($zapnout ? 'zapálil(a) ' : 'zhasl(a) ') . $predmet['nazev'],
    ], (int)$user['id']);
}

echo json_encode([
    'ok' => true,
    'zapnuto' => $zapnout,
    'token_id' => (int)$token['id'],
    'sahy' => $sloupce['svetlo_sahy'],
    'typ' => $sloupce['svetlo_typ'],
    'zbyva' => $mamZbyva ? $sloupce['svetlo_zbyva'] : null,
    'popis' => $svetlo['popis'],
]);
