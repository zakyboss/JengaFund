<?php
/**
 * Database Connection Configuration for JengaFund
 */

// Simple .env loader logic for local development
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . '=' . trim($parts[1]));
        }
    }
}

// Fetch credentials from environment variables, or fallback to local defaults
$host    = getenv('DB_HOST') ?: '127.0.0.1';
$db      = getenv('DB_NAME') ?: 'jengafund';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') ?: ''; 
$charset = getenv('CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log the error internally and hide sensitive details from the user
    error_log("JengaFund Connection Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database connection failed. Please contact the administrator.');
}

// Show success message only if this file is accessed directly (not via include/require)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "Connected successfully to the database: <strong>" . htmlspecialchars($db) . "</strong>";
}

return $pdo;