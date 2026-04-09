<?php
require_once 'helpers.php';
require_once 'auth.php';
sessionStart();
header('Cache-Control: no-store, no-cache, must-revalidate');
$chambres    = lireJson('chambres.json');
$prestations = lireJson('prestations.json');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hacienda dos Sonhos</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

</head>
<body>

<nav>
    <div class="c">
        <a href="#" class="logo">Hacienda dos Sonhos</a>
        <div style="display:flex;gap:1.5rem;align-items:center;">
            <a href="#hebergements">Séjours</a>
            <a href="#reservation">Réserver</a>
            <?php if (($_SESSION['role'] ?? '') === 'client'): ?>
                <a href="client.php">Mes réservations</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section id="hero">
    <div class="c">
        <h1>Hacienda <em>dos Sonhos</em></h1>
        <p>340 hectares de nature brésilienne entre Cerrado et Amazonie.</p>
    </div>
</section>

<section id="hebergements">
    <div class="c">
        <h2>Nos hébergements</h2>
        <div id="grille-chambres">
            <?php foreach ($chambres as $c): ?>
                <div class="chambre">
                    <h3><?= htmlspecialchars($c['nom']) ?></h3>
                    <p><?= $c['prix_nuit'] ?>€ / nuit — <?= $c['capacite'] ?> pers. max</p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="reservation">
    <div class="c">
        <h2>Demande de réservation</h2>
        <div id="msg-resa"></div>
        <form id="form-resa">
            <div class="champ">
                <label for="nom">Nom complet *</label>
                <input type="text" id="nom" name="nom" required>
            </div>
            <div class="champ">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="champ-groupe">
                <div class="champ">
                    <label for="date_debut">Date d'arrivée *</label>
                    <input type="date" id="date_debut" name="date_debut" required>
                </div>
                <div class="champ">
                    <label for="date_fin">Date de départ *</label>
                    <input type="date" id="date_fin" name="date_fin" required>
                </div>
            </div>
            <div class="champ">
                <label for="nb_personnes">Nombre de personnes *</label>
                <input type="number" id="nb_personnes" name="nb_personnes" min="1" required>
            </div>
            <div class="champ">
                <label>Prestations souhaitées</label>
                <div class="liste-options">
                    <?php foreach ($prestations as $p): ?>
                    <label class="option-item">
                        <input type="checkbox" name="prestations[]" value="<?= $p['idPrestation'] ?>">
                        <?= htmlspecialchars($p['nom']) ?>
                        <span class="option-prix"><?= $p['prix'] ?>€</span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="champ">
                <label for="activites_souhaitees">Activités souhaitées</label>
                <textarea id="activites_souhaitees" name="activites_souhaitees" rows="2"
                    placeholder="Tennis, balade en bateau… (demandes détaillées possibles après connexion)"></textarea>
            </div>
            <button type="submit">Envoyer la demande</button>
        </form>
    </div>
</section>

<footer>
    <div class="c">
        <p>&copy; <?= date('Y') ?> Hacienda dos Sonhos</p>
    </div>
</footer>


<script src="js/main.js"></script>
</body>
</html>