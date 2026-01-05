/**
 * YAML/MD Editor - Frontend JavaScript
 */

let editor = null;
let currentFile = null;
let isModified = false;
let fileToDelete = null;
let fileToRename = null;
let previewActive = false;
let autosaveTimer = null;
const AUTOSAVE_DELAY = 15000; // 15 Sekunden

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

// YAML Linter für CodeMirror registrieren
CodeMirror.registerHelper('lint', 'yaml', function(text) {
    const errors = [];
    if (typeof jsyaml === 'undefined') {
        return errors;
    }
    try {
        jsyaml.load(text);
    } catch (e) {
        const line = e.mark ? e.mark.line : 0;
        const col = e.mark ? e.mark.column : 0;
        errors.push({
            from: CodeMirror.Pos(line, col),
            to: CodeMirror.Pos(line, col + 1),
            message: e.reason || e.message,
            severity: 'error'
        });
    }
    return errors;
});

// JSON Linter für CodeMirror registrieren
CodeMirror.registerHelper('lint', 'json', function(text) {
    const errors = [];
    if (!text.trim()) {
        return errors;
    }
    try {
        JSON.parse(text);
    } catch (e) {
        // Versuche Zeile und Spalte aus der Fehlermeldung zu extrahieren
        let line = 0;
        let col = 0;
        const match = e.message.match(/position (\d+)/);
        if (match) {
            const pos = parseInt(match[1]);
            const lines = text.substring(0, pos).split('\n');
            line = lines.length - 1;
            col = lines[lines.length - 1].length;
        }
        errors.push({
            from: CodeMirror.Pos(line, col),
            to: CodeMirror.Pos(line, col + 1),
            message: e.message,
            severity: 'error'
        });
    }
    return errors;
});

// Theme Management
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    if (savedTheme === 'light') {
        document.body.classList.add('light-theme');
        document.getElementById('themeToggle').textContent = '☀️';
    }
}

function toggleTheme() {
    const isLight = document.body.classList.toggle('light-theme');
    localStorage.setItem('theme', isLight ? 'light' : 'dark');
    document.getElementById('themeToggle').textContent = isLight ? '☀️' : '🌙';

    // CodeMirror Theme wechseln
    if (editor) {
        editor.setOption('theme', isLight ? 'default' : 'dracula');
    }
}

// Font Size Management
let currentFontSize = parseInt(localStorage.getItem('fontSize')) || 14;

function initFontSize() {
    document.getElementById('fontSizeDisplay').textContent = currentFontSize + 'px';
    applyFontSize();
}

function changeFontSize(delta) {
    const newSize = currentFontSize + delta;
    if (newSize >= 10 && newSize <= 24) {
        currentFontSize = newSize;
        localStorage.setItem('fontSize', currentFontSize);
        document.getElementById('fontSizeDisplay').textContent = currentFontSize + 'px';
        applyFontSize();
    }
}

function applyFontSize() {
    if (editor) {
        editor.getWrapperElement().style.fontSize = currentFontSize + 'px';
        editor.refresh();
    }
}

// Mobile Sidebar Toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// Editor initialisieren
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initFontSize();
    initEditor();
    loadFiles();
});

function initEditor() {
    const textarea = document.getElementById('editor');
    textarea.style.display = 'none';

    const isLight = document.body.classList.contains('light-theme');
    editor = CodeMirror.fromTextArea(textarea, {
        theme: isLight ? 'default' : 'dracula',
        lineNumbers: true,
        lineWrapping: true,
        indentUnit: 2,
        tabSize: 2,
        indentWithTabs: false,
        autofocus: false,
        gutters: ['CodeMirror-lint-markers'],
        lint: false  // Wird beim Öffnen einer Datei aktiviert
    });

    editor.setSize('100%', '100%');
    editor.getWrapperElement().style.display = 'none';
    applyFontSize();

    // Änderungen tracken
    editor.on('change', () => {
        if (currentFile) {
            isModified = true;
            updateSaveButton();
            // Markdown-Vorschau aktualisieren
            if (previewActive) {
                updatePreview();
            }
            // Autosave für Markdown-Dateien
            if (isMarkdownFile(currentFile)) {
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(() => {
                    if (isModified && currentFile && isMarkdownFile(currentFile)) {
                        autosave();
                    }
                }, AUTOSAVE_DELAY);
            }
        }
    });

    // Tastenkombinationen
    editor.setOption('extraKeys', {
        'Ctrl-S': (cm) => {
            saveFile();
            return false;
        },
        'Ctrl-B': (cm) => {
            insertFormat('bold');
            return false;
        },
        'Ctrl-I': (cm) => {
            insertFormat('italic');
            return false;
        },
        'Tab': (cm) => {
            // Tab mit Leerzeichen ersetzen
            cm.replaceSelection('  ', 'end');
        }
    });
}

