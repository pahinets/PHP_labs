<?php
// Спільна HTML-форма для add.php та edit.php
// Очікує змінні: $action (URL), $values (title, author, is_pinned), $errors, $submitLabel
?>
<form method="post" action="<?= h($action) ?>">

    <div class="field">
        <label for="title">Назва теми</label>
        <input type="text" id="title" name="title" value="<?= h($values['title']) ?>">
        <?php if (!empty($errors['title'])): ?>
            <div class="field-error"><?= h($errors['title']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="author">Автор</label>
        <input type="text" id="author" name="author" value="<?= h($values['author']) ?>">
        <?php if (!empty($errors['author'])): ?>
            <div class="field-error"><?= h($errors['author']) ?></div>
        <?php endif; ?>
    </div>

    <div class="field field-check">
        <input type="checkbox" id="is_pinned" name="is_pinned" value="1" <?= $values['is_pinned'] ? 'checked' : '' ?>>
        <label for="is_pinned">Закріпити тему</label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn"><?= h($submitLabel) ?></button>
        <a href="index.php" class="btn btn-outline">Скасувати</a>
    </div>

</form>
