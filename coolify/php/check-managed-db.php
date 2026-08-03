<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = getenv('WORDPRESS_DB_HOST') ?: '';
$dbName = getenv('WORDPRESS_DB_NAME') ?: '';
$dbUser = getenv('WORDPRESS_DB_USER') ?: '';
$dbPass = getenv('WORDPRESS_DB_PASSWORD') ?: '';
$caFile = '/run/secrets/digitalocean-mysql-ca.crt';

if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPass === '') {
    fwrite(STDERR, "ERROR: One or more WORDPRESS_DB_* variables are empty.\n");
    exit(2);
}

if (!is_readable($caFile)) {
    fwrite(STDERR, "ERROR: DigitalOcean CA certificate is not readable at {$caFile}.\n");
    exit(2);
}

$host = $dbHost;
$port = 3306;

if (preg_match('/^\[(.+)]:(\d+)$/', $dbHost, $matches)) {
    $host = $matches[1];
    $port = (int) $matches[2];
} elseif (substr_count($dbHost, ':') === 1) {
    [$host, $portValue] = explode(':', $dbHost, 2);
    $port = (int) $portValue;
}

$connection = mysqli_init();
mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
mysqli_ssl_set($connection, null, null, $caFile, null, null);

$connected = mysqli_real_connect(
    $connection,
    $host,
    $dbUser,
    $dbPass,
    $dbName,
    $port,
    null,
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    fwrite(STDERR, 'MYSQL ERROR ' . mysqli_connect_errno() . ': ' . mysqli_connect_error() . "\n");
    exit(1);
}

$result = mysqli_query($connection, "SHOW STATUS LIKE 'Ssl_cipher'");
$row = $result ? mysqli_fetch_assoc($result) : null;
$cipher = $row['Value'] ?? '';

if ($cipher === '') {
    fwrite(STDERR, "ERROR: MySQL connected without a TLS cipher.\n");
    exit(1);
}

fwrite(STDOUT, "OK: Connected to Managed MySQL using TLS cipher {$cipher}.\n");
