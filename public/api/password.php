<?php
/**
 * API-Endpunkt für Passwort-Änderung
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

$method = $_SERVER['REQUEST_METHOD'];

// CSRF-Schutz
if ($method === 'POST') {
    $auth->requireCsrfToken();
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['error' => 'Alle Felder müssen ausgefüllt werden']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'Neue Passwörter stimmen nicht überein']);
        exit;
    }

    $user = $auth->getCurrentUser();
    $userModel = new User();

    $userModel->changePassword($user['id'], $currentPassword, $newPassword);

    echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
