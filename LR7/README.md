# ЛР7 — відтворення оптимізації (варіант 9)

PHP 8.1+ (`pdo_mysql`, `mbstring`), MySQL/MariaDB, ApacheBench (`ab`).
Запускати весь репозиторій: спільні файли безпеки розташовані поруч із LR7.
Для звичайних даних залишається `schema.sql`; дослід використовує **лише
окрему `practicum7_bench`**, не робочу `practicum4`.

## 1. Seed: 1000 тем / 100 авторів

PowerShell, папка `LR7` (локально — `Practicum07`):

```powershell
$env:PHP_LAB_DSN = 'mysql:host=127.0.0.1;dbname=practicum7_bench;charset=utf8mb4'
$env:PHP_LAB_DB_USER = 'root'
$env:PHP_LAB_DB_PASSWORD = ''
php -r '$p=new PDO("mysql:host=127.0.0.1;charset=utf8mb4",getenv("PHP_LAB_DB_USER"),getenv("PHP_LAB_DB_PASSWORD"));$p->exec(file_get_contents("seed.sql"));'
php -S 127.0.0.1:8007 -t ..
```

**Увага:** повторний `seed.sql` замінює тільки `practicum7_bench.topics`.
Фіксовані ID 1–1000, по 10 тем автора, 50 pinned; індексу author ще немає.
Не запускати `schema.sql` для цього досліду: він уже додає індекс.

## 2. Той самий API до / після індексу

Другий термінал, та сама папка та ті самі три змінні середовища БД,
що й у сервера. `ab` має бути в PATH або задайте `AB_BIN`.

```powershell
# Якщо ab не в PATH: $env:AB_BIN = 'C:\Apache24\bin\ab.exe'
$folder = Split-Path (Get-Location) -Leaf
$api = "http://127.0.0.1:8007/$folder/api.php"
php measure.php before $api | Set-Content -Encoding utf8 "$env:TEMP\lab7-before.json"
php -r '$p=require "db.php";$p->exec(file_get_contents("create_index.sql"));$p->query("ANALYZE TABLE topics")->fetchAll();'
php measure.php after $api | Set-Content -Encoding utf8 "$env:TEMP\lab7-after.json"
```

Обидва заміри: `?resource=topics&mode=original&author=author_042`, **1 SQL,
без кешу та підрахунку N+1**. Змінюється лише індекс. `measure.php` перевіряє
БД/seed/стан індексу/HTTP/JSON/SQL, виконує точний `explain.sql`, 10 профілів
PHP та три `ab -n 200 -c 20`. JSON містить усі прогони й хеші даних/відповіді;
ці тимчасові файли не треба додавати в Git.

Фактичні EXPLAIN і результати: **[results.md](results.md)**.
Результат на іншій машині не мусить збігатися до мілісекунди.

## 3. N+1 та кеш — окремо

Після додавання індексу, без зміни даних:

```powershell
$ab = if ($env:AB_BIN) { $env:AB_BIN } else { 'ab' }
& $ab -n 200 -c 20 "$api`?resource=topics&mode=before"
& $ab -n 200 -c 20 "$api`?resource=topics&mode=after"
curl.exe "$api`?resource=topics&action=pinned"
& $ab -n 200 -c 20 "$api`?resource=topics&action=pinned"
```

`before`: 1001 SQL; `after`: 2 SQL з тим самим JSON; теплий pinned: 0 SQL,
TTL 60 с, інвалідація після змін. `X-Elapsed-Ms`, `X-Peak-Memory-Mb`,
`X-SQL-Queries`, `X-Cache` показують профіль. Точний `author` використовує
індекс; старий `q=...` — підрядок `LIKE '%...%'`, його індекс не прискорює.
У Windows PHP built-in server послідовний: `-c 20` — клієнти/черга, не workers.
POST захищено CSRF; закріплення — лише модератору. Дослід використовує GET.
