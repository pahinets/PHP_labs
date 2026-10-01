<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Крок 2. Масив даних домену — теми форуму
$topics = [
    ['title' => 'Як налаштувати XAMPP на macOS?', 'author' => 'ivan_k', 'repliesCount' => 34, 'createdAt' => '2026-09-10'],
    ['title' => 'Різниця між echo та print',        'author' => 'olena_p', 'repliesCount' => 7,  'createdAt' => '2026-09-14'],
    ['title' => 'Асоціативні масиви vs об\'єкти',    'author' => 'max_dev', 'repliesCount' => 25, 'createdAt' => '2026-09-15'],
    ['title' => 'Помилка "Undefined array key"',     'author' => 'sashko',  'repliesCount' => 12, 'createdAt' => '2026-09-18'],
    ['title' => 'Найкращі практики роботи з foreach', 'author' => 'nata_l',  'repliesCount' => 41, 'createdAt' => '2026-09-20'],
];

// Крок 3. Функція форматування одного запису
function formatTopic(array $topic): string {
    return $topic['title'];
}

// Крок 4. Умовна логіка — мітка для теми
function getTopicLabel(array $topic): string {
    return $topic['repliesCount'] > 20 ? 'Гаряча тема' : 'Звичайна тема';
}

// Крок 6. Агрегатний показник — сумарна кількість повідомлень (відповідей)
$totalReplies = array_sum(array_column($topics, 'repliesCount'));
$hotTopicsCount = count(array_filter($topics, fn($t) => $t['repliesCount'] > 20));
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Форум — список тем</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>Дошка обговорень</h1>
        <a href="form.php" class="btn btn-outline">+ Нова тема</a>
    </div>

    <!-- Крок 5. Вивід даних через foreach у вигляді таблиці -->
    <table>
        <tr>
            <th>Тема</th>
            <th>Автор</th>
            <th>Дата</th>
            <th>Відповідей</th>
            <th>Мітка</th>
        </tr>
        <?php foreach ($topics as $topic) { ?>
            <tr>
                <td class="topic-title"><?= htmlspecialchars(formatTopic($topic)) ?></td>
                <td><?= htmlspecialchars($topic['author']) ?></td>
                <td><?= htmlspecialchars($topic['createdAt']) ?></td>
                <td><?= $topic['repliesCount'] ?></td>
                <td>
                    <span class="label <?= $topic['repliesCount'] > 20 ? 'label-hot' : '' ?>">
                        <?= getTopicLabel($topic) ?>
                    </span>
                </td>
            </tr>
        <?php } ?>
    </table>

    <!-- Агрегатний блок -->
    <div class="summary">
        Сумарна кількість повідомлень: <strong><?= $totalReplies ?></strong>
        &nbsp;|&nbsp;
        Гарячих тем: <strong><?= $hotTopicsCount ?></strong>
    </div>

</div>

</body>
</html>