<?php echo ee('CP/Alert')->get('shared-form'); ?>

<div class="panel datagrab">
    <div class="panel-heading">
        <?php
        echo form_open($form_action);
        echo form_hidden('validate', 'n');
        ?>

        <?php if (empty($imports)): ?>
            <p>No imports found.</p>
        <?php else: ?>
            <p>
                <?php echo $this->embed('ee:_shared/form/fields/dropdown', [
                    'choices' => $imports,
                    'field_name' => 'import_id',
                    'value' => '',
                    'class' => 'inline-block',
                ]); ?>

                <input type="submit" value="Create new endpoint" class="button button--primary inline" />
            </p>
        <?php endif; ?>

        <?php echo form_close(); ?>
    </div>

    <?php if ($table): ?>
        <?php echo form_open($form_action); ?>

        <div class="panel-body panel-body__table">
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
        <h5>To use endpoints:</h5>
        <ol>
            <li><p>Create an Import: Begin by creating an import using a static or remote JSON or XML file that matches the sender’s data format.
                    Run the import through the DataGrab interface and verify it works as expected.</p></li>
            <li><p>Create the Endpoint: Assign the import to a new endpoint. The sender will POST data to the Endpoint URL, including proper authentication.</p></li>
        </ol>

        <h5>Endpoints require:</h5>
        <ol>
            <li><p>A configured JSON or XML-based import.</p></li>
            <li><p>POST requests must include body content that matches the import’s format.</p></li>
            <li><p>A <a href="https://docs.boldminded.com/datagrab/docs/automatic-imports/importing-with-cron">crontab process running</a>
                    with the <code>--consumer</code> flag to process data received by the endpoint. If you have multiple endpoints with
                    different imports, each import requires its own dedicated consumer process.</p></li>
        </ol>
    </div>
</div>
