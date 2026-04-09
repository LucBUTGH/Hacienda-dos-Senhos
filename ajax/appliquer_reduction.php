<?php
// appliquer_reduction.php — l'admin applique une réduction sur une prestation d'une réservation
// Appelé en AJAX depuis js/admin.js quand l'admin change le select de réduction sur une prestation.
// Les réductions sont stockées dans reservations.json sous la clé "reductions" :
// un objet dont les clés sont les idPrestation et les valeurs le pourcentage (ex: {"3": 20, "5": 10})
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin(); // seul l'admin peut appliquer des réductions

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
// Whitelist des valeurs autorisées : on n'accepte pas n'importe quel pourcentage
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
// On ne peut modifier que les réservations validées (pas celles en attente ou refusées)
if ($reservations[$index]['statut'] !== 'validee') {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation non validée.']);
    exit;
}

// Sécurité : vérifier que la prestation appartient bien à cette réservation
if (!in_array($idPrestation, $reservations[$index]['prestations'])) {
    echo json_encode(['ok' => false, 'erreur' => 'Cette prestation n\'est pas dans la réservation.']);
    exit;
}

// Initialiser le champ reductions s'il n'existe pas encore dans le JSON
if (!isset($reservations[$index]['reductions']) || !is_array($reservations[$index]['reductions'])) {
    $reservations[$index]['reductions'] = [];
}

if ($reduction === 0) {
    // Supprimer la réduction (revenir au prix normal)
    unset($reservations[$index]['reductions'][$idPrestation]);
    // Cast en (object) pour que json_encode produise {} et non [] quand le tableau est vide
    $reservations[$index]['reductions'] = (object) $reservations[$index]['reductions'];
} else {
    // Appliquer ou mettre à jour la réduction sur cette prestation
    $reservations[$index]['reductions'][$idPrestation] = $reduction;
}

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true]);
