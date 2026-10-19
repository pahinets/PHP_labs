<?php
// Клас доступу до таблиці topics: усі SQL-запити варіанта 9 зібрано в одному місці
class TopicRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Крок 3. SELECT: усі теми (запит без параметрів — query())
    public function listAll(): array
    {
        return $this->pdo
            ->query('SELECT * FROM topics ORDER BY is_pinned DESC, created_at DESC, id DESC')
            ->fetchAll();
    }

    // SELECT одного рядка за id (для форми редагування)
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM topics WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // SELECT * FROM topics WHERE author = :author
    public function listByAuthor(string $author): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM topics WHERE author = :author ORDER BY created_at DESC, id DESC');
        $stmt->execute([':author' => $author]);
        return $stmt->fetchAll();
    }

    // SELECT * FROM topics WHERE is_pinned = 1
    public function listPinned(): array
    {
        return $this->pdo
            ->query('SELECT * FROM topics WHERE is_pinned = 1 ORDER BY created_at DESC, id DESC')
            ->fetchAll();
    }

    // Крок 4. INSERT; повертає id щойно доданого рядка
    public function addTopic(string $title, string $author, bool $isPinned = false): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO topics (title, author, created_at, is_pinned)
             VALUES (:title, :author, CURDATE(), :is_pinned)'
        );
        $stmt->execute([
            ':title'     => $title,
            ':author'    => $author,
            ':is_pinned' => (int) $isPinned,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Крок 5. UPDATE ... WHERE id = :id; повертає кількість змінених рядків
    public function updateTopic(int $id, string $title, string $author, bool $isPinned): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE topics
                SET title = :title, author = :author, is_pinned = :is_pinned
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'     => $title,
            ':author'    => $author,
            ':is_pinned' => (int) $isPinned,
            ':id'        => $id,
        ]);
        return $stmt->rowCount();
    }

    // Крок 6. DELETE FROM topics WHERE id = :id
    public function deleteTopic(int $id): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM topics WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }
}
