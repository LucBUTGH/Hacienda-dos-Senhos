<?php
// message_activite.php — le client enregistre son message sur une activité validée
require_once '../helpers.php';
require_once '../auth.php';
requireClient();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idActivitePrevue = intval($_POST['idActivitePrevue'] ?? 0);
$message          = trim($_POST['message'] ?? '');

if (!$idActivitePrevue) {
    echo json_encode(['ok' => false, 'erreur' => 'Données invalides.']);
    exit;
}

// Trouver la réservation du client connecté
$clients = lireJson('clients.json');
$client  = null;
foreach ($clients as $cl) {
    if ($cl['idClient'] === $_SESSION['idClient']) { $client = $cl; break; }
}

$activitesPrevues = lireJson('activites_prevues.json');

$found = false;
foreach ($activitesPrevues as &$ap) {
    if ($ap['idActivitePrevue'] !== $idActivitePrevue) continue;
    foreach ($ap['participants'] as &$p) {
        if ($p['idResa'] == $client['idResa']) {
            $p['message'] = $message;
            $found = true;
            break;
        }
    }
    unset($p);
    break;
}
unset($ap);

if (!$found) {
    echo json_encode(['ok' => false, 'erreur' => 'Activité introuvable ou vous n\'êtes pas participant.']);
    exit;
}

if (!ecrireJson('activites_prevues.json', $activitesPrevues)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
