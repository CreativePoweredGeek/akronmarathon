<?php

namespace BoldMinded\DataGrab\DataTypes;

use BoldMinded\DataGrab\Dependency\Cake\Utility\Hash;
use BoldMinded\DataGrab\Service\Importer;
use Exception;

/**
 * Datagrab Type Class
 *
 * Provides the basic methods to create an import type
 *
 * @package   DataGrab
 * @author    BoldMinded, LLC <support@boldminded.com>
 * @copyright Copyright (c) BoldMinded, LLC
 **/

abstract class AbstractDataType
{
    public string $type = '';
    public array $datatype_info = [];
    public array $settings = [];
    public array $errors = [];
    public array $warnings = [];
    public bool $isConfigMode = false;
    public array $items = [];
    public array $itemsFlat = [];
    public int $sub_item_ptr = 0;

    const INCLUDES_CHILDREN_LABEL = '[includes %d child]';
    const INCLUDES_CHILDREN_LABEL_PLURAL = '[includes %d children]';
    const PARENT_NODE_NAME = '__parent__';

    public function display_name(): string
    {
        return $this->datatype_info['name'] ?? 'n/a';
    }

    public function settings_form(array $values = []): array
    {
        return [['This data type has no additional settings.']];
    }

    function initialise(array|null $settings): void
    {
        if ($settings && isset($settings['datatype'])) {
            $this->settings = $settings['datatype'];
            $this->settings['importId'] = (int) ($settings['import']['id'] ?? 0);
        }
    }

    public function display_configuration(
        Importer $importer,
        string   $field_name,
        string   $field_label,
        string   $field_type,
        string   $field_required = '',
        array    $data = []
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
        return $this->items;
    }

    public function fetch(string $data = '')
    {
    }

    protected function curlFetch(string $url, int $importId = 0)
    {
        // Local filesystem paths are only honored for CP-configured imports, never
        // for request-supplied filenames (see Datagrab::run_action).
        if (substr($url, 0, 1) === '/') {
            $result = @file_get_contents($url);

            if ($result === false) {
                $error = error_get_last();

                $message = sprintf('file_get_contents failed for %s: No such file or directory', $url);

                if ($error && isset($error['message'])) {
                    $message = sprintf('file_get_contents failed for %s: %s', $url, $error['message']);
                }

                throw new Exception($message);
            }

            return $result;
        }

        $urlGuard = ee('datagrab:UrlGuard');
        $urlGuard->assertAllowed($url);

        $verifyPeerSetting = bool_config_item('datagrab_verify_peer');
        $followRedirectsSetting = ee()->config->item('datagrab_follow_redirects');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyPeerSetting ? 2 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeerSetting ? 1 : 0);
        // Redirects are followed manually below so each hop can be re-validated.
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

        // Provide 2 methods of setting custom headers when fetching remote files.
        // Allows setting authentication headers, etc.
        $customHeaders = ee()->config->item('datagrab_custom_headers') ?: [];

        if (ee()->extensions->active_hook('datagrab_custom_headers') === true) {
            $customHeaders = ee()->extensions->call('datagrab_custom_headers', $url, $importId, $customHeaders);
        }

