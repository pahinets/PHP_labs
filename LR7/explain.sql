-- Той самий SELECT, що API mode=original&author=author_042.
-- Тут підставлено значення параметра :author; поля та ORDER BY збережено.
EXPLAIN
SELECT t.id, t.title, t.author, t.created_at, t.is_pinned
FROM topics t
WHERE t.author = 'author_042'
ORDER BY t.id DESC;
