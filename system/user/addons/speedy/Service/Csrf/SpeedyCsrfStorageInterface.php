<?php

namespace BoldMinded\Speedy\Service\Csrf;

/**
 * A clone of EE's native class, but with no EE dependencies
 */
interface SpeedyCsrfStorageInterface
{
    /**
     * Get the token expiration time
     *
     * @return int The token expiration in seconds
     */
    public function getExpiration();

    /**
     * Store a new token for the user
     *
     * @param string $token New token to store
     * @return void
     */
    public function storeToken($token);

    /**
     * Delete the user's current token
     *
     * @return void
     */
    public function deleteToken();

    /**
     * Fetch the user's token
     *
     * @return string Current user token
     */
    public function fetchToken();

    /**
     * @return void
     */
    public function refreshToken();
}
