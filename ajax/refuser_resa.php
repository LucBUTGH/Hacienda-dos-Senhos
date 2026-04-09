<?php
// refuser_resa.php — l'admin refuse une réservation en attente
// Appelé en AJAX depuis js/admin.js quand l'admin clique sur "Refuser".
// Simple changement de statut : la réservation reste dans le fichier mais ne peut plus être traitée.
// Aucun compte client n'est créé (contrairement à valider_resa.php).
require_once '../helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idResa = intval($_POST['idResa'] ?? 0);

if (!$idResa) {
    echo json_encode(['ok' => false, 'erreur' => 'Données manquantes.']);
    exit;
}

$reservations = lireJson('reservations.json');

// Retrouver la réservation par son index pour pouvoir la modifier
$index = null;
foreach ($reservations as $i => $r) {
    if ($r['idResa'] === $idResa) { $index = $i; break; }
}

if ($index === null) {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation introuvable.']);
    exit;
}

// Sécurité : on ne peut refuser qu'une réservation encore en attente
if ($reservations[$index]['statut'] !== 'en_attente') {
    echo json_encode(['ok' => false, 'erreur' => 'Cette réservation a déjà été traitée.']);
    exit;
}

$reservations[$index]['statut'] = 'refusee';

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, veuillez réessayer.']);
    exit;
}

echo json_encode(['ok' => true]);
