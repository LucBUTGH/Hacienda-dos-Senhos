// admin.js — Hacienda dos Sonhos

/* ================================================================
   SECTION ACTIVITÉS
   ================================================================ */

function chargerActivitesDuJour(date) {
    const $zone = $('#liste-activites-jour');
    const $msg  = $('#msg-activites');
    const animateurs = $('#animateurs-data').data('animateurs') || [];

    $msg.hide().text('');
    $zone.html('<p class="vide">Chargement…</p>');

    $.get('ajax/activites_du_jour.php', { date: date }, function (data) {
        if (!data.ok) {
            $zone.html('<p class="vide">' + data.erreur + '</p>');
            return;
        }
        if (data.groupes.length === 0) {
            $zone.html('<p class="vide">Aucune demande d\'activité non satisfaite pour cette date.</p>');
            return;
        }

        // Construire les options d'animateurs
        let optAnimateurs = '<option value="">— Animateur —</option>';
        animateurs.forEach(function (nom) {
            optAnimateurs += '<option value="' + nom + '">' + nom + '</option>';
        });

        let html = '';
        data.groupes.forEach(function (groupe) {
            const act        = groupe.activite;
            const typeSpec   = act.type_special;
            const idActivite = act.idActivite;

            html += '<div class="activite-groupe-card" data-type="' + typeSpec + '" data-date="' + date + '">';
            html += '<div class="activite-groupe-header">';
            html += '<strong>' + act.nom + '</strong>';
            const labelsGranH = { 'heure': 'À l\'heure', 'demi-journee': 'Demi-journée', 'journee': 'Journée' };
            const tarifsEntries = Object.entries(act.tarifs || {});
            const tarifsStr = tarifsEntries.map(function([g, p]) { return (labelsGranH[g] || g) + ' ' + p + '€'; }).join(' · ');
            html += ' <span class="badge-granularite">' + tarifsStr + '</span>';
            html += '</div>';

            // Avertissement activités de groupe
            if (typeSpec === 'groupe') {
                const nbMinDef = act.nb_min_personnes || 0;
                html += '<p class="hint-special">Activité de groupe — minimum ' + nbMinDef + ' participants. '
                      + 'Vérifiez le mode souhaité par chaque client avant de valider.</p>';
            }

            // Liste des demandes (checkboxes)
            const labelsGran = { 'heure': 'À l\'heure', 'demi-journee': 'Demi-journée', 'journee': 'Journée' };
            const labelsMode = { 'interne': '🔒 Entre eux', 'ouvert': '🔓 Ouvert' };

            html += '<div class="demandes-liste">';
            groupe.demandes.forEach(function (d) {
                html += '<label class="demande-admin-item">';
                html += '<input type="checkbox" class="chk-demande" value="' + d.idDemande + '" data-id-activite="' + idActivite + '">';
                html += ' <strong>' + d.nomClient + '</strong>';
                html += ' · ' + d.nbPersonnes + ' pers.';
                if (d.granularite) html += ' · ' + (labelsGran[d.granularite] || d.granularite);
                if (d.niveau)      html += ' · <em>' + d.niveau + '</em>';
                if (d.difficulte)  html += ' · <em>' + d.difficulte + '</em>';
                if (d.mode)        html += ' · <strong>' + (labelsMode[d.mode] || d.mode) + '</strong>';
                if (d.preferences) html += ' <span style="color:#888;font-size:.85rem">— ' + $('<div>').text(d.preferences).html() + '</span>';
                html += '</label>';
            });
            html += '</div>';

            // Champs de validation
            html += '<div class="validation-groupe">';
            html += '<select class="select-animateur">' + optAnimateurs + '</select>';

            if (typeSpec === 'groupe') {
                const nbMinDef = act.nb_min_personnes || 0;
                html += '<input type="number" class="input-nb-min" min="1" value="' + nbMinDef + '" placeholder="Min. participants" style="width:150px" title="Seuil minimum de participants">';
            }

            html += '<button class="btn-valider-activite btn-action">Valider la sélection</button>';
            html += '<span class="msg-groupe-action"></span>';
            html += '</div>';
            html += '</div>';
        });

        $zone.html(html);

    }, 'json').fail(function () {
        $zone.html('<p class="vide" style="color:#b00020">Erreur réseau.</p>');
    });
}

