<?php
require_once 'helpers.php';
require_once 'auth.php';
requireAdmin();

$reservations = lireJson('reservations.json');
$chambres     = lireJson('chambres.json');
$clients      = lireJson('clients.json');

// Index prestations par ID pour résolution rapide
$prestationsIndex = [];
foreach (lireJson('prestations.json') as $p) {
    $prestationsIndex[$p['idPrestation']] = $p['nom'];
}
$animateurs = lireJson('animateurs.json');

// En attente en premier, puis validée, puis refusée
usort($reservations, function ($a, $b) {
    $ordre = ['en_attente' => 0, 'validee' => 1, 'refusee' => 2];
    return ($ordre[$a['statut']] ?? 3) <=> ($ordre[$b['statut']] ?? 3);
});

// Index clients par idResa pour affichage rapide
$clientsParResa = [];
foreach ($clients as $cl) {
    $clientsParResa[$cl['idResa']] = $cl;
}

// Résumé occupation des chambres (toutes dates confondues, résas validées)
$chambresOccupees = array_unique(array_column(
    array_filter($reservations, fn($r) => $r['statut'] === 'validee'),
    'idChambre'
));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Hacienda dos Sonhos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="admin-body">

<nav>
    <div class="c">
        <a href="index.php" class="logo">Hacienda dos Sonhos</a>
        <span class="nav-admin-label">Administration</span>
        <a href="logout.php" style="color:rgba(255,255,255,.7);font-size:.85rem;">Déconnexion</a>
    </div>
</nav>

<main class="c admin-main">

    <h1>Réservations</h1>

    <div class="admin-stats">
        <div class="stat">
            <span class="stat-val" id="stat-en-attente"><?= count(array_filter($reservations, fn($r) => $r['statut'] === 'en_attente')) ?></span>
            <span class="stat-label">En attente</span>
        </div>
        <div class="stat">
            <span class="stat-val"><?= count($chambres) - count($chambresOccupees) ?> / <?= count($chambres) ?></span>
            <span class="stat-label">Chambres libres</span>
        </div>
    </div>

    <?php if (empty($reservations)): ?>
        <p class="vide">Aucune réservation pour le moment.</p>
    <?php else: ?>
        <div id="liste-reservations">
        <?php foreach ($reservations as $r):
            $dispoChambre = array_values(array_filter(
                chambresDisponibles($chambres, $reservations, $r['date_debut'], $r['date_fin']),
                fn($ch) => $ch['capacite'] >= $r['nb_personnes']
            ));
            $client = $clientsParResa[$r['idResa']] ?? null;
            $chambreAssignee = null;
            if ($r['idChambre']) {
                foreach ($chambres as $ch) {
                    if ($ch['idChambres'] === $r['idChambre']) { $chambreAssignee = $ch; break; }
                }
            }
        ?>
        <div class="resa-card resa-<?= $r['statut'] ?>" data-id="<?= $r['idResa'] ?>" data-debut="<?= $r['date_debut'] ?>" data-fin="<?= $r['date_fin'] ?>">
            <div class="resa-header">
                <div>
                    <span class="badge badge-<?= $r['statut'] ?>">
                        <?= $r['statut'] === 'en_attente' ? 'En attente' : ($r['statut'] === 'validee' ? 'Validée' : 'Refusée') ?>
                    </span>
                    <strong><?= htmlspecialchars($r['nom']) ?></strong>
                    <span class="resa-id">#<?= $r['idResa'] ?></span>
                </div>
                <span class="resa-dates">
                    <?= date('d/m/Y', strtotime($r['date_debut'])) ?> → <?= date('d/m/Y', strtotime($r['date_fin'])) ?>
                    · <?= $r['nb_personnes'] ?> pers.
                </span>
            </div>

            <div class="resa-details">
                <p><?= htmlspecialchars($r['email']) ?></p>
                <?php
                $nomsPrestations = is_array($r['prestations'])
                    ? array_map(fn($id) => $prestationsIndex[$id] ?? "Prestation #$id", $r['prestations'])
                    : ($r['prestations'] ? [$r['prestations']] : []);
                $activitesSouhaitees = is_string($r['activites_souhaitees'] ?? '')
                    ? ($r['activites_souhaitees'] ?? '')
                    : implode(', ', $r['activites_souhaitees']);
                ?>
                <?php if ($nomsPrestations): ?>
                    <p><em>Prestations : <?= htmlspecialchars(implode(', ', $nomsPrestations)) ?></em></p>
                <?php endif; ?>
                <?php if ($activitesSouhaitees): ?>
                    <p><em>Activités souhaitées : <?= htmlspecialchars($activitesSouhaitees) ?></em></p>
                <?php endif; ?>
                <?php if ($chambreAssignee): ?>
                    <p>Chambre : <strong><?= htmlspecialchars($chambreAssignee['nom']) ?></strong></p>
                <?php endif; ?>
                <?php if ($client): ?>
                    <p>Client créé : <?= htmlspecialchars($client['nom']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($r['statut'] === 'en_attente'): ?>
            <div class="resa-actions">
                <?php if (empty($dispoChambre)): ?>
                    <p class="no-chambre">Aucune chambre disponible pour ces dates.</p>
                <?php else: ?>
                    <select class="select-chambre">
                        <option value="">— Choisir une chambre —</option>
                        <?php foreach ($dispoChambre as $ch): ?>
                            <option value="<?= $ch['idChambres'] ?>">
                                <?= htmlspecialchars($ch['nom']) ?> (<?= $ch['capacite'] ?> pers. max · <?= $ch['prix_nuit'] ?>€/nuit)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-valider" data-id="<?= $r['idResa'] ?>">Valider</button>
                <?php endif; ?>
                <button class="btn-refuser" data-id="<?= $r['idResa'] ?>">Refuser</button>
            </div>
            <div class="msg-action"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <!-- ===== SECTION GESTION DES ACTIVITÉS ===== -->
    <h1 class="section-titre" style="margin-top:2.5rem">Gestion des activités</h1>

    <div class="activites-admin-section">
        <div class="champ-inline" style="margin-bottom:1rem">
            <div class="champ" style="flex:0 0 auto">
                <label for="date-activites">Voir les demandes du</label>
                <input type="date" id="date-activites" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Animateurs disponibles (passé au JS via data) -->
        <div id="animateurs-data" style="display:none"
            data-animateurs="<?= htmlspecialchars(json_encode(array_column($animateurs, 'nom'))) ?>">
        </div>

        <div id="msg-activites"></div>
        <div id="liste-activites-jour">
            <p class="vide">Sélectionnez une date pour afficher les demandes.</p>
        </div>
    </div>

</main>

<script src="js/admin.js"></script>
</body>
</html>
