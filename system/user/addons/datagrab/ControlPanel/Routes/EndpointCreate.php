<?php

namespace BoldMinded\DataGrab\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class EndpointCreate extends EndpointRoutes
{
    /**
     * @var string
     */
    protected $route_path = 'endpoint-create';

    /**
     * @var string
     */
    protected $cp_page_title = 'EndpointCreate';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('endpoint-create', 'Create Endpoint');

        ee()->load->library('form_validation');

        $vars['cp_page_title'] = 'Create Endpoint';
        $vars['base_url'] = ee('CP/URL')->make('addons/settings/datagrab/endpoint-create')->compile();
        $vars['save_btn_text'] = 'save';
        $vars['save_btn_text_working'] = 'btn_saving';

        $this->setBody('ee:_shared/form', $this->endpointForm($vars));

        return $this;
    }
}
