<?php
/**
 * Hauptseite - YAML/MD Editor
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getCurrentUser();
$csrfToken = $auth->getCsrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>YAML/MD Editor</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/favicon/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.css">
    <style>
        :root {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --bg-tertiary: #0f3460;
            --text-primary: #fff;
            --text-secondary: #ccc;
            --text-muted: #888;
            --accent: #4a90d9;
            --accent-hover: #357abd;
            --border: #0f3460;
            --sidebar-bg: #16213e;
            --editor-bg: #1a1a2e;
            --preview-bg: #1e1e2e;
        }

        body.light-theme {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e0e0e0;
            --text-primary: #1a1a2e;
            --text-secondary: #333;
            --text-muted: #666;
            --accent: #4a90d9;
            --accent-hover: #357abd;
            --border: #ddd;
            --sidebar-bg: #ffffff;
            --editor-bg: #fafafa;
            --preview-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background 0.3s, color 0.3s;
        }

        /* Header */
        .header {
            background: var(--bg-secondary);
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 1.25rem;
            color: var(--accent);
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

        .font-controls {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--bg-tertiary);
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
        }

        .font-btn {
            padding: 0.25rem 0.5rem;
            background: transparent;
            color: var(--text-primary);
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .font-btn:hover {
            color: var(--accent);
        }

        #fontSizeDisplay {
            color: var(--text-muted);
            font-size: 0.8rem;
            min-width: 35px;
            text-align: center;
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
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h2 {
            font-size: 1rem;
            color: var(--text-primary);
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
            background: var(--bg-tertiary);
        }

        .file-item.active {
            background: var(--accent);
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

        .file-icon.markdown {
            color: #42b983;
        }

        .file-icon.json {
            color: #f0ad4e;
        }

        .file-icon.text {
            color: #999;
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
            color: var(--text-primary);
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
            display: flex;
        }

        .editor-wrapper {
            flex: 1;
            overflow: hidden;
            min-width: 0;
        }

        .CodeMirror {
            height: 100%;
            font-size: 14px;
        }

        /* Markdown Preview */
        .preview-pane {
            flex: 1;
            overflow: auto;
            padding: 1rem;
            background: var(--preview-bg);
            border-left: 1px solid var(--border);
            display: none;
            min-width: 0;
        }

        .preview-pane.active {
            display: block;
        }

        .preview-pane h1, .preview-pane h2, .preview-pane h3,
        .preview-pane h4, .preview-pane h5, .preview-pane h6 {
            color: var(--text-primary);
            margin: 1rem 0 0.5rem 0;
        }

        .preview-pane h1 { font-size: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 0.3rem; }
        .preview-pane h2 { font-size: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.3rem; }
        .preview-pane h3 { font-size: 1.25rem; }

        .preview-pane p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0.5rem 0;
        }

        .preview-pane a {
            color: #8be9fd;
        }

        .preview-pane code {
            background: #282a36;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: monospace;
            color: #50fa7b;
        }

        .preview-pane pre {
            background: #282a36;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
            margin: 1rem 0;
        }

        .preview-pane pre code {
            padding: 0;
            background: transparent;
        }

        .preview-pane ul, .preview-pane ol {
            color: #ccc;
            margin: 0.5rem 0;
            padding-left: 2rem;
        }

        .preview-pane li {
            margin: 0.25rem 0;
        }

        .preview-pane blockquote {
            border-left: 4px solid #6272a4;
            margin: 1rem 0;
            padding-left: 1rem;
            color: #999;
        }

        .preview-pane table {
            border-collapse: collapse;
            width: 100%;
            margin: 1rem 0;
        }

        .preview-pane th, .preview-pane td {
            border: 1px solid #44475a;
            padding: 0.5rem;
            text-align: left;
        }

        .preview-pane th {
            background: #282a36;
            color: #f8f8f2;
        }

        .preview-pane td {
            color: #ccc;
        }

        .preview-pane hr {
            border: none;
            border-top: 1px solid #44475a;
            margin: 1.5rem 0;
        }

        .preview-pane img {
            max-width: 100%;
            height: auto;
        }

        .btn-preview {
            padding: 0.5rem 1rem;
            background: #6272a4;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-preview:hover {
            background: #7283b5;
        }

        .btn-preview.active {
            background: #bd93f9;
        }

        .btn-export {
            padding: 0.5rem 0.75rem;
            background: #50fa7b;
            color: #1a1a2e;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .btn-export:hover {
            background: #69fb8f;
        }

        /* Editor Toolbar */
        .editor-toolbar {
            display: none;
            padding: 0.5rem;
            background: #1e1f29;
            border-bottom: 1px solid #44475a;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .editor-toolbar.active {
            display: flex;
        }

        .toolbar-btn {
            padding: 0.4rem 0.6rem;
            background: #44475a;
            color: #f8f8f2;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.85rem;
            min-width: 32px;
        }

        .toolbar-btn:hover {
            background: #6272a4;
        }

        .toolbar-separator {
            width: 1px;
            background: #44475a;
            margin: 0 0.25rem;
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
            font-weight: bold;
            max-width: 400px;
            word-wrap: break-word;
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
        /* Sidebar Toggle Button */
        .sidebar-toggle {
            display: none;
            padding: 0.5rem;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }

            .header h1 {
                font-size: 1rem;
            }

            .header-right {
                gap: 0.5rem;
            }

            .user-info {
                display: none;
            }

            .font-controls {
                display: none;
            }

            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100vh;
                z-index: 100;
                transition: left 0.3s ease;
                width: 280px;
            }

            .sidebar.open {
                left: 0;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 99;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .editor-pane {
                width: 100% !important;
            }

            .preview-pane.active {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 50;
            }

            .modal {
                min-width: auto !important;
                width: 90%;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Dateien">☰</button>
        <h1>YAML/MD Editor</h1>
        <div class="header-right">
            <span class="user-info">Angemeldet als: <?= htmlspecialchars($user['username']) ?></span>
            <span class="font-controls">
                <button class="font-btn" onclick="changeFontSize(-1)" title="Schrift kleiner">A-</button>
                <span id="fontSizeDisplay">14px</span>
                <button class="font-btn" onclick="changeFontSize(1)" title="Schrift größer">A+</button>
            </span>
            <button class="header-btn secondary" id="themeToggle" onclick="toggleTheme()" title="Theme wechseln">🌙</button>
            <button class="header-btn secondary" onclick="showPasswordModal()">Passwort ändern</button>
            <button class="header-btn secondary" onclick="showShortcutsModal()" title="Tastaturkürzel">⌨️ Hilfe</button>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin.php" class="header-btn secondary">Benutzer verwalten</a>
            <?php endif; ?>
            <a href="logout.php" class="header-btn">Abmelden</a>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="main-container">
        <aside class="sidebar" id="sidebar">
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
                    <button class="btn-preview" id="btnPreview" style="display: none;" onclick="togglePreview()">Vorschau</button>
                    <button class="btn-export" id="btnExportHtml" style="display: none;" onclick="exportAsHtml()" title="Als HTML exportieren">HTML</button>
                    <button class="btn-export" id="btnExportPdf" style="display: none;" onclick="exportAsPdf()" title="Als PDF drucken">PDF</button>
                    <button class="btn-save" id="btnSave" disabled onclick="saveFile()">Speichern</button>
                </div>
            </div>
            <!-- Markdown Toolbar -->
            <div class="editor-toolbar" id="editorToolbar">
                <button class="toolbar-btn" onclick="insertFormat('bold')" title="Fett (Ctrl+B)"><b>B</b></button>
                <button class="toolbar-btn" onclick="insertFormat('italic')" title="Kursiv (Ctrl+I)"><i>I</i></button>
                <button class="toolbar-btn" onclick="insertFormat('strikethrough')" title="Durchgestrichen">S̶</button>
                <div class="toolbar-separator"></div>
                <button class="toolbar-btn" onclick="insertFormat('h1')" title="Überschrift 1">H1</button>
                <button class="toolbar-btn" onclick="insertFormat('h2')" title="Überschrift 2">H2</button>
                <button class="toolbar-btn" onclick="insertFormat('h3')" title="Überschrift 3">H3</button>
                <div class="toolbar-separator"></div>
                <button class="toolbar-btn" onclick="insertFormat('ul')" title="Liste">•</button>
                <button class="toolbar-btn" onclick="insertFormat('ol')" title="Nummerierte Liste">1.</button>
                <button class="toolbar-btn" onclick="insertFormat('checklist')" title="Checkliste">☑</button>
                <div class="toolbar-separator"></div>
                <button class="toolbar-btn" onclick="insertFormat('link')" title="Link">🔗</button>
                <button class="toolbar-btn" onclick="insertFormat('image')" title="Bild">🖼</button>
                <button class="toolbar-btn" onclick="insertFormat('code')" title="Code">&lt;/&gt;</button>
                <button class="toolbar-btn" onclick="insertFormat('codeblock')" title="Code-Block">```</button>
                <div class="toolbar-separator"></div>
                <button class="toolbar-btn" onclick="insertFormat('quote')" title="Zitat">"</button>
                <button class="toolbar-btn" onclick="insertFormat('hr')" title="Trennlinie">―</button>
                <button class="toolbar-btn" onclick="insertFormat('table')" title="Tabelle">▦</button>
            </div>
            <div class="editor-container">
                <div class="no-file-selected" id="noFileMessage">
                    Wählen Sie eine Datei aus oder erstellen Sie eine neue
                </div>
                <div class="editor-wrapper">
                    <textarea id="editor"></textarea>
                </div>
                <div class="preview-pane" id="previewPane"></div>
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

    <!-- Modal für Datei umbenennen -->
    <div class="modal-overlay" id="renameModal">
        <div class="modal">
            <h3>Datei umbenennen</h3>
            <p style="margin-bottom: 0.5rem; color: #888; font-size: 0.9rem;">Alter Name: <span id="oldFileName"></span></p>
            <input type="text" class="modal-input" id="renameFileName" placeholder="Neuer Dateiname">
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideRenameModal()">Abbrechen</button>
                <button class="modal-btn primary" onclick="confirmRename()">Umbenennen</button>
            </div>
        </div>
    </div>

    <!-- Fehler Modal -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal" style="border-left: 4px solid #e74c3c;">
            <h3 style="color: #e74c3c;">Berechtigungsfehler</h3>
            <p id="errorMessage" style="margin: 1rem 0; color: #ccc; line-height: 1.6;"></p>
            <p style="margin-bottom: 1rem; color: #888; font-size: 0.9rem;">
                Führen Sie den angegebenen Befehl auf dem Host-System aus und versuchen Sie es erneut.
            </p>
            <div class="modal-buttons">
                <button class="modal-btn primary" onclick="hideErrorModal()">Verstanden</button>
            </div>
        </div>
    </div>

    <!-- Passwort ändern Modal -->
    <div class="modal-overlay" id="passwordModal">
        <div class="modal">
            <h3>Passwort ändern</h3>
            <input type="password" class="modal-input" id="currentPassword" placeholder="Aktuelles Passwort">
            <input type="password" class="modal-input" id="newPassword" placeholder="Neues Passwort">
            <input type="password" class="modal-input" id="confirmPassword" placeholder="Neues Passwort bestätigen">
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hidePasswordModal()">Abbrechen</button>
                <button class="modal-btn primary" onclick="changePassword()">Ändern</button>
            </div>
        </div>
    </div>

    <!-- Tastaturkürzel Modal -->
    <div class="modal-overlay" id="shortcutsModal">
        <div class="modal" style="min-width: 500px;">
            <h3>Tastaturkürzel</h3>
            <div style="margin: 1rem 0;">
                <h4 style="color: #4a90d9; margin-bottom: 0.5rem; font-size: 0.9rem;">Allgemein</h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #0f3460;">
                        <td style="padding: 0.5rem 0; color: #ccc;"><kbd style="background: #0f3460; padding: 0.2rem 0.5rem; border-radius: 3px;">Ctrl + S</kbd></td>
                        <td style="padding: 0.5rem 0; color: #888;">Datei speichern</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #0f3460;">
                        <td style="padding: 0.5rem 0; color: #ccc;"><kbd style="background: #0f3460; padding: 0.2rem 0.5rem; border-radius: 3px;">Tab</kbd></td>
                        <td style="padding: 0.5rem 0; color: #888;">2 Leerzeichen einfügen</td>
                    </tr>
                </table>

                <h4 style="color: #4a90d9; margin: 1rem 0 0.5rem; font-size: 0.9rem;">Markdown-Formatierung</h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #0f3460;">
                        <td style="padding: 0.5rem 0; color: #ccc;"><kbd style="background: #0f3460; padding: 0.2rem 0.5rem; border-radius: 3px;">Ctrl + B</kbd></td>
                        <td style="padding: 0.5rem 0; color: #888;">Fett</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #0f3460;">
                        <td style="padding: 0.5rem 0; color: #ccc;"><kbd style="background: #0f3460; padding: 0.2rem 0.5rem; border-radius: 3px;">Ctrl + I</kbd></td>
                        <td style="padding: 0.5rem 0; color: #888;">Kursiv</td>
                    </tr>
                </table>

                <h4 style="color: #4a90d9; margin: 1rem 0 0.5rem; font-size: 0.9rem;">Toolbar-Buttons (Markdown)</h4>
                <p style="color: #888; font-size: 0.9rem; line-height: 1.6;">
                    Überschriften (H1, H2, H3) • Fett, Kursiv, Durchgestrichen • Listen (ungeordnet, nummeriert, Checkliste) • Links, Bilder • Code, Codeblock • Zitat, Trennlinie, Tabelle
                </p>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn primary" onclick="hideShortcutsModal()">Schließen</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-yaml/4.1.0/js-yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
