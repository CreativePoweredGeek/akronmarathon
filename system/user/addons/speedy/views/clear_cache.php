<div class="box">
    <h1><?= $cp_page_title ?></h1>
    <?= form_open('', 'class="settings"') ?>
        <?= ee('CP/Alert')->get('shared-form') ?>

        <p><?= sprintf(lang('speedy_confirm_clear_cache'), lang('speedy_driver_' . $driver)) ?></p>

        <fieldset class="form-ctrls">
            <button type="submit" name="confirm" class="btn" data-submit-text="<?= lang('speedy_confirm_clear_button') ?>" data-work-text="<?= lang('speedy_clearing_button') ?>">
                <?= lang('speedy_confirm_clear_button') ?>
            </button>
        </fieldset>
    <?= form_close() ?>
</div>
