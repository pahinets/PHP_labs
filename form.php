<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==========================================================
// Крок 3. Серверна обробка й валідація (варіант 9 — Форум)
// ==========================================================

// Безпечне виведення користувацьких даних
function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Довжина рядка в символах (працює і без розширення mbstring)
function strLength(string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return (int) preg_match_all('/./us', $value);
}

// Безпечне читання рядкового поля з $_POST (масив замість рядка -> '')
function postString(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

$errors = [];
$submitted = false;

$title   = '';
$author  = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;

    $title   = postString('title');
    $author  = postString('author');
    $message = postString('message');

    // --- Валідація title: обов'язкове поле + без заборонених спецсимволів ---
    if ($title === '') {
        $errors['title'] = 'Назва теми обов\'язкова';
    } elseif (!preg_match('/^[^<>{}\[\]\\\\\/]+$/u', $title)) {
        $errors['title'] = 'Назва теми містить заборонені символи: < > { } [ ] \ /';
    } elseif (strLength($title) < 5 || strLength($title) > 100) {
        $errors['title'] = 'Назва теми має бути від 5 до 100 символів';
    }

    // --- Валідація author: обов'язкове поле ---
    if ($author === '') {
        $errors['author'] = 'Ім\'я автора обов\'язкове';
    } elseif (!preg_match('/^[\p{L}\s\'\-]+$/u', $author)) {
        $errors['author'] = 'Ім\'я автора може містити лише літери, пробіли, апостроф та дефіс';
    }

    // --- Валідація message: не менше 10 символів ---
    if (strLength($message) < 10) {
        $errors['message'] = 'Повідомлення має містити не менше 10 символів';
    }
}

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

            <!--
                novalidate: браузер не показує власні підказки, натомість усі перевірки
                виконує JS нижче (ті самі правила, що й у атрибутах та на сервері).
            -->
            <form method="post" action="form.php" id="topicForm" novalidate>

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

                <div class="form-actions">
                    <button type="submit" class="btn">Опублікувати тему</button>
                    <button type="button" class="btn btn-outline" id="clearDraftBtn">Очистити чернетку</button>
                </div>

            </form>

        <?php endif; ?>

    </div>

</div>

<!-- ==========================================================
     Крок 5. Клієнтська JS-валідація
     Крок 6. Чернетка форми через localStorage
     ========================================================== -->
<script>
(function () {
    const DRAFT_KEY = 'forum_new_topic_draft';

    const form = document.getElementById('topicForm');

    // Форми немає -> це сторінка підтвердження (сервер прийняв дані).
    // Тільки тепер чернетка більше не потрібна.
    if (!form) {
        try {
            localStorage.removeItem(DRAFT_KEY);
        } catch (e) {
            console.warn('Не вдалося очистити чернетку в localStorage:', e);
        }
        return;
    }

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

    // --- Клієнтська валідація: ті самі правила, що й на сервері ---
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

    function showError(name, text) {
        errorBoxes[name].textContent = text;
        errorBoxes[name].hidden = false;
    }

    function hideError(name) {
        errorBoxes[name].hidden = true;
    }

    // --- Відновлення чернетки при завантаженні сторінки ---
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

    // --- Збереження чернетки при кожному введенні ---
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
            hideError(name); // користувач править поле -> прибираємо стару помилку
        });
    });

    // --- Очищення чернетки вручну ---
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

    // --- Перевірка перед відправкою (без перезавантаження сторінки) ---
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
        // Чернетку тут НЕ видаляємо: сервер ще може відхилити дані.
        // Вона очищується на сторінці підтвердження (див. початок скрипта).
    });
})();
</script>

</body>
</html>