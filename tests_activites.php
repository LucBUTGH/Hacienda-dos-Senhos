<?php
/**
 * Tests de la gestion des activités
 * Exécuter : php tests_activites.php
 */

require_once __DIR__ . '/helpers.php';

$ok = 0;
$ko = 0;

function test(string $nom, bool $condition, string $detail = '') {
    global $ok, $ko;
    if ($condition) {
        echo "  OK  $nom\n";
        $ok++;
    } else {
        echo "  FAIL  $nom" . ($detail ? " — $detail" : "") . "\n";
        $ko++;
    }
}

// ── Chargement des données ──────────────────────────────────────────
$activites        = lireJson('activites.json');
$demandes         = lireJson('demandes_activites.json');
$activitesPrevues = lireJson('activites_prevues.json');
$reservations     = lireJson('reservations.json');
$clients          = lireJson('clients.json');
$animateurs       = lireJson('animateurs.json');

// Index
$activitesIndex = [];
foreach ($activites as $a) $activitesIndex[$a['idActivite']] = $a;
$resaIndex = [];
foreach ($reservations as $r) $resaIndex[$r['idResa']] = $r;

// =====================================================================
echo "\n=== 1. Intégrité des données ===\n";
// =====================================================================

test('Activités chargées', count($activites) > 0, 'activites.json vide');
test('Demandes chargées', count($demandes) > 0, 'demandes_activites.json vide');
test('Réservations chargées', count($reservations) > 0);
test('Clients chargés', count($clients) > 0);
test('Animateurs chargés', count($animateurs) > 0);

// Vérifier que chaque activité a les champs requis
foreach ($activites as $a) {
    $hasFields = isset($a['idActivite'], $a['nom'], $a['granularites'], $a['tarifs'], $a['type_special']);
    test("Activité #{$a['idActivite']} ({$a['nom']}) — champs complets", $hasFields);

    // Vérifier que chaque granularité a un tarif correspondant
    foreach ($a['granularites'] as $g) {
        $hasTarif = isset($a['tarifs'][$g]);
        test("  Tarif pour '$g'", $hasTarif, "granularité '$g' sans tarif dans {$a['nom']}");
    }
}

// =====================================================================
echo "\n=== 2. Cohérence des demandes ===\n";
// =====================================================================

foreach ($demandes as $d) {
    $id = $d['idDemande'];

    // La demande pointe vers une activité existante
    $actExiste = isset($activitesIndex[$d['idActivite']]);
    test("Demande #$id — activité #{$d['idActivite']} existe", $actExiste);

    if ($actExiste) {
        $act = $activitesIndex[$d['idActivite']];

        // La granularité demandée est valide pour cette activité
        $granOk = in_array($d['granularite'], $act['granularites']);
        test("Demande #$id — granularité '{$d['granularite']}' valide", $granOk,
            "granularités possibles : " . implode(', ', $act['granularites']));

        // Vérifier les champs extra
        if (in_array('niveau', $act['champs_extra'])) {
            $niveauOk = !empty($d['niveau']);
            test("Demande #$id — niveau renseigné", $niveauOk, 'champ niveau requis mais vide');
        }
        if (in_array('difficulte', $act['champs_extra'])) {
            $diffOk = !empty($d['difficulte']);
            test("Demande #$id — difficulté renseignée", $diffOk, 'champ difficulte requis mais vide');
        }
        if (in_array('mode', $act['champs_extra'])) {
            $modeOk = in_array($d['mode'], ['interne', 'ouvert']);
            test("Demande #$id — mode valide", $modeOk, "mode '{$d['mode']}' invalide");
        }
    }

    // La demande pointe vers une réservation existante et validée
    $resaExiste = isset($resaIndex[$d['idResa']]);
    test("Demande #$id — réservation #{$d['idResa']} existe", $resaExiste);
    if ($resaExiste) {
        $resa = $resaIndex[$d['idResa']];
        test("Demande #$id — réservation validée", $resa['statut'] === 'validee',
            "statut = {$resa['statut']}");

        // nb_personnes ne dépasse pas la capacité de la résa
        $nbOk = $d['nb_personnes'] <= $resa['nb_personnes'];
        test("Demande #$id — nb_personnes ({$d['nb_personnes']}) <= résa ({$resa['nb_personnes']})", $nbOk);
    }
}

// Vérifier pas de doublons (même idResa + même idActivite)
$combos = [];
$doublons = false;
foreach ($demandes as $d) {
    $key = $d['idResa'] . '-' . $d['idActivite'];
    if (isset($combos[$key])) { $doublons = true; break; }
    $combos[$key] = true;
}
test('Pas de demande en doublon (même résa + même activité)', !$doublons);

// =====================================================================
echo "\n=== 3. Activités prévues (validées) ===\n";
// =====================================================================

