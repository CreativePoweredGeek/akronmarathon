<?php

namespace BoldMinded\DataGrab\ControlPanel;

class ModalController
{
    /**
     * @param $name
     * @param $template
     * @param $vars
     * @param bool $autoOpen
     */
    public function create($name = '', $template = '', $vars = array(), $autoOpen = false)
    {
        if (substr($template, 0, 3) != 'ee:') {
            $template = 'bloqs:' . $template;
        }

        ee('CP/Modal')->addModal($name, ee('View')
            ->make($template)
            ->render(array_merge($vars, [
                'name' => $name
            ]))
        );

        if ($autoOpen) {
            ee()->cp->add_to_foot('<script type="text/javascript">$(function(){ $(".'. $name .'").trigger("modal:open"); });</script>');
        }
    }
}
