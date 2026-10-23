<div class="box panel">
    <div class="tbl-ctrls">
        <div class="panel-heading">
            <div class="title-bar">
                <h3 class="title-bar__title title-bar--large"><?=isset($cp_heading) ? $cp_heading : ee()->lang->line('dashboard')?></h3>

                <div class="title-bar__extra-tools">
                    <a href="<?=isset($add_detour_link) ? $add_detour_link : ''?>" class="btn button button--primary"><?=ee()->lang->line('label_add_detour')?></a>
                </div>

            </div>
        </div>

        <div class="entry-pannel-notice-wrap">
            <div class="app-notice-wrap"><?=ee('CP/Alert')->getAllInlines()?></div>
        </div>

        <div class="detour-charts-container detour-charts-container--inset">
            <div class="detour-chart-wrapper detour-chart-wrapper-full">
                <?php if (!empty($hit_counter_enabled)): ?>
                <div class="detour-dashboard-toggle-wrap">
                    <div class="detour-dashboard-toggle" role="tablist" aria-label="<?=lang('dashboard_toggle_aria')?>">
                        <button type="button" class="detour-dashboard-toggle__btn" data-range-target="day" role="tab" aria-selected="false"><?=lang('dashboard_toggle_day')?></button>
                        <button type="button" class="detour-dashboard-toggle__btn" data-range-target="week" role="tab" aria-selected="false"><?=lang('dashboard_toggle_week')?></button>
                        <button type="button" class="detour-dashboard-toggle__btn is-active" data-range-target="month" role="tab" aria-selected="true"><?=lang('dashboard_toggle_month')?></button>
                        <button type="button" class="detour-dashboard-toggle__btn" data-range-target="year" role="tab" aria-selected="false"><?=lang('dashboard_toggle_year')?></button>
                    </div>
                </div>
                <div class="detour-chart-card detour-chart-card--detours-top">
                    <div class="detour-chart-card__header">
                        <div id="detourDetoursTopRedirectsTitle" class="detour-chart-card__title"><?=lang('detours_top_10_redirects_hit')?></div>
                    </div>
                    <div class="detour-chart-card__canvas-wrap-main">
                        <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                        <div class="detour-chart-card__canvas-wrap" style="min-height: 315px; height: 315px;">
                            <canvas id="detourDetoursTopRedirectsChart" class="detour-chart-card__canvas"></canvas>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="detour-chart-card detour-chart-card--status">
                    <div class="detour-chart-card__header">
                        <div class="detour-chart-card__title"><?=lang('dashboard_enable_redirect_reporting_title')?></div>
                    </div>
                    <div class="detour-chart-card__status-wrap" style="min-height: 0; padding-top: 16px; padding-bottom: 16px;">
                        <p class="detour-chart-card__status-text"><?=lang('dashboard_enable_redirect_reporting_desc')?></p>
                        <?php echo form_open($enable_hit_counter_url, array('class' => 'detour-enable-reporting-form')); ?>
                            <?php if (defined('CSRF_TOKEN')): ?>
                                <input type="hidden" name="csrf_token" value="<?=CSRF_TOKEN?>">
                            <?php endif; ?>
                            <button type="submit" class="button button--primary"><?=lang('dashboard_enable_redirect_reporting_cta')?></button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="detour-listing-header">
            <div class="title-bar">
                <h3 class="title-bar__title"><?=lang('detours_all_detours')?></h3>
                <div class="title-bar__extra-tools">
                    <?php echo form_open($search_url, array('class' => 'filter-search-bar__search', 'method' => 'post')); ?>
                        <div class="field-control with-icon-start filter-search-bar__input-wrap">
                            <i class="fal fa-search icon-start"></i>
                            <input class="filter-search-bar__input" placeholder="<?=lang('search')?>" type="text" name="search" value="<?=form_prep(isset($search_query) ? $search_query : '')?>" aria-label="<?=lang('search')?>">
                        </div>
                        <button type="submit" class="button button--primary"><?=lang('search')?></button>
                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>

        <?php echo form_open($delete_action_url, array('class' => 'settings')); ?>
            <?php $this->embed('ee:_shared/table', $table); ?>

            <?php if (isset($pagination) && !empty($pagination)) {
                echo $pagination;
            } ?>

            <?php 
            if (!empty($table['data'])) {
                $this->embed('ee:_shared/form/bulk-action-bar', [
                    'options' => [
                        [
                            'value' => "",
                            'text' => '-- ' . lang('with_selected') . ' --'
                        ],
                        [
                            'value' => "delete",
                            'text' => ee()->lang->line('btn_delete_detours'),
                            'attrs' => ' data-confirm-trigger="selected" rel="modal-confirm-delete"'
                        ]
                    ],
                    'modal' => true
                ]);
            }
            ?>
        <?php echo form_close(); ?>
    </div>
</div>

<?php
$modal_vars = array(
    'name' => 'modal-confirm-delete',
    'form_url' => $delete_action_url,
    'hidden' => array(
        'bulk_action' => 'delete'
    )
);

$modal = $this->make('ee:_shared/modal_confirm_delete')->render($modal_vars);
ee('CP/Modal')->addModal('delete', $modal);
?>

