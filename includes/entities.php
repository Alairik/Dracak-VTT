<?php
declare(strict_types=1);

// Registr entit pro generický editor. Každý klíč = název tabulky v DB.
// 'quick' u pole = patří do rychlé šablony (vidět hned); ostatní pole
// jsou schovaná pod "Zobrazit všechna pole", ale ukládají se ve stejném
// formuláři/tabulce — žádná druhá entita navíc.
//
// 'filter' u pole zapíná server-side filtr v seznamu:
//   'range'  — od/do na numerickém sloupci (BETWEEN v SQL)
//   'select' — dropdown; 'options' na poli = pevná nabídka, jinak se
//              nabídka natáhne jako DISTINCT hodnoty sloupce z DB
//
// 'relations' u entity = M:N vztahy editovatelné jako tag box na
// edit formuláři (viz docs/zadani-redesign-ui.md + design handoff).
// 'row_owned' => true zapíná sloupec created_by (migrace 0009) a
// row-level kontrolu pro roli hráč přes dracak_can_edit_row().

// Sdílená 4 pole pro entity, co dávají zranění/léčení kostkou (kouzlo,
// schopnost, lektvar, finta) — řeší "2k6+2" i "modré blesky: lze seslat
// vícekrát, každé seslání přidá dalších 1k6". Sloupce zavádí migrace
// database/0003_kostky_a_bonusy.sql. Tři "core" sloupce (pocet_kostek/
// typ_kostky/pevny_bonus) se v editoru zobrazují/editují jako jedno
// kompaktní pole "2k6+2" (dracak_kostky_zapis_format/parse v
// entity_crud.php) — quick=>false, protože je nahrazuje syntetické pole
// 'kostky_zapis', které editor.php vkládá do formuláře ručně.
function dracak_kostky_pole(): array
{
    $typyKostek = ['k3', 'k4', 'k6', 'k8', 'k10', 'k12', 'k20', 'k100'];
    return [
        ['name' => 'pocet_kostek', 'label' => 'Počet kostek', 'type' => 'number', 'quick' => false, 'kostky_core' => true],
        ['name' => 'typ_kostky', 'label' => 'Typ kostky', 'type' => 'select', 'options' => $typyKostek, 'quick' => false, 'kostky_core' => true],
        ['name' => 'pevny_bonus', 'label' => 'Pevný bonus (např. +2 u "2k6+2")', 'type' => 'number', 'quick' => false, 'kostky_core' => true],
        ['name' => 'vicenasobne', 'label' => 'Lze provést/seslat vícekrát (škáluje počet kostek, např. modré blesky)', 'type' => 'checkbox', 'quick' => false],
        ['name' => 'max_pouziti', 'label' => 'Max. počet použití (prázdné = bez limitu, např. "3x denně")', 'type' => 'text', 'quick' => false],
    ];
}

