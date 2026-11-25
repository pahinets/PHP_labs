<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
forum_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    forum_auth_action($_POST);
    header('Location: moderator.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    forum_deny(405, 'Метод не підтримується.');
}
$moderator = forum_is_moderator();
$field = forum_csrf_field();
$assets = is_dir(__DIR__ . '/Practicum08') ? 'Practicum08' : 'LR8';
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Форум — модератор</title>
    <link rel="stylesheet" href="<?= $assets ?>/style.css">
    <script src="<?= $assets ?>/theme.js"></script>
</head>
<body>
<div class="container">
<div class="page-header"><h1>Доступ модератора</h1><button type="button" id="theme-toggle" class="btn btn-outline">Темна тема</button></div>
<div class="form-wrap">
<p><?= $moderator ? 'Ви увійшли як модератор (сесія до 30 хв).' : 'Гість: читання та створення звичайних тем.' ?></p>
<form method="post">
    <?= $field ?>
    <input type="hidden" name="action" value="<?= $moderator ? 'logout' : 'login' ?>">
    <?php if (!$moderator): ?>
        <div class="field"><label>Пароль <input type="password" name="password" autocomplete="current-password" required maxlength="1024"></label></div>
    <?php endif; ?>
    <button type="submit" class="btn"><?= $moderator ? 'Вийти' : 'Увійти' ?></button>
</form>
<p>Пароль налаштовує власник сервера; у репозиторії його немає.</p>
<ul>
<?php for ($i = 1; $i <= 8; $i++):
    $local = sprintf('Practicum%02d', $i);
    $folder = is_dir(__DIR__ . '/' . $local) ? $local : 'LR' . $i;
    $page = $i <= 4 ? 'index.php' : 'index.html';
?>
    <li><a href="<?= $folder . '/' . $page ?>">Практикум <?= $i ?></a></li>
<?php endfor; ?>
</ul>
</div></div>
</body>
</html>
