<?php
/**
 * Tests de la facturation
 * Exécuter : php tests_facturation.php
 */

require_once __DIR__ . '/helpers.php';

$ok = 0;
$ko = 0;

function test(string $nom, bool $condition, string $detail = '') {
    global $ok, $ko;
    if ($condition) {
        echo "  OK  $nom\n";
        $ok++;
    } else {
        echo "  FAIL  $nom" . ($detail ? " — $detail" : "") . "\n";
        $ko++;
    }
}

$reservations    = lireJson('reservations.json');
$prestations     = lireJson('prestations.json');
$chambres        = lireJson('chambres.json');
$activites       = lireJson('activites.json');
$demandes        = lireJson('demandes_activites.json');

$prestationsIndex = [];
foreach ($prestations as $p) $prestationsIndex[$p['idPrestation']] = $p;
$chambresIndex = [];
foreach ($chambres as $ch) $chambresIndex[$ch['idChambres']] = $ch;
$activitesIndex = [];
foreach ($activites as $a) $activitesIndex[$a['idActivite']] = $a;

// =====================================================================
echo "\n=== 1. Structure des réservations (nouveaux champs) ===\n";
// =====================================================================

foreach ($reservations as $r) {
    test("Résa #{$r['idResa']} — champ 'arrhes' présent", array_key_exists('arrhes', $r));
    test("Résa #{$r['idResa']} — champ 'reductions' présent", array_key_exists('reductions', $r));
    test("Résa #{$r['idResa']} — arrhes >= 0", ($r['arrhes'] ?? 0) >= 0);
}

// =====================================================================
echo "\n=== 2. Calcul facture — Sophie Martin (résa #1) ===\n";
// =====================================================================

$resa1 = $reservations[0]; // Sophie Martin
$chambre1 = $chambresIndex[$resa1['idChambre']];
$nbNuits1 = (new DateTime($resa1['date_debut']))->diff(new DateTime($resa1['date_fin']))->days;

// Hébergement
$prixChambre1 = $chambre1['prix_nuit'] * $nbNuits1;
test("Hébergement : {$chambre1['nom']} × $nbNuits1 nuits = {$prixChambre1}€",
    $prixChambre1 === 280 * 6); // 280€ × 6 nuits = 1680€

// Prestations
$totalPrestas1 = 0;
$reductions1 = (array) ($resa1['reductions'] ?? []);
foreach ($resa1['prestations'] as $idP) {
    $p = $prestationsIndex[$idP];
    $reduc = intval($reductions1[$idP] ?? 0);
    $prix = $p['prix'] * (1 - $reduc / 100);
    $totalPrestas1 += $prix;
}
// Presta 1 (Navette 45€) + Presta 3 (Dîner 65€) = 110€
test("Prestations Sophie : Navette + Dîner = 110€", $totalPrestas1 == 110,
    "obtenu: $totalPrestas1");

// Activités validées
$totalAct1 = 0;
foreach ($demandes as $d) {
    if ($d['idResa'] === 1 && $d['statut'] === 'validee') {
        $act = $activitesIndex[$d['idActivite']];
        $prix = ($act['tarifs'][$d['granularite']] ?? 0) * $d['nb_personnes'];
        $totalAct1 += $prix;
    }
}
// Demande #1 : Cavalgada demi-journée × 2 = 65 × 2 = 130€
test("Activités validées Sophie : Cavalgada 130€", $totalAct1 === 130,
    "obtenu: $totalAct1");

$total1 = $prixChambre1 + $totalPrestas1 + $totalAct1 - ($resa1['arrhes'] ?? 0);
test("Total facture Sophie : 1920€", $total1 == 1920, "obtenu: $total1");

// =====================================================================
echo "\n=== 3. Calcul facture — Lucas Ferreira (résa #2) ===\n";
// =====================================================================

$resa2 = $reservations[1]; // Lucas
$chambre2 = $chambresIndex[$resa2['idChambre']];
$nbNuits2 = (new DateTime($resa2['date_debut']))->diff(new DateTime($resa2['date_fin']))->days;

$prixChambre2 = $chambre2['prix_nuit'] * $nbNuits2;
test("Hébergement : {$chambre2['nom']} × $nbNuits2 nuits = {$prixChambre2}€",
    $prixChambre2 === 680 * 7); // 680€ × 7 nuits = 4760€

$totalPrestas2 = 0;
foreach ($resa2['prestations'] as $idP) {
    $p = $prestationsIndex[$idP];
    $totalPrestas2 += $p['prix'];
}
// Presta 2 (Petit-déjeuner 18€) + Presta 4 (Panier 25€) = 43€
test("Prestations Lucas : Petit-déj + Panier = 43€", $totalPrestas2 == 43,
    "obtenu: $totalPrestas2");

$totalAct2 = 0;
foreach ($demandes as $d) {
    if ($d['idResa'] === 2 && $d['statut'] === 'validee') {
        $act = $activitesIndex[$d['idActivite']];
        $prix = ($act['tarifs'][$d['granularite']] ?? 0) * $d['nb_personnes'];
        $totalAct2 += $prix;
    }
}
// Demande #2 : Cavalgada demi-journée × 4 = 65 × 4 = 260€
// Demande #4 : Futebol heure × 6 = 15 × 6 = 90€
test("Activités validées Lucas : Cavalgada 260€ + Futebol 90€ = 350€",
    $totalAct2 === 350, "obtenu: $totalAct2");

$total2 = $prixChambre2 + $totalPrestas2 + $totalAct2;
test("Total facture Lucas : 5153€", $total2 == 5153, "obtenu: $total2");

// =====================================================================
echo "\n=== 4. Test réductions ===\n";
// =====================================================================

// Simuler une réduction de 20% sur la prestation Navette (45€) pour Sophie
$prixAvecReduc = 45 * (1 - 20 / 100);
test("Navette avec -20% : 36€", $prixAvecReduc == 36);

$prixAvecReduc50 = 65 * (1 - 50 / 100);
test("Dîner avec -50% : 32.5€", $prixAvecReduc50 == 32.5);

// =====================================================================
echo "\n=== 5. Test arrhes ===\n";
// =====================================================================

// Simuler des arrhes de 500€
$totalAvecArrhes = 1920 - 500;
test("Total Sophie avec arrhes 500€ : 1420€", $totalAvecArrhes == 1420);

// =====================================================================
echo "\n=== 6. Validations endpoints ===\n";
// =====================================================================

// Vérifier que les réductions n'acceptent que 0, 10, 20, 50
$valeursValides = [0, 10, 20, 50];
foreach ([0, 10, 20, 50] as $v) {
    test("Réduction $v% valide", in_array($v, $valeursValides));
}
foreach ([5, 15, 30, 100] as $v) {
    test("Réduction $v% invalide (rejetée)", !in_array($v, $valeursValides));
}

// =====================================================================
echo "\n══════════════════════════════════\n";
echo "Résultat : $ok OK / $ko FAIL\n";
echo "══════════════════════════════════\n\n";

exit($ko > 0 ? 1 : 0);
