<div class="panel">
    <div class="form-standard">
        <div class="panel-heading">
            <div class="form-btns form-btns-top">
                <div class="title-bar title-bar--large">
                    <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
                    <div class="title-bar__extra-tools">
                        <a href="<?= $back_btn_href ?>" class="button button--default"><?= $back_btn ?></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            <div class="settings">
                <?php foreach($stats as $server): ?>
                    <?php if (!empty($server['label'])): ?>
                        <h2><?= $server['label'] ?></h2>
                    <?php endif ?>
                    <?php $end = end($server['stats']) ?>
                    <?php foreach($server['stats'] as $stat): ?>
                        <fieldset class="col-group<?= $stat === $end ? ' last' : '' ?>">
                            <div class="setting-txt col w-8">
                                <h3><?= $stat['title'] ?></h3>
                                <em><?= $stat['description'] ?></em>
                            </div>
                            <div class="setting-field col w-8 last">
                                <?= $stat['content'] ?>
                            </div>
                        </fieldset>
                    <?php endforeach ?>
                <?php endforeach ?>
            </div>
        </div>
        <div class="panel-footer">
            <div class="field-instruct"><em><?= lang('speedy_hits_and_misses') ?></em></div>
        </div>
    </div>
</div>
