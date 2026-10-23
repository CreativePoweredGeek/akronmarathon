<?php

namespace EEHarbor\DetourPro\Conduit;

use EEHarbor\DetourPro\FluxCapacitor\Conduit\McpNav as FluxNav;
use EEHarbor\DetourPro\FluxCapacitor\FluxCapacitor;

class McpNav extends FluxNav
{
    protected function defaultItems()
    {
        $default_items = array(
            'index' => lang('nav_dashboard'),
            'detours' => lang('nav_home'),
            'import' => lang('nav_import_redirects'),
            'purge_hits' => lang('nav_purge'),
            'missing_pages' => lang('nav_missing_page_tracker'),
            'https://eeharbor.com/detour-pro/documentation' => lang('nav_documentation'),
            'settings' => lang('nav_settings'),
        );

        if (FluxCapacitor::L) {
            $default_items['license'] = lang('nav_license');
        }

        return $default_items;
    }

    protected function defaultButtons()
    {
        return array();
    }

    protected function defaultActiveMap()
    {
        return array(
            'detour_pro' => 'dashboard',
            'index' => 'dashboard',
            'dashboard' => 'dashboard',
            'detours' => 'detours',
            'addUpdate' => 'detours',
            'import' => 'import',
            'import_map' => 'import',
            'import_preview' => 'import',
            'import_execute' => 'import',
        );
    }

    public function generateNav()
    {
        if ($this->flux->is_ee2()) {
            return parent::generateNav();
        }

        $this->sidebar = ee('CP/Sidebar')->make();
        $last_segment = ee()->uri->segment_array();
        $last_segment = end($last_segment);
        $activeTitle = null;

        // Primary nav items
        $main_items = array(
            array(
                'title' => lang('nav_dashboard'),
                'url' => $this->flux->getBaseURL('dashboard'),
                'segments' => array('detour_pro', 'index', 'dashboard'),
            ),
            array(
                'title' => lang('nav_home'),
                'url' => $this->flux->getBaseURL('detours'),
                'segments' => array('detours', 'addUpdate'),
            ),
            array(
                'title' => lang('nav_import_redirects'),
                'url' => $this->flux->getBaseURL('import'),
                'segments' => array('import', 'import_map', 'import_preview', 'import_execute'),
            ),
            array(
                'title' => lang('nav_purge'),
                'url' => $this->flux->getBaseURL('purge_hits'),
                'segments' => array('purge_hits'),
            ),
            array(
                'title' => lang('nav_missing_page_tracker'),
                'url' => $this->flux->getBaseURL('missing_pages'),
                'segments' => array('missing_pages'),
            ),
        );

        foreach ($main_items as $item_data) {
            $item = $this->sidebar->addItem($item_data['title'], $item_data['url']);
            if (in_array($last_segment, $item_data['segments'])) {
                $item->isActive();
                $activeTitle = $item_data['title'];
            }
        }

        // Documentation section (header is intentionally non-clickable)
        $documentation_header = $this->sidebar->addHeader('Documentation');
        $documentation_list = $documentation_header->addBasicList();
        $documentation_item = $documentation_list->addItem(
            lang('nav_documentation'),
            'https://eeharbor.com/detour-pro/documentation'
        );
        if (method_exists($documentation_item, 'urlIsExternal')) {
            $documentation_item->urlIsExternal(true);
        }

        // Settings section (header is intentionally non-clickable)
        $settings_header = $this->sidebar->addHeader('Settings');
        $settings_list = $settings_header->addBasicList();

        $settings_item = $settings_list->addItem(
            lang('nav_settings'),
            $this->flux->getBaseURL('settings')
        );
        if ($last_segment == 'settings') {
            $settings_item->isActive();
            $activeTitle = lang('nav_settings');
        }

        if (FluxCapacitor::L) {
            $license_item = $settings_list->addItem(
                lang('nav_license'),
                $this->flux->getBaseURL('license')
            );
            if ($last_segment == 'license') {
                $license_item->isActive();
                $activeTitle = lang('nav_license');
            }
        }
        
        if ($activeTitle) {
            ee()->view->cp_page_title = $activeTitle;
            ee()->cp->set_breadcrumb($this->flux->moduleURL('index'), 'EEHarbor\DetourPro');
        }
    }

    public function postGenerateNav()
    {
    }
}
