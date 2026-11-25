<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/* ---------------- 文章 ---------------- */

function posts(string $status = 'published'): array
{
    $sql = 'SELECT * FROM posts';
    if ($status !== 'all') {
        $sql .= ' WHERE status = :status';
    }
    $sql .= ' ORDER BY published_at DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($status === 'all' ? [] : [':status' => $status]);
    return array_map('post_normalize', $stmt->fetchAll());
}

function post_normalize(array $row): array
{
    $row['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) $row['tags']))));
    $row['category_id'] = (int) ($row['category_id'] ?? 0);
    $row['url'] = '/posts/' . $row['slug'] . '.html';
    $row['date_label'] = fmt_date($row['published_at']);
    return $row;
}

function post_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? post_normalize($row) : null;
}

function post_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM posts WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ? post_normalize($row) : null;
}

function post_save(array $in, ?int $id = null): array
{
    $title = trim((string) ($in['title'] ?? ''));
    $slug  = trim((string) ($in['slug'] ?? ''));
    $body  = (string) ($in['body_html'] ?? '');
    if ($title === '') {
        return [false, '标题不能为空'];
    }
    if (!valid_slug($slug)) {
        return [false, 'slug 只能是小写字母、数字和连字符，例如 pixijs-frame-budget'];
    }
    if (trim(strip_tags($body)) === '') {
        return [false, '正文不能为空'];
    }
    $status = ($in['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $publishedAt = (string) ($in['published_at'] ?? '');
    if ($publishedAt !== '' && !valid_date($publishedAt)) {
        return [false, '发布日期格式应为 YYYY-MM-DD'];
    }
    if ($status === 'published' && $publishedAt === '') {
        $publishedAt = today();
    }
    $updatedOn = (string) ($in['updated_on'] ?? '');
    if ($updatedOn !== '' && !valid_date($updatedOn)) {
        return [false, '更新日期格式应为 YYYY-MM-DD'];
    }
    $categoryId = (int) ($in['category_id'] ?? 0);
    $category = $categoryId > 0 ? category_by_id($categoryId) : null;
    if ($categoryId > 0 && !$category) {
        return [false, '所选分类不存在，请重新选择'];
    }
    $section = $category ? $category['name'] : trim((string) ($in['section'] ?? ''));
    [$words, $minutes] = reading_stats($body);
    $tags = $in['tags'] ?? '';
    if (is_array($tags)) {
        $tags = implode(',', $tags);
    }
    $tags = implode(',', array_values(array_filter(array_map('trim', explode(',', (string) $tags)))));
    $data = [
        'slug'            => $slug,
        'title'           => $title,
        'seo_title'       => trim((string) ($in['seo_title'] ?? '')),
        'description'     => trim((string) ($in['description'] ?? '')),
        'eyebrow'         => trim((string) ($in['eyebrow'] ?? '')),
        'section'         => $section,
        'category_id'     => $categoryId > 0 ? $categoryId : null,
        'tags'            => $tags,
        'project'         => trim((string) ($in['project'] ?? '')),
        'published_at'    => $publishedAt !== '' ? $publishedAt : null,
        'updated_on'      => $updatedOn !== '' ? $updatedOn : ($publishedAt !== '' ? $publishedAt : null),
        'word_count'      => $words,
        'reading_minutes' => $minutes,
        'keywords'        => trim((string) ($in['keywords'] ?? '')) ?: implode(', ', explode(',', $tags)),
        'body_html'       => $body,
        'status'          => $status,
    ];
    try {
        if ($id) {
            $data['id'] = $id;
            $sql = 'UPDATE posts SET slug=:slug,title=:title,seo_title=:seo_title,description=:description,
                    eyebrow=:eyebrow,section=:section,category_id=:category_id,tags=:tags,project=:project,published_at=:published_at,
                    updated_on=:updated_on,word_count=:word_count,reading_minutes=:reading_minutes,
                    keywords=:keywords,body_html=:body_html,status=:status WHERE id=:id';
            $stmt = db()->prepare($sql);
            $stmt->execute($data);
            return [true, '已保存文章「' . $title . '」'];
        }
        $sql = 'INSERT INTO posts (slug,title,seo_title,description,eyebrow,section,category_id,tags,project,published_at,
                updated_on,word_count,reading_minutes,keywords,body_html,status)
                VALUES (:slug,:title,:seo_title,:description,:eyebrow,:section,:category_id,:tags,:project,:published_at,
                :updated_on,:word_count,:reading_minutes,:keywords,:body_html,:status)';
        $stmt = db()->prepare($sql);
        $stmt->execute($data);
        return [true, '已新建文章「' . $title . '」'];
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            return [false, 'slug 已被使用，请换一个'];
        }
        return [false, '保存失败：' . $e->getMessage()];
    }
}

function post_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
    return $stmt->execute([$id]);
}

