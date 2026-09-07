<?php

namespace BoldMinded\Speedy\Commands\Support;

use ArrayAccess;

/**
 * An array-like container that returns a default for missing keys instead of
 * emitting "Undefined array key" warnings.
 *
 * Used for the simulated session userdata: EE's template parser reads many
 * member/session keys (private_messages, total_forum_posts, mfa_enabled, ...)
 * that do not exist in a CLI context. This swallows those reads gracefully.
 */
class LenientArray implements ArrayAccess
{
    /** @var array */
    private $data;

    /** @var mixed Returned for any key that is not set. */
    private $default;

    public function __construct(array $data = [], $default = '')
    {
        $this->data = $data;
        $this->default = $default;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        // Report keys as existing so isset() checks in the parser take the
        // populated branch and then read a (defaulted) value via offsetGet.
        return true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? $this->default;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }
}
