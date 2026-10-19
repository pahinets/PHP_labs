<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Крок 5. Редагування запису (SELECT для заповнення форми + UPDATE)
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/classes/TopicRepository.php';
require_once __DIR__ . '/lib/functions.php';

$repo = new TopicRepository($pdo);

// id з адресного рядка перевіряємо як ціле число
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$topic = $id ? $repo->findById($id) : null;

if ($topic === null) {
    header('Location: index.php?status=missing');
    exit;
}

$errors = [];
$values = [
    'title'     => $topic['title'],
    'author'    => $topic['author'],
    'is_pinned' => (bool) $topic['is_pinned'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title']     = postString('title');
    $values['author']    = postString('author');
    $values['is_pinned'] = (($_POST['is_pinned'] ?? '') === '1');

    $errors = validateTopic($values['title'], $values['author']);

    if (empty($errors)) {
        $repo->updateTopic($id, $values['title'], $values['author'], $values['is_pinned']);
        header('Location: index.php?status=updated');
        exit;
    }
}

$action = 'edit.php?id=' . $id;
$submitLabel = 'Зберегти зміни';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Форум — редагування теми</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>Редагування теми №<?= (int) $id ?></h1>
        <div class="header-actions">
            <button type="button" id="theme-toggle" class="btn btn-outline">Темна тема</button>
            <a href="index.php" class="btn btn-outline">← До списку тем</a>
        </div>
    </div>

    <div class="form-wrap">
        <?php require __DIR__ . '/topic_form.php'; ?>
    </div>

</div>

</body>
</html>
