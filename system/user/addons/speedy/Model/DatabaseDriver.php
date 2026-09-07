<?php

namespace BoldMinded\Speedy\Model;

use ExpressionEngine\Service\Model\Model;

class DatabaseDriver extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'speedy_db_driver';

    /** @var array */
    protected static $_table_columns = [
        'id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        'site_id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        'key' => ['type' => 'varchar', 'constraint' => 250, 'null' => false],
        'created_at' => ['type' => 'int', 'constraint' => 10, 'null' => true],
        'expires_at' => ['type' => 'int', 'constraint' => 10, 'null' => false],
        'ttl' => ['type' => 'int', 'constraint' => 10, 'null' => false, 'default' => 360],
        'value' => ['type' => 'longtext', 'null' => true],
    ];

    /** @var array */
    protected static $_typed_columns = [
        'id' => 'int',
        'site_id' => 'int',
    ];

    protected static $_relationships = [
        'Stats' => array(
            'model' => 'DatabaseDriverStats',
            'type' => 'HasMany',
            'from_key' => 'key',
            'to_key' => 'key'
        )
    ];

    protected $id;
    protected $site_id;
    protected $key;
    protected $ttl;
    protected $created_at;
    protected $expires_at;
    protected $value;
}