<script>
(function () {
    function initDetourCharts() {
        if (typeof Chart === 'undefined') {
            return;
        }

        if (typeof EE === 'undefined' || !EE) {
            window.EE = {lang: {}};
        }

        if (typeof EE.lang === 'undefined' || !EE.lang) {
            EE.lang = {};
        }

    var hitCounterEnabled = <?=!empty($hit_counter_enabled) ? 'true' : 'false'?>;
    var chartData = <?= isset($detours_chart_data) ? $detours_chart_data : 'null' ?>;

    if (!hitCounterEnabled) {
        return;
    }

    function hasDataSetValue(dataSet) {
        if (!dataSet || !dataSet.data || !dataSet.data.length) {
            return false;
        }

        for (var i = 0; i < dataSet.data.length; i++) {
            if (Number(dataSet.data[i]) !== 0) {
                return true;
            }
        }

        return false;
    }

    function hasChartData(chartSection) {
        if (!chartSection || !chartSection.labels || !chartSection.labels.length || !chartSection.datasets || !chartSection.datasets.length) {
            return false;
        }

        for (var i = 0; i < chartSection.datasets.length; i++) {
            if (hasDataSetValue(chartSection.datasets[i])) {
                return true;
            }
        }

        return false;
    }

    function setNoResultsState(canvasId, hasData) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) {
            return;
        }

        var wrap = canvas.parentNode;
        if (!wrap) {
            return;
        }

        var noResults = wrap.parentNode.querySelector('.no-results');
        if (noResults) {
            noResults.hidden = hasData;
        }

        canvas.hidden = !hasData;
        wrap.hidden = !hasData;
    }

    function renderBarChart(canvasId, section, existingChart) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) {
            return existingChart;
        }

        var hasData = hasChartData(section);
        setNoResultsState(canvasId, hasData);

        if (existingChart && typeof existingChart.destroy === 'function') {
            existingChart.destroy();
        }

        if (!hasData) {
            return null;
        }

        var links = section && section.links && section.links.length ? section.links : [];

        function getElementLink(activeElements) {
            if (!activeElements || !activeElements.length) {
                return '';
            }

            var index = typeof activeElements[0].index === 'number' ? activeElements[0].index : -1;
            if (index < 0 || !links[index]) {
                return '';
            }

            return String(links[index]);
        }

        return new Chart(ctx, {
            type: 'bar',
            data: section,
            options: {
                onClick: function (_event, activeElements) {
                    var targetLink = getElementLink(activeElements);
                    if (targetLink) {
                        window.location.href = targetLink;
                    }
                },
                onHover: function (_event, activeElements) {
                    var hasTargetLink = getElementLink(activeElements) !== '';
                    ctx.style.cursor = hasTargetLink ? 'pointer' : 'default';
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 40,
                            minRotation: 0
                        }
                    }
                }
            }
        });
    }

    var topRedirectsChart = null;

    function setText(elementId, value) {
        var element = document.getElementById(elementId);
        if (!element || !value) {
            return;
        }

        element.textContent = value;
    }

    function renderRange(rangeName) {
        var rangeData = chartData && chartData.ranges && chartData.ranges[rangeName] ? chartData.ranges[rangeName] : null;
        setText('detourDetoursTopRedirectsTitle', rangeData ? rangeData.top_redirects_title : null);
        topRedirectsChart = renderBarChart(
            'detourDetoursTopRedirectsChart',
            rangeData ? rangeData.top_redirects : null,
            topRedirectsChart
        );
    }

    function initializeRangeToggle() {
        var toggleRoot = document.querySelector('.detour-dashboard-toggle');
        if (!toggleRoot) {
            renderRange('month');
            return;
        }

        var buttons = toggleRoot.querySelectorAll('[data-range-target]');
        var storageKey = 'detour_detours_range';
        var defaultRange = chartData && chartData.default_range ? chartData.default_range : 'month';

        function setRange(rangeName) {
            var range = rangeName;
            if (range !== 'day' && range !== 'week' && range !== 'month' && range !== 'year') {
                range = defaultRange;
            }

            renderRange(range);

            for (var i = 0; i < buttons.length; i++) {
                var isActiveButton = buttons[i].getAttribute('data-range-target') === range;
                buttons[i].classList.toggle('is-active', isActiveButton);
                buttons[i].setAttribute('aria-selected', isActiveButton ? 'true' : 'false');
            }

            try {
                window.localStorage.setItem(storageKey, range);
            } catch (e) {
                // no-op
            }
        }

        for (var j = 0; j < buttons.length; j++) {
            buttons[j].addEventListener('click', function () {
                setRange(this.getAttribute('data-range-target'));
            });
        }

        var initialRange = defaultRange;

        try {
            var savedRange = window.localStorage.getItem(storageKey);
            if (savedRange === 'day' || savedRange === 'week' || savedRange === 'month' || savedRange === 'year') {
                initialRange = savedRange;
            }
        } catch (e) {
            // no-op
        }

        setRange(initialRange);
    }

        initializeRangeToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDetourCharts);
    } else {
        initDetourCharts();
    }
})();
</script>
