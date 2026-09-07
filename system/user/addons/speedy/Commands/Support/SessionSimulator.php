<?php

namespace BoldMinded\Speedy\Commands\Support;

/**
 * A lenient stand-in for EE_Session used by the speedy:generate command.
 *
 * Speedy's caching guards read ee()->session->userdata['member_id'], and the
 * fragment template parse reaches deeper into the session (cache(), getMember(),
 * assorted userdata keys). Rather than enumerate every member EE might touch,
 * this stub absorbs unknown method calls and property reads harmlessly so the
 * parser can run against plain-text content in a CLI context.
 */
class SessionSimulator
{
    /** @var LenientArray Mirrors EE_Session::$userdata; guest session by default. */
    public $userdata;

    /** @var array Mirrors EE_Session::$cache, a per-request key/value store. */
    public $cache = [];

    public function __construct()
    {
        $this->userdata = new LenientArray([
            'member_id' => 0,
            'group_id' => 0,
        ]);
    }

    /**
     * No-op. The Facade calls this when an object is registered as 'session';
     * we have no cookies to set in a CLI context.
     */
    public function setSessionCookies()
    {
    }

    /**
     * Mirrors EE_Session::cache().
     */
    public function cache($class, $key, $default = false)
    {
        return $this->cache[$class][$key] ?? $default;
    }

    /**
     * Mirrors EE_Session::set_cache().
     */
    public function set_cache($class, $key, $value)
    {
        $this->cache[$class][$key] = $value;

        return $this;
    }

    /**
     * Absorb any other EE_Session method the parser calls (getMember(), etc.).
     * Returns null, which the parser treats as "no data".
     */
    public function __call($name, $arguments)
    {
        return null;
    }

    /**
     * Return a harmless default for any unknown property read.
     */
    public function __get($name)
    {
        return null;
    }

    public function __isset($name)
    {
        return false;
    }
}
