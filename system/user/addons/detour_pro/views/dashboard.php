<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title title-bar--large"><?=isset($cp_heading) ? $cp_heading : lang('nav_dashboard')?></h3>

            <div class="title-bar__extra-tools">
                <a href="<?=isset($add_detour_link) ? $add_detour_link : ''?>" class="btn button button--primary"><?=lang('label_add_detour')?></a>
            </div>
        </div>
    </div>

    <div class="panel-body">
        <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

        <div class="detour-dashboard-toggle-wrap">
            <div class="detour-dashboard-toggle" role="tablist" aria-label="<?=lang('dashboard_toggle_aria')?>">
                <button type="button" class="detour-dashboard-toggle__btn" data-range-target="day" role="tab" aria-selected="false"><?=lang('dashboard_toggle_day')?></button>
                <button type="button" class="detour-dashboard-toggle__btn" data-range-target="week" role="tab" aria-selected="false"><?=lang('dashboard_toggle_week')?></button>
                <button type="button" class="detour-dashboard-toggle__btn is-active" data-range-target="month" role="tab" aria-selected="true"><?=lang('dashboard_toggle_month')?></button>
                <button type="button" class="detour-dashboard-toggle__btn" data-range-target="year" role="tab" aria-selected="false"><?=lang('dashboard_toggle_year')?></button>
            </div>
        </div>

        <div class="detour-charts-container">
            <div class="detour-chart-wrapper-row">
                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detourNotFoundTitle" class="detour-chart-card__title"><?=lang('dashboard_404s_by_day')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap-main">
                            <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                            <div class="detour-chart-card__canvas-wrap">
                                <canvas id="detour404Chart" class="detour-chart-card__canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($hit_counter_enabled)): ?>
                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detour301TrendTitle" class="detour-chart-card__title"><?=lang('dashboard_301s_by_day')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap-main">
                            <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                            <div class="detour-chart-card__canvas-wrap">
                                <canvas id="detour301Chart" class="detour-chart-card__canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card detour-chart-card--status">
                        <div class="detour-chart-card__header">
                            <div class="detour-chart-card__title"><?=lang('dashboard_enable_redirect_reporting_title')?></div>
                        </div>
                        <div class="detour-chart-card__status-wrap">
                            <p class="detour-chart-card__status-text"><?=lang('dashboard_enable_redirect_reporting_desc')?></p>
                            <?php echo form_open($enable_hit_counter_url, array('class' => 'detour-enable-reporting-form')); ?>
                                <?php if (defined('CSRF_TOKEN')): ?>
                                    <input type="hidden" name="csrf_token" value="<?=CSRF_TOKEN?>">
                                <?php endif; ?>
                                <button type="submit" class="button button--primary"><?=lang('dashboard_enable_redirect_reporting_cta')?></button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($hit_counter_enabled)): ?>
            <div class="detour-chart-wrapper-row">
                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detourComparisonTitle" class="detour-chart-card__title"><?=lang('dashboard_redirected_vs_404_day')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap-main">
                            <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                            <div class="detour-chart-card__canvas-wrap">
                                <canvas id="detourRedirectVs404Chart" class="detour-chart-card__canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detour-chart-wrapper detour-chart-wrapper-half">
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detourTop301Title" class="detour-chart-card__title"><?=lang('dashboard_top_10_301_hits')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap-main">
                            <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                            <div class="detour-chart-card__canvas-wrap">
                                <canvas id="detourTop301Chart" class="detour-chart-card__canvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="detour-chart-wrapper detour-chart-wrapper-full">
                <div class="detour-chart-card">
                    <div class="detour-chart-card__header">
                        <div id="detourTopNeededTitle" class="detour-chart-card__title"><?=lang('dashboard_top_10_needed_redirects')?></div>
                    </div>
                    <div class="detour-chart-card__canvas-wrap-main">
                        <div class="no-results" hidden><?=lang('dashboard_no_data')?></div>
                        <div class="detour-chart-card__canvas-wrap">
                            <canvas id="detourTopNeededChart" class="detour-chart-card__canvas"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detour-chart-wrapper detour-chart-wrapper-full">
                <div class="detour-chart-card">
                    <div class="detour-chart-card__header">
                        <div id="detourNeededTableTitle" class="detour-chart-card__title"><?=isset($needed_table_title) ? $needed_table_title : lang('dashboard_needed_redirects_table_title_all_time')?></div>
                    </div>
                    <div class="detour-chart-card__table-wrap">
                        <div class="table-responsive">
                            <table class="mainTable detour-needed-table" role="table">
                                <thead>
                                    <tr>
                                        <th><?=lang('title_url')?></th>
                                        <th><?=lang('title_hits')?></th>
                                        <th><?=lang('title_detour')?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($top_needed_redirects)): ?>
                                    <?php foreach ($top_needed_redirects as $row): ?>
                                        <tr>
                                            <td><?=htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8')?></td>
                                            <td><?=htmlspecialchars((string) $row['hits'], ENT_QUOTES, 'UTF-8')?></td>
                                            <td><a href="<?=$row['add_detour_link']?>"><?=lang('label_add_detour')?></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3"><?=lang('dashboard_no_data')?></td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    var chartData = <?= isset($chart_data) ? $chart_data : 'null' ?>;
    var hitCounterEnabled = <?=!empty($hit_counter_enabled) ? 'true' : 'false'?>;
    var chartInstances = {
        notFound: null,
        redirect301: null,
        comparison: null,
        top301: null,
        topNeeded: null
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
                onClick: function (event, activeElements) {
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

    function renderVerticalComparisonBarChart(canvasId, section, existingChart) {
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
            type: 'bar',
            data: chartSection,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: normalized.hasData,
                        position: 'top'
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

    function setText(elementId, value) {
        var element = document.getElementById(elementId);
        if (!element || !value) {
            return;
        }

        element.textContent = value;
    }

    function renderRange(rangeName) {
        var rangeData = chartData && chartData.range_views && chartData.range_views[rangeName] ? chartData.range_views[rangeName] : null;

        setText('detourNotFoundTitle', rangeData ? rangeData.not_found_title : null);
        setText('detourTopNeededTitle', rangeData ? rangeData.top_needed_title : null);

        chartInstances.notFound = renderLineChart(
            'detour404Chart',
            rangeData ? rangeData.not_found_trend : null,
            chartInstances.notFound
        );

        chartInstances.topNeeded = renderBarChart(
            'detourTopNeededChart',
            rangeData ? rangeData.top_needed_redirects : null,
            chartInstances.topNeeded
        );

        if (hitCounterEnabled) {
            setText('detour301TrendTitle', rangeData ? rangeData.redirect_301_title : null);
            setText('detourComparisonTitle', rangeData ? rangeData.comparison_title : null);
            setText('detourTop301Title', rangeData ? rangeData.top_301_title : null);

            chartInstances.redirect301 = renderLineChart(
                'detour301Chart',
                rangeData ? rangeData.redirect_301_trend : null,
                chartInstances.redirect301
            );

            chartInstances.comparison = renderVerticalComparisonBarChart(
                'detourRedirectVs404Chart',
                rangeData ? rangeData.redirect_vs_404 : null,
                chartInstances.comparison
            );

            chartInstances.top301 = renderBarChart(
                'detourTop301Chart',
                rangeData ? rangeData.top_301 : null,
                chartInstances.top301
            );
        }
    }

    function initializeRangeToggle() {
        var toggleRoot = document.querySelector('.detour-dashboard-toggle');
        if (!toggleRoot) {
            renderRange('month');
            return;
        }

        var buttons = toggleRoot.querySelectorAll('[data-range-target]');
        var storageKey = 'detour_dashboard_view';
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