return [

    // --- OBSAH: kouzlo/příšera/dovednost/lektvar/vybavení, editovatelné hráčem dle práv ---

    'kouzla' => [
        'label' => 'Kouzla',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        // Řádek štítků v seznamu, ve stylu "SPECIFIKACE KOUZEL" z pravidel
        // (Mana/Dosah/Rozsah/Trvání pohromadě, ne zahrabané v odstavci).
        'summary_fields' => ['uroven_kouzla' => 'Lv.', 'cena_magenergie' => 'Mana', 'cena_dalsi_seslani' => '+ mana/další', 'dosah' => 'Dosah', 'rozsah' => 'Rozsah', 'doba_seslani' => 'Vyvolání', 'doba_trvani' => 'Trvání', 'typ_unavy' => 'Typ únavy'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'uroven_kouzla', 'label' => 'Úroveň kouzla', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ['name' => 'cena_magenergie', 'label' => 'Cena magenergie (první/jediné seslání)', 'type' => 'text', 'quick' => true],
            ['name' => 'cena_dalsi_seslani', 'label' => 'Cena za každé další seslání (jen u vícenásobných, pokud se liší)', 'type' => 'text', 'quick' => true],
            ...dracak_kostky_pole(),
            ['name' => 'popis', 'label' => 'Popis / efekt', 'type' => 'textarea', 'quick' => true],
            ['name' => 'seznam_kouzel_id', 'label' => 'Seznam kouzel', 'type' => 'select_fk', 'ref_table' => 'seznamy_kouzel', 'ref_label' => 'nazev'],
            ['name' => 'dosah', 'label' => 'Dosah (vzdálenost)', 'type' => 'text'],
            ['name' => 'rozsah', 'label' => 'Rozsah (počet cílů / oblast)', 'type' => 'text'],
            ['name' => 'doba_seslani', 'label' => 'Doba seslání', 'type' => 'text', 'filter' => 'select'],
            ['name' => 'doba_trvani', 'label' => 'Doba trvání', 'type' => 'text', 'filter' => 'select'],
            // Jen 2 skutečně používané hodnoty (ověřeno proti drd-db-full-v1.sql
            // i textu pravidel) — vyčerpávající = stínová únava (za kolo),
            // nevyčerpávající = reálná únava (paušálně za směnu). Sloupec v DB
            // pořád technicky umí i 'udrzovaci'/'zaostreni_vule' (viz migrace
            // 0010), appka je ale záměrně nenabízí, protože nejsou skutečné
            // hodnoty typu únavy — viz udrzovaci pole a komentář v migraci.
            ['name' => 'typ_unavy', 'label' => 'Typ únavy při sesílání (vyčerpávající = stínová / nevyčerpávající = reálná)', 'type' => 'select', 'options' => ['vycerpavajici', 'nevycerpavajici']],
            ['name' => 'udrzovaci', 'label' => 'Vyžaduje udržovací magenergii, dokud kouzlo běží (nezávislé na typu únavy výš)', 'type' => 'checkbox'],
            ['name' => 'past_id', 'label' => 'Past (záchranný hod)', 'type' => 'select_fk', 'ref_table' => 'pasti', 'ref_label' => 'vlastnosti'],
        ],
        // Mana (cena_magenergie) a Dosah jsou volný text ("5 magů", "dotek",
        // "3 magy za první blesk...") — filtr na nich je speciální případ
        // řešený přímo v editor.php (parsování úvodního čísla v PHP), ne
        // přes generický 'filter' mechanismus výš, a stejně tak filtr na
        // Povolání (jde přes seznam_kouzel_id → seznamy_kouzel.povolani_id).
        'special_filters' => ['kouzla_mana_dosah_povolani'],
        'relations' => [
            ['join_table' => 'kouzlo_obor_magie', 'own_fk' => 'kouzlo_id', 'other_fk' => 'obor_id', 'other_table' => 'obory_magie', 'other_label' => 'nazev', 'label' => 'Obory magie'],
            ['join_table' => 'kouzlo_efekty', 'own_fk' => 'kouzlo_id', 'other_fk' => 'efekt_id', 'other_table' => 'efekty', 'other_label' => 'nazev', 'label' => 'Efekty'],
        ],
    ],

    'zvlastni_schopnosti' => [
        'label' => 'Schopnosti a dovednosti',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'vysledky_testu' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['druh' => 'Druh', 'vlastnost_id' => 'Vlastnost', 'uroven_od' => 'Od Lv.'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'druh', 'label' => 'Druh', 'type' => 'select', 'options' => ['schopnost', 'dovednost'], 'quick' => true, 'filter' => 'select'],
            ['name' => 'vlastnost_id', 'label' => 'Klíčová vlastnost', 'type' => 'select_fk', 'ref_table' => 'vlastnosti', 'ref_label' => 'nazev', 'quick' => true, 'filter' => 'select_fk'],
            ['name' => 'uroven_od', 'label' => 'Od úrovně', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ...dracak_kostky_pole(),
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'mechanika', 'label' => 'Mechanika (poznámka navíc, cíl efektu...)', 'type' => 'text'],
            ['name' => 'vyzaduje_id', 'label' => 'Vyžaduje (prerekvizita)', 'type' => 'select_fk', 'ref_table' => 'zvlastni_schopnosti', 'ref_label' => 'nazev'],
            ['name' => 'past_id', 'label' => 'Past (záchranný hod)', 'type' => 'select_fk', 'ref_table' => 'pasti', 'ref_label' => 'vlastnosti'],
        ],
        'relations' => [
            ['join_table' => 'schopnost_povolani', 'own_fk' => 'schopnost_id', 'other_fk' => 'povolani_id', 'other_table' => 'povolani', 'other_label' => 'nazev', 'label' => 'Povolání'],
            ['join_table' => 'schopnost_efekty', 'own_fk' => 'schopnost_id', 'other_fk' => 'efekt_id', 'other_table' => 'efekty', 'other_label' => 'nazev', 'label' => 'Efekty'],
        ],
    ],

    'predmety' => [
        'label' => 'Vybavení',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['typ' => 'Typ', 'utocnost' => 'Útočnost', 'zraneni' => 'Zranění', 'oc' => 'Obrana', 'dosah' => 'Dosah'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['zbran', 'zbroj', 'surovina', 'artefakt'], 'quick' => true, 'filter' => 'select'],
            ['name' => 'utocnost', 'label' => 'Útočnost', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ['name' => 'zraneni', 'label' => 'Zranění', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ['name' => 'oc', 'label' => 'Obrana (OČ)', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'kategorie_zbrane', 'label' => 'Kategorie zbraně (sečná/bodná/drtivá/vrhací/střelná)', 'type' => 'text'],
            ['name' => 'dosah', 'label' => 'Dosah (na blízko, např. "1,5 sáhu")', 'type' => 'text'],
            ['name' => 'dostrel_efektivni', 'label' => 'Dostřel efektivní (na dálku)', 'type' => 'number'],
            ['name' => 'dostrel_maximalni', 'label' => 'Dostřel maximální', 'type' => 'number'],
            ['name' => 'sil_pozadavek', 'label' => 'Požadavek na Sílu (např. "SIL +2 a vyšší")', 'type' => 'text'],
            ['name' => 'uc', 'label' => 'ÚČ (jen improvizované zbraně, PPP)', 'type' => 'number'],
            ['name' => 'typ_pro_vyrazeni_dveri', 'label' => 'Typ pro vyražení dveří', 'type' => 'select', 'options' => ['ostre_bodne', 'ostre_drtive', 'tupe_drtive']],
            ['name' => 'vaha', 'label' => 'Váha', 'type' => 'number'],
            ['name' => 'cena', 'label' => 'Cena', 'type' => 'number', 'filter' => 'range'],
        ],
        'relations' => [
            ['join_table' => 'predmet_efekty', 'own_fk' => 'predmet_id', 'other_fk' => 'efekt_id', 'other_table' => 'efekty', 'other_label' => 'nazev', 'label' => 'Efekty'],
        ],
    ],

    'lektvary' => [
        'label' => 'Lektvary a elixíry',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['cena' => 'Cena'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ...dracak_kostky_pole(),
            ['name' => 'popis', 'label' => 'Popis / efekt', 'type' => 'textarea', 'quick' => true],
            ['name' => 'cena', 'label' => 'Cena', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ['name' => 'doba_pripravy', 'label' => 'Doba přípravy', 'type' => 'text'],
            ['name' => 'vyrobce_povolani_id', 'label' => 'Vyrábí povolání', 'type' => 'select_fk', 'ref_table' => 'povolani', 'ref_label' => 'nazev'],
            ['name' => 'past_id', 'label' => 'Past (záchranný hod)', 'type' => 'select_fk', 'ref_table' => 'pasti', 'ref_label' => 'vlastnosti'],
        ],
        'relations' => [
            ['join_table' => 'lektvar_efekty', 'own_fk' => 'lektvar_id', 'other_fk' => 'efekt_id', 'other_table' => 'efekty', 'other_label' => 'nazev', 'label' => 'Efekty'],
            ['join_table' => 'lektvar_suroviny', 'own_fk' => 'lektvar_id', 'other_fk' => 'predmet_id', 'other_table' => 'predmety', 'other_label' => 'nazev', 'label' => 'Suroviny', 'extra_column' => 'mnozstvi', 'extra_type' => 'text', 'extra_placeholder' => 'množství'],
        ],
    ],

    'finty' => [
        'label' => 'Finty',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['typ' => 'Typ', 'pocet_akci' => 'Počet akcí'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['utocna', 'obranna', 'kombinovana'], 'quick' => true, 'filter' => 'select'],
            ...dracak_kostky_pole(),
            ['name' => 'poznamky', 'label' => 'Poznámky', 'type' => 'textarea', 'quick' => true],
            ['name' => 'bonus_iniciativa', 'label' => 'Bonus k iniciativě', 'type' => 'number'],
            ['name' => 'pocet_akci', 'label' => 'Počet akcí', 'type' => 'number'],
            ['name' => 'pozadovana_zbran', 'label' => 'Požadovaná zbraň', 'type' => 'text'],
            ['name' => 'pozadovany_protivnik', 'label' => 'Požadovaný protivník', 'type' => 'text'],
        ],
        'relations' => [
            ['join_table' => 'finta_prerekvizity', 'own_fk' => 'finta_id', 'other_fk' => 'vyzaduje_finta_id', 'other_table' => 'finty', 'other_label' => 'nazev', 'label' => 'Vyžaduje finty'],
        ],
    ],

    'povolani' => [
        'label' => 'Povolání',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['zakladni', 'vetev'], 'quick' => true, 'filter' => 'select'],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'rodic_povolani_id', 'label' => 'Rodičovské povolání (větev od)', 'type' => 'select_fk', 'ref_table' => 'povolani', 'ref_label' => 'nazev'],
            ['name' => 'odemyka_se_od_urovne', 'label' => 'Odemyká se od úrovně', 'type' => 'number'],
            ['name' => 'pouziva_magenergii', 'label' => 'Používá magenergii', 'type' => 'checkbox'],
            ['name' => 'primarni_vlastnost_id', 'label' => 'Primární vlastnost', 'type' => 'select_fk', 'ref_table' => 'vlastnosti', 'ref_label' => 'nazev'],
            ['name' => 'sum_zaklad', 'label' => 'SUM základ', 'type' => 'number'],
            ['name' => 'som_zaklad', 'label' => 'SOM základ', 'type' => 'number'],
            ['name' => 'zsm_zaklad', 'label' => 'ZSM základ', 'type' => 'number'],
        ],
        'relations' => [
            ['join_table' => 'povolani_jazyky', 'own_fk' => 'povolani_id', 'other_fk' => 'jazyk_id', 'other_table' => 'jazyky', 'other_label' => 'nazev', 'label' => 'Jazyky'],
        ],
    ],

    'rasy' => [
        'label' => 'Rasy',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['pohyblivost_zaklad' => 'Pohyblivost (+OBR)'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'pohyblivost_zaklad', 'label' => 'Pohyblivost — základ (finální = tohle + oprava za OBR)', 'type' => 'number', 'quick' => true, 'filter' => 'range'],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'rodic_rasa_id', 'label' => 'Rodičovská rasa (klan/varianta od)', 'type' => 'select_fk', 'ref_table' => 'rasy', 'ref_label' => 'nazev'],
        ],
        'relations' => [
            ['join_table' => 'rasa_schopnosti', 'own_fk' => 'rasa_id', 'other_fk' => 'schopnost_id', 'other_table' => 'zvlastni_schopnosti', 'other_label' => 'nazev', 'label' => 'Rasové schopnosti'],
            ['join_table' => 'rasa_bonusy_vlastnosti', 'own_fk' => 'rasa_id', 'other_fk' => 'vlastnost_id', 'other_table' => 'vlastnosti', 'other_label' => 'nazev', 'label' => 'Bonusy k vlastnostem', 'extra_column' => 'modifikator', 'extra_type' => 'stepper'],
            ['join_table' => 'rasa_povolani', 'own_fk' => 'rasa_id', 'other_fk' => 'povolani_id', 'other_table' => 'povolani', 'other_label' => 'nazev', 'label' => 'Dostupná povolání'],
            ['join_table' => 'rasa_jazyky', 'own_fk' => 'rasa_id', 'other_fk' => 'jazyk_id', 'other_table' => 'jazyky', 'other_label' => 'nazev', 'label' => 'Jazyky'],
        ],
    ],

    'efekty' => [
        'label' => 'Efekty (buff/debuff...)',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['buff', 'debuff', 'dot', 'hot', 'modifikator', 'imunita', 'zranitelnost', 'odolnost'], 'quick' => true, 'filter' => 'select'],
            ['name' => 'hodnota_vzorec', 'label' => 'Hodnota / vzorec', 'type' => 'text', 'quick' => true],
            ['name' => 'cil', 'label' => 'Cíl (životy/OČ/ÚČ...)', 'type' => 'text'],
            ['name' => 'mechanika_aplikace', 'label' => 'Kdy se aplikuje', 'type' => 'select', 'options' => ['jednorazove', 'na_zacatku_kola', 'na_konci_kola', 'pri_zasahu']],
            ['name' => 'trvani', 'label' => 'Trvání', 'type' => 'text'],
            ['name' => 'tooltip_text', 'label' => 'Tooltip text (mouseover v boji)', 'type' => 'textarea'],
        ],
    ],

    'pasti' => [
        'label' => 'Pasti (záchranné hody)',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'row_owned' => true,
        'order_by' => 'id',
        'fields' => [
            ['name' => 'vlastnosti', 'label' => 'Vlastnosti (např. "Odl" nebo "Sil+Odl")', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'obtiznost', 'label' => 'Obtížnost/nebezpečnost', 'type' => 'text', 'quick' => true],
            ['name' => 'poznamka', 'label' => 'Poznámka', 'type' => 'textarea', 'quick' => true],
            ['name' => 'efekt_uspech_id', 'label' => 'Efekt při úspěchu', 'type' => 'select_fk', 'ref_table' => 'efekty', 'ref_label' => 'nazev'],
            ['name' => 'efekt_neuspech_id', 'label' => 'Efekt při neúspěchu', 'type' => 'select_fk', 'ref_table' => 'efekty', 'ref_label' => 'nazev'],
        ],
    ],

    // --- BESTIÁŘ A PJ OBSAH: vždy skryté hráčům, editovatelné jen PJ/adminem ---

    'nestvury' => [
        'label' => 'Příšery (bestiář)',
        'group' => 'bestiar',
        'hidden_from_players' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['velikost' => 'Velikost', 'zivotaschopnost' => 'Živ.', 'uc' => 'ÚČ', 'oc' => 'OČ'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'zivotaschopnost', 'label' => 'Životaschopnost', 'type' => 'text', 'quick' => true],
            ['name' => 'uc', 'label' => 'ÚČ', 'type' => 'text', 'quick' => true],
            ['name' => 'oc', 'label' => 'OČ', 'type' => 'text', 'quick' => true],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'odolnost', 'label' => 'Odolnost', 'type' => 'text'],
            ['name' => 'velikost', 'label' => 'Velikost', 'type' => 'text', 'filter' => 'select'],
            ['name' => 'bojovnost', 'label' => 'Bojovnost', 'type' => 'text'],
            ['name' => 'pohyblivost', 'label' => 'Pohyblivost', 'type' => 'text'],
            ['name' => 'vytrvalost', 'label' => 'Vytrvalost', 'type' => 'text'],
            ['name' => 'inteligence', 'label' => 'Inteligence', 'type' => 'text'],
            ['name' => 'charisma', 'label' => 'Charisma', 'type' => 'text'],
            ['name' => 'poklady', 'label' => 'Poklady', 'type' => 'text'],
            ['name' => 'zkusenost', 'label' => 'Zkušenost', 'type' => 'number', 'filter' => 'range'],
            ['name' => 'ochoceni', 'label' => 'Ochočení', 'type' => 'text'],
            ['name' => 'prostredi', 'label' => 'Prostředí', 'type' => 'text'],
        ],
        'relations' => [
            ['join_table' => 'nestvura_zranitelnosti', 'own_fk' => 'nestvura_id', 'other_fk' => 'kod_zranitelnosti_id', 'other_table' => 'kody_zranitelnosti', 'other_label' => 'nazev', 'label' => 'Zranitelnosti', 'extra_column' => 'modifikator', 'extra_type' => 'text', 'extra_placeholder' => 'např. 1/2'],
            ['join_table' => 'nestvura_schopnosti', 'own_fk' => 'nestvura_id', 'other_fk' => 'schopnost_id', 'other_table' => 'zvlastni_schopnosti', 'other_label' => 'nazev', 'label' => 'Schopnosti'],
        ],
    ],

    'pj_poznamky' => [
        'label' => 'PJ pravidla a poznámky',
        'group' => 'bestiar',
        'hidden_from_players' => true,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'obsah', 'label' => 'Obsah', 'type' => 'textarea', 'quick' => true],
        ],
    ],

    // --- ČÍSELNÍKY: systémová referenční data, jen admin/PJ, generický formulář ---
    // Vlastní jednodušší UI v editoru (tabulka s inline editací), ne stejný
    // vzor jako velké entity — proto tu nejsou 'relations' ani 'filter'.

    'vlastnosti' => ['label' => 'Vlastnosti', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'id'],
    'opravy_za_atribut' => ['label' => 'Opravy za atribut', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'stupen_od'],
    'nosnost_podle_sily' => ['label' => 'Nosnost podle Síly', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'oprava_sil'],
    'narocnost_akci_unava' => ['label' => 'Náročnost akcí (únava)', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'id'],
    'stupne_unavy' => ['label' => 'Stupně únavy', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'prah_nasobek'],
    'odstraneni_unavy' => ['label' => 'Odstranění únavy', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'id'],
    'vysledky_testu' => ['label' => 'Výsledky testu (škála)', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'poradi'],
    // 'velikosti' vynechané záměrně — primární klíč je `kod`, ne `id`,
    // generický editor v1 počítá jen s `id`. Uprav přes phpMyAdmin.
    'obory_magie' => ['label' => 'Obory magie', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'kod'],
    'skupiny_kouzel' => ['label' => 'Skupiny kouzel', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'kody_zranitelnosti' => ['label' => 'Kódy zranitelnosti', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'kod'],
    'jazyky' => ['label' => 'Jazyky', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'presvedceni' => ['label' => 'Přesvědčení', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'zkusenostni_tabulky' => ['label' => 'Zkušenostní tabulky', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'seznamy_kouzel' => ['label' => 'Seznamy kouzel', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
];
