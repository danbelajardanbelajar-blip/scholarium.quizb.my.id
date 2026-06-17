<?php
// Download Google Drive folder as ZIP using service account
// Requires: composer require google/apiclient (library di ../familyhood.my.id/vendor/google/)

// Locate Composer autoload (try a few known locations)
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
    echo 'Composer autoload not found. Please ensure google/apiclient is installed.';
    exit;
}

// Prevent PHP warnings/deprecation messages from being printed (they corrupt ZIP output)
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/config/download_folder_php_errors.log');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Path to service account JSON
$serviceAccountPath = __DIR__ . '/config/service-account.json';

if (!file_exists($serviceAccountPath)) {
    http_response_code(500);
    echo 'Service account JSON not found.';
    exit;
}

$folderId = isset($_GET['id']) ? $_GET['id'] : '';
if ($folderId === '') {
    http_response_code(400);
    echo 'Missing folder ID.';
    exit;
}

if (!class_exists('Google_Client')) {
    http_response_code(500);
    echo 'Google Client library not loaded.';
    exit;
}

$client = new Google_Client();
$client->setAuthConfig($serviceAccountPath);
$client->addScope(Google_Service_Drive::DRIVE_READONLY);
$service = new Google_Service_Drive($client);

// Logging helper (temporary) - safe file inside config/, rotated manually
$LOG_PATH = __DIR__ . '/config/download_folder.log';
function log_msg($m) {
    global $LOG_PATH;
    $t = '['.date('c').'] ' . (is_string($m) ? $m : json_encode($m)) . "\n";
    @file_put_contents($LOG_PATH, $t, FILE_APPEND | LOCK_EX);
}

log_msg('Starting download request for folder: ' . $folderId);

try {
    // main flow begins


function listFilesRecursive($service, $folderId, $path = '') {
    $files = [];
    $pageToken = null;
    do {
        $response = $service->files->listFiles([
            'q' => "'$folderId' in parents and trashed=false",
            'fields' => 'nextPageToken, files(id, name, mimeType)',
            'pageToken' => $pageToken
        ]);
        foreach ($response->files as $file) {
            $filePath = $path . $file->name;
            if ($file->mimeType === 'application/vnd.google-apps.folder') {
                // include folder marker (empty dir)
                $files[] = ['id' => $file->id, 'name' => $file->name, 'path' => $filePath . '/', 'is_folder' => true];
                $files = array_merge($files, listFilesRecursive($service, $file->id, $filePath . '/'));
            } else {
                $files[] = ['id' => $file->id, 'name' => $file->name, 'path' => $filePath, 'mimeType' => $file->mimeType];
            }
        }
        $pageToken = $response->getNextPageToken();
    } while ($pageToken);
    return $files;
}

// Ensure ZipArchive available and set no time limit for large downloads
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'PHP ZipArchive extension is required.';
    exit;
}
set_time_limit(0);

// Determine working temp directory (prefer system temp, but fall back to project tmp if necessary)
$systemTmp = sys_get_temp_dir();
$open_basedir = ini_get('open_basedir') ?: '';
$workDir = $systemTmp;
$canUseSystemTmp = is_writable($systemTmp) && (!$open_basedir || strpos($open_basedir, $systemTmp) !== false);
if (!$canUseSystemTmp) {
    $altTmp = __DIR__ . '/tmp';
    if (!is_dir($altTmp)) @mkdir($altTmp, 0755, true);
    if (is_writable($altTmp)) {
        $workDir = $altTmp;
        log_msg('Using project tmp dir for temp files: ' . $workDir);
    } else {
        log_msg('Project tmp dir not writable: ' . $altTmp . ' and system tmp not usable: ' . $systemTmp);
    }
}

// Test we can access folder metadata (permission check)
try {
    $folderMeta = $service->files->get($folderId, ['fields' => 'id,name,mimeType']);
    if ($folderMeta->getMimeType() !== 'application/vnd.google-apps.folder') {
        // It's acceptable if user passes a file ID — still handle
        $folderName = $folderMeta->getName();
    } else {
        $folderName = $folderMeta->getName();
    }
} catch (Exception $e) {
    log_msg('Permission/metadata error: ' . $e->getMessage());
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Cannot access folder: ' . htmlspecialchars($e->getMessage()) . "\n";
    echo 'Ensure the folder is shared with the service account email: ' . htmlspecialchars(getenv('SERVICE_ACCOUNT_EMAIL') ?: 'scholarium@scholarium-482812.iam.gserviceaccount.com');
    exit;
}

$files = listFilesRecursive($service, $folderId);
log_msg('Files found: ' . count($files));
if (empty($files)) {
    http_response_code(404);
    echo 'No files found in folder.';
    exit;
}

