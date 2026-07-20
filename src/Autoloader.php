<?php

namespace QNBPay;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal PSR-4 autoloader for the QNBPay\ namespace.
 *
 * Deliberately dependency-free so the plugin never relies on a Composer-built
 * vendor/ directory at runtime.
 *
 * @since 2.0.0
 */
final class Autoloader
{
    /** @var string Absolute path to the base source directory (with trailing slash). */
    private static $baseDir = '';

    /** @var string Namespace prefix handled by this autoloader. */
    private static $prefix = 'QNBPay\\';

    /** @var bool Whether the autoloader has been registered. */
    private static $registered = false;

    /**
     * Register the autoloader with SPL.
     *
     * @param string $baseDir Absolute path to the src/ directory.
     * @return void
     */
    public static function register($baseDir)
    {
        self::$baseDir = rtrim(str_replace('\\', '/', (string) $baseDir), '/') . '/';

        if (self::$registered) {
            return;
        }

        spl_autoload_register([__CLASS__, 'load']);
        self::$registered = true;
    }

    /**
     * Attempt to load a class from the QNBPay\ namespace.
     *
     * @param string $class Fully-qualified class name.
     * @return void
     */
    public static function load($class)
    {
        if (strncmp($class, self::$prefix, strlen(self::$prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen(self::$prefix));
        $relative = str_replace('\\', '/', $relative);
        $file = self::$baseDir . $relative . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
}
