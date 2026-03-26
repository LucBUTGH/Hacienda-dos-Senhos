<?php
require_once 'helpers.php';
require_once 'auth.php';
sessionStart();

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim($_POST['email']    ?? '');
    $nouveau = trim($_POST['nouveau']  ?? '');
    $confirm = trim($_POST['confirmer'] ?? '');

    if (!$email || !$nouveau || !$confirm) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Email invalide.';
    } elseif (strlen($nouveau) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($nouveau !== $confirm) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $clients = lireJson('clients.json');
        $trouve  = false;

        foreach ($clients as &$cl) {
            if ($cl['email'] === $email) {
                $cl['motDePasse'] = password_hash($nouveau, PASSWORD_DEFAULT);
                $trouve = true;
                break;
            }
        }
        unset($cl);

        if (!$trouve) {
            $erreur = 'Aucun compte associé à cet email.';
        } elseif (!ecrireJson('clients.json', $clients)) {
            $erreur = 'Erreur serveur, veuillez réessayer.';
        } else {
            $succes = 'Mot de passe mis à jour. Vous pouvez vous connecter.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — Hacienda dos Sonhos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">

<nav>
    <div class="c">
        <a href="index.php" class="logo">Hacienda dos Sonhos</a>
    </div>
</nav>

<main class="c" style="max-width:420px;padding-top:3rem;">
    <h1 style="margin-bottom:.5rem;">Mot de passe oublié</h1>
    <p style="color:#888;font-size:.9rem;margin-bottom:1.5rem;">
        Entrez votre email et choisissez un nouveau mot de passe.
    </p>

    <?php if ($erreur): ?>
        <div class="form-erreur" style="margin-bottom:1rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    <?php if ($succes): ?>
        <div style="background:#e6f4ec;color:#1d5c3f;border:1px solid #b2d8c0;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;">
            <?= htmlspecialchars($succes) ?>
            <a href="login.php" style="display:block;margin-top:.5rem;color:#143d2a;font-weight:bold;">→ Se connecter</a>
        </div>
    <?php endif; ?>

    <?php if (!$succes): ?>
    <form method="POST">
        <div class="champ">
            <label for="email">Email du compte</label>
            <input type="email" id="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="champ">
            <label for="nouveau">Nouveau mot de passe</label>
            <input type="password" id="nouveau" name="nouveau" required minlength="6">
        </div>
        <div class="champ">
            <label for="confirmer">Confirmer le mot de passe</label>
            <input type="password" id="confirmer" name="confirmer" required>
        </div>
        <button type="submit" class="btn-action" style="width:100%;padding:.6rem;">Changer le mot de passe</button>
    </form>

    <p style="text-align:center;margin-top:1rem;font-size:.85rem;">
        <a href="login.php" style="color:#143d2a;">← Retour à la connexion</a>
    </p>
    <?php endif; ?>
</main>

</body>
</html>
