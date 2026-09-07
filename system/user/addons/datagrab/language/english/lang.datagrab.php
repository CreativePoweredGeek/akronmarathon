<?php

$lang = [

    'datagrab_module_name' => DATAGRAB_NAME,
    'datagrab_module_description' => 'Easily import data into ExpressionEngine channel entries',

    'datagrab_license' => 'License',
    'datagrab_license_name' => 'License Key',
    'datagrab_license_desc' => 'Enter your license key from boldminded.com, or the expressionengine.com store. If you purchased from expressionengine.com you need to <a href="https://boldminded.com/claim">claim your license</a>.',

    'datagrab_filename_instructions' => 'Can be a file on the local file system or from a remote website site url. 
        You can also use <code>{base_url}</code> or <code>{base_path}</code> if your file is located at the root of 
        your ExpressionEngine installation. For example: <code>{base_url}/my-files/some-file.xml</code>',

    'datagrab_configuration_error' => 'Configuration Error',
    'datagrab_troubleshooting' => 'Please see our <a href="https://docs.boldminded.com/datagrab/docs/troubleshooting">troubleshooting guide</a>.',
    'datagrab_troubleshooting_import_type' => 'DataGrab was unable to determine your import type. This could possibly be caused by missing or corrupt import settings. Check the DataGrab-import.log file for details, or try re-creating your import from scratch.',
    'datagrab_no_fields_found' => 'DataGrab was able to read your %s file, but could not find fields to import. Double check your configuration and refer to the <a href="https://docs.boldminded.com/datagrab/docs/troubleshooting">troubleshooting guide</a>.',
    'datagrab_import_type_not_found' => 'DataGrab was unable to load settings and determine an import type. This is likely due to corrupted settings, or PHP\'s Session is not writable.',

    'datagrab_configuration_warning' => 'Possible issues detected with your import file',
    'datagrab_configuration_warning_desc' => 'The following possible issues were detected with your import file. While it <i>may</i> still import successfully, it is recommended that you resolve these issues before proceeding.',

    'dg_ep_auth_name' => 'Parameter Name',
    'dg_ep_auth_value' => 'Parameter Value',

    'dg_ep_import' => 'Import',
    'dg_ep_import_desc' => 'Which import configuration will be used when this endpoint is called?',

    'dg_ep_auth_type' => 'Authentication Type',
    'dg_ep_auth_type_desc' => 'How will the authentication parameters below be sent?',

    'dg_ep_auth' => 'Authentication Parameters',
    'dg_ep_auth_description' => 'Enter a set of key/value pairs from the sender to be used to authenticate the incoming request.',

    'step_settings' => '1. Import Settings',
    'step_check_settings' => '2. Check Settings',
    'step_configure_import' => '3. Configure Import',
    'step_save' => '4. Save Import',

    'datagrab_no_endpoints' => 'No Endpoints found',
    'datagrab_add_endpoint' => 'Add Endpoint',

    'datagrab_requirement_pcntl' => 'The <a href="https://www.php.net/manual/en/book.pcntl.php">pcntl extension</a> is not instaled on your system. You will need to install this before using DataGrab.',

    'datagrab_no_results' => 'No imports configured',

    '' => '',

];
