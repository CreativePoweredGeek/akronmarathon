$(function () {

    var $panelToggles = $('.js-datagrab-toggle-panel');

    $panelToggles.on('click', function (event) {
        event.preventDefault();

        var $panel = $(this).closest('.panel');
        $panel.find('> .panel-body, > .panel-footer').toggleClass('hidden');
    });

    var $toggleAll = $('.js-datagrab-toggle-all-custom-fields');

    $toggleAll.on('click', function (event) {
        event.preventDefault();

        var $toggle = $(this);
        var toggleText = $toggle.text();
        var toggleTextExpanded = $toggle.data('textExpanded');
        var toggleTextCollapsed = $toggle.data('textCollapsed');
        var state = $toggle.data('state');
        var $panels = $('#fieldset-custom_fields .panel-body');

        if (state === 'collapsed') {
            $toggle.data('state', 'expanded');
            $panels.removeClass('hidden');
        } else {
            $toggle.data('state', 'collapsed');
            $panels.addClass('hidden');
        }

        $toggle.text(toggleText === toggleTextCollapsed ? toggleTextExpanded : toggleTextCollapsed);
    });

    var chars = '0123456789ABCDEF';
    var string_length = 16;
    $('#generate').click( function() {
        var randomstring = '';
        for (var i=0; i<string_length; i++) {
            var rnum = Math.floor(Math.random() * chars.length);
            randomstring += chars.substring(rnum,rnum+1);
        }
        $('#passkey').val(randomstring);
    });

});
