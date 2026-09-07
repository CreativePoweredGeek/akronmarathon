<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Database\Eloquent\Casts;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Database\Eloquent\Castable;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\Stringable;
class AsStringable implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return \Illuminate\Contracts\Database\Eloquent\CastsAttributes<\Illuminate\Support\Stringable, string|\Stringable>
     */
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes
        {
            public function get($model, $key, $value, $attributes)
            {
                return isset($value) ? new Stringable($value) : null;
            }
            public function set($model, $key, $value, $attributes)
            {
                return isset($value) ? (string) $value : null;
            }
        };
    }
}
