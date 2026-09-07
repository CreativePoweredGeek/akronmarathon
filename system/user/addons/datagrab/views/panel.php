<div class="panel datagrab-panel">
    <?php if ($heading ?? ''): ?>
    <div class="panel-heading">
        <span class="ico sub-arrow js-datagrab-toggle-panel"></span>
        <?= $heading ?>
    </div>
    <?php endif; ?>
    <div class="panel-body datagrab-configure__section hidden">
        <?= $html ?? '' ?>
    </div>
</div>
