<?php
/**
 * Simple .env file loader
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^["\'](.+)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            // Set environment variable
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Get environment variable with optional default
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Load the .env file
loadEnv(__DIR__ . '/.env');
?>