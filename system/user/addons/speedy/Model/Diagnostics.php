<?php

namespace BoldMinded\Speedy\Model;

use ExpressionEngine\Service\Model\Model;

class Diagnostics extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'speedy_diagnostics';

    /** @var array */
    protected static $_table_columns = [
        'id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        'site_id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        'key' => ['type' => 'varchar', 'constraint' => 250, 'null' => true],
        'driver' => ['type' => 'char', 'constraint' => 20, 'null' => true],
        'query_count'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true],
        'queries' => ['type' => 'longtext', 'null' => true],
        'execution_time'  => ['type' => 'float', 'unsigned' => true],
    ];

    /** @var array */
    protected static $_typed_columns = [
        'id' => 'int',
        'site_id' => 'int',
        'query_count' => 'int',
        'execution_time' => 'float',
    ];

    protected $id;
    protected $site_id;
    protected $key;
    protected $driver;
    protected $query_count;
    protected $queries;
    protected $execution_time;
}
