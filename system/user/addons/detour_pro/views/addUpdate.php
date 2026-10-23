<div class="panel">
    <div class="form-standard">
        <?php echo form_open($action_url, array('id' => 'addUpdateForm', 'data-checkurl' => $check_url, 'data-existing' => (!empty($original_url) ? $original_url : ''))); ?>

        <?php
        if ($id) {
            echo form_input(array(
                'name' => 'id',
                'value' => $id,
                'type' => 'hidden',
                'id' => 'existing_id'
            ));

            echo form_input(array(
                'name' => 'existing_url',
                'value' => $original_url,
                'type' => 'hidden',
                'id' => 'existing_url'
            ));
        }
        ?>

        <div class="panel-heading">
            <div class="form-btns form-btns-top">
                <div class="title-bar title-bar--large">
                    <h3 class="title-bar__title"><?php echo ee()->lang->line('label_' . (!empty($id) ? 'edit' : 'add') . '_detour'); ?></h3>
                    <div class="title-bar__extra-tools">
                        <button class="button button--primary" type="submit" name="submit" value="submit"><?php echo ee()->lang->line('btn_save_detour'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-body">
            <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

            <?php if (!empty($id)): ?>
            <div class="detour-charts-container detour-charts-container--inset">
                <div class="detour-chart-wrapper detour-chart-wrapper-full">
                    <?php if (!empty($hit_counter_enabled)): ?>
                    <div class="detour-dashboard-toggle-wrap">
                        <div class="detour-dashboard-toggle" role="tablist" aria-label="<?=lang('detour_hits_toggle_aria')?>">
                            <button type="button" class="detour-dashboard-toggle__btn" data-range-target="day" role="tab" aria-selected="false"><?=lang('dashboard_toggle_day')?></button>
                            <button type="button" class="detour-dashboard-toggle__btn" data-range-target="week" role="tab" aria-selected="false"><?=lang('dashboard_toggle_week')?></button>
                            <button type="button" class="detour-dashboard-toggle__btn is-active" data-range-target="month" role="tab" aria-selected="true"><?=lang('dashboard_toggle_month')?></button>
                            <button type="button" class="detour-dashboard-toggle__btn" data-range-target="year" role="tab" aria-selected="false"><?=lang('dashboard_toggle_year')?></button>
                            <button type="button" class="detour-dashboard-toggle__btn" data-range-target="all" role="tab" aria-selected="false"><?=lang('detour_hits_toggle_all')?></button>
                        </div>
                    </div>
                    <div class="detour-chart-card">
                        <div class="detour-chart-card__header">
                            <div id="detourEditHitsTitle" class="detour-chart-card__title"><?=lang('detour_hits_last_30_days')?></div>
                        </div>
                        <div class="detour-chart-card__canvas-wrap">
                            <div class="no-results" hidden>
                                <div class="no-results__inner">
                                    <span class="no-results__text"><?=lang('dashboard_no_data')?></span>
                                </div>
                            </div>
                            <canvas id="detourEditHitsChart" class="detour-chart-card__canvas"></canvas>
                        </div>
                    </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <fieldset>
                <div class="field-instruct">
                    <label><?php echo ee()->lang->line('label_original_url'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_original_url'); ?></em>
                </div>
                <div class="field-control">
                    <?php echo form_input(array(
                        'name'  => 'original_url',
                        'id'    => 'original_url',
                        'value' => $original_url,
                        'size'  => '50',
                    )); ?>
                    <div id="original_url_check"></div>
                </div>
            </fieldset>

            <fieldset>
                <div class="field-instruct">
                    <label><?php echo ee()->lang->line('label_new_url'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_new_url'); ?></em>
                </div>
                <div class="field-control">
                    <?php echo form_input(array(
                        'name'  => 'new_url',
                        'id'    => 'new_url',
                        'value' => $new_url,
                        'size'  => '50',
                    )); ?>
                    <?php if (!$allow_trailing_slash) { ?>
                        <div class="note-fieldtype" style="margin-top: 10px;">
                            <div class="note-fieldtype__icon">
                                <i class="fa fa-hand-point-right"></i>
                            </div>
                            <div class="note-fieldtype__content">
                                <p>Note: Trailing slashes will be removed from your Detours. To disable this, turn on the "Allow Trailing Slashes" setting.</p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </fieldset>

            <fieldset>
                <div class="field-instruct">
                    <label><?php echo ee()->lang->line('label_detour_note'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_detour_note'); ?></em>
                </div>
                <div class="field-control">
                    <?php echo form_input(array(
                        'name'      => 'note',
                        'id'        => 'note',
                        'value'     => $note,
                        'size'      => '50',
                        'maxlength' => '255',
                    )); ?>
                </div>
            </fieldset>

            <fieldset>
                <div class="field-instruct">
                    <label><?php echo ee()->lang->line('label_detour_method'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_detour_method'); ?></em>
                </div>
                <div class="field-control">
                    <?php echo form_dropdown('detour_method', $detour_methods, $detour_method); ?>
                </div>
            </fieldset>

            <fieldset>
                <div class="field-instruct">
                    <label><?php echo ee()->lang->line('label_start_date'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_start_date'); ?></em>
                </div>
                <div class="field-control">
                    <?php echo form_input(array(
                        'name'  => 'start_date',
                        'id'    => 'start_date',
                        'value' => $start_date,
                        'size'  => '30',
                        'rel'   => 'date-picker',
                    )); ?>
                </div>
            </fieldset>

            <?php if ($start_date) { ?>
                <fieldset>
                    <div class="field-instruct">
                        <label><?php echo ee()->lang->line('label_clear_start_date'); ?></label>
                        <em><?php echo ee()->lang->line('subtext_clear_start_date'); ?></em>
                    </div>
                    <div class="field-control">
                        <label class="checkbox-label">
                            <?php echo form_checkbox('clear_start_date', '1'); ?>
                            <div class="checkbox-label__text">
                                <div><?php echo ee()->lang->line('label_clear_start_date'); ?></div>
                            </div>
                        </label>
                    </div>
                </fieldset>
            <?php } ?>

            <fieldset>
                <div class="field-instruct">
                    <label><?php echo ee()->lang->line('label_end_date'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_end_date'); ?></em>
                </div>
                <div class="field-control">
                    <?php echo form_input(array(
                        'name'  => 'end_date',
                        'id'    => 'end_date',
                        'value' => $end_date,
                        'size'  => '30',
                        'rel'   => 'date-picker',
                    )); ?>
                </div>
            </fieldset>

            <?php if ($end_date) { ?>
                <fieldset>
                    <div class="field-instruct">
                        <label><?php echo ee()->lang->line('label_clear_end_date'); ?></label>
                        <em><?php echo ee()->lang->line('subtext_clear_end_date'); ?></em>
                    </div>
                    <div class="field-control">
                        <label class="checkbox-label">
                            <?php echo form_checkbox('clear_end_date', '1'); ?>
                            <div class="checkbox-label__text">
                                <div><?php echo ee()->lang->line('label_clear_end_date'); ?></div>
                            </div>
                        </label>
                    </div>
                </fieldset>
            <?php } ?>
        </div>

        <div class="panel-footer">
            <div class="form-btns">
                <button class="button button--primary" type="submit" name="submit" value="submit"><?php echo ee()->lang->line('btn_save_detour'); ?></button>
            </div>
        </div>

        <?php echo form_close(); ?>
    </div>
</div>

<?php if (!empty($id) && !empty($hit_counter_enabled)): ?>
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

    var chartData = <?= isset($detour_hits_chart_data) ? $detour_hits_chart_data : 'null' ?>;
    var defaultTitle = <?=json_encode(lang('detour_hits_last_30_days'))?>;
    var chartInstance = null;

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

        var noResults = wrap.querySelector('.no-results');
        if (noResults) {
            noResults.hidden = hasData;
        }

        canvas.hidden = !hasData;
    }

    function setTitle(text) {
        var title = document.getElementById('detourEditHitsTitle');
        if (!title) {
            return;
        }

        title.textContent = text || defaultTitle;
    }

    function renderRange(rangeName) {
        var ctx = document.getElementById('detourEditHitsChart');
        if (!ctx) {
            return;
        }

        var rangeData = chartData && chartData.range_views && chartData.range_views[rangeName] ? chartData.range_views[rangeName] : null;
        var chartSection = rangeData && rangeData.chart ? rangeData.chart : null;
        var hasData = hasChartData(chartSection);

        setTitle(rangeData && rangeData.title ? rangeData.title : defaultTitle);
        setNoResultsState('detourEditHitsChart', hasData);

        if (chartInstance && typeof chartInstance.destroy === 'function') {
            chartInstance.destroy();
        }

        if (!hasData) {
            chartInstance = null;
            return;
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: chartSection,
            options: {
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
                    }
                }
            }
        });
    }

    function initializeRangeToggle() {
        var toggleRoot = document.querySelector('.detour-dashboard-toggle');
        if (!toggleRoot) {
            renderRange('month');
            return;
        }

        var buttons = toggleRoot.querySelectorAll('[data-range-target]');
        var storageKey = 'detour_edit_hits_range_<?= (int) $id ?>';
        var defaultRange = chartData && chartData.default_range ? chartData.default_range : 'month';

        function setRange(rangeName) {
            var range = rangeName;
            if (range !== 'day' && range !== 'week' && range !== 'month' && range !== 'year' && range !== 'all') {
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
            if (savedRange === 'day' || savedRange === 'week' || savedRange === 'month' || savedRange === 'year' || savedRange === 'all') {
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
<?php endif; ?>
