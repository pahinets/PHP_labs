<?php
// Та сама БД та параметри підключення, що у практикумах 4–5.
return new PDO(
    'mysql:host=localhost;dbname=practicum4;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
