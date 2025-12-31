<?php
/**
 * User-Model für CRUD-Operationen
 */

require_once __DIR__ . '/Database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Alle Benutzer abrufen
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT id, username, email, role, created_at, updated_at FROM users ORDER BY username");
        return $stmt->fetchAll();
    }

    /**
     * Alle Benutzer mit Passwort-Hash abrufen (für Export)
     */
    public function getAllWithPasswords(): array
    {
        $stmt = $this->db->query("SELECT id, username, password, email, role, created_at, updated_at FROM users ORDER BY username");
        return $stmt->fetchAll();
    }

    /**
     * Benutzer nach ID abrufen
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, email, role, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Benutzer nach Username abrufen
     */
    public function getByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Neuen Benutzer erstellen
     */
    public function create(string $username, string $password, string $email = '', string $role = 'user'): bool
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception("Passwort muss mindestens " . PASSWORD_MIN_LENGTH . " Zeichen haben");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$username, $hashedPassword, $email, $role]);
    }

    /**
     * Benutzer mit bereits gehashtem Passwort erstellen (für Import)
     */
    public function createWithHashedPassword(string $username, string $hashedPassword, string $email = '', string $role = 'user'): bool
    {
        $stmt = $this->db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$username, $hashedPassword, $email, $role]);
    }

    /**
     * Benutzer aktualisieren
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['username'])) {
            $fields[] = 'username = ?';
            $values[] = $data['username'];
        }

        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = $data['email'];
        }

        if (isset($data['role'])) {
            $fields[] = 'role = ?';
            $values[] = $data['role'];
        }

        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                throw new Exception("Passwort muss mindestens " . PASSWORD_MIN_LENGTH . " Zeichen haben");
            }
            $fields[] = 'password = ?';
            $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $values[] = $id;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Benutzer mit bereits gehashtem Passwort aktualisieren (für Import)
     */
    public function updateWithHashedPassword(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['username'])) {
            $fields[] = 'username = ?';
            $values[] = $data['username'];
        }

        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = $data['email'];
        }

        if (isset($data['role'])) {
            $fields[] = 'role = ?';
            $values[] = $data['role'];
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = 'password = ?';
            $values[] = $data['password']; // Bereits gehasht
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $values[] = $id;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Benutzer löschen
     */
    public function delete(int $id): bool
    {
        // Admin kann nicht gelöscht werden
        $user = $this->getById($id);
        if ($user && $user['username'] === 'admin') {
            throw new Exception("Der Admin-Benutzer kann nicht gelöscht werden");
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Login-Validierung
     */
    public function validateLogin(string $username, string $password): ?array
    {
        $user = $this->getByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }

        return null;
    }

    /**
     * Passwort ändern (mit Validierung des aktuellen Passworts)
     */
    public function changePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        // Benutzer mit Passwort abrufen
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("Benutzer nicht gefunden");
        }

        // Aktuelles Passwort prüfen
        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception("Aktuelles Passwort ist falsch");
        }

        // Neues Passwort validieren
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            throw new Exception("Neues Passwort muss mindestens " . PASSWORD_MIN_LENGTH . " Zeichen haben");
        }

        // Neues Passwort setzen
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$hashedPassword, $id]);
    }
}
