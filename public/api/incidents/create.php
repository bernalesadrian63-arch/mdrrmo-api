<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/database.php';
api_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    api_json(405, ['error' => 'Method not allowed. Use POST.']);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) api_json(400, ['error' => 'Request body must be valid JSON.']);

$type = trim((string) ($input['incident_type'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$people = filter_var($input['persons_affected'] ?? null, FILTER_VALIDATE_INT);
$latitude = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$accuracy = array_key_exists('gps_accuracy_m', $input) && $input['gps_accuracy_m'] !== ''
    ? filter_var($input['gps_accuracy_m'], FILTER_VALIDATE_FLOAT)
    : null;

$errors = [];
if ($type === '' || strlen($type) > 80) $errors['incident_type'] = 'Required; maximum 80 characters.';
if ($description === '' || strlen($description) > 5000) $errors['description'] = 'Required; maximum 5000 characters.';
if ($people === false || $people < 0) $errors['persons_affected'] = 'Must be a non-negative whole number.';
if ($latitude === false || $latitude < -90 || $latitude > 90) $errors['latitude'] = 'Must be between -90 and 90.';
if ($longitude === false || $longitude < -180 || $longitude > 180) $errors['longitude'] = 'Must be between -180 and 180.';
if ($accuracy === false || ($accuracy !== null && $accuracy < 0)) $errors['gps_accuracy_m'] = 'Must be zero or greater.';
if ($errors !== []) api_json(422, ['error' => 'Validation failed.', 'fields' => $errors]);

try {
    $db = db_connection();
    ensure_schema($db);
    $statement = $db->prepare(<<<'SQL'
        INSERT INTO incident_reports (incident_type, description, persons_affected, latitude, longitude, gps_accuracy_m)
        VALUES (:incident_type, :description, :persons_affected, :latitude, :longitude, :gps_accuracy_m)
        RETURNING id, incident_type, description, persons_affected, latitude, longitude, gps_accuracy_m, status, created_at
    SQL);
    $statement->execute([
        'incident_type' => $type,
        'description' => $description,
        'persons_affected' => $people,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'gps_accuracy_m' => $accuracy,
    ]);
    $report = $statement->fetch();
    try {
        $geo = $db->prepare('UPDATE incident_reports SET location = ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography WHERE id = :id');
        $geo->execute(['lon' => $longitude, 'lat' => $latitude, 'id' => $report['id']]);
    } catch (PDOException $exception) {
        // Numeric coordinate columns remain available when PostGIS is not installed.
    }
    api_json(201, ['data' => $report]);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    api_json(500, ['error' => 'Server error. Check the service logs.']);
}
