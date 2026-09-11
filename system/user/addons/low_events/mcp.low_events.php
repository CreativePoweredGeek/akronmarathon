<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Low Events Module Control Panel class
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
include_once "addon.setup.php";
use Low\Events\FluxCapacitor\Base\Mcp;

class Low_events_mcp extends Mcp
{
}
