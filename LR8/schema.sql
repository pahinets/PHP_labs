-- Для першого встановлення. Наявна БД практикумів 4–5 не очищається.
CREATE DATABASE IF NOT EXISTS practicum4
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE practicum4;

CREATE TABLE IF NOT EXISTS topics (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    author VARCHAR(50) NOT NULL,
    created_at DATE NOT NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Індекс можна додати й до існуючої таблиці без видалення даних (MySQL/MariaDB).
SET @index_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'topics' AND index_name = 'idx_topics_author');
SET @create_index = IF(@index_exists = 0,
    'CREATE INDEX idx_topics_author ON topics (author)', 'SELECT 1');
PREPARE index_statement FROM @create_index;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;
