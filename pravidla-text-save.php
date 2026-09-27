<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/entity_crud.php';

// AJAX endpoint volaný z tužky "upravit text" v pravidla.php (viz
// assets/pravidla/pravidla.js / inline skript tamtéž) — ukládá přepis
// JEDNOHO nadpisu/odstavce knihy pravidel do pravidla_texty (líné
// vytváření řádku, viz migrace database/migrations/0037_pravidla_texty.sql
// a entita 'pravidla_texty' v includes/entities.php). Sdílí přesně
// stejnou vlastnickou/oprávnění logiku jako editor.php (dracak_can_edit
// + dracak_can_edit_row) — žádný speciální mechanismus bokem — a
// sanitizace uloženého HTML běží uvnitř dracak_entity_save() (pole má
// 'sanitize_html' => true), ne tady zvlášť, takže platí stejně i pro
// případnou editaci přes generický editor.php.

header('Content-Type: application/json; charset=utf-8');

function dracak_text_save_fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

dracak_session_start();
$user = dracak_current_user();
if ($user === null) {
    dracak_text_save_fail(401, 'Musíš být přihlášený.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dracak_text_save_fail(405, 'Jen POST.');
}

$table = 'pravidla_texty';
$allEntities = require __DIR__ . '/includes/entities.php';
$config = $allEntities[$table];

if (!dracak_can_edit($user, $table)) {
    dracak_text_save_fail(403, 'Nemáš právo upravovat text pravidel.');
}

$knihaId = trim((string)($_POST['kniha_id'] ?? ''));
// Přesně formát id="hNNNN"/"bNNNN" z content/pravidla-*.html (viz
// pravidla.php) — nic jiného se sem ukládat nemá, jinak by šlo přes
// tenhle endpoint zapsat libovolný řetězec jako "kotvu".
if (!preg_match('/^[hb][1-9][0-9]{0,9}$/', $knihaId)) {
    dracak_text_save_fail(400, 'Neplatné ID v knize.');
}

$obsahRaw = (string)($_POST['obsah'] ?? '');
if (strlen($obsahRaw) > 200000) {
    dracak_text_save_fail(413, 'Text je příliš dlouhý.');
}

$existing = dracak_entity_find_by($table, 'kniha_id', $knihaId);
if (!dracak_can_edit_row($user, $table, $config, $existing)) {
    dracak_text_save_fail(403, 'Tenhle text patří jinému uživateli — smíš upravovat jen svoje vlastní přepisy.');
}

$fields = dracak_entity_fields($table, $config);
$id = $existing['id'] ?? null;
// dracak_entity_save() sanitizuje 'obsah' samo (viz 'sanitize_html' => true
// u pole v entities.php) — obsahRaw jde dovnitř nedůvěryhodné, ven z DB
// se čte už čisté (viz dracak_entity_get níž).
dracak_entity_save($table, $fields, $id !== null ? (int)$id : null, [
    'kniha_id' => $knihaId,
    'obsah' => $obsahRaw,
], $config, (int)$user['id']);

$saved = dracak_entity_find_by($table, 'kniha_id', $knihaId);
echo json_encode([
    'ok' => true,
    'kniha_id' => $knihaId,
    'obsah' => $saved['obsah'] ?? '',
], JSON_UNESCAPED_UNICODE);
