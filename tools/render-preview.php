<?php
declare(strict_types=1);

/**
 * 无数据库预览：把 seed.json 映射成与数据库一致的结构，渲染成完整静态站。
 * 用法：php tools/render-preview.php [输出目录]
 * 仅用于本地检查模板与样式，正式发布走后台「生成静态站」。
 */

require_once __DIR__ . '/../app/lib/bootstrap.php';
require_once __DIR__ . '/../app/lib/render.php';

$out = $argv[1] ?? (CY_ROOT . '/.preview-dist');
$seed = json_decode((string) file_get_contents(CY_APP . '/content/seed.json'), true);
if (!is_array($seed)) {
    fwrite(STDERR, "seed.json 解析失败\n");
    exit(1);
}

function pdate(?string $d): ?string
{
    return $d ? substr($d, 0, 10) : null;
}

$posts = [];
foreach (($seed['posts'] ?? []) as $p) {
    $tags = $p['tags'] ?? [];
    $posts[] = [
        'id' => count($posts) + 1,
        'slug' => $p['slug'],
        'url' => '/posts/' . $p['slug'] . '.html',
        'title' => $p['title'],
        'seo_title' => $p['seo_title'],
        'description' => $p['description'],
        'eyebrow' => $p['eyebrow'],
        'section' => $p['section'],
        'tags' => $tags,
        'project' => $p['project'] ?? '',
        'published_at' => pdate($p['published_at'] ?? ''),
        'updated_on' => pdate($p['updated_on'] ?? ($p['published_at'] ?? '')),
        'word_count' => (int) $p['word_count'],
        'reading_minutes' => (int) $p['reading_minutes'],
        'keywords' => $p['keywords'],
        'body_html' => $p['body_html'],
        'status' => 'published',
    ];
}
usort($posts, static fn ($a, $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));

$works = [];
$order = 0;
foreach (($seed['works'] ?? []) as $w) {
    $list = $w['list'] ?? [];
    $statusKind = str_contains((string) ($list['status_class'] ?? ''), 'wip') ? 'wip' : 'live';
    $works[] = [
        'id' => $order + 1,
        'slug' => $w['slug'],
        'url' => '/works/' . $w['slug'] . '.html',
        'title' => $w['title'],
        'seo_title' => $w['seo_title'],
        'description' => $w['description'],
        'eyebrow' => $w['eyebrow'],
        'en' => $list['en'] ?? '',
        'lede' => $w['lede'],
        'meta_chips' => array_values(array_filter((array) ($w['meta'] ?? []), static fn ($c) => trim((string) $c) !== '/')),
        'specs' => $w['specs'] ?? [],
        'points' => $list['points'] ?? [],
        'status_label' => $list['status'] ?? '',
        'status_kind' => $statusKind,
        'period' => $list['period'] ?? '',
        'metric' => $list['metric'] ?? '',
        'mark_key' => ['tinyplayer' => 'eq', 'winlite' => 'winlite', 'pixel-workshop' => 'pixel'][$w['slug']] ?? 'generic',
        'body_html' => $w['body_html'],
        'related_html' => $w['related_html'] ?? '',
        'app_type' => $w['slug'] === 'pixel-workshop' ? 'VideoGame' : 'SoftwareApplication',
        'application_category' => $w['application_category'] ?? '',
        'operating_system' => $w['operating_system'] ?? '',
        'version' => $w['version'] ?? '',
        'sort_order' => ['winlite' => 0, 'tinyplayer' => 1, 'pixel-workshop' => 2][$w['slug']] ?? $order,
        'is_published' => 1,
        'published_at' => pdate($w['published_at'] ?? ''),
        'updated_at' => pdate($w['updated_at'] ?? ($w['published_at'] ?? '')),
    ];
    $order++;
}
usort($works, static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

$stats = [
    'posts' => count($posts),
    'works' => count($works),
    'drafts' => 0,
    'latest_date' => $posts[0]['updated_on'] ?? '',
    'latest_post' => $posts[0]['published_at'] ?? '',
];

function put(string $rel, string $html): void
{
    $target = rtrim($GLOBALS['out'], '/') . '/' . $rel;
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($target, $html);
    echo str_pad(number_format(strlen($html)), 8, ' ', STR_PAD_LEFT) . "  " . $rel . "\n";
}

put('index.html', render_template('index', ['posts' => $posts, 'works' => $works, 'stats' => $stats]));
put('blog.html', render_template('blog', ['posts' => $posts]));
put('works.html', render_template('works', ['works' => $works]));

foreach ($posts as $i => $p) {
    $projectUrl = '';
    foreach ($works as $w) {
        if ($w['title'] === $p['project']) {
            $projectUrl = '../works/' . $w['slug'] . '.html';
        }
    }
    put('posts/' . $p['slug'] . '.html', render_template('post', [
        'post' => $p,
        'newer' => $posts[$i - 1] ?? null,
        'older' => $posts[$i + 1] ?? null,
        'projectUrl' => $projectUrl,
    ]));
}

foreach ($works as $i => $w) {
    put('works/' . $w['slug'] . '.html', render_template('work', [
        'work' => $w,
        'prevWork' => $works[$i - 1] ?? null,
        'nextWork' => $works[$i + 1] ?? null,
    ]));
}

foreach (['404.html', 'about.html', 'column.html'] as $static) {
    $from = CY_APP . '/templates/static/' . $static;
    if (is_file($from)) {
        @mkdir(dirname($out . '/' . $static), 0775, true);
        copy($from, $out . '/' . $static);
        echo "          " . $static . " (static)\n";
    }
}

$assetSrc = CY_APP . '/assets';
@mkdir($out . '/assets', 0775, true);
$assetItems = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($assetSrc, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($assetItems as $item) {
    /** @var SplFileInfo $item */
    $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $assetSrc)) + 1);
    $target = $out . '/assets/' . $rel;
    if ($item->isDir()) {
        @mkdir($target, 0775, true);
        continue;
    }
    @mkdir(dirname($target), 0775, true);
    copy($item->getPathname(), $target);
}
echo "          assets/\n";

echo "输出目录: " . realpath($out) . "\n";
