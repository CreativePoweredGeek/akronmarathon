<?php

namespace BoldMinded\DataGrab\Model;

use ExpressionEngine\Service\Model\Model;

class Endpoint extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'datagrab_endpoints';

    /** @var array */
    protected static $_table_columns = [
        'id'  => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        'site_id' => ['type' => 'int', 'constraint' => 10, 'default' => 1],
        'import_id' => ['type' => 'int', 'constraint' => 10, 'null' => false],
        'name' => ['type' => 'varchar', 'constraint' => 250, 'null' => false],
        'settings' => ['type' => 'text'],
        'auth_type' => ['type' => 'varchar', 'constraint' => 10, 'null' => false],
    ];

    protected static $_validation_rules = array(
        'name' => 'required|maxLength[URL_TITLE_MAX_LENGTH]|alphaDashPeriodEmoji|validateUnique',
        'import_id' => 'required',
    );

    /** @var array */
    protected static $_typed_columns = [
        'id' => 'int',
        'import_id' => 'int',
        'site_id' => 'int',
    ];

    protected static $_relationships = [
        'Import' => [
            'type' => 'belongsTo',
            'from_key' => 'import_id',
            'to_key' => 'id',
        ],
    ];

    protected $id;
    protected $site_id;
    protected $import_id;
    protected $name;
    protected $settings;
    protected $auth_type;
}
