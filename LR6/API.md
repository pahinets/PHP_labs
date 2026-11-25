# Практикум №6 — API форуму (варіант 9)

Єдина точка входу — **api.php**. Продовжує практикуми 4–5:
БД `practicum4`, таблиця `topics(id, title, author, created_at, is_pinned)`.
PHP 8+ із `pdo_mysql` і `mbstring`, MySQL/MariaDB. Фреймворки не використовуються.

## Запуск

1. Запустити MySQL/MariaDB. Якщо БД з ЛР4 вже є, її можна використовувати без повторного імпорту.
2. Для нового встановлення імпортувати `schema.sql`: він не видаляє наявні дані.
   Таблиця спочатку порожня; теми додаються через сторінку або POST.
3. У `db.php` перевірити host, dbname, користувача та пароль. Типово — `localhost`,
   `practicum4`, `root` без пароля, як у ЛР5. Ці налаштування призначені лише для локальної лабораторної.
4. Із папки `LR6` цього репозиторію (локально — `Practicum06`) виконати:

```console
php -S 127.0.0.1:8006
```

Відкрити `http://127.0.0.1:8006/`. Не відкривати index.html через file://.
Альтернатива: розмістити папку в htdocs локального Apache, як попередні роботи.

## Маршрути

| Метод | Query string до api.php | Результат |
|---|---|---|
| GET | `?resource=topics` | 200, масив тем за id DESC |
| GET | `?resource=topics&q=sashko` | 200, пошук підрядка автора; %, _ і ! буквальні |
| GET | `?resource=topics&id=1` | 200, одна тема; 404, якщо її немає |
| POST | `?resource=topics` | 201, створена тема; заголовок Location |
| POST | `?resource=topics&action=pin&id=1` | 200, тема з is_pinned=1; 404, якщо її немає |

Повторний pin успішний (200), не створює тем і не змінює дату.
Теми не пересортовуються за ознакою pin: порядок id DESC збережено з ЛР5.

### Вхідні дані створення

JSON-об'єкт (`Content-Type: application/json`) або звичайна HTML-форма
(`application/x-www-form-urlencoded`, `multipart/form-data`).

- `title`: рядок 5–100 символів UTF-8 після trim.
- `author`: рядок 1–50 символів UTF-8 після trim.
- `is_pinned`: необов'язкове; 0/1, false/true (JSON), `"0"`/`"1"` (форма). Типово 0.
- `created_at` генерується БД через CURDATE(); клієнтські id/created_at ігноруються.

`id` у URL — ціле число 1…2147483647 без початкових нулів.
PIN бере id тільки з URL; тіло запиту йому не потрібне.

### Відповіді

Усі відповіді API: `Content-Type: application/json; charset=utf-8`.
Числові id та is_pinned не перетворюються на рядки.

```json
{"success":true,"data":{"id":1,"title":"Нова тема форуму","author":"sashko","created_at":"2026-11-25","is_pinned":0}}
```

```json
{"success":false,"error":"Тему не знайдено."}
```

Помилка полів додатково містить `errors`, наприклад:

```json
{"success":false,"error":"Перевірте введені дані.","errors":{"title":"Назва теми: від 5 до 100 символів."}}
```

| Код | Значення |
|---|---|
| 200 | Успішне читання або закріплення |
| 201 | Тему створено |
| 400 | Некоректний id, тип параметра, JSON або поля |
| 404 | Невідомий ресурс/дія або відсутній запис |
| 405 | Метод не підтримується; `Allow` містить дозволені методи маршруту |
| 500 | Помилка БД; подробиці лише в серверному журналі |

Маршрут і поля перевіряються до підключення БД. Некоректний id повертає 400,
а коректний, але відсутній id — 404. Для POST до теми без action метод не підтримується
(405, Allow: GET); для GET pin — 405, Allow: POST.

## Ручна перевірка (PowerShell)

У PowerShell використовувати `curl.exe`. Файл `topic.json` зберегти в UTF-8:
`{"title":"Нова тема форуму","author":"sashko","is_pinned":0}`.

```powershell
curl.exe -i "http://127.0.0.1:8006/api.php?resource=topics"
curl.exe -i "http://127.0.0.1:8006/api.php?resource=topics&id=1"
curl.exe -i -X POST "http://127.0.0.1:8006/api.php?resource=topics" -H "Content-Type: application/json" --data-binary "@topic.json"
curl.exe -i -X POST "http://127.0.0.1:8006/api.php?resource=topics" --data-urlencode "title=Another topic" --data-urlencode "author=sashko"
curl.exe -i -X POST "http://127.0.0.1:8006/api.php?resource=topics&action=pin&id=1"
curl.exe -i "http://127.0.0.1:8006/api.php?resource=unknown"
curl.exe -i -X DELETE "http://127.0.0.1:8006/api.php?resource=topics"
curl.exe -i -X POST "http://127.0.0.1:8006/api.php?resource=topics" -d "title=abc"
```

Підставити реальний id із відповіді POST. Не виконувати ці приклади проти чужих даних.
Це навчальний локальний API без автентифікації, не готовий публічний сервіс.

## Автоматична перевірка

На окремій тестовій БД `practicum4` з тією самою схемою, з PHP у PATH:

```console
python tests/test_api.py http://127.0.0.1:8006
```

Тести перевіряють маршрути, методи/Allow, валідацію, JSON/форму, SQL-параметри,
читання й повторне закріплення. Створюють лише записи з унікальним тестовим автором;
при завершенні видаляють тільки власні id з цим автором через db.php (DELETE у API немає).
Для зупинки PHP-сервера — Ctrl+C.
