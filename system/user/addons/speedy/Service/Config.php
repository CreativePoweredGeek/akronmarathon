<?php

namespace BoldMinded\Speedy\Service;

class Config
{
    /**
     * @param string $name
     * @return mixed
     */
    public static function getItem($name)
    {
        return ee()->config->item($name);
    }

    /**
     * @return string
     */
    public static function getSiteCachePath()
    {
        if (self::getItem('speedy_static_path') !== false) {
            $cachePath = rtrim(ee()->config->item('speedy_static_path'), '/');
        }

        if (empty($cachePath)) {
            $cachePath = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : FCPATH;
            $cachePath = rtrim($cachePath, '/') . '/static';
        }

        return $cachePath . '/' . self::getItem('site_short_name');
    }
}
