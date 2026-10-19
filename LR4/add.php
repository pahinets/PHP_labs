<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Крок 4. Додавання запису (INSERT через підготовлений запит)
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/classes/TopicRepository.php';
require_once __DIR__ . '/lib/functions.php';

$repo = new TopicRepository($pdo);

$errors = [];
$values = ['title' => '', 'author' => '', 'is_pinned' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title']     = postString('title');
    $values['author']    = postString('author');
    $values['is_pinned'] = (($_POST['is_pinned'] ?? '') === '1');

    $errors = validateTopic($values['title'], $values['author']);

    if (empty($errors)) {
        $repo->addTopic($values['title'], $values['author'], $values['is_pinned']);
        header('Location: index.php?status=added');
        exit;
    }
}

$action = 'add.php';
$submitLabel = 'Додати тему';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Форум — нова тема</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>Створення нової теми</h1>
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
