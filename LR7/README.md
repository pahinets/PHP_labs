# Практична робота №7 — оптимізація PHP API

Варіант 9: форум. Продовження ЛР6 без фреймворків: профілювання, індекс `author`,
усунення N+1 підрахунку тем автора та кеш `listPinned()` на 60 секунд.

## Запуск

Потрібні PHP 8.1+ з `pdo_mysql`, `mbstring` та MySQL/MariaDB.
Виконайте `schema.sql` через клієнт БД: скрипт створює `practicum4.topics`,
не видаляє наявних даних і додає `idx_topics_author`, якщо його ще немає.
Типові параметри `db.php`: localhost, БД practicum4, root, порожній пароль
(як у попередніх лабораторних; тільки локальне навчальне середовище).

Із папки роботи (`LR7` у GitHub, локально `Practicum07`):

```powershell
php -S 127.0.0.1:8007
```

Відкрити http://127.0.0.1:8007/index.html.
Для іншої БД можна задати змінні середовища перед запуском:

```powershell
$env:PHP_LAB_DSN = 'mysql:host=127.0.0.1;dbname=practicum7_test;charset=utf8mb4'
$env:PHP_LAB_DB_USER = 'root'
$env:PHP_LAB_DB_PASSWORD = ''
# Необов'язково: окремий каталог поза webroot, доступний для запису PHP.
$env:PHP_LAB_CACHE_DIR = "$env:TEMP\php-practicum07-cache"
php -S 127.0.0.1:8007
```

`schema.sql` налаштовано на стандартну БД `practicum4`; для окремої тестової БД
потрібно змінити в копії скрипта її назву. Навантажувальні дані не додавати до
робочої БД попередніх лабораторних. API не має авторизації та обмеження частоти:
це локальна лабораторна, не публічний production-сервіс.

## API та профіль

Відповідь: `{"success":true,"data":...}` або `{"success":false,"error":"..."}`.
Маршрути ЛР6 збережено: GET список / GET `id`, POST створення (JSON або form),
POST `id=...&action=pin`. Коди: 200, 201, 400, 404, 405 (`Allow`), 500.
Назва після trim — 5–100 символів, автор — 1–50, `is_pinned` — 0/1 або boolean.

Додано:

- `GET api.php?resource=topics&mode=original` — логіка списку ЛР6, 1 SQL,
  без нового поля підрахунку.
- `mode=before` — навчальний N+1: список + COUNT для кожної теми.
- `mode=after` (типово) — список + груповий підрахунок, 2 SQL.
  Обидва режими повертають однакове `author_topic_count`.
- `author=author_042` — точний автор (індекс); `q=...` — старий пошук підрядка
  автора, з буквальними `%`/`_`. B-tree не обіцяє прискорення `LIKE '%...%'`.
- `GET api.php?resource=topics&action=pinned` — лише закріплені, без фільтрів
  `q`/`author` та без `id`; кількість тем автора тут не обчислюється.

Кожна відповідь має `X-Elapsed-Ms` (microtime до завершення JSON-кодування),
`X-Peak-Memory-Mb` (memory_get_peak_usage(true)), `X-SQL-Queries` і `X-Cache`.
Профіль також пишеться в PHP error_log, а JSON не засмічується debug-виводом.
Лічильник рахує виконання SQL, не виклики prepare чи встановлення PDO-з'єднання.

## Фактичне порівняння «до / після»

Windows, PHP 8.5.11 built-in server, MariaDB 11.4.5, ApacheBench 2.3.
Окрема БД: 1000 тем, 100 авторів по 10 тем, 50 закріплених.
Час PHP — медіана 10 послідовних запитів, пам'ять — максимальний пік.
Кожен ab-тест: `-n 200 -c 20`, усі 200 завершено, Failed requests=0,
Non-2xx=0. Набір даних у порівнянні не змінювався.

