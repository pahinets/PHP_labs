<?php
// Практикум 7. Профілювання, GROUP BY, індекс та кеш (варіант 9 — форум).
declare(strict_types=1);
require_once dirname(__DIR__) . '/security.php';

require __DIR__ . '/performance.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        error_log('Practicum07 JSON: ' . json_last_error_msg());
        http_response_code(500);
        $json = '{"success":false,"error":"Помилка формування JSON."}';
    }
    profileHeaders();
    echo $json;
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
    $stmt = sql($pdo, 'SELECT id, title, author, created_at, is_pinned FROM topics WHERE id = :id',
        [':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        fail(404, 'Тему не знайдено.');
    }
    return normalizeTopic($row);
}

function listTopics(PDO $pdo, string $q, string $author, string $mode): void
{
    $where = [];
    $params = [];
    if ($q !== '') {
        // ! — явний ESCAPE-символ: %, _ та ! у пошуку є буквальними.
        $pattern = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $where[] = "t.author LIKE :q ESCAPE '!'";
        $params[':q'] = $pattern;
    }
    if ($author !== '') {
        $where[] = 't.author = :author';
        $params[':author'] = $author;
    }
    $query = 'SELECT t.id, t.title, t.author, t.created_at, t.is_pinned FROM topics t';
    if ($where !== []) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    $rows = array_map('normalizeTopic', sql($pdo, $query . ' ORDER BY t.id DESC',
        $params === [] ? null : $params)->fetchAll());
    if ($mode === 'before') {
        // Навчальний N+1 для НОВОЇ можливості; оригінальна ЛР6 цього не робила.
        foreach ($rows as &$row) {
            $row['author_topic_count'] = (int) sql($pdo,
                'SELECT COUNT(*) FROM topics WHERE author = :author',
                [':author' => $row['author']])->fetchColumn();
        }
        unset($row);
    } elseif ($mode === 'after') {
        // Один GROUP BY + JOIN зіставляє авторів за collation MySQL,
        // а не за чутливими до регістру ключами PHP-масиву.
        $counts = sql($pdo, 'SELECT t.id, c.cnt FROM topics t JOIN
            (SELECT author, COUNT(*) AS cnt FROM topics GROUP BY author) c
            ON c.author = t.author')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as &$row) {
            $row['author_topic_count'] = (int) $counts[$row['id']];
        }
        unset($row);
    }
    respond(200, ['success' => true, 'data' => $rows]);
}

function listPinned(PDO $pdo): array
{
    return array_map('normalizeTopic', sql($pdo,
        'SELECT id, title, author, created_at, is_pinned FROM topics
         WHERE is_pinned = 1 ORDER BY id DESC')->fetchAll());
}

function getTopic(PDO $pdo, int $id): void
{
    respond(200, ['success' => true, 'data' => findTopic($pdo, $id)]);
}

function createTopic(PDO $pdo, array $input): void
{
    sql($pdo,
        'INSERT INTO topics (title, author, created_at, is_pinned)
         VALUES (:title, :author, CURDATE(), :is_pinned)', [
        ':title' => $input['title'],
        ':author' => $input['author'],
        ':is_pinned' => $input['is_pinned'],
    ]);
    $id = (int) $pdo->lastInsertId();
    invalidatePinned();
    header('Location: api.php?resource=topics&id=' . $id);
    respond(201, ['success' => true, 'data' => findTopic($pdo, $id)]);
}

function pinTopic(PDO $pdo, int $id): void
{
    sql($pdo, 'UPDATE topics SET is_pinned = 1 WHERE id = :id', [':id' => $id]);
    invalidatePinned();
    // rowCount() може бути 0 для вже закріпленої теми — це не означає 404.
    respond(200, ['success' => true, 'data' => findTopic($pdo, $id)]);
}

// Спочатку перевіряємо маршрут і дані: помилки клієнта не потребують БД.
$resource = queryString('resource');
$action = queryString('action');
if ($resource !== 'topics') {
    fail(404, 'Невідомий ресурс.');
}
if (!in_array($action, ['', 'pin', 'pinned'], true)) {
    fail(404, 'Невідома дія.');
}
$id = topicId();
$q = queryString('q');
$author = queryString('author');
$mode = queryString('mode', 'after');
if (!in_array($mode, ['original', 'before', 'after'], true)) {
    fail(400, 'mode: original, before або after.');
}
$input = [];
if ($action === 'pinned') {
    allowMethods(['GET']);
    if ($id !== null || $q !== '' || $author !== '') {
        fail(400, 'Список закріплених тем не приймає id, q або author.');
    }
} elseif ($action === 'pin') {
    allowMethods(['POST']);
    forum_require_csrf(readInput());
    forum_require_moderator();
    if ($id === null) {
        fail(400, 'Для закріплення теми потрібен id.');
    }
} elseif ($id !== null) {
    allowMethods(['GET']);
} else {
    allowMethods(['GET', 'POST']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = readInput();
        forum_require_csrf($raw);
        $input = validateTopic($raw);
        if ($input['is_pinned']) forum_require_moderator();
    }
}

try {
    $pdo = require __DIR__ . '/db.php';
    if ($action === 'pinned') {
        respond(200, ['success' => true, 'data' => cachedPinned(fn() => listPinned($pdo))]);
    } elseif ($action === 'pin') {
        pinTopic($pdo, $id);
    } elseif ($id !== null) {
        getTopic($pdo, $id);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        createTopic($pdo, $input);
    } else {
        listTopics($pdo, $q, $author, $mode);
    }
} catch (PDOException $e) {
    error_log('Practicum07 database error: ' . $e->getMessage());
    fail(500, 'Помилка бази даних. Спробуйте пізніше.');
}