        if (
            $customHeaders &&
            isset($customHeaders[$importId]) &&
            is_array($customHeaders[$importId]) &&
            count($customHeaders[$importId]) > 0
        ) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders[$importId]);
        }

        $maxRedirects = $followRedirectsSetting ? (int) (ee()->config->item('datagrab_max_redirects') ?: 5) : 0;
        $redirects = 0;

        do {
            $data = curl_exec($ch);

            $errorNo = curl_errno($ch);
            $errorMsg = curl_error($ch);
            $info = curl_getinfo($ch);
            $httpCode = $info['http_code'];

            $isRedirect = in_array($httpCode, [301, 302, 303, 307, 308], true) && !empty($info['redirect_url']);

            if (!$isRedirect || $redirects >= $maxRedirects) {
                break;
            }

            try {
                $urlGuard->assertAllowed($info['redirect_url']);
            } catch (Exception $exception) {
                curl_close($ch);
                throw $exception;
            }

            curl_setopt($ch, CURLOPT_URL, $info['redirect_url']);
            $redirects++;
        } while (true);

        curl_close($ch);

        if ($errorNo || $errorMsg) {
            $this->addError(sprintf('cURL error #%d: %s', $errorNo, $errorMsg));
        }

        if ($httpCode !== 200) {
            $this->addError(sprintf('cURL Request Error Code: %d', $httpCode));
        }

        return $data;
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

    public function get_value($values, $field)
    {
        return $values["datatype"][$field] ?? '';
    }

    public function getErrors(): array
    {
        return array_unique($this->errors);
    }

    public function addError(string $error = ''): void
    {
        $this->errors[] = $error;
    }

    public function getWarnings(): array
    {
        return array_unique($this->warnings);
    }

    public function addWarning(string $warning = ''): void
    {
        $this->warnings[] = $warning;
    }

    public function next()
    {
        $item = current($this->itemsFlat);
        next($this->itemsFlat);

        return $item;
    }

    public function fetch_columns(): array
    {
        try {
            $columns = $this->getUniqueColumns();

            foreach ($columns as $key => $value) {
                if ($value && strlen($value) > 32) {
                    $value = substr(htmlspecialchars($value), 0, 32) . '...';
                }

                if (is_array($value)) {
                    $value = '';
                }

                if ($value && str_starts_with($value, '[includes')) {
                    $columns[$key] = sprintf('%s - <b>%s</b>', $key, $value);
                } else {
                    $columns[$key] = sprintf('%s - eg, %s', $key, $value);
                }
            }
        } catch (\Error $error) {
            $this->addError($error->getMessage());
        }

        return $columns;
    }

    /**
     * When importing Grid data, or anything with repeated rows, we'll end up with possibly a very long
     * list of column names, e.g. something/0/title, something/1/title, something/2/title, but we don't
     * need every single column names, just the unique ones so we can configure what the import is looking for.
     *
     * @param string $columnName
     * @return bool
     */
    private function getUniqueColumns(): array
    {
        $uniqueKeys = [];

        foreach ($this->itemsFlat as $item) {
            foreach ($item as $key => $value) {
                $cleanedKey = preg_replace('/(?<=\/)\d+(?=\/)/', '{n}', $key);
                if (!isset($uniqueKeys[$cleanedKey])) {
                    $uniqueKeys[$cleanedKey] = $value;
                }
            }
        }

        return $uniqueKeys;
    }

    public function initialise_sub_item()
    {
        if ($this->datatype_info['allow_subloop'] !== true) {
            return false;
        }

        $this->sub_item_ptr = 0;

        return true;
    }

    public function prepare_item_key(string|null $key, string $replacement = '.{n}.'): string
    {
        if (!$key) {
            return '';
        }

        $key = str_replace('/{n}/', $replacement, $key);
        $key = str_replace('/', '.', $key);

        // extract() for some reason is hard coded to use .{n}. tokens
        return $key;
    }

    public function item_key_placeholders(string|null $key): string
    {
        if (!$key) {
            return '';
        }

        $key = preg_replace('/(?<=\/)\d+(?=\/)/', '{n}', $key);
        //$key = preg_replace('/\/(\d+)\//', '/{n}/', $key);
        //$key = preg_replace('/\.(\d+)\./', '/{n}/', $key);
        //$key = str_replace('.', '/', $key);

        return $key;
    }

    public function check_item_key(array $item, string $id)
    {
        return Hash::check($item, $id);
    }

    public function extract(array $item, string $id): array
    {
        return Hash::extract($item, $id);
    }

    public function expand(array $item, string $separator = '/'): array
    {
        return Hash::expand($item, $separator);
    }

    public function get_item(array $item, string $key): string
    {
        if (empty($item)) {
            return '';
        }

        $preparedKey = $this->prepare_item_key($key);
        $expandedItem = Hash::expand($item, '/');

        if ($preparedKey && $this->check_item_key($expandedItem, $preparedKey)) {
            return trim(stripcslashes(current(Hash::extract($expandedItem, $preparedKey))));
        }

        return '';
    }

    /**
     * Given a full importItem array of paths and values, find the parent path,
     * then find all the immediate children of that parent. Primarily used for
     * Grid fields inside of Fluid, but could be applied to anything else.
     * Requires the child field to have a main "value" setting that points to
     * a JSON or XML node that ends in __parent__ (which is added in the datatype
     * files when it detects more than 1 child for node)
     */
    public function extract_sub_items(array $items, string $key): array
    {
        $expandedItems = Hash::expand($items, '/');
        $parentPath = $this->findParentPath($key);

        $parentPathParts = explode('.', $parentPath);
        array_pop($parentPathParts);
        $parentPath = implode('.', $parentPathParts);

        $extractedItems = Hash::extract($expandedItems, $parentPath);
        $flattenedItems = Hash::flatten($extractedItems);
        $flattenedSubItems = [];

        foreach ($flattenedItems as $index => $value) {
            if (str_contains($index, '__parent__')) {
                continue;
            }

            $newKey = $parentPath . '.' . $index;
            $newKey = $this->pathDotToSlash($newKey);
            $flattenedSubItems[$newKey] = $value;
        }

        return $flattenedSubItems;
    }

    private function pathDotToSlash(string $path): string
    {
        return preg_replace('/\.(\d+)\./', '/$1/', $path);
    }

    private function findParentPath(string $path): string {
        $preparedPath = preg_replace('/\/(\d+)\//', '.$1.', $path);
        $preparedPath = str_replace('/' . self::PARENT_NODE_NAME, '', $preparedPath);

        if (preg_match('/^(.*)\.(\d+)(\D.*|$)/', $preparedPath, $matches)) {
            return $matches[1] . '.' . ((int) $matches[2] + 1);
        }

        return $path;
    }

    /**
     * Last 3 parameters are present for consistency with legacy imports.
     * Once the _legacy imports are removed, this can be cleaned up.
     */
    public function get_sub_item(
        array $item,
        string $key,
        array $config = [], // Deprecated
        string $field = '', // Deprecated
        array $column = [], // Deprecated
    ) {
        if ($this->sub_item_ptr == -1) {
            return false;
        }

        if ($key) {
            $expanded = Hash::expand($item, '/');
            $extracted = Hash::extract($expanded, $this->prepare_item_key($key));

            if (array_key_exists($this->sub_item_ptr, $extracted)) {
                $value = $extracted[$this->sub_item_ptr];
                $this->sub_item_ptr++;

                return $value;
            }
        }

        $this->sub_item_ptr = -1;

        return false;
    }
}
