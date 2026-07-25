<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * SEO Lite (Pro) Module Control Panel File
 *
 * @category   Module
 * @package    ExpressionEngine
 * @subpackage Addons
 * @author     0to9 Digital - Robin Treur
 * @link       https://0to9.nl
 */
class Seo_lite_mcp 
{
	var $base;			// the base url for this module			
	var $form_base;		// base url for forms
	var $module_name = "seo_lite";	

	function __construct( $switch = TRUE )
	{   
        // uncomment this if you want navigation buttons at the top
		ee()->cp->set_right_nav(array(
				'settings'			=> $this->base,
				'docs'	=> 'https://github.com/0to9Digital/SEO-Lite-v2',
			));


		//  Onward!
		ee()->load->library('table');
		ee()->load->library('javascript');
		ee()->load->helper('form');
		ee()->lang->loadfile('seo_lite');

        // The Control Panel's left sidebar is built with the Sidebar Service:
        $sidebar = ee('CP/Sidebar')->make();
        if(substr(APP_VER, 0, 1) < 6) {
            // IF EE5
            $sidebar_list = $sidebar->addHeader('Sidebar');
            $sidebar_items = $sidebar_list->addBasicList();
            $sidebar_items->addItem('Settings', ee('CP/URL', 'addons/settings/seo_lite'));
            $sidebar_items->addItem('Instructions', ee('CP/URL', 'addons/settings/seo_lite/instructions'));
            $sidebar_items->addItem('Audit Overview', ee('CP/URL', 'addons/settings/seo_lite/audit_overview'));
        } else {
            // IF EE6
            $settings = $sidebar->addItem('Settings', ee('CP/URL', 'addons/settings/seo_lite'));
            $settings->withIcon('cog');
            $instructions = $sidebar->addItem('Instructions', ee('CP/URL', 'addons/settings/seo_lite/instructions'));
            $instructions->withIcon('info-circle');
            $audit = $sidebar->addItem('Audit Overview', ee('CP/URL', 'addons/settings/seo_lite/audit_overview'));
            $audit->withIcon('clipboard-check');
        }
        
        ee('CP/URL', 'addons/settings/seo_lite/audit_entry');

        ee()->cp->add_to_head("<link rel='stylesheet' href='" . URL_THIRD_THEMES . "seo_lite/css/seo_lite.css?v2.2.2'>");
        ee()->cp->add_to_foot("<script type='text/javascript' charset='utf-8' src='". URL_THIRD_THEMES . "seo_lite/js/seo_lite.js?v2.2.2'></script>");
	}

	function index() 
	{
		$vars = array();

        $site_id = ee()->config->item('site_id');
        $config = ee()->db->get_where('seolite_config', array('site_id' => $site_id));

        if($config->num_rows() == 0) // we did not find any config for this site id, so just load any other
        {
            $config = ee()->db->get_where('seolite_config');
        }

		$vars['template'] = $config->row('template');
        $vars['default_description'] = $config->row('default_description');
		$vars['default_keywords'] = $config->row('default_keywords');
        $vars['default_title_postfix'] = $config->row('default_title_postfix');
        $vars['default_og_description'] = $config->row('default_og_description');
        $vars['default_og_image'] = $config->row('default_og_image');
        $vars['default_og_image_url'] = $this->getSeoLiteFileUrl($vars['default_og_image']);
        $vars['default_twitter_description'] = $config->row('default_twitter_description');
        $vars['default_twitter_image'] = $config->row('default_twitter_image');
        $vars['default_twitter_image_url'] = $this->getSeoLiteFileUrl($vars['default_twitter_image']);
        $vars['include_pagination_in_canonical'] = $config->row('include_pagination_in_canonical');
        $vars['save_settings_url'] =  ee('CP/URL', 'addons/settings/seo_lite/save_settings');

        $view = ee('View')->make('seo_lite:index');

        return $view->render($vars);
	}

    function instructions() 
	{

        $view = ee('View')->make('seo_lite:instructions');

        return $view->render();
	}

    private function getSeoLiteFileUrl($file_reference)
    {
        if ($file_reference === null || $file_reference === '') {
            return '';
        }

        if ((string) (int) $file_reference === (string) $file_reference) {
            $file = ee('Model')->get('File', (int) $file_reference)->first();

            return $file ? $file->getAbsoluteURL() : '';
        }

        ee()->load->library('file_field');

        return ee()->file_field->parse_string($file_reference);
    }

