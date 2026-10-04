<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

function ts_fielders_choice_load_services()
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    require_once __DIR__ . '/CategoryChoiceLists.php';

    $loaded = true;
}
