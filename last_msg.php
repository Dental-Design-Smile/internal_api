<?php
error_reporting(0);

$crmConfig = [
    'wow' => [
        'db'    => 'wazzup_wow',
        'query' => "SELECT m.timestamp, m.channel
                     FROM message m
                     WHERE m.contact = ? AND m.direction = 'incoming'
                     ORDER BY m.timestamp DESC
                     LIMIT 1",
    ],
    'curvy' => [
        'db'    => 'wazzup_curvy',
        'query' => "SELECT m.timestamp, m.channel
                     FROM message m
                     WHERE m.contact = ? AND m.direction = 'incoming'
                     ORDER BY m.timestamp DESC
                     LIMIT 1",
    ],
    'dds' => [
        'db'    => 'wazzup_dds',
        'query' => "SELECT m.timestamp, m.channel
                     FROM message m
                     WHERE m.contact = ? AND m.direction = 'incoming'
                     ORDER BY m.timestamp DESC
                     LIMIT 1",
    ],
    'daso' => [
        'db'    => 'wazzup_daso',
        'query' => "SELECT m.timestamp, m.channel
                     FROM message m
                     WHERE m.contact = ? AND m.direction = 'incoming'
                     ORDER BY m.timestamp DESC
                     LIMIT 1",
    ],
    'ecl' => [
        'db'    => 'wazzup_ecl',
        'query' => "SELECT m.timestamp, m.channel
                     FROM message m
                     WHERE m.contact = ? AND m.direction = 'incoming'
                     ORDER BY m.timestamp DESC
                     LIMIT 1",
    ],
];

$crm = $_GET['crm'] ?? null;
if (!$crm || !isset($crmConfig[$crm])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Missing or invalid crm parameter. Valid values: " . implode(', ', array_keys($crmConfig))]);
    exit;
}

$phone = $_GET['phone'] ?? null;
if ($phone === null || trim($phone) === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Missing phone parameter"]);
    exit;
}

$contact = ltrim(trim($phone), '+');

$ini_db = parse_ini_file(__DIR__ . '/app.ini');
$conn = new mysqli($ini_db['servername'], $ini_db['db_user'], $ini_db['db_password'], $crmConfig[$crm]['db']);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare($crmConfig[$crm]['query']);
$stmt->bind_param('s', $contact);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'timestamp' => $row['timestamp'] ?? null,
    'channel'   => $row['channel'] ?? null,
]);
exit;
