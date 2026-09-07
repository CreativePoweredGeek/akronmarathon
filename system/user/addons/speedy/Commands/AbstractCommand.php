<?php

namespace BoldMinded\Speedy\Commands;

use ExpressionEngine\Cli\Cli;

class AbstractCommand extends Cli
{
    protected function checkPath()
    {
        if (
            config_item('speedy_driver') === 'static' &&
            !config_item('speedy_static_path')
        ) {
            $this->output->outln('<<red>>For best results make sure your "speedy_static_path" config value is set. For example, $config[\'speedy_static_path\'] = \'/var/www/site/static/\';<<reset>>');
        }
    }
}
