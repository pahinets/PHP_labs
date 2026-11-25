<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type === 'application/json') {
        try {
            $input = json_decode(file_get_contents('php://input'), false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            forum_deny(400, 'Некоректний JSON.');
        }
        if (!$input instanceof stdClass) {
            forum_deny(400, 'Очікується JSON-об’єкт.');
        }
        $input = (array) $input;
    } else {
        $input = $_POST;
    }
    forum_auth_action($input);
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    forum_deny(405, 'Метод не підтримується.');
}
echo json_encode(['success' => true, 'data' => forum_auth_state()], JSON_UNESCAPED_UNICODE);
