<?php
// Крок 5. JSON-ендпоінт додавання теми.
// Приймає POST: JSON-тіло (php://input) або звичайну форму ($_POST).
// Успіх — 201 і доданий запис; помилка валідації — 422; інше — 400/405/500.

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Дозволено лише POST']);
}

// JSON-тіло або $_POST
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        respond(400, ['error' => 'Некоректний JSON у тілі запиту']);
    }
} else {
    $input = $_POST;
}

$title  = isset($input['title'])  && is_string($input['title'])  ? trim($input['title'])  : '';
$author = isset($input['author']) && is_string($input['author']) ? trim($input['author']) : '';
$isPinned = filter_var($input['is_pinned'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Ті самі правила, що й validateTopic() у ПР4
$errors = [];
$titleLength = mb_strlen($title, 'UTF-8');
if ($title === '') {
    $errors['title'] = 'Назва теми обов\'язкова';
} elseif ($titleLength < 5 || $titleLength > 100) {
    $errors['title'] = 'Назва теми має бути від 5 до 100 символів';
}
if ($author === '') {
    $errors['author'] = 'Ім\'я автора обов\'язкове';
} elseif (mb_strlen($author, 'UTF-8') > 50) {
    $errors['author'] = 'Ім\'я автора має бути не довшим за 50 символів';
}

if ($errors) {
    respond(422, ['error' => 'Перевірте поля форми', 'errors' => $errors]);
}

try {
    $pdo = require __DIR__ . '/db.php';

    // Підготовлений запит (ПР4)
    $stmt = $pdo->prepare(
        'INSERT INTO topics (title, author, created_at, is_pinned)
         VALUES (:title, :author, CURDATE(), :is_pinned)'
    );
    $stmt->execute([
        ':title'     => $title,
        ':author'    => $author,
        ':is_pinned' => (int) $isPinned,
    ]);
    $id = (int) $pdo->lastInsertId();

    // Повертаємо запис так, як його зберегла БД (зокрема created_at)
    $stmt = $pdo->prepare('SELECT id, title, author, created_at, is_pinned FROM topics WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $topic = $stmt->fetch();
    $topic['id'] = (int) $topic['id'];
    $topic['is_pinned'] = (int) $topic['is_pinned'];

    respond(201, $topic);
} catch (PDOException $e) {
    respond(500, ['error' => 'Помилка бази даних']);
}
