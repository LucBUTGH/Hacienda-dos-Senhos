<?php
// demander_activite.php — soumission d'une demande d'activité par le client
require_once '../helpers.php';
require_once '../auth.php';
requireClient();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idActivite  = intval($_POST['idActivite'] ?? 0);
$nbPersonnes = intval($_POST['nb_personnes'] ?? 0);
$granularite = trim($_POST['granularite'] ?? '');
$preferences = trim($_POST['preferences'] ?? '');
// Champs extra optionnels selon le type d'activité
$niveau      = trim($_POST['niveau'] ?? '');
$difficulte  = trim($_POST['difficulte'] ?? '');
$mode        = trim($_POST['mode'] ?? '');

if (!$idActivite || $nbPersonnes <= 0 || !$granularite) {
    echo json_encode(['ok' => false, 'erreur' => 'Veuillez choisir une activité, une durée et un nombre de personnes.']);
    exit;
}

// Vérifier que l'activité et la granularite existent
$activites = lireJson('activites.json');
$activiteInfo = null;
foreach ($activites as $a) {
    if ($a['idActivite'] === $idActivite) { $activiteInfo = $a; break; }
}
if (!$activiteInfo) {
    echo json_encode(['ok' => false, 'erreur' => 'Activité inconnue.']);
    exit;
}
if (!in_array($granularite, $activiteInfo['granularites'])) {
    echo json_encode(['ok' => false, 'erreur' => 'Durée invalide pour cette activité.']);
    exit;
}

// Valider les champs extra requis
if (in_array('niveau', $activiteInfo['champs_extra']) && !$niveau) {
    echo json_encode(['ok' => false, 'erreur' => 'Veuillez indiquer votre niveau.']);
    exit;
}
if (in_array('difficulte', $activiteInfo['champs_extra']) && !$difficulte) {
    echo json_encode(['ok' => false, 'erreur' => 'Veuillez choisir une difficulté.']);
    exit;
}
if (in_array('mode', $activiteInfo['champs_extra']) && !in_array($mode, ['interne', 'ouvert'])) {
    echo json_encode(['ok' => false, 'erreur' => 'Veuillez choisir un mode de participation.']);
    exit;
}

// Trouver la réservation du client
$clients = lireJson('clients.json');
$client = null;
foreach ($clients as $cl) {
    if ($cl['idClient'] === $_SESSION['idClient']) { $client = $cl; break; }
}

$reservations = lireJson('reservations.json');
$resa = null;
foreach ($reservations as $r) {
    if ($r['idResa'] === $client['idResa']) { $resa = $r; break; }
}

if (!$resa || $resa['statut'] !== 'validee') {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation non validée.']);
    exit;
}
if ($nbPersonnes > $resa['nb_personnes']) {
    echo json_encode(['ok' => false, 'erreur' => 'Nombre de personnes dépasse votre réservation (' . $resa['nb_personnes'] . ' max).']);
    exit;
}

// Vérifier qu'une demande identique n'existe pas déjà
$demandes = lireJson('demandes_activites.json');
foreach ($demandes as $d) {
    if ($d['idResa'] === $resa['idResa'] && $d['idActivite'] === $idActivite) {
        echo json_encode(['ok' => false, 'erreur' => 'Vous avez déjà une demande pour cette activité.']);
        exit;
    }
}

$nouvelle = [
    'idDemande'    => prochainId($demandes, 'idDemande'),
    'idResa'       => $resa['idResa'],
    'idActivite'   => $idActivite,
    'nb_personnes' => $nbPersonnes,
    'granularite'  => $granularite,
    'niveau'       => $niveau ?: null,
    'difficulte'   => $difficulte ?: null,
    'mode'         => $mode ?: null,
    'preferences'  => $preferences,
    'statut'       => 'en_attente',
];

$demandes[] = $nouvelle;

if (!ecrireJson('demandes_activites.json', $demandes)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

echo json_encode(['ok' => true, 'demande' => $nouvelle, 'nomActivite' => $activiteInfo['nom']]);
