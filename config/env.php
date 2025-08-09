<?php
// Environment configuration loader
class Environment {
    private static $config = [];
    private static $loaded = false;
    
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }
        
        $envFile = $envFile ?: __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            // Fallback to development defaults if .env doesn't exist
            self::loadDefaults();
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            
            // Skip lines that don't contain '=' to prevent fatal errors
            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Skip empty variable names
            if (empty($name)) {
                continue;
            }
            
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            self::$config[$name] = $value;
            
            // Also set as environment variable
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    private static function loadDefaults() {
        // Development defaults - NEVER use these in production
        self::$config = [
            'DB_HOST' => 'localhost',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_NAME' => 'users_db',
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost',
            'SESSION_SECURE' => 'false',
            'SESSION_HTTPONLY' => 'true',
            'SESSION_SAMESITE' => 'Lax',
            'CORS_ALLOWED_ORIGINS' => 'http://localhost,http://127.0.0.1'
        ];
        self::$loaded = true;
    }
    
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }
    
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }
    
    public static function isDebug() {
        return self::get('APP_DEBUG') === 'true';
    }
}

// Load environment on include
Environment::load();
?>
