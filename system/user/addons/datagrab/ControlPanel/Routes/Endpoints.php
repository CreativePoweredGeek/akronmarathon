<?php

namespace BoldMinded\DataGrab\ControlPanel\Routes;

use BoldMinded\DataGrab\ControlPanel\ModalController;
use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Endpoints extends EndpointRoutes
{
    /**
     * @var string
     */
    protected $route_path = 'endpoints';

    /**
     * @var string
     */
    protected $cp_page_title = 'Endpoints';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('endpoints', $this->cp_page_title);

        $table = ee('CP/Table', [
            'limit' => 0,
            'autosort' => false,
        ]);

        $table->setColumns([
            'Name' => ['encode' => false],
            'Endpoint URL' => ['encode' => false],
            'Import' => ['encode' => false],
            'Manage Actions'  => ['encode' => false],
        ]);

        $table->setNoResultsText('No endpoints found.');

        $rowData = [];
        $endpoints = $this->getEndpoints();
        $modalController = new ModalController();

        foreach ($endpoints as $endpoint) {
            $editLink = ee('CP/URL')->make('addons/settings/datagrab/endpoint-edit/' . $endpoint->id);

            $colToolbar = '<div class="button-group button-group-xsmall">';
            $colToolbar .= '<a class="button button--default" title="Edit endpoint" href="' . $editLink . '"><i class="fas fa-edit"></i></a>';
            $colToolbar .= '<a class="button button--default danger-link m-link" rel="modal-confirm-remove'. $endpoint->id .'" data-confirm="'. $endpoint->name .'" title="Delete endpoint" href="#"><i class="fas fa-trash-alt"></i></a>';
            $colToolbar .= '</div>';

            if ($endpoint->Import->name) {
                $importLink = '<a class="button button--default button--xsmall" title="Configure import fields" href="' . ee('CP/URL')->make('addons/settings/datagrab/load', ['id' => $endpoint->Import->id]) . '"><i class="fas fa-cog"></i></a> ' . $endpoint->Import->name;
            } else {
                $importLink = '<span class="st-error">Import Missing</span>';
            }

            $column = [
                sprintf('<a href="%s">%s</a>', $editLink, $endpoint->name),
                '<code>' . $this->getEndpointActionUrl(['endpoint' => $endpoint->name]) . '</code>',
                $importLink,
                $colToolbar,
            ];

            $rowData[] = [
                'attrs' => [],
                'columns' => $column
            ];

            $modalController->create('modal-confirm-remove'. $endpoint->id, 'ee:_shared/modal_confirm_remove', [
                'form_url' => ee('CP/URL')->make('addons/settings/datagrab/endpoint-delete/' . $endpoint->id),
                'hidden' => ['id' => $endpoint->id],
                'checklist' => [['kind' => 'Endpoint', 'desc' => $endpoint->name]]
            ]);
        }

        $table->setData($rowData);

        $vars = [
            'imports' => $this->getImportsAsChoices(),
            'table' => $table,
            'form_action' => ee('CP/URL', 'addons/settings/datagrab/endpoint-create'),
        ];

        $this->setBody('endpoints', $vars);

        return $this;
    }
}
