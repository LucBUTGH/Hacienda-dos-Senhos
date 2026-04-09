<?php
// valider_activites.php — l'admin valide un groupe de demandes d'activités pour un jour donné
// Appelé en AJAX depuis js/admin.js dans la section "Gestion des activités".
// L'admin sélectionne plusieurs demandes du même type et les regroupe en une "activité prévue".
// Une activité prévue = une session planifiée à une date, avec un animateur et une liste de participants.
require_once '../helpers.php';
require_once '../auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erreur' => 'Méthode non autorisée']);
    exit;
}

$date       = trim($_POST['date'] ?? '');
$idDemandes = array_map('intval', $_POST['idDemandes'] ?? []); // IDs des demandes à regrouper
$animateur  = trim($_POST['animateur'] ?? '');
$nbMin      = intval($_POST['nb_min'] ?? 0); // seuil minimum de participants (activités de groupe)

if (!$date || empty($idDemandes) || !$animateur) {
    echo json_encode(['ok' => false, 'erreur' => 'Données incomplètes (date, demandes, animateur requis).']);
    exit;
}

// Vérifier que l'animateur existe dans le référentiel animateurs.json
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

// Construction d'index par ID pour éviter des boucles imbriquées
$demandesIndex = [];
foreach ($demandes as $d) { $demandesIndex[$d['idDemande']] = $d; }
$activitesIndex = [];
foreach ($activites as $a) { $activitesIndex[$a['idActivite']] = $a; }

// Construire un set de toutes les demandes déjà planifiées (peu importe la date)
// Une demande ne peut appartenir qu'à une seule activité prévue
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

// Vérification de cohérence : toutes les demandes doivent être du même type d'activité
// et construction de la liste des participants (une entrée par réservation)
$participants    = [];
$idActivite      = null;
$totalPersonnes  = 0;

foreach ($idDemandes as $idD) {
    $d = $demandesIndex[$idD] ?? null;
    if (!$d) {
        echo json_encode(['ok' => false, 'erreur' => "Demande #$idD introuvable."]);
        exit;
    }
    // Le premier idActivite rencontré fait référence ; les suivants doivent correspondre
    if ($idActivite === null) {
        $idActivite = $d['idActivite'];
    } elseif ($idActivite !== $d['idActivite']) {
        echo json_encode(['ok' => false, 'erreur' => 'Les demandes doivent être du même type d\'activité.']);
        exit;
    }
    $participants[]  = ['idResa' => $d['idResa'], 'message' => '']; // message vide, le client le remplira
    $totalPersonnes += $d['nb_personnes'];
}

$activiteInfo = $activitesIndex[$idActivite] ?? null;
if (!$activiteInfo) {
    echo json_encode(['ok' => false, 'erreur' => 'Type d\'activité introuvable.']);
    exit;
}

// Règle métier : les activités de type "groupe" ont un nombre minimum de participants
// L'admin peut augmenter ce seuil, mais jamais le descendre sous le minimum du catalogue
if ($activiteInfo['type_special'] === 'groupe') {
    $catalogMin    = $activiteInfo['nb_min_personnes'] ?? 0;
    $seuilEffectif = max($nbMin > 0 ? $nbMin : 0, $catalogMin);
    if ($seuilEffectif > 0 && $totalPersonnes < $seuilEffectif) {
        echo json_encode([
            'ok'     => false,
            'erreur' => "Nombre total de participants insuffisant ($totalPersonnes / $seuilEffectif requis)."
        ]);
        exit;
    }
}

// Création de l'activité prévue qui sera visible dans l'espace client des participants
$nouvelle = [
    'idActivitePrevue' => prochainId($activitesPrevues, 'idActivitePrevue'),
    'idDemandes'       => $idDemandes,   // liens vers les demandes d'origine
    'idActivite'       => $idActivite,
    'date'             => $date,
    'animateur'        => $animateur,
    'participants'     => $participants, // chaque participant peut laisser un message
    'nb_min'           => $nbMin ?: null,
];

$activitesPrevues[] = $nouvelle;

if (!ecrireJson('activites_prevues.json', $activitesPrevues)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur.']);
    exit;
}

// Passer le statut des demandes à "validee" pour qu'elles disparaissent des listes en attente
foreach ($demandes as &$d) {
    if (in_array($d['idDemande'], $idDemandes)) {
        $d['statut'] = 'validee';
    }
}
unset($d); // libérer la référence après la boucle (bonne pratique avec &)

if (!ecrireJson('demandes_activites.json', $demandes)) {
    echo json_encode(['ok' => false, 'erreur' => 'Erreur serveur (mise à jour demandes).']);
    exit;
}

echo json_encode(['ok' => true]);
