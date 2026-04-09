<?php
// appliquer_reduction.php — l'admin applique une réduction sur une prestation d'une réservation
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idResa      = intval($_POST['idResa'] ?? 0);
$idPrestation = intval($_POST['idPrestation'] ?? 0);
$reduction   = intval($_POST['reduction'] ?? 0);

if (!$idResa || !$idPrestation) {
    echo json_encode(['ok' => false, 'erreur' => 'Données manquantes.']);
    exit;
}
if (!in_array($reduction, [0, 10, 20, 50])) {
    echo json_encode(['ok' => false, 'erreur' => 'Réduction invalide (0, 10, 20 ou 50 uniquement).']);
    exit;
}

$reservations = lireJson('reservations.json');
$index = null;
foreach ($reservations as $i => $r) {
    if ($r['idResa'] === $idResa) { $index = $i; break; }
}

if ($index === null) {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation introuvable.']);
    exit;
}
if ($reservations[$index]['statut'] !== 'validee') {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation non validée.']);
    exit;
}

// Vérifier que la prestation est dans la réservation
if (!in_array($idPrestation, $reservations[$index]['prestations'])) {
    echo json_encode(['ok' => false, 'erreur' => 'Cette prestation n\'est pas dans la réservation.']);
    exit;
}

// Initialiser reductions si absent
if (!isset($reservations[$index]['reductions']) || !is_array($reservations[$index]['reductions'])) {
    $reservations[$index]['reductions'] = [];
}

if ($reduction === 0) {
    unset($reservations[$index]['reductions'][$idPrestation]);
    // Reindexer pour garder un objet JSON propre
    $reservations[$index]['reductions'] = (object) $reservations[$index]['reductions'];
} else {
    $reservations[$index]['reductions'][$idPrestation] = $reduction;
}

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
