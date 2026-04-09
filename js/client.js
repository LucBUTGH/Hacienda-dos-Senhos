// client.js — Hacienda dos Sonhos

$(document).ready(function () {

    // ── Données activités ─────────────────────────────────────────────
    const $data      = $('#activites-data');
    const activites  = $data.data('activites') || [];
    const activitesMap = {};
    activites.forEach(function (a) { activitesMap[a.idActivite] = a; });

    const labelsGranularite = { 'heure': 'À l\'heure', 'demi-journee': 'Demi-journée', 'journee': 'Journée entière' };

    // ── Formulaire dynamique selon l'activité choisie ─────────────────
    $('#idActivite').on('change', function () {
        const id  = parseInt($(this).val());
        const act = activitesMap[id];

        // Masquer tous les champs dynamiques
        $('.champ-dynamique').hide();
        $('#granularite, #niveau, #difficulte, #mode').html('').prop('required', false);
        $('#hint-groupe').hide();

        if (!act) return;

        // Granularite
        let granOpts = '<option value="">— Choisir —</option>';
        act.granularites.forEach(function (g) {
            const prix = act.tarifs[g];
            granOpts += '<option value="' + g + '">' + labelsGranularite[g] + ' — ' + prix + '€/pers.</option>';
        });
        $('#granularite').html(granOpts).prop('required', true);
        $('#wrap-granularite').show();

        // Niveau
        if (act.champs_extra.includes('niveau')) {
            let opts = '<option value="">— Choisir —</option>';
            act.options.niveau.forEach(function (n) {
                opts += '<option value="' + n + '">' + n + '</option>';
            });
            $('#niveau').html(opts).prop('required', true);
            $('#wrap-niveau').show();
        }

        // Difficulté
        if (act.champs_extra.includes('difficulte')) {
            let opts = '<option value="">— Choisir —</option>';
            act.options.difficulte.forEach(function (d) {
                opts += '<option value="' + d + '">' + d + '</option>';
            });
            $('#difficulte').html(opts).prop('required', true);
            $('#wrap-difficulte').show();
        }

        // Mode (activités de groupe)
        if (act.champs_extra.includes('mode')) {
            let opts = '<option value="">— Choisir —</option>';
            Object.entries(act.options.mode).forEach(function ([val, label]) {
                opts += '<option value="' + val + '">' + label + '</option>';
            });
            $('#mode').html(opts).prop('required', true);
            $('#wrap-mode').show();

            if (act.nb_min_personnes) {
                $('#hint-groupe').text(act.nb_min_personnes + ' joueurs minimum requis pour cette activité.').show();
            }
        }
    });

    // ── Soumission de la demande ──────────────────────────────────────
    $('#form-demande-activite').on('submit', function (e) {
        e.preventDefault();

        const $msg = $('#msg-demande');
        $msg.removeClass('succes erreur').text('').hide();

        $.post('ajax/demander_activite.php', $(this).serialize(), function (data) {
            if (data.ok) {
                const act = activitesMap[data.demande.idActivite];
                const gran = labelsGranularite[data.demande.granularite] || data.demande.granularite;
                const nbP  = data.demande.nb_personnes;

                $msg.addClass('succes').text('Demande envoyée : ' + data.nomActivite + ' · ' + gran + ' · ' + nbP + ' pers.').show();
                $('#form-demande-activite')[0].reset();
                $('.champ-dynamique').hide();
                $('#idActivite').val('');
                $('#vide-demandes').remove();

                // Construire les détails de la carte
                let details = gran + ' · ' + nbP + ' pers.';
                if (data.demande.niveau)     details += ' · ' + data.demande.niveau;
                if (data.demande.difficulte) details += ' · ' + data.demande.difficulte;
                if (data.demande.mode === 'interne') details += ' · Entre nous';
                if (data.demande.mode === 'ouvert')  details += ' · Ouvert à d\'autres clients';

                const $card = $(
                    '<div class="demande-card">' +
                    '<span class="badge badge-en_attente">En attente</span> ' +
                    '<strong>' + $('<span>').text(data.nomActivite).html() + '</strong>' +
                    ' <span style="color:#888;font-size:.85rem">' + details + '</span>' +
                    '</div>'
                );
                $('#liste-demandes').prepend($card);
            } else {
                $msg.addClass('erreur').text(data.erreur).show();
            }
        }, 'json').fail(function () {
            $msg.addClass('erreur').text('Erreur réseau.').show();
        });
    });

    // ── Commande de prestations ──────────────────────────────────────
    $('#form-prestations').on('submit', function (e) {
        e.preventDefault();

        const $msg = $('#msg-prestations');
        $msg.removeClass('succes erreur').text('').hide();

        $.post('ajax/commander_prestation.php', $(this).serialize(), function (data) {
            if (data.ok) {
                $msg.addClass('succes').text('Prestations mises à jour.').show();
                // Recharger la page pour actualiser la facture
                setTimeout(function () { location.reload(); }, 800);
            } else {
                $msg.addClass('erreur').text(data.erreur).show();
            }
        }, 'json').fail(function () {
            $msg.addClass('erreur').text('Erreur réseau.').show();
        });
    });

    // ── Message sur activité validée ─────────────────────────────────
    $(document).on('click', '.btn-save-message', function () {
        const $btn  = $(this);
        const idAP  = $btn.data('id');
        const $form = $btn.closest('.message-form');
        const $msg  = $form.find('.msg-message');

        $btn.prop('disabled', true);
        $msg.text('');

        $.post('ajax/message_activite.php', {
            idActivitePrevue: idAP,
            message: $form.find('.input-message').val()
        }, function (data) {
            $msg.css('color', data.ok ? '#1d5c3f' : '#b00020')
                .text(data.ok ? 'Message enregistré.' : data.erreur);
            $btn.prop('disabled', false);
        }, 'json').fail(function () {
            $msg.css('color', '#b00020').text('Erreur réseau.');
            $btn.prop('disabled', false);
        });
    });

});
