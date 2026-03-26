<?php


define('DATA_DIR', __DIR__ . '/data/');

/**
 * Lit un fichier JSON et retourne un tableau PHP.
 */
function lireJson(string $fichier): array {
    $chemin = DATA_DIR . $fichier;
    if (!file_exists($chemin)) return [];
    $contenu = file_get_contents($chemin);
    return json_decode($contenu, true) ?? [];
}

/**
 * Écrit un tableau PHP dans un fichier JSON.
 */
function ecrireJson(string $fichier, array $donnees): bool {
    $chemin = DATA_DIR . $fichier;
    $json   = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($chemin, $json, LOCK_EX) !== false;
}

/**
 * Génère un ID auto-incrémenté à partir du tableau existant.
 * @param string $cle  Le nom du champ ID dans les données (ex: 'idChambres', 'idClient', 'idResa')
 */
function prochainId(array $tableau, string $cle): int {
    if (empty($tableau)) return 1;
    $ids = array_column($tableau, $cle);
    if (empty($ids)) return 1;
    return max($ids) + 1;
}

/**
 * Retourne les chambres disponibles pour une période donnée.
 * Une chambre est occupée si une résa validée chevauche les dates.
 */
function chambresDisponibles(array $chambres, array $reservations, string $debut, string $fin): array {
    $occupees = [];
    foreach ($reservations as $r) {
        if ($r['statut'] === 'validee' && $r['idChambre'] !== null) {
            if ($r['date_debut'] < $fin && $r['date_fin'] > $debut) {
                $occupees[] = $r['idChambre'];
            }
        }
    }
    return array_values(array_filter($chambres, fn($c) => !in_array($c['idChambres'], $occupees)));
}

/**
 * Génère un mot de passe aléatoire.
 */
function genererMotDePasse(int $longueur = 10): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $mdp = '';
    for ($i = 0; $i < $longueur; $i++) {
        $mdp .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $mdp;
}