<?php
// commander_prestation.php — le client met à jour ses prestations (sans validation admin)
require_once '../helpers.php';
require_once '../auth.php';
requireClient();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$prestationsChoisies = array_map('intval', $_POST['prestations'] ?? []);

// Vérifier que les IDs existent
$prestationsValides = lireJson('prestations.json');
$idsValides = array_column($prestationsValides, 'idPrestation');
foreach ($prestationsChoisies as $id) {
    if (!in_array($id, $idsValides)) {
        echo json_encode(['ok' => false, 'erreur' => "Prestation #$id inconnue."]);
        exit;
    }
}

// Trouver le client et sa réservation
$clients = lireJson('clients.json');
$client = null;
foreach ($clients as $cl) {
    if ($cl['idClient'] === $_SESSION['idClient']) { $client = $cl; break; }
}

$reservations = lireJson('reservations.json');
$index = null;
foreach ($reservations as $i => $r) {
    if ($r['idResa'] === $client['idResa']) { $index = $i; break; }
}

if ($index === null || $reservations[$index]['statut'] !== 'validee') {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation non trouvée ou non validée.']);
    exit;
}

// Mettre à jour les prestations
$reservations[$index]['prestations'] = $prestationsChoisies;

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
