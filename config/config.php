<?php
/**
 * Konfigurationsdatei für den YAML/MD Editor
 */

// Verzeichnis mit den zu editierenden Dateien
define('FILES_DIR', __DIR__ . '/../data/files');

// Datenbank-Pfad
define('DB_PATH', __DIR__ . '/../database/users.db');

// Erlaubte Dateiendungen
define('ALLOWED_EXTENSIONS', ['yaml', 'yml', 'md', 'markdown']);

// Session-Einstellungen
define('SESSION_LIFETIME', 3600); // 1 Stunde

// Sicherheitseinstellungen
define('PASSWORD_MIN_LENGTH', 8);
