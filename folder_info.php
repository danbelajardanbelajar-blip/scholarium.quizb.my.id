<?php
header('Content-Type: application/json; charset=utf-8');
// Simple endpoint to return folder metadata (name, total size, file counts)
// Requires composer autoload and service account JSON

$autoloadCandidates = [
    __DIR__ . '/../familyhood.my.id/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
$found = false;
foreach ($autoloadCandidates as $c) {
    if (file_exists($c)) { require_once $c; $found = true; break; }
}
if (!$found) {
    http_response_code(500);
    echo json_encode(['error' => 'autoload_missing']);
    exit;
}

$serviceAccountPath = __DIR__ . '/config/service-account.json';
if (!file_exists($serviceAccountPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'service_account_missing']);
    exit;
}

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing_id']);
    exit;
}

$client = new Google_Client();
$client->setAuthConfig($serviceAccountPath);
$client->addScope(Google_Service_Drive::DRIVE_READONLY);
$service = new Google_Service_Drive($client);

function listMetaRecursive($service, $folderId, &$filesOut) {
    $pageToken = null;
    do {
        $response = $service->files->listFiles([
            'q' => "'$folderId' in parents and trashed=false",
            'fields' => 'nextPageToken, files(id, name, mimeType, size)',
            'pageToken' => $pageToken
        ]);
        foreach ($response->files as $f) {
            $filesOut[] = $f;
            if ($f->mimeType === 'application/vnd.google-apps.folder') {
                listMetaRecursive($service, $f->id, $filesOut);
            }
        }
        $pageToken = $response->getNextPageToken();
    } while ($pageToken);
}

try {
    $meta = $service->files->get($id, ['fields' => 'id,name,mimeType']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'cannot_access', 'message' => $e->getMessage()]);
    exit;
}

$all = [];
listMetaRecursive($service, $id, $all);

$totalBytes = 0;
$fileCount = 0;
$googleDocsCount = 0;
foreach ($all as $f) {
    $mime = $f->mimeType ?? '';
    if ($mime === 'application/vnd.google-apps.folder') continue;
    $fileCount++;
    if (isset($f->size) && is_numeric($f->size)) {
        $totalBytes += (int)$f->size;
    } else {
        // google docs native types typically have no size; count separately
        if (strpos($mime, 'application/vnd.google-apps') === 0) $googleDocsCount++;
    }
}

function human_size($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $exp = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp];
}

echo json_encode([
    'id' => $id,
    'name' => $meta->getName(),
    'files' => $fileCount,
    'google_docs' => $googleDocsCount,
    'bytes' => $totalBytes,
    'human' => human_size($totalBytes),
]);
exit;
