<?php
require_once '../helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$nom   = trim($_POST['nom']   ?? '');
$email = trim($_POST['email'] ?? '');
$debut = trim($_POST['date_debut'] ?? '');
$fin   = trim($_POST['date_fin']   ?? '');
$nb    = intval($_POST['nb_personnes'] ?? 0);
$prestats = array_map('intval', $_POST['prestations'] ?? []);
$activ    = trim($_POST['activites_souhaitees'] ?? '');

if (!$nom || !$email || !$debut || !$fin || $nb <= 0) {
    echo json_encode(['ok' => false, 'erreur' => 'Tous les champs obligatoires doivent être remplis.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erreur' => 'Adresse email invalide.']);
    exit;
}

if ($fin <= $debut) {
    echo json_encode(['ok' => false, 'erreur' => 'La date de départ doit être après la date d\'arrivée.']);
    exit;
}

if (strtotime($debut) < strtotime('today')) {
    echo json_encode(['ok' => false, 'erreur' => 'La date d\'arrivée ne peut pas être dans le passé.']);
    exit;
}

$reservations = lireJson('reservations.json');

$nouvelle = [
    'idResa'               => prochainId($reservations, 'idResa'),
    'nom'                  => $nom,
    'email'                => $email,
    'date_debut'           => $debut,
    'date_fin'             => $fin,
    'nb_personnes'         => $nb,
    'prestations'          => $prestats,
    'activites_souhaitees' => $activ,
    'statut'               => 'en_attente',
    'idChambre'            => null,
    'arrhes'               => 0,
    'reductions'           => (object) [],
];

$reservations[] = $nouvelle;

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, veuillez réessayer.']);
    exit;
}

echo json_encode(['ok' => true]);
