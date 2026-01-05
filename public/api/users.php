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

// CSRF-Schutz für modifizierende Requests
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $auth->requireCsrfToken();
}

try {
    switch ($method) {
        case 'GET':
            // Export-Funktion
            if (isset($_GET['export'])) {
                $users = $userModel->getAllWithPasswords();
                $exportData = [
                    'version' => '1.0',
                    'exported_at' => date('Y-m-d H:i:s'),
                    'users' => $users
                ];
                header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_His') . '.json"');
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                break;
            }

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
            $input = json_decode(file_get_contents('php://input'), true);

            // Import-Funktion
            if (isset($input['import']) && isset($input['users'])) {
                $imported = 0;
                $skipped = 0;
                $errors = [];

                foreach ($input['users'] as $userData) {
                    try {
                        $existingUser = $userModel->getByUsername($userData['username']);
                        if ($existingUser) {
                            if (isset($input['overwrite']) && $input['overwrite']) {
                                // Benutzer aktualisieren (mit gehashtem Passwort)
                                $userModel->updateWithHashedPassword($existingUser['id'], $userData);
                                $imported++;
                            } else {
                                $skipped++;
                            }
                        } else {
                            // Neuen Benutzer mit bereits gehashtem Passwort erstellen
                            $userModel->createWithHashedPassword(
                                $userData['username'],
                                $userData['password'],
                                $userData['email'] ?? '',
                                $userData['role'] ?? 'user'
                            );
                            $imported++;
                        }
                    } catch (Exception $e) {
                        $errors[] = $userData['username'] . ': ' . $e->getMessage();
                    }
                }

                $message = "$imported Benutzer importiert";
                if ($skipped > 0) {
                    $message .= ", $skipped übersprungen";
                }
                if (count($errors) > 0) {
                    $message .= ". Fehler: " . implode(', ', $errors);
                }

                echo json_encode(['success' => true, 'message' => $message, 'imported' => $imported, 'skipped' => $skipped]);
                break;
            }

            // Neuen Benutzer erstellen
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
