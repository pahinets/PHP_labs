<?php
require_once dirname(__DIR__) . '/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    forum_deny(405, 'Видалення дозволене лише через POST.');
}
forum_require_csrf($_POST);
forum_require_moderator();
// Крок 6. Видалення запису (DELETE через підготовлений запит)
$pdo = require __DIR__ . '/db.php';
require_once __DIR__ . '/classes/TopicRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$repo = new TopicRepository($pdo);
$deleted = $id && $id > 0 ? $repo->deleteTopic($id) : 0;
if ($deleted) forum_invalidate_pinned();

header('Location: index.php?status=' . ($deleted > 0 ? 'deleted' : 'missing'));
exit;
