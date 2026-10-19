<?php
// Крок 5. Бібліотека допоміжних функцій

// Скільки часу минуло від дати
function timeAgo(string $date, ?DateTimeImmutable $now = null): string
{
    try {
        $then = new DateTimeImmutable($date);
    } catch (Exception $e) {
        return 'невідомо';
    }

    $now = $now ?? new DateTimeImmutable('now');

    // Порівнюємо лише дати, без часу
    $then = $then->setTime(0, 0);
    $now = $now->setTime(0, 0);

    if ($then > $now) {
        return 'ще не настало';
    }

    $days = (int) $then->diff($now)->days;

    if ($days === 0) {
        return 'сьогодні';
    }
    if ($days === 1) {
        return 'вчора';
    }
    if ($days < 30) {
        return "{$days} дн. тому";
    }
    if ($days < 365) {
        return intdiv($days, 30) . ' міс. тому';
    }
    return intdiv($days, 365) . ' р. тому';
}

// Очищення тексту й екранування HTML для безпечного виведення
function sanitizeText(string $text): string
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ПР4. Допоміжні функції для форм (перенесено з form.php ЛР2)

// Безпечне виведення в HTML-атрибути та текст
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Довжина рядка в символах
function strLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

// Безпечне читання рядкового поля з $_POST
function postString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Перевірка полів теми; повертає масив помилок (порожній — дані коректні)
function validateTopic(string $title, string $author): array
{
    $errors = [];

    if ($title === '') {
        $errors['title'] = 'Назва теми обов\'язкова';
    } elseif (strLength($title) < 5 || strLength($title) > 100) {
        $errors['title'] = 'Назва теми має бути від 5 до 100 символів';
    }

    if ($author === '') {
        $errors['author'] = 'Ім\'я автора обов\'язкове';
    } elseif (strLength($author) > 50) {
        $errors['author'] = 'Ім\'я автора має бути не довшим за 50 символів';
    }

    return $errors;
}
