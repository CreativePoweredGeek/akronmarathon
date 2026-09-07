<?php

namespace BoldMinded\DataGrab\DataTypes;

abstract class AbstractDataTypeLegacy
{
    public $type = '';
    // @todo these should be private/protected with getters and setters
    public $settings = [];
    public $config_defaults = [];
    public $handle;
    public $titles;
    public $errors = [];
    public $isConfigMode = false;

    function display_name()
    {
        return $this->datatype_info["name"];
    }

    /**
     * @param array $values
     * @return array[]
     */
    public function settings_form(array $values = []): array
    {
        return [['This data type has no additional settings.']];
    }

    function initialise($settings)
    {
        if ($settings && isset($settings['datatype'])) {
            $this->settings = $settings['datatype'];
            $this->settings['importId'] = (int) ($settings['import']['id'] ?? 0);
        }
    }

    public function display_configuration(
        Importer $importer,
        string $field_name,
        string $field_label,
        string $field_type,
        string $field_required = '',
        array $data = []
    ): array
    {
        return [];
    }

    public function getFilename(): string
    {
        $fileName = $this->settings['filename'] ?? '';

        if (preg_match('/^\$(\w+)?/', $fileName)) {
            $fileName = env(substr($fileName, 1));
        }

        return reduce_double_slashes(str_replace(
            ['{base_url}', '{base_path}'],
            [ee()->config->item('base_url'), ee()->config->item('base_path')],
            $fileName
        ));
    }

    public function getItems(): array
    {
        return [];
    }

    public function fetch(string $data = '')
    {
    }

    protected function curlFetch(string $url, int $importId = 0)
    {
        try {
            ee('datagrab:UrlGuard')->assertAllowed($url);
        } catch (\Exception $exception) {
            $this->addError($exception->getMessage());
            return false;
        }

        $verifyPeer = bool_config_item('datagrab_verify_peer');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer ? 1 : 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

        $customHeaders = ee()->config->item('datagrab_custom_headers') ?: [];

        if (
            $customHeaders &&
            isset($customHeaders[$importId]) &&
            is_array($customHeaders[$importId]) &&
            count($customHeaders[$importId]) > 0
        ) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders[$importId]);
        }

        $data = curl_exec($ch);

        $errorNo = curl_errno($ch);
        $errorMsg = curl_error($ch);

        curl_close($ch);

        $info = curl_getinfo($ch);
        $httpCode = $info['http_code'];

        if ($errorNo || $errorMsg) {
            $this->addError(sprintf('cURL error #%d: %s', $errorNo, $errorMsg));
        }

        if ($httpCode !== 200) {
            $this->addError(sprintf('cURL Request Error Code: %d', $httpCode));
        }

        return $data;
    }

    public function next()
    {
        return false;
    }

    public function fetch_columns(): array
    {
        return [];
    }

    public function total_rows()
    {
        $count = 0;
        while ($this->next()) {
            $count++;
        }
        return $count;
    }

    public function total_rows_real()
    {
        return count($this->items) ?? 0;
    }

    public function clean_up($entries, $settings)
    {
    }

    public function get_item(array $item, string $id): string
    {
        if (isset($item[$id])) {
            return trim(stripcslashes($item[$id]));
        }

        return '';
    }

    public function get_value($values, $field)
    {
        return $values["datatype"][$field] ?? '';
    }

    public function initialise_sub_item()
    {
        return false;
    }

    public function get_sub_item(array $item, string $key, array $config = [], string $field = '', array $column = [])
    {
        return $item;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return array_unique($this->errors);
    }

    /**
     * @param string $error
     * @return void
     */
    public function addError(string $error = '')
    {
        $this->errors[] = $error;
    }

    // Unsupported in legacy imports
    public function getWarnings(): array
    {
        return [];
    }

    // Unsupported in legacy imports
    public function addWarning(string $warning = ''): void
    {
    }
}
