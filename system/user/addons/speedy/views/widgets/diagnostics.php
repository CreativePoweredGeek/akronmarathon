<div class="field-instruct"><em><?= lang('speedy_diagnostics_desc') ?></em></div>

<ul class="simple-list">
    <?php foreach($diagnostics as $row): ?>
        <li>
            <a class="normal-link" href="<?= ee('CP/URL')->make('addons/settings/speedy/diagnostic_item', [
                'key' => $row->key,
            ]);?>">
                <?= $row->key; ?>
                <span class="meta-info float-right ml-s">
                    <?= round($row->execution_time, 3) ?> seconds
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
