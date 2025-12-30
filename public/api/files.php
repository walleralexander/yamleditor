<?php
/**
 * API-Endpunkt für Datei-Operationen (CRUD)
 */

// Fehlerausgabe als JSON statt HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Custom error handler für JSON-Ausgabe
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../src/Auth.php';
    require_once __DIR__ . '/../../src/FileManager.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurationsfehler: ' . $e->getMessage()]);
    exit;
}

$auth = new Auth();

// Authentifizierung prüfen
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

$fileManager = new FileManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Datei lesen oder alle auflisten
            if (isset($_GET['file'])) {
                $file = $fileManager->read($_GET['file']);
                if ($file === null) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Datei nicht gefunden']);
                } else {
                    echo json_encode(['success' => true, 'data' => $file]);
                }
            } else {
                $files = $fileManager->listFiles();
                echo json_encode(['success' => true, 'data' => $files]);
            }
            break;

        case 'POST':
            // Neue Datei erstellen
            $input = json_decode(file_get_contents('php://input'), true);
            $filename = $input['filename'] ?? '';
            $content = $input['content'] ?? '';

            if (empty($filename)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dateiname erforderlich']);
                break;
            }

            $fileManager->create($filename, $content);
            echo json_encode(['success' => true, 'message' => 'Datei erstellt']);
            break;

        case 'PUT':
            // Datei aktualisieren
            $input = json_decode(file_get_contents('php://input'), true);
            $filename = $input['filename'] ?? '';
            $content = $input['content'] ?? '';

            if (empty($filename)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dateiname erforderlich']);
                break;
            }

            $fileManager->update($filename, $content);
            echo json_encode(['success' => true, 'message' => 'Datei gespeichert']);
            break;

        case 'DELETE':
            // Datei löschen
            $filename = $_GET['file'] ?? '';

            if (empty($filename)) {
                http_response_code(400);
                echo json_encode(['error' => 'Dateiname erforderlich']);
                break;
            }

            $fileManager->delete($filename);
            echo json_encode(['success' => true, 'message' => 'Datei gelöscht']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