function post_adjacent(array $post): array
{
    $date = $post['published_at'];
    $newer = null;
    $older = null;
    if ($date) {
        $stmt = db()->prepare("SELECT * FROM posts WHERE status = 'published' AND (published_at > ? OR (published_at = ? AND id > ?))
                               ORDER BY published_at ASC, id ASC LIMIT 1");
        $stmt->execute([$date, $date, $post['id']]);
        $row = $stmt->fetch();
        $newer = $row ? post_normalize($row) : null;

        $stmt = db()->prepare("SELECT * FROM posts WHERE status = 'published' AND (published_at < ? OR (published_at = ? AND id < ?))
                               ORDER BY published_at DESC, id DESC LIMIT 1");
        $stmt->execute([$date, $date, $post['id']]);
        $row = $stmt->fetch();
        $older = $row ? post_normalize($row) : null;
    }
    return [$newer, $older];
}

/* ---------------- 作品 ---------------- */

function works(string $published = 'all'): array
{
    $sql = 'SELECT * FROM works';
    if ($published === 'published') {
        $sql .= ' WHERE is_published = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $rows = db()->query($sql)->fetchAll();
    return array_map('work_normalize', $rows);
}

function work_normalize(array $row): array
{
    $row['meta_chips'] = json_decode_field($row['meta_chips'] ?? null, []);
    $row['specs'] = json_decode_field($row['specs'] ?? null, []);
    $row['points'] = json_decode_field($row['points'] ?? null, []);
    $row['cover_image'] = (string) ($row['cover_image'] ?? '');
    $row['url'] = '/works/' . $row['slug'] . '.html';
    return $row;
}

function work_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM works WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? work_normalize($row) : null;
}

/**
 * 封面只接受上传目录里的相对路径，避免把外部 URL 写进静态站。
 */
function work_cover_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }
    if (!preg_match('#^assets/uploads/works/[a-z0-9._-]+$#i', $path)) {
        return '';
    }
    return $path;
}

function work_save(array $in, ?int $id = null): array
{    $title = trim((string) ($in['title'] ?? ''));
    $slug  = trim((string) ($in['slug'] ?? ''));
    $body  = (string) ($in['body_html'] ?? '');
    if ($title === '') {
        return [false, '作品名不能为空'];
    }
    if (!valid_slug($slug)) {
        return [false, 'slug 只能是小写字母、数字和连字符'];
    }
    if (trim(strip_tags($body)) === '') {
        return [false, '正文不能为空'];
    }
    $lines = static function ($value): array {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        }
        return array_values(array_filter(array_map('trim', $items), static fn ($v) => $v !== ''));
    };
    $specs = [];
    foreach ($lines($in['specs'] ?? '') as $line) {
        $parts = explode('|', $line, 2);
        if (count($parts) === 2 && trim($parts[0]) !== '') {
            $specs[] = ['label' => trim($parts[0]), 'value' => trim($parts[1])];
        }
    }
    $kind = in_array($in['status_kind'] ?? '', ['live', 'wip'], true) ? $in['status_kind'] : '';
    $mark = (string) ($in['mark_key'] ?? 'generic');
    if (!in_array($mark, ['generic', 'winlite', 'eq', 'pixel'], true)) {
        $mark = 'generic';
    }
    $data = [
        'slug'                 => $slug,
        'title'                => $title,
        'seo_title'            => trim((string) ($in['seo_title'] ?? '')),
        'description'          => trim((string) ($in['description'] ?? '')),
        'eyebrow'              => trim((string) ($in['eyebrow'] ?? '')),
        'en'                   => trim((string) ($in['en'] ?? '')),
        'lede'                 => trim((string) ($in['lede'] ?? '')),
        'meta_chips'           => json_encode($lines($in['meta_chips'] ?? ''), JSON_UNESCAPED_UNICODE),
        'specs'                => json_encode($specs, JSON_UNESCAPED_UNICODE),
        'points'               => json_encode($lines($in['points'] ?? ''), JSON_UNESCAPED_UNICODE),
        'status_label'         => trim((string) ($in['status_label'] ?? '')),
        'status_kind'          => $kind,
        'period'               => trim((string) ($in['period'] ?? '')),
        'metric'               => trim((string) ($in['metric'] ?? '')),
        'mark_key'             => $mark,
        'cover_image'          => work_cover_path((string) ($in['cover_image'] ?? '')),
        'body_html'            => $body,
        'related_html'         => (string) ($in['related_html'] ?? ''),
        'app_type'             => trim((string) ($in['app_type'] ?? 'SoftwareApplication')) ?: 'SoftwareApplication',
        'application_category' => trim((string) ($in['application_category'] ?? '')),
        'operating_system'     => trim((string) ($in['operating_system'] ?? '')),
        'version'              => trim((string) ($in['version'] ?? '')),
        'sort_order'           => (int) ($in['sort_order'] ?? 0),
        'is_published'         => !empty($in['is_published']) ? 1 : 0,
    ];
    try {
        if ($id) {
            $data['id'] = $id;
            $stmt = db()->prepare('UPDATE works SET slug=:slug,title=:title,seo_title=:seo_title,description=:description,
                eyebrow=:eyebrow,en=:en,lede=:lede,meta_chips=:meta_chips,specs=:specs,points=:points,
                status_label=:status_label,status_kind=:status_kind,period=:period,metric=:metric,mark_key=:mark_key,
                cover_image=:cover_image,
                body_html=:body_html,related_html=:related_html,app_type=:app_type,application_category=:application_category,
                operating_system=:operating_system,version=:version,sort_order=:sort_order,is_published=:is_published
                WHERE id=:id');
            $stmt->execute($data);
            return [true, '已保存作品「' . $title . '」'];
        }
        $stmt = db()->prepare('INSERT INTO works (slug,title,seo_title,description,eyebrow,en,lede,meta_chips,specs,points,
            status_label,status_kind,period,metric,mark_key,cover_image,body_html,related_html,app_type,application_category,
            operating_system,version,sort_order,is_published)
            VALUES (:slug,:title,:seo_title,:description,:eyebrow,:en,:lede,:meta_chips,:specs,:points,
            :status_label,:status_kind,:period,:metric,:mark_key,:cover_image,:body_html,:related_html,:app_type,:application_category,
            :operating_system,:version,:sort_order,:is_published)');
        $stmt->execute($data);
        return [true, '已新建作品「' . $title . '」'];
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            return [false, 'slug 已被使用，请换一个'];
        }
        return [false, '保存失败：' . $e->getMessage()];
    }
}

