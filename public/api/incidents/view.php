<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/database.php';
api_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS');
    api_json(405, ['error' => 'Method not allowed. Use GET.']);
}
require_read_token();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) api_json(400, ['error' => 'Provide a positive report id.']);

try {
    $db = db_connection();
    ensure_schema($db);
    $statement = $db->prepare('SELECT id, incident_type, description, persons_affected, latitude, longitude, gps_accuracy_m, status, created_at FROM incident_reports WHERE id = :id');
    $statement->execute(['id' => $id]);
    $report = $statement->fetch();
    if (!$report) api_json(404, ['error' => 'Report not found.']);
    api_json(200, ['data' => $report]);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    api_json(500, ['error' => 'Server error. Check the service logs.']);
}
