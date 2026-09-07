<?= ee('CP/Alert')->getAllInlines() ?>

<div class="panel">
    <div class="panel-heading">
        <div class="form-btns form-btns-top">
            <div class="title-bar title-bar--large">
                <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
                <div class="title-bar__extra-tools">
                    <?= form_open('', 'style="display: flex; margin-right: 2em;"') ?>
                        <?php if ($search_path !== ''): ?>
                            <input type="hidden" name="path" value="<?= htmlspecialchars($search_path, ENT_QUOTES) ?>">
                        <?php endif; ?>
                        <input
                            type="search"
                            name="keyword"
                            value="<?= htmlspecialchars($search_keyword, ENT_QUOTES) ?>"
                            placeholder="<?= lang('speedy_driver_items_search') ?>"
                            minlength="3"
                            style="min-width: 320px;"
                        />
                        <button type="submit" class="button button--default"><?= lang('speedy_driver_items_search_submit') ?></button>
                        <?php if ($search_keyword !== ''): ?>
                            <a href="<?= $clear_search_url ?>" class="button button--default"><?= lang('speedy_driver_items_search_clear') ?></a>
                        <?php endif; ?>
                    <?= form_close() ?>

                    <a href="<?= $flush_driver['href'] ?>" class="btn"><?= $flush_driver['content'] ?></a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($search_alert)): ?>
        <div class="panel-heading">
            <p style="margin: 0; color: var(--ee-text-subtle, #8a8a8a);"><?= $search_alert ?></p>
        </div>
    <?php endif; ?>

    <div class="table-responsive table-responsive--collapsible">
        <?php $this->embed('ee:_shared/table', $driver_items); ?>
        <?php echo $pagination ?>
    </div>
</div>
