<?php
// activites_du_jour.php — liste des demandes d'activités non satisfaites pour une date donnée
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin();

header('Content-Type: application/json');

$date = trim($_GET['date'] ?? '');
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => false, 'erreur' => 'Date invalide']);
    exit;
}

$reservations     = lireJson('reservations.json');
$demandes         = lireJson('demandes_activites.json');
$activitesPrevues = lireJson('activites_prevues.json');
$activites        = lireJson('activites.json');
$clients          = lireJson('clients.json');

// Index par ID
$activitesIndex = [];
foreach ($activites as $a) {
    $activitesIndex[$a['idActivite']] = $a;
}
$resaIndex = [];
foreach ($reservations as $r) {
    $resaIndex[$r['idResa']] = $r;
}
$clientsParResa = [];
foreach ($clients as $cl) {
    $clientsParResa[$cl['idResa']] = $cl;
}

// Demandes déjà satisfaites (toutes dates confondues — une demande ne peut être planifiée qu'une seule fois)
$demandesSatisfaites = [];
foreach ($activitesPrevues as $ap) {
    foreach ($ap['idDemandes'] as $idD) {
        $demandesSatisfaites[$idD] = true;
    }
}

// Construire la liste des demandes à afficher (réplication : visible chaque jour du séjour)
$result = [];
foreach ($demandes as $d) {
    if (isset($demandesSatisfaites[$d['idDemande']])) continue;

    $resa = $resaIndex[$d['idResa']] ?? null;
    // Seuls les clients avec réservation validée, dont le séjour inclut cette date
    if (!$resa || $resa['statut'] !== 'validee') continue;
    if ($resa['date_debut'] > $date || $resa['date_fin'] <= $date) continue;

    $activite = $activitesIndex[$d['idActivite']] ?? null;
    $client   = $clientsParResa[$d['idResa']] ?? null;

    $result[] = [
        'idDemande'   => $d['idDemande'],
        'idResa'      => $d['idResa'],
        'nomClient'   => $client['nom'] ?? 'Inconnu',
        'nbPersonnes' => $d['nb_personnes'],
        'granularite' => $d['granularite'] ?? null,
        'niveau'      => $d['niveau'] ?? null,
        'difficulte'  => $d['difficulte'] ?? null,
        'mode'        => $d['mode'] ?? null,
        'preferences' => $d['preferences'],
        'activite'    => $activite,
    ];
}

// Regrouper par type d'activité pour faciliter la validation groupée
$parActivite = [];
foreach ($result as $item) {
    $id = $item['activite']['idActivite'];
    if (!isset($parActivite[$id])) {
        $parActivite[$id] = [
            'activite' => $item['activite'],
            'demandes' => [],
        ];
    }
    $parActivite[$id]['demandes'][] = $item;
}

echo json_encode(['ok' => true, 'groupes' => array_values($parActivite), 'date' => $date]);
