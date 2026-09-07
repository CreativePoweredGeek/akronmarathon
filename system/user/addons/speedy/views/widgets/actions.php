<div class="field-instruct"><em><?= lang('speedy_flush_all_desc') ?></em></div>
<?php echo form_open($clearAllUrl, '') ?>
<p style="margin-top: 1em"><input type="submit" value="<?= lang('speedy_flush_all_btn') ?>" class="button button--secondary" data-work-text="<?= lang('speedy_flush_all_btn_working') ?>"></p>
<?php echo form_close() ?>