| Режим | Час PHP, мс | Пік, МБ | SQL | RPS | ab mean, мс |
|---|---:|---:|---:|---:|---:|
| original — ЛР6 без підрахунку | 2.985 | 2.000 | 1 | 107.35 | 186.303 |
| before — N+1 підрахунок | 379.600 | 2.000 | 1001 | 2.48 | 8053.566 |
| after — GROUP BY + індекс | 5.051 | 2.000 | 2 | 92.88 | 215.324 |
| pinned — теплий кеш | 2.215 | 2.000 | 0 | 151.37 | 132.123 |

**Важливо:** початкова ЛР6 вже мала один SELECT. N+1 тут — окремий
навчальний режим для нового підрахунку, а не дефект старої роботи.
Before/after повернули побайтово однакові 134007 байтів JSON
(SHA-256 `971cb67652f255b6fb452264ccd382c49944fe146915b41b1ca3b9ede08950e6`).
Покращення цих режимів — спільний ефект усунення N+1 та індексу.
Original і pinned мають іншу семантику, тому не є прямою парою до/після.

```powershell
ab -n 200 -c 20 "http://127.0.0.1:8007/api.php?resource=topics&mode=original"
ab -n 200 -c 20 "http://127.0.0.1:8007/api.php?resource=topics&mode=before"
ab -n 200 -c 20 "http://127.0.0.1:8007/api.php?resource=topics&mode=after"
# Перший запит прогріває кеш (MISS), наступні дають HIT.
curl.exe -i "http://127.0.0.1:8007/api.php?resource=topics&action=pinned"
ab -n 200 -c 20 "http://127.0.0.1:8007/api.php?resource=topics&action=pinned"
```

PHP built-in server у Windows обробляє запити послідовно: 20 — кількість
конкурентних клієнтів/черга, не 20 PHP-worker. `ab mean` враховує це очікування,
а не лише PHP. Другий рядок ab `across all concurrent requests` — не час PHP.
Цифри залежать від машини; це локальний дослід, не production-capacity.

### EXPLAIN та індекс

```sql
EXPLAIN SELECT * FROM topics WHERE author = 'author_042';
CREATE INDEX idx_topics_author ON topics(author); -- лише якщо ще немає
ANALYZE TABLE topics;
EXPLAIN SELECT * FROM topics WHERE author = 'author_042';
```

| Стан | type | key | rows (оцінка) | Extra |
|---|---|---|---:|---|
| До | ALL | NULL | 1000 | Using where |
| Після | ref | idx_topics_author | 10 | Using index condition |

`rows` — оцінка оптимізатора, не виміряне число прочитаних рядків.
GROUP BY з JOIN зіставляє авторів за collation БД, зокрема Alice/alice.

### Кеш listPinned()

Файл поза webroot; ключ містить шлях застосунку, DSN та користувача БД.
TTL 60 с. Холодний запит: MISS, 1 SQL, 3.683 мс; теплий — HIT, 0 SQL.
PDO-з'єднання на HIT усе одно встановлюється. INSERT/UPDATE інвалідують кеш.
Зовнішні зміни БД видно після TTL; пошкоджений/прострочений кеш перечитується.
LOCK_EX охоплює читання й наповнення та узгоджує їх з інвалідацією.
Недоступний кеш дає BYPASS і коректну відповідь із БД, а не відмову API.

Локально перевірено валідації ЛР6, SQL-лічильник, порожню БД, collation,
фільтри, HIT/MISS/BYPASS, TTL, пошкодження та інвалідацію. У GitHub навмисно
лише робочі PHP/HTML/JS/CSS, SQL і цей обов'язковий короткий звіт;
DOCX/PDF, тести, інструменти, результати запусків, логи та кеш не публікуються.

Підсумкові 15 регресійних перевірок пройшли. Після ab-замірів додано захист
від пошкодженого кешу з `{}` замість списку чи зайвим `1e400` та від помилки
JSON-кодування (коректна JSON-відповідь 500). Це не змінює SQL/дані штатних режимів.
Перевірено case-insensitive collation; акцентні еквіваленти та справжні
багатопроцесні interleaving-сценарії окремо не тестувалися.
