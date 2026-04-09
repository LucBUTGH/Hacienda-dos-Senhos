<?php
// message_activite.php — le client enregistre son message sur une activité confirmée
// Appelé en AJAX depuis js/client.js quand le client clique sur "Enregistrer" sous son message.
// Chaque participant d'une activité prévue peut laisser un message visible par tous les autres participants.
// Le client ne peut modifier que son propre message (vérifié via son idResa en session).
require_once '../helpers.php';
require_once '../auth.php';
requireClient();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idActivitePrevue = intval($_POST['idActivitePrevue'] ?? 0);
$message          = trim($_POST['message'] ?? ''); // message libre, peut être vide pour l'effacer

if (!$idActivitePrevue) {
    echo json_encode(['ok' => false, 'erreur' => 'Données invalides.']);
    exit;
}

// Retrouver la réservation du client connecté pour identifier son entrée dans les participants
$clients = lireJson('clients.json');
$client  = null;
foreach ($clients as $cl) {
    if ($cl['idClient'] === $_SESSION['idClient']) { $client = $cl; break; }
}

$activitesPrevues = lireJson('activites_prevues.json');

// Parcours avec référence (&) pour pouvoir modifier directement le tableau en mémoire
$found = false;
foreach ($activitesPrevues as &$ap) {
    if ($ap['idActivitePrevue'] !== $idActivitePrevue) continue;
    // Chercher le client parmi les participants de cette activité
    foreach ($ap['participants'] as &$p) {
        if ($p['idResa'] == $client['idResa']) {
            $p['message'] = $message; // mise à jour du message
            $found = true;
            break;
        }
    }
    unset($p); // libérer la référence interne
    break;
}
unset($ap); // libérer la référence externe

// Si le client n'est pas participant de cette activité, on refuse (sécurité)
if (!$found) {
    echo json_encode(['ok' => false, 'erreur' => 'Activité introuvable ou vous n\'êtes pas participant.']);
    exit;
}

if (!ecrireJson('activites_prevues.json', $activitesPrevues)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
