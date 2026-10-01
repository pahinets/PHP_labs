<?php
// Крок 2. Базовий клас — тема форуму
class Topic
{
    // Властивості protected: недоступні ззовні, але доступні класам-нащадкам
    protected string $title;
    protected string $author;
    protected string $createdAt;

    // Крок 2. Конструктор — ініціалізація властивостей
    public function __construct(string $title, string $author, string $createdAt)
    {
        $this->title = $title;
        $this->author = $author;
        $this->createdAt = $createdAt;
    }

    // Крок 2. Методи доступу до властивостей
    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    // Крок 2. Текстовий опис об'єкта
    public function getInfo(): string
    {
        return "{$this->title} — автор {$this->author}, створено {$this->createdAt}";
    }
}
