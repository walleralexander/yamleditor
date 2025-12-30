<?php
/**
 * Konfigurationsdatei für den YAML/MD Editor
 */

// .env Datei laden
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!getenv($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

// Verzeichnis mit den zu editierenden Dateien
$filesDir = getenv('FILES_DIR') ?: 'data/files';
if ($filesDir[0] === '/') {
    define('FILES_DIR', $filesDir);
} else {
    define('FILES_DIR', __DIR__ . '/../' . $filesDir);
}

// Datenbank-Pfad
$dbPath = getenv('DB_PATH') ?: 'database/users.db';
if ($dbPath[0] === '/') {
    define('DB_PATH', $dbPath);
} else {
    define('DB_PATH', __DIR__ . '/../' . $dbPath);
}

// Erlaubte Dateiendungen
define('ALLOWED_EXTENSIONS', ['yaml', 'yml', 'md', 'markdown']);

// Session-Einstellungen
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 3600));

// Sicherheitseinstellungen
define('PASSWORD_MIN_LENGTH', (int)(getenv('PASSWORD_MIN_LENGTH') ?: 8));

// Admin-Einstellungen
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');
