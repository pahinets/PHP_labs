<?php
// Крок 2. Підключення до бази даних через PDO

$dsn = 'mysql:host=localhost;dbname=practicum4;charset=utf8mb4';

$pdo = new PDO($dsn, 'root', '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // помилки SQL -> винятки PDOException
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // рядки як асоціативні масиви
    PDO::ATTR_EMULATE_PREPARES   => false,                    // справжні підготовлені запити на боці СУБД
]);

return $pdo;
