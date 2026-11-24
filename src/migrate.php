<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $db = db_connection();
    ensure_schema($db);

    $tableExists = $db->query("SELECT to_regclass('public.incident_reports')")->fetchColumn();
    if (!$tableExists) {
        throw new RuntimeException('The incident_reports table was not created.');
    }

    $postgisVersion = $db->query("SELECT extversion FROM pg_extension WHERE extname = 'postgis'")->fetchColumn();
    if ($postgisVersion === false) {
        error_log('[mdrrmo-api] PostGIS is not enabled; numeric coordinates remain available.');
        fwrite(STDOUT, 'Database schema ready; PostGIS is not enabled.' . PHP_EOL);
        exit(0);
    }

    $locationColumn = $db->query("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'incident_reports' AND column_name = 'location'")->fetchColumn();
    if ($locationColumn === false) {
        throw new RuntimeException('PostGIS is enabled but the incident location column is missing.');
    }

    fwrite(STDOUT, 'Database schema ready with PostGIS.' . PHP_EOL);
} catch (Throwable $error) {
    error_log('[mdrrmo-api] Database initialization failed: ' . $error->getMessage());
    fwrite(STDERR, 'Database initialization failed. Check DATABASE_URL and Render PostgreSQL configuration.' . PHP_EOL);
    exit(1);
}
