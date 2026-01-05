<?php
/**
 * Admin-Seite für Benutzerverwaltung
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

$auth = new Auth();
$auth->requireAdmin();

$user = $auth->getCurrentUser();
$csrfToken = $auth->getCsrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Benutzerverwaltung - YAML/MD Editor</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/favicon/favicon.ico">
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
            min-height: 100vh;
        }

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

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.5rem;
        }

        .btn-add {
            padding: 0.5rem 1rem;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-add:hover {
            background: #219a52;
        }

        .btn-secondary {
            padding: 0.5rem 1rem;
            background: #4a90d9;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: #357abd;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .users-table {
            width: 100%;
            background: #16213e;
            border-radius: 10px;
            overflow: hidden;
        }

        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #0f3460;
        }

        .users-table th {
            background: #0f3460;
            font-weight: 600;
        }

        .users-table tr:hover {
            background: rgba(74, 144, 217, 0.1);
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .role-badge.admin {
            background: #e74c3c;
            color: #fff;
        }

        .role-badge.user {
            background: #3498db;
            color: #fff;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }

        .action-btn.edit {
            background: #f39c12;
            color: #fff;
        }

        .action-btn.delete {
            background: #e74c3c;
            color: #fff;
        }

        .action-btn:hover {
            opacity: 0.8;
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            min-width: 450px;
            max-width: 90%;
        }

        .modal h3 {
            margin-bottom: 1.5rem;
            color: #fff;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #aaa;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #0f3460;
            background: #1a1a2e;
            color: #fff;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #4a90d9;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #0f3460;
            background: #1a1a2e;
            color: #fff;
            border-radius: 5px;
            font-size: 1rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1.5rem;
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

        .modal-btn.danger {
            background: #e74c3c;
            color: #fff;
        }

        /* Toast */
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

        .help-text {
            font-size: 0.8rem;
            color: #6272a4;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Benutzerverwaltung</h1>
        <div class="header-right">
            <a href="index.php" class="header-btn">Zurück zum Editor</a>
        </div>
    </header>

    <div class="container">
        <div class="section-header">
            <h2>Benutzer</h2>
            <div class="header-actions">
                <button class="btn-secondary" onclick="exportUsers()">Export</button>
                <button class="btn-secondary" onclick="document.getElementById('importFile').click()">Import</button>
                <input type="file" id="importFile" accept=".json" style="display: none" onchange="importUsers(this.files[0])">
                <button class="btn-add" onclick="showAddModal()">+ Neuer Benutzer</button>
            </div>
        </div>

        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Benutzername</th>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Wird per JavaScript gefüllt -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal für Benutzer hinzufügen/bearbeiten -->
    <div class="modal-overlay" id="userModal">
        <div class="modal">
            <h3 id="modalTitle">Neuer Benutzer</h3>
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="form-group">
                    <label for="username">Benutzername</label>
                    <input type="text" class="form-input" id="username" required>
                </div>
                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <input type="email" class="form-input" id="email">
                </div>
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password" class="form-input" id="password">
                    <div class="help-text" id="passwordHelp">Mindestens 8 Zeichen</div>
                </div>
                <div class="form-group">
                    <label for="role">Rolle</label>
                    <select class="form-select" id="role">
                        <option value="user">Benutzer</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn cancel" onclick="hideUserModal()">Abbrechen</button>
                    <button type="submit" class="modal-btn primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal für Löschen bestätigen -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>Benutzer löschen</h3>
            <p style="margin-bottom: 1rem; color: #ccc;">Möchten Sie den Benutzer "<span id="deleteUserName"></span>" wirklich löschen?</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideDeleteModal()">Abbrechen</button>
                <button class="modal-btn danger" onclick="confirmDeleteUser()">Löschen</button>
            </div>
        </div>
    </div>

    <!-- Modal für Import-Optionen -->
    <div class="modal-overlay" id="importModal">
        <div class="modal">
            <h3>Benutzer importieren</h3>
            <p style="margin-bottom: 1rem; color: #ccc;">
                <span id="importInfo"></span>
            </p>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="importOverwrite"> Bestehende Benutzer überschreiben
                </label>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideImportModal()">Abbrechen</button>
                <button class="modal-btn primary" onclick="confirmImport()">Importieren</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        let users = [];
        let userToDelete = null;
        let isEditMode = false;

        // CSRF Token für API-Requests
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        // Fetch-Wrapper mit CSRF-Token
        function apiFetch(url, options = {}) {
            const defaultHeaders = {
                'X-CSRF-Token': getCsrfToken()
            };
            options.headers = { ...defaultHeaders, ...options.headers };
            return fetch(url, options);
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
        });

        async function loadUsers() {
            try {
                const response = await fetch('api/users.php');
                const result = await response.json();

                if (result.success) {
                    users = result.data;
                    renderUsers();
                } else {
                    showToast(result.error || 'Fehler beim Laden', 'error');
                }
            } catch (error) {
                showToast('Netzwerkfehler', 'error');
            }
        }

        function renderUsers() {
            const tbody = document.getElementById('usersTableBody');

            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.id}</td>
                    <td>${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.email || '-')}</td>
                    <td><span class="role-badge ${user.role}">${user.role === 'admin' ? 'Admin' : 'Benutzer'}</span></td>
                    <td>${formatDate(user.created_at)}</td>
                    <td>
                        <button class="action-btn edit" onclick="editUser(${user.id})">Bearbeiten</button>
                        <button class="action-btn delete" onclick="deleteUser(${user.id}, '${escapeHtml(user.username)}')" ${user.username === 'admin' ? 'disabled' : ''}>Löschen</button>
                    </td>
                </tr>
            `).join('');
        }

        function showAddModal() {
            isEditMode = false;
            document.getElementById('modalTitle').textContent = 'Neuer Benutzer';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('password').required = true;
            document.getElementById('passwordHelp').textContent = 'Mindestens 8 Zeichen';
            document.getElementById('userModal').classList.add('active');
            document.getElementById('username').focus();
        }

        function editUser(id) {
            const user = users.find(u => u.id === id);
            if (!user) return;

            isEditMode = true;
            document.getElementById('modalTitle').textContent = 'Benutzer bearbeiten';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email || '';
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('passwordHelp').textContent = 'Leer lassen, um Passwort nicht zu ändern';
            document.getElementById('role').value = user.role;
            document.getElementById('userModal').classList.add('active');
        }

        function hideUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const userId = document.getElementById('userId').value;
            const data = {
                username: document.getElementById('username').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                role: document.getElementById('role').value
            };

            if (userId) {
                data.id = parseInt(userId);
            }

            try {
                const response = await apiFetch('api/users.php', {
                    method: isEditMode ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    hideUserModal();
                    showToast(isEditMode ? 'Benutzer aktualisiert' : 'Benutzer erstellt', 'success');
                    loadUsers();
                } else {
                    showToast(result.error || 'Fehler', 'error');
                }
            } catch (error) {
                showToast('Netzwerkfehler', 'error');
            }
        });

        function deleteUser(id, username) {
            userToDelete = id;
            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('deleteModal').classList.add('active');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            userToDelete = null;
        }

        async function confirmDeleteUser() {
            if (!userToDelete) return;

            try {
                const response = await apiFetch(`api/users.php?id=${userToDelete}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    hideDeleteModal();
                    showToast('Benutzer gelöscht', 'success');
                    loadUsers();
                } else {
                    showToast(result.error || 'Fehler beim Löschen', 'error');
                }
            } catch (error) {
                showToast('Netzwerkfehler', 'error');
            }
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => toast.remove(), 3000);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        // Export-Funktion
        function exportUsers() {
            window.location.href = 'api/users.php?export=1';
        }

        // Import-Funktion
        let importData = null;

        function importUsers(file) {
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    importData = JSON.parse(e.target.result);

                    if (!importData.users || !Array.isArray(importData.users)) {
                        showToast('Ungültiges Dateiformat', 'error');
                        return;
                    }

                    document.getElementById('importInfo').textContent =
                        `${importData.users.length} Benutzer gefunden (exportiert am ${importData.exported_at || 'unbekannt'})`;
                    document.getElementById('importOverwrite').checked = false;
                    document.getElementById('importModal').classList.add('active');
                } catch (err) {
                    showToast('Fehler beim Lesen der Datei: ' + err.message, 'error');
                }
            };
            reader.readAsText(file);

            // Reset file input
            document.getElementById('importFile').value = '';
        }

        function hideImportModal() {
            document.getElementById('importModal').classList.remove('active');
            importData = null;
        }

        async function confirmImport() {
            if (!importData || !importData.users) return;

            try {
                const response = await apiFetch('api/users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        import: true,
                        users: importData.users,
                        overwrite: document.getElementById('importOverwrite').checked
                    })
                });

                const result = await response.json();

                if (result.success) {
                    hideImportModal();
                    showToast(result.message, 'success');
                    loadUsers();
                } else {
                    showToast(result.error || 'Fehler beim Import', 'error');
                }
            } catch (error) {
                showToast('Netzwerkfehler', 'error');
            }
        }

        // Modal schließen bei Klick außerhalb
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