$zip = new ZipArchive();
$tmpZip = tempnam($workDir, 'gdrivezip');
if ($tmpZip === false) {
    log_msg('tempnam failed in workDir=' . $workDir . ' falling back to sys tmp');
    $tmpZip = tempnam($systemTmp, 'gdrivezip');
}
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    log_msg('Failed to create temporary ZIP at ' . $tmpZip);
    http_response_code(500);
    echo 'Failed to create temporary ZIP.';
    exit;
}

// Helper map for Google Docs export types and extensions
function getExportMimeAndExt($gMime) {
    $map = [
        'application/vnd.google-apps.document' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'ext' => 'docx'],
        'application/vnd.google-apps.spreadsheet' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => 'xlsx'],
        'application/vnd.google-apps.presentation' => ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'ext' => 'pptx'],
        'application/vnd.google-apps.drawing' => ['mime' => 'image/png', 'ext' => 'png'],
    ];
    return $map[$gMime] ?? ['mime' => 'application/pdf', 'ext' => 'pdf'];
}

$tmpFiles = [];
foreach ($files as $file) {
    if (!empty($file['is_folder'])) {
        // Add directory entry
        $zip->addEmptyDir($file['path']);
        continue;
    }
    try {
        $remoteMime = $file['mimeType'] ?? '';
        $safeEntryPath = str_replace('\\', '/', $file['path']); // ensure forward slashes in ZIP
        log_msg('Starting download of file: ' . $file['id'] . ' -> ' . $safeEntryPath . ' (mime=' . $remoteMime . ')');

        // Create a temporary file to stream into
        $tmpFile = tempnam($workDir, 'gfile_');
        if ($tmpFile === false) throw new Exception('Failed to create temp file in workDir=' . $workDir);
        $fh = fopen($tmpFile, 'wb');
        if ($fh === false) throw new Exception('Failed to open temp file for writing: ' . $tmpFile);

        if (strpos($remoteMime, 'application/vnd.google-apps') === 0) {
            // Export Google Docs types
            $export = getExportMimeAndExt($remoteMime);
            $resp = $service->files->export($file['id'], $export['mime'], ['alt' => 'media']);
            $body = $resp->getBody();
            // Stream body to temp file
            if (method_exists($body, 'detach')) {
                $res = $body->detach();
                if (is_resource($res)) {
                    stream_copy_to_stream($res, $fh);
                    @fclose($res);
                } else {
                    // fallback
                    fwrite($fh, $body->getContents());
                }
            } else {
                fwrite($fh, $body->getContents());
            }
            fclose($fh);

            $entryName = $safeEntryPath . '.' . $export['ext'];
            $zip->addFile($tmpFile, $entryName);
            log_msg('Added exported Google Doc to ZIP: ' . $entryName);
        } else {
            // Regular file: stream and add
            $resp = $service->files->get($file['id'], ['alt' => 'media']);
            $body = $resp->getBody();
            if (method_exists($body, 'detach')) {
                $res = $body->detach();
                if (is_resource($res)) {
                    stream_copy_to_stream($res, $fh);
                    @fclose($res);
                } else {
                    fwrite($fh, $body->getContents());
                }
            } else {
                fwrite($fh, $body->getContents());
            }
            fclose($fh);

            $zip->addFile($tmpFile, $safeEntryPath);
            log_msg('Added file to ZIP: ' . $safeEntryPath);
        }

        // keep temp files until ZIP successfully closed
        $tmpFiles[] = $tmpFile;

    } catch (Exception $e) {
        // Skip problematic file but record an entry
        log_msg('Error downloading file ' . $file['id'] . ': ' . $e->getMessage());
        $zip->addFromString((isset($safeEntryPath) ? $safeEntryPath : $file['id']) . '.ERROR.txt', 'Failed to download: ' . $e->getMessage());
    }
}

// Before closing, log temp files and environment
log_msg('About to close ZIP (numFiles=' . $zip->numFiles . ', tmpFiles=' . count($tmpFiles) . ')');
log_msg('sys_get_temp_dir=' . sys_get_temp_dir() . ', tmp example=' . (count($tmpFiles)? $tmpFiles[0] : 'none'));
log_msg('open_basedir=' . var_export(ini_get('open_basedir'), true));
log_msg('is_writable(temp_dir)=' . (is_writable(sys_get_temp_dir()) ? 'yes' : 'no'));

$closeResult = $zip->close();
log_msg('zip->close returned: ' . var_export($closeResult, true));

