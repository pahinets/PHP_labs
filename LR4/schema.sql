-- Крок 1. База даних і таблиця topics (варіант 9 — Форум/дошка обговорень)
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS practicum4
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE practicum4;

DROP TABLE IF EXISTS topics;
CREATE TABLE topics (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(100) NOT NULL,
    author     VARCHAR(50)  NOT NULL,
    created_at DATE         NOT NULL,
    is_pinned  TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Початкові дані (ті самі теми, що й у ЛР3)
INSERT INTO topics (title, author, created_at, is_pinned) VALUES
    ('Правила форуму — прочитайте перед публікацією', 'admin',   '2026-09-01', 1),
    ('Як налаштувати XAMPP на macOS?',                'ivan_k',  '2026-09-10', 1),
    ('Різниця між echo та print',                     'olena_p', '2026-09-14', 0),
    ('Асоціативні масиви vs об''єкти',                'max_dev', '2026-09-15', 0),
    ('Помилка "Undefined array key"',                 'sashko',  '2026-09-18', 0),
    ('Найкращі практики роботи з foreach',            'sashko',  '2026-09-20', 0);
