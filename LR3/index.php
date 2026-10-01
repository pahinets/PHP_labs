<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Крок 6. Підключення класів і бібліотеки функцій
require_once __DIR__ . '/classes/Topic.php';
require_once __DIR__ . '/classes/PinnedTopic.php';
require_once __DIR__ . '/classes/Forum.php';
require_once __DIR__ . '/lib/functions.php';

// Крок 6. Створення менеджера й об'єктів базового та похідного класів
$forum = new Forum();

$forum->addTopic(new PinnedTopic('Правила форуму — прочитайте перед публікацією', 'admin', '2026-09-01', '2026-12-31'));
$forum->addTopic(new PinnedTopic('Як налаштувати XAMPP на macOS?', 'ivan_k', '2026-09-10', '2026-10-31'));
$forum->addTopic(new Topic('Різниця між echo та print', 'olena_p', '2026-09-14'));
$forum->addTopic(new Topic('Асоціативні масиви vs об\'єкти', 'max_dev', '2026-09-15'));
$forum->addTopic(new Topic('Помилка "Undefined array key"', 'sashko', '2026-09-18'));
$forum->addTopic(new Topic('Найкращі практики роботи з foreach', 'sashko', '2026-09-20'));

// Крок 6. Вибірки через методи менеджера
$searchAuthor = 'sashko';
$authorTopics = $forum->listByAuthor($searchAuthor);
$pinnedTopics = $forum->listPinned();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Форум — ООП</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>Дошка обговорень</h1>
    </div>

    <!-- Крок 6. Вивід усіх тем у вигляді таблиці -->
    <table>
        <tr>
            <th>Тема</th>
            <th>Автор</th>
            <th>Створено</th>
            <th>Тип</th>
            <th>Інформація (getInfo)</th>
        </tr>
        <?php foreach ($forum->getAll() as $topic) { ?>
            <tr>
                <td class="topic-title"><?= sanitizeText($topic->getTitle()) ?></td>
                <td><?= sanitizeText($topic->getAuthor()) ?></td>
                <td><?= sanitizeText($topic->getCreatedAt()) ?> (<?= timeAgo($topic->getCreatedAt()) ?>)</td>
                <td>
                    <?php if ($topic instanceof PinnedTopic) { ?>
                        <span class="label label-hot">Закріплена</span>
                    <?php } else { ?>
                        <span class="label">Звичайна</span>
                    <?php } ?>
                </td>
                <td><?= sanitizeText($topic->getInfo()) ?></td>
            </tr>
        <?php } ?>
    </table>

    <!-- Крок 6. Теми одного автора -->
    <div class="section">
        <h2>Теми автора <?= sanitizeText($searchAuthor) ?> (<?= count($authorTopics) ?>)</h2>
        <ul>
            <?php foreach ($authorTopics as $topic) { ?>
                <li><?= sanitizeText($topic->getInfo()) ?></li>
            <?php } ?>
        </ul>
    </div>

    <!-- Крок 6. Закріплені теми -->
    <div class="section">
        <h2>Закріплені теми (<?= count($pinnedTopics) ?>)</h2>
        <ul>
            <?php foreach ($pinnedTopics as $topic) { ?>
                <li><?= sanitizeText($topic->getInfo()) ?></li>
            <?php } ?>
        </ul>
    </div>

    <!-- Підсумок -->
    <div class="summary">
        Усього тем: <strong><?= $forum->count() ?></strong>
        &nbsp;|&nbsp;
        Закріплених: <strong><?= count($pinnedTopics) ?></strong>
    </div>

</div>

</body>
</html>
