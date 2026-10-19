<?php
// Крок 6. Видалення запису (DELETE через підготовлений запит)
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/classes/TopicRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$repo = new TopicRepository($pdo);
$deleted = $id ? $repo->deleteTopic($id) : 0;

header('Location: index.php?status=' . ($deleted > 0 ? 'deleted' : 'missing'));
exit;