// If close failed or file missing, attempt retry creating zip directly in config dir
if (!$closeResult || !file_exists($tmpZip) || filesize($tmpZip) === 0) {
    log_msg('ZIP close failed or tmp zip missing/empty; attempting fallback: create ZIP in config/ directory');
    $altZip = __DIR__ . '/config/fallback-drive-' . uniqid() . '.zip';
    $z2 = new ZipArchive();
    if ($z2->open($altZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        log_msg('Fallback: cannot open alt zip at ' . $altZip);
        // log environment details
        log_msg('Disk free space temp_dir=' . @disk_free_space(sys_get_temp_dir()) . ', config_dir_free=' . @disk_free_space(__DIR__ . '/config'));
    } else {
        foreach ($tmpFiles as $tf) {
            if (file_exists($tf)) {
                // derive entry name by reading a map? we stored entry names via zip->addFile; we need a mapping
                // For simplicity, add file using its basename; this loses folder structure but preserves content for debugging
                $z2->addFile($tf, basename($tf));
            } else {
                log_msg('Fallback: tmp file missing: ' . $tf);
            }
        }
        $z2->close();
        if (file_exists($altZip)) {
            log_msg('Fallback ZIP created at ' . $altZip . ' size=' . filesize($altZip));
            $tmpZip = $altZip; // use fallback as the file to deliver
        } else {
            log_msg('Fallback ZIP creation failed');
        }
    }
}

// Inspect ZIP and log entries for debugging
$zipEntries = [];
$za = new ZipArchive();
if (file_exists($tmpZip) && $za->open($tmpZip) === true) {
    for ($i = 0; $i < $za->numFiles; $i++) {
        $zipEntries[] = $za->getNameIndex($i);
    }
    $za->close();
    log_msg('ZIP entries count: ' . count($zipEntries) . ', names: ' . json_encode(array_slice($zipEntries, 0, 200)));
} else {
    log_msg('Failed to open tmp zip for inspection: ' . $tmpZip . ' exists=' . (file_exists($tmpZip)?'yes':'no'));
}

// Save debug copy (do not overwrite existing) for manual inspection
$debugCopy = __DIR__ . '/config/debug-drive-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $folderId) . '.zip';
if (!file_exists($debugCopy) && file_exists($tmpZip)) {
    copy($tmpZip, $debugCopy);
    log_msg('Saved debug ZIP copy to: ' . $debugCopy . ' (size=' . filesize($debugCopy) . ')');
} else {
    log_msg('Debug ZIP already exists at: ' . $debugCopy . ' (size=' . (file_exists($debugCopy)?filesize($debugCopy):0) . ')');
}

// Output ZIP for download with folder name
$rawFolderName = $folderName ?: $folderId;
// ASCII-safe filename fallback
$zipFilenameAscii = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $rawFolderName) . '.zip';
// UTF-8 encoded filename for modern clients
$zipFilenameUtf8 = rawurlencode($rawFolderName . '.zip');

// Make sure no previous output buffers corrupt binary stream
while (ob_get_level()) ob_end_clean();

// Ensure file size valid and suppress further error display during streaming
$filesize = @filesize($tmpZip);
if ($filesize === false || $filesize === 0) {
    log_msg('Refusing to send empty or invalid ZIP (size=' . var_export($filesize, true) . ') at ' . $tmpZip);
    header('Content-Type: text/plain');
    echo "Server error: created ZIP is empty. Periksa log: $LOG_PATH";
    exit;
}

@ini_set('display_errors', '0');
header('Content-Type: application/zip');
// Provide both ASCII filename and UTF-8 filename*
header('Content-Disposition: attachment; filename="' . $zipFilenameAscii . '"; filename*=UTF-8\'\'' . $zipFilenameUtf8);
header('Content-Length: ' . $filesize);
header('Cache-Control: private');

$fp = fopen($tmpZip, 'rb');
if ($fp) {
    // stream file to client
    fpassthru($fp);
    fclose($fp);
    log_msg('ZIP delivered successfully: ' . $zipName . ' (tmp=' . $tmpZip . ', size=' . $filesize . ')');

    // cleanup temporary files used for assembling ZIP
    $deleted = 0;
    foreach ($tmpFiles as $tf) {
        if (file_exists($tf)) { @unlink($tf); $deleted++; }
    }
    log_msg('Cleaned up tmp files: ' . $deleted . '/' . count($tmpFiles));

    // remove the tmp ZIP file (we saved debug copy earlier)
    @unlink($tmpZip);
} else {
    log_msg('Failed to open tmp zip for streaming: ' . $tmpZip);
    header('Content-Type: text/plain');
    echo "Server error: unable to open ZIP for streaming. Check log: $LOG_PATH";
    exit;
}

} catch (Throwable $e) {
    // top-level catch: log and return friendly message
    log_msg('Unhandled exception: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Internal Server Error. Check log: $LOG_PATH";
    if (isset($tmpZip) && file_exists($tmpZip)) @unlink($tmpZip);
    exit;
}

exit;
