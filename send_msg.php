<?php

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    $body = json_encode(['error' => 'Method not allowed']);
    echo $body;
    exit;
}

// Config por CRM: cada uno tiene su db y el nombre de su key en app.ini (API key de Wazzup)
$crmConfig = [
    'wow' => [
        'db'      => 'wazzup_wow',
        'ini_key' => 'wazzup_key_wow',
    ],
    'curvy' => [
        'db'      => 'wazzup_curvy',
        'ini_key' => 'wazzup_key_curvy',
    ],
    'dds' => [
        'db'      => 'wazzup_dds',
        'ini_key' => 'wazzup_key_dds',
    ],
    'daso' => [
        'db'      => 'wazzup_daso',
        'ini_key' => 'wazzup_key_daso',
    ],
    'ecl' => [
        'db'      => 'wazzup_ecl',
        'ini_key' => 'wazzup_key_ecl',
    ],
];

// Parametros en el body (JSON; si no es JSON, form-data)
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$crm     = $input['crm'] ?? '';
$numero  = trim($input['numero'] ?? '');
$channel = trim($input['channel'] ?? '');
$type    = $input['type'] ?? '';
$userID  = (int)($input['user_id'] ?? 0);
$text           = $input['text'] ?? '';
$templateId     = $input['templateId'] ?? '';
$templateValues = $input['templateValues'] ?? [];

if ($crm === '' || !isset($crmConfig[$crm])) {
    http_response_code(400);
    $body = json_encode(['error' => 'Missing or invalid crm parameter. Valid values: ' . implode(', ', array_keys($crmConfig))]);
    welcome_log($body);
    echo $body;
    exit;
}

$url = "https://api.wazzup24.com/v3/message";

$ini = parse_ini_file(__DIR__ . '/app.ini');
$apiKey = $ini[$crmConfig[$crm]['ini_key']] ?? '';

$servername = $ini['servername'];
$username = $ini['db_user'];
$password = $ini['db_password'];
$dbname = $crmConfig[$crm]['db'];

$conn = new mysqli($servername, $username, $password, $dbname);

$chatType = 'whatsapp';

if ($type === 'text') {
    $messages = [
        [
            "channelId" => $channel,
            "chatId" => $numero,
            "chatType" => $chatType,
            "text" => $text,
        ]
    ];
} elseif ($type === 'template') {
    $messages = [[
        "channelId" => $channel,
        "chatId" => $numero,
        "chatType" => $chatType,
        "templateId" => $templateId,
        "templateValues" => $templateValues,
    ]];
} else {
    http_response_code(400);
    $body = json_encode(['error' => 'type invalido']);
    welcome_log($body);
    echo $body;
    exit;
}

$responses = [];

foreach ($messages as $data) {
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $responses[] = json_encode(['error' => curl_error($ch)]);
    } else {
        $responses[] = $response;

        $j_response = json_decode($response, true);

        if (!empty($j_response['messageId'])) {
            $name = 'no name';
            $last_message = date('Y-m-d H:i:s');
            $stmt2 = $conn->prepare("INSERT IGNORE
                   INTO contacts (id, name, platform, last_message)
                   VALUES (?,?,?,?)
                   ");
            $stmt2->bind_param("ssss",
                $j_response["chatId"],
                $name,
                $chatType,
                $last_message
            );
            $stmt2->execute();
            $stmt2->close();

            $stmt = $conn->prepare("INSERT
                   INTO message (id, channel, contact, user, need_answer)
                   VALUES (?,?,?,?,0)
                   ");
            $stmt->bind_param("sssi",
                $j_response["messageId"],
                $channel,
                $numero,
                $userID,
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    curl_close($ch);
}

$conn->close();

$body = count($responses) === 1 ? $responses[0] : '[' . implode(',', $responses) . ']';
welcome_log($body);
echo $body;
