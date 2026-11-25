<?php
// Практикум 6. Єдина точка входу API (варіант 9 — форум).
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(int $status, string $message, array $errors = []): void
{
    $payload = ['success' => false, 'error' => $message];
    if ($errors !== []) {
        $payload['errors'] = $errors;
    }
    respond($status, $payload);
}

function allowMethods(array $allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        fail(405, 'Метод не підтримується для цього маршруту.');
    }
}

function queryString(string $name, string $default = ''): string
{
    if (!array_key_exists($name, $_GET)) {
        return $default;
    }
    if (!is_string($_GET[$name]) || !mb_check_encoding($_GET[$name], 'UTF-8')) {
        fail(400, 'Параметр ' . $name . ' має бути рядком UTF-8.');
    }
    return trim($_GET[$name]);
}

function topicId(): ?int
{
    if (!array_key_exists('id', $_GET)) {
        return null;
    }
    $value = queryString('id');
    // Межі додатного INT у таблиці topics; не перетворюємо "1abc" на 1.
    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (float) $value > 2147483647) {
        fail(400, 'id має бути цілим числом від 1 до 2147483647.');
    }
    return (int) $value;
}

function readInput(): array
{
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type === 'application/json') {
        try {
            $value = json_decode(file_get_contents('php://input'), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            fail(400, 'Некоректний JSON у тілі запиту.');
        }
        if (!$value instanceof stdClass) {
            fail(400, 'Тіло JSON має бути об’єктом.');
        }
        return (array) $value;
    }
    if (in_array($type, ['', 'application/x-www-form-urlencoded', 'multipart/form-data'], true)) {
        return $_POST;
    }
    fail(400, 'Передайте JSON-об’єкт або дані HTML-форми.');
}

function validateTopic(array $input): array
{
    $errors = [];
    $values = [];
    foreach (['title' => [5, 100], 'author' => [1, 50]] as $field => [$min, $max]) {
        $raw = $input[$field] ?? null;
        $value = is_string($raw) ? trim($raw) : '';
        if (!is_string($raw) || !mb_check_encoding($value, 'UTF-8')
            || mb_strlen($value, 'UTF-8') < $min || mb_strlen($value, 'UTF-8') > $max) {
            $errors[$field] = ($field === 'title' ? 'Назва теми' : 'Автор')
                . ": від $min до $max символів.";
        }
        $values[$field] = $value;
    }
    $pinned = array_key_exists('is_pinned', $input) ? $input['is_pinned'] : 0;
    if (!in_array($pinned, [0, 1, '0', '1', false, true], true)) {
        $errors['is_pinned'] = 'is_pinned має дорівнювати 0 або 1.';
    }
    if ($errors !== []) {
        fail(400, 'Перевірте введені дані.', $errors);
    }
    $values['is_pinned'] = (int) $pinned;
    return $values;
}

function normalizeTopic(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['is_pinned'] = (int) $row['is_pinned'];
    return $row;
}

function findTopic(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT id, title, author, created_at, is_pinned FROM topics WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        fail(404, 'Тему не знайдено.');
    }
    return normalizeTopic($row);
}

function listTopics(PDO $pdo, string $q): void
{
    if ($q !== '') {
        // ! — явний ESCAPE-символ: %, _ та ! у пошуку є буквальними.
        $pattern = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $stmt = $pdo->prepare(
            "SELECT id, title, author, created_at, is_pinned
             FROM topics WHERE author LIKE :q ESCAPE '!' ORDER BY id DESC"
        );
        $stmt->execute([':q' => $pattern]);
    } else {
        $stmt = $pdo->query('SELECT id, title, author, created_at, is_pinned FROM topics ORDER BY id DESC');
    }
    respond(200, ['success' => true, 'data' => array_map('normalizeTopic', $stmt->fetchAll())]);
}

function getTopic(PDO $pdo, int $id): void
{
    respond(200, ['success' => true, 'data' => findTopic($pdo, $id)]);
}

function createTopic(PDO $pdo, array $input): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO topics (title, author, created_at, is_pinned)
         VALUES (:title, :author, CURDATE(), :is_pinned)'
    );
    $stmt->execute([
        ':title' => $input['title'],
        ':author' => $input['author'],
        ':is_pinned' => $input['is_pinned'],
    ]);
    $id = (int) $pdo->lastInsertId();
    header('Location: api.php?resource=topics&id=' . $id);
    respond(201, ['success' => true, 'data' => findTopic($pdo, $id)]);
}

function pinTopic(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE topics SET is_pinned = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
    // rowCount() може бути 0 для вже закріпленої теми — це не означає 404.
    respond(200, ['success' => true, 'data' => findTopic($pdo, $id)]);
}

// Спочатку перевіряємо маршрут і дані: помилки клієнта не потребують БД.
$resource = queryString('resource');
$action = queryString('action');
if ($resource !== 'topics') {
    fail(404, 'Невідомий ресурс.');
}
if ($action !== '' && $action !== 'pin') {
    fail(404, 'Невідома дія.');
}
$id = topicId();
$q = queryString('q');
$input = [];
if ($action === 'pin') {
    allowMethods(['POST']);
    if ($id === null) {
        fail(400, 'Для закріплення теми потрібен id.');
    }
} elseif ($id !== null) {
    allowMethods(['GET']);
} else {
    allowMethods(['GET', 'POST']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = validateTopic(readInput());
    }
}

try {
    $pdo = require __DIR__ . '/db.php';
    if ($action === 'pin') {
        pinTopic($pdo, $id);
    } elseif ($id !== null) {
        getTopic($pdo, $id);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        createTopic($pdo, $input);
    } else {
        listTopics($pdo, $q);
    }
} catch (PDOException $e) {
    error_log('Practicum06 database error: ' . $e->getMessage());
    fail(500, 'Помилка бази даних. Спробуйте пізніше.');
}
