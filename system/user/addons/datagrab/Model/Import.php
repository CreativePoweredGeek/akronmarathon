<?php

namespace BoldMinded\DataGrab\Model;

use ExpressionEngine\Service\Model\Model;

// @todo Switching the whole module to use this class instead of $db->query() calls will be a process.

class Import extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'datagrab';

    /** @var array */
    protected static $_typed_columns = [
        'id' => 'int',
        'site_id' => 'int',
        'total_records' => 'int',
        'last_record' => 'int',
        'last_run' => 'int',
        'last_started' => 'int',
        'total_delete_records' => 'int',
        'error_records' => 'int',
        'last_delete_record' => 'int',
        'order' => 'int',
        'version' => 'float',
    ];

    protected $id;
    protected $site_id;
    protected $name;
    protected $description;
    protected $passkey;
    protected $settings;
    protected $settings_legacy;
    protected $total_records;
    protected $last_record;
    protected $status;
    protected $last_run;
    protected $last_started;
    protected $import_id;
    protected $import_entryids;
    protected $total_delete_records;
    protected $error_records;
    protected $delete_entryids;
    protected $last_delete_record;
    protected $order;
    protected $version;
}
