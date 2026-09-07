<?php

$lang = [
    // Module details
    'speedy'                                    => SPEEDY_NAME,
    'speedy_module_name'                        => SPEEDY_NAME,
    'speedy_module_description'                 => 'An advanced cache module for ExpressionEngine.',
    // License
    'speedy_license' => 'License',
    'speedy_license_name' => 'License Key',
    'speedy_license_desc' => 'Enter your license key from boldminded.com, or the expressionengine.com store. If you purchased from expressionengine.com you need to <a href="https://boldminded.com/claim">claim your license</a>.',
    // Drivers
    'speedy_driver_apc'                         => 'APC',
    'speedy_driver_apcu'                        => 'APCu',
    'speedy_driver_dummy'                       => 'Dummy',
    'speedy_driver_database'                    => 'Database',
    'speedy_driver_file'                        => 'Filesystem',
    'speedy_driver_memcache'                    => 'Memcache',
    'speedy_driver_memcached'                   => 'Memcached',
    'speedy_driver_redis'                       => 'Redis',
    'speedy_driver_static'                      => 'Static',
    // Cache Info
    'speedy_cache_info'                         => 'Cache Information',
    'speedy_cache_info_driver'                  => 'Driver',
    'speedy_cache_info_count'                   => 'Number of Items',
    'speedy_cache_info_status'                  => 'Status',
    'speedy_cache_info_manage'                  => 'Manage',
    'speedy_cache_info_manage_view'             => 'View Driver Stats',
    'speedy_cache_info_manage_items'            => 'View Driver Items',
    'speedy_cache_info_manage_settings'         => 'Configure Driver',
    // Diagnostics Info
    'speedy_diagnostics'                        => 'Diagnostics',
    'speedy_diagnostics_overview'               => 'Diagnostics Overview',
    'speedy_diagnostics_desc'                   => 'Diagnostics provide an overview of the amount of queries and the
        execution time used by each item being cached ordered by the total execution time. Things may appear in this
        list that are not actually cached. A page or block of content can be wrapped in a cache tag, but if you have
        defined rules to ignore or conditionally not cache something then it is not actually cached. However, the
        diagnostics are still recorded to help identify performance issues or bottlenecks and to help you further
        optimize your templates.',
    'speedy_diagnostics_info_key'               => 'Cache Key',
    'speedy_diagnostics_info_query_count'       => 'Query Count',
    'speedy_diagnostics_info_execution_time'    => 'Execution Time',
    'speedy_diagnostics_info_manage'            => 'Manage',
    'speedy_diagnostics_delete_item_success'    => 'Diagnostic Deleted',
    'speedy_diagnostics_delete_item_success_desc' => 'The %s diagnostic record was deleted.',
    'speedy_diagnostics_delete_item_extended_success_desc' => 'The %s diagnostic record was deleted because the related cache item no longer exists.',
    'speedy_diagnostic_item'                    => 'Diagnostic Item',
    'speedy_diagnostic_time'                    => 'Seconds',
    'speedy_diagnostic_query'                   => 'Query',
    'speedy_diagnostic_item_miss'               => 'This cached item has expired or been deleted, but you can still view the diagnostics from the last hit.',
    'speedy_diagnostic_item_driver'             => 'Cache Driver',
    'speedy_diagnostic_item_driver_desc'        => 'The driver used to cache this item.',
    'speedy_diagnostic_item_query_count'        => 'Total Queries',
    'speedy_diagnostic_item_query_count_desc'   => 'The total number of queries prevented by caching this content.',
    'speedy_diagnostic_item_execution_time'     => 'Execution Time',
    'speedy_diagnostic_item_execution_time_desc'=> 'The total execution time prevented by caching this content.',
    'speedy_diagnostic_item_not_found'          => 'Diagnostic not found for %s. Either the cache item has expired or been deleted, or the data was not found in the diagnositcs table.',
    'speedy_delete_all_diagnostic_success'      => 'Diagnotics cleared!',

    // Driver Status
    'speedy_driver_name'                        => 'Cache Driver',
    'speedy_driver_status'                      => '%s Driver Support',
    'speedy_driver_status_available'            => 'available',
    'speedy_driver_status_available_desc'       => 'This driver is supported and available to use.',
    'speedy_driver_status_unsupported'          => 'unsupported',
    'speedy_driver_status_unsupported_desc'     => 'This driver is not supported. The following issues must be true to use this driver.',
    'speedy_driver_status_unconfigured'         => 'unconfigured',
    'speedy_driver_status_unconfigured_desc'    => 'This driver is supported, but requires configuration. The following issues must be true to use this driver.',
    // Refresh Cache
    'speedy_refresh_data'                       => 'Refresh Cache',
    'speedy_refresh_data_desc'                  => 'This will refresh all <i>expired</i> cache items (based on their ttl) from enabled drivers, but leave tags.',
    'speedy_refresh_data_btn'                   => 'Refresh',
    'speedy_refresh_data_btn_working'           => 'Refreshing Cache',
    'speedy_refresh_data_success'               => 'Success',
    'speedy_refresh_data_success_desc'          => 'Cache data has been refreshed.',
    'speedy_refresh_data_error'                 => 'Error',
    'speedy_refresh_data_error_desc'            => 'One or more cache drivers failed to refresh.',
    // Flush Driver
    'speedy_flush_driver'                       => 'Clear Driver',
    'speedy_flush_driver_desc'                  => 'This will clear a specific driver\'s cache, but leave tags.',
    'speedy_flush_driver_btn'                   => 'Clear',
    'speedy_flush_driver_btn_working'           => 'Clearing Cache',
    'speedy_flush_driver_success'               => 'Success',
    'speedy_flush_driver_success_desc'          => '%s cache driver has been cleared.',
    'speedy_flush_driver_error'                 => 'Error',
    'speedy_flush_driver_error_desc'            => 'There was an error clearing the cache.',
    // Flush All
    'speedy_flush_all'                          => 'Clear All Cache',
    'speedy_flush_all_desc'                     => 'This will clear <i>all</i> cache items from enabled drivers, and related tags.',
    'speedy_flush_all_btn'                      => 'Clear All',
    'speedy_flush_all_btn_working'              => 'Clearing All Cache',
    'speedy_flush_all_success'                  => 'Success',
    'speedy_flush_all_success_desc'             => 'All cache drivers and tags have been cleared.',
    'speedy_flush_all_success_queue'            => 'Queued',
    'speedy_flush_all_success_queue_desc'       => 'All cache drivers and tags have been queued for clearing.',
    'speedy_flush_all_error'                    => 'Error',
    'speedy_flush_all_error_desc'               => 'One or more cache drivers failed to clear.',
    // Reverse Proxy Purger
    'speedy_purge_all'                          => 'Purge All',
    'speedy_purge_all_desc'                     => 'Purge <i>all</i> cache items from %s. This will not clear Speedy\'s cache.',
    'speedy_purge_all_btn'                      => 'Purge All',
    'speedy_purge_all_btn_working'              => 'Purging All Cache',
    'speedy_purge_all_success'                  => 'Success',
    'speedy_purge_all_success_desc'             => '%s cache has been purged.',
    'speedy_purge_all_error'                    => 'Error',
    'speedy_purge_all_error_desc'               => '%s cache failed to purge.',


    // Driver Stats
    'speedy_driver_stats'                       => '%s Driver Stats',
    'speedy_driver_stats_hits'                  => 'Hits',
    'speedy_driver_stats_hits_desc'             => 'Number of keys that have been requested and found present.',
    'speedy_driver_stats_misses'                => 'Misses',
    'speedy_driver_stats_misses_desc'           => 'Number of items that have been requested and not found.',
    'speedy_hits_and_misses'                    => 'Hits and misses are not an accurate reflection of how many times
                                                    the cached item has been viewed on the front-end. It includes all
                                                    requests to the driver, even viewing the cache item in the
                                                    ExpressionEngine control panel.',
    'speedy_driver_stats_uptime'                => 'Uptime',
    'speedy_driver_stats_uptime_desc'           => 'Time that the server is running.',
    'speedy_driver_stats_memory_usage'          => 'Memory Usage',
    'speedy_driver_stats_memory_usage_desc'     => 'Memory used by this server to store items.',
    'speedy_driver_stats_memory_available'      => 'Memory Available',
    'speedy_driver_stats_memory_available_desc' => 'Memory allowed to use for storage.',
    'speedy_driver_stats_na'                    => 'N/A',
    'speedy_driver_stats_back'                  => 'Back',
    // Driver Items
    'speedy_driver_items'                       => 'View Items',
    'speedy_driver_items_path'                  => 'View Items: %s',
    'speedy_driver_items_clear_cache'           => 'Clear Cache',
    'speedy_driver_items_key'                   => 'Key',
    'speedy_driver_items_created'               => 'Created At',
    'speedy_driver_items_expires'               => 'Expires At',
    'speedy_driver_items_manage'                => 'Manage',
    'speedy_driver_items_search'                => 'Search this folder and its contents…',
    'speedy_driver_items_search_submit'         => 'Search',
    'speedy_driver_items_search_clear'          => 'Clear search',
    'speedy_driver_items_search_min'            => 'Enter at least 3 characters to search.',
    'speedy_driver_items_search_results'        => 'Showing %1$d result(s) for "%2$s"',
    'speedy_driver_items_search_none'           => 'No items match "%s" in this folder.',
    // Driver Items
    'speedy_driver_item'                        => 'View Item',
    'speedy_driver_item_key'                    => 'Key',
    'speedy_driver_item_key_desc'               => 'The fully qualified cache key.',
    'speedy_driver_item_ttl'                    => 'TTL',
    'speedy_driver_item_ttl_desc'               => 'Lifetime of the cached item.',
    'speedy_driver_item_ttl_format'             => '%d seconds',
    'speedy_driver_item_ttl_remaining'          => 'seconds remaining',
    'speedy_driver_item_created'                => 'Created At',
    'speedy_driver_item_created_desc'           => 'The time the cached item was generated.',
    'speedy_driver_item_expires'                => 'Expires At',
    'speedy_driver_item_expires_desc'           => 'The time the cached item will expire.',
    'speedy_driver_item_size'                   => 'Size',
    'speedy_driver_item_size_desc'              => 'The size of the cached content on disk.',
    'speedy_driver_item_size_bytes'             => 'bytes',
    'speedy_driver_item_content'                => 'Content',
    'speedy_driver_item_content_desc'           => '',
    'speedy_driver_item_back'                   => 'Back',
    // Delete Item
    'speedy_delete_item_success'                => 'Success',
    'speedy_delete_item_success_desc'           => 'The item was deleted successfully.',
    'speedy_delete_item_error'                  => 'Error',
    'speedy_delete_item_error_desc'             => 'There was an error deleting the item.',
    // Delete Path
    'speedy_delete_path_success'                => 'Success',
    'speedy_delete_path_success_desc'           => 'All items from this path were deleted.',
    'speedy_delete_path_error'                  => 'Error',
    'speedy_delete_path_error_desc'             => 'One or more items from this path were not deleted.',
    // Cache Clearing
    'speedy_cache_clear_url'                    => 'Alternative Cache Clearing Options',
    'speedy_cache_clear_url_desc' => '<p>Send a request to <code>%s</code> with your configured secret in the
                                      <code>X-Speedy-Secret</code> request header (or as a <code>secret</code> POST field),
                                      or use the <code>php system/ee/eecli.php speedy:clear</code> CLI command, such
                                      as after a deployment, to clear all drivers at any time. The secret is no longer
                                      accepted as a query-string parameter so it is not exposed in logs or browser history.</p>',
    'speedy_cache_clear_url_no_secret_desc' => '<p>The cache-clear action endpoints are disabled until you configure a
                                      secret. Add <code>$config[\'speedy_secret\'] = \'a-long-random-value\';</code> to your
                                      config.php file, then pass that value in the <code>X-Speedy-Secret</code> request
                                      header (or as a <code>secret</code> POST field) when calling <code>%s</code>.</p>',
    // Frontedit Support
    'speedy_frontedit_check'                    => 'Frontedit Compatibliity',
    'speedy_frontedit_check_desc'               => 'In order to use Speedy with ExpressionEngine Pro\'s Frontedit feature, you will need to add the following line to your config.php file. <p><br><code>$config[\'speedy_frontedit_check_url\'] = \'%s\';</code></p>',
    'speedy_frontedit_check_static_desc'        => '<br><p>You will then need to regenerate the utilities files for your static folder: <code>%s</code>. <a href="%s">Regenerate files</a></p>',

    'speedy_frontedit_check_msm_desc'           => 'In order to best use Speedy with ExpressionEngine Pro\'s Frontedit feature, you will need to add the following line to your config.php file. <p>
<br /><pre>
$config[\'speedy_msm\'] = [
    1 => [
        \'static_path\' => \'/var/www/html/static\',
        \'frontedit_check_url\' => \'https://site-one.com/?ACT=%s\',
    ],
    2 => [
        \'static_path\' => \'/var/www/html/static\',
        \'frontedit_check_url\' => \'https://site-two.com/?ACT=%s\',
    ],
    // Additional sites here
];
</pre>
</p>',
    'speedy_frontedit_check_static_msm_desc'    => '<br><p>You will then need to regenerate the utilities files for your static folder. <a href="%s">Regenerate files</a></p>',

    // Driver Settings
    'speedy_driver_settings'                    => '%s Driver Configuration',
    'speedy_driver_settings_save_btn'           => 'Save Configuration',
    'speedy_driver_settings_save_btn_working'   => 'Saving Configuration',
    'speedy_driver_settings_saved'              => 'Driver configuration saved',
    'speedy_driver_settings_saved_desc'         => 'Your driver configuration has been saved successfully.',
    'speedy_driver_settings_error'              => 'Attention: Driver configuration not saved',
    'speedy_driver_settings_error_desc'         => 'We were unable to save your driver configuration, please review and fix errors below.',
    'speedy_driver_settings_config_override'    => 'Settings loaded from config.php',
    'speedy_driver_settings_config_override_desc' => 'The following settings are loaded from your config.php file. Saving this form will not update the settings. To change these settings update the <b>%s</b> key in your config.php file.',
    // Driver Settings - Memcache
    'speedy_memcache_persistent'                => 'Persistent Connection',
    'speedy_memcache_servers'                   => 'Servers',
    'speedy_memcache_servers_host'              => 'Host',
    'speedy_memcache_servers_port'              => 'Port',
    'speedy_memcache_servers_weight'            => 'Weight',
    'speedy_memcache_servers_no_results'        => 'No servers added',
    'speedy_memcache_servers_add_btn'           => 'Add New Server',
    // Driver Settings - Memcached
    'speedy_memcached_persistent'               => 'Persistent Connection',
    'speedy_memcached_servers'                  => 'Servers',
    'speedy_memcached_servers_host'             => 'Host',
    'speedy_memcached_servers_port'             => 'Port',
    'speedy_memcached_servers_weight'           => 'Weight',
    'speedy_memcached_servers_no_results'       => 'No servers added',
    'speedy_memcached_servers_add_btn'          => 'Add New Server',
    // Driver Settings - Redis
    'speedy_redis_static'                       => 'Redis as Static',
    'speedy_redis_static_desc'                  => 'If you want to use Redis as a static caching option.
                                                    This is preferred over static file caching if your site is behind a load balancer on multiple servers.',
    'speedy_redis_servers'                      => 'Servers',
    'speedy_redis_servers_host'                 => 'Host',
    'speedy_redis_servers_port'                 => 'Port',
    'speedy_redis_servers_timeout'              => 'Timeout',
    'speedy_redis_servers_password'             => 'Password',
    'speedy_redis_servers_database'             => 'Database',
    'speedy_redis_servers_no_results'           => 'No servers added',
    'speedy_redis_servers_add_btn'              => 'Add New Server',
    'speedy_redis_ignores'                     => 'Ignored URLs',
    'speedy_redis_ignores_desc'                => 'The Redis driver will cache every page by default. You can add URLs below that will not be cached starting at site.com/<b>path/to/page</b>.
                                                      You can also use regular expressions to add more complicated ignore rules. For example, if you have a multi-lingual site, the following will
                                                      ignore all pages in the products section: <b>^[a-z]{2}/products</b>. <b>Note: This is only valid if Redis as Static is enabled.</b>',
    'speedy_redis_ignore_url'                  => 'URL',
    'speedy_redis_ignore_url_no_results'       => 'No URLs added',
    'speedy_redis_ignore_url_add_btn'          => 'Add New URL',
    // Driver Settings - Static
    'speedy_static_ignores'                     => 'Ignored URLs',
    'speedy_static_ignores_desc'                => 'The Static driver will cache every page by default. You can add URLs below that will not be cached starting at site.com/<b>path/to/page</b>.
                                                      You can also use regular expressions to add more complicated ignore rules. For example, if you have a multi-lingual site, the following will
                                                      ignore all pages in the products section: <b>^[a-z]{2}/products</b>',
    'speedy_static_ignore_url'                  => 'URL',
    'speedy_static_ignore_url_no_results'       => 'No URLs added',
    'speedy_static_ignore_url_add_btn'          => 'Add New URL',
    // Global messages
    'speedy_static_files_not_created'           => 'Static configuration is not complete.',
    //'speedy_static_files_recreate'              => 'Regenerate Static configuration files.',
    'speedy_static_files_not_created_desc'      => 'You are using the Static or Redis as Static drivers, but configuration is not complete. Speedy will add necessary files to your static folder: <code>%s</code>. <a href="%s">Finish configuration</a>.',
    'speedy_static_files_recreate_desc'         => 'You are using the Static or Redis as Static drivers, and the configuration has recently changed. It is suggested that you regenerate the utilities files in your static folder: <code>%s</code>. <a href="%s">Regenerate files</a>.',
    'speedy_create_static_files_success'        => 'Static configuration complete.',
    'speedy_create_static_files_success_desc'   => 'Speedy created the necessary files in your static folder: <code>%s</code>',
    'speedy_create_static_files_error'          => 'Static configuration failed.',
    'speedy_create_static_files_error_desc'     => 'Speedy was unable to created the necessary files in your static folder: <code>%s</code>. Please check the directory permissions to make sure it is writable.',

    'speedy_changed_file_message'               => 'If you see this file it indicates that driver settings have been changed, and static utility files may need to be regenerated.',
    'speedy_configured_file_message'            => 'If you see this file it indicates that static caching is enabled and properly configured.',

    'speedy_driver_not_supported'               => '%s cache driver is not supported on this system.',
    'speedy_driver_not_configured'              => '%s cache driver requires configuration to be used.',
    'speedy_driver_item_not_found'              => 'Cache item not found.',
    'speedy_invalid_cache_key'                  => 'The specified key "%s" is not valid. A key must contain only alpha-numeric characters, hyphens and underscores.',

    'speedy_mcp_configuration'                  => 'Configuration',
    'speedy_mcp_home'                           => 'Overview',
    'speedy_mcp_drivers'                        => 'Drivers',
    'speedy_mcp_diagnostics'                    => 'Diagnostics',
    'speedy_mcp_purger'                         => 'Reverse Proxy Purger',
    'speedy_mcp_documentation'                  => 'Documentation',
    'speedy_mcp_regenerate'                     => 'Regenerate Static Cache Utilities',
    'speedy_mcp_release_notes'                  => 'Release Notes',
    'speedy_settings'                           => 'Driver Settings',

    'speedy_mcp_license'                        => 'License',

    'speedy_purger_saved'                       => 'Reverse proxy purger settings saved',
    'speedy_purger_saved_desc'                  => '',

    'speedy_mcp_msm'                     => 'Multi-Site Manager Enabled',
    'speedy_mcp_msm_desc'                => 'All cache items, tags, and diagnostics are specific to <b>%s</b>. Any cache or tag clearing will only clear items relevant to this site.',

    'speedy_mcp_queue_available'           => 'Queue Available',
    'speedy_mcp_queue_available_desc'      => 'The Queue module is installed but not enabled.
        Add <code>$config[\'speedy_use_queue\'] = \'yes\';</code> to your config.php file to enable the Queue module to
        handle all cache clearing and URL refreshing requests.',

    'speedy_mcp_queue_enabled'           => 'Queue Enabled',
    'speedy_mcp_queue_enabled_desc'      => 'The Queue module is enabled and will handle all cache clearing and URL
        refreshing requests. For this reason you may not see immediate updates in the cache status table below.
        Make sure the Queue module is <a href="https://docs.boldminded.com/queue/docs/configuration">properly configured</a>.',

    'speedy_any'                         => 'Any',
    'speedy_mcp_cache_clearing'          => 'Cache Clearing Rules',
    'speedy_mcp_cache_clearing_settings_channel' => '%s Channel Settings',
    'speedy_mcp_cache_clearing_settings_category_group' => '%s Category Group Settings',
    'speedy_channel'                     => 'Channel',
    'speedy_any_channel'                 => 'Any Channel',
    'speedy_any_category_group'          => 'Any Category Group',
    'speedy_clearing_settings'           => 'Cache Break Settings',

    // Control Panel Drivers
    'speedy_driver'                      => 'Driver',
    'speedy_manage'                      => 'Manage',
    'speedy_disabled'                    => 'Warning',
    'speedy_disabled_desc'               => 'All caching via Speedy is currently disabled. To enable Speedy set <code>speedy_enabled</code> to <code>yes</code> in your config.php file. E.g. <code>$config[\'speedy_enabled\'] = \'yes\';</code>',
    'speedy_confirm_clear_cache'         => 'Are you sure you want to clear the %s driver cache?',
    'speedy_confirm_clear_button'        => 'Confirm, and Clear',
    'speedy_clearing_button'             => 'Clearing...',

    // Clear cache
    'speedy_confirm_clear_all_cache'     => 'Are you sure you want to clear the cache for all drivers?',
    'speedy_confirm_clear_all_button'    => 'Confirm, and Clear All Drivers',

    // Tag clearing
    'speedy_tag_clearing'                => 'Clear Tags',
    'speedy_mcp_tag_clearing'            => 'Clear Tags',
    'speedy_tag_clearing_desc'           => 'Select which tags and related cache items you want to clear.',
    'speedy_clear_tags_button'           => 'Clear Tags',
    'speedy_clear_tags_button_working'   => 'Clearing...',
    'speedy_tag_clearing_success'        => 'Items with the selected tags were cleared from cache.',
    'speedy_tag_clearing_no_tags'        => 'No tagged cache items.',
    'speedy_tag_clearing_no_tags_desc'   => 'Nothing to clear! Looks like you\'re good to go.',

    // View Items
    'speedy_items'                       => 'Items',
    'speedy_expires'                     => 'Expires',
    'speedy_delete_item'                 => 'Delete Item',
    'speedy_delete_path'                 => 'Delete All Children',

    // Cache Clearing Settings
    'speedy_clearing_settings_tab'       => 'Settings',
    'speedy_clearing_refresh'            => 'Refresh Items',
    'speedy_clearing_refresh_desc'       => 'Should Speedy refresh cached items after they are deleted?',
    'speedy_clearing_statuses'           => 'Clear Statuses',
    'speedy_clearing_statuses_desc'      => 'Clear cache of entries matching the following statuses. If no status is selected, the cache will be cleared regardless of the entry\'s status. (Default behavior)',
    'speedy_clearing_categories'         => 'Clear Categories',
    'speedy_clearing_categories_desc'    => 'Clear cache of entries matching one or more of the following categories. If no categories are selected, the cache will be cleared regardless of the entry\'s assigned categories. (Default behavior)',
    'speedy_clearing_tags'               => 'Clear Tags',
    'speedy_clearing_no_tags'            => 'No tags to clear',
    'speedy_clearing_add_tag'            => 'Add New Tag',
    'speedy_clearing_items'              => 'Clear Items',
    'speedy_clearing_no_items'           => 'No item to clear',
    'speedy_clearing_add_item'           => 'Add New Item',
    'speedy_clearing_saved'              => 'Settings Saved',
    'speedy_clearing_saved_desc'         => 'The cache breaking settings for "%s" have been saved.',
    'speedy_clearing_not_saved'          => 'Cannot Save Settings',
    'speedy_clearing_not_saved_desc'     => 'We were unable to save this channel\'s settings, please review and fix errors below.',

    'speedy_clearing_tags_desc_channel'          => 'Any items that have these tags will be removed or refreshed when an entry in this channel changes.',
    'speedy_clearing_tags_desc_cont_channel'     =>
        '<h2>Examples:</h2>' .
        '<p>To clear all items with a tag of "apple", you would add <code>apple</code></p>' .
        '<p>To clear all items with a tag of the current channel name, you could add <code>{channel_name}</code></p>' .
        '<p><a href="https://docs.boldminded.com/speedy/docs/control-panel#cache-breaking">View the docs</a> for more information on cache breaking.</p>',

    'speedy_clearing_items_desc_channel' => 'You can add items or item paths to remove or refresh when an entry in this channel changes.',
    'speedy_clearing_items_desc_cont_channel' =>
        '<p>Items should begin with either <code>global/</code>, <code>local/</code>, or <code>static/</code>, depending on their cache type.</p>' .
        '<p>If you are specifying a parent path (as opposed to an item key), then be sure to give it a trailing slash.</p>' .
        '<h2>Examples:</h2>' .
        '<p>To clear all static items for the entire site, you would add: <code>static/</code></p>' .
        '<p>If you had a "blog" section of your site, and wanted to remove all local cached content under that section, you would add: <code>local/blog/</code></p>' .
        '<p>If you wanted to clear a specific item, like your home page, you could add: <code>local/item</code> (assuming your home page has a cache item with the key "item")</p>' .
        '<p>To clear a global item with a key of "footer", you could add: <code>global/footer</code></p>' .
        '<p>To clear all local caches where <code>{segment_1}</code> matched the current <code>{channel_name}</code> and <code>{segment_2}</code> matched the <code>{url_title}</code>, use <code>local/{channel_name}/{url_title}/</code></p>' .
        '<p>You can format date variables using a <code>format</code> parameter, e.g. <code>local/blog/{entry_date format="%Y"}/</code>. Both <code>{entry_date}</code> and <code>{edit_date}</code> are supported, using ExpressionEngine\'s <a href="https://docs.expressionengine.com/latest/templates/date_variable_formatting.html">date variable formatting</a>.</p>' .
        '<p><a href="https://docs.boldminded.com/speedy/docs/control-panel#cache-breaking">View the docs</a> for more information on cache breaking.</p>',

    'speedy_clearing_tags_desc_category_group' => 'Any items that have these tags will be removed or refreshed when a category in this group changes.',
    'speedy_clearing_tags_desc_cont_category_group' =>
        '<h2>Examples:</h2>' .
        '<p>To clear all items with a tag of "apple", you would add <code>apple</code></p>' .
        '<p>To clear all items with a tag of the current category group name, you could add <code>{category_group_name}</code></p>' .
        '<p><a href="https://docs.boldminded.com/speedy/docs/control-panel#cache-breaking">View the docs</a> for more information on cache breaking.</p>',

    'speedy_clearing_items_desc_category_group' => 'You can add items or item paths to remove or refresh when an category in this group changes.',
    'speedy_clearing_items_desc_cont_category_group' =>
        '<p>Items should begin with either <code>global/</code>, <code>local/</code>, or <code>static/</code>, depending on their cache type.</p>' .
        '<p>If you are specifying a parent path (as opposed to an item key), then be sure to give it a trailing slash.</p>' .
        '<h2>Examples:</h2>' .
        '<p>To clear all static items for the entire site, you would add: <code>static/</code></p>' .
        '<p>If you had a "blog" section of your site, and wanted to remove all local cached content under that section, you would add: <code>local/blog/</code></p>' .
        '<p>If you wanted to clear a specific item, like your home page, you could add: <code>local/item</code> (assuming your home page has a cache item with the key "item")</p>' .
        '<p>To clear a global item with a key of "footer", you could add: <code>global/footer</code></p>' .
        '<p>To clear all local caches where <code>{segment_1}</code> matched the current <code>{category_group_name}</code> and <code>{segment_2}</code> matched the <code>{category_url_title}</code>, use <code>local/{category_group_name}/{category_url_title}/</code></p>' .
        '<p>You can format date variables using a <code>format</code> parameter, e.g. <code>local/blog/{entry_date format="%Y"}/</code>. Both <code>{entry_date}</code> and <code>{edit_date}</code> are supported, using ExpressionEngine\'s <a href="https://docs.expressionengine.com/latest/templates/date_variable_formatting.html">date variable formatting</a>.</p>' .
        '<p><a href="https://docs.boldminded.com/speedy/docs/control-panel#cache-breaking">View the docs</a> for more information on cache breaking.</p>',

    // Validation rules
    'speedy_valid_tag'                   => 'Tags must not contain the pipe "|" character.',

    // Supported Checks
    'speedy_supported_function_usable'   => 'The function "%s" does not exist, or is denylisted by the Suhosin PHP extension.',
    'speedy_supported_class_exists'      => 'The class "%s" does not exist.',
    'speedy_supported_ini_enabled'       => 'The ini key "%s" is not enabled.',
    'speedy_supported_config_equals'     => 'The config key "%s" is not set to "%s".',
    'speedy_supported_config_not_equals' => 'The config key "%s" is set to "%s".',

    'speedy_loopback_check'              => 'Possible HTTP "loopback" issue',
    'speedy_loopback_check_desc'         => 'If Refresh Items is enabled, Speedy might not be able to refresh previously cached pages. Please <a target="_blank" href="https://docs.boldminded.com/speedy/docs/cache-clearing/refreshing-items">refer to the documentation for troubleshooting</a>.',

    // END
    ''                                     => '',
];
