<?php
// activites_du_jour.php — liste des demandes d'activités en attente pour une date donnée
// Appelé en AJAX depuis js/admin.js quand l'admin sélectionne une date dans la section activités.
// Retourne les demandes regroupées par type d'activité pour que l'admin puisse les valider ensemble.
// Une demande apparaît pour chaque jour du séjour du client (pas seulement le jour d'arrivée).
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin();

header('Content-Type: application/json');

// Validation stricte du format de date pour éviter des injections ou des erreurs silencieuses
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

// Construction d'index par ID pour des lookups rapides sans boucles imbriquées
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

// Construire un set des demandes déjà planifiées dans une activité prévue
// Une demande déjà planifiée ne doit plus apparaître dans la liste
$demandesSatisfaites = [];
foreach ($activitesPrevues as $ap) {
    foreach ($ap['idDemandes'] as $idD) {
        $demandesSatisfaites[$idD] = true;
    }
}

// Filtrer les demandes à afficher :
// - non encore planifiées
// - appartenant à un client dont le séjour inclut la date demandée (date_debut <= date < date_fin)
// - réservation validée (pas en attente ou refusée)
$result = [];
foreach ($demandes as $d) {
    if (isset($demandesSatisfaites[$d['idDemande']])) continue; // déjà planifiée, on passe

    $resa = $resaIndex[$d['idResa']] ?? null;
    if (!$resa || $resa['statut'] !== 'validee') continue; // réservation invalide
    if ($resa['date_debut'] > $date || $resa['date_fin'] <= $date) continue; // client pas présent ce jour-là

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

// Regroupement par type d'activité : l'admin valide des groupes homogènes (ex: tous les tennis ensemble)
// Le JS affiche ensuite chaque groupe avec ses cases à cocher pour sélectionner les participants
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
