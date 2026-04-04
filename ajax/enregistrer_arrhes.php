<?php
// enregistrer_arrhes.php — l'admin enregistre un montant d'arrhes pour une réservation
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idResa = intval($_POST['idResa'] ?? 0);
$arrhes = floatval($_POST['arrhes'] ?? 0);

if (!$idResa) {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation manquante.']);
    exit;
}
if ($arrhes < 0) {
    echo json_encode(['ok' => false, 'erreur' => 'Le montant des arrhes ne peut pas être négatif.']);
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

$reservations[$index]['arrhes'] = $arrhes;

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
