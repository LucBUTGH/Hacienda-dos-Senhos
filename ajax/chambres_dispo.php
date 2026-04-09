<?php
// chambres_dispo.php — retourne les chambres disponibles pour une période et un nombre de personnes
// Appelé en AJAX depuis js/admin.js quand l'admin ouvre la liste de choix de chambre pour une réservation.
// Utilisé uniquement par l'admin (pas de requireAdmin ici car appelé dynamiquement sans session dans certains contextes).
require_once '../helpers.php';

header('Content-Type: application/json');

$debut = trim($_GET['debut'] ?? '');
$fin   = trim($_GET['fin']   ?? '');

if (!$debut || !$fin) {
    echo json_encode(['ok' => false, 'chambres' => []]);
    exit;
}

// Filtrage optionnel par capacité : si nb_personnes est fourni, on exclut les chambres trop petites
$nbPersonnes  = intval($_GET['nb_personnes'] ?? 0);
$chambres     = lireJson('chambres.json');
$reservations = lireJson('reservations.json');

// chambresDisponibles() (définie dans helpers.php) exclut les chambres dont une résa validée chevauche la période
$dispo = chambresDisponibles($chambres, $reservations, $debut, $fin);

if ($nbPersonnes > 0) {
    $dispo = array_filter($dispo, fn($ch) => $ch['capacite'] >= $nbPersonnes);
}

// array_values() pour réindexer le tableau après array_filter (évite des trous d'index en JSON)
echo json_encode(['ok' => true, 'chambres' => array_values($dispo)]);
