<?= ee('CP/Alert')->getAllInlines() ?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3>Channel Rules</h3>
        </div>
        <p class="text-secondary">Channel rules only apply when an entry in the channel is saved.</p>
    </div>
    <div class="table-responsive table-responsive--collapsible">
        <?php $this->embed('ee:_shared/table', $channelTable); ?>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar">
            <h3>Category Group Rules</h3>
        </div>
        <p class="text-secondary">Category group rules only apply when a category in the group is saved.</p>
    </div>
    <div class="table-responsive table-responsive--collapsible">
        <?php $this->embed('ee:_shared/table', $categoryGroupTable); ?>
    </div>
</div>
