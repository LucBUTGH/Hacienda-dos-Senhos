<?php
// auth.php — gestion de l'authentification et des rôles
// Inclus en tête de chaque page et fichier AJAX qui nécessite une protection.
// Deux rôles possibles en session : "admin" et "client".

// Identifiants admin en dur (pas de BDD, pas de hash — à changer en production)
define('ADMIN_LOGIN',    'admin');
define('ADMIN_PASSWORD', 'admin');

// Démarre la session PHP une seule fois (évite les warnings si session_start() est appelé plusieurs fois)
function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Bloque l'accès si l'utilisateur n'est pas admin.
// Redirige vers login.php en passant l'URL courante en paramètre "next"
// pour y revenir automatiquement après connexion.
function requireAdmin(): void {
    sessionStart();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Bloque l'accès si l'utilisateur n'est pas un client connecté.
// Même mécanisme de redirection que requireAdmin().
function requireClient(): void {
    sessionStart();
    if (($_SESSION['role'] ?? '') !== 'client') {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Vérifie simplement si quelqu'un est connecté (peu importe le rôle)
// Utilisé dans index.php pour afficher le lien "Mes réservations" si connecté
function isLoggedIn(): bool {
    sessionStart();
    return !empty($_SESSION['role']);
}
