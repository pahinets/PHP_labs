<?php
require_once __DIR__ . '/Topic.php';

// Крок 3. Похідний клас — закріплена тема
class PinnedTopic extends Topic
{
    // Крок 3. Власна властивість нащадка
    private string $pinnedUntil;

    // Крок 3. Конструктор: спершу викликаємо конструктор батьківського класу
    public function __construct(string $title, string $author, string $createdAt, string $pinnedUntil)
    {
        parent::__construct($title, $author, $createdAt);
        $this->pinnedUntil = $pinnedUntil;
    }

    public function getPinnedUntil(): string
    {
        return $this->pinnedUntil;
    }

    // Крок 3. Перевизначений метод — розширює опис батьківського класу
    public function getInfo(): string
    {
        return parent::getInfo() . ", закріплено до {$this->pinnedUntil}";
    }
}
