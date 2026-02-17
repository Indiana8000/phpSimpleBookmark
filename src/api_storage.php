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
    private int    $countLevel = 0;

    public function __construct(
        string $dataFile = __DIR__ . '/backups/data.json',
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

        // Use new data.json
        if (file_exists($this->dataFile)) {
            $json = file_get_contents($this->dataFile);
            $data = json_decode($json, true);
        // Fallback old data.json
        } else if (file_exists(__DIR__ . '/data.json')) {
            $json = file_get_contents(__DIR__ . '/data.json');
            $data = json_decode($json, true);
            rename(__DIR__ . '/data.json', $this->dataFile);
        // Fallback to backup
        } else {
            $data = null;
        }

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

    public function findById(array &$list, int $id): ?array // Read-only Access!
    {
        foreach ($list as &$entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        return null;
    }

    public function findIndexById(array $list, int $id): ?int
    {
        foreach ($list as $i => $entry) {
            if ($entry['id'] === $id) {
                return $i;
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

    private function findOrCreateCategory(string $path, array &$data): int
    {
        if (!$path)
            throw new RuntimeException('Missing category name!');

        foreach ($data['categories'] as $c) {
            if ($c['name'] === $path) return $c['id'];
        }

        $id = $this->nextId($data['categories']);
        $now = date('c');
        $data['categories'][] = [
            'id'=>$id,
            'name'=>$path,
            'icon'=>'bi-folder',
            'created_at'=>$now,
            'modified_at'=>$now
        ];
        return $id;
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

    private function dateToTimestamp(?string $dateString): ?int
    {
        if (!$dateString) return null;
        try {
            $dt = new DateTime($dateString);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    private function timestampToDate(int $timestamp): string
    {
        return date('c', $timestamp);
    }

    private function emptyStructure(): array
    {
        $now = date('c');
        return [
            'categories' => [
                [
                    "id" => 1,
                    "name" => "General/General",
                    "icon" => "bi-stars",
                    "created_at" => $now,
                    "modified_at" => $now
                ],
                [
                    "id" => 2,
                    "name" => "General/News",
                    "icon" => "bi-folder",
                    "created_at" => $now,
                    "modified_at" => $now
                ]
            ],
            'items'      => [
                [
                    "id" => 1,
                    "category_id" => 1,
                    "title" => "Example Entry: Google",
                    "url" => "https://www.google.com/",
                    "content" => "Google Search",
                    "image" => "",
                    "preview" => "",
                    "created_at" => $now,
                    "modified_at" => $now
                ]
            ]
        ];
    }

    /* ==========================================================
     * Export / Import (Netscape Bookmark Format)
     * ======================================================== */

    public function exportBookmarks(string $filename = 'bookmarks.html'): void
    {
        $data = $this->load();

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
        echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
        echo "<TITLE>Bookmarks</TITLE>\n";
        echo "<H1>Bookmarks</H1>\n";
        echo "<DL><p>\n";

        foreach ($data['categories'] as $cat) {
            $catAddDate = $this->dateToTimestamp($cat['created_at'] ?? null);
            $catModDate = $this->dateToTimestamp($cat['modified_at'] ?? null);
            $catDateAttr = '';
            if ($catAddDate) $catDateAttr .= ' ADD_DATE="' . $catAddDate . '"';
            if ($catModDate) $catDateAttr .= ' LAST_MODIFIED="' . $catModDate . '"';
            echo "<DT><H3$catDateAttr>" . htmlspecialchars($cat['name']) . "</H3>\n";
            echo "<DL><p>\n";

            foreach ($data['items'] as $item) {
                if ($item['category_id'] !== $cat['id']) continue;

                $iconAttr = '';
                if (!empty($item['image'])) {
                    $path = __DIR__.'/'.$item['image'];
                    if (file_exists($path)) {
                        $mime = mime_content_type($path);
                        $base64 = base64_encode(file_get_contents($path));
                        $iconAttr = ' ICON="data:'.$mime.';base64,'.$base64.'"';
                    }
                }

                $addDate = $this->dateToTimestamp($item['created_at'] ?? null);
                $modDate = $this->dateToTimestamp($item['modified_at'] ?? null);
                $dateAttr = '';
                if ($addDate) $dateAttr .= ' ADD_DATE="' . $addDate . '"';
                if ($modDate) $dateAttr .= ' LAST_MODIFIED="' . $modDate . '"';

                $url   = htmlspecialchars($item['url'] ?? '#');
                $title = htmlspecialchars($item['title'] ?? 'Untitled');
                $desc  = htmlspecialchars($item['content'] ?? '');
                echo "<DT><A$iconAttr$dateAttr HREF=\"$url\" DESCRIPTION=\"$desc\">$title</A>\n";
            }

            echo "</DL><p>\n";
        }

        echo "</DL><p>\n";
        exit;
    }

    public function importBookmarks(string $file)
    {
        $html = file_get_contents($file);
        $html = str_replace('</A>', '</A></DT>', $html);

        $dir = __DIR__ . "/uploads/thumb/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $dl = $dom->getElementsByTagName('dl')->item(0);
        $data = $this->load();
        $this->countLevel = 0;
        $this->importBookmarkNode($dl, '', $data);
        $this->save($data);
    }

    private function importBookmarkNode($node, string $path, array &$data)
    {
        $this->countLevel++;
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'dt') {
                $dtChild = $child->firstElementChild;
                if ($dtChild->nodeName === 'h3') {
                    // Category
                    $name = trim($dtChild->textContent);
                    $newPath = $path ? "$path/$name" : $name;
                    error_log(str_repeat('  ', $this->countLevel) . "==> " . $newPath);

                    $next = $child->nextElementSibling;

                    if ($next && $next->nodeName === 'dl') {
                        $this->importBookmarkNode($next, $newPath, $data);
                    }
                } else if ($dtChild->nodeName === 'a' && $path !== '') {
                    // Item
                    $url    = $dtChild->getAttribute('href');
                    $title  = trim($dtChild->textContent);
                    $desc   = $dtChild->getAttribute('description');
                    error_log(str_repeat('  ', $this->countLevel) . "--> " . $title);

                    // Ensure category exists (create if not) and get its ID
                    $newPath = $path;
                    if(!str_contains($newPath, '/')) $newPath .= "/001 - Root";
                    $catId  = $this->findOrCreateCategory($newPath, $data);
                    $itemId = $this->nextId($data['items']);

                    // Get timestamps from attributes if available (Netscape Bookmark Format)
                    $now = date('c');
                    $created_at = $now;
                    $modified_at = $now;

                    $addDate = $dtChild->getAttribute('add_date');
                    $lastMod = $dtChild->getAttribute('last_modified');
                    if ($addDate) {
                        $created_at = $this->timestampToDate(intval($addDate));
                    }
                    if ($lastMod) {
                        $modified_at = $this->timestampToDate(intval($lastMod));
                    }

                    // Create item with extracted data
                    $item = [
                        'id' => $itemId,
                        'category_id' => $catId,
                        'title' => $title,
                        'url' => $url,
                        'content' => $desc,
                        'image' => '',
                        'preview' => '',
                        'created_at' => $created_at,
                        'modified_at' => $modified_at
                    ];

                    // Extract ICON attribute if it's a data URI
                    $icon = $dtChild->getAttribute('icon');
                    if ($icon && str_starts_with($icon,'data:')) {
                        if (preg_match('/data:(.*);base64,(.*)/',$icon,$m)) {
                            $ext = explode('/',$m[1])[1] ?? 'png';
                            $bin = base64_decode($m[2]);

                            $file = "uploads/thumb/$itemId.$ext";
                            file_put_contents(__DIR__.'/'.$file, $bin);

                            $item['image'] = $file;
                        }
                    }
                    $data['items'][] = $item;
                }
            }
        }
        $this->countLevel--;
    }



}
