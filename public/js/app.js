/**
 * YAML/MD Editor - Frontend JavaScript
 */

let editor = null;
let currentFile = null;
let isModified = false;
let fileToDelete = null;

// Editor initialisieren
document.addEventListener('DOMContentLoaded', () => {
    initEditor();
    loadFiles();
});

function initEditor() {
    const textarea = document.getElementById('editor');
    textarea.style.display = 'none';

    editor = CodeMirror.fromTextArea(textarea, {
        theme: 'dracula',
        lineNumbers: true,
        lineWrapping: true,
        indentUnit: 2,
        tabSize: 2,
        indentWithTabs: false,
        autofocus: false
    });

    editor.setSize('100%', '100%');
    editor.getWrapperElement().style.display = 'none';

    // Änderungen tracken
    editor.on('change', () => {
        if (currentFile) {
            isModified = true;
            updateSaveButton();
        }
    });

    // Tastenkombination Ctrl+S zum Speichern
    editor.setOption('extraKeys', {
        'Ctrl-S': (cm) => {
            saveFile();
            return false;
        }
    });
}

function updateSaveButton() {
    const btn = document.getElementById('btnSave');
    btn.disabled = !isModified || !currentFile;
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
                <span class="file-icon ${file.type}">${file.type === 'yaml' ? '📄' : '📝'}</span>
                <span>${escapeHtml(file.name)}</span>
            </div>
            <div class="file-actions">
                <button class="file-action-btn delete" onclick="event.stopPropagation(); showDeleteModal('${escapeHtml(file.name)}')" title="Löschen">🗑️</button>
            </div>
        </div>
    `).join('');
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
            const mode = result.data.type === 'yaml' ? 'yaml' : 'markdown';
            editor.setOption('mode', mode);
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
        } else {
            showToast(result.error || 'Fehler beim Öffnen der Datei', 'error');
        }
    } catch (error) {
        showToast('Netzwerkfehler', 'error');
    }
}

// YAML validieren
function validateYaml(content) {
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

// Datei speichern
async function saveFile() {
    if (!currentFile || !isModified) return;

    // YAML-Dateien vor dem Speichern validieren
    const isYaml = currentFile.toLowerCase().endsWith('.yaml') || currentFile.toLowerCase().endsWith('.yml');
    if (isYaml) {
        const validation = validateYaml(editor.getValue());
        if (!validation.valid) {
            let errorMsg = 'YAML-Syntaxfehler';
            if (validation.line) {
                errorMsg += ` in Zeile ${validation.line}`;
                // Zur fehlerhaften Zeile springen
                editor.setCursor(validation.line - 1, 0);
                editor.focus();
            }
            errorMsg += `: ${validation.message}`;
            showToast(errorMsg, 'error');
            return;
        }
    }

    try {
        const response = await fetch('api/files.php', {
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
    const validExtensions = ['.yaml', '.yml', '.md', '.markdown'];
    const hasValidExtension = validExtensions.some(ext => filename.toLowerCase().endsWith(ext));

    if (!hasValidExtension) {
        showToast('Ungültige Dateiendung. Erlaubt: yaml, yml, md, markdown', 'error');
        return;
    }

    try {
        const response = await fetch('api/files.php', {
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

async function confirmDelete() {
    if (!fileToDelete) return;

    try {
        const response = await fetch(`api/files.php?file=${encodeURIComponent(fileToDelete)}`, {
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

    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Fehler-Modal für wichtige Fehlermeldungen
function showErrorModal(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorModal').classList.add('active');
}

function hideErrorModal() {
    document.getElementById('errorModal').classList.remove('active');
}

// Hilfsfunktion für HTML-Escaping
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Enter-Taste im Modal
document.getElementById('newFileName').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        createNewFile();
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
