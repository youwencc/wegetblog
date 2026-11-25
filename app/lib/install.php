<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/publish.php';

/**
 * 建表（幂等）。
 */
function install_schema(): void
{
    $sql = (string) file_get_contents(CY_APP . '/schema.sql');
    // 去掉注释行，按分号切分执行
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        db()->exec($statement);
    }
    migrate_schema();
}

/**
 * 增量迁移：给已经存在的库补字段/补表，重复执行安全。
 */
function migrate_schema(): void
{
    $columns = [
        ['works', 'cover_image', "VARCHAR(255) NOT NULL DEFAULT '' AFTER mark_key"],
        ['posts', 'category_id', 'INT UNSIGNED NULL AFTER section'],
    ];
    foreach ($columns as [$table, $column, $definition]) {
        $stmt = db()->query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");
        if ($stmt && $stmt->fetch()) {
            continue;
        }
        db()->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
    settings_ensure_table();
    migrate_categories();
    db()->exec("CREATE TABLE IF NOT EXISTS memos (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title       VARCHAR(120) NOT NULL DEFAULT '',
        content     TEXT NOT NULL,
        pinned      TINYINT(1) NOT NULL DEFAULT 0,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_memos_pinned (pinned, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * 建分类表；把旧文章里的 section 文本迁成分类并回填 category_id。
 */
function migrate_categories(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS categories (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name        VARCHAR(60) NOT NULL,
        slug        VARCHAR(80) NOT NULL,
        description VARCHAR(400) NOT NULL DEFAULT '',
        sort_order  INT NOT NULL DEFAULT 0,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_categories_slug (slug),
        UNIQUE KEY uk_categories_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $sections = db()->query("SELECT DISTINCT section FROM posts WHERE section <> '' AND category_id IS NULL")->fetchAll();
    foreach ($sections as $row) {
        $name = trim((string) $row['section']);
        if ($name === '') {
            continue;
        }
        $stmt = db()->prepare('SELECT id FROM categories WHERE name = ?');
        $stmt->execute([$name]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id === 0) {
            $slug = category_auto_slug($name);
            $ins = db()->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (?,?,?,?)');
            $ins->execute([$name, $slug, '', 0]);
            $id = (int) db()->lastInsertId();
        }
        $upd = db()->prepare('UPDATE posts SET category_id = ? WHERE section = ? AND category_id IS NULL');
        $upd->execute([$id, $name]);
    }
}

function table_count(string $table): int
{
    return (int) db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

/**
 * 从 app/content/seed.json 导入现有文章与作品（仅当表为空时）。
 * 返回 [posts_inserted, works_inserted, skipped]
 */
function import_seed(bool $force = false): array
{
    $file = cfg('site.seed_file', 'app/content/seed.json');
    if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $file)) {
        $file = CY_ROOT . '/' . ltrim($file, '/');
    }
    if (!is_file($file)) {
        throw new RuntimeException('找不到种子文件: ' . $file);
    }
    $seed = json_decode((string) file_get_contents($file), true);
    if (!is_array($seed)) {
        throw new RuntimeException('seed.json 解析失败');
    }
    $insertedPosts = 0;
    $insertedWorks = 0;
    $skipped = 0;

    if ($force || table_count('posts') === 0) {
        // 全量导入时，先删掉种子里的同名文章，保证内容与 seed.json 一致；不碰其他文章
        if ($force) {
            $stmt = db()->prepare('DELETE FROM posts WHERE slug = ?');
            foreach (($seed['posts'] ?? []) as $p) {
                if (is_array($p) && !empty($p['slug'])) {
                    $stmt->execute([(string) $p['slug']]);
                }
            }
        }
        foreach (($seed['posts'] ?? []) as $p) {
            if (!is_array($p) || empty($p['slug'])) {
                continue;
            }
            $tags = $p['tags'] ?? [];
            if (!is_array($tags)) {
                $tags = array_filter(array_map('trim', explode(',', (string) $tags)));
            }
            $body = (string) ($p['body_html'] ?? '');
            [$words, $minutes] = reading_stats($body);
            $stmt = db()->prepare('INSERT INTO posts (slug,title,seo_title,description,eyebrow,section,tags,project,
                published_at,updated_on,word_count,reading_minutes,keywords,body_html,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                (string) $p['slug'],
                (string) ($p['title'] ?? ''),
                (string) ($p['seo_title'] ?? ''),
                (string) ($p['description'] ?? ''),
                (string) ($p['eyebrow'] ?? ''),
                (string) ($p['section'] ?? ''),
                implode(',', array_map('strval', $tags)),
                (string) ($p['project'] ?? ''),
                ($p['published_at'] ?? '') ?: null,
                ($p['updated_at'] ?? $p['updated_on'] ?? $p['published_at'] ?? '') ?: null,
                (int) ($p['word_count'] ?? $words),
                (int) ($p['reading_minutes'] ?? $minutes),
                (string) ($p['keywords'] ?? ''),
                $body,
                'published',
            ]);
            $insertedPosts++;
        }
    } else {
        $skipped++;
    }

    if ($force || table_count('works') === 0) {
        if ($force) {
            $stmt = db()->prepare('DELETE FROM works WHERE slug = ?');
            foreach (($seed['works'] ?? []) as $w) {
                if (is_array($w) && !empty($w['slug'])) {
                    $stmt->execute([(string) $w['slug']]);
                }
            }
        }
        $order = 0;
        foreach (($seed['works'] ?? []) as $w) {
            if (!is_array($w) || empty($w['slug'])) {
                continue;
            }
            $list = is_array($w['list'] ?? null) ? $w['list'] : [];
            $statusKind = 'live';
            if (str_contains((string) ($list['status_class'] ?? ''), 'wip')) {
                $statusKind = 'wip';
            }
            $mark = ['tinyplayer' => 'eq', 'winlite' => 'winlite', 'pixel-workshop' => 'pixel'][$w['slug']] ?? 'generic';
            $version = '';
            if (preg_match('/v[\d.]+/', (string) ($list['status'] ?? ''), $m)) {
                $version = ltrim($m[0], 'v');
            }
            $isGame = $w['slug'] === 'pixel-workshop';
            $category = (string) ($w['application_category'] ?? '');
            $category = match ($category) {
                'Game' => 'GameApplication',
                'Utilities' => 'UtilityApplication',
                'Multimedia' => 'MultimediaApplication',
                default => $category,
            };
            $stmt = db()->prepare('INSERT INTO works (slug,title,seo_title,description,eyebrow,en,lede,meta_chips,specs,points,
                status_label,status_kind,period,metric,mark_key,body_html,related_html,app_type,application_category,
                operating_system,version,sort_order,is_published,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                (string) $w['slug'],
                (string) ($w['title'] ?? ''),
                (string) ($w['seo_title'] ?? ''),
                (string) ($w['description'] ?? ''),
                (string) ($w['eyebrow'] ?? ''),
                (string) ($list['en'] ?? ''),
                (string) ($w['lede'] ?? ''),
                json_encode(array_values(array_filter((array) ($w['meta'] ?? []), static fn ($c) => trim((string) $c) !== '/')), JSON_UNESCAPED_UNICODE),
                json_encode(array_values((array) ($w['specs'] ?? [])), JSON_UNESCAPED_UNICODE),
                json_encode(array_values((array) ($list['points'] ?? [])), JSON_UNESCAPED_UNICODE),
                (string) ($list['status'] ?? ''),
                $statusKind,
                (string) ($list['period'] ?? ''),
                (string) ($list['metric'] ?? ''),
                $mark,
                (string) ($w['body_html'] ?? ''),
                (string) ($w['related_html'] ?? ''),
                (string) ($w['app_type'] ?? ($isGame ? 'VideoGame' : 'SoftwareApplication')),
                $category !== '' ? $category : ($isGame ? 'GameApplication' : 'UtilityApplication'),
                (string) ($w['operating_system'] ?? ($isGame ? 'Web' : 'Windows 10 / 11')),
                (string) ($w['version'] ?? $version),
                ['winlite' => 0, 'tinyplayer' => 1, 'pixel-workshop' => 2][$w['slug']] ?? $order,
                1,
                ($w['published_at'] ?? '') ?: now(),
                ($w['updated_at'] ?? '') ?: now(),
            ]);
            $order++;
            $insertedWorks++;
        }
    } else {
        $skipped++;
    }

    return ['posts' => $insertedPosts, 'works' => $insertedWorks, 'skipped' => $skipped];
}

/**
 * 全量导入：按 seed.json 覆盖同名文章/作品，补建分类，缺设置时写入默认设置。
 * 不属于种子条目的内容不会被删除。
 */
function import_seed_full(): array
{
    $result = import_seed(true);
    migrate_categories();

    settings_ensure_table();
    $stmt = db()->prepare('SELECT COUNT(*) FROM settings WHERE k = ?');
    $stmt->execute(['site']);
    $count = (int) $stmt->fetchColumn();
    if ($count === 0) {
        settings_store(settings_defaults());
        $result['settings'] = 1;
    } else {
        $result['settings'] = 0;
    }
    $result['categories'] = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    return $result;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
