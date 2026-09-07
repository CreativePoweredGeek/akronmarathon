<?php

namespace BoldMinded\Speedy\Commands\Support;

/**
 * A minimal stand-in for EE_URI used by the speedy:generate command.
 *
 * Speedy derives a cache item's key/URL from ee()->uri->uri_string(). From the
 * CLI there is no request URI, so we feed it a synthetic path per generated item
 * (e.g. "speedy-generate/item-42") to give every item a distinct cache key.
 */
class UriSimulator
{
    /**
     * Mirrors EE_URI::$uri_string. EE core reads this both as a property
     * (e.g. EE_Functions::fetch_current_uri) and via the uri_string() method,
     * so we expose both and keep them in sync.
     *
     * @var string
     */
    public $uri_string = '';

    /** @var array Mirrors EE_URI::$segments */
    public $segments = [];

    /** @var array Mirrors EE_URI::$rsegments */
    public $rsegments = [];

    public function setUriString(string $uriString): self
    {
        $this->uri_string = trim($uriString, '/');
        $this->segments = $this->uri_string === '' ? [] : explode('/', $this->uri_string);
        $this->rsegments = $this->segments;

        return $this;
    }

    /**
     * Mirrors EE_URI::segment_array().
     */
    public function segment_array()
    {
        return $this->segments;
    }

    /**
     * Mirrors EE_URI::rsegment_array().
     */
    public function rsegment_array()
    {
        return $this->rsegments;
    }

    /**
     * Mirrors EE_URI::uri_string().
     */
    public function uri_string()
    {
        return $this->uri_string;
    }

    /**
     * Mirrors EE_URI::segment(). Generated content has no {segment_N} variables,
     * so this only needs to exist for the parse path; return the no-result value.
     */
    public function segment($n, $no_result = false)
    {
        $segments = explode('/', $this->uri_string);

        return $segments[$n - 1] ?? $no_result;
    }

    /**
     * Absorb any other EE_URI method the parser calls; return an empty string,
     * a safe default for the string-context uses in template parsing.
     */
    public function __call($name, $arguments)
    {
        return '';
    }

    public function __get($name)
    {
        return '';
    }
}
