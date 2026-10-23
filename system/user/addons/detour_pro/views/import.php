<div class="panel">
    <div class="form-standard">
        <?php if ($wizard_step === 'upload'): ?>
            <?php echo form_open($action_url, array('enctype' => 'multipart/form-data')); ?>

            <div class="panel-heading">
                <div class="form-btns form-btns-top">
                    <div class="title-bar title-bar--large">
                        <h3 class="title-bar__title"><?=lang('label_import_redirects')?></h3>
                        <div class="title-bar__extra-tools">
                            <button class="button button--primary" type="submit" name="submit_upload" value="submit_upload"><?=lang('import_btn_continue_mapping')?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

                <div class="note-fieldtype" style="margin-bottom: 16px;">
                    <div class="note-fieldtype__icon">
                        <i class="fa fa-info-circle"></i>
                    </div>
                    <div class="note-fieldtype__content">
                        <p><?=lang('import_header_required')?></p>
                    </div>
                </div>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=lang('label_import_csv_file')?></label>
                        <em><?=lang('subtext_import_csv_file')?></em>
                    </div>
                    <div class="field-control col w-8 last">
                        <input type="file" name="import_file" accept=".csv,text/csv" required>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=lang('label_import_delimiter')?></label>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('delimiter', $delimiter_options, $selected_delimiter); ?>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=lang('label_import_method')?></label>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('fallback_method', $detour_methods, $selected_method); ?>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=lang('label_import_skip_existing')?></label>
                        <em><?=lang('subtext_import_skip_existing')?></em>
                    </div>
                    <div class="field-control col w-8 last">
                        <label class="checkbox-label">
                            <?php echo form_checkbox('skip_existing', '1', $skip_existing); ?>
                            <div class="checkbox-label__text">
                                <div><?=lang('label_import_skip_existing')?></div>
                            </div>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="panel-footer">
                <div class="form-btns">
                    <button class="button button--primary" type="submit" name="submit_upload" value="submit_upload"><?=lang('import_btn_continue_mapping')?></button>
                </div>
            </div>

            <?php echo form_close(); ?>
        <?php endif; ?>

        <?php if ($wizard_step === 'map'): ?>
            <?php echo form_open($action_url); ?>
            <?php echo form_hidden('token', $token); ?>

            <div class="panel-heading">
                <div class="form-btns form-btns-top">
                    <div class="title-bar title-bar--large">
                        <h3 class="title-bar__title"><?=lang('import_map_title')?></h3>
                        <div class="title-bar__extra-tools">
                            <button class="button button--primary" type="submit" name="submit_mapping" value="submit_mapping"><?=lang('import_btn_preview')?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body" data-import-mapping="1">
                <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

                <div class="note-fieldtype" style="margin-bottom: 16px;">
                    <div class="note-fieldtype__icon">
                        <i class="fa fa-info-circle"></i>
                    </div>
                    <div class="note-fieldtype__content">
                        <p><?=lang('import_map_subtext')?></p>
                    </div>
                </div>

                <?php if (!empty($sample_rows)): ?>
                    <div class="tbl-wrap" style="margin-bottom: 20px;">
                        <table class="mainTable">
                            <thead>
                                <tr>
                                    <?php foreach ($headers as $header_index => $header_label): ?>
                                        <th>
                                            <?php
                                            $display_header = trim((string) $header_label);
                                            if ($display_header === '') {
                                                $display_header = sprintf(lang('import_column_label'), ((int) $header_index + 1));
                                            }
                                            echo htmlspecialchars($display_header, ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sample_rows as $sample_row): ?>
                                    <tr>
                                        <?php foreach ($headers as $header_index => $header_label): ?>
                                            <td><?=htmlspecialchars(isset($sample_row[(string) $header_index]) ? $sample_row[(string) $header_index] : '', ENT_QUOTES, 'UTF-8')?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=$field_labels['original_url']?> *</label>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('mapping[original_url]', $header_options, isset($mapping['original_url']) ? $mapping['original_url'] : '', 'data-target-field="original_url"'); ?>
                        <div class="meta-info"><small><?=lang('import_sample_value')?>: <code data-sample-for="original_url"></code></small></div>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=$field_labels['new_url']?> *</label>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('mapping[new_url]', $header_options, isset($mapping['new_url']) ? $mapping['new_url'] : '', 'data-target-field="new_url"'); ?>
                        <div class="meta-info"><small><?=lang('import_sample_value')?>: <code data-sample-for="new_url"></code></small></div>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=$field_labels['detour_method']?></label>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('selected_method', $detour_methods, $selected_method); ?>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=$field_labels['start_date']?></label>
                        <em><?=lang('import_iso_date_hint')?></em>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('mapping[start_date]', $optional_header_options, isset($mapping['start_date']) ? $mapping['start_date'] : '', 'data-target-field="start_date"'); ?>
                        <div class="meta-info"><small><?=lang('import_sample_value')?>: <code data-sample-for="start_date"></code></small></div>
                    </div>
                </fieldset>

                <fieldset class="col-group">
                    <div class="field-instruct col w-8">
                        <label><?=$field_labels['end_date']?></label>
                        <em><?=lang('import_iso_date_hint')?></em>
                    </div>
                    <div class="field-control col w-8 last">
                        <?php echo form_dropdown('mapping[end_date]', $optional_header_options, isset($mapping['end_date']) ? $mapping['end_date'] : '', 'data-target-field="end_date"'); ?>
                        <div class="meta-info"><small><?=lang('import_sample_value')?>: <code data-sample-for="end_date"></code></small></div>
                    </div>
                </fieldset>
            </div>

            <div class="panel-footer">
                <div class="form-btns">
                    <a href="<?=$back_url?>" class="button button--default"><?=lang('import_btn_back_upload')?></a>
                    <button class="button button--primary" type="submit" name="submit_mapping" value="submit_mapping"><?=lang('import_btn_preview')?></button>
                </div>
            </div>

            <?php echo form_close(); ?>

            <script>
                window.DetourImportWizard = {
                    sampleRows: <?=!empty($sample_rows_json) ? $sample_rows_json : '[]';?>
                };
            </script>
        <?php endif; ?>

        <?php if ($wizard_step === 'preview'): ?>
            <?php echo form_open($action_url); ?>
            <?php echo form_hidden('token', $token); ?>

            <div class="panel-heading">
                <div class="form-btns form-btns-top">
                    <div class="title-bar title-bar--large">
                        <h3 class="title-bar__title"><?=lang('import_preview_title')?></h3>
                        <div class="title-bar__extra-tools">
                            <button class="button button--primary" type="submit" name="submit_execute" value="submit_execute"><?=lang('btn_import_redirects')?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

                <div class="tbl-wrap" style="margin-bottom: 20px;">
                    <table class="mainTable">
                        <thead>
                            <tr>
                                <th><?=lang('import_preview_stat')?></th>
                                <th><?=lang('import_preview_value')?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><?=lang('import_preview_would_insert')?></td><td><?=isset($counters['would_insert']) ? (int) $counters['would_insert'] : 0?></td></tr>
                            <tr><td><?=lang('import_preview_would_update')?></td><td><?=isset($counters['would_update']) ? (int) $counters['would_update'] : 0?></td></tr>
                            <tr><td><?=lang('import_preview_would_skip_existing')?></td><td><?=isset($counters['would_skip_existing']) ? (int) $counters['would_skip_existing'] : 0?></td></tr>
                            <tr><td><?=lang('import_preview_would_skip_invalid')?></td><td><?=isset($counters['would_skip_invalid']) ? (int) $counters['would_skip_invalid'] : 0?></td></tr>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($preview_rows)): ?>
                    <div class="tbl-wrap" style="margin-bottom: 20px;">
                        <table class="mainTable">
                            <thead>
                                <tr>
                                    <th><?=lang('import_preview_line')?></th>
                                    <th><?=lang('import_preview_action')?></th>
                                    <th><?=$field_labels['original_url']?></th>
                                    <th><?=$field_labels['new_url']?></th>
                                    <th><?=$field_labels['detour_method']?></th>
                                    <th><?=$field_labels['start_date']?></th>
                                    <th><?=$field_labels['end_date']?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview_rows as $preview_row): ?>
                                    <tr>
                                        <td><?=isset($preview_row['line']) ? (int) $preview_row['line'] : 0?></td>
                                        <td><?=htmlspecialchars(lang('import_action_' . $preview_row['action']), ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars((string) $preview_row['original_url'], ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars((string) $preview_row['new_url'], ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars((string) $preview_row['detour_method'], ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars((string) $preview_row['start_date'], ENT_QUOTES, 'UTF-8')?></td>
                                        <td><?=htmlspecialchars((string) $preview_row['end_date'], ENT_QUOTES, 'UTF-8')?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($invalid_rows)): ?>
                    <div class="note-fieldtype">
                        <div class="note-fieldtype__icon">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <div class="note-fieldtype__content">
                            <p><strong><?=lang('import_preview_invalid_rows_title')?></strong></p>
                            <ul>
                                <?php foreach ($invalid_rows as $invalid_row): ?>
                                    <li><?=htmlspecialchars(sprintf(lang('import_invalid_row_line_reason'), $invalid_row['line'], $invalid_row['reason']), ENT_QUOTES, 'UTF-8')?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-footer">
                <div class="form-btns">
                    <a href="<?=$back_url?>" class="button button--default"><?=lang('import_btn_back_mapping')?></a>
                    <button class="button button--primary" type="submit" name="submit_execute" value="submit_execute"><?=lang('btn_import_redirects')?></button>
                </div>
            </div>

            <?php echo form_close(); ?>
        <?php endif; ?>
    </div>
</div>
