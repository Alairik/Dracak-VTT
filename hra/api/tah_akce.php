<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj_akce.php';

// Tvor na tahu odehrál akci (krok, běh, sprint, otočka, útok a jejich spojení, čekání…) — celé pravidlo
// a popis je v includes/vtt_souboj_akce.php. Smí postava na tahu (její hráč) nebo PJ.
// Tělo: {mapa_id, token_id, druh, cesta?: [{col,row,smer}], cil?: {col,row}, smer?: stupně, cil_token_id?, pocet?}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$vysledek = dracak_vtt_souboj_proved_akci(dracak_db(), $mapa, $jePj, $user, $data);
if ($vysledek['po_commitu'] !== null) {
    ($vysledek['po_commitu'])();
}
echo json_encode($vysledek['odpoved'], JSON_UNESCAPED_UNICODE);
