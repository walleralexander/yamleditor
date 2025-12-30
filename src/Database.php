<?php
/**
 * Datenbank-Klasse für SQLite-Verbindung
 */

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbPath = DB_PATH;
            $dbDir = dirname($dbPath);

            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            self::$instance = new PDO('sqlite:' . $dbPath);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::initTables();
        }
        return self::$instance;
    }

    private static function initTables(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";

        self::$instance->exec($sql);

        // Prüfen ob Admin-User existiert, sonst erstellen
        $adminUsername = defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin';
        $adminPassword = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'admin123';

        $stmt = self::$instance->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $stmt->execute([$adminUsername]);
        $result = $stmt->fetch();

        if ($result['count'] == 0) {
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = self::$instance->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$adminUsername, $hashedPassword, 'admin']);
        }
    }
}
