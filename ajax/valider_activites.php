<?php
// valider_activites.php — validation d'un groupe de demandes d'activités par l'admin
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$date       = trim($_POST['date'] ?? '');
$idDemandes = array_map('intval', $_POST['idDemandes'] ?? []);
$animateur  = trim($_POST['animateur'] ?? '');
$nbMin      = intval($_POST['nb_min'] ?? 0);

if (!$date || empty($idDemandes) || !$animateur) {
    echo json_encode(['ok' => false, 'erreur' => 'Données incomplètes (date, demandes, animateur requis).']);
    exit;
}

// Vérifier que l'animateur existe
$animateurs = lireJson('animateurs.json');
$animateurValide = false;
foreach ($animateurs as $an) {
    if ($an['nom'] === $animateur) { $animateurValide = true; break; }
}
if (!$animateurValide) {
    echo json_encode(['ok' => false, 'erreur' => 'Animateur inconnu.']);
    exit;
}

$demandes         = lireJson('demandes_activites.json');
$activitesPrevues = lireJson('activites_prevues.json');
$activites        = lireJson('activites.json');

// Index demandes et activites par ID
$demandesIndex = [];
foreach ($demandes as $d) { $demandesIndex[$d['idDemande']] = $d; }
$activitesIndex = [];
foreach ($activites as $a) { $activitesIndex[$a['idActivite']] = $a; }

// Demandes déjà planifiées (toutes dates confondues)
$demandesDejaPlannifiees = [];
foreach ($activitesPrevues as $ap) {
    foreach ($ap['idDemandes'] as $idD) {
        $demandesDejaPlannifiees[$idD] = true;
    }
}
foreach ($idDemandes as $idD) {
    if (isset($demandesDejaPlannifiees[$idD])) {
        echo json_encode(['ok' => false, 'erreur' => "La demande #$idD a déjà été planifiée pour un autre jour."]);
        exit;
    }
}

// Vérifier toutes les demandes et construire la liste des participants
$participants    = [];
$idActivite      = null;
$totalPersonnes  = 0;

foreach ($idDemandes as $idD) {
    $d = $demandesIndex[$idD] ?? null;
    if (!$d) {
        echo json_encode(['ok' => false, 'erreur' => "Demande #$idD introuvable."]);
        exit;
    }
    // Toutes les demandes d'un même groupe doivent être du même type
    if ($idActivite === null) {
        $idActivite = $d['idActivite'];
    } elseif ($idActivite !== $d['idActivite']) {
        echo json_encode(['ok' => false, 'erreur' => 'Les demandes doivent être du même type d\'activité.']);
        exit;
    }
    $participants[]  = ['idResa' => $d['idResa'], 'message' => ''];
    $totalPersonnes += $d['nb_personnes'];
}

$activiteInfo = $activitesIndex[$idActivite] ?? null;
if (!$activiteInfo) {
    echo json_encode(['ok' => false, 'erreur' => 'Type d\'activité introuvable.']);
    exit;
}

// Vérification spécifique aux activités de groupe
if ($activiteInfo['type_special'] === 'groupe') {
    // Utiliser le seuil saisi par l'admin s'il est fourni, sinon celui par défaut
    $seuilEffectif = $nbMin > 0 ? $nbMin : ($activiteInfo['nb_min_personnes'] ?? 0);
    if ($seuilEffectif > 0 && $totalPersonnes < $seuilEffectif) {
        echo json_encode([
            'ok'     => false,
            'erreur' => "Nombre total de participants insuffisant ($totalPersonnes / $seuilEffectif requis)."
        ]);
        exit;
    }
}

$nouvelle = [
    'idActivitePrevue' => prochainId($activitesPrevues, 'idActivitePrevue'),
    'idDemandes'       => $idDemandes,
    'idActivite'       => $idActivite,
    'date'             => $date,
    'animateur'        => $animateur,
    'participants'     => $participants,
    'nb_min'           => $nbMin ?: null,
];

$activitesPrevues[] = $nouvelle;

if (!ecrireJson('activites_prevues.json', $activitesPrevues)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

// Mettre à jour le statut des demandes validées
foreach ($demandes as &$d) {
    if (in_array($d['idDemande'], $idDemandes)) {
        $d['statut'] = 'validee';
    }
}
unset($d);

if (!ecrireJson('demandes_activites.json', $demandes)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur (mise à jour demandes).']);
    exit;
}

echo json_encode(['ok' => true]);
