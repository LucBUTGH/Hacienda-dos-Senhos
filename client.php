<?php
require_once 'helpers.php';
require_once 'auth.php';
requireClient();

$clients      = lireJson('clients.json');
$reservations = lireJson('reservations.json');
$chambres     = lireJson('chambres.json');
$activites    = lireJson('activites.json');

$prestationsAll = lireJson('prestations.json');
$prestationsIndex = [];
foreach ($prestationsAll as $p) {
    $prestationsIndex[$p['idPrestation']] = $p;
}
$activitesIndex = [];
foreach ($activites as $a) {
    $activitesIndex[$a['idActivite']] = $a;
}

// Trouver le client connecté
$client = null;
foreach ($clients as $cl) {
    if ($cl['idClient'] === $_SESSION['idClient']) { $client = $cl; break; }
}

// Trouver sa réservation
$resa = null;
foreach ($reservations as $r) {
    if ($r['idResa'] === $client['idResa']) { $resa = $r; break; }
}

// Trouver la chambre associée
$chambre = null;
if ($resa && $resa['idChambre']) {
    foreach ($chambres as $ch) {
        if ($ch['idChambres'] === $resa['idChambre']) { $chambre = $ch; break; }
    }
}

$nbNuits = ($resa)
    ? (new DateTime($resa['date_debut']))->diff(new DateTime($resa['date_fin']))->days
    : 0;

