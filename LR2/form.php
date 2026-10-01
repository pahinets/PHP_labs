<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Крок 3. Функція безпечного виведення даних (екранування HTML)
function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Крок 3. Функція довжини рядка в символах
function strLength(string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return (int) preg_match_all('/./us', $value);
}

// Крок 3. Функція читання рядкового поля з $_POST
function postString(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Крок 3. Змінні стану — помилки та введені значення
$errors = [];
$submitted = false;

$title   = '';
$author  = '';
$message = '';

// Крок 3. Обробка надісланої форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;

    $title   = postString('title');
    $author  = postString('author');
    $message = postString('message');

    // Крок 3. Валідація title — обов'язкове, без заборонених спецсимволів, 5–100 символів
    if ($title === '') {
        $errors['title'] = 'Назва теми обов\'язкова';
    } elseif (!preg_match('/^[^<>{}\[\]\\\\\/]+$/u', $title)) {
        $errors['title'] = 'Назва теми містить заборонені символи: < > { } [ ] \ /';
    } elseif (strLength($title) < 5 || strLength($title) > 100) {
        $errors['title'] = 'Назва теми має бути від 5 до 100 символів';
    }

    // Крок 3. Валідація author — обов'язкове, лише літери, пробіли, апостроф, дефіс
    if ($author === '') {
        $errors['author'] = 'Ім\'я автора обов\'язкове';
    } elseif (!preg_match('/^[\p{L}\s\'\-]+$/u', $author)) {
        $errors['author'] = 'Ім\'я автора може містити лише літери, пробіли, апостроф та дефіс';
    }

    // Крок 3. Валідація message — не менше 10 символів
    if (strLength($message) < 10) {
        $errors['message'] = 'Повідомлення має містити не менше 10 символів';
    }
}

// Крок 4. Ознака успішної обробки — форму надіслано без помилок
$success = $submitted && empty($errors);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Форум — нова тема</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1>Створення нової теми</h1>
        <a href="index.php" class="btn btn-outline">← До списку тем</a>
    </div>

    <div class="form-wrap">

        <?php if ($success): ?>
            <!-- Крок 4. Підтвердження прийнятих даних -->
            <div class="summary">
                Дані прийнято. Тему <strong><?= h($title) ?></strong>
                створено автором <strong><?= h($author) ?></strong>.<br>
                Повідомлення: <?= nl2br(h($message)) ?>
            </div>
            <div class="form-actions">
                <a href="form.php" class="btn">Створити ще одну тему</a>
            </div>
        <?php else: ?>

            <!-- Крок 2. HTML-форма (novalidate — перевірку виконує JS) -->
            <form method="post" action="form.php" id="topicForm" novalidate>

                <!-- Поле: назва теми -->
                <div class="field">
                    <label for="title">Назва теми</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        minlength="5"
                        maxlength="100"
                        pattern="[^<>\{\}\[\]\\\/]+"
                        value="<?= h($title) ?>"
                    >
                    <div class="field-error" id="titleError" <?= empty($errors['title']) ? 'hidden' : '' ?>><?= h($errors['title'] ?? '') ?></div>
                </div>

                <!-- Поле: автор -->
                <div class="field">
                    <label for="author">Автор</label>
                    <input
                        type="text"
                        id="author"
                        name="author"
                        required
                        pattern="[\p{L}\s'\-]+"
                        value="<?= h($author) ?>"
                    >
                    <div class="field-error" id="authorError" <?= empty($errors['author']) ? 'hidden' : '' ?>><?= h($errors['author'] ?? '') ?></div>
                </div>

                <!-- Поле: повідомлення -->
                <div class="field">
                    <label for="message">Повідомлення</label>
                    <textarea
                        id="message"
                        name="message"
                        rows="5"
                        required
                        minlength="10"
                    ><?= h($message) ?></textarea>
                    <div class="field-error" id="messageError" <?= empty($errors['message']) ? 'hidden' : '' ?>><?= h($errors['message'] ?? '') ?></div>
                </div>

                <!-- Кнопки форми -->
                <div class="form-actions">
                    <button type="submit" class="btn">Опублікувати тему</button>
                    <button type="button" class="btn btn-outline" id="clearDraftBtn">Очистити чернетку</button>
                </div>

            </form>

        <?php endif; ?>

    </div>

