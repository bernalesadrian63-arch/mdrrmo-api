<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/database.php';
api_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS');
    api_json(405, ['error' => 'Method not allowed. Use GET.']);
}
require_read_token();

try {
    $db = db_connection();
    ensure_schema($db);
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
    $statement = $db->prepare('SELECT id, incident_type, description, persons_affected, latitude, longitude, gps_accuracy_m, status, created_at FROM incident_reports ORDER BY created_at DESC LIMIT :limit');
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    api_json(200, ['data' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    api_json(500, ['error' => 'Server error. Check the service logs.']);
}
