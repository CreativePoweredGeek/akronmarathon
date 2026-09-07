<?php

namespace BoldMinded\Speedy\Service\Csrf;

/**
 * A clone of EE's native class, but with no EE dependencies
 */
class SpeedyCookie implements SpeedyCsrfStorageInterface
{
    const COOKIE_NAME = 'csrf_token';

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    private function getCookieName(): string
    {
        $prefix = $this->options['cookiePrefix'] ?? 'exp_';

        return $prefix . self::COOKIE_NAME;
    }

    /**
     * Get the expiration value
     *
     * @return int expiration in seconds
     */
    public function getExpiration()
    {
        return time() + 60 * 60 * 2; // 2 hours
    }

    /**
     * Set the token cookie
     *
     * @param string $token New token value
     * @return void
     */
    public function storeToken($token)
    {
        @setcookie($this->getCookieName(), $token, $this->getExpiration(), '/', '', true, true);
    }

    /**
     * Delete the token cookie
     *
     * @return void
     */
    public function deleteToken()
    {
        @setcookie($this->getCookieName(), '', time() - 86500);
    }

    /**
     * Fetch the current session token from the cookie.
     *
     * @return string Stored token
     */
    public function fetchToken()
    {
        return $_COOKIE[$this->getCookieName()] ?? '';
    }

    /**
     * Refresh the current token
     * @return void
     */
    public function refreshToken()
    {
        if ($token = $this->fetchToken()) {
            $this->storeToken($token);
        }
    }
}