function updateSaveButton() {
    const btn = document.getElementById('btnSave');
    btn.disabled = !isModified || !currentFile;
}

// Prüft ob Datei eine Markdown-Datei ist
function isMarkdownFile(filename) {
    if (!filename) return false;
    const lower = filename.toLowerCase();
    return lower.endsWith('.md') || lower.endsWith('.markdown');
}

// Autosave-Funktion (ohne Validierung, nur für Markdown)
async function autosave() {
    if (!currentFile || !isModified || !isMarkdownFile(currentFile)) return;

    try {
        const response = await apiFetch('api/files.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filename: currentFile,
                content: editor.getValue()
            })
        });

        if (!response.ok) {
            console.error('Autosave failed:', response.status);
            return;
        }

        const result = await response.json();

        if (result.success) {
            isModified = false;
            updateSaveButton();
            showToast('Automatisch gespeichert', 'success');
        }
    } catch (error) {
        console.error('Autosave error:', error);
    }
}

// Dateien laden
async function loadFiles() {
    try {
        const response = await fetch('api/files.php');
        const result = await response.json();

        if (result.success) {
            renderFileList(result.data);
        } else {
            showToast(result.error || 'Fehler beim Laden der Dateien', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

function renderFileList(files) {
    const container = document.getElementById('fileList');

    if (files.length === 0) {
        container.innerHTML = '<div style="padding: 1rem; color: #6272a4; text-align: center;">Keine Dateien vorhanden</div>';
        return;
    }

    container.innerHTML = files.map(file => `
        <div class="file-item ${currentFile === file.name ? 'active' : ''}" onclick="openFile('${escapeHtml(file.name)}')">
            <div class="file-name">
                <span class="file-icon ${file.type}">${getFileIcon(file.type)}</span>
                <span>${escapeHtml(file.name)}</span>
            </div>
            <div class="file-actions">
                <button class="file-action-btn rename" onclick="event.stopPropagation(); showRenameModal('${escapeHtml(file.name)}')" title="Umbenennen">✏️</button>
                <button class="file-action-btn delete" onclick="event.stopPropagation(); showDeleteModal('${escapeHtml(file.name)}')" title="Löschen">🗑️</button>
            </div>
        </div>
    `).join('');
}

function getFileIcon(type) {
    switch (type) {
        case 'yaml': return '📄';
        case 'markdown': return '📝';
        case 'json': return '📋';
        case 'text': return '📃';
        default: return '📄';
    }
}

// Datei öffnen
async function openFile(filename) {
    // Warnung bei ungespeicherten Änderungen
    if (isModified && currentFile) {
        if (!confirm('Sie haben ungespeicherte Änderungen. Fortfahren?')) {
            return;
        }
    }

    try {
        const response = await fetch(`api/files.php?file=${encodeURIComponent(filename)}`);
        const result = await response.json();

        if (result.success) {
            currentFile = filename;

            // Editor-Modus setzen
            const fileType = result.data.type;
            let mode = 'text';
            if (fileType === 'yaml') mode = 'yaml';
            else if (fileType === 'markdown') mode = 'markdown';
            else if (fileType === 'json') mode = 'application/json';
            else mode = 'text';

            const isMarkdown = fileType === 'markdown';
            const isYaml = fileType === 'yaml';
            const isJson = fileType === 'json';

            editor.setOption('mode', mode);

            // Linting für YAML und JSON aktivieren
            if (isYaml) {
                editor.setOption('lint', { getAnnotations: CodeMirror.lint.yaml });
            } else if (isJson) {
                editor.setOption('lint', { getAnnotations: CodeMirror.lint.json });
            } else {
                editor.setOption('lint', false);
            }

            // Preview-Button, Export-Buttons und Toolbar nur für Markdown anzeigen
            document.getElementById('btnPreview').style.display = isMarkdown ? 'inline-block' : 'none';
            document.getElementById('btnExportHtml').style.display = isMarkdown ? 'inline-block' : 'none';
            document.getElementById('btnExportPdf').style.display = isMarkdown ? 'inline-block' : 'none';
            document.getElementById('editorToolbar').classList.toggle('active', isMarkdown);

            // Preview zurücksetzen wenn nicht Markdown
            if (!isMarkdown && previewActive) {
                hidePreview();
            }

            editor.setValue(result.data.content);
            editor.clearHistory();

            // isModified NACH setValue zurücksetzen, da setValue das change-Event auslöst
            isModified = false;

            // UI aktualisieren
            document.getElementById('noFileMessage').style.display = 'none';
            editor.getWrapperElement().style.display = 'block';
            document.getElementById('editorTitle').textContent = filename;
            updateSaveButton();

            // Dateiliste aktualisieren (aktive Markierung)
            loadFiles();

            // Editor refreshen nachdem er sichtbar ist
            editor.refresh();
            editor.focus();

            // Vorschau aktualisieren wenn aktiv
            if (previewActive && isMarkdown) {
                updatePreview();
            }

            // Sidebar auf Mobilgeräten schließen
            closeSidebar();
        } else {
            showToast(result.error || 'Fehler beim Öffnen der Datei', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

// YAML validieren
function validateYaml(content) {
    // Prüfen ob js-yaml Bibliothek geladen ist
    if (typeof jsyaml === 'undefined') {
        console.error('js-yaml library not loaded');
        return { valid: true }; // Fallback: keine Validierung
    }

    try {
        jsyaml.load(content);
        return { valid: true };
    } catch (e) {
        return {
            valid: false,
            message: e.message,
            line: e.mark ? e.mark.line + 1 : null
        };
    }
}

// JSON validieren
function validateJson(content) {
    if (!content.trim()) {
        return { valid: true }; // Leere Datei ist OK
    }

    try {
        JSON.parse(content);
        return { valid: true };
    } catch (e) {
        let line = null;
        const match = e.message.match(/position (\d+)/);
        if (match) {
            const pos = parseInt(match[1]);
            const lines = content.substring(0, pos).split('\n');
            line = lines.length;
        }
        return {
            valid: false,
            message: e.message,
            line: line
        };
    }
}

// Datei speichern
async function saveFile() {
    if (!currentFile || !isModified) return;

    const lower = currentFile.toLowerCase();
    const isYaml = lower.endsWith('.yaml') || lower.endsWith('.yml');
    const isJson = lower.endsWith('.json');

    // YAML-Dateien vor dem Speichern validieren
    if (isYaml) {
        const validation = validateYaml(editor.getValue());
        if (!validation.valid) {
            let errorMsg = 'YAML-Syntaxfehler';
            if (validation.line) {
                errorMsg += ` in Zeile ${validation.line}`;
                editor.setCursor(validation.line - 1, 0);
                editor.focus();
            }
            errorMsg += `: ${validation.message}`;
            showToast(errorMsg, 'error');
            return;
        }
    }

    // JSON-Dateien vor dem Speichern validieren
    if (isJson) {
        const validation = validateJson(editor.getValue());
        if (!validation.valid) {
            let errorMsg = 'JSON-Syntaxfehler';
            if (validation.line) {
                errorMsg += ` in Zeile ${validation.line}`;
                editor.setCursor(validation.line - 1, 0);
                editor.focus();
            }
            errorMsg += `: ${validation.message}`;
            showToast(errorMsg, 'error');
            return;
        }
    }

    try {
        const response = await apiFetch('api/files.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filename: currentFile,
                content: editor.getValue()
            })
        });

        if (!response.ok) {
            const result = await response.json().catch(() => ({}));
            showToast(result.error || `Fehler: ${response.status}`, 'error');
            return;
        }

        const result = await response.json();

        if (result.success) {
            isModified = false;
            updateSaveButton();
            showToast('Datei gespeichert', 'success');
        } else {
            showToast(result.error || 'Fehler beim Speichern', 'error');
        }
    } catch (error) {
        console.error('Save error:', error);
        showToast('Netzwerkfehler: ' + error.message, 'error');
    }
}

// Neue Datei Modal
function showNewFileModal() {
    document.getElementById('newFileModal').classList.add('active');
    document.getElementById('newFileName').value = '';
    document.getElementById('newFileName').focus();
}

function hideNewFileModal() {
    document.getElementById('newFileModal').classList.remove('active');
}

async function createNewFile() {
    const filename = document.getElementById('newFileName').value.trim();

    if (!filename) {
        showToast('Bitte Dateiname eingeben', 'error');
        return;
    }

    // Prüfen ob gültige Endung
    const validExtensions = ['.yaml', '.yml', '.md', '.markdown', '.txt', '.json'];
    const hasValidExtension = validExtensions.some(ext => filename.toLowerCase().endsWith(ext));

    if (!hasValidExtension) {
        showToast('Ungültige Dateiendung. Erlaubt: yaml, yml, md, markdown, txt, json', 'error');
        return;
    }

    try {
        const response = await apiFetch('api/files.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filename: filename,
                content: ''
            })
        });

        const result = await response.json();

        if (result.success) {
            hideNewFileModal();
            showToast('Datei erstellt', 'success');
            await loadFiles();
            openFile(filename);
        } else {
            showToast(result.error || 'Fehler beim Erstellen', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

// Löschen Modal
function showDeleteModal(filename) {
    fileToDelete = filename;
    document.getElementById('deleteFileName').textContent = filename;
    document.getElementById('deleteModal').classList.add('active');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    fileToDelete = null;
}

// Umbenennen Modal
function showRenameModal(filename) {
    fileToRename = filename;
    document.getElementById('oldFileName').textContent = filename;
    document.getElementById('renameFileName').value = filename;
    document.getElementById('renameModal').classList.add('active');
    const input = document.getElementById('renameFileName');
    input.focus();
    // Dateiname ohne Extension selektieren
    const lastDot = filename.lastIndexOf('.');
    if (lastDot > 0) {
        input.setSelectionRange(0, lastDot);
    }
}

function hideRenameModal() {
    document.getElementById('renameModal').classList.remove('active');
    fileToRename = null;
}

async function confirmRename() {
    if (!fileToRename) return;

    const newName = document.getElementById('renameFileName').value.trim();

    if (!newName) {
        showToast('Bitte neuen Dateinamen eingeben', 'error');
        return;
    }

    if (newName === fileToRename) {
        hideRenameModal();
        return;
    }

    // Prüfen ob gültige Endung
    const validExtensions = ['.yaml', '.yml', '.md', '.markdown', '.txt', '.json'];
    const hasValidExtension = validExtensions.some(ext => newName.toLowerCase().endsWith(ext));

    if (!hasValidExtension) {
        showToast('Ungültige Dateiendung. Erlaubt: yaml, yml, md, markdown, txt, json', 'error');
        return;
    }

    try {
        const response = await apiFetch('api/files.php', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                oldName: fileToRename,
                newName: newName
            })
        });

        const result = await response.json();

        if (result.success) {
            hideRenameModal();
            showToast('Datei umbenannt', 'success');

            // Wenn die umbenannte Datei gerade geöffnet war, neue Datei öffnen
            if (currentFile === fileToRename) {
                currentFile = newName;
                document.getElementById('editorTitle').textContent = newName;
            }

            loadFiles();
        } else {
            showToast(result.error || 'Fehler beim Umbenennen', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

async function confirmDelete() {
    if (!fileToDelete) return;

    try {
        const response = await apiFetch(`api/files.php?file=${encodeURIComponent(fileToDelete)}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            hideDeleteModal();
            showToast('Datei gelöscht', 'success');

            // Wenn die gelöschte Datei gerade geöffnet war
            if (currentFile === fileToDelete) {
                currentFile = null;
                isModified = false;
                editor.setValue('');
                editor.getWrapperElement().style.display = 'none';
                document.getElementById('noFileMessage').style.display = 'flex';
                document.getElementById('editorTitle').textContent = 'Keine Datei ausgewählt';
                updateSaveButton();
            }

            loadFiles();
        } else {
            showToast(result.error || 'Fehler beim Löschen', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

// Toast-Benachrichtigungen
function showToast(message, type = 'success') {
    // Bei Berechtigungsfehlern Modal anzeigen statt Toast
    if (type === 'error' && (message.includes('chmod') || message.includes('Schreibrechte') || message.includes('Permission'))) {
        showErrorModal(message);
        return;
    }

    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    // Fehler-Toasts länger anzeigen
    const duration = type === 'error' ? 6000 : 3000;
    setTimeout(() => {
        toast.remove();
    }, duration);
}

// Fehler-Modal für wichtige Fehlermeldungen
function showErrorModal(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorModal').classList.add('active');
}

function hideErrorModal() {
    document.getElementById('errorModal').classList.remove('active');
}

// Passwort ändern Modal
function showPasswordModal() {
    document.getElementById('passwordModal').classList.add('active');
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('currentPassword').focus();
}

function hidePasswordModal() {
    document.getElementById('passwordModal').classList.remove('active');
}

// Tastaturkürzel Modal
function showShortcutsModal() {
    document.getElementById('shortcutsModal').classList.add('active');
}

function hideShortcutsModal() {
    document.getElementById('shortcutsModal').classList.remove('active');
}

async function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (!currentPassword || !newPassword || !confirmPassword) {
        showToast('Alle Felder müssen ausgefüllt werden', 'error');
        return;
    }

    if (newPassword !== confirmPassword) {
        showToast('Neue Passwörter stimmen nicht überein', 'error');
        return;
    }

    try {
        const response = await apiFetch('api/password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            })
        });

        const result = await response.json();

        if (result.success) {
            hidePasswordModal();
            showToast('Passwort erfolgreich geändert', 'success');
        } else {
            showToast(result.error || 'Fehler beim Ändern des Passworts', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

// Hilfsfunktion für HTML-Escaping
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Markdown Preview Funktionen
function togglePreview() {
    if (previewActive) {
        hidePreview();
    } else {
        showPreview();
    }
}

function showPreview() {
    previewActive = true;
    document.getElementById('previewPane').classList.add('active');
    document.getElementById('btnPreview').classList.add('active');
    updatePreview();
    editor.refresh();
}

function hidePreview() {
    previewActive = false;
    document.getElementById('previewPane').classList.remove('active');
    document.getElementById('btnPreview').classList.remove('active');
    editor.refresh();
}

function updatePreview() {
    const content = editor.getValue();
    const previewPane = document.getElementById('previewPane');

    if (typeof marked !== 'undefined') {
        // Marked.js Optionen
        marked.setOptions({
            breaks: true,
            gfm: true
        });
        const rawHtml = marked.parse(content);
        // XSS-Schutz mit DOMPurify
        if (typeof DOMPurify !== 'undefined') {
            previewPane.innerHTML = DOMPurify.sanitize(rawHtml);
        } else {
            previewPane.innerHTML = rawHtml;
        }
    } else {
        previewPane.innerHTML = '<p style="color: #e74c3c;">Markdown-Parser nicht geladen</p>';
    }
}

// Export als HTML
function exportAsHtml() {
    if (!currentFile || !isMarkdownFile(currentFile)) return;

    const content = editor.getValue();
    if (typeof marked === 'undefined') {
        showToast('Markdown-Parser nicht geladen', 'error');
        return;
    }

    marked.setOptions({ breaks: true, gfm: true });
    let html = marked.parse(content);
    if (typeof DOMPurify !== 'undefined') {
        html = DOMPurify.sanitize(html);
    }

    const fullHtml = `<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${escapeHtml(currentFile.replace(/\.[^/.]+$/, ''))}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; color: #333; }
        h1, h2, h3 { color: #1a1a2e; border-bottom: 1px solid #eee; padding-bottom: 0.3rem; }
        pre { background: #f5f5f5; padding: 1rem; border-radius: 5px; overflow-x: auto; }
        code { background: #f0f0f0; padding: 0.2rem 0.4rem; border-radius: 3px; font-family: monospace; }
        pre code { background: none; padding: 0; }
        blockquote { border-left: 4px solid #4a90d9; margin: 1rem 0; padding-left: 1rem; color: #666; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; }
        th { background: #f5f5f5; }
        a { color: #4a90d9; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
${html}
</body>
</html>`;

    const blob = new Blob([fullHtml], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = currentFile.replace(/\.[^/.]+$/, '.html');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showToast('HTML exportiert', 'success');
}

// Export als PDF (über Druckdialog)
function exportAsPdf() {
    if (!currentFile || !isMarkdownFile(currentFile)) return;

    const content = editor.getValue();
    if (typeof marked === 'undefined') {
        showToast('Markdown-Parser nicht geladen', 'error');
        return;
    }

    marked.setOptions({ breaks: true, gfm: true });
    let html = marked.parse(content);
    if (typeof DOMPurify !== 'undefined') {
        html = DOMPurify.sanitize(html);
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>${escapeHtml(currentFile.replace(/\.[^/.]+$/, ''))}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 100%; margin: 2rem; line-height: 1.6; color: #333; }
        h1, h2, h3 { color: #1a1a2e; border-bottom: 1px solid #eee; padding-bottom: 0.3rem; }
        pre { background: #f5f5f5; padding: 1rem; border-radius: 5px; overflow-x: auto; }
        code { background: #f0f0f0; padding: 0.2rem 0.4rem; border-radius: 3px; font-family: monospace; }
        pre code { background: none; padding: 0; }
        blockquote { border-left: 4px solid #4a90d9; margin: 1rem 0; padding-left: 1rem; color: #666; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; }
        th { background: #f5f5f5; }
        a { color: #4a90d9; }
        img { max-width: 100%; height: auto; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
${html}
</body>
</html>`);
    printWindow.document.close();

    printWindow.onload = function() {
        printWindow.print();
    };
}

// Markdown Formatierung einfügen
function insertFormat(type) {
    const selection = editor.getSelection();
    const cursor = editor.getCursor();
    let replacement = '';
    let cursorOffset = 0;

    switch (type) {
        case 'bold':
            replacement = selection ? `**${selection}**` : '**text**';
            cursorOffset = selection ? 0 : -2;
            break;
        case 'italic':
            replacement = selection ? `*${selection}*` : '*text*';
            cursorOffset = selection ? 0 : -1;
            break;
        case 'strikethrough':
            replacement = selection ? `~~${selection}~~` : '~~text~~';
            cursorOffset = selection ? 0 : -2;
            break;
        case 'h1':
            replacement = `# ${selection || 'Überschrift'}`;
            break;
        case 'h2':
            replacement = `## ${selection || 'Überschrift'}`;
            break;
        case 'h3':
            replacement = `### ${selection || 'Überschrift'}`;
            break;
        case 'ul':
            replacement = selection
                ? selection.split('\n').map(line => `- ${line}`).join('\n')
                : '- Listenpunkt';
            break;
        case 'ol':
            replacement = selection
                ? selection.split('\n').map((line, i) => `${i + 1}. ${line}`).join('\n')
                : '1. Listenpunkt';
            break;
        case 'checklist':
            replacement = selection
                ? selection.split('\n').map(line => `- [ ] ${line}`).join('\n')
                : '- [ ] Aufgabe';
            break;
        case 'link':
            replacement = selection ? `[${selection}](url)` : '[Linktext](url)';
            break;
        case 'image':
            replacement = selection ? `![${selection}](url)` : '![Beschreibung](url)';
            break;
        case 'code':
            replacement = selection ? `\`${selection}\`` : '`code`';
            cursorOffset = selection ? 0 : -1;
            break;
        case 'codeblock':
            replacement = selection ? `\`\`\`\n${selection}\n\`\`\`` : '```\ncode\n```';
            break;
        case 'quote':
            replacement = selection
                ? selection.split('\n').map(line => `> ${line}`).join('\n')
                : '> Zitat';
            break;
        case 'hr':
            replacement = '\n---\n';
            break;
        case 'table':
            replacement = '| Spalte 1 | Spalte 2 | Spalte 3 |\n|----------|----------|----------|\n| Zelle 1  | Zelle 2  | Zelle 3  |';
            break;
    }

    editor.replaceSelection(replacement);
    editor.focus();

    // Cursor positionieren wenn nötig
    if (cursorOffset !== 0 && !selection) {
        const newCursor = editor.getCursor();
        editor.setCursor({ line: newCursor.line, ch: newCursor.ch + cursorOffset });
    }
}

// Enter-Taste im Modal
document.getElementById('newFileName').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        createNewFile();
    }
});

document.getElementById('renameFileName').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        confirmRename();
    }
});

// Modal schließen bei Klick außerhalb
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});

// Warnung bei Verlassen der Seite mit ungespeicherten Änderungen
window.addEventListener('beforeunload', (e) => {
    if (isModified) {
        e.preventDefault();
        e.returnValue = '';
    }
});
