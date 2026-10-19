<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Крок 3. Підключення до БД і вивід усіх записів (SELECT)
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/classes/TopicRepository.php';
require_once __DIR__ . '/lib/functions.php';

$repo = new TopicRepository($pdo);

// Фільтри: ?author=... (listByAuthor) та ?pinned=1 (listPinned)
$author = isset($_GET['author']) && is_string($_GET['author']) ? trim($_GET['author']) : '';
$pinnedOnly = isset($_GET['pinned']) && $_GET['pinned'] === '1';

if ($author !== '') {
    $topics = $repo->listByAuthor($author);
    $heading = 'Теми автора «' . $author . '»';
} elseif ($pinnedOnly) {
    $topics = $repo->listPinned();
    $heading = 'Закріплені теми';
} else {
    $topics = $repo->listAll();
    $heading = 'Усі теми';
}

// Повідомлення після redirect (лише з фіксованого списку)
$messages = [
    'added'   => 'Тему додано.',
    'updated' => 'Зміни збережено.',
    'deleted' => 'Тему видалено.',
    'missing' => 'Тему не знайдено.',
];
$status = $_GET['status'] ?? '';
$flash = is_string($status) ? ($messages[$status] ?? null) : null;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Форум — PDO</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>Дошка обговорень</h1>
        <div class="header-actions">
            <button type="button" id="theme-toggle" class="btn btn-outline">Темна тема</button>
            <a href="add.php" class="btn btn-outline">+ Нова тема</a>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash <?= $status === 'missing' ? 'flash-error' : '' ?>"><?= h($flash) ?></div>
    <?php endif; ?>

    <!-- Пошук за автором (listByAuthor) і фільтр закріплених (listPinned) -->
    <form method="get" action="index.php" class="toolbar">
        <input type="text" name="author" placeholder="Автор" value="<?= h($author) ?>">
        <button type="submit" class="btn">Знайти</button>
        <a href="index.php?pinned=1" class="btn btn-outline">Лише закріплені</a>
        <a href="index.php" class="btn btn-outline">Усі теми</a>
    </form>

    <div class="section">
        <h2><?= sanitizeText($heading) ?> (<?= count($topics) ?>)</h2>
    </div>

    <?php if (count($topics) === 0): ?>
        <div class="empty">Тем не знайдено.</div>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Тема</th>
                <th>Автор</th>
                <th>Створено</th>
                <th>Тип</th>
                <th>Дії</th>
            </tr>
            <?php foreach ($topics as $topic): ?>
                <tr>
                    <td><?= (int) $topic['id'] ?></td>
                    <td class="topic-title"><?= sanitizeText($topic['title']) ?></td>
                    <td><?= sanitizeText($topic['author']) ?></td>
                    <td><?= sanitizeText($topic['created_at']) ?> (<?= timeAgo($topic['created_at']) ?>)</td>
                    <td>
                        <?php if ($topic['is_pinned']): ?>
                            <span class="label label-hot">Закріплена</span>
                        <?php else: ?>
                            <span class="label">Звичайна</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="edit.php?id=<?= (int) $topic['id'] ?>">Редагувати</a>
                        <!-- Крок 6. Видалення з підтвердженням через confirm() -->
                        <form method="post" action="delete.php"
                              onsubmit="return confirm('Видалити тему «<?= h(addslashes($topic['title'])) ?>»?');">
                            <input type="hidden" name="id" value="<?= (int) $topic['id'] ?>">
                            <button type="submit" class="btn btn-danger">Видалити</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="summary">
        Показано тем: <strong><?= count($topics) ?></strong>
        &nbsp;|&nbsp;
        Закріплених у вибірці: <strong><?= count(array_filter($topics, fn($t) => $t['is_pinned'])) ?></strong>
    </div>

</div>

</body>
</html>
