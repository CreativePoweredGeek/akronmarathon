<?php

namespace BoldMinded\DataGrab\Service;

use BoldMinded\DataGrab\FieldTypes\AbstractFieldType;
use AbstractModule;
use Datagrab_default;

class DataGrabLoader
{
    /**
     * @return void
     */
    private function loadDataGrabFieldType()/*: void */
    {
        if (!class_exists('Datagrab_default')) {
            require_once sprintf('%sdatagrab/fieldtypes/datagrab_default.php', PATH_THIRD);
        }
    }

    /**
     * @param string $fieldType
     * @return string
     */
    private function getFieldTypePath(string $fieldType): string
    {
        // If someone want's to hi-jack and make their own ft handler...
        $thirdPartyPath = sprintf('%sdatagrab_fieldtypes/datagrab_%s.php', PATH_THIRD, $fieldType);

        if (file_exists($thirdPartyPath)) {
            return $thirdPartyPath;
        }

        $nativePath = sprintf('%sdatagrab/fieldtypes/datagrab_%s.php', PATH_THIRD, $fieldType);

        if (file_exists($nativePath)) {
            return $nativePath;
        }

        return '';
    }

    /**
     * @param string $fieldType
     * @param bool   $useDefault
     * @return AbstractFieldType
     */
    public function loadFieldTypeHandler(string $fieldType, bool $useDefault = false)/*: ?AbstractFieldType for backwards compatibility, for awhile. */
    {
        $this->loadDataGrabFieldType();

        $className = 'Datagrab_' . $fieldType;

        // Some fieldtypes are nearly identical to others
        $dependencies = [
            'file_grid' => [
                'grid'
            ],
            'checkboxes' => [
                'multi_select'
            ],
        ];

        foreach ($dependencies as $dependencyType => $dependencyList) {
            if ($fieldType === $dependencyType) {
                foreach ($dependencyList as $dependency) {
                    $dependencyPath = $this->getFieldTypePath($dependency);
                    if ($dependencyPath) {
                        require_once $dependencyPath;
                    }
                }
            }
        }

        $fieldTypePath = $this->getFieldTypePath($fieldType);

        if ($fieldTypePath) {
            require_once $fieldTypePath;
            return new $className();
        }

        $defaultPath = $this->getFieldTypePath('default');

        if ($useDefault && $defaultPath) {
            require_once $defaultPath;
            return new Datagrab_default();
        }

        return null;
    }

    /**
     * @return void
     */
    private function loadDataGrabModule()/*: void */
    {
        if (!class_exists('ModuleInterface')) {
            require_once sprintf('%sdatagrab/modules/ModuleInterface.php', PATH_THIRD);
        }

        if (!class_exists('AbstractModule')) {
            require_once sprintf('%sdatagrab/modules/AbstractModule.php', PATH_THIRD);
        }
    }

    /**
     * @param string $moduleName
     * @return string
     */
    private function getModulePath(string $moduleName): string
    {
        // If someone want's to hi-jack and make their own module handler...
        $thirdPartyPath = sprintf('%sdatagrab_modules/DataGrab%s.php', PATH_THIRD, $moduleName);

        if (file_exists($thirdPartyPath)) {
            return $thirdPartyPath;
        }

        $nativePath = sprintf('%sdatagrab/modules/DataGrab%s.php', PATH_THIRD, $moduleName);

        if (file_exists($nativePath)) {
            return $nativePath;
        }

        return '';
    }

    /**
     * @param string $fieldType
     * @return AbstractModule
     */
    public function loadModuleHandler(string $moduleName)/*: ?AbstractFieldType for backwards compatibility, for awhile. */
    {
        $this->loadDataGrabModule();

        $className = sprintf('DataGrab%s', $moduleName);
        $fieldTypePath = $this->getModulePath($moduleName);

        if ($fieldTypePath) {
            require_once $fieldTypePath;
            return new $className();
        }

        return null;
    }

    public function fetchModuleHandlers(): array
    {
        if (!function_exists('directory_map')) {
            require_once APPPATH . 'helpers/directory_helper.php';
        }

        $files = directory_map(PATH_THIRD . 'datagrab/modules');
        $handlers = [];

        foreach ($files as $file) {
            if (preg_match('/DataGrab(.*?)\.php/', $file, $matches)) {
                /** @var AbstractModule $handler */
                $handler = $this->loadModuleHandler($matches[1]);
                if ($handler->isInstalled()) {
                    $handlers[] = $handler;
                }
            }
        }

        return $handlers;
    }
}
