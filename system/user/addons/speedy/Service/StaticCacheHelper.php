<?php

namespace BoldMinded\Speedy\Service;

use BoldMinded\Speedy\Service\Csrf\SpeedyCsrf;

class StaticCacheHelper
{
    const QUERY_DELIMITER = '+';

    /**
     * @var array
     */
    private $allowList;

    /**
     * @var SpeedyCsrf|null
     */
    private $csrf;

    /**
     * @var array
     */
    private $queryParameters;

    /**
     * @var array
     */
    private $options;

    /**
     * @param array           $allowList
     * @param array           $queryParameters
     * @param SpeedyCsrf|null $csrf
     * @param array           $options
     */
    public function __construct(
        array $allowList,
        array $queryParameters = [],
        SpeedyCsrf|null $csrf = null,
        array $options = []
    ) {
        $this->allowList = $allowList;
        $this->queryParameters = $queryParameters;
        $this->csrf = $csrf;
        $this->options = $options;
    }

    /**
     * Use the URL from the site's config file to determine if Frontedit is enabled.
     * We use a full action URL so we can avoid all EE specific code in these files,
     * but we still need to boot EE somehow and do the check, so curl the action
     * URL, which does boot EE, to get the response. Note that this will affect the
     * page load time and it might not be sub 100ms b/c of the EE boot up process.
     *
     * @return bool
     */
    public function isFrontEditEnabled(): bool
    {
        if (!$this->options['frontEditCheckUrl']) {
            return false;
        }

        $cookieName = $this->options['cookiePrefix'] . 'frontedit';

        // Users without CP access won't have this cookie set, so don't check to see if
        // frontedit enabled and give the user a cached response. Users with CP access will have the cookie
        // set, so we then make teh curl request to see if it's enabled.
        if (!isset($_COOKIE[$cookieName]) || (isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] !== 'on')) {
            return false;
        }