foreach ($activitesPrevues as $ap) {
    $id = $ap['idActivitePrevue'];

    // Activité référencée existe
    test("ActivitéPrévue #$id — activité #{$ap['idActivite']} existe",
        isset($activitesIndex[$ap['idActivite']]));

    // Animateur valide
    $animNoms = array_column($animateurs, 'nom');
    test("ActivitéPrévue #$id — animateur '{$ap['animateur']}' existe",
        in_array($ap['animateur'], $animNoms));

    // Toutes les demandes référencées existent
    $demandesIndex2 = array_column($demandes, null, 'idDemande');
    foreach ($ap['idDemandes'] as $idD) {
        test("ActivitéPrévue #$id — demande #$idD existe", isset($demandesIndex2[$idD]));
    }

    // Les demandes référencées pointent toutes vers la même activité
    $activitesDesDemandes = [];
    foreach ($ap['idDemandes'] as $idD) {
        if (isset($demandesIndex2[$idD])) {
            $activitesDesDemandes[] = $demandesIndex2[$idD]['idActivite'];
        }
    }
    $memeActivite = count(array_unique($activitesDesDemandes)) <= 1;
    test("ActivitéPrévue #$id — toutes les demandes pour la même activité", $memeActivite);

    // Participants correspondent aux demandes
    $resasDesParticipants = array_column($ap['participants'], 'idResa');
    $resasDesDemandes = [];
    foreach ($ap['idDemandes'] as $idD) {
        if (isset($demandesIndex2[$idD])) {
            $resasDesDemandes[] = $demandesIndex2[$idD]['idResa'];
        }
    }
    sort($resasDesParticipants);
    sort($resasDesDemandes);
    test("ActivitéPrévue #$id — participants = demandes",
        $resasDesParticipants === $resasDesDemandes,
        'participants: ' . implode(',', $resasDesParticipants) . ' vs demandes: ' . implode(',', $resasDesDemandes));

    // Vérifier que la date est dans la période de séjour de chaque participant
    foreach ($ap['participants'] as $p) {
        $resa = $resaIndex[$p['idResa']] ?? null;
        if ($resa) {
            $dateDans = $ap['date'] >= $resa['date_debut'] && $ap['date'] < $resa['date_fin'];
            test("ActivitéPrévue #$id — date {$ap['date']} dans séjour résa #{$p['idResa']} ({$resa['date_debut']} → {$resa['date_fin']})",
                $dateDans);
        }
    }

    // Activité de groupe : vérifier le minimum de participants
    $actInfo = $activitesIndex[$ap['idActivite']] ?? null;
    if ($actInfo && $actInfo['type_special'] === 'groupe') {
        $totalPers = 0;
        foreach ($ap['idDemandes'] as $idD) {
            if (isset($demandesIndex2[$idD])) {
                $totalPers += $demandesIndex2[$idD]['nb_personnes'];
            }
        }
        $minReq = $ap['nb_min'] ?: ($actInfo['nb_min_personnes'] ?? 0);
        test("ActivitéPrévue #$id — groupe : $totalPers personnes >= $minReq minimum",
            $totalPers >= $minReq);
    }
}

// =====================================================================
echo "\n=== 4. Logique activites_du_jour ===\n";
// =====================================================================

// Simuler la logique de activites_du_jour.php pour le 27/03/2026
$dateTest = '2026-03-27';

$demandesSatisfaites = [];
foreach ($activitesPrevues as $ap) {
    if ($ap['date'] === $dateTest) {
        foreach ($ap['idDemandes'] as $idD) {
            $demandesSatisfaites[$idD] = true;
        }
    }
}

$demandesVisibles = [];
foreach ($demandes as $d) {
    if (isset($demandesSatisfaites[$d['idDemande']])) continue;
    $resa = $resaIndex[$d['idResa']] ?? null;
    if (!$resa || $resa['statut'] !== 'validee') continue;
    if ($resa['date_debut'] > $dateTest || $resa['date_fin'] <= $dateTest) continue;
    $demandesVisibles[] = $d;
}

echo "Date testée : $dateTest\n";
echo "Demandes satisfaites ce jour : " . count($demandesSatisfaites) . "\n";
echo "Demandes visibles (non satisfaites, séjour actif) : " . count($demandesVisibles) . "\n";

foreach ($demandesVisibles as $d) {
    $actNom = $activitesIndex[$d['idActivite']]['nom'] ?? '?';
    $clientNom = $resaIndex[$d['idResa']]['nom'] ?? '?';
    echo "  → Demande #{$d['idDemande']} : $actNom — $clientNom ({$d['nb_personnes']} pers.)\n";
}

// Vérifier qu'aucune demande satisfaite ne réapparaît
foreach ($demandesVisibles as $d) {
    test("Demande #{$d['idDemande']} n'est pas déjà satisfaite",
        !isset($demandesSatisfaites[$d['idDemande']]));
}

// Vérifier que les demandes de réservations hors période sont exclues
foreach ($demandes as $d) {
    $resa = $resaIndex[$d['idResa']] ?? null;
    if ($resa && ($resa['date_debut'] > $dateTest || $resa['date_fin'] <= $dateTest)) {
        $presente = false;
        foreach ($demandesVisibles as $dv) {
            if ($dv['idDemande'] === $d['idDemande']) { $presente = true; break; }
        }
        test("Demande #{$d['idDemande']} hors période exclue", !$presente);
    }
}

// =====================================================================
echo "\n=== 5. Statuts des demandes vs activités prévues ===\n";
// =====================================================================

// Vérifier la cohérence : si une demande est dans activites_prevues, son statut devrait être 'validee'
$demandesDansAP = [];
foreach ($activitesPrevues as $ap) {
    foreach ($ap['idDemandes'] as $idD) {
        $demandesDansAP[$idD] = true;
    }
}

$demandesIndex3 = array_column($demandes, null, 'idDemande');
foreach ($demandesDansAP as $idD => $_) {
    if (isset($demandesIndex3[$idD])) {
        $statut = $demandesIndex3[$idD]['statut'];
        test("Demande #$idD dans activité prévue — statut = 'validee'",
            $statut === 'validee',
            "statut actuel = '$statut'");
    }
}

// Demandes en_attente ne devraient pas être dans activites_prevues
foreach ($demandes as $d) {
    if ($d['statut'] === 'en_attente') {
        test("Demande #{$d['idDemande']} en_attente — pas dans activités prévues",
            !isset($demandesDansAP[$d['idDemande']]));
    }
}

// =====================================================================
echo "\n══════════════════════════════════\n";
echo "Résultat : $ok OK / $ko FAIL\n";
echo "══════════════════════════════════\n\n";

exit($ko > 0 ? 1 : 0);
