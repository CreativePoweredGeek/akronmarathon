<?php if ($importId): ?>
    <div class="panel">
        <div class="panel-heading">
            <div class="title-bar title-bar--large">
                <span class="ico sub-arrow js-datagrab-toggle-panel"></span>
                <h3 class="title-bar__title">Running this import</h3>
            </div>
        </div>
        <div class="panel-body hidden">
            <p>The recommended method of running imports, especially large ones, is through the ExpressionEngine CLI.
                You will need to setup two cron jobs executing the following commands. You can <a href="https://docs.boldminded.com/datagrab/docs/automatic-imports/importing-with-cron">read more about this in
                    detail in the docs</a>.</p>
            <br />

            <pre>
php system/ee/eecli.php import:run --id=<?php echo $importId; ?> --producer
php system/ee/eecli.php import:run --id=<?php echo $importId; ?> --consumer
        </pre>

            <p>Smaller imports can be run through the CLI as well with the following single command or by clicking the
                import button in the DataGrab Imports list.</p>
            <br />

            <pre>
php system/ee/eecli.php import:run --id=<?php echo $importId; ?>
        </pre>

            <br />
            <p>Defining whether an import is considered small or large depends on several factors. These include the number
                of items being imported, the number of custom fields involved, the amount of memory available to PHP, and
                the request timeout settings, among others. The <a href="https://docs.boldminded.com/datagrab/docs/running-imports">docs</a> cover this in a little more detail.</p>
        </div>
    </div>
<?php endif; ?>

<?php
echo form_open($form_action);
echo form_hidden("datagrab_step", "configure_import");

$tableTemplate = [
    'table_open' => '<table class="grid-field__table datagrab-configure__table">',
];
?>

<style>
    .grid-field {
        margin-bottom: 1.5em;
    }
</style>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <span class="ico sub-arrow js-datagrab-toggle-panel"></span>
            <h3 class="title-bar__title">1. Import Settings</h3>
            <div class="form-btns">
                <a href="<?= $back_link ?>" class="button button--secondary button--small">Edit Settings</a>
            </div>
        </div>
    </div>
    <div class="panel-body hidden">
        <p>Import Type: <b><?= ucfirst($importType) ?></b></p>
        <p>Import Format: <b><?= $importFormat ?></b></p>
        <?php if ($importType === 'entry'): ?>
            <p>Channel: <b><?= $channel_title ?></b></p>
        <?php elseif ($importType === 'file'): ?>
            <p>File Directory: <b><?= $import_directory_name ?></b></p>
        <?php endif; ?>
        <?php
        foreach ($datatype_settings as $key => $value) {
            if ($key === 'delimiter') {
                if ($value === "\t") {
                    $value = 'TAB';
                }
                if ($value === " ") {
                    $value = 'SPACE';
                }
            }

            echo sprintf(
                '<p>%s: <b>%s</b></p>',
                $key,
                $value
            );
        }
        ?>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <span class="ico sub-arrow js-datagrab-toggle-panel"></span>
            <h3 class="title-bar__title">2. Check Settings</h3>
        </div>
    </div>
    <div class="panel-body hidden">
        <?php echo implode('', $checkSettings ?? []); ?>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <span class="ico sub-arrow js-datagrab-toggle-panel"></span>
            <h3 class="title-bar__title">3. <?php echo $title ?></h3>
            <div class="form-btns">
                <button class="button button--primary" type="submit" name="save" data-submit-text="Save" data-work-text="Saving...">Save</button>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <?php echo implode('', $fieldSets ?? []); ?>
    </div>
    <div class="panel-footer">
        <a href="<?php echo $back_link ?>" style="float: left;" class="button button--secondary">Back to Settings</a>
        <div class="form-btns">
            <button class="button button--primary" type="submit" name="save" data-submit-text="Save" data-work-text="Saving...">Save</button>
        </div>
    </div>
</div>

<?php echo form_close(); ?>
