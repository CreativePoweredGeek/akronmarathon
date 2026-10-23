<div class="panel">
    <div class="form-standard">
        <?php echo form_open($action_url); ?>
        
        <div class="panel-heading">
            <div class="form-btns form-btns-top">
                <div class="title-bar title-bar--large">
                    <h3 class="title-bar__title">Purge Hit Counter</h3>
                    <div class="title-bar__extra-tools">
                        <button class="button button--primary button--danger" type="submit" name="submit" value="submit"><?php echo ee()->lang->line('btn_purge_detours'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel-body">
            <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>
            
            <?php echo ee('CP/Alert')
                ->makeInline()
                ->asWarning()
                ->addToBody('All Detour Pro Hit Counter data will be deleted!')
                ->render(); ?>
            
            <div class="txt-wrap">
                <h3>You currently have <?php echo number_format($total_detour_hits); ?> rows in your Detour Hits table.</h3>
                
                <p>Detour Pro tracks hits on a granular level by recording the Detour and the date of execution. This can create many rows in the database. You may purge the hit data by clicking the button below. You may also disable the hit counter under the settings. As of Detour Pro 1.5, the hit counter is turned off by default.</p>
            </div>
        </div>
        
        <div class="panel-footer">
            <div class="form-btns">
                <button class="button button--primary button--danger" type="submit" name="submit" value="submit"><?php echo ee()->lang->line('btn_purge_detours'); ?></button>
            </div>
        </div>
        
        <?php echo form_close(); ?>
    </div>
</div>
