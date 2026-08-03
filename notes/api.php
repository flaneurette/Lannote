<?php
/**
 * L A N N O T E - api.php
 *
 * Notes are stored as individual Markdown files under notes/data/, one
 * file per note, optionally grouped into category folders:
 *
 *   notes/data/notes.json.migrated        (old storage, kept as backup)
 *   notes/data/categories.json            (list of known categories)
 *   notes/data/my-uncategorized-note-1.md (no category -> lives at the root)
 *   notes/data/travel/trip-planning-2.md  (category "travel")
 *
 * Each .md file has a small front-matter header followed by a blank
 * line and then the raw note body:
 *
 *   ---
 *   id: 3
 *   title: My Note
 *   category: travel
 *   created: 2026-07-30T14:32:00+02:00
 *   updated: 2026-07-30T14:32:00+02:00
 *   ---
 *
 *   Note body goes here, in markdown.
 *
 * The id in the front matter is the source of truth for identity. The
 * FOLDER a file lives in is the source of truth for its category - so
 * moving a .md file into (or out of) a category folder by hand is
 * enough to re-categorize it; the front matter is kept in sync
 * automatically the next time the note list is scanned. Category
 * folders are created lazily, the first time a note is saved into
 * them, and removed again if they become empty.
 *
 * Endpoints (all under notes/api.php):
 *
 *   GET  ?action=loadnotes
 *        -> [{ "link": "1", "notename": "My Note", "category": "travel", "updated": "..." }, ...]
 *
 *   GET  ?action=loadnote&id=1
 *        -> { "title": "My Note", "note": "...", "category": "travel", "created": "...", "updated": "..." }
 *
 *   GET  ?action=loadcategories
 *        -> ["notes", "journal", "mundane", ...]
 *
 *   POST ?action=savenote
 *        body: { "id": "1" | null, "title": "...", "note": "...", "category": "travel" }
 *        -> { "success": true, "id": "1" }
 *
 *   POST ?action=deletenote
 *        body: { "id": "1" }
 *        -> { "success": true }
 */

session_start();
require __DIR__ . '/../assets/php/ip.php';
require __DIR__ . '/../constants.php';

$ip_ok = in_array($_SERVER['REMOTE_ADDR'], $allowed_ips);
if (!$ip_ok || empty($_SESSION['authed'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}


// header('Content-Type: application/json');

// Allow same-origin fetch() calls without extra config.
// If you're serving the frontend from a different origin, uncomment:
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST');
// header('Access-Control-Allow-Headers: Content-Type');

$dataDir        = '../'. SECURE_PATH . 'notes/data';

$categoriesFile = $dataDir . '/categories.json';
$defaultCategories = [
   "ai",
   "art",
   "contacts",
   "electronics",
   "finance",
   "fiction",
   "fantasy",
   "games",
   "health",
   "herbs",
   "hobby",
   "housekeeping",
   "ideas",
   "internet",
   "journal",
   "lyrics",
   "magic",
   "medical",
   "miscellaneous",
   "mundane",
   "music",
   "math",
   "nature",
   "notes",
   "occult",
   "poetry",
   "personal",
   "programming",
   "psychology",
   "recipes",
   "science",
   "shopping",
   "social",
   "snippets",
   "study",
   "survival",
   "travel",
   "team",
   "work",
   "writing"
];

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}
if (!file_exists($categoriesFile)) {
    file_put_contents($categoriesFile, json_encode($defaultCategories, JSON_PRETTY_PRINT));
}

function slugify(string $text): string {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'untitled';
    }
    return substr($text, 0, 60);
}

function sanitizeCategory(string $category): string {
    $category = trim($category);
    if ($category === '') {
        return '';
    }
    $category = strtolower($category);
    $category = preg_replace('/[^a-z0-9]+/', '-', $category);
    return trim($category, '-');
}

/**
 * The folder a note in this category should live in. Creates the
 * folder if it doesn't exist yet. Empty category -> $dataDir itself.
 */