$(document).ready(function () {

    // Charger au changement de date
    $('#date-activites').on('change', function () {
        chargerActivitesDuJour($(this).val());
    });

    // Lancer au chargement avec la date du jour
    if ($('#date-activites').length) {
        chargerActivitesDuJour($('#date-activites').val());
    }

    // Valider un groupe d'activités
    $(document).on('click', '.btn-valider-activite', function () {
        const $btn    = $(this);
        const $groupe = $btn.closest('.activite-groupe-card');
        const $msg    = $groupe.find('.msg-groupe-action');
        const date    = $groupe.data('date');
        const type    = $groupe.data('type');

        // Récupérer les demandes cochées
        const idDemandes = [];
        $groupe.find('.chk-demande:checked').each(function () {
            idDemandes.push($(this).val());
        });

        if (idDemandes.length === 0) {
            $msg.css('color', '#b00020').text('Sélectionnez au moins une demande.');
            return;
        }

        const animateur = $groupe.find('.select-animateur').val();
        if (!animateur) {
            $msg.css('color', '#b00020').text('Choisissez un animateur.');
            return;
        }

        const nbMin = $groupe.find('.input-nb-min').val() || 0;

        $btn.prop('disabled', true);
        $msg.text('');

        $.post('ajax/valider_activites.php', {
            date:       date,
            idDemandes: idDemandes,
            animateur:  animateur,
            nb_min:     nbMin,
        }, function (data) {
            if (data.ok) {
                // Retirer les demandes validées de la liste
                $groupe.find('.chk-demande:checked').closest('.demande-admin-item').fadeOut(300, function () {
                    $(this).remove();
                    // Si le groupe est vide, le retirer
                    if ($groupe.find('.chk-demande').length === 0) {
                        $groupe.fadeOut(400, function () { $(this).remove(); });
                        if ($('.activite-groupe-card').length === 0) {
                            $('#liste-activites-jour').html('<p class="vide">Toutes les demandes ont été satisfaites pour cette date.</p>');
                        }
                    }
                });
                $msg.css('color', '#1d5c3f').text('Activité planifiée avec succès.');
            } else {
                $msg.css('color', '#b00020').text(data.erreur);
            }
            $btn.prop('disabled', false);
        }, 'json').fail(function () {
            $msg.css('color', '#b00020').text('Erreur réseau.');
            $btn.prop('disabled', false);
        });
    });

});

function updateStatEnAttente() {
    $('#stat-en-attente').text($('.resa-card.resa-en_attente').length);
}

function refreshCardRooms($card) {
    const debut  = $card.data('debut');
    const fin    = $card.data('fin');
    const idResa = $card.data('id');
    const nbPersonnes = $card.data('nb-personnes');

    $.get('ajax/chambres_dispo.php', { debut: debut, fin: fin, nb_personnes: nbPersonnes }, function (data) {
        const $actions    = $card.find('.resa-actions');
        const refuserBtn  = '<button class="btn-refuser" data-id="' + idResa + '">Refuser</button>';

        if (!data.ok || data.chambres.length === 0) {
            $actions.html('<p class="no-chambre">Aucune chambre disponible pour ces dates.</p>' + refuserBtn);
        } else {
            let options = '<option value="">— Choisir une chambre —</option>';
            data.chambres.forEach(function (ch) {
                options += '<option value="' + ch.idChambres + '">' +
                    ch.nom + ' (' + ch.capacite + ' pers. max · ' + ch.prix_nuit + '€/nuit)</option>';
            });
            $actions.html(
                '<select class="select-chambre">' + options + '</select>' +
                '<button class="btn-valider" data-id="' + idResa + '">Valider</button>' +
                refuserBtn
            );
        }
    }, 'json');
}

