<div class="box panel">
    <div class="tbl-ctrls">
        <div class="panel-heading">
            <div class="title-bar">
                <h3 class="title-bar__title title-bar--large"><?=isset($cp_heading) ? $cp_heading : ee()->lang->line('nav_missing_page_tracker')?></h3>
            </div>
        </div>

        <div class="entry-pannel-notice-wrap">
            <div class="app-notice-wrap"><?=ee('CP/Alert')->getAllInlines()?></div>
        </div>

        <div class="detour-charts-container detour-charts-container--inset">
            <div class="detour-dashboard-toggle-wrap">
                <div class="detour-dashboard-toggle" role="tablist" aria-label="<?=lang('dashboard_toggle_aria')?>">
                    <button type="button" class="detour-dashboard-toggle__btn" data-range-target="day" role="tab" aria-selected="false"><?=lang('dashboard_toggle_day')?></button>
                    <button type="button" class="detour-dashboard-toggle__btn" data-range-target="week" role="tab" aria-selected="false"><?=lang('dashboard_toggle_week')?></button>
                    <button type="button" class="detour-dashboard-toggle__btn is-active" data-range-target="month" role="tab" aria-selected="true"><?=lang('dashboard_toggle_month')?></button>
                    <button type="button" class="detour-dashboard-toggle__btn" data-range-target="year" role="tab" aria-selected="false"><?=lang('dashboard_toggle_year')?></button>
                </div>
            </div>

            <div class="detour-chart-wrapper-row">
                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detourMissingTopNeededTitle" class="detour-chart-card__title"><?=lang('dashboard_top_10_needed_redirects')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap-main">
                            <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                            <div class="detour-chart-card__canvas-wrap">
                                <canvas id="detourMissingTopNeededChart" class="detour-chart-card__canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detourMissingNotFoundTitle" class="detour-chart-card__title"><?=lang('dashboard_404s_by_day')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap-main">
                            <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                            <div class="detour-chart-card__canvas-wrap">
                                <canvas id="detourMissing404DayChart" class="detour-chart-card__canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="detour-listing-header">
            <div class="title-bar">
                <h3 class="title-bar__title"><?=lang('missing_pages_all_404_hits')?></h3>
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

        <?php $this->embed('ee:_shared/table', $table); ?>

        <?php if (isset($pagination) && !empty($pagination)) {
            echo $pagination;
        } ?>
    </div>
</div>

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

    var chartData = <?= isset($missing_page_chart_data) ? $missing_page_chart_data : 'null' ?>;
    var defaultNotFoundTitle = <?=json_encode(lang('dashboard_404s_by_day'))?>;
    var defaultTopNeededTitle = <?=json_encode(lang('dashboard_top_10_needed_redirects'))?>;
    var chartInstances = {
        topNeeded: null,
        notFound: null
    };

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

    function normalizeChartSection(section) {
        var hasData = hasChartData(section);

        return {
            hasData: hasData,
            data: hasData ? section : {labels: [], datasets: []}
        };
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

    function renderLineChart(canvasId, section, existingChart) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) {
            return existingChart;
        }

        var normalized = normalizeChartSection(section);
        var chartSection = normalized.data;
        setNoResultsState(canvasId, normalized.hasData);

        if (existingChart && typeof existingChart.destroy === 'function') {
            existingChart.destroy();
        }

        if (!normalized.hasData) {
            return null;
        }

        return new Chart(ctx, {
            type: 'line',
            data: chartSection,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: normalized.hasData,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    function renderBarChart(canvasId, section, existingChart) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) {
            return existingChart;
        }

        var normalized = normalizeChartSection(section);
        var chartSection = normalized.data;
        setNoResultsState(canvasId, normalized.hasData);

        if (existingChart && typeof existingChart.destroy === 'function') {
            existingChart.destroy();
        }

        if (!normalized.hasData) {
            return null;
        }

        var links = chartSection && chartSection.links && chartSection.links.length ? chartSection.links : [];

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
            data: chartSection,
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
                        display: normalized.hasData,
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

    function renderRange(rangeName) {
        var rangeData = chartData && chartData.ranges && chartData.ranges[rangeName] ? chartData.ranges[rangeName] : null;
        var notFoundTitle = document.getElementById('detourMissingNotFoundTitle');
        var topNeededTitle = document.getElementById('detourMissingTopNeededTitle');
        if (notFoundTitle) {
            notFoundTitle.textContent = rangeData && rangeData.not_found_title ? rangeData.not_found_title : defaultNotFoundTitle;
        }
        if (topNeededTitle) {
            topNeededTitle.textContent = rangeData && rangeData.top_needed_title ? rangeData.top_needed_title : defaultTopNeededTitle;
        }

        chartInstances.topNeeded = renderBarChart(
            'detourMissingTopNeededChart',
            rangeData ? rangeData.top_needed_redirects : null,
            chartInstances.topNeeded
        );
        chartInstances.notFound = renderLineChart(
            'detourMissing404DayChart',
            rangeData ? rangeData.not_found_daily : null,
            chartInstances.notFound
        );
    }

    function initializeRangeToggle() {
        var toggleRoot = document.querySelector('.detour-dashboard-toggle');
        if (!toggleRoot) {
            renderRange('month');
            return;
        }

        var buttons = toggleRoot.querySelectorAll('[data-range-target]');
        var storageKey = 'detour_missing_pages_range';
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