</div>

<!-- Крок 5–6. Клієнтська JS-валідація та чернетка форми в localStorage -->
<script>
(function () {
    const DRAFT_KEY = 'forum_new_topic_draft';

    const form = document.getElementById('topicForm');

    // Крок 6. Сторінка підтвердження — чернетка більше не потрібна
    if (!form) {
        try {
            localStorage.removeItem(DRAFT_KEY);
        } catch (e) {
            console.warn('Не вдалося очистити чернетку в localStorage:', e);
        }
        return;
    }

    // Крок 5. Елементи форми та блоки повідомлень про помилки
    const fields = {
        title:   document.getElementById('title'),
        author:  document.getElementById('author'),
        message: document.getElementById('message')
    };
    const errorBoxes = {
        title:   document.getElementById('titleError'),
        author:  document.getElementById('authorError'),
        message: document.getElementById('messageError')
    };
    const clearBtn = document.getElementById('clearDraftBtn');

    // Крок 5. Клієнтська валідація — ті самі правила, що й на сервері
    function validate() {
        const errors = {};

        const title = fields.title.value.trim();
        const titleLen = Array.from(title).length;
        if (title === '') {
            errors.title = 'Назва теми обов\'язкова';
        } else if (/[<>{}\[\]\\\/]/.test(title)) {
            errors.title = 'Назва теми містить заборонені символи: < > { } [ ] \\ /';
        } else if (titleLen < 5 || titleLen > 100) {
            errors.title = 'Назва теми має бути від 5 до 100 символів';
        }

        const author = fields.author.value.trim();
        if (author === '') {
            errors.author = 'Ім\'я автора обов\'язкове';
        } else if (!/^[\p{L}\s'\-]+$/u.test(author)) {
            errors.author = 'Ім\'я автора може містити лише літери, пробіли, апостроф та дефіс';
        }

        if (Array.from(fields.message.value.trim()).length < 10) {
            errors.message = 'Повідомлення має містити не менше 10 символів';
        }

        return errors;
    }

    // Крок 5. Показ і приховування повідомлення про помилку
    function showError(name, text) {
        errorBoxes[name].textContent = text;
        errorBoxes[name].hidden = false;
    }

    function hideError(name) {
        errorBoxes[name].hidden = true;
    }

    // Крок 6. Відновлення чернетки при завантаженні сторінки
    try {
        const saved = localStorage.getItem(DRAFT_KEY);
        if (saved) {
            const draft = JSON.parse(saved);
            if (!fields.title.value && draft.title)     fields.title.value = draft.title;
            if (!fields.author.value && draft.author)   fields.author.value = draft.author;
            if (!fields.message.value && draft.message) fields.message.value = draft.message;
        }
    } catch (e) {
        console.warn('Не вдалося прочитати чернетку з localStorage:', e);
    }

    // Крок 6. Збереження чернетки при кожному введенні
    function saveDraft() {
        const draft = {
            title: fields.title.value,
            author: fields.author.value,
            message: fields.message.value
        };
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        } catch (e) {
            console.warn('Не вдалося зберегти чернетку в localStorage:', e);
        }
    }

    Object.keys(fields).forEach(function (name) {
        fields[name].addEventListener('input', function () {
            saveDraft();
            hideError(name);
        });
    });

    // Крок 6. Очищення чернетки вручну
    clearBtn.addEventListener('click', function () {
        try {
            localStorage.removeItem(DRAFT_KEY);
        } catch (e) {
            console.warn('Не вдалося очистити чернетку в localStorage:', e);
        }
        Object.keys(fields).forEach(function (name) {
            fields[name].value = '';
            hideError(name);
        });
    });

    // Крок 5. Перевірка перед відправкою (без перезавантаження сторінки)
    form.addEventListener('submit', function (event) {
        const errors = validate();
        const names = Object.keys(fields);
        let firstInvalid = null;

        names.forEach(function (name) {
            if (errors[name]) {
                showError(name, errors[name]);
                if (!firstInvalid) firstInvalid = fields[name];
            } else {
                hideError(name);
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
        }
        // Крок 6. Чернетку не видаляємо — сервер ще може відхилити дані
    });
})();
</script>

</body>
</html>
