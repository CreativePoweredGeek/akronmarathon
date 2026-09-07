<?php

namespace BoldMinded\DataGrab\ControlPanel\Routes;

use BoldMinded\DataGrab\ControlPanel\MiniGrid;
use BoldMinded\DataGrab\Model\Endpoint;
use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;
use ExpressionEngine\Service\Model\Collection;

abstract class EndpointRoutes extends AbstractRoute
{
    protected MiniGrid $miniGrid;

    public function __construct()
    {
        parent::__construct();

        $this->miniGrid = new MiniGrid;
    }

    protected function getEndpoints(): Collection
    {
        return ee('Model')->get('datagrab:Endpoint')
            ->filter('site_id', ee()->config->item('site_id'))
            ->with('Import')
            ->all();
    }

    protected function findEndpoint(int $endpointId): Endpoint | null
    {
        return ee('Model')->get('datagrab:Endpoint')
            ->filter('id', $endpointId)
            ->with('Import')
            ->first();
    }

    /** @return array<int, string> */
    protected function getImportsAsChoices(): array
    {
        return ee('Model')->get('datagrab:Import')
            ->filter('site_id', ee()->config->item('site_id'))
            ->order('order', 'ASC')
            ->order('name', 'ASC')
            ->all()
            ->getDictionary('id', 'name');
    }

    protected function getEndpointActionUrl(array $params = []): string
    {
        $action = ee('Model')->get('Action')
            ->filter('class', 'Datagrab')
            ->filter('method', 'run_endpoint')
            ->first();

        if (!$action) {
            return '';
        }

        $siteIndex = ee()->functions->fetch_site_index(false, false);
        $additional = rtrim('&' . http_build_query($params, '', '&'), '&');

        return $siteIndex . '?ACT=' . $action->action_id . $additional;
    }

    protected function endpointSave(): bool | Endpoint
    {
        if (ee('Request')->post('validate') === 'n') {
            return true;
        }

        $endpointId = (int) ee('Request')->post('id');

        if ($endpointId) {
            /** @var Endpoint $endpoint */
            $endpoint = ee('Model')->get('datagrab:Endpoint')->filter('id', $endpointId)->first();
        } else {
            /** @var Endpoint $endpoint */
            $endpoint = ee('Model')->make('datagrab:Endpoint');
        }

        $endpoint->name = ee('Request')->post('name');
        $endpoint->import_id = ee('Request')->post('import_id');
        $endpoint->site_id = ee()->config->item('site_id');
        $endpoint->settings = json_encode([
            'auth_grid' => $this->miniGrid->cleanRowData(ee('Request')->post('auth_grid') ?? []),
        ]);
        $endpoint->auth_type = ee('Request')->post('auth_type');

        $result = $endpoint->validate();

        if ($result->failed()) {
            ee()->form_validation->_error_array = $result->renderErrors();

            return false;
        }

        $endpoint->save();

        return $endpoint;
    }

    protected function endpointForm(array $vars = [], int|null $endpointId = null): array
    {
        if (!empty($_POST)) {
            $endpoint = $this->endpointSave();

            if ($endpoint instanceof Endpoint) {
                $endpointId = $endpoint->id;

                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->cannotClose()
                    ->withTitle(lang('success'))
                    ->addToBody(sprintf('%s endpoint saved!', $endpoint->name))
                    ->now();
            }
        }

        $name = '';
        $importId = '';
        $authSettings = [];
        $authType = 'headers';

        // No ID parameter exists in the URL
        if ($endpointId === 0) {
            $this->endpointNotFound();
        }

        if ($endpointId) {
            $endpoint = $this->findEndpoint($endpointId);

            // ID parameter is in the URL but it's invalid
            if (!$endpoint) {
                $this->endpointNotFound();
            }

            if ($endpoint) {
                $name = $endpoint->name;
                $importId = $endpoint->import_id;
                $authSettings = $endpoint->settings ? json_decode($endpoint->settings, true) : [];
                $authType = $endpoint->auth_type;

                $vars['form_hidden'] = [
                    'id' => $endpoint->id,
                ];
            }
        }

        $grid = $this->miniGrid->makeAuthSettingsGrid($authSettings);

        $vars['sections'] = [
            [
                [
                    'title'  => 'name',
                    'desc'   => lang('alphadash_desc'),
                    'fields' => [
                        'name' => [
                            'type'      => 'text',
                            'value'     => $name,
                            'required'  => true,
                            'attrs'     => 'spellcheck="false"'
                        ],
                    ],
                ],
            ],
            [
                [
                    'title'  => 'dg_ep_import',
                    'desc'   => 'dg_ep_import_desc',
                    'fields' => [
                        'import_id' => [
                            'type'      => 'dropdown',
                            'value'     => $importId,
                            'required'  => true,
                            'choices'   => $this->getImportsAsChoices()
                        ],
                    ],
                ],
            ],
            [
                [
                    'title'  => 'dg_ep_auth_type',
                    'desc'   => 'dg_ep_auth_type_desc',
                    'fields' => [
                        'auth_type' => [
                            'type'      => 'dropdown',
                            'value'     => $authType ?: 'headers',
                            'choices'   => ['headers' => 'HTTP Headers', 'get' => 'GET parameters']
                        ],
                    ],
                ],
            ],
            [
                [
                    'title'  => 'dg_ep_auth',
                    'desc'   => 'dg_ep_auth_description',
                    'fields' => [
                        'import_id' => [
                            'type'      => 'html',
                            'content'   => '<div class="fieldset-faux" style="margin-top: 1em">' . ee('View')->make('_shared/table')->render($grid->viewData()) . '</div>',
                        ],
                    ],
                ],
            ],
        ];

        return $vars;
    }

    private function endpointNotFound()
    {
        ee('CP/Alert')->makeInline('shared-form')
            ->asIssue()
            ->cannotClose()
            ->withTitle(lang('error'))
            ->addToBody('The requested endpoint does not exist.')
            ->defer();

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/datagrab/endpoints')->compile());
    }
}
