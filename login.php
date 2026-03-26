<?php
require_once 'helpers.php';
require_once 'auth.php';

sessionStart();

// Déjà connecté → rediriger
if (!empty($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'client.php'));
    exit;
}

$erreur = '';
$next   = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']  ?? '');
    $mdp   = trim($_POST['mdp']    ?? '');
    $next  = trim($_POST['next']   ?? '');

    // Tentative admin
    if ($login === ADMIN_LOGIN && $mdp === ADMIN_PASSWORD) {
        $_SESSION['role'] = 'admin';
        $_SESSION['nom']  = 'Admin';
        $dest = ($next && str_starts_with($next, '/')) ? $next : 'admin.php';
        header('Location: ' . $dest);
        exit;
    }

    // Tentative client (par email)
    $clients = lireJson('clients.json');
    $trouve  = null;
    foreach ($clients as $cl) {
        if ($cl['email'] === $login) { $trouve = $cl; break; }
    }

    if ($trouve && password_verify($mdp, $trouve['motDePasse'])) {
        $_SESSION['role']     = 'client';
        $_SESSION['nom']      = $trouve['nom'];
        $_SESSION['idClient'] = $trouve['idClient'];
        $dest = ($next && str_starts_with($next, '/')) ? $next : 'index.php';
        header('Location: ' . $dest);
        exit;
    }

    $erreur = 'Identifiants incorrects.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Hacienda dos Sonhos</title>
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
    <h1 style="margin-bottom:1.5rem;">Connexion</h1>

    <?php if ($erreur): ?>
        <div class="form-erreur" style="margin-bottom:1rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" class="resa-form">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

        <div class="form-group">
            <label for="login">Identifiant ou email</label>
            <input type="text" id="login" name="login" required autofocus
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="mdp">Mot de passe</label>
            <input type="password" id="mdp" name="mdp" required>
        </div>

        <button type="submit" class="btn-submit">Se connecter</button>
    </form>

    <p style="text-align:center;margin-top:1rem;font-size:.85rem;">
        <a href="reset_password.php" style="color:#143d2a;">Mot de passe oublié ?</a>
    </p>
</main>

</body>
</html>
