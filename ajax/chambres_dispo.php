<?php
require_once '../helpers.php';

header('Content-Type: application/json');

$debut = trim($_GET['debut'] ?? '');
$fin   = trim($_GET['fin']   ?? '');

if (!$debut || !$fin) {
    echo json_encode(['ok' => false, 'chambres' => []]);
    exit;
}

$nbPersonnes  = intval($_GET['nb_personnes'] ?? 0);
$chambres     = lireJson('chambres.json');
$reservations = lireJson('reservations.json');
$dispo        = chambresDisponibles($chambres, $reservations, $debut, $fin);

if ($nbPersonnes > 0) {
    $dispo = array_filter($dispo, fn($ch) => $ch['capacite'] >= $nbPersonnes);
}

echo json_encode(['ok' => true, 'chambres' => array_values($dispo)]);
