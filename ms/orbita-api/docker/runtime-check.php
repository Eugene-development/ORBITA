<?php
// Read configuration only. Never connect to a database or print secret values.
function invalidRuntime(string $message): never
{
    fwrite(STDERR, "Runtime configuration error: {$message}\n");
    exit(1);
}

$production = getenv('APP_ENV') === 'production';
$key = getenv('APP_KEY') ?: '';
if ($production || $key !== '') {
    if (str_starts_with($key, 'APP_KEY=')) {
        invalidRuntime('APP_KEY contains a second APP_KEY= prefix. Use only the existing key value.');
    }
    $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    if ($decoded === false || strlen($decoded) !== 32) {
        invalidRuntime('APP_KEY must contain the existing 32-byte AES-256 key. Do not rotate it during deployment.');
    }
}
if (!$production) {
    return;
}
if (filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    invalidRuntime('APP_DEBUG must be false in production.');
}
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $name) {
    $value = getenv($name);
    if ($value === false || trim($value) === '' || preg_match('/^(REPLACE_|CHANGE_ME)/', $value)) {
        invalidRuntime("{$name} must be set to the actual value for this project in the server .env.");
    }
}
if (getenv('DB_URL')) {
    invalidRuntime('DB_URL overrides DB_* settings; remove it when using the documented DB_* configuration.');
}
$ca = getenv('MYSQL_ATTR_SSL_CA') ?: '';
if ($ca === '') {
    invalidRuntime('MYSQL_ATTR_SSL_CA must point to cert/mysql-ca.pem included in the image.');
}
$ca = str_starts_with($ca, '/') ? $ca : dirname(__DIR__).'/'.$ca;
if (!is_readable($ca) || @openssl_x509_read(file_get_contents($ca)) === false) {
    invalidRuntime('The configured public MySQL CA is missing or invalid. Rebuild the image with cert/mysql-ca.pem.');
}
