<?php
require_once __DIR__ . '/Topic.php';
require_once __DIR__ . '/PinnedTopic.php';

// Крок 4. Клас-менеджер — колекція тем форуму
class Forum
{
    // Масив об'єктів Topic та PinnedTopic
    private array $topics = [];

    // Крок 4. Додавання теми (приймає і Topic, і PinnedTopic)
    public function addTopic(Topic $topic): void
    {
        $this->topics[] = $topic;
    }

    // Крок 4. Усі теми
    public function getAll(): array
    {
        return $this->topics;
    }

    // Крок 4. Кількість тем
    public function count(): int
    {
        return count($this->topics);
    }

    // Крок 4. Фільтрація — теми вказаного автора
    public function listByAuthor(string $author): array
    {
        return array_values(array_filter(
            $this->topics,
            fn(Topic $topic) => $topic->getAuthor() === $author
        ));
    }

    // Крок 4. Фільтрація — лише закріплені теми
    public function listPinned(): array
    {
        return array_values(array_filter(
            $this->topics,
            fn(Topic $topic) => $topic instanceof PinnedTopic
        ));
    }
}
