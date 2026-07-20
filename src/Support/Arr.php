<?php

namespace QNBPay\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Array / data access helpers.
 *
 * Replaces the previous rappasoft/laravel-helpers `data_get()` global function
 * so the plugin no longer defines a global helper that can collide with other
 * plugins. All access is namespaced and null-safe for PHP 7.4 - 8.5.
 *
 * @since 2.0.0
 */
final class Arr
{
    /**
     * Retrieve a value from a nested array/object using "dot" notation.
     *
     * Mirrors the subset of Laravel's data_get() this plugin relies on.
     *
     * @param mixed        $target  Array or object to read from.
     * @param string|array $key     Key or dot-notation path (e.g. "cart.total").
     * @param mixed        $default Value returned when the key is not found.
     * @return mixed
     */
    public static function get($target, $key, $default = null)
    {
        if (null === $key || '' === $key) {
            return $target;
        }

        $segments = is_array($key) ? $key : explode('.', (string) $key);

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif ($target instanceof \ArrayAccess && isset($target[$segment])) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return $default instanceof \Closure ? $default() : $default;
            }
        }

        return $target;
    }

    /**
     * Null-safe string cast used throughout the request builders.
     *
     * Prevents PHP 8.1+ "passing null to non-nullable" deprecations when values
     * originate from unvalidated request data.
     *
     * @param mixed $value
     * @return string
     */
    public static function str($value)
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
