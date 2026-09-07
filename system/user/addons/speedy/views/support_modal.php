<div class="modal-wrap <?= $name ?> hidden">
    <div class="modal">
        <div class="col-group">
            <div class="col w-16">
                <a class="m-close" href="#"></a>
                <div class="box">
                    <h1><?= sprintf(lang('speedy_driver_status'), lang('speedy_driver_' . $name)) ?></h1>
                    <div class="settings">

                        <?php if ($supported): ?>
                            <?php
                            echo ee('CP/Alert')->makeInline()
                                ->asSuccess()
                                ->withTitle(lang('speedy_driver_status_available_desc'))
                                ->cannotClose()
                                ->render();
                            ?>
                        <?php elseif ($configuration): ?>
                            <?php
                            echo ee('CP/Alert')->makeInline()
                                ->asWarning()
                                ->withTitle(lang('speedy_driver_status_unconfigured_desc'))
                                ->cannotClose()
                                ->render();
                            ?>
                        <?php else: ?>
                            <?php
                            echo ee('CP/Alert')->makeInline()
                                ->asIssue()
                                ->withTitle(lang('speedy_driver_status_unsupported_desc'))
                                ->cannotClose()
                                ->render();
                            ?>
                        <?php endif ?>

                        <div class="txt-wrap">
                            <?php if (!empty($crosslist)): ?>
                                <ul class="crosslist">
                                    <?php $end = end($crosslist) ?>
                                    <?php foreach ($crosslist as $item): ?>
                                        <li<?= ($item === $end && empty($checklist)) ? ' class="last"' : '' ?>>
                                            <?php echo $item ?>
                                        </li>
                                    <?php endforeach ?>
                                </ul>
                            <?php endif ?>
                            <?php if (!empty($checklist)): ?>
                                <ul class="checklist">
                                    <?php $end = end($checklist) ?>
                                    <?php foreach ($checklist as $item): ?>
                                        <li<?= ($item === $end) ? ' class="last"' : '' ?>>
                                            <?php echo $item ?>
                                        </li>
                                    <?php endforeach ?>
                                </ul>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
