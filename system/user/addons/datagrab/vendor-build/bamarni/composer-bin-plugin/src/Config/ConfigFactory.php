<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Bamarni\Composer\Bin\Config;

use BoldMinded\DataGrab\Dependency\Composer\Config as ComposerConfig;
use BoldMinded\DataGrab\Dependency\Composer\Factory;
use BoldMinded\DataGrab\Dependency\Composer\Json\JsonFile;
use BoldMinded\DataGrab\Dependency\Composer\Json\JsonValidationException;
use BoldMinded\DataGrab\Dependency\Seld\JsonLint\ParsingException;
final class ConfigFactory
{
    /**
     * @throws JsonValidationException
     * @throws ParsingException
     */
    public static function createConfig() : ComposerConfig
    {
        $config = Factory::createConfig();
        $file = new JsonFile(Factory::getComposerFile());
        if (!$file->exists()) {
            return $config;
        }
        $file->validateSchema(JsonFile::LAX_SCHEMA);
        $config->merge($file->read());
        return $config;
    }
    private function __construct()
    {
    }
}
