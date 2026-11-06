<?php
// Крок 1. JSON-ендпоінт списку тем (варіант 9 — Форум/дошка обговорень).
// На відміну від index.php з ПР4, виводить не HTML, а лише дані.
// GET ?q=... — жива фільтрація за автором теми (author).

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = require __DIR__ . '/db.php';

    $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

    if ($q !== '') {
        // Спецсимволи LIKE (\ % _) екрануємо, щоб пошук працював як звичайний пошук підрядка
        $pattern = '%' . addcslashes($q, '\\%_') . '%';
        $stmt = $pdo->prepare(
            'SELECT id, title, author, created_at, is_pinned
               FROM topics
              WHERE author LIKE :q
              ORDER BY id DESC'
        );
        $stmt->execute([':q' => $pattern]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, title, author, created_at, is_pinned
               FROM topics
              ORDER BY id DESC'
        );
    }

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['is_pinned'] = (int) $row['is_pinned'];
    }
    unset($row);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Помилка бази даних'], JSON_UNESCAPED_UNICODE);
}
