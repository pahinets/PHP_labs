<?php
// Крок 5. Бібліотека допоміжних функцій

// Крок 5. Скільки часу минуло від дати (наприклад, «3 дн. тому»)
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

// Крок 5. Очищення тексту й екранування HTML для безпечного виведення
function sanitizeText(string $text): string
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
