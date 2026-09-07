<?php

namespace BoldMinded\Speedy\Model;

use ExpressionEngine\Service\Model\Model;

class Url extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'speedy_urls';

    /** @var array */
    protected static $_table_columns = [
        'id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        'site_id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        'key' => ['type' => 'varchar', 'constraint' => 250, 'null' => false],
        'url' => ['type' => 'text'],
    ];

    /** @var array */
    protected static $_typed_columns = [
        'id' => 'int',
    ];

    protected $id;
    protected $site_id;
    protected $key;
    protected $url;
}
