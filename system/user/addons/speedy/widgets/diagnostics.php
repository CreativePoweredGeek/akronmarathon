<?php

namespace BoldMinded\Speedy\Widgets;

use ExpressionEngine\Addons\Pro\Service\Dashboard\AbstractDashboardWidget;
use ExpressionEngine\Addons\Pro\Service\Dashboard\DashboardWidgetInterface;

class Diagnostics extends AbstractDashboardWidget implements DashboardWidgetInterface {

    public $width = 'half'; //optional, if you want full width widget

    public function getTitle(): string
    {
        return 'Speedy Cache Diagnostics';
    }

    public function getRightHead(): string
    {
        $url = ee('CP/URL')->make('addons/settings/speedy/diagnostics');

        return sprintf('<a href="%s" class="button button--default button--small">View All</a>', $url);
    }

    public function getContent(): string
    {
        $diagnostics = ee('Model')
            ->get('speedy:Diagnostics')
            ->order('execution_time', 'desc')
            ->limit(10)
            ->all()
        ;

        return ee('View')->make('speedy:widgets/diagnostics')->render([
            'diagnostics' => $diagnostics,
        ]);
    }
}
