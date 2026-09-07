<?php

namespace BoldMinded\Speedy\Widgets;

use ExpressionEngine\Addons\Pro\Service\Dashboard\AbstractDashboardWidget;
use ExpressionEngine\Addons\Pro\Service\Dashboard\DashboardWidgetInterface;

class Actions extends AbstractDashboardWidget implements DashboardWidgetInterface {

    public $width = 'half'; //optional, if you want full width widget

    public function getTitle(): string
    {
        return 'Speedy Clear All Cache';
    }

    public function getRightHead(): string
    {
        $url = ee('CP/URL')->make('addons/settings/speedy');

        return sprintf('<a href="%s" class="button button--default button--small">Speedy Settings</a>', $url);
    }

    public function getContent(): string
    {
        $clearAllUrl = ee('CP/URL')->make('addons/settings//speedy/flush_all');

        return ee('View')->make('speedy:widgets/actions')->render([
            'clearAllUrl' => $clearAllUrl,
        ]);
    }
}