$(document).ready(function () {

    // Valider une réservation
    $(document).on('click', '.btn-valider', function () {
        const $btn    = $(this);
        const idResa  = $btn.data('id');
        const $card   = $btn.closest('.resa-card');
        const $select = $card.find('.select-chambre');
        const $msg    = $card.find('.msg-action');
        const idChambre = $select.val();

        if (!idChambre) {
            $msg.removeClass('succes').addClass('erreur').text('Veuillez sélectionner une chambre.').show();
            return;
        }

        $btn.prop('disabled', true);

        $.post('ajax/valider_resa.php', { idResa: idResa, idChambre: idChambre }, function (data) {
            if (data.ok) {
                $card.find('.badge').removeClass('badge-en_attente').addClass('badge-validee').text('Validée');
                $card.removeClass('resa-en_attente').addClass('resa-validee');
                $card.find('.resa-actions').replaceWith(
                    '<div class="email-template">' +
                    '<p class="email-template-label">Message à copier dans votre logiciel de mail :</p>' +
                    '<textarea readonly>' + $('<div>').text(data.messageType).html() + '</textarea>' +
                    '</div>'
                );
                $msg.remove();
                updateStatEnAttente();

                // Rafraîchir les selects des autres cards en attente
                $('.resa-card.resa-en_attente').each(function () {
                    refreshCardRooms($(this));
                });
            } else {
                $msg.removeClass('succes').addClass('erreur').text(data.erreur).show();
                $btn.prop('disabled', false);
            }
        }, 'json').fail(function () {
            $msg.removeClass('succes').addClass('erreur').text('Erreur réseau.').show();
            $btn.prop('disabled', false);
        });
    });

    // Appliquer une réduction sur une prestation
    $(document).on('change', '.select-reduction', function () {
        const $select = $(this);
        const $ligne  = $select.closest('.reduction-ligne');
        const $msg    = $ligne.find('.msg-reduction');
        const idResa      = $ligne.data('id-resa');
        const idPrestation = $ligne.data('id-prestation');
        const reduction    = $select.val();

        $msg.text('');

        $.post('ajax/appliquer_reduction.php', {
            idResa: idResa,
            idPrestation: idPrestation,
            reduction: reduction
        }, function (data) {
            if (!data.ok) $msg.css('color', '#b00020').text(data.erreur);
            if (data.ok) {
                const prixBase = parseFloat($ligne.find('.label-prestation').data('prix-base'));
                const pct = parseInt(reduction, 10);
                const prixReduit = pct > 0 ? Math.round(prixBase * (1 - pct / 100) * 100) / 100 : prixBase;
                $ligne.find('.prix-affiche').text(prixReduit);
                setTimeout(function () { $msg.text(''); }, 1500);
            }
        }, 'json').fail(function () {
            $msg.css('color', '#b00020').text('Erreur');
        });
    });

    // Refuser une réservation
    $(document).on('click', '.btn-refuser', function () {
        const $btn   = $(this);
        const idResa = $btn.data('id');
        const $card  = $btn.closest('.resa-card');
        const $msg   = $card.find('.msg-action');

        $btn.prop('disabled', true);

        $.post('ajax/refuser_resa.php', { idResa: idResa }, function (data) {
            if (data.ok) {
                $card.find('.badge').removeClass('badge-en_attente').addClass('badge-refusee').text('Refusée');
                $card.removeClass('resa-en_attente').addClass('resa-refusee');
                $card.find('.resa-actions').remove();
                $msg.remove();
                updateStatEnAttente();
            } else {
                $msg.removeClass('succes').addClass('erreur').text(data.erreur).show();
                $btn.prop('disabled', false);
            }
        }, 'json').fail(function () {
            $msg.removeClass('succes').addClass('erreur').text('Erreur réseau.').show();
            $btn.prop('disabled', false);
        });
    });

});