// Charger les demandes et activités prévues du client
$demandesClient   = [];
$activitesPrevues = [];
if ($resa) {
    foreach (lireJson('demandes_activites.json') as $d) {
        if ($d['idResa'] === $resa['idResa']) $demandesClient[] = $d;
    }
    // Activités prévues où ce client est participant
    foreach (lireJson('activites_prevues.json') as $ap) {
        foreach ($ap['participants'] as $p) {
            if ($p['idResa'] == $resa['idResa']) {
                $ap['_monMessage'] = $p['message'];
                $activitesPrevues[] = $ap;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace — Hacienda dos Sonhos</title>
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
        <span style="color:rgba(255,255,255,.7);font-size:.85rem;">
            Bonjour, <?= htmlspecialchars($client['nom']) ?>
        </span>
        <a href="logout.php" style="color:rgba(255,255,255,.7);font-size:.85rem;">Déconnexion</a>
    </div>
</nav>

<main class="c admin-main">
    <h1>Ma réservation</h1>

    <?php if (!$resa): ?>
        <p class="vide">Aucune réservation trouvée.</p>
    <?php else: ?>
    <div class="resa-card resa-<?= $resa['statut'] ?>">
        <div class="resa-header">
            <div>
                <span class="badge badge-<?= $resa['statut'] ?>">
                    <?= $resa['statut'] === 'en_attente' ? 'En attente' : ($resa['statut'] === 'validee' ? 'Validée' : 'Refusée') ?>
                </span>
                <strong>#<?= $resa['idResa'] ?></strong>
            </div>
            <span class="resa-dates">
                <?= date('d/m/Y', strtotime($resa['date_debut'])) ?> → <?= date('d/m/Y', strtotime($resa['date_fin'])) ?>
                · <?= $nbNuits ?> nuit<?= $nbNuits > 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="resa-details">
            <p><?= $resa['nb_personnes'] ?> personne<?= $resa['nb_personnes'] > 1 ? 's' : '' ?></p>
            <?php if ($chambre): ?>
                <p>Chambre : <strong><?= htmlspecialchars($chambre['nom']) ?></strong></p>
                <p><?= htmlspecialchars($chambre['description']) ?></p>
                <p><?= $chambre['prix_nuit'] ?>€ / nuit
                    · Total : <strong><?= $chambre['prix_nuit'] * $nbNuits ?>€</strong></p>
            <?php endif; ?>
            <?php
            $nomsPrestations = is_array($resa['prestations'])
                ? array_map(fn($id) => $prestationsIndex[$id]['nom'] ?? "Prestation #$id", $resa['prestations'])
                : ($resa['prestations'] ? [$resa['prestations']] : []);
            $activitesSouhaitees = is_array($resa['activites_souhaitees'])
                ? implode(', ', $resa['activites_souhaitees'])
                : ($resa['activites_souhaitees'] ?? '');
            ?>
            <?php if ($nomsPrestations): ?>
                <p><em>Prestations : <?= htmlspecialchars(implode(', ', $nomsPrestations)) ?></em></p>
            <?php endif; ?>
            <?php if ($activitesSouhaitees): ?>
                <p><em>Activités souhaitées : <?= htmlspecialchars($activitesSouhaitees) ?></em></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($resa['statut'] === 'validee'): ?>

    <!-- ===== SECTION FACTURE ===== -->
    <h2 class="section-titre">Ma facture prévisionnelle</h2>

    <?php
    // Calcul facture
    $totalFacture = 0;
    $reductions = $resa['reductions'] ?? [];
    $arrhes = $resa['arrhes'] ?? 0;

    // Demandes d'activités validées pour ce client
    $demandesValidees = [];
    foreach (lireJson('demandes_activites.json') as $d) {
        if ($d['idResa'] === $resa['idResa'] && $d['statut'] === 'validee') {
            $demandesValidees[] = $d;
        }
    }
    ?>

    <div class="facture-card" id="facture-card">
        <table class="facture-table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Détail</th>
                    <th class="facture-montant">Montant</th>
                </tr>
            </thead>
            <tbody>
                <!-- Hébergement -->
                <?php if ($chambre): ?>
                <?php $prixChambre = $chambre['prix_nuit'] * $nbNuits; $totalFacture += $prixChambre; ?>
                <tr>
                    <td>Hébergement</td>
                    <td><?= htmlspecialchars($chambre['nom']) ?> — <?= $chambre['prix_nuit'] ?>€ × <?= $nbNuits ?> nuit<?= $nbNuits > 1 ? 's' : '' ?></td>
                    <td class="facture-montant"><?= $prixChambre ?>€</td>
                </tr>
                <?php endif; ?>

                <!-- Prestations -->
                <?php foreach ($resa['prestations'] as $idP):
                    $presta = $prestationsIndex[$idP] ?? null;
                    if (!$presta) continue;
                    $reduc = intval($reductions[$idP] ?? 0);
                    $prixPresta = $presta['prix'] * (1 - $reduc / 100);
                    $totalFacture += $prixPresta;
                ?>
                <tr>
                    <td>Prestation</td>
                    <td>
                        <?= htmlspecialchars($presta['nom']) ?>
                        <?php if ($reduc > 0): ?>
                            <span class="facture-reduction">(-<?= $reduc ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="facture-montant"><?= number_format($prixPresta, 2, ',', '') ?>€</td>
                </tr>
                <?php endforeach; ?>

                <!-- Activités validées -->
                <?php foreach ($demandesValidees as $dv):
                    $actInfo = $activitesIndex[$dv['idActivite']] ?? null;
                    if (!$actInfo) continue;
                    $prixAct = ($actInfo['tarifs'][$dv['granularite']] ?? 0) * $dv['nb_personnes'];
                    $totalFacture += $prixAct;
                    $labelsGran = ['heure' => 'à l\'heure', 'demi-journee' => 'demi-journée', 'journee' => 'journée'];
                ?>
                <tr>
                    <td>Activité</td>
                    <td>
                        <?= htmlspecialchars($actInfo['nom']) ?>
                        — <?= $labelsGran[$dv['granularite']] ?? $dv['granularite'] ?>, <?= $dv['nb_personnes'] ?> pers.
                    </td>
                    <td class="facture-montant"><?= number_format($prixAct, 2, ',', '') ?>€</td>
                </tr>
                <?php endforeach; ?>

                <!-- Arrhes -->
                <?php if ($arrhes > 0): $totalFacture -= $arrhes; ?>
                <tr class="facture-arrhes">
                    <td>Arrhes reçues</td>
                    <td></td>
                    <td class="facture-montant">-<?= number_format($arrhes, 2, ',', '') ?>€</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="facture-total">
                    <td colspan="2"><strong>Total</strong></td>
                    <td class="facture-montant"><strong><?= number_format($totalFacture, 2, ',', '') ?>€</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ===== COMMANDE DE PRESTATIONS ===== -->
    <h2 class="section-titre">Commander des prestations</h2>
    <div class="activite-form-card">
        <p style="font-size:.85rem;color:#888;margin-bottom:1rem">Cochez les prestations souhaitées. Elles seront ajoutées directement à votre facture.</p>
        <div id="msg-prestations"></div>
        <form id="form-prestations">
            <div class="liste-options">
                <?php foreach ($prestationsAll as $p): ?>
                <label class="option-item">
                    <input type="checkbox" name="prestations[]" value="<?= $p['idPrestation'] ?>"
                        <?= in_array($p['idPrestation'], $resa['prestations']) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($p['nom']) ?>
                    <span class="option-prix"><?= $p['prix'] ?>€</span>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn-action" style="margin-top:.75rem">Enregistrer les prestations</button>
        </form>
    </div>

    <!-- ===== SECTION ACTIVITÉS ===== -->
    <h2 class="section-titre">Mes activités</h2>

    <!-- Données activités pour le JS -->
    <div id="activites-data" style="display:none"
        data-activites="<?= htmlspecialchars(json_encode($activites)) ?>"
        data-nb-max="<?= $resa['nb_personnes'] ?>"></div>

    <!-- Formulaire de demande d'activité -->
    <div class="activite-form-card">
        <h3>Faire une demande d'activité</h3>
        <div id="msg-demande"></div>
        <form id="form-demande-activite">
            <div class="champ">
                <label for="idActivite">Activité</label>
                <select id="idActivite" name="idActivite" required>
                    <option value="">— Choisir une activité —</option>
                    <?php foreach ($activites as $a): ?>
                    <option value="<?= $a['idActivite'] ?>">
                        <?= htmlspecialchars($a['nom']) ?>
                        — <?= htmlspecialchars($a['description']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Champs affichés dynamiquement par JS -->
            <div class="champ-inline">
                <div class="champ champ-dynamique" id="wrap-granularite" style="display:none">
                    <label for="granularite">Durée</label>
                    <select id="granularite" name="granularite"></select>
                </div>
                <div class="champ">
                    <label for="nb_personnes_act">Personnes concernées</label>
                    <input type="number" id="nb_personnes_act" name="nb_personnes"
                        min="1" max="<?= $resa['nb_personnes'] ?>" value="1" required>
                    <span style="font-size:.8rem;color:#888"><?= $resa['nb_personnes'] ?> personne<?= $resa['nb_personnes'] > 1 ? 's' : '' ?> dans votre réservation</span>
                </div>
            </div>

            <div class="champ champ-dynamique" id="wrap-niveau" style="display:none">
                <label for="niveau">Niveau</label>
                <select id="niveau" name="niveau"></select>
            </div>

            <div class="champ champ-dynamique" id="wrap-difficulte" style="display:none">
                <label for="difficulte">Difficulté souhaitée</label>
                <select id="difficulte" name="difficulte"></select>
            </div>

            <div class="champ champ-dynamique" id="wrap-mode" style="display:none">
                <label for="mode">Mode de participation</label>
                <select id="mode" name="mode"></select>
                <span id="hint-groupe" style="font-size:.8rem;color:#856404;display:none"></span>
            </div>

            <div class="champ">
                <label for="preferences">Préférences / contraintes</label>
                <textarea id="preferences" name="preferences" rows="2"
                    placeholder="Ex : pas le premier jour, de préférence le matin…"></textarea>
            </div>
            <button type="submit" class="btn-action">Envoyer la demande</button>
        </form>
    </div>

    <!-- Liste des demandes en attente -->
    <div id="liste-demandes">
        <?php if (empty($demandesClient)): ?>
            <p class="vide" id="vide-demandes">Aucune demande d'activité pour l'instant.</p>
        <?php else: ?>
            <?php foreach ($demandesClient as $d):
                $act = $activitesIndex[$d['idActivite']] ?? null;
            ?>
            <div class="demande-card">
                <span class="badge badge-en_attente">En attente</span>
                <strong><?= $act ? htmlspecialchars($act['nom']) : 'Activité #' . $d['idActivite'] ?></strong>
                · <?= $d['nb_personnes'] ?> pers.
                <?php if ($d['preferences']): ?>
                    <em style="color:#888"> — <?= htmlspecialchars($d['preferences']) ?></em>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Activités validées -->
    <?php if (!empty($activitesPrevues)): ?>
    <h3 class="section-sous-titre">Activités confirmées</h3>
    <?php foreach ($activitesPrevues as $ap):
        $act = $activitesIndex[$ap['idActivite']] ?? null;
    ?>
    <div class="activite-prevue-card">
        <div class="activite-prevue-header">
            <strong><?= $act ? htmlspecialchars($act['nom']) : 'Activité #' . $ap['idActivite'] ?></strong>
            <span class="activite-date"><?= date('d/m/Y', strtotime($ap['date'])) ?></span>
        </div>
        <p>Animateur : <?= htmlspecialchars($ap['animateur']) ?>
            <?php if (!empty($ap['arbitre'])): ?> · Arbitre : <?= htmlspecialchars($ap['arbitre']) ?><?php endif; ?>
        </p>

        <!-- Messages de tous les participants -->
        <?php $autresMessages = array_filter($ap['participants'], fn($p) => $p['idResa'] != $resa['idResa'] && $p['message']); ?>
        <?php if (!empty($autresMessages)): ?>
        <div class="participants-messages">
            <?php foreach ($autresMessages as $p): ?>
                <p class="participant-message"><em>"<?= htmlspecialchars($p['message']) ?>"</em></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Mon message -->
        <div class="message-form">
            <label>Mon message (visible par tous les participants)</label>
            <textarea class="input-message" rows="2"
                placeholder="Ex : je veux une partie de tennis tranquille…"><?= htmlspecialchars($ap['_monMessage']) ?></textarea>
            <button class="btn-save-message btn-action" data-id="<?= $ap['idActivitePrevue'] ?>">Enregistrer</button>
            <span class="msg-message"></span>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; // statut validee ?>
    <?php endif; // resa existe ?>
</main>

<script src="js/client.js"></script>
</body>
</html>
