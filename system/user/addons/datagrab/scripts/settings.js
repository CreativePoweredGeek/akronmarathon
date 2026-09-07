$(function () {

    var $importType = $('.js-dg-import-type');
    var $importTypeField = $importType.find('select');

    var $importFile = $('.js-dg-import-file');
    var $importFileField = $importFile.find('select');

    var $importChannel = $('.js-dg-import-channel');
    var $importChannelField = $importChannel.find('select');

    var toggle = function ($fieldset, isVisible) {
        $fieldset
            .toggleClass('hidden', !isVisible)
            .toggleClass('fieldset-required', isVisible)
        ;
    };

    $importTypeField.on('change', function (event) {
        var val = $(this).find(':selected').val();

        // Member imports target global member fields, so neither a channel
        // nor an upload directory is selected.
        toggle($importFile, val === 'file');
        toggle($importChannel, val === 'entry');
    });

    $importTypeField.trigger('change');

});
