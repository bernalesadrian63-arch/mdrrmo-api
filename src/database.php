<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

/** Create the reports table and optional PostGIS index if they do not exist. */
function ensure_schema(PDO $db): void
{
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS incident_reports (
            id BIGSERIAL PRIMARY KEY,
            incident_type VARCHAR(80) NOT NULL,
            description TEXT NOT NULL,
            persons_affected INTEGER NOT NULL DEFAULT 0 CHECK (persons_affected >= 0),
            latitude DOUBLE PRECISION NOT NULL CHECK (latitude BETWEEN -90 AND 90),
            longitude DOUBLE PRECISION NOT NULL CHECK (longitude BETWEEN -180 AND 180),
            gps_accuracy_m DOUBLE PRECISION CHECK (gps_accuracy_m IS NULL OR gps_accuracy_m >= 0),
            status VARCHAR(24) NOT NULL DEFAULT 'received',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    SQL);

    // PostGIS is optional on databases that do not provide the extension.
    try {
        $db->exec('CREATE EXTENSION IF NOT EXISTS postgis');
        $db->exec('ALTER TABLE incident_reports ADD COLUMN IF NOT EXISTS location GEOGRAPHY(POINT, 4326)');
        $db->exec('UPDATE incident_reports SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)::geography WHERE location IS NULL');
        $db->exec('CREATE INDEX IF NOT EXISTS incident_reports_location_idx ON incident_reports USING GIST (location)');
    } catch (PDOException $exception) {
        // Basic incident reporting still works if PostGIS is unavailable.
        error_log('PostGIS setup skipped: ' . $exception->getMessage());
    }
}

function api_json(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . (getenv('CORS_ALLOW_ORIGIN') ?: '*'));
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Protect report reads with a secret configured in the Render service environment. */
function require_read_token(): void
{
    $expected = getenv('API_READ_TOKEN') ?: '';
    if ($expected === '') {
        api_json(503, ['error' => 'Read access is not configured. Set API_READ_TOKEN in the service environment.']);
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $provided = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
    if ($provided === '' || !hash_equals($expected, $provided)) {
        api_json(401, ['error' => 'Unauthorized.']);
    }
}
