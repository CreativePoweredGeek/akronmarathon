$(function () {
    var wizardData = window.DetourImportWizard || {};
    var sampleRows = wizardData.sampleRows || [];
    var $mappingRoot = $('[data-import-mapping]');

    if (!$mappingRoot.length || !sampleRows.length) {
        return;
    }

    function getSampleValue(columnIndex) {
        var i;
        var rowValue = '';

        for (i = 0; i < sampleRows.length; i++) {
            if (sampleRows[i] && sampleRows[i][columnIndex] !== undefined) {
                rowValue = String(sampleRows[i][columnIndex] || '');
                if ($.trim(rowValue) !== '') {
                    return rowValue;
                }
            }
        }

        return rowValue;
    }

    function updateFieldSample($select) {
        var field = $select.data('target-field');
        var columnIndex = String($select.val() || '');
        var sampleText = '';
        var $sampleTarget = $('[data-sample-for="' + field + '"]');

        if (columnIndex !== '') {
            sampleText = getSampleValue(columnIndex);
        }

        $sampleTarget.text(sampleText);
    }

    $mappingRoot.find('select[data-target-field]').each(function () {
        updateFieldSample($(this));
    }).on('change', function () {
        updateFieldSample($(this));
    });
});
