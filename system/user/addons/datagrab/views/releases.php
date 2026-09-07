<?php if (isset($releases[0]['isNew']) && $releases[0]['isNew']): ?>
    <?php echo $message; ?>
<?php endif; ?>

<div class="panel">
    <div class="panel-heading">
        <h1>Release Notes</h1>
    </div>
    <table class="table-responsive table-responsive--collapsible" style="border: none; background: none;">
        <thead>
        <tr>
            <th>Release Date</th>
            <th>Release Notes</th>
        </tr>
        </thead>
        <tbody class="publisher-releases">
        <?php foreach ($releases as $release): ?>
            <tr>
                <td>
                    <p>
                        <?php if ($release['currentVersion'] == $release['version']): ?>
                            <span class="st-info">Current - <?php echo $release['version'] ?></span>
                        <?php else: ?>
                            <span class="st-draft"><?php echo $release['version'] ?></span>
                        <?php endif; ?>
                        <?php if ($release['isNew']): ?>
                            <span class="st-enable">Update Available</span>
                        <?php endif; ?>
                    </p>
                    <p><?php echo $release['date'] ?></p>
                </td>
                <td><?php echo $release['notes'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
