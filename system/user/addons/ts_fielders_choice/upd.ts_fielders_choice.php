<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Ts_fielders_choice_upd
{
    public $version = '0.0.2b';

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function update($current = '')
    {
        if ($current === $this->version) {
            return false;
        }

        return true;
    }
}
