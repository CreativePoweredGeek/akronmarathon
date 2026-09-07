<?php

namespace BoldMinded\DataGrab\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class EndpointEdit extends EndpointRoutes
{
    /**
     * @var string
     */
    protected $route_path = 'endpoint-edit';

    /**
     * @var string
     */
    protected $cp_page_title = 'Edit Endpoint';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('endpoint-edit', 'Edit Endpoint');

        ee()->load->library('form_validation');

        $vars['cp_page_title'] = 'Edit Endpoint';
        $vars['base_url'] = ee('CP/URL')->make('addons/settings/datagrab/endpoint-edit')->compile();
        $vars['save_btn_text'] = 'save';
        $vars['save_btn_text_working'] = 'btn_saving';

        $this->setBody('ee:_shared/form', $this->endpointForm($vars, (int) $id));

        return $this;
    }
}
