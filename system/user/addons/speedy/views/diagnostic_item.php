<?= ee('CP/Alert')->getAllInlines() ?>

<div class="panel">
    <div class="panel-heading">
        <div class="form-btns form-btns-top">
            <div class="title-bar title-bar--large">
                <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
                <div class="title-bar__extra-tools">
                    <a href="<?= $back_btn_href ?>" class="button button--default"><?= $back_btn ?></a>
                    <?php if ($item_url): ?>
                        <a href="<?= $item_url ?>" class="button button--action"><?= lang('speedy_driver_item') ?></a>
                    <?php endif; ?>
                    <a href="<?= $delete_url ?>" class="button button--primary" data-work-text="Deleting...">Delete</a>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_driver_item_key') ?></h3>
                <em><?= lang('speedy_driver_item_key_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $key ?>
            </div>
        </fieldset>
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_diagnostic_item_driver') ?></h3>
                <em><?= lang('speedy_diagnostic_item_driver_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $driver ?>
            </div>
        </fieldset>
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_diagnostic_item_query_count') ?></h3>
                <em><?= lang('speedy_diagnostic_item_query_count_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $query_count ?? 'n/a' ?>
            </div>
        </fieldset>
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_diagnostic_item_execution_time') ?></h3>
                <em><?= lang('speedy_diagnostic_item_execution_time_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $execution_time ? round($execution_time, 3) : 'n/a' ?> seconds
            </div>
        </fieldset>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3>Queries</h3>
        </div>
    </div>
    <?php if ($should_save_queries): ?>
        <div class="table-responsive table-responsive--collapsible">
            <?php $this->embed('ee:_shared/table', $queries); ?>
        </div>
    <?php else: ?>
        <div class="panel-body">
            <p>Query saving is disabled. To see a list of queries performed when caching this item add the following
            to your config.php file:</p>
            <p><code>$config['speedy_diagnostics_save_queries'] = 'yes';</code></p>
        </div>
    <?php endif; ?>
</div>
