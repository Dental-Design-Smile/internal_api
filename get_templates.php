<?php

header('Content-Type: application/json');

// Config por CRM: db y nombre de la key en app.ini (API key de Wazzup)
$crmConfig = [
    'wow'   => ['db' => 'wazzup_wow',   'ini_key' => 'wazzup_key_wow'],
    'curvy' => ['db' => 'wazzup_curvy', 'ini_key' => 'wazzup_key_curvy'],
    'dds'   => ['db' => 'wazzup_dds',   'ini_key' => 'wazzup_key_dds'],
    'daso'  => ['db' => 'wazzup_daso',  'ini_key' => 'wazzup_key_daso'],
    'ecl'   => ['db' => 'wazzup_ecl',   'ini_key' => 'wazzup_key_ecl'],
];

$crm = $_GET['crm'] ?? '';
if ($crm === '' || !isset($crmConfig[$crm])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid crm parameter. Valid values: ' . implode(', ', array_keys($crmConfig))]);
    exit;
}

$user = $_GET['user'] ?? null;
if (!$user || !ctype_digit((string)$user)) {
    http_response_code(400);
    echo json_encode(["error" => "Falta o invalido 'user'"]);
    exit;
}
$user = (int) $user;

$ini = parse_ini_file(__DIR__ . '/app.ini');

$mysqli = new mysqli($ini['servername'], $ini['db_user'], $ini['db_password'], $crmConfig[$crm]['db']);

// Verificar conexión
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT t.tag_id
    FROM users u
    JOIN template_tag t ON t.name = u.department
    WHERE u.id = ?
");

$stmt->bind_param("i", $user);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$stmt->close();
$mysqli->close();

// Si no hay tag_id no podemos filtrar: cortamos aquí.
if (!$row) {
    http_response_code(200);
    echo json_encode(["error" => "no templates for the user"]);
    exit;
}
$tagFilter = $row['tag_id'];

$apiKey = $ini[$crmConfig[$crm]['ini_key']] ?? '';

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(["error" => "Falta la API Key en app.ini"]);
    exit;
}

$ch = curl_init('https://api.wazzup24.com/v3/templates/whatsapp');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey,
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(502);
    echo json_encode(['error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Si la API devolvió error, se devuelve tal cual
if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(["error" => "Respuesta inválida"]);
    exit;
}

// Filtrar por el tag del user y solo categoria UTILITY
$filtered = array_values(array_filter($data, function ($template) use ($tagFilter) {
    return ($template['category'] ?? '') === 'UTILITY' &&
        in_array($tagFilter, $template['tags'] ?? []);
}));

// Dejar solo los campos requeridos
$filtered = array_map(function ($template) {
    return [
        'id'       => $template['templateGuid'] ?? null,
        'name'     => $template['title'] ?? null,
        'channels' => $template['channels'] ?? [],
        'language' => $template['language'] ?? null,
        'template' => $template['components'] ?? [],
    ];
}, $filtered);

http_response_code($httpCode);
echo json_encode($filtered);
