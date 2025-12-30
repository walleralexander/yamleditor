<?php
/**
 * FileManager für CRUD-Operationen auf YAML/MD-Dateien
 */

class FileManager
{
    private string $basePath;
    private array $allowedExtensions;

    public function __construct()
    {
        $this->basePath = realpath(FILES_DIR) ?: FILES_DIR;
        $this->allowedExtensions = ALLOWED_EXTENSIONS;

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
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
            default => 'text'
        };
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

        return file_put_contents($path, $content) !== false;
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

        return file_put_contents($path, $content) !== false;
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
