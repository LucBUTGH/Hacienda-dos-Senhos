<?php
require_once '../helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$idResa   = intval($_POST['idResa']    ?? 0);
$idChambre = intval($_POST['idChambre'] ?? 0);

if (!$idResa || !$idChambre) {
    echo json_encode(['ok' => false, 'erreur' => 'Données manquantes.']);
    exit;
}

$reservations = lireJson('reservations.json');
$chambres     = lireJson('chambres.json');
$clients      = lireJson('clients.json');

// Trouver la réservation
$index = null;
foreach ($reservations as $i => $r) {
    if ($r['idResa'] === $idResa) { $index = $i; break; }
}

if ($index === null) {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation introuvable.']);
    exit;
}

$resa = $reservations[$index];

if ($resa['statut'] !== 'en_attente') {
    echo json_encode(['ok' => false, 'erreur' => 'Cette réservation a déjà été traitée.']);
    exit;
}

// Vérifier la chambre existe
$chambre = null;
foreach ($chambres as $ch) {
    if ($ch['idChambres'] === $idChambre) { $chambre = $ch; break; }
}

if (!$chambre) {
    echo json_encode(['ok' => false, 'erreur' => 'Chambre introuvable.']);
    exit;
}

// Vérifier disponibilité (double-check serveur)
$dispo = chambresDisponibles($chambres, $reservations, $resa['date_debut'], $resa['date_fin']);
$idsDispo = array_column($dispo, 'idChambres');

if (!in_array($idChambre, $idsDispo)) {
    echo json_encode(['ok' => false, 'erreur' => 'Cette chambre n\'est plus disponible pour ces dates.']);
    exit;
}

// Vérifier que la capacité de la chambre est suffisante
if ($chambre['capacite'] < $resa['nb_personnes']) {
    echo json_encode(['ok' => false, 'erreur' => "La chambre \"{$chambre['nom']}\" (capacité {$chambre['capacite']}) ne peut pas accueillir {$resa['nb_personnes']} personnes."]);
    exit;
}

// Créer le compte client
$motDePasse = genererMotDePasse();

$nouveauClient = [
    'idClient'    => prochainId($clients, 'idClient'),
    'nom'         => $resa['nom'],
    'email'       => $resa['email'],
    'motDePasse'  => password_hash($motDePasse, PASSWORD_DEFAULT),
    'idResa'      => $idResa,
];

$clients[] = $nouveauClient;

// Mettre à jour la réservation
$reservations[$index]['statut']    = 'validee';
$reservations[$index]['idChambre'] = $idChambre;

// Sauvegarder
if (!ecrireJson('reservations.json', $reservations) || !ecrireJson('clients.json', $clients)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, veuillez réessayer.']);
    exit;
}

// Construire le message type pour l'admin
$protocole = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$urlClient = $protocole . '://' . $_SERVER['HTTP_HOST'] . '/client.php';

$nbNuits = (new DateTime($resa['date_debut']))->diff(new DateTime($resa['date_fin']))->days;

$messageType = "Bonjour {$resa['nom']},\n\n"
    . "Votre réservation à l'Hacienda dos Sonhos a été validée !\n\n"
    . "Chambre : {$chambre['nom']}\n"
    . "Arrivée : " . date('d/m/Y', strtotime($resa['date_debut'])) . "\n"
    . "Départ : " . date('d/m/Y', strtotime($resa['date_fin'])) . "\n"
    . "Durée : {$nbNuits} nuit(s)\n\n"
    . "Connectez-vous à votre espace client pour effectuer vos demandes d'activités (tennis, balade en bateau, etc.) :\n"
    . "URL : {$urlClient}\n"
    . "Email : {$resa['email']}\n"
    . "Mot de passe : {$motDePasse}\n\n"
    . "À bientôt !\nL'équipe Hacienda dos Sonhos";

echo json_encode([
    'ok'           => true,
    'messageType'  => $messageType,
    'nomChambre'   => $chambre['nom'],
    'nomClient'    => $resa['nom'],
]);
