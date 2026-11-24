<?php
declare(strict_types=1);
require_once __DIR__ . '/src/db_connection.php';
try {
    $db = db_connection();
    echo 'Database connection successful' . PHP_EOL;
    echo 'PostgreSQL version: ' . $db->query('SELECT version()')->fetchColumn() . PHP_EOL;
    try {
        echo 'PostGIS version: ' . $db->query('SELECT PostGIS_Full_Version()')->fetchColumn() . PHP_EOL;
    } catch (PDOException $exception) {
        echo 'PostGIS is not installed; coordinate columns still work.' . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database connection failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