function noteDirForCategory(string $dataDir, string $category): string {
    if ($category === '') {
        return $dataDir;
    }
    $dir = $dataDir . '/' . $category;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function noteFilePath(string $dataDir, string $category, int $id, string $title): string {
    $dir = noteDirForCategory($dataDir, $category);
    return $dir . '/' . slugify($title) . '-' . $id . '.md';
}

function cleanupEmptyCategoryDir(string $dataDir, string $dir): void {
    if ($dir === $dataDir || !is_dir($dir)) {
        return;
    }
    $contents = array_diff(scandir($dir), ['.', '..']);
    if (empty($contents)) {
        @rmdir($dir);
    }
}

/**
 * Serialize meta + body into the on-disk file format.
 */
function noteFileContents(array $meta, string $body): string {
    $lines = ['---'];
    foreach (['id', 'title', 'category', 'created', 'updated'] as $key) {
        $val = isset($meta[$key]) ? str_replace(["\r", "\n"], ' ', (string)$meta[$key]) : '';
        $lines[] = "$key: $val";
    }
    $lines[] = '---';
    return implode("\n", $lines) . "\n\n" . $body;
}

function writeNoteFile(string $path, array $meta, string $body): bool {
    return file_put_contents($path, noteFileContents($meta, $body)) !== false;
}

function parseNoteFile(string $path): ?array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $raw = str_replace("\r\n", "\n", $raw);

    if (strpos($raw, "---\n") === 0) {
        $closePos = strpos($raw, "\n---\n", 4);
        if ($closePos !== false) {
            $frontMatter = substr($raw, 4, $closePos - 4);
            $body = ltrim(substr($raw, $closePos + 5), "\n");

            $meta = [];
            foreach (explode("\n", $frontMatter) as $line) {
                if ($line === '') continue;
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $meta[trim($parts[0])] = trim($parts[1]);
                }
            }
            return ['meta' => $meta, 'body' => $body];
        }
    }

    // No (valid) front matter - whole file is the body.
    return [
        'meta' => ['title' => pathinfo($path, PATHINFO_FILENAME)],
        'body' => $raw,
    ];
}

function scanNotes(string $dataDir): array {
    $files = []; // path => category

    foreach (glob($dataDir . '/*.md') ?: [] as $f) {
        $files[$f] = '';
    }
    foreach (glob($dataDir . '/*', GLOB_ONLYDIR) ?: [] as $subdir) {
        $category = basename($subdir);
        foreach (glob($subdir . '/*.md') ?: [] as $f) {
            $files[$f] = $category;
        }
    }

    $parsed = [];
    $usedIds = [];
    foreach ($files as $file => $category) {
        $p = parseNoteFile($file);
        if ($p === null) continue;
        $p['category'] = $category;
        $parsed[$file] = $p;
        if (!empty($p['meta']['id'])) {
            $usedIds[] = (int)$p['meta']['id'];
        }
    }
    $nextId = ($usedIds ? max($usedIds) : 0) + 1;

    $notes = [];
    foreach ($parsed as $file => $p) {
        $meta = $p['meta'];
        $category = $p['category'];
        $needsRewrite = false;

        if (empty($meta['id'])) {
            $now = date('c');
            $meta = [
                'id'       => (int)$nextId++,
                'title'    => $meta['title'] ?? pathinfo($file, PATHINFO_FILENAME),
                'category' => $category,
                'created'  => $now,
                'updated'  => $now,
            ];
            $needsRewrite = true;
        } elseif (($meta['category'] ?? '') !== $category) {
            $meta['category'] = $category;
            $needsRewrite = true;
        }

        if ($needsRewrite) {
            writeNoteFile($file, $meta, $p['body']);
        }

        $notes[] = [
            'id'       => (int)$meta['id'],
            'title'    => $meta['title'] ?? '',
            'category' => $category,
            'created'  => $meta['created'] ?? null,
            'updated'  => $meta['updated'] ?? null,
            'note'     => $p['body'],
            'file'     => $file,
        ];
    }

    return $notes;
}

function findNoteById(array $notes, int $id): ?array {
    foreach ($notes as $n) {
        if ((int)$n['id'] === $id) {
            return $n;
        }
    }
    return null;
}

/**
 * Known categories: whatever's in categories.json, plus any category
 * folders that exist on disk but aren't listed yet (e.g. created by
 * hand). Newly discovered folders are appended, list order otherwise
 * preserved, and the file is rewritten if anything changed.
 */
function loadCategories(string $dataDir, string $categoriesFile): array {
    $raw = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
    $categories = is_array($raw) ? array_values($raw) : [];

    $merged = $categories;
    foreach (glob($dataDir . '/*', GLOB_ONLYDIR) ?: [] as $subdir) {
        $name = basename($subdir);
        if (!in_array($name, $merged, true)) {
            $merged[] = $name;
        }
    }

    if ($merged !== $categories) {
        file_put_contents($categoriesFile, json_encode($merged, JSON_PRETTY_PRINT));
    }

    return $merged;
}

/**
 * One-time migration: if an old notes.json exists and there are no .md
 * files yet, convert every entry into its own markdown file (all
 * uncategorized, at the root), then rename the json file out of the
 * way so this only runs once.
 */
