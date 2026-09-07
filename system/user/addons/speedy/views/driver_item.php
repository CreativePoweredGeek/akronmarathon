<?= ee('CP/Alert')->getAllInlines() ?>

<div class="panel">
    <div class="panel-heading">
        <div class="form-btns form-btns-top">
            <div class="title-bar title-bar--large">
                <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
                <div class="title-bar__extra-tools">
                    <a href="<?= $back_btn_href ?>" class="button button--default"><?= $back_btn ?></a>
                    <?php if ($diagnostic_url): ?>
                        <a href="<?= $diagnostic_url ?>" class="button button--action">View Diagnostics</a>
                    <?php endif ?>
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
                <h3><?= lang('speedy_driver_item_ttl') ?></h3>
                <em><?= lang('speedy_driver_item_ttl_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $ttl ?>
            </div>
        </fieldset>

        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_driver_item_created') ?></h3>
                <em><?= lang('speedy_driver_item_created_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $created_at ?>
            </div>
        </fieldset>

        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_driver_item_expires') ?></h3>
                <em><?= lang('speedy_driver_item_expires_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $expires_at ?>
                <?php if ($expires_at !== '&infin;'): ?>
                    (<?= $ttl_remaining ?> <?= lang('speedy_driver_item_ttl_remaining') ?>)
                <?php endif ?>
            </div>
        </fieldset>

        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_driver_item_size') ?></h3>
                <em><?= lang('speedy_driver_item_size_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $size ?> (<?= $size_bytes ?> <?= lang('speedy_driver_item_size_bytes') ?>)
            </div>
        </fieldset>

        <?php if (!empty($diagnostics)): ?>

        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_diagnostic_item_query_count') ?></h3>
                <em><?= lang('speedy_diagnostic_item_query_count_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $diagnostics['query_count'] ?? 'n/a' ?>
            </div>
        </fieldset>
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3><?= lang('speedy_diagnostic_item_execution_time') ?></h3>
                <em><?= lang('speedy_diagnostic_item_execution_time_desc') ?></em>
            </div>
            <div class="setting-field col w-8 last">
                <?= $diagnostics['execution_time'] ? round($diagnostics['execution_time'], 3): 'n/a' ?> seconds
            </div>
        </fieldset>

        <?php endif; ?>

        <fieldset class="col-group last">
            <div class="setting-txt col w-16">
                <h3><?= lang('speedy_driver_item_content') ?></h3>
                <em><?= lang('speedy_driver_item_content_desc') ?></em>
            </div>
            <div class="setting-field col w-16 last">
                <textarea rows="30" readonly disabled><?= htmlspecialchars($content, ENT_QUOTES) ?></textarea>
            </div>
        </fieldset>
    </div>
</div>
