<?php
/**
 * FileManager für CRUD-Operationen auf YAML/MD-Dateien
 */

class FileManager
{
    private string $basePath;
    private string $backupPath;
    private array $allowedExtensions;
    private int $maxBackups;

    public function __construct()
    {
        $this->basePath = realpath(FILES_DIR) ?: FILES_DIR;
        $this->backupPath = $this->basePath . DIRECTORY_SEPARATOR . '.backups';
        $this->allowedExtensions = ALLOWED_EXTENSIONS;
        $this->maxBackups = defined('MAX_BACKUPS') ? MAX_BACKUPS : 15;

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Prüft ob Dateiendung erlaubt ist
     */
    private function isAllowedFile(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $this->allowedExtensions);
    }

    /**
     * Sichere Pfadprüfung gegen Directory Traversal
     */
    private function securePath(string $filename): ?string
    {
        $filename = basename($filename);
        $fullPath = $this->basePath . DIRECTORY_SEPARATOR . $filename;

        if (!$this->isAllowedFile($filename)) {
            return null;
        }

        return $fullPath;
    }

    /**
     * Alle Dateien auflisten
     */
    public function listFiles(): array
    {
        $files = [];

        if (!is_dir($this->basePath)) {
            return $files;
        }

        $iterator = new DirectoryIterator($this->basePath);

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->isAllowedFile($file->getFilename())) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'type' => $this->getFileType($file->getFilename())
                ];
            }
        }

        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * Dateityp ermitteln
     */
    private function getFileType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'yaml', 'yml' => 'yaml',
            'md', 'markdown' => 'markdown',
            'json' => 'json',
            'txt' => 'text',
            default => 'text'
        };
    }

    /**
     * Konvertiert UTF-8 Text zu ASCII (entfernt/ersetzt nicht-ASCII Zeichen)
     */
    private function toAscii(string $content): string
    {
        // Fancy quotes und andere Unicode-Zeichen durch ASCII ersetzen
        $replacements = [
            "\xE2\x80\x9C" => '"',  // "
            "\xE2\x80\x9D" => '"',  // "
            "\xE2\x80\x9E" => '"',  // „
            "\xE2\x80\x98" => "'",  // '
            "\xE2\x80\x99" => "'",  // '
            "\xE2\x80\x9A" => "'",  // ‚
            "\xE2\x80\x93" => '-',  // –
            "\xE2\x80\x94" => '-',  // —
            "\xE2\x80\xA6" => '...', // …
            "\xC2\xA0" => ' ',      // non-breaking space
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    /**
     * Backup einer Datei erstellen
     */
    private function createBackup(string $path, string $filename): void
    {
        if (!file_exists($path)) {
            return;
        }

        // Backup-Dateiname mit Datum und Uhrzeit
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = pathinfo($filename, PATHINFO_FILENAME) . '_' . $timestamp . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        $backupFile = $this->backupPath . DIRECTORY_SEPARATOR . $backupName;

        // Backup erstellen
        copy($path, $backupFile);

        // Alte Backups aufräumen
        $this->cleanupBackups($filename);
    }

    /**
     * Alte Backups löschen, nur die neuesten behalten
     */
    private function cleanupBackups(string $filename): void
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $pattern = $this->backupPath . DIRECTORY_SEPARATOR . $baseName . '_*.' . $ext;

        $backups = glob($pattern);
        if ($backups === false || count($backups) <= $this->maxBackups) {
            return;
        }

        // Nach Änderungsdatum sortieren (neueste zuerst)
        usort($backups, fn($a, $b) => filemtime($b) - filemtime($a));

        // Ältere Backups löschen
        $toDelete = array_slice($backups, $this->maxBackups);
        foreach ($toDelete as $file) {
            unlink($file);
        }
    }

    /**
     * Datei lesen
     */
    public function read(string $filename): ?array
    {
        $path = $this->securePath($filename);

        if ($path === null || !file_exists($path)) {
            return null;
        }

        return [
            'name' => $filename,
            'content' => file_get_contents($path),
            'type' => $this->getFileType($filename)
        ];
    }

    /**
     * Datei erstellen
     */
    public function create(string $filename, string $content = ''): bool
    {
        $path = $this->securePath($filename);

        if ($path === null) {
            throw new Exception("Ungültige Dateiendung. Erlaubt: " . implode(', ', $this->allowedExtensions));
        }

        if (file_exists($path)) {
            throw new Exception("Datei existiert bereits");
        }

        if (!is_writable($this->basePath)) {
            throw new Exception("Keine Schreibrechte. Bitte auf dem Host ausführen: chmod 777 " . $this->basePath);
        }

        // Konvertiere zu ASCII
        $content = $this->toAscii($content);

        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new Exception("Datei konnte nicht erstellt werden. Schreibrechte prüfen.");
        }
        return true;
    }

    /**
     * Datei aktualisieren
     */
    public function update(string $filename, string $content): bool
    {
        $path = $this->securePath($filename);

        if ($path === null || !file_exists($path)) {
            throw new Exception("Datei nicht gefunden");
        }

        if (!is_writable($path)) {
            throw new Exception("Keine Schreibrechte für diese Datei. Bitte auf dem Host ausführen: chmod 666 " . basename($path));
        }

        // Backup vor dem Speichern erstellen
        $this->createBackup($path, $filename);

        // Konvertiere zu ASCII
        $content = $this->toAscii($content);

        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new Exception("Datei konnte nicht gespeichert werden. Schreibrechte prüfen.");
        }
        return true;
    }

    /**
     * Datei umbenennen
     */
    public function rename(string $oldName, string $newName): bool
    {
        $oldPath = $this->securePath($oldName);
        $newPath = $this->securePath($newName);

        if ($oldPath === null || $newPath === null) {
            throw new Exception("Ungültige Dateiendung");
        }

        if (!file_exists($oldPath)) {
            throw new Exception("Quelldatei nicht gefunden");
        }

        if (file_exists($newPath)) {
            throw new Exception("Zieldatei existiert bereits");
        }

        return rename($oldPath, $newPath);
    }

    /**
     * Datei löschen
     */
    public function delete(string $filename): bool
    {
        $path = $this->securePath($filename);

        if ($path === null || !file_exists($path)) {
            throw new Exception("Datei nicht gefunden");
        }

        return unlink($path);
    }
}
