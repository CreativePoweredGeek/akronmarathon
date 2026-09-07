<?php

namespace BoldMinded\Speedy\Model;

use ExpressionEngine\Service\Model\Model;

class DriverConfiguration extends Model
{
    protected static $_primary_key = 'id';
    protected static $_table_name = 'speedy_driver_configuration';

    protected static $_typed_columns = [
        'driver'   => 'string',
        'settings' => 'json',
    ];

    protected $id;
    protected $site_id;
    protected $driver;
    protected $settings;
}
