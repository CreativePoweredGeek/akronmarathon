<?php

namespace BoldMinded\DataGrab\Dependency;

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\League\\Csv\\delimiter_detect')) {
    require __DIR__ . '/functions.php';
}