function migrateJsonIfNeeded(string $dataDir): void {
    $jsonFile = $dataDir . '/notes.json';
    if (!file_exists($jsonFile)) {
        return;
    }
    $existingMd = glob($dataDir . '/*.md');
    if ($existingMd !== false && count($existingMd) > 0) {
        return;
    }

    $raw = file_get_contents($jsonFile);
    $notes = json_decode($raw, true);
    if (is_array($notes)) {
        foreach ($notes as $n) {
            if (!isset($n['id']) || $n['id'] === '' || $n['id'] === null) continue;
            $id = (int)$n['id'];
            $now = date('c');
            $meta = [
                'id'       => $id,
                'title'    => $n['title'] ?? '',
                'category' => '',
                'created'  => $n['created'] ?? $now,
                'updated'  => $n['updated'] ?? $now,
            ];
            $path = noteFilePath($dataDir, '', $id, $meta['title']);
            writeNoteFile($path, $meta, $n['note'] ?? '');
        }
    }

    rename($jsonFile, $jsonFile . '.migrated');
}

migrateJsonIfNeeded($dataDir);

// --- Router ---

if(isset($_GET['action'])) {
	$action = substr(htmlspecialchars($_GET['action']),0,30);
	} else {
  	$action = '';
}

// Require CSRF token for any state-changing action
if (in_array($action, ['savenote', 'deletenote']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !isset($body['csrf']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $body['csrf'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

switch ($action) {

    case 'loadnotes': {
        $notes = scanNotes($dataDir);

        // Sort by most recently updated first
        usort($notes, function ($a, $b) {
            return strcmp($b['updated'] ?? '', $a['updated'] ?? '');
        });

        $list = array_map(function ($n) {
            return [
                'link'     => (int)$n['id'],
                'notename' => $n['title'] !== '' ? $n['title'] : '(untitled)',
                'category' => $n['category'],
                'updated'  => $n['updated'] ?? null,
            ];
        }, $notes);

        echo json_encode($list);
        break;
    }

    case 'loadcategories': {
        echo json_encode(loadCategories($dataDir, $categoriesFile));
        break;
    }

    case 'loadnote': {
 
	if(isset($_GET['id'])) {
		$id = (int)$_GET['id'];
		} else {
	  	$id = '';
	}

        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            break;
        }

        $notes = scanNotes($dataDir);
        $note = findNoteById($notes, $id);

        if ($note === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Note not found']);
            break;
        }

        echo json_encode([
            'title'    => $note['title'],
            'note'     => $note['note'],
            'category' => $note['category'],
            'created'  => $note['created'],
            'updated'  => $note['updated'],
        ]);
        break;
    }

    case 'deletenote': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Use POST for deletenote']);
            break;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || empty($body['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            break;
        }

        $id = (int)$body['id'];
        $notes = scanNotes($dataDir);
        $note = findNoteById($notes, $id);

        if ($note === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Note not found']);
            break;
        }

        $oldDir = dirname($note['file']);
        if (!@unlink($note['file'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete note file']);
            break;
        }
        cleanupEmptyCategoryDir($dataDir, $oldDir);

        echo json_encode(['success' => true]);
        break;
    }

    case 'savenote': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Use POST for savenote']);
            break;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            break;
        }

        $id       = (isset($body['id']) && $body['id'] !== null && $body['id'] !== '') ? (int)$body['id'] : null;
        if ($id !== null && $id <= 0) {
            $id = null;
        }
        $title    = trim($body['title'] ?? '');
        $note     = $body['note'] ?? '';
        $category = sanitizeCategory($body['category'] ?? '');

        $notes = scanNotes($dataDir);
        $now = date('c'); // ISO 8601, e.g. 2026-07-30T14:32:00+02:00

        if ($id !== null && $id !== '') {
            // Update existing note
            $id = (int)$id;
            $existing = findNoteById($notes, $id);
            if ($existing === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Note not found']);
                break;
            }

            $meta = [
                'id'       => $id,
                'title'    => $title,
                'category' => $category,
                'created'  => $existing['created'] ?? $now,
                'updated'  => $now,
            ];

            $newPath = noteFilePath($dataDir, $category, $id, $title);

            if (!writeNoteFile($newPath, $meta, $note)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to write note file']);
                break;
            }

            // Title and/or category changed -> old file/folder may be stale.
            if ($newPath !== $existing['file']) {
                $oldDir = dirname($existing['file']);
                @unlink($existing['file']);
                cleanupEmptyCategoryDir($dataDir, $oldDir);
            }
        } else {
            // Create new note
            $maxId = 0;
            foreach ($notes as $n) {
                $maxId = max($maxId, (int)$n['id']);
            }
            $id = (int)($maxId + 1);

            $meta = [
                'id'       => $id,
                'title'    => $title,
                'category' => $category,
                'created'  => $now,
                'updated'  => $now,
            ];

            $path = noteFilePath($dataDir, $category, $id, $title);

            if (!writeNoteFile($path, $meta, $note)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to write note file']);
                break;
            }
        }

        echo json_encode(['success' => true, 'id' => (int)$id]);
        break;
    }

    default: {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
    }
}
