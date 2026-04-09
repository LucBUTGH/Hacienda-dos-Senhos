<?php
// commander_prestation.php — le client met à jour ses prestations (sans validation admin)
// Appelé en AJAX depuis js/client.js quand le client coche/décoche des prestations dans son espace.
// Contrairement aux activités, les prestations sont modifiables librement par le client à tout moment.
// Le tableau de prestations dans la réservation est entièrement remplacé à chaque appel.
require_once '../helpers.php';
require_once '../auth.php';
requireClient(); // seul un client connecté peut modifier ses prestations

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

// Tableau des IDs de prestations cochées (peut être vide si tout est décoché)
$prestationsChoisies = array_map('intval', $_POST['prestations'] ?? []);

// Vérification que chaque ID envoyé correspond à une vraie prestation du catalogue
// Empêche un client malveillant d'injecter un ID inventé
$prestationsValides = lireJson('prestations.json');
$idsValides = array_column($prestationsValides, 'idPrestation');
foreach ($prestationsChoisies as $id) {
    if (!in_array($id, $idsValides)) {
        echo json_encode(['ok' => false, 'erreur' => "Prestation #$id inconnue."]);
        exit;
    }
}

// Retrouver la réservation du client connecté via $_SESSION['idClient']
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

// On ne peut commander des prestations que sur une réservation validée
if ($index === null || $reservations[$index]['statut'] !== 'validee') {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation non trouvée ou non validée.']);
    exit;
}

// Remplacement complet du tableau de prestations (pas d'ajout/suppression unitaire)
$reservations[$index]['prestations'] = $prestationsChoisies;

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
