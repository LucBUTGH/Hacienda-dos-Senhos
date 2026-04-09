<?php
// valider_resa.php — l'admin valide une réservation en lui attribuant une chambre
// Appelé en AJAX depuis js/admin.js quand l'admin clique sur "Valider" dans le dashboard.
// En plus de valider la réservation, ce fichier CRÉE le compte client (email + mot de passe généré).
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

// Retrouver la réservation par son index dans le tableau (on a besoin de l'index pour la modifier)
$index = null;
foreach ($reservations as $i => $r) {
    if ($r['idResa'] === $idResa) { $index = $i; break; }
}

if ($index === null) {
    echo json_encode(['ok' => false, 'erreur' => 'Réservation introuvable.']);
    exit;
}

$resa = $reservations[$index];

// Sécurité : empêche de valider deux fois la même réservation
if ($resa['statut'] !== 'en_attente') {
    echo json_encode(['ok' => false, 'erreur' => 'Cette réservation a déjà été traitée.']);
    exit;
}

// Vérifier que la chambre choisie existe bien
$chambre = null;
foreach ($chambres as $ch) {
    if ($ch['idChambres'] === $idChambre) { $chambre = $ch; break; }
}

if (!$chambre) {
    echo json_encode(['ok' => false, 'erreur' => 'Chambre introuvable.']);
    exit;
}

// Double-check disponibilité côté serveur : le select dans l'UI peut être désynchronisé
// si une autre réservation vient d'être validée sur la même chambre entre-temps
$dispo = chambresDisponibles($chambres, $reservations, $resa['date_debut'], $resa['date_fin']);
$idsDispo = array_column($dispo, 'idChambres');

if (!in_array($idChambre, $idsDispo)) {
    echo json_encode(['ok' => false, 'erreur' => 'Cette chambre n\'est plus disponible pour ces dates.']);
    exit;
}

// Vérifier que la capacité de la chambre est suffisante pour le groupe
if ($chambre['capacite'] < $resa['nb_personnes']) {
    echo json_encode(['ok' => false, 'erreur' => "La chambre \"{$chambre['nom']}\" (capacité {$chambre['capacite']}) ne peut pas accueillir {$resa['nb_personnes']} personnes."]);
    exit;
}

// Génération du mot de passe en clair (à communiquer au client par email)
// Le mot de passe est stocké hashé dans clients.json, jamais en clair
$motDePasse = genererMotDePasse();

$nouveauClient = [
    'idClient'    => prochainId($clients, 'idClient'),
    'nom'         => $resa['nom'],
    'email'       => $resa['email'],
    'motDePasse'  => password_hash($motDePasse, PASSWORD_DEFAULT), // hash bcrypt
    'idResa'      => $idResa, // lien vers la réservation pour retrouver les données du séjour
];

$clients[] = $nouveauClient;

// Mise à jour du statut et de la chambre attribuée
$reservations[$index]['statut']    = 'validee';
$reservations[$index]['idChambre'] = $idChambre;

// Sauvegarde atomique des deux fichiers
if (!ecrireJson('reservations.json', $reservations) || !ecrireJson('clients.json', $clients)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur, veuillez réessayer.']);
    exit;
}

// Construction du message type que l'admin devra envoyer manuellement au client par email
// Ce message contient les identifiants de connexion à l'espace client
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
    'messageType'  => $messageType, // affiché dans un textarea dans l'UI admin pour copier-coller
    'nomChambre'   => $chambre['nom'],
    'nomClient'    => $resa['nom'],
]);
