<?php

use BoldMinded\DataGrab\Service\Importer;

interface ModuleInterface
{
    /**
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param Importer $importer
     * @param array          $data
     * @return array
     */
    public function displayConfiguration(Importer $importer, array $data = []): array;

    /**
     * @param Importer $importer
     * @return array
     */
    public function saveConfiguration(Importer $importer): array;

    /**
     * @param Importer $importer
     * @param array          $data
     * @param array          $item
     * @param array          $custom_fields
     * @param string         $action
     * @return mixed
     */
    public function handle(Importer $importer, array &$data = [], array $item = [], array $custom_fields = [], string $action = '');
}
