<?php

namespace BoldMinded\DataGrab\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class EndpointDelete extends EndpointRoutes
{
    /**
     * @var string
     */
    protected $route_path = 'endpoint-delete';

    /**
     * @var string
     */
    protected $cp_page_title = 'Delete Endpoint';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $endpoint = $this->findEndpoint($id);

        if ($endpoint) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asSuccess()
                ->cannotClose()
                ->withTitle(lang('success'))
                ->addToBody(sprintf('%s deleted', $endpoint->name))
                ->defer();

            $endpoint->delete();
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab/endpoints')->compile());

        return $this;
    }
}
