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
            <a href="login.php" class="btn-connexion">Se connecter</a>
        </div>
    </div>
</nav>

<section id="hero">
    <div class="c">
        <h1>Hacienda <em>dos Sonhos</em></h1>
        <p>340 hectares de nature brésilienne entre Cerrado et Amazonie.</p>
    </div>
</section>

<section id="presentation">
    <div class="pres-edito">
        <div class="pres-photo-col">
            <img src="assets/image1.png" alt="La hacienda dos Sonhos">
        </div>
        <div class="pres-text-col">
            <span class="pres-overline">Mato Grosso &mdash; Brésil</span>
            <blockquote class="pres-citation">
                &laquo;&nbsp;Ici, le temps se mesure au rythme des saisons, des marées et du chant des aras.&nbsp;&raquo;
            </blockquote>
            <p class="pres-corps">Nichée entre le Cerrado et l'Amazonie, l'Hacienda dos Sonhos s'étend sur 340&nbsp;hectares de nature brésilienne préservée. Forêts denses, lacs cristallins, savanes infinies&nbsp;: un écrin où la faune endémique côtoie une architecture coloniale restaurée avec soin.</p>
            <p class="pres-corps">Le domaine se vit au gré des envies — balade en barque à l'aube, farniente sous les araucarias, repas préparés avec les fruits du verger. Chaque séjour est une immersion, jamais un spectacle.</p>
        </div>
    </div>
    <div class="pres-chiffres">
        <div class="pres-chiffre">
            <span class="chiffre-val">340</span>
            <span class="chiffre-label">hectares</span>
        </div>
        <div class="pres-chiffre">
            <span class="chiffre-val">4</span>
            <span class="chiffre-label">demeures</span>
        </div>
        <div class="pres-chiffre">
            <span class="chiffre-val">XII</span>
            <span class="chiffre-label">activités</span>
        </div>
        <div class="pres-chiffre">
            <span class="chiffre-val">1</span>
            <span class="chiffre-label">domaine unique</span>
        </div>
    </div>
</section>

<section id="hebergements">
    <div class="c">
        <div class="sejours-entete">
            <span class="sejours-surtitre">Où séjourner</span>
            <h2 class="sejours-titre">Quatre demeures,<br><em>quatre façons d'habiter la nature.</em></h2>
        </div>

        <?php
        $placeholders = [
            ['img' => 'assets/2pers.png',  'num' => 'I'],
            ['img' => 'assets/4pers.png',  'num' => 'II'],
            ['img' => 'assets/8pers.png',  'num' => 'III'],
            ['img' => 'assets/2pers2.png', 'num' => 'IV'],
        ];

        $vedette = $chambres[0];
        $p0 = $placeholders[0];
        ?>
        <div class="chambre chambre--vedette">
            <img class="chambre-img" src="<?= $p0['img'] ?>" alt="<?= htmlspecialchars($vedette['nom']) ?>">
            <div class="chambre-body">
                <span class="chambre-num"><?= $p0['num'] ?></span>
                <h3><?= htmlspecialchars($vedette['nom']) ?></h3>
                <p class="chambre-desc"><?= htmlspecialchars($vedette['description']) ?></p>
                <p class="chambre-prix"><?= $vedette['prix_nuit'] ?>&thinsp;€ <span>/ nuit</span></p>
                <p class="chambre-capa"><?= $vedette['capacite'] ?> personnes maximum</p>
                <a href="#reservation" class="chambre-lien">Demander ce séjour</a>
            </div>
        </div>

        <div class="chambres-secondaires">
            <?php foreach (array_slice($chambres, 1) as $i => $c):
                $p = $placeholders[$i + 1];
            ?>
            <div class="chambre chambre--secondaire">
                <img class="chambre-img" src="<?= $p['img'] ?>" alt="<?= htmlspecialchars($c['nom']) ?>">
                <div class="chambre-body">
                    <span class="chambre-num"><?= $p['num'] ?></span>
                    <h3><?= htmlspecialchars($c['nom']) ?></h3>
                    <p class="chambre-desc"><?= htmlspecialchars($c['description']) ?></p>
                    <p class="chambre-prix"><?= $c['prix_nuit'] ?>&thinsp;€ <span>/ nuit</span></p>
                </div>
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