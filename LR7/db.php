<?php
// Типово та сама БД, що у ЛР4–6. Env дозволяє безпечно тестувати окрему БД.
return new PDO(
    getenv('PHP_LAB_DSN') ?: 'mysql:host=localhost;dbname=practicum4;charset=utf8mb4',
    getenv('PHP_LAB_DB_USER') ?: 'root',
    getenv('PHP_LAB_DB_PASSWORD') ?: '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
