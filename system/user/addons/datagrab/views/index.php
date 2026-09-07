<?php echo ee('CP/Alert')->get('shared-form'); ?>

<div class="panel datagrab">
    <div class="panel-heading">
        <?php
        echo form_open($form_action);
        echo form_hidden("datagrab_step", "index");
        ?>

        <p>
            <?php if ($isWordpressEdition): ?>
                <input type="hidden" name="type" value="wordpress" />
            <?php else: ?>
                <?php echo $this->embed('ee:_shared/form/fields/dropdown', [
                    'choices' => $types,
                    'field_name' => 'type',
                    'value' => '',
                    'class' => 'inline-block',
                ]); ?>
            <?php endif; ?>

            <input type="submit" value="Create new import" class="button button--primary" />
        </p>

        <?php echo form_close(); ?>
    </div>

    <?php if ($table): ?>
        <?php echo form_open($form_action); ?>

        <div class="panel-body panel-body__table datagrab-imports table-sortable">
            <?php $this->embed('ee:_shared/table', $table->viewData()); ?>
        </div>

        <?php echo form_close(); ?>
    <?php endif; ?>
</div>

<div class="app-notice app-notice--inline app-notice---attention">
    <div class="app-notice__tag">
        <span class="app-notice__icon"></span>
    </div>
    <div class="app-notice__content">
        <p>
            <strong>Saved imports</strong> can be run from outside the
            Control Panel <a href="https://docs.boldminded.com/datagrab/docs/automatic-imports/importing-with-cron">using the CLI Commands</a> or with the <i>Import URL</i>.
        </p>
    </div>
</div>
