<?php

namespace BoldMinded\Speedy\Service\Csrf;

/**
 * A clone of EE's native class, but with no EE dependencies
 */
class SpeedyCsrf
{
    const REQUEST_METHOD = 'REQUEST_METHOD';
    const CSRF_TOKEN = 'csrf_token';
    const TOKEN_LENGTH = 40; // the token is always the sha1 of a random string

    /**
     * @var SpeedyCsrfStorageInterface
     */
    private $backend;

    private string $requestToken;
    private string $sessionToken;

    public function __construct(array $options = [])
    {
        $sessionId = $_SESSION['session_id'] ?? '';

        // One more attempt
        if (!$sessionId && isset($_COOKIE[$options['cookiePrefix'] . 'sessionid'])) {
            $sessionId = $_COOKIE[$options['cookiePrefix'] . 'sessionid'];
        }

        if (is_string($sessionId) && preg_match('/^[A-Za-z0-9]{'. self::TOKEN_LENGTH .'}$/', $sessionId)) {
            $this->backend = new SpeedyDatabase($options, $sessionId);
        } else {
            $this->backend = new SpeedyCookie($options);
        }
    }

    /**
     * Get the user's token
     *
     * This is used to insert a token into forms and ajax requests.
     *
     * @return string user csrf token
     */
    public function getUserToken(): string
    {
        return $this->fetchSessionToken();
    }

    /**
     * Access the csrf token timeout
     *
     * This can sometimes be useful to know when creating pages that may be open
     * for a very long time.
     *
     * @return int token timeout [0 = no timeout]
     */
    public function getExpiration(): int
    {
        return $this->backend->getExpiration();
    }

    /**
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return $this->checkToken();
    }

    /**
     * @return void
     */
    public function setTokenHeader()
    {
        // Retrieve the current token
        $token = $this->getUserToken();

        // Send the header and legacy header for ajax requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
            isset($_SERVER[self::REQUEST_METHOD]) &&
            $_SERVER[self::REQUEST_METHOD] === 'POST' &&
            ctype_alnum($token)
        ) {
            header('X-CSRF-TOKEN: '. $token);
        }
    }

    /**
     * Refresh the user's token
     *
     * This should generally be used any time you need to create a new token
     * for a user. Definitely call this on login and logout.
     *
     * @return string new token
     */
    public function refreshToken(): string
    {
        $token = sha1(uniqid(strval(rand(-PHP_INT_MAX, PHP_INT_MAX)), true));

        $this->backend->deleteToken();
        $this->backend->storeToken($token);

        return $token;
    }

    /**
     * Check the csrf token for this request
     *
     * @return bool True/False for a valid or invalid token, respectively
     */
    public function checkToken(): bool
    {
        $this->backend->refreshToken();

        // Exempt safe html methods (@see RFC2616)
        $safe = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

        if (in_array($_SERVER[self::REQUEST_METHOD], $safe)) {
            return true;
        }

        // Fetch data, these methods enforce token time limits
        $this->fetchSessionToken();
        $this->fetchRequestToken();

        // Main check
        if ($this->requestToken === $this->sessionToken) {
            return true;
        }

        return false;
    }

    /**
     * Fetch the current request's token.
     *
     * We check both headers and post for legacy and new fields. We also check
     * the token length to further limit the attacker's options.
     *
     * @return void
     */
    private function fetchRequestToken()
    {
        $token = $_POST[self::CSRF_TOKEN] ?? '';

        // Remove csrf information from the post array to make this feature
        // completely transparent to the developer.
        unset($_POST[self::CSRF_TOKEN]);

        if (!$this->tokenIsValidFormat($token)) {
            $token = '';
        }

        $this->requestToken = $token;
    }

    /**
     * Fetch the current session token from the storage backend.
     *
     * Will only return tokens that are within the valid token timeout. If
     * no token exists it will attempt to set one
     *
     * @return string Current user token as returned by the storage backend
     */
    private function fetchSessionToken(): string
    {
        if (!isset($this->sessionToken)) {
            $this->sessionToken = $this->backend->fetchToken();
        }

        if (!$this->tokenIsValidFormat($this->sessionToken)) {
            $this->sessionToken = $this->refreshToken();
        }

        return $this->sessionToken;
    }

    /**
     * Reject failed tokens or tokens that are bogus hashes
     *
     * @param string $token
     * @return bool
     */
    private function tokenIsValidFormat(string $token = ''): bool
    {
        if (empty($token) || strlen($token) !== self::TOKEN_LENGTH) {
            return false;
        }

        return preg_match('/^[a-f0-9]/', $token) === 1;
    }
}
