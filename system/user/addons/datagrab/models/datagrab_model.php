<?php

class Datagrab_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();

        ee()->load->library('logger');
        ee()->logger->deprecated('6.0.0', "Use ee('datagrab:Importer') instead of ee()->datagrab");
    }

    public function __call($method, $arguments)
    {
        ee()->logger->deprecated('6.0.0', sprintf(
            "Use ee('datagrab:Importer')->%s() instead of ee()->datagrab->%s()", $method, $method
        ));
    }
}
