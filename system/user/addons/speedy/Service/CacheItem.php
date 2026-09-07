<?php

namespace BoldMinded\Speedy\Service;

final class CacheItem
{
    const INVALID_CHAR_LIST = '{}()|\@:';

    /** @var string */
    private $key;

    /** @var bool */
    private $hit = false;

    /** @var int */
    private $ttl = 0;

    /** @var string */
    private $value;

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        self::validateKey($key);

        $this->key = trim($key);

        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isHit()
    {
        return $this->hit;
    }

    /**
     * @param $hit
     * @return $this
     */
    public function setHit($hit)
    {
        $this->hit = $hit;

        return $this;
    }

    /**
     * @return int
     */
    public function getTTL()
    {
        return $this->ttl;
    }

    /**
     * @param int $ttl
     */
    public function setTTL($ttl)
    {
        $this->ttl = $ttl;
    }

    /**
     * @param mixed $key
     */
    public static function validateKey($key)
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException('[Speedy] The cache key must be a string.');
        }
        if (strlen($key) <= 1) {
            throw new \InvalidArgumentException('[Speedy] The cache key must be at least 1 character.');
        }
        if (strpos($key, "\0") !== false) {
            throw new \InvalidArgumentException('[Speedy] The cache key contains a null byte.');
        }
        if (strpos($key, '\\') !== false) {
            throw new \InvalidArgumentException('[Speedy] The cache key contains a backslash.');
        }
        if ($key[0] === '/') {
            throw new \InvalidArgumentException('[Speedy] The cache key must not be an absolute path.');
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $key)) {
            throw new \InvalidArgumentException('[Speedy] The cache key must not contain a parent-directory ("..") segment.');
        }

        if (!self::isKeyRegex($key) && strpbrk($key, self::INVALID_CHAR_LIST) !== false) {
            throw new \InvalidArgumentException(sprintf('[Speedy] The cache key contains one or more of the following invalid characters: %s.', self::INVALID_CHAR_LIST));
        }
    }

    public static function isKeyRegex(string $key): bool
    {
        return strpos($key, '^') !== false;
    }

    public static function removeRegexIndicator(string $key): string
    {
        if (self::isKeyRegex($key)) {
            return str_replace('^', '', $key);
        }

        return $key;
    }
}
