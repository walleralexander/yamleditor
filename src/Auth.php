<?php
/**
 * Authentifizierungs-Klasse
 */

require_once __DIR__ . '/User.php';

class Auth
{
    private User $userModel;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->userModel = new User();
    }

    /**
     * Login durchführen
     */
    public function login(string $username, string $password): bool
    {
        $user = $this->userModel->validateLogin($username, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            return true;
        }

        return false;
    }

    /**
     * Logout durchführen
     */
    public function logout(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Prüfen ob eingeloggt
     */
    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }

        // Session-Timeout prüfen
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Prüfen ob Admin
     */
    public function isAdmin(): bool
    {
        return $this->isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Aktuellen Benutzer abrufen
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Zugriff erfordern (Redirect zu Login wenn nicht eingeloggt)
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Admin-Zugriff erfordern
     */
    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            die('Zugriff verweigert');
        }
    }
}
