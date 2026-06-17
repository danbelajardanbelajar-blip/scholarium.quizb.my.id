<?php
// Diagnostic script to test service account JWT and token fetch
error_reporting(E_ALL);
ini_set('display_errors', 1);

$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../familyhood.my.id/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
$found = false;
foreach ($autoloadCandidates as $c) {
    if (file_exists($c)) { require_once $c; $found = true; echo "Loaded autoload: $c\n"; break; }
}
if (!$found) {
    echo "Composer autoload not found. Please ensure google/apiclient is installed.\n";
}

$configPath = __DIR__ . '/config/service-account.json';
if (!file_exists($configPath)) { echo "Service account JSON not found at $configPath\n"; exit(1); }

$json = json_decode(file_get_contents($configPath), true);
if (!is_array($json)) { echo "Failed to parse JSON\n"; exit(1); }

echo "Service account email: " . ($json['client_email'] ?? '(missing)') . "\n";
echo "Private key id: " . ($json['private_key_id'] ?? '(missing)') . "\n";

// Check private key can be parsed by OpenSSL
$priv = $json['private_key'] ?? '';
$ok = false;
if ($priv !== '') {
    $res = openssl_pkey_get_private($priv);
    if ($res === false) {
        echo "openssl_pkey_get_private() failed: " . openssl_error_string() . "\n";
    } else {
        echo "Private key parsed by OpenSSL OK.\n";
        openssl_free_key($res);
        $ok = true;
    }
} else {
    echo "private_key missing in JSON\n";
}

// Show time info
echo "Server time (UTC): " . gmdate('c') . "\n";

// Try to fetch token using Google Client
if (class_exists('Google_Client')) {
    try {
        $client = new Google_Client();
        $client->setAuthConfig($configPath);
        $client->addScope(Google_Service_Drive::DRIVE_READONLY);
        $token = $client->fetchAccessTokenWithAssertion();
        echo "fetchAccessTokenWithAssertion result:\n";
        var_export($token);
        echo "\n";
    } catch (Exception $e) {
        echo "fetchAccessTokenWithAssertion threw Exception: " . get_class($e) . " - " . $e->getMessage() . "\n";
        if (method_exists($e, 'getTraceAsString')) echo $e->getTraceAsString() . "\n";
    }
} else {
    echo "Google_Client not available; cannot fetch token.\n";
}

// Quick test: attempt to manually sign a payload with openssl_sign
if ($ok) {
    $payload = 'test';
    $res = openssl_pkey_get_private($priv);
    $sig = '';
    if (openssl_sign($payload, $sig, $res, OPENSSL_ALGO_SHA256)) {
        echo "openssl_sign succeeded, signature length: " . strlen($sig) . "\n";
    } else {
        echo "openssl_sign failed: " . openssl_error_string() . "\n";
    }
}

?>