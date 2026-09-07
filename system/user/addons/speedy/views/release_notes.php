<div class="table-list-wrap">
    <div class="tbl-ctrls">
        <h1>Release Notes</h1>
        <?php echo $message; ?>
        <table>
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
</div>
