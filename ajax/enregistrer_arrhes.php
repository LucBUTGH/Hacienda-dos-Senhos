<?php
// enregistrer_arrhes.php — l'admin enregistre le montant d'arrhes versées par un client
// Appelé en AJAX depuis js/admin.js quand l'admin saisit et confirme le montant des arrhes.
// Les arrhes sont une avance de 30% sur l'hébergement, versée à la réservation.
// Ce montant est ensuite déduit du total dans la facture affichée dans client.php.
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin(); // seul l'admin peut enregistrer les arrhes

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idResa = intval($_POST['idResa'] ?? 0);
$arrhes = floatval($_POST['arrhes'] ?? 0); // floatval car le montant peut être décimal

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
// Les arrhes ne peuvent être enregistrées que sur une réservation validée
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
