<?php

use BoldMinded\Speedy\Service\Drivers\AbstractDriver;
use BoldMinded\Speedy\Service\Drivers\ConfigurableDriverInterface;
use BoldMinded\Speedy\Service\Drivers\DummyDriver;
use ExpressionEngine\Service\JumpMenu\AbstractJumpMenu;


/**
 * @package     ExpressionEngine
 * @subpackage  Extensions
 * @category    Bloqs
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2012, 2019 - BoldMinded, LLC
 * @link        http://boldminded.com/add-ons/bloqs
 * @license
 *
 * Copyright (c) 2019. BoldMinded, LLC
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */

class Speedy_jump extends AbstractJumpMenu
{
    /**
     * @var \BoldMinded\Speedy\Test\Support\MockEE|\BoldMinded\Trek\Test\Support\MockEE|eeSingletonMock|false|mixed|\Mockery\MockInterface
     */
    private $drivers;

    /**
     * @var array[]
     */
    protected static $items = [
        'clearAll' => [
            'icon' => 'fa-database',
            'command' => 'cache clear driver',
            'command_title' => 'Clear All Drivers',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'flush_all',
        ],
        'clearDriver' => [
            'icon' => 'fa-database',
            'command' => 'cache clear driver',
            'command_title' => 'Clear <i>[driver]</i>',
            'dynamic' => true,
            'requires_keyword' => false,
            'target' => 'clearDriver',
        ],
        'clearTag' => [
            'icon' => 'fa-tag',
            'command' => 'cache clear tag',
            'command_title' => 'Clear <i>[tag]</i>',
            'dynamic' => true,
            'requires_keyword' => false,
            'target' => 'clearTag',
        ],
    ];

    public function __construct()
    {
        $this->drivers = ee('speedy:DriverFactory');
    }

    /**
     * Inject the CSRF token into state-changing static targets. flush_all is a
     * destructive GET action that now requires a valid token.
     */
    public function getItems()
    {
        $items = static::$items;

        if (defined('CSRF_TOKEN') && isset($items['clearAll'])) {
            $items['clearAll']['target'] = 'flush_all?token=' . CSRF_TOKEN;
        }

        return $items;
    }

    /**
     * @param array $searchKeywords
     * @return array
     */
    public function clearDriver(array $searchKeywords = []): array
    {
        $results = [];

        $drivers = $this->getSelectDriverChoices();
        $searchResults = preg_grep('/.*' . implode(' ', $searchKeywords) . '.*/i', $drivers);

        if (empty($searchResults)) {
            return $results;
        }

        $filteredDrivers = array_filter($drivers, function ($value, $key) use ($searchResults) {
            return array_key_exists($key, $searchResults) || in_array($value, $searchResults);
        }, ARRAY_FILTER_USE_BOTH);

        // The flush_driver action requires a CSRF token (it is state-changing
        // and reachable via GET), so include it in the jump-menu target.
        $token = defined('CSRF_TOKEN') ? '&token=' . CSRF_TOKEN : '';

        foreach ($filteredDrivers as $shortName => $label) {
            $results[$shortName] = [
                'icon' => 'fa-database',
                'command' => $shortName,
                'command_title' => $label,
                'dynamic' => false,
                'requires_keyword' => false,
                'target' => 'flush_driver?driver_name=' . $shortName . $token,
            ];
        }

        return $results;
    }

    /**
     * @param array $searchKeywords
     * @return array
     */
    public function clearTag(array $searchKeywords = []): array
    {
        $results = [];

        $tags = ee('Model')->get('speedy:Tag')->all()->getDictionary('tag', 'tag');
        $searchResults = preg_grep('/.*' . implode(' ', $searchKeywords) . '.*/i', $tags);

        if (empty($searchResults)) {
            return $results;
        }

        $filteredTags = array_filter($tags, function ($value) use ($searchResults) {
            return in_array($value, $searchResults);
        });

        foreach ($filteredTags as $tag) {
            $results[$tag] = [
                'icon' => 'fa-tag',
                'command' => $tag,
                'command_title' => $tag,
                'dynamic' => false,
                'requires_keyword' => false,
                'target' => 'tag_clearing?tags=' . $tag,
            ];
        }

        return $results;
    }

    /**
     * @return array
     */
    private function getSelectDriverChoices()
    {
        $choices = [];

        /** @var AbstractDriver $driver */
        foreach ($this->drivers->getDrivers() as $driver) {
            $name = $driver->getName();

            // Skip dummy and non-supported drivers
            if ($name === DummyDriver::NAME || !$driver->isSupported()) {
                continue;
            }

            // Skip non-configured drivers
            if ($driver instanceof ConfigurableDriverInterface && !$driver->isConfigured()) {
                continue;
            }

            $choices[$name] = lang('speedy_driver_' . $name);
        }

        return $choices;
    }
}
