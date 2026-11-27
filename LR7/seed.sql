-- Лише тестова БД: повторний запуск замінює practicum7_bench.topics.
-- Робоча practicum4 не змінюється. Початковий стан — без індексу author.
CREATE DATABASE IF NOT EXISTS practicum7_bench
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE practicum7_bench;
DROP TABLE IF EXISTS topics;
CREATE TABLE topics (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    author VARCHAR(50) NOT NULL,
    created_at DATE NOT NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO topics (id, title, author, created_at, is_pinned)
SELECT n, CONCAT('Тема форуму ', LPAD(n, 4, '0')),
       CONCAT('author_', LPAD(MOD(n, 100), 3, '0')),
       '2026-11-25', IF(MOD(n, 20) = 0, 1, 0)
FROM (
    SELECT a.d + 10 * b.d + 100 * c.d + 1 AS n
    FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
          UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
          UNION ALL SELECT 8 UNION ALL SELECT 9) a
    CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
          UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
          UNION ALL SELECT 8 UNION ALL SELECT 9) b
    CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
          UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
          UNION ALL SELECT 8 UNION ALL SELECT 9) c
) numbers
ORDER BY n;
