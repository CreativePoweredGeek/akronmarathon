<?php

namespace Litzinger\FileField;

use File_ft;

/**
 * ExpressionEngine FileField Class
 *
 * @package     ExpressionEngine
 * @subpackage  Libraries
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2016 - Brian Litzinger
 * @link        http://boldminded.com
 * @license
 *
 *  MIT License
 *
 *  Copyright (c) 2016 Brian Litzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

// Load the EE files that we're using
require_once APPPATH.'fieldtypes/EE_Fieldtype.php';
require_once PATH_ADDONS.'file/ft.file.php';

class FileField
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var string
     */
    private $fieldValue;

    /**
     * @param $fieldName
     * @param $fieldValue
     * @param array $settings
     */
    public function __construct($fieldName, $fieldValue, $settings = [])
    {
        $this->fieldName = $fieldName;
        $this->fieldValue = $fieldValue;
        $this->settings = $this->configureOptions($settings);
    }

    /**
     * @return string
     */
    public function render()
    {
        $fieldId = ee('Format')->make('Text', $this->fieldName)->urlSlug();
        $fileFt = new File_ft;
        $fileFt->settings = $this->settings;
        $fileFt->field_name = $this->fieldName;

        $fieldView = $fileFt->display_field($this->fieldValue);

        // Update some properties so the JS that is added to the page finds what it needs to.
        // This is for fields that contain array data, e.g. field[name]
        $fieldView = preg_replace('/data-input-image=\'(.*?)\'/', 'data-input-image="'. $fieldId .'"', $fieldView);
        $fieldView = preg_replace('/<img class="hidden" id="(.*?)"/', '<img class="hidden" id="'. $fieldId .'"', $fieldView);
        $fieldView = preg_replace('/id="(.*?)"/', 'id="'. $fieldId .'"', $fieldView);

        return $fieldView;
    }

    private function configureOptions(Array $options)
    {
        return array_replace([
            'allowed_directories' => '',
            'field_content_type' => 'all',
            'num_existing' => 0,
            'show_existing' => 'n',
        ], $options);
    }
}
