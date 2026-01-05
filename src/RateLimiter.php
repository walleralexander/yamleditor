<?php
/**
 * Rate Limiter für Login-Versuche
 * Speichert fehlgeschlagene Versuche in SQLite
 */

require_once __DIR__ . '/Database.php';

class RateLimiter
{
    private PDO $db;
    private int $maxAttempts;
    private int $lockoutTime;

    public function __construct(int $maxAttempts = 5, int $lockoutTime = 900)
    {
        $this->db = Database::getInstance();
        $this->maxAttempts = $maxAttempts;
        $this->lockoutTime = $lockoutTime; // 15 Minuten in Sekunden
        $this->initTable();
    }

    /**
     * Tabelle für Login-Versuche erstellen
     */
    private function initTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                username TEXT,
                attempt_time INTEGER NOT NULL,
                success INTEGER DEFAULT 0
            )
        ");

        // Index für schnelle Abfragen
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip_address, attempt_time)");
    }

    /**
     * IP-Adresse des Clients ermitteln
     */
    public function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Proxy-Header berücksichtigen (nur wenn vertrauenswürdig)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }

        return $ip;
    }

    /**
     * Prüfen ob IP gesperrt ist
     */
    public function isBlocked(?string $username = null): bool
    {
        $ip = $this->getClientIp();
        $since = time() - $this->lockoutTime;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts
            FROM login_attempts
            WHERE ip_address = :ip
            AND attempt_time > :since
            AND success = 0
        ");
        $stmt->execute(['ip' => $ip, 'since' => $since]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['attempts'] >= $this->maxAttempts;
    }

    /**
     * Verbleibende Sperrzeit in Sekunden
     */
    public function getRemainingLockoutTime(): int
    {
        $ip = $this->getClientIp();
        $since = time() - $this->lockoutTime;

        $stmt = $this->db->prepare("
            SELECT MAX(attempt_time) as last_attempt
            FROM login_attempts
            WHERE ip_address = :ip
            AND attempt_time > :since
            AND success = 0
        ");
        $stmt->execute(['ip' => $ip, 'since' => $since]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['last_attempt']) {
            $unlockTime = $result['last_attempt'] + $this->lockoutTime;
            return max(0, $unlockTime - time());
        }

        return 0;
    }

    /**
     * Fehlgeschlagenen Login-Versuch aufzeichnen
     */
    public function recordFailedAttempt(?string $username = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, username, attempt_time, success)
            VALUES (:ip, :username, :time, 0)
        ");
        $stmt->execute([
            'ip' => $this->getClientIp(),
            'username' => $username,
            'time' => time()
        ]);
    }

    /**
     * Erfolgreichen Login aufzeichnen und alte Versuche löschen
     */
    public function recordSuccessfulLogin(?string $username = null): void
    {
        $ip = $this->getClientIp();

        // Erfolg aufzeichnen
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, username, attempt_time, success)
            VALUES (:ip, :username, :time, 1)
        ");
        $stmt->execute([
            'ip' => $ip,
            'username' => $username,
            'time' => time()
        ]);

        // Fehlgeschlagene Versuche für diese IP löschen
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts
            WHERE ip_address = :ip AND success = 0
        ");
        $stmt->execute(['ip' => $ip]);
    }

    /**
     * Alte Einträge bereinigen (älter als 24 Stunden)
     */
    public function cleanup(): void
    {
        $cutoff = time() - 86400; // 24 Stunden

        $stmt = $this->db->prepare("
            DELETE FROM login_attempts WHERE attempt_time < :cutoff
        ");
        $stmt->execute(['cutoff' => $cutoff]);
    }

    /**
     * Anzahl verbleibender Versuche
     */
    public function getRemainingAttempts(): int
    {
        $ip = $this->getClientIp();
        $since = time() - $this->lockoutTime;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts
            FROM login_attempts
            WHERE ip_address = :ip
            AND attempt_time > :since
            AND success = 0
        ");
        $stmt->execute(['ip' => $ip, 'since' => $since]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return max(0, $this->maxAttempts - $result['attempts']);
    }
}
