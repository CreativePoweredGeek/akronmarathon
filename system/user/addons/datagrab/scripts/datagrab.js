$(function () {

    let delay = 4000;
    let timers = {};

    let startPolling = function (id) {
        timers[id] = setTimeout(function request() {
            pollQueueStatus(id);
            timers[id] = setTimeout(request, delay);
        }, delay);
    };

    /**
     * @param {int} id
     * @param {boolean} restart
     */
    let loadPreview = function (id, restart, showBar) {
        let $progressBar = $('.import-progress-bar[data-id="'+ id +'"]');
        let $importStatusDisplay = $('.import-status[data-id="'+ id +'"]');
        let $syncButton = $('a[data-action="dg-sync"][data-id="'+ id +'"]');
        let src = $progressBar.data('src');
        let status = $syncButton.data('status');

        if (restart || status === 'COMPLETED' || status === 'NEW') {
            src = src + '&restart=yes';
        } else {
            src = src + '&consume=yes';
        }

        startPolling(id);

        $progressBar.html('<div class="progress-bar">' +
            '<div class="progress" style="width: 0"></div>' +
            '</div><iframe id="iframe_' + id + '" src="' + src + '" scrolling="no" allowtransparency="true"></iframe>');

        if (showBar) {
            $progressBar.addClass('import-progress-bar__active');
            $importStatusDisplay.addClass('import-status__hidden');
        }
    };

    let pollQueueStatus = function (id) {
        $.ajax({
            type: "GET",
            url: EE.datagrab.fetch_queue_status + '&id=' + id,
            cache: false,
            success: function (data) {
                if (!data || !data.response || !data.response[id]) {
                    return;
                }

                let importData = data.response[id];
                let $progressBar = $('.import-progress-bar[data-id="'+ id +'"]');
                let $purgeButton = $('a[data-action="dg-purge"][ data-id="' + id + '"]');
                let $importStatusDisplay = $('.import-status[data-id="'+ id +'"]');
                let $syncButton = $('a[data-action="dg-sync"][data-id="'+ id +'"]');
                let $restartButton = $('a[data-action="dg-sync"][data-restart="yes"][data-id="'+ id +'"]');
                let $queueSize = $('.queue-size[data-id="'+ id +'"]');

                $importStatusDisplay.html(importData.display_status);
                $queueSize.text(importData.import_queue_size + importData.delete_queue_size);
                $syncButton.data('status', importData.status);

                let totalRecords = importData.total_records;

                // If we have a large import going slow down the polling
                if (totalRecords > 2500) {
                    console.log('Slowing it down...');
                    delay = 10000;
                }

                let percent = Math.ceil(importData.last_record / totalRecords * 100);

                if (isNaN(percent)) {
                    percent = 0;
                }

                if (importData.import_queue_size > 0 || importData.delete_queue_size > 0) {
                    $purgeButton.removeClass('hidden');
                } else {
                    $purgeButton.addClass('hidden');
                }

                if (importData.total_delete_records > 0 && importData.delete_queue_size > 0) {
                    $progressBar.addClass('import-progress-bar__deleting');
                    percent = Math.ceil(100 - (importData.delete_queue_size / importData.total_delete_records * 100));
                }

                if (importData.status === 'RUNNING') {
                    $importStatusDisplay.addClass('import-status__hidden');
                    $progressBar.addClass('import-progress-bar__active');
                    $progressBar
                        .find('.progress')
                        .attr('style', 'width:' + percent + '%')
                        .html('<span style="left:'+ percent +'%">' + percent + '%</span>')
                    ;
                }

                if (importData.status === 'WAITING') {
                    $importStatusDisplay.removeClass('import-status__hidden').replaceWith(importData.display_status);
                    $progressBar.removeClass('import-progress-bar__active');
                    $progressBar
                        .find('.progress')
                        .attr('style', 'width:' + percent + '%')
                    ;
                    $restartButton.addClass('hidden');

                    if (importData.import_queue_size === 0) {
                        $restartButton.removeClass('hidden');
                    }

                    loadPreview(id, false, false);
                }

                if (importData.status === 'COMPLETED') {
                    $importStatusDisplay.replaceWith(importData.display_status);
                    $progressBar
                        .removeClass('import-progress-bar__active')
                        .removeClass('import-progress-bar__deleting')
                        .find('.progress')
                        .attr('style', 'width:0');
                    $restartButton.addClass('hidden');
                    clearTimeout(timers[id]);
                }

                // If the body contents of the iframe has content, then it means it's
                // likely a server side error such as a timeout or 504 response.
                // So force a reload to keep the import going.
                // @todo is this necessary?
                let $iframe = $('#iframe_' + id);
                if ($iframe.length && $iframe[0].contentWindow.document.body.innerHTML !== '') {
                    console.log($iframe[0].contentWindow.document.body.innerHTML);
                    // $iframe[0].src = $iframe[0].src;
                }
            },
            error: function (xhr, text, msg) {
                console.log(xhr, text, msg);
            }
        });
    };

    let $syncButtons = $('a[data-action="dg-sync"]');
    let $resetButtons = $('a[data-action="dg-reset"]');

    // Buttons are disabled on page load, make sure the JS here has
    // been booted up before allowing them to be clicked.
    $syncButtons.removeClass('disabled');
    $resetButtons.removeClass('disabled');

    $syncButtons.click(function (event) {
        event.preventDefault();

        let $button = $(this);
        let id = $button.data('id');

        loadPreview(id, false, true);
    });

    // On page load, if an import is in waiting status auto-start it.
    // This will allow users to reload the page or leave and return to
    // the page and keep imports moving along.
    $syncButtons.each(function () {
        let $button = $(this);
        let currentStatus = $button.data('status');
        let id = $button.data('id');

        if (currentStatus === 'WAITING' || currentStatus === 'RUNNING') {
            loadPreview(id, false, true);
        }
    });

    let $purgeButtons = $('a[data-action="dg-purge"]');
    $purgeButtons.removeClass('disabled');

    $purgeButtons.click(function (event) {
        event.preventDefault();

        let $button = $(this);
        let id = $button.data('id');

        $.ajax({
            type: "POST",
            url: EE.datagrab.purge_queue,
            data: {
                id: id,
                csrf_token: EE.CSRF_TOKEN
            },
            error: function (xhr, text, msg) {
                console.log(xhr, text, msg);
            }
        });
    });

    $sortableTable = $('.table-sortable');

    $sortableTable.sortable({
        axis: 'y',  // Only allow vertical dragging
        handle: '.handle', // Set drag handle
        items: 'tr', // Only allow these to be sortable
        sort: EE.sortable_sort_helper,
        forcePlaceholderSize: true,
        start: function (event, ui) {
        },
        stop: function (event, ui) {
        },
        update: function (event, ui) {
            $.ajax({
                type: "POST",
                url: EE.datagrab.sort_imports,
                data: $sortableTable.closest('form').serialize(),
                error: function (xhr, text, msg) {
                    console.log(xhr, text, msg);
                }
            });
        }
    });
});
