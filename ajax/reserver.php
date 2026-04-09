<?php
// reserver.php — soumission du formulaire de réservation depuis index.php (visiteur non connecté)
// Ce fichier est appelé en AJAX par js/main.js quand le visiteur clique sur "Envoyer la demande".
// Aucune authentification requise : n'importe qui peut faire une demande.
require_once '../helpers.php';

header('Content-Type: application/json');

// Seul le POST est accepté (formulaire HTML)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

// Récupération et assainissement des données du formulaire
$nom   = trim($_POST['nom']   ?? '');
$email = trim($_POST['email'] ?? '');
$debut = trim($_POST['date_debut'] ?? '');
$fin   = trim($_POST['date_fin']   ?? '');
$nb    = intval($_POST['nb_personnes'] ?? 0); // intval protège contre les injections sur ce champ numérique
$prestats = array_map('intval', $_POST['prestations'] ?? []); // tableau d'IDs de prestations cochées
$activ    = trim($_POST['activites_souhaitees'] ?? '');

// Validation des champs obligatoires
if (!$nom || !$email || !$debut || !$fin || $nb <= 0) {
    echo json_encode(['ok' => false, 'erreur' => 'Tous les champs obligatoires doivent être remplis.']);
    exit;
}

// Validation du format email côté serveur (ne pas se fier uniquement au HTML)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erreur' => 'Adresse email invalide.']);
    exit;
}

// Cohérence des dates : départ doit être après l'arrivée
if ($fin <= $debut) {
    echo json_encode(['ok' => false, 'erreur' => 'La date de départ doit être après la date d\'arrivée.']);
    exit;
}

// Pas de réservation dans le passé
if (strtotime($debut) < strtotime('today')) {
    echo json_encode(['ok' => false, 'erreur' => 'La date d\'arrivée ne peut pas être dans le passé.']);
    exit;
}

$reservations = lireJson('reservations.json');

// Création de la réservation avec statut "en_attente" — l'admin devra la valider ou la refuser
// idChambre est null car la chambre n'est attribuée que lors de la validation par l'admin
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
    'idChambre'            => null,  // attribué lors de la validation admin
    'arrhes'               => 0,
    'reductions'           => (object) [], // objet JSON vide, clé = idPrestation, valeur = % réduction
];

$reservations[] = $nouvelle;

if (!ecrireJson('reservations.json', $reservations)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, veuillez réessayer.']);
    exit;
}

echo json_encode(['ok' => true]);
