<?php
/**
 * API-Endpunkt für Benutzer-Verwaltung (CRUD)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/User.php';

$auth = new Auth();

// Authentifizierung prüfen
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

// Nur Admins dürfen Benutzer verwalten
if (!$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Zugriff verweigert']);
    exit;
}

$userModel = new User();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Benutzer abrufen
            if (isset($_GET['id'])) {
                $user = $userModel->getById((int)$_GET['id']);
                if ($user === null) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Benutzer nicht gefunden']);
                } else {
                    echo json_encode(['success' => true, 'data' => $user]);
                }
            } else {
                $users = $userModel->getAll();
                echo json_encode(['success' => true, 'data' => $users]);
            }
            break;

        case 'POST':
            // Neuen Benutzer erstellen
            $input = json_decode(file_get_contents('php://input'), true);

            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            $email = trim($input['email'] ?? '');
            $role = $input['role'] ?? 'user';

            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'Benutzername und Passwort erforderlich']);
                break;
            }

            $userModel->create($username, $password, $email, $role);
            echo json_encode(['success' => true, 'message' => 'Benutzer erstellt']);
            break;

        case 'PUT':
            // Benutzer aktualisieren
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Benutzer-ID erforderlich']);
                break;
            }

            $data = [];
            if (isset($input['username'])) $data['username'] = trim($input['username']);
            if (isset($input['email'])) $data['email'] = trim($input['email']);
            if (isset($input['role'])) $data['role'] = $input['role'];
            if (!empty($input['password'])) $data['password'] = $input['password'];

            $userModel->update($id, $data);
            echo json_encode(['success' => true, 'message' => 'Benutzer aktualisiert']);
            break;

        case 'DELETE':
            // Benutzer löschen
            $id = (int)($_GET['id'] ?? 0);

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Benutzer-ID erforderlich']);
                break;
            }

            $userModel->delete($id);
            echo json_encode(['success' => true, 'message' => 'Benutzer gelöscht']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
