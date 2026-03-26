// main.js — Hacienda dos Sonhos

$(document).ready(function () {

    $('#form-resa').on('submit', function (e) {
        e.preventDefault();

        const $msg = $('#msg-resa');
        $msg.removeClass('succes erreur').text('');

        $.post('ajax/reserver.php', $(this).serialize(), function (data) {
            if (data.ok) {
                $msg.addClass('succes').text('Votre demande a bien été envoyée. Nous vous contacterons sous peu.');
                $('#form-resa')[0].reset();
            } else {
                $msg.addClass('erreur').text(data.erreur);
            }
        }, 'json').fail(function () {
            $msg.addClass('erreur').text('Une erreur réseau est survenue.');
        });
    });

});