        $ch = @curl_init($this->options['frontEditCheckUrl']);

        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_URL, $this->options['frontEditCheckUrl']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, false);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);

        // Verify the TLS certificate and hostname by default. The response from
        // this request controls whether a logged-in editor is served cached or
        // live content, so an on-path attacker must not be able to MITM it.
        // Verification can only be disabled via an explicit, documented opt-in
        // ($config['speedy_frontedit_check_insecure'] = 'yes'), e.g. for a
        // self-signed certificate in development.
        $insecure = !empty($this->options['frontEditCheckInsecure']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$insecure);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $insecure ? 0 : 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cache-Control: no-cache']);

        if ($ch === false) {
            return false;
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result && ($response = @json_decode($result, true))) {
            return $response['response'] ?? false;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getQueryString(): string
    {
        $queryString = '';
        $uri = $this->getUri();

        if ($uri) {
            $queryString = $this->parseQueryString($uri);

            if ($this->isQueryCacheEnabled()) {
                $uri = $this->filterAllowlistedQueryParams($uri);
                // GET vars should be compressed into 1 value to be compatible with what CE Cache is doing
                $queryString = $this->parseQueryString($uri);
                $queryString = $this->sanitize($queryString, true);
            } elseif ($queryString) {
                // Set this as the real query string, in case there is a redirect
                $_SERVER['QUERY_STRING'] = $queryString;
                // Now set it to blank b/c its not a cacheable query string value
                $queryString = '';
            }
        }

        return $queryString;
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        $uri = '';

        if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])) {
            $uri = $_SERVER['REQUEST_URI'];

            if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
                $uri = str_replace($_SERVER['SCRIPT_NAME'], '', $uri);
            }
        }

        if (
            $uri === '/' ||
            substr($uri, 0, 2) === '/?' ||
            substr($uri, 0, 2) === '/#'
        ) {
            $uri = '/index';
        }

        return $uri;
    }

    /**
     * Encode/shorten the query string to avoid file or folder name length limits
     *
     * @return string
     */
    public function getQueryStringEncoded(): string
    {
        $queryString = $this->getQueryString();

        return $queryString ? md5($queryString) : '';
    }

    /**
     * Given an array, determine if query caching is enabled.
     *
     * @return bool
     */
    private function isQueryCacheEnabled(): bool
    {
        if (!empty($this->allowList)) {
            $diff = array_intersect($this->allowList, array_keys($this->queryParameters));

            if (empty($diff)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the GET parameters without the uri segments
     *
     * @param $uri
     * @return string|null
     */
    private function parseQueryString(string $uri = ''): string
    {
        $uriParts = explode('?', $uri);
        return $uriParts[1] ?? '';
    }

    /**
     * @param string $uri (actually... just the path and query string e.g. '/about?foo=bar&v=2')
     * @return string $uri with only allowlisted query parameters
     */
    public function filterAllowlistedQueryParams(string $uri = ''): string
    {
        $uriWithAllowListedQueryParameters = strtok($uri, '?');

        if (!$this->allowList || empty($this->allowList)) {
            return $uriWithAllowListedQueryParameters;
        }

        $filteredQueryParameters = array_intersect_key(
            $this->queryParameters,
            array_flip($this->allowList)
        );

        // alphabetize query params by key
        ksort($filteredQueryParameters);

        if ($filteredQueryParameters) {
            $uriWithAllowListedQueryParameters .= '?' . http_build_query($filteredQueryParameters);
        }

        return $uriWithAllowListedQueryParameters;
    }

    /**
     * Filename Security - Taken from EE core
     */
    public function sanitize(string $str = '', bool $relative = false): string
    {
        $bad = [
            "../","<!--","-->","<",">",
            "'",'"','&','$','#','{','}','[',']','=',';','?', "%20","%22",
            "%3c",   // <
            "%253c", // <
            "%3e",   // >
            "%0e",   // >
            "%28",   // (
            "%29",   // )
            "%2528", // (
            "%26",   // &
            "%24",   // $
            "%3f",   // ?
            "%3b",   // ;
            "%3d"    // =
        ];

        if (!$relative) {
            $bad[] = './';
            $bad[] = '/';
        }

        $str = $this->removeInvisibleCharacters($str);
        return stripslashes(str_replace($bad, '', $str));
    }

    /**
     * Remove Invisible Characters - Taken from EE core
     *
     * This prevents sandwiching null characters between ascii characters, like Java\0script.
     */
    private function removeInvisibleCharacters(string $str = ''): string
    {
        $nonDisplayables = [];

        // every control character except newline (dec 10)
        // carriage return (dec 13), and horizontal tab (dec 09)
        // and strip all RTL / LTR type markers
        $nonDisplayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127
        $nonDisplayables[] = '/[\x{202e}\x{202d}\x{200f}\x{200e}]/uS'; // RTLO 202e, LTRO 202d, RTL 200f, LTR 200e
        $nonDisplayables[] = '/&#(?:823[78]|820[67]);/'; // HTML entity versions of RTL/LTR markers

        do {
            $str = preg_replace($nonDisplayables, '', $str, -1, $count);
        }
        while ($count);

        return $str;
    }

    public function prepareContent(string $content = ''): string
    {
        $content = $this->replaceSpecialCharacters($content);
        $content = $this->replaceCsrfToken($content);

        $this->parseRedirectTag($content);

        if (isset($_GET['speedy_debug']) && defined('SPEEDY_STATIC_START')) {
            $content .= '<!-- [Speedy Debug] total load time - ' . ( microtime(true) - SPEEDY_STATIC_START ) . ' -->';
        }

        return $content;
    }

    public function prepareHtmlContent(string $content = ''): string
    {
        $content = $this->replaceCsrfToken($content);

        $this->parseRedirectTag($content);

        if (isset($_GET['speedy_debug']) && defined('SPEEDY_STATIC_START')) {
            $content .= '<!-- [Speedy Debug] total load time - ' . ( microtime(true) - SPEEDY_STATIC_START ) . ' -->';
        }

        return $content;
    }

    private function replaceSpecialCharacters(string $content = ''): string
    {
        return str_replace(['\\"', "\'", '\n'], ['"', "'", "\n"], $content);
    }

    private function parseRedirectTag(string $content = ''): void
    {
        // Check for a redirect tag
        if (strpos($content, '{redirect=') !== false) {
            // Don't have access to EE's parsing of this tag, so do it manually.
            preg_match("/\{redirect\s*=\s*(\042|\047)([^\\1]*?)\\1\}/si", $content, $match);
            $redirectMatch = preg_replace('/\'|"/', '', $match[2]);
            preg_match('/status_code=(\d+)/', $redirectMatch, $codeMatch);
            $statusCode = $codeMatch[1] ?? null;
            $redirect = trim(preg_replace('/status_code=(\d+)/', '', $redirectMatch));

            if ($redirect) {
                $this->redirectTo($redirect, $statusCode);
                exit();
            }
        }
    }

    /**
     * Attempt to replace CSRF_TOKEN fields in a cached file with a valid token for the current user. With this update
     * users can still submit a form from a cached page securely.
     */
    private function replaceCsrfToken(string $content= ''): string
    {
        $token =  $this->csrf->getUserToken();

        // Update any html attributes or query string variables
        $content = preg_replace(
            '/csrf_token={csrf_token}/um',
            'csrf_token='. $token,
            $content
        );

        // Update speedy_csrf_token variable if present in a template
        $content = preg_replace(
            '/{speedy_csrf_token}/um',
            $token,
            $content
        );

        // Update hidden form fields with the new token value.
        return preg_replace(
            '/<input type="hidden" name="csrf_token" value="{csrf_token}"\s?\/?>/um',
            '<input type="hidden" name="csrf_token" value="'. $token .'" data-updated="true" />',
            $content
        );
    }

    /**
     * @param $path
     * @param int|null $statusCode
     */
    public function redirectTo($path = null, $statusCode = null)
    {
        // If we already have a fully qualified domain, use it, and force https.
        if (strpos($path, 'http') !== false) {
            $url = str_replace('http://', 'https://', $path);
        } else {
            $url = 'https://'.$_SERVER['HTTP_HOST'];
            $url = rtrim($url, '/');

            if ($path) {
                $url .= ($path) ? '/' . trim($path, '/') : '';
            }
        }

        if ($statusCode == 301) {
            header("HTTP/1.1 301 Moved Permanently");
        }

        // Set no caching
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header('X-Redirect: Speedy StaticCacheHandler.php', false);
        header('Location: ' . $url);
        exit;
    }
}
