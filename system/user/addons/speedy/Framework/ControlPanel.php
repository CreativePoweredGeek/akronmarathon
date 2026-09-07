<?php

namespace BoldMinded\Speedy\Framework;

use ExpressionEngine\Library\CP\Table;
use ExpressionEngine\Library\CP\URL;
use ExpressionEngine\Service\Alert\Alert;
use ExpressionEngine\Service\Sidebar\Sidebar;

class ControlPanel
{
    protected string $moduleName;
    protected string $heading;
    protected array $breadcrumbs = [];

    public function __construct(string $moduleName = '')
    {
        $this->moduleName = $moduleName;
    }

    protected function makeUrl(string $action = '', array $params = []): URL
    {
        $path = 'addons/settings/' . $this->moduleName . '/' . $action;
        $path = rtrim($path, '/');

        return ee('CP/URL')->make($path, $params);
    }

    protected function setHeading(string $title): void
    {
        $this->heading = lang($title);
        ee()->view->header = ['title' => $this->heading];
    }

    protected function addBreadcrumb(string $title, string $url = ''): void
    {
        $this->breadcrumbs[(string) $url] = lang($title);
    }

    protected function render(string $view, array $data = []): array
    {
        if ($this->heading && empty($data['cp_page_title'])) {
            $data['cp_page_title'] = $this->heading;
        }

        return [
            'heading'    => $this->heading,
            'breadcrumb' => $this->breadcrumbs,
            'body'       => $this->renderView($view, $data),
        ];
    }

    protected function renderView(string $view, array $data): string
    {
        return ee('View')->make($this->moduleName . ':' . $view)->render($data);
    }

    protected function makeSidebar(): Sidebar
    {
        return ee('CP/Sidebar')->make();
    }

    protected function makeTable(array $data, array $columns, array $tableOptions = ['sortable' => false, 'limit' => 25]): Table
    {
        $table = ee('CP/Table', $tableOptions);
        $table->setColumns($columns);
        $table->setData($data);

        return $table;
    }

    protected function addInlineAlert(string $name = 'shared-form'): Alert
    {
        return ee('CP/Alert')->makeInline($name);
    }

    protected function addModal(string $name, string $html): void
    {
        ee('CP/Modal')->addModal($name, $html);
    }
}
