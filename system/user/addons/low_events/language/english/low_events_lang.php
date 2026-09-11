<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Low Events language file
 *
 * @package        low_events
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-events
 * @copyright      Copyright (c) 2012-2017, Low
 */
$lang = array(

    //----------------------------------------
    // Required for MODULES page
    //----------------------------------------

    "low_events_module_name" =>
    "Low Events",

    "low_events_module_description" =>
    "Easy Event Listings",

    //----------------------------------------
    // Fieldtype settings
    //----------------------------------------

    "le_first_day" =>
    "Date picker week starts with",

    "le_time_interval" =>
    "Time interval in minutes",

    "le_default_duration" =>
    "Default duration in minutes",

    "le_default_all_day" =>
    "Check “All Day” by default",

    "le_hide_all_day" =>
    "Hide “All Day” checkbox",

    "le_hide_end_date" =>
    "Hide end date",

    "le_overwrite_entry_date" =>
    "Overwrite entry date",

    "le_overwrite_expiration_date" =>
    "Overwrite expiration date",

    //----------------------------------------
    // Display field values
    //----------------------------------------

    "le_all_day" =>
    "All Day",

    "le_decimal" =>
    ".",

    "le_mins" =>
    "mins",

    "le_hr" =>
    "hr",

    "le_hrs" =>
    "hrs",

    //----------------------------------------
    // Validation messages
    //----------------------------------------

    "start_date_invalid" =>
    "Start date is invalid",

    "end_date_invalid" =>
    "End date is invalid",

    "start_time_invalid" =>
    "Start time is invalid",

    "end_time_invalid" =>
    "End time is invalid",

    "event_ends_before_start" =>
    "An event cannot end before it starts",

    /* END */
    '' => ''
);
