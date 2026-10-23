$(function () {
    var check_timeout;
    var check_url = $('#addUpdateForm').data('checkurl');
    var existing_url = $('#addUpdateForm').data('existing');

    $('#addUpdateForm #original_url').on('keyup', function () {
        var original_url = $(this).val();

        clearTimeout(check_timeout);
        check_timeout = setTimeout(function () {
            $('#original_url_check').removeClass('check-error check-success').html('<div class="loader"></div> Checking...');

            $.post(check_url, { original_url:original_url, existing_url:existing_url }, function (r) {
                if (r.status === 'success') {
                    $('#original_url_check').removeClass('check-error').addClass('check-success').html('✓ ' + r.message);
                } else if (r.message) {
                    $('#original_url_check').removeClass('check-success').addClass('check-error').html('✖ ' + r.message);
                } else {
                    $('#original_url_check').removeClass('check-success').addClass('check-error').html(r);
                }
            }, 'json');
        }, 500);
    });
});