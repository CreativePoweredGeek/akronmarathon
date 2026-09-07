<?= ee('CP/Alert')->getAllInlines() ?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
            <div class="title-bar__extra-tools">
                <a href="<?= $delete_all_url ?>" class="btn">Clear All</a>
            </div>
        </div>
    </div>
    <!-- Fix bug: https://github.com/ExpressionEngine/ExpressionEngine/issues/3999 -->
    <div class="table-responsive table-responsive--collapsible" style="overflow: visible">
        <?php $this->embed('ee:_shared/table', $diagnosticsData); ?>
        <?php echo $pagination ?>
    </div>
</div>
