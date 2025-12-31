<?php
/**
 * storage.php
 *
 * Robuste JSON-Storage-Schicht mit:
 * - File Locking
 * - Atomic Writes
 * - Backups (Rolling)
 * - Validierung
 * - ID-Management
 * - Referential Integrity
 * - Self-Healing (Backup-Fallback)
 */

declare(strict_types=1);

class Storage
{
    private string $dataFile;
    private string $backupDir;
    private int    $maxBackups = 50;

    private ?array $cache = null;

    public function __construct(
        string $dataFile = __DIR__ . '/data.json',
        string $backupDir = __DIR__ . '/backups'
    ) {
        $this->dataFile  = $dataFile;
        $this->backupDir = $backupDir;

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /* ==========================================================
     * Public API
     * ======================================================== */

    public function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!file_exists($this->dataFile)) {
            $this->cache = $this->emptyStructure();
            return $this->cache;
        }

        $json = file_get_contents($this->dataFile);
        $data = json_decode($json, true);

        if ($data === null) {
            $data = $this->restoreFromBackup();
        }

        $this->validateStructure($data);
        $this->cache = $data;

        return $data;
    }

    public function save(array $data): void
    {
        $this->validateStructure($data);
        $this->validateIntegrity($data);

        $this->createBackup();

        $tmpFile = $this->dataFile . '.tmp';
        $json    = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('JSON encoding failed');
        }

        file_put_contents($tmpFile, $json, LOCK_EX);
        rename($tmpFile, $this->dataFile);

        $this->cache = $data;
        $this->cleanupBackups();

        clearstatcache();
    }

    /* ==========================================================
     * CRUD Helper
     * ======================================================== */

    public function nextId(array $list): int
    {
        return empty($list) ? 1 : max(array_column($list, 'id')) + 1;
    }

    public function findById(array &$list, int $id): ?array
    {
        foreach ($list as &$entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        return null;
    }

    public function deleteById(array &$list, int $id): bool
    {
        foreach ($list as $i => $entry) {
            if ($entry['id'] === $id) {
                unset($list[$i]);
                $list = array_values($list);
                return true;
            }
        }
        return false;
    }

    /* ==========================================================
     * Validation
     * ======================================================== */

    private function validateStructure(array $data): void
    {
        if (!isset($data['categories'], $data['items'])) {
            throw new RuntimeException('Invalid data structure');
        }

        if (!is_array($data['categories']) || !is_array($data['items'])) {
            throw new RuntimeException('Invalid data types');
        }
    }

    private function validateIntegrity(array $data): void
    {
        $catIds = array_column($data['categories'], 'id');

        foreach ($data['items'] as $item) {
            if (!in_array($item['category_id'], $catIds)) {
                throw new RuntimeException(
                    'Invalid category reference for item ' . $item['id']
                );
            }
        }
    }

    /* ==========================================================
     * Backup Handling
     * ======================================================== */

    private function createBackup(): void
    {
        if (!file_exists($this->dataFile)) {
            return;
        }

        $name = 'data_' . date('Ymd_His') . '.json';
        copy($this->dataFile, $this->backupDir . '/' . $name);
    }

    private function cleanupBackups(): void
    {
        $files = glob($this->backupDir . '/data_*.json');
        if (count($files) <= $this->maxBackups) {
            return;
        }

        sort($files);
        $remove = array_slice($files, 0, count($files) - $this->maxBackups);

        foreach ($remove as $file) {
            unlink($file);
        }
    }

    private function restoreFromBackup(): array
    {
        $files = glob($this->backupDir . '/data_*.json');
        rsort($files);

        foreach ($files as $file) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if ($data !== null) {
                return $data;
            }
        }

        return $this->emptyStructure();
    }

    /* ==========================================================
     * Helpers
     * ======================================================== */

    private function emptyStructure(): array
    {
        return [
            'categories' => [],
            'items'      => []
        ];
    }
}