function work_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM works WHERE id = ?');
    return $stmt->execute([$id]);
}

function work_adjacent(array $work): array
{
    $items = works('published');
    $index = null;
    foreach ($items as $i => $item) {
        if ((int) $item['id'] === (int) $work['id']) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return [null, null];
    }
    return [$items[$index - 1] ?? null, $items[$index + 1] ?? null];
}

/* ---------------- 分类 ---------------- */

function categories(): array
{
    $rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS post_count
        FROM categories c ORDER BY c.sort_order ASC, c.id ASC')->fetchAll();
    foreach ($rows as &$row) {
        $row['post_count'] = (int) $row['post_count'];
    }
    return $rows;
}

function category_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function category_save(array $in, ?int $id = null): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $slug = trim((string) ($in['slug'] ?? ''));
    if ($name === '') {
        return [false, '分类名不能为空'];
    }
    if ($slug !== '' && !valid_slug($slug)) {
        return [false, 'slug 只能是小写字母、数字和连字符，可留空自动生成'];
    }
    if ($slug === '') {
        $slug = category_auto_slug($name);
    }
    $data = [
        'name'        => $name,
        'slug'        => $slug,
        'description' => trim((string) ($in['description'] ?? '')),
        'sort_order'  => (int) ($in['sort_order'] ?? 0),
    ];
    try {
        if ($id) {
            $data['id'] = $id;
            $stmt = db()->prepare('UPDATE categories SET name=:name,slug=:slug,description=:description,sort_order=:sort_order WHERE id=:id');
            $stmt->execute($data);
            // 分类改名后，文章的 section 文本跟着换
            $stmt = db()->prepare('UPDATE posts SET section = ? WHERE category_id = ?');
            $stmt->execute([$name, $id]);
            return [true, '已保存分类「' . $name . '」'];
        }
        $stmt = db()->prepare('INSERT INTO categories (name,slug,description,sort_order)
            VALUES (:name,:slug,:description,:sort_order)');
        $stmt->execute($data);
        return [true, '已新建分类「' . $name . '」'];
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            return [false, '分类名或 slug 已被使用'];
        }
        return [false, '保存失败：' . $e->getMessage()];
    }
}

function category_delete(int $id): bool
{
    $stmt = db()->prepare('UPDATE posts SET category_id = NULL WHERE category_id = ?');
    $stmt->execute([$id]);
    $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
    return $stmt->execute([$id]);
}

/**
 * 中文分类名没有现成拼音表，生成一个稳定且不重复的 ASCII slug。
 */
function category_auto_slug(string $name): string
{
    $base = 'cat-' . substr(md5($name), 0, 6);
    $slug = $base;
    $i = 1;
    while (true) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE slug = ?');
        $stmt->execute([$slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

/* ---------------- 统计 ---------------- */

function site_stats(): array
{
    return [
        'posts'       => (int) db()->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn(),
        'works'       => (int) db()->query('SELECT COUNT(*) FROM works WHERE is_published = 1')->fetchColumn(),
        'categories'  => (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'drafts'      => (int) db()->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn(),
        'latest_date' => (string) (db()->query("SELECT MAX(GREATEST(COALESCE(published_at, '1970-01-01'), COALESCE(updated_on, '1970-01-01'))) FROM posts WHERE status = 'published'")->fetchColumn() ?: ''),
        'latest_post' => (string) (db()->query("SELECT MAX(published_at) FROM posts WHERE status = 'published'")->fetchColumn() ?: ''),
    ];
}
