<?php
require __DIR__ . '/lib_clicks.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

function read_raw_body(): string {
    $raw = file_get_contents('php://input');
    return is_string($raw) ? $raw : '';
}

function parse_payload(): array {
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    // JSON
    if (strpos($contentType, 'application/json') !== false) {
        $raw = read_raw_body();
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // Form-data / x-www-form-urlencoded
    if (!empty($_POST) && is_array($_POST)) {
        return $_POST;
    }

    // Fallback: try decode raw as JSON
    $raw = read_raw_body();
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$payload = parse_payload();
$target = trim((string)($payload['target'] ?? ''));
$source = trim((string)($payload['source'] ?? ''));
$label = trim((string)($payload['label'] ?? ''));

// Basic allow-list: only store reasonably-sized strings
if ($target === '' || strlen($target) > 2000) {
    http_response_code(204);
    exit;
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

clicks_store([
    'created_at' => gmdate('c'),
    'target' => $target,
    'label' => $label,
    'source' => $source,
    'ip' => $ip,
    'user_agent' => $ua,
]);

http_response_code(204);
exit;
