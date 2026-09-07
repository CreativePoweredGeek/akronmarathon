<?php

namespace BoldMinded\Speedy\Model;

use ExpressionEngine\Service\Model\Model;

class DatabaseDriverStats extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'speedy_db_driver_stats';

    /** @var array */
    protected static $_table_columns = [
        'id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        'site_id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        'key' => ['type' => 'varchar', 'constraint' => 250, 'null' => false],
        'hits' => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
        'misses' => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
    ];

    /** @var array */
    protected static $_typed_columns = [
        'id' => 'int',
        'site_id' => 'int',
        'hits' => 'int',
        'misses' => 'int',
    ];

    protected static $_relationships = [
        'Stats' => array(
            'model' => 'DatabaseDriver',
            'type' => 'BelongsTo',
            'from_key' => 'key',
            'to_key' => 'key'
        )
    ];

    protected $id;
    protected $site_id;
    protected $key;
    protected $hits;
    protected $misses;
}