    private function getPublisherLanguages()
    {
        return ee()->db->select('
                id,
                short_name,
                long_name,
                short_name_segment
            ')
            ->from('publisher_languages')
            ->where('is_enabled', 'y')
            ->order_by('id', 'asc')
            ->get()
            ->result_array();
    }

    private function getDefaultPublisherLanguageId()
    {
        $languages = $this->getPublisherLanguages();

        return isset($languages[0]['id']) ? $languages[0]['id'] : null;
    }

    private function getAuditTargetPath($entry_id, $language_segment = '')
    {
        if (empty($entry_id)) {
            return '';
        }

        $entry = ee('Model')->get('ChannelEntry', $entry_id)
            ->with('Channel')
            ->first();

        if (! $entry) {
            return '';
        }

        if ($entry->hasPageURI()) {
            $path = $entry->getPageURI();
        } elseif (!empty($entry->Channel->preview_url)) {
            $url_title = $entry->url_title ?: $entry->entry_id;
            $path = str_replace(['{url_title}', '{entry_id}'], [$url_title, $entry->entry_id], $entry->Channel->preview_url);
        } else {
            return '';
        }

        $parsed_path = parse_url($path);

        if ($parsed_path && isset($parsed_path['host'])) {
            $path = isset($parsed_path['path']) ? $parsed_path['path'] : '/';

            if (isset($parsed_path['query']) && $parsed_path['query'] !== '') {
                $path .= '?' . $parsed_path['query'];
            }
        }

        $path = '/' . ltrim((string) $path, '/');

        if ($language_segment) {
            $language_prefix = '/' . trim($language_segment, '/');

            if ($path !== $language_prefix && strpos($path, $language_prefix . '/') !== 0) {
                $path = $language_prefix . ($path === '/' ? '' : $path);
            }
        }

        return $path;
    }

    private function getSeoLiteAbsoluteUrl($path)
    {
        if ($path === '') {
            return '';
        }

        return ee()->functions->create_url(ltrim($path, '/'));
    }

    function getAuditOverviewData($publisherInstalled) {
        $page = intval(ee()->input->get('page'));
        $per_page = 15;
        $site_id = ee()->config->item('site_id');

        if (empty($page)) $page = 1;

        $start_num = ($page * $per_page) - $per_page;

        $total = ee()->db->count_all_results('channel_titles');

        // If no matching channel_id, total is 0
        if (empty($total)) {
            $total_records = 0;
        } else {
            $total_records = $total;
        }
        
        if ($publisherInstalled) {
            $data['entries'] =
            ee()->db->select('
                ct.entry_id,
                ct.title,
                sl.title as meta_title,
                sl.description as meta_description,
                sl.keywords as meta_keywords,
                sl.robots_directive as meta_robots,
                sl.og_title as og_title,
                sl.og_type as og_type,
                sl.og_description as og_description,
                sl.og_url as og_url,
                sl.og_image as og_image,
                sl.twitter_title as twitter_title,
                sl.twitter_type as twitter_type,
                sl.twitter_description as twitter_description,
                sl.twitter_image as twitter_image,
                sl.publisher_lang_id as publisher_lang_id,
                sl.publisher_status as publisher_status,
                pub.short_name as language_name
            ')
            ->from('channel_titles ct')
            ->where('ct.site_id', $site_id)
            ->limit($per_page, $start_num)
            ->order_by('ct.entry_id', 'desc')
            ->join('publisher_seolite_content sl', 'ct.entry_id = sl.entry_id', 'left')
            ->join('publisher_languages pub', 'sl.publisher_lang_id = pub.id', 'left')
            ->get()
            ->result_array();
        } else {
            $data['entries'] =
            ee()->db->select('
                ct.entry_id,
                ct.title,
                sl.title as meta_title,
                sl.description as meta_description,
                sl.keywords as meta_keywords,
                sl.robots_directive as meta_robots,
                sl.og_title as og_title,
                sl.og_type as og_type,
                sl.og_description as og_description,
                sl.og_url as og_url,
                sl.og_image as og_image,
                sl.twitter_title as twitter_title,
                sl.twitter_type as twitter_type,
                sl.twitter_description as twitter_description,
                sl.twitter_image as twitter_image
            ')
            ->from('channel_titles ct')
            ->where('ct.site_id', $site_id)
            ->limit($per_page, $start_num)
            ->join('seolite_content sl', 'ct.entry_id = sl.entry_id', 'left')
            ->get()
            ->result_array();
        }

        // Get pagination
        $data['pagination'] = ee('CP/Pagination', $total_records)
            ->currentPage($page)
            ->perPage($per_page)
            ->queryStringVariable('page')
            ->displayPageLinks(5)
            ->render(ee('CP/URL', 'addons/settings/seo_lite/audit_overview'));

        return $data;
    }
    
    function getAuditEntryData($publisherInstalled, $publisher_id) {
        $site_id = ee()->config->item('site_id');
        $entry_id = ee()->input->get('entry_id');
        

        if($publisherInstalled) {
            $data = ee()->db->select('
                ct.entry_id,
                ct.title,
                sl.title as meta_title,
                sl.description as meta_description,
                sl.keywords as meta_keywords,
                sl.robots_directive as meta_robots,
                sl.og_title as og_title,
                sl.og_type as og_type,
                sl.og_description as og_description,
                sl.og_url as og_url,
                sl.og_image as og_image,
                sl.twitter_title as twitter_title,
                sl.twitter_type as twitter_type,
                sl.twitter_description as twitter_description,
                sl.twitter_image as twitter_image,
                sl.publisher_lang_id as publisher_lang_id,
                sld.default_keywords as default_keywords,
                sld.default_description as default_description,
                sld.default_title_postfix as default_title_postfix,
                sld.default_og_description as default_og_description,
                sld.default_og_image as default_og_image,
                sld.default_twitter_description as default_twitter_description,
                sld.default_twitter_image as default_twitter_image,
                pub.short_name as language_name,
                pub.short_name_segment as language_segment
            ')
            ->from('channel_titles ct')
            ->where('ct.entry_id', $entry_id)
            ->where('publisher_lang_id', $publisher_id)
            ->where('ct.site_id', $site_id)
            ->join('publisher_seolite_content sl', 'ct.entry_id = sl.entry_id', 'left')
            ->join('seolite_config sld', 'ct.site_id = sld.site_id', 'left')
            ->join('publisher_languages pub', 'sl.publisher_lang_id = pub.id', 'left')
            ->get();
            
        } else {
            $data = ee()->db->select('
                ct.entry_id,
                ct.title,
                sl.title as meta_title,
                sl.description as meta_description,
                sl.keywords as meta_keywords,
                sl.robots_directive as meta_robots,
                sl.og_title as og_title,
                sl.og_type as og_type,
                sl.og_description as og_description,
                sl.og_url as og_url,
                sl.og_image as og_image,
                sl.twitter_title as twitter_title,
                sl.twitter_type as twitter_type,
                sl.twitter_description as twitter_description,
                sl.twitter_image as twitter_image,
                sld.default_keywords as default_keywords,
                sld.default_description as default_description,
                sld.default_title_postfix as default_title_postfix,
                sld.default_og_description as default_og_description,
                sld.default_og_image as default_og_image,
                sld.default_twitter_description as default_twitter_description,
                sld.default_twitter_image as default_twitter_image
            ')
            ->from('channel_titles ct')
            ->where('ct.entry_id', $entry_id)
            ->where('ct.site_id', $site_id)
            ->join('seolite_content sl', 'ct.entry_id = sl.entry_id', 'left')
            ->join('seolite_config sld', 'ct.site_id = sld.site_id', 'left')
            ->get();
        }
        
        if($data->num_rows == 0) {
            if ($publisherInstalled && $publisher_id) {
                $data = ee()->db->select('
                    ct.entry_id,
                    ct.title,
                    sld.default_keywords as default_keywords,
                    sld.default_description as default_description,
                    sld.default_title_postfix as default_title_postfix,
                    sld.default_og_description as default_og_description,
                    sld.default_og_image as default_og_image,
                    sld.default_twitter_description as default_twitter_description,
                    sld.default_twitter_image as default_twitter_image,
                    pub.short_name as language_name,
                    pub.short_name_segment as language_segment
                ')
                ->from('channel_titles ct')
                ->where('ct.entry_id', $entry_id)
                ->where('ct.site_id', $site_id)
                ->join('seolite_config sld', 'ct.site_id = sld.site_id', 'left')
                ->join('publisher_languages pub', 'pub.id = ' . ee()->db->escape($publisher_id), 'left')
                ->get();
            } else {
                $data = ee()->db->select('
                    ct.entry_id,
                    ct.title,
                    sld.default_keywords as default_keywords,
                    sld.default_description as default_description,
                    sld.default_title_postfix as default_title_postfix,
                    sld.default_og_description as default_og_description,
                    sld.default_og_image as default_og_image,
                    sld.default_twitter_description as default_twitter_description,
                    sld.default_twitter_image as default_twitter_image
                ')
                ->from('channel_titles ct')
                ->where('ct.entry_id', $entry_id)
                ->where('ct.site_id', $site_id)
                ->join('seolite_config sld', 'ct.site_id = sld.site_id', 'left')
                ->get();
            }
        }

        $results = $data->result_array();

        if (!empty($results)) {
            return $results[0];
        }

        return array(
            'entry_id' => $entry_id,
            'title' => '',
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'meta_robots' => '',
            'og_title' => '',
            'og_type' => '',
            'og_description' => '',
            'og_url' => '',
            'og_image' => '',
            'twitter_title' => '',
            'twitter_type' => '',
            'twitter_description' => '',
            'twitter_image' => '',
            'default_keywords' => '',
            'default_description' => '',
            'default_title_postfix' => '',
            'default_og_description' => '',
            'default_og_image' => '',
            'default_twitter_description' => '',
            'default_twitter_image' => '',
            'language_name' => '',
            'language_segment' => ''
        );
    }
    
    function PublisherInstalled() {
        return ee('Addon')->get('publisher') && ee('Addon')->get('publisher')->isInstalled();
    }

    function audit_overview() 
	{
        $publisher = $this->PublisherInstalled();
        
        $vars['data'] =  $this->getAuditOverviewData($publisher);
        $vars['data']['publisher'] = $publisher;

        if ($publisher) {
            $vars['data']['default_publisher_id'] = $this->getDefaultPublisherLanguageId();
        }

        $view = ee('View')->make('seo_lite:audit_overview');
        return $view->render($vars);
	}

    function audit_entry() 
	{
        $publisher = $this->PublisherInstalled();
        $publisher_id = ee()->input->get('publisher_id');
        $languages = array();

        if ($publisher) {
            $languages = $this->getPublisherLanguages();

            if (empty($publisher_id) && isset($languages[0]['id'])) {
                $publisher_id = $languages[0]['id'];
            }
        }

        $vars['data'] =  $this->getAuditEntryData($publisher, $publisher_id);
        $audit_target_path = $this->getAuditTargetPath(
            isset($vars['data']['entry_id']) ? $vars['data']['entry_id'] : null,
            isset($vars['data']['language_segment']) ? $vars['data']['language_segment'] : ''
        );
        $vars['data']['audit_target_path'] = $audit_target_path;
        $vars['data']['entry_url'] = $this->getSeoLiteAbsoluteUrl($audit_target_path);
        $vars['data']['og_image_url'] = $this->getSeoLiteFileUrl(
            !empty($vars['data']['og_image']) ? $vars['data']['og_image'] : (isset($vars['data']['default_og_image']) ? $vars['data']['default_og_image'] : '')
        );
        $vars['data']['twitter_image_url'] = $this->getSeoLiteFileUrl(
            !empty($vars['data']['twitter_image']) ? $vars['data']['twitter_image'] : (isset($vars['data']['default_twitter_image']) ? $vars['data']['default_twitter_image'] : '')
        );

        if($publisher) {
            $vars['data']['publisher'] = $publisher;
            $vars['data']['publisher_id'] = $publisher_id;
            $vars['data']['languages'] = $languages;
        }

        $view = ee('View')->make('seo_lite:audit_entry');

        return $view->render($vars);
	}
	
	function save_settings()
	{
		$template = ee()->input->post('seolite_template');
        $default_keywords = ee()->input->post('seolite_default_keywords');
        $default_description = ee()->input->post('seolite_default_description');
        $default_title_postfix = ee()->input->post('seolite_default_title_postfix');
        $default_og_description = ee()->input->post('seolite_default_og_description');
        $default_og_image = ee()->input->post('seolite_default_og_image');
        $default_twitter_description = ee()->input->post('seolite_default_twitter_description');
        $default_twitter_image = ee()->input->post('seolite_default_twitter_image');
        
        $include_pagination_in_canonical = ee()->input->post('seolite_include_pagination_in_canonical');

        $site_id = ee()->config->item('site_id');
        $config = ee()->db->get_where('seolite_config', array('site_id' => $site_id));

        $data_arr = array(
                'template' => $template,
                'default_keywords' => $default_keywords,
                'default_description' => $default_description,
                'default_title_postfix' => $default_title_postfix,
                'default_og_description' => $default_og_description,
                'default_og_image' => $default_og_image,
                'default_twitter_description' => $default_twitter_description,
                'default_twitter_image' => $default_twitter_image,
                'include_pagination_in_canonical' => $include_pagination_in_canonical,
            );

        if($config->num_rows() == 0)
        {
            $data_arr['site_id'] = $site_id;
            ee()->db->insert('seolite_config', $data_arr);
        }
        else
        {
            ee()->db->where('site_id', $site_id);
            ee()->db->update('seolite_config', $data_arr);
        }

        ee('CP/Alert')->makeStandard('seolite-settings-saved')
            ->asSuccess()
            ->withTitle(lang('seolite_settings_saved_title'))
            ->addToBody(lang('seolite_settings_saved'))
            ->defer();

		ee()->functions->redirect(ee('CP/URL', 'addons/settings/seo_lite'));
	}

}

/* End of file mcp.seo_lite.php */
