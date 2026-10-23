<?php
// URI sniffer
$uri = array(
    'ee'  => 'ExpressionEngine: ee()->uri->uri_string',
    'php' => 'PHP: $_SERVER[\'REQUEST_URI\']',
);

// Default redirect method
$redirects = array(
    '301' => '301 (Permanent)',
    '302' => '302 (Temporary)',
);
?>
<div class="panel">
    <div class="form-standard">
        <?php echo form_open($action_url, array('class' => 'settings')); ?>

        <div class="panel-heading">
            <div class="form-btns form-btns-top">
                <div class="title-bar title-bar--large">
                    <h3 class="title-bar__title">Settings</h3>
                    <div class="title-bar__extra-tools">
                        <button class="button button--primary" type="submit" name="submit" value="submit"><?php echo ee()->lang->line('btn_save_settings'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-body">
            <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

            <fieldset class="col-group">
                <div class="field-instruct col w-8">
                    <label><?php echo ee()->lang->line('label_setting_url_detect'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_setting_url_detect'); ?></em>
                </div>
                <div class="field-control col w-8 last">
                    <?php echo form_dropdown('url_detect', $uri, $settings->url_detect); ?>
                </div>
            </fieldset>

            <fieldset class="col-group">
                <div class="field-instruct col w-8">
                    <label><?php echo ee()->lang->line('label_setting_default_method'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_setting_default_method'); ?></em>
                </div>
                <div class="field-control col w-8 last">
                    <?php echo form_dropdown('default_method', $redirects, $settings->default_method); ?>
                </div>
            </fieldset>

            <fieldset class="col-group">
                <div class="field-instruct col w-8">
                    <label><?php echo ee()->lang->line('label_setting_hit_counter'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_setting_hit_counter'); ?></em>
                </div>
                <div class="field-control col w-8 last">
                    <label class="checkbox-label">
                        <?php echo form_checkbox('hit_counter', 'y', $settings->hit_counter); ?>
                        <div class="checkbox-label__text">
                            <div><?php echo ee()->lang->line('label_setting_hit_counter'); ?></div>
                        </div>
                    </label>
                </div>
            </fieldset>

            <fieldset class="col-group">
                <div class="field-instruct col w-8">
                    <label><?php echo ee()->lang->line('label_setting_allow_trailing_slash'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_setting_allow_trailing_slash'); ?></em>
                </div>
                <div class="field-control col w-8 last">
                    <label class="checkbox-label">
                        <?php echo form_checkbox('allow_trailing_slash', '1', $settings->allow_trailing_slash); ?>
                        <div class="checkbox-label__text">
                            <div><?php echo ee()->lang->line('label_setting_allow_trailing_slash'); ?></div>
                        </div>
                    </label>
                    <div class="note-fieldtype" style="margin-top: 10px;">
                        <div class="note-fieldtype__icon">
                            <i class="fa fa-hand-point-right"></i>
                        </div>
                        <div class="note-fieldtype__content">
                            <p><?php echo ee()->lang->line('notice_allow_trailing_slash'); ?></p>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="col-group">
                <div class="field-instruct col w-8">
                    <label><?php echo ee()->lang->line('label_setting_allow_qs'); ?></label>
                    <em><?php echo ee()->lang->line('subtext_setting_allow_qs'); ?></em>
                </div>
                <div class="field-control col w-8 last">
                    <label class="checkbox-label">
                        <?php echo form_checkbox('allow_qs', '1', $settings->allow_qs); ?>
                        <div class="checkbox-label__text">
                            <div><?php echo ee()->lang->line('label_setting_allow_qs'); ?></div>
                        </div>
                    </label>
                </div>
            </fieldset>
        </div>

        <div class="panel-footer">
            <div class="form-btns">
                <button class="button button--primary" type="submit" name="submit" value="submit"><?php echo ee()->lang->line('btn_save_settings'); ?></button>
            </div>
        </div>

        <?php echo form_close(); ?>
    </div>
</div>
