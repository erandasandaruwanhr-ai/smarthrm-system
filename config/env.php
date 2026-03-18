<?php
/**
 * Environment Configuration Loader for SmartHRM
 * Loads environment variables from .env file
 */

class Environment {
    private static $variables = [];
    private static $loaded = false;

    /**
     * Load environment variables from .env file
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path)) {
            // If .env file doesn't exist, use defaults
            self::setDefaults();
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                // Set environment variable
                self::$variables[$key] = $value;

                // Also set as $_ENV and putenv for compatibility
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        // Check in order: custom variables, $_ENV, getenv
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Set environment variable
     */
    public static function set($key, $value) {
        self::$variables[$key] = $value;
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }

    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$variables[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }

    /**
     * Get all environment variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }

        return array_merge($_ENV, self::$variables);
    }

    /**
     * Set default values when .env file doesn't exist
     */
    private static function setDefaults() {
        $defaults = [
            'APP_NAME' => 'SmartHRM System',
            'APP_VERSION' => '1.0.0',
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'APP_TIMEZONE' => 'Asia/Colombo',
            'BASE_URL' => 'http://localhost/pbpictures/smarthrmjiffy/',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'smarthrm_db',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'SESSION_TIMEOUT' => '3600',
            'MAX_FILE_SIZE' => '10485760',
            'UPLOAD_DIR' => 'uploads/',
            'RECORDS_PER_PAGE' => '25',
        ];

        foreach ($defaults as $key => $value) {
            self::$variables[$key] = $value;
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    /**
     * Helper function for boolean values
     */
    public static function getBool($key, $default = false) {
        $value = strtolower(self::get($key, $default));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    /**
     * Helper function for integer values
     */
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }

    /**
     * Helper function for array values (comma-separated)
     */
    public static function getArray($key, $default = []) {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return array_map('trim', explode(',', $value));
    }
}

// Auto-load environment variables when this file is included
Environment::load();
?>