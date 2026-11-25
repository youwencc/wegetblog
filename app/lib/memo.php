<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * 后台备忘录：只存在数据库里，不进入前台静态站。
 */

function memo_all(int $limit = 0): array
{
    $sql = 'SELECT * FROM memos ORDER BY pinned DESC, updated_at DESC, id DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return db()->query($sql)->fetchAll();
}

/**
 * 所有置顶备忘录，后台顶部横条用。
 */
function memo_pinned(): array
{
    return db()->query('SELECT * FROM memos WHERE pinned = 1 ORDER BY updated_at DESC, id DESC')->fetchAll();
}

function memo_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM memos WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * 新建或更新备忘录，返回 [ok, message]。
 */
function memo_save(array $in, ?int $id = null): array
{
    $title = trim((string) ($in['title'] ?? ''));
    $content = trim((string) ($in['content'] ?? ''));
    $pinned = !empty($in['pinned']) ? 1 : 0;

    if ($content === '') {
        return [false, '备忘录内容不能为空。'];
    }
    if (mb_strlen($content) > 5000) {
        return [false, '备忘录内容太长了，控制在 5000 字以内。'];
    }
    if (mb_strlen($title) > 120) {
        return [false, '标题最多 120 个字。'];
    }

    if ($id) {
        $stmt = db()->prepare('UPDATE memos SET title = ?, content = ?, pinned = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$title, $content, $pinned, $id]);
        return [true, '备忘录已更新。'];
    }
    $stmt = db()->prepare('INSERT INTO memos (title, content, pinned) VALUES (?, ?, ?)');
    $stmt->execute([$title, $content, $pinned]);
    return [true, '备忘录已添加。'];
}

function memo_delete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM memos WHERE id = ?');
    $stmt->execute([$id]);
}

function memo_toggle_pin(int $id): void
{
    $stmt = db()->prepare('UPDATE memos SET pinned = 1 - pinned, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$id]);
}

function memo_stats(): array
{
    return [
        'total'  => (int) db()->query('SELECT COUNT(*) FROM memos')->fetchColumn(),
        'pinned' => (int) db()->query('SELECT COUNT(*) FROM memos WHERE pinned = 1')->fetchColumn(),
    ];
}

function memo_excerpt(string $content, int $length = 90): string
{
    $plain = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
    return mb_strlen($plain) > $length ? mb_substr($plain, 0, $length) . '…' : $plain;
}
