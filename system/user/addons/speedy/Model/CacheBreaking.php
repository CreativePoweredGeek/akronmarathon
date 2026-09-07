<?php

namespace BoldMinded\Speedy\Model;

use ExpressionEngine\Service\Model\Model;

class CacheBreaking extends Model
{
    /** @var string */
    protected static $_primary_key = 'id';

    /** @var string */
    protected static $_table_name = 'speedy_breaking';

    /** @var array */
    protected static $_table_columns = [
        'id'           => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
        'entity_type'  => ['type' => 'text'],
        'entity_id'    => ['type' => 'int', 'constraint' => 10, 'null' => false, 'unsigned' => true],
        'refresh'      => ['type' => 'varchar', 'constraint' => 1],
        'tags'         => ['type' => 'text'],
        'items'        => ['type' => 'text'],
        'statuses'     => ['type' => 'text'],
        'categories'   => ['type' => 'text'],
    ];

    /** @var array */
    protected static $_typed_columns = [
        'id'           => 'int',
        'entity_id'    => 'int',
        'refresh'      => 'yesNo',
        'tags'         => 'pipeDelimited',
        'items'        => 'pipeDelimited',
        'statuses'     => 'pipeDelimited',
        'categories'   => 'pipeDelimited',
    ];

    /** @var array */
    protected static $_validation_rules = [
        'refresh'   => 'required|enum[y,n]',
    ];

    protected $id;
    protected $entity_type;
    protected $entity_id;
    protected $refresh = false;
    protected $tags;
    protected $items;
    protected $statuses;
    protected $categories;

    /**
     * @param string $value
     * @return \ExpressionEngine\Service\Validation\Result
     */
    public function validateTag($value)
    {
        /** @var \ExpressionEngine\Service\Validation\Validator $validator */
        $validator = ee('Validation')->make([
            'tag_name' => 'xss|noHtml|validTag',
        ]);
        $validator->defineRule('validTag', function ($key, $value) {
            if (preg_match('~^[^\|]+$~i', $value)) {
                return true;
            }

            return lang('speedy_valid_tag');
        });

        return $validator->validate([
            'tag_name' => $value,
        ]);
    }

    /**
     * @param string $value
     * @return \ExpressionEngine\Service\Validation\Result
     */
    public function validateItem($value)
    {
        /** @var \ExpressionEngine\Service\Validation\Validator $validator */
        $validator = ee('Validation')->make([
            'item_name' => 'xss|noHtml',
        ]);

        return $validator->validate([
            'item_name' => $value,
        ]);
    }
}
