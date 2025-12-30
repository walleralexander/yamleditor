<?php
/**
 * Hauptseite - YAML/MD Editor
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YAML/MD Editor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #1a1a2e;
            color: #fff;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: #16213e;
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #0f3460;
        }

        .header h1 {
            font-size: 1.25rem;
            color: #4a90d9;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            color: #aaa;
            font-size: 0.9rem;
        }

        .header-btn {
            padding: 0.5rem 1rem;
            background: #4a90d9;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .header-btn:hover {
            background: #357abd;
        }

        .header-btn.secondary {
            background: #444;
        }

        .header-btn.secondary:hover {
            background: #555;
        }

        /* Main Container */
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #16213e;
            border-right: 1px solid #0f3460;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid #0f3460;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h2 {
            font-size: 1rem;
            color: #fff;
        }

        .btn-new {
            padding: 0.4rem 0.8rem;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .btn-new:hover {
            background: #219a52;
        }

        .file-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .file-item {
            padding: 0.75rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
            transition: background 0.2s;
        }

        .file-item:hover {
            background: #0f3460;
        }

        .file-item.active {
            background: #4a90d9;
        }

        .file-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-icon {
            font-size: 1rem;
        }

        .file-icon.yaml {
            color: #f7df1e;
        }

        .file-icon.md {
            color: #42b983;
        }

        .file-actions {
            display: none;
            gap: 0.5rem;
        }

        .file-item:hover .file-actions {
            display: flex;
        }

        .file-action-btn {
            padding: 0.25rem 0.5rem;
            background: transparent;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .file-action-btn:hover {
            opacity: 1;
        }

        .file-action-btn.delete:hover {
            color: #e74c3c;
        }

        /* Editor Area */
        .editor-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #282a36;
        }

        .editor-header {
            padding: 0.75rem 1rem;
            background: #1e1f29;
            border-bottom: 1px solid #44475a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .editor-title {
            font-size: 0.95rem;
            color: #f8f8f2;
        }

        .editor-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-save {
            padding: 0.5rem 1rem;
            background: #50fa7b;
            color: #282a36;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-save:hover {
            background: #5af584;
        }

        .btn-save:disabled {
            background: #44475a;
            color: #6272a4;
            cursor: not-allowed;
        }

        .editor-container {
            flex: 1;
            overflow: hidden;
        }

        .CodeMirror {
            height: 100%;
            font-size: 14px;
        }

        .no-file-selected {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6272a4;
            font-size: 1.1rem;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: #16213e;
            padding: 1.5rem;
            border-radius: 10px;
            min-width: 400px;
            max-width: 90%;
        }

        .modal h3 {
            margin-bottom: 1rem;
            color: #fff;
        }

        .modal-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #0f3460;
            background: #1a1a2e;
            color: #fff;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .modal-input:focus {
            outline: none;
            border-color: #4a90d9;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .modal-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .modal-btn.primary {
            background: #4a90d9;
            color: #fff;
        }

        .modal-btn.cancel {
            background: #444;
            color: #fff;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 2000;
        }

        .toast {
            padding: 1rem 1.5rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            color: #fff;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: #27ae60;
        }

        .toast.error {
            background: #e74c3c;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>YAML/MD Editor</h1>
        <div class="header-right">
            <span class="user-info">Angemeldet als: <?= htmlspecialchars($user['username']) ?></span>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin.php" class="header-btn secondary">Benutzer verwalten</a>
            <?php endif; ?>
            <a href="logout.php" class="header-btn">Abmelden</a>
        </div>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Dateien</h2>
                <button class="btn-new" onclick="showNewFileModal()">+ Neu</button>
            </div>
            <div class="file-list" id="fileList">
                <!-- Dateien werden per JavaScript geladen -->
            </div>
        </aside>

        <main class="editor-area">
            <div class="editor-header">
                <span class="editor-title" id="editorTitle">Keine Datei ausgewählt</span>
                <div class="editor-actions">
                    <button class="btn-save" id="btnSave" disabled onclick="saveFile()">Speichern</button>
                </div>
            </div>
            <div class="editor-container">
                <div class="no-file-selected" id="noFileMessage">
                    Wählen Sie eine Datei aus oder erstellen Sie eine neue
                </div>
                <textarea id="editor"></textarea>
            </div>
        </main>
    </div>

    <!-- Modal für neue Datei -->
    <div class="modal-overlay" id="newFileModal">
        <div class="modal">
            <h3>Neue Datei erstellen</h3>
            <input type="text" class="modal-input" id="newFileName" placeholder="dateiname.yaml oder dateiname.md">
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideNewFileModal()">Abbrechen</button>
                <button class="modal-btn primary" onclick="createNewFile()">Erstellen</button>
            </div>
        </div>
    </div>

    <!-- Modal für Datei löschen -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>Datei löschen</h3>
            <p style="margin-bottom: 1rem; color: #ccc;">Möchten Sie die Datei "<span id="deleteFileName"></span>" wirklich löschen?</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideDeleteModal()">Abbrechen</button>
                <button class="modal-btn primary" style="background: #e74c3c;" onclick="confirmDelete()">Löschen</button>
            </div>
        </div>
    </div>

    <!-- Fehler Modal -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal" style="border-left: 4px solid #e74c3c;">
            <h3 style="color: #e74c3c;">⚠️ Berechtigungsfehler</h3>
            <p id="errorMessage" style="margin: 1rem 0; color: #ccc; line-height: 1.6;"></p>
            <p style="margin-bottom: 1rem; color: #888; font-size: 0.9rem;">
                Führen Sie den angegebenen Befehl auf dem Host-System aus und versuchen Sie es erneut.
            </p>
            <div class="modal-buttons">
                <button class="modal-btn primary" onclick="hideErrorModal()">Verstanden</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
