<div class="box addon-license panel">
    <?php echo form_open($action_url, array('class' => 'settings')); ?>

    <div class="panel-heading">
        <div class="form-btns form-btns-top">
            <div class="title-bar title-bar--large">
                <h3 class="title-bar__title">License</h3>

                <div class="title-bar__extra-tools">
                     <input class="btn submit" type="submit" value="Save License Key" />
                </div>
            </div>
         </div>
    </div>

    <div class="panel-body">
        <div class="app-notice-wrap"><?php echo ee('CP/Alert')->getAllInlines(); ?></div>

        <fieldset class="col-group required">
            <div class="field-instruct col w-8">
                <label for="license_key">License Key</label>
                <em>You can retrieve your license key from <b><a target="_blank" href="https://eeharbor.com/members">your Account page on EEHarbor.com</a></b>.</em>
            </div>
            <div class="field-control col w-8 last">
                <?php echo form_input('license_key', strpos((string) $license_key, 'ignore-site-') !== false ? '' : $license_key); ?>
            </div>
        </fieldset>

        <fieldset class="col-group required">
            <div class="field-instruct col w-8">
                <label for="ignore_site">Ignore This Site</label>
                <em>If you have Multi-Site Manager (MSM) enabled, you can choose which sites to use this add-on on.</em>
            </div>
            <div class="field-control col w-8 last">
                <?php echo form_checkbox('ignore_site', '1', $ignore_site); ?>
            </div>
        </fieldset>

        <fieldset class="col-group license-status-group">
            <div class="field-instruct col w-8">
                <label>License Status</label>
            </div>
            <div class="field-control col w-8 last">
                <div class="license_status_badge"></div>

                <div class="license_status_i" style="display:none;">
                    <div class="license_status_warning">
                        This add-on will cease to function if put on a production website!
                    </div>
                    <div class="license_status">
                        <h4>Invalid</h4>
                        <p>We were unable to find a match for your License Key in our system. You can use the add-on while performing
                        <strong>local development</strong> but you <strong>must</strong> enter a valid license before making your
                        site live. To purchase a license or look up an existing license, please visit
                        <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
                <div class="license_status_u" style="display:none;">
                    <div class="license_status_warning">
                        This add-on will cease to function if put on a production website!
                    </div>
                    <div class="license_status">
                        <h4>Unlicensed</h4>
                        <p>You have not entered a license key. You can use the add-on while performing <strong>local development</strong>
                        but you <strong>must</strong> enter a valid license before making your site live. To purchase a license or look
                        up an existing license, please visit
                        <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
                <div class="license_status license_status_e" style="display:none;">
                    <h4>Expired</h4>
                    <p>Your license is valid but has expired. You can continue to use you add-on while it is expired but if you wish to update
                    to the latest version, you will need to purchase an upgrade. To upgrade, please login to your account on
                    <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>, find your license and click the "Renew" button.</p>
                </div>
                <div class="license_status license_status_d" style="display:none;">
                    <h4>Duplicate</h4>
                    <p>Your license key is currently registered on another website. For more information, please login to your account on
                    <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                </div>
                <div class="license_status license_status_vp" style="display:none;">
                    <h4>Valid through ExpressionEngine Pro</h4>
                    <p>Your license is valid through your ExpressionEngine Pro subscription.</p>
                    <p>You can manage your ExpressionEngine Pro subscription on <a target="_blank" href="https://expressionengine.com">ExpressionEngine.com</a>.</p>
                </div>
                <div class="license_status_w" style="display:none;">
                    <div class="license_status_warning">
                        This add-on will cease to function if put on a production website!
                    </div>
                    <div class="license_status">
                        <h4>License Mismatch</h4>
                        <p>The license key you entered is registered to a different add-on. For more information, please login to your account
                        on <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
                <div class="license_status_p" style="display:none;">
                    <div class="license_status_warning">
                        This add-on will cease to function if put on a production website!
                    </div>
                    <div class="license_status">
                        <h4>License Missing Production Domain</h4>
                        <p>You must enter your production domain in your Account page on EEHarbor.com.</p>
                        <p>This would be the final domain the add-on is going to run on (i.e. http://www.clientsite.com).</p>
                        <p>For more information, please login to your account on <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
                <div class="license_status_m" style="display:none;">
                    <div class="license_status">
                        <h4>Maintenance Mode</h4>
                        <p>The licensing server is undergoing maintenance. Your add-on will not be affected by this.
                        If you need assistance, please contact us on <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
                <div class="license_status_disabled" style="display:none;">
                    <div class="license_status">
                        <h4>Add-on Disabled</h4>
                        <p>This add-on has been disabled until a valid license is entered.</p>
                        <p>The unlicensed use of this add-on on production websites is a violation of the Add-on License Agreement.</p>
                        <p>
                            <b>To renable this add-on:</b><br />
                            <ol>
                                <li>Enter a valid license</li>
                                <li>Enter your production domain for this license on your account page on EEHarbor.com</li>
                            </ol>
                        </p>

                        <p>If you need assistance, please contact us on <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
                <div class="license_status_ignored" style="display:none;">
                    <div class="license_status">
                        <h4>Site Ignored (add-on disabled)</h4>
                        <p>This add-on has been disabled for this site.</p>
                        <p>The unlicensed use of this add-on on production websites is a violation of the Add-on License Agreement.</p>
                        <p>
                            <b>To renable this add-on:</b><br />
                            <ol>
                                <li>Uncheck "Ignore This Site"</li>
                                <li>Enter a valid license</li>
                                <li>Enter your production domain for this license on your account page on EEHarbor.com</li>
                            </ol>
                        </p>

                        <p>If you need assistance, please contact us on <a target="_blank" href="https://eeharbor.com/">EEHarbor.com</a>.</p>
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset class="col-group last">
            <div class="field-instruct col w-8 ">
                <label>License Agreement</label>
            </div>
            <div class="field-control">
                <em>By using this software, you agree to the <b><a target="_blank" href="https://eeharbor.com/license">Add-on License Agreement</a></b>.</em>
            </div>
        </fieldset>
    </div>

    <div class="panel-footer">
        <div class="form-btns">
            <input class="btn submit" type="submit" value="Save License Key" />
        </div>
    </div>

    <?php echo form_close(); ?>
</div>
