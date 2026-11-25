<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/settings.php';

/**
 * 输出 <head> 与 <body> 之前的公共部分。
 * 选项：title, description, path, type, og_title, og_desc, published, modified,
 *       section, keywords, image, page, jsonld(array|string), depth(0|1), body_class
 * page 传入 home/blog/works/about/column 时，后台 SEO 设置会覆盖标题与描述。
 */
function page_head(array $o): void
{
    $depth = (int) ($o['depth'] ?? 0);
    $root  = $depth > 0 ? '../' : '';
    $title = (string) ($o['title'] ?? cfg('site.name'));
    $desc  = (string) ($o['description'] ?? '');
    $pageKey = (string) ($o['page'] ?? '');
    if ($pageKey !== '') {
        $seo = seo_for_page($pageKey);
        if ($seo['title'] !== '') {
            $title = $seo['title'];
        }
        if ($seo['desc'] !== '') {
            $desc = $seo['desc'];
        }
    }
    $canonical = site_url((string) ($o['path'] ?? ''));
    $type  = (string) ($o['type'] ?? 'website');
    $ogTitle = $pageKey !== '' && seo_for_page($pageKey)['title'] !== '' ? $title : (string) ($o['og_title'] ?? $title);
    $ogDesc  = $pageKey !== '' && seo_for_page($pageKey)['desc'] !== '' ? $desc : (string) ($o['og_desc'] ?? $desc);
    $keywords = trim((string) ($o['keywords'] ?? ''));
    if ($keywords === '') {
        $keywords = trim((string) (settings_seo()['keywords'] ?? ''));
    }
    $image = trim((string) ($o['image'] ?? ''));
    if ($image === '') {
        $image = seo_og_image();
    }
    $jsonld = $o['jsonld'] ?? null;
    $jsonldStr = is_array($jsonld)
        ? json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        : (string) $jsonld;

    echo "<!DOCTYPE html>\n";
    echo '<html lang="' . h((string) cfg('site.lang', 'zh-CN')) . '" data-theme="light">' . "\n<head>\n";
    echo '<meta charset="utf-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo "<script>\n  try {\n    var t = localStorage.getItem(\"cy-theme\");\n    if (!t) t = window.matchMedia(\"(prefers-color-scheme: dark)\").matches ? \"dark\" : \"light\";\n    document.documentElement.setAttribute(\"data-theme\", t);\n  } catch (e) {}\n</script>\n";
    echo '<title>' . h($title) . "</title>\n";
    echo '<meta name="description" content="' . h($desc) . '">' . "\n";
    if ($keywords !== '') {
        echo '<meta name="keywords" content="' . h($keywords) . '">' . "\n";
    }
    echo '<meta name="author" content="' . h((string) cfg('site.author')) . '">' . "\n";
    echo '<link rel="canonical" href="' . h($canonical) . '">' . "\n";
    echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
    echo '<meta name="theme-color" content="#f7f3e9">' . "\n\n";
    echo '<meta property="og:type" content="' . h($type) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . h((string) cfg('site.name')) . '">' . "\n";
    echo '<meta property="og:locale" content="zh_CN">' . "\n";
    echo '<meta property="og:title" content="' . h($ogTitle) . '">' . "\n";
    echo '<meta property="og:description" content="' . h($ogDesc) . '">' . "\n";
    echo '<meta property="og:url" content="' . h($canonical) . '">' . "\n";
    if ($image !== '') {
        echo '<meta property="og:image" content="' . h($image) . '">' . "\n";
    }
    if (!empty($o['published'])) {
        echo '<meta property="article:published_time" content="' . h((string) $o['published']) . '">' . "\n";
        echo '<meta property="article:author" content="' . h((string) cfg('site.author')) . '">' . "\n";
        echo '<meta property="article:section" content="' . h((string) ($o['section'] ?? '')) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
    if ($image !== '') {
        echo '<meta name="twitter:image" content="' . h($image) . '">' . "\n";
    }

    echo '<link rel="alternate" type="application/rss+xml" title="' . h((string) cfg('site.name')) . ' RSS" href="' . h(site_url('feed.xml')) . '">' . "\n";
    echo '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 64 64\'%3E%3Crect width=\'64\' height=\'64\' fill=\'%23191712\'/%3E%3Crect x=\'12\' y=\'12\' width=\'18\' height=\'18\' fill=\'%23a6432b\'/%3E%3Crect x=\'34\' y=\'34\' width=\'18\' height=\'18\' fill=\'%23f8f6f0\'/%3E%3C/svg%3E">' . "\n";
    echo '<link rel="stylesheet" href="assets/fonts.css">' . "\n";
    echo '<link rel="stylesheet" href="' . $root . 'assets/style.css">' . "\n";
    echo '<script src="' . $root . 'assets/main.js" defer></script>' . "\n";
    if ($jsonldStr !== '') {
        echo '<script type="application/ld+json">' . "\n" . $jsonldStr . "\n" . '</script>' . "\n";
    }
    echo "</head>\n<body>\n";
}

function site_header(int $depth = 0, string $active = '', bool $progress = false): void
{
    $root = $depth > 0 ? '../' : '';
    $items = settings_nav();
    echo '<a class="skip" href="#main">跳到正文</a>' . "\n";
    if ($progress) {
        echo '<div class="progress" data-progress aria-hidden="true"></div>' . "\n";
    }
    echo '<header class="site-header">' . "\n  <div class=\"shell header-inner\">\n";
    echo '    <a class="brand" href="' . $root . 'index.html" aria-label="' . h((string) cfg('site.author')) . '的技术笔记，返回首页">' . "\n";
    echo '      <span class="brand-name">' . h((string) cfg('site.author')) . '</span>' . "\n";
    echo '      <span class="brand-sep">/</span>' . "\n";
    echo '      <span class="brand-meta">技术笔记</span>' . "\n    </a>\n";
    echo '    <nav class="site-nav" aria-label="主导航">' . "\n";
    foreach ($items as $item) {
        $current = $item['key'] !== '' && $item['key'] === $active ? ' aria-current="page"' : '';
        echo '      <a href="' . h(settings_href($item['url'], $root)) . '"' . $current . '>' . h($item['label']) . '</a>' . "\n";
    }
    echo '    </nav>' . "\n";
    echo '    <button class="theme-toggle" type="button" data-theme-toggle aria-label="切换到深色模式">' . "\n";
    echo '      <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>' . "\n";
    echo '      <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8" stroke="currentColor" stroke-width="1.4"/></svg>' . "\n";
    echo '    </button>' . "\n  </div>\n</header>\n";
}

function site_footer(int $depth = 0): void
{
    $root = $depth > 0 ? '../' : '';
    $footer = settings_footer();
    echo '<footer class="site-footer">' . "\n  <div class=\"shell\">\n";
    echo '    <div class="wordmark">' . "\n";
    echo '      <span class="wm-name">' . h((string) cfg('site.author')) . '</span>' . "\n";
    echo '      <span class="wm-meta">Chen Yu · Technical Notes</span>' . "\n    </div>\n";
    echo '    <div class="footer-grid">' . "\n      <div>\n";
    echo '        <p class="footer-name">技术笔记</p>' . "\n";
    echo '        <p class="footer-note">' . h((string) $footer['intro']) . '</p>' . "\n      </div>\n      <div>\n";
    echo '        <p class="f-label">目录</p>' . "\n        <ul class=\"f-links\">\n";
    foreach ($footer['links'] as $link) {
        echo '          <li><a href="' . h(settings_href((string) $link['url'], $root)) . '">' . h((string) $link['label']) . '</a></li>' . "\n";
    }
    echo '        </ul>' . "\n      </div>\n      <div>\n";
    echo '        <p class="f-label">联系</p>' . "\n        ";
    echo settings_contact_links_html($root) . "\n";
    echo '      </div>' . "\n    </div>\n";
    echo settings_colophon_html() . "\n";
    echo '  </div>' . "\n</footer>\n";
}

function render_template(string $name, array $vars): string
{
    $file = CY_APP . '/templates/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('模板不存在: ' . $name);
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    return (string) ob_get_clean();
}

/* ---------------- 列表片段 ---------------- */

function post_entry_home(array $p): string
{
    $tag = $p['tags'][0] ?? '';
    return '<li>'
        . '<a class="entry" href="posts/' . h($p['slug']) . '.html">'
        . '<time class="entry-date" datetime="' . h((string) $p['published_at']) . '">' . h(fmt_date($p['published_at'])) . '</time>'
        . '<span><span class="entry-head">'
        . '<span class="entry-title">' . h($p['title']) . '</span>'
        . '<span class="entry-tag">' . h($tag) . '</span>'
        . '</span><span class="entry-desc">' . h($p['description']) . '</span></span>'
        . '</a></li>';
}

function post_entry_blog(array $p): string
{
    $tags = implode(' ', $p['tags']);
    $tag = $p['tags'][0] ?? '';
    return '<li data-tags="' . h($tags) . '">'
        . '<a class="entry" href="posts/' . h($p['slug']) . '.html">'
        . '<time class="entry-date" datetime="' . h((string) $p['published_at']) . '">' . h(fmt_date($p['published_at'], 'm.d')) . '</time>'
        . '<span><span class="entry-head">'
        . '<span class="entry-title">' . h($p['title']) . '</span>'
        . '<span class="entry-tag">' . (int) $p['reading_minutes'] . ' min · ' . h($tag) . '</span>'
        . '</span><span class="entry-desc">' . h($p['description']) . '</span></span>'
        . '</a></li>';
}

function home_recent_html(array $posts, int $limit = 4): string
{
    $items = array_slice($posts, 0, $limit);
    return '<ol class="entries">' . implode('', array_map('post_entry_home', $items)) . '</ol>';
}

function blog_filters_html(array $posts): string
{
    $tags = [];
    foreach ($posts as $p) {
        foreach ($p['tags'] as $tag) {
            if (!isset($tags[$tag])) {
                $tags[$tag] = 0;
            }
            $tags[$tag]++;
        }
    }
    $out = '<div class="filters" data-filters aria-label="按标签筛选">'
        . '<span class="lbl">筛选</span>'
        . '<button class="filter-btn" type="button" data-filter="all" aria-pressed="true">全部 ' . count($posts) . '</button>';
    $i = 0;
    foreach ($tags as $tag => $n) {
        if ($i++ >= 7) {
            break;
        }
        $out .= '<button class="filter-btn" type="button" data-filter="' . h($tag) . '" aria-pressed="false">' . h($tag) . '</button>';
    }
    $out .= '<span class="filter-count" data-filter-count>' . count($posts) . ' 篇</span></div>';
    return $out;
}

function blog_archive_html(array $posts): string
{
    if (!$posts) {
        return '<div data-year-group><p class="archive-empty">还没有文章。</p></div>';
    }
    $groups = [];
    foreach ($posts as $p) {
        $year = $p['published_at'] ? substr((string) $p['published_at'], 0, 4) : '未发布';
        $groups[$year][] = $p;
    }
    $out = '<div data-year-group>';
    foreach ($groups as $year => $items) {
        $out .= '<div class="archive-year"><h2>' . h((string) $year) . '</h2><span>' . count($items) . ' 篇 · 全年</span></div>';
        $out .= '<ol class="entries">' . implode('', array_map('post_entry_blog', $items)) . '</ol>';
    }
    return $out . '</div>';
}

function work_mark_svg(string $key): string
{
    switch ($key) {
        case 'winlite':
            return '<svg viewBox="0 0 64 44"><rect x="3" y="4" width="58" height="36" fill="none" stroke="var(--rule-strong)"/><line x1="3" y1="12" x2="61" y2="12" stroke="var(--rule-strong)"/><rect x="6" y="7" width="2" height="2" fill="var(--accent)"/><rect x="10" y="7" width="2" height="2" fill="var(--rule-strong)"/><rect x="14" y="7" width="2" height="2" fill="var(--rule-strong)"/><rect x="9" y="18" width="38" height="4" fill="var(--rule-strong)"/><rect x="9" y="25" width="26" height="4" fill="var(--ink)"/><rect x="9" y="32" width="13" height="4" fill="var(--accent)"/></svg>';
        case 'eq':
            return '<svg viewBox="0 0 64 44"><line x1="8" y1="36" x2="56" y2="36" stroke="var(--rule-strong)"/><rect x="12" y="22" width="5" height="13" fill="var(--ink)"/><rect x="21" y="14" width="5" height="21" fill="var(--ink)"/><rect x="30" y="25" width="5" height="10" fill="var(--ink)"/><rect x="39" y="9" width="5" height="26" fill="var(--accent)"/><rect x="48" y="19" width="5" height="16" fill="var(--ink)"/></svg>';
        case 'pixel':
            return '<svg viewBox="0 0 64 44"><rect x="14" y="6" width="36" height="24" fill="none" stroke="var(--rule-strong)"/><rect x="17" y="9" width="30" height="18" fill="var(--paper-3)"/><rect x="21" y="12" width="4" height="4" fill="var(--accent)"/><rect x="27" y="12" width="4" height="4" fill="var(--ink)"/><rect x="33" y="12" width="4" height="4" fill="var(--rule-strong)"/><rect x="21" y="18" width="4" height="4" fill="var(--ink)"/><rect x="27" y="18" width="4" height="4" fill="var(--accent)"/><rect x="33" y="18" width="4" height="4" fill="var(--ink)"/><rect x="30" y="30" width="4" height="4" fill="var(--ink)"/><rect x="24" y="34" width="16" height="3" fill="var(--ink)"/></svg>';
        default:
            return '<svg viewBox="0 0 64 44"><rect x="6" y="6" width="52" height="32" fill="none" stroke="var(--rule-strong)"/><rect x="10" y="16" width="18" height="3" fill="var(--ink)"/><rect x="10" y="23" width="28" height="3" fill="var(--rule-strong)"/><rect x="10" y="30" width="12" height="3" fill="var(--accent)"/></svg>';
    }
}

function work_article_html(array $w, string $heading = 'h2', bool $periodInEn = false, int $pointLimit = 0): string
{
    $statusClass = $w['status_kind'] ? ' status--' . h($w['status_kind']) : '';
    $status = $w['status_label'] !== ''
        ? '<span class="status' . $statusClass . '">' . h($w['status_label']) . '</span>'
        : '';
    $en = (string) $w['en'];
    if ($w['period'] !== '') {
        $en = trim(str_replace('· ' . $w['period'], '', $en));
    }
    if ($periodInEn && $w['period'] !== '') {
        $en .= ' · ' . $w['period'];
    }
    $en = trim($en, " \t·");
    $points = '';
    $list = $pointLimit > 0 ? array_slice($w['points'], 0, $pointLimit) : $w['points'];
    foreach ($list as $point) {
        $points .= '<li>' . h((string) $point) . '</li>';
    }
    return '<article class="work">'
        . '<div class="work-mark' . ($w['mark_key'] === 'eq' ? ' work-mark--eq' : '') . '" aria-hidden="true">'
        . (trim((string) ($w['cover_image'] ?? '')) !== ''
            ? '<img src="' . h(ltrim((string) $w['cover_image'], '/')) . '" alt="" loading="lazy">'
            : work_mark_svg((string) $w['mark_key']))
        . '</div>'
        . '<div><div class="work-title">'
        . '<' . $heading . '><a href="works/' . h($w['slug']) . '.html">' . h($w['title']) . '</a></' . $heading . '>'
        . '<span class="work-en">' . h($en) . '</span></div>'
        . '<p class="work-desc">' . h($w['description']) . '</p>'
        . '<ul class="work-points">' . $points . '</ul></div>'
        . '<div class="work-meta">' . $status
        . '<div>' . h($w['period']) . '</div>'
        . '<div>' . h($w['metric']) . '</div></div>'
        . '</article>';
}

function works_list_html(array $works, string $heading = 'h2', bool $periodInEn = false, int $pointLimit = 0): string
{
    $items = '';
    foreach ($works as $w) {
        $items .= '<li>' . work_article_html($w, $heading, $periodInEn, $pointLimit) . '</li>';
    }
    return $items;
}

function figures_html(array $stats): string
{
    return '<div class="figures">'
        . '<div><b>' . str_pad((string) $stats['posts'], 2, '0', STR_PAD_LEFT) . '</b>篇文章</div>'
        . '<div><b>' . str_pad((string) $stats['works'], 2, '0', STR_PAD_LEFT) . '</b>个作品</div>'
        . '<div><b>' . (int) cfg('site.start_year', 2021) . '</b>开始写</div>'
        . '<div><b>' . h($stats['latest_date'] ? fmt_date($stats['latest_date']) : '—') . '</b>最近更新</div>'
        . '</div>';
}

/* ---------------- JSON-LD ---------------- */

function graph_jsonld(array $nodes): array
{
    return ['@context' => 'https://schema.org', '@graph' => $nodes];
}

function post_jsonld(array $p): array
{
    $url = site_url($p['url']);
    return graph_jsonld([
        [
            '@type' => 'BlogPosting',
            '@id' => $url . '#post',
            'headline' => $p['title'],
            'description' => $p['description'],
            'url' => $url,
            'mainEntityOfPage' => $url,
            'datePublished' => $p['published_at'],
            'dateModified' => $p['updated_on'] ?: $p['published_at'],
            'inLanguage' => 'zh-CN',
            'wordCount' => (int) $p['word_count'],
            'timeRequired' => 'PT' . (int) $p['reading_minutes'] . 'M',
            'author' => ['@id' => site_url('#person')],
            'publisher' => ['@id' => site_url('#person')],
            'keywords' => $p['keywords'],
            'articleSection' => $p['section'],
            'isPartOf' => ['@id' => site_url('#website')],
        ],
        breadcrumb_jsonld([
            ['首页', site_url('')],
            ['博客', site_url('blog.html')],
            [$p['title'], $url],
        ]),
    ]);
}

function work_jsonld(array $w): array
{
    $url = site_url($w['url']);
    $app = [
        '@type' => $w['app_type'] ?: 'SoftwareApplication',
        '@id' => $url . '#app',
        'name' => $w['title'],
        'url' => $url,
        'description' => $w['description'],
        'author' => ['@id' => site_url('#person')],
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'CNY'],
    ];
    if ($w['application_category']) {
        $app['applicationCategory'] = $w['application_category'];
    }
    if ($w['operating_system']) {
        $app['operatingSystem'] = $w['operating_system'];
    }
    if ($w['version']) {
        $app['softwareVersion'] = $w['version'];
    }
    if (trim((string) ($w['cover_image'] ?? '')) !== '') {
        $app['image'] = site_url((string) $w['cover_image']);
    }
    return graph_jsonld([
        $app,
        breadcrumb_jsonld([
            ['首页', site_url('')],
            ['作品', site_url('works.html')],
            [$w['title'], $url],
        ]),
    ]);
}

function blog_jsonld(array $posts): array
{
    $url = site_url('blog.html');
    $items = [];
    foreach ($posts as $p) {
        $items[] = [
            '@type' => 'BlogPosting',
            'headline' => $p['title'],
            'url' => site_url($p['url']),
            'datePublished' => $p['published_at'],
            'author' => ['@id' => site_url('#person')],
        ];
    }
    return graph_jsonld([
        [
            '@type' => 'Blog',
            '@id' => $url . '#blog',
            'url' => $url,
            'name' => cfg('site.author') . '的技术博客',
            'description' => 'PixiJS 性能优化、WPF 内存排查、Godot 迁移与桌面软件设计。',
            'inLanguage' => 'zh-CN',
            'author' => ['@id' => site_url('#person')],
            'blogPost' => $items,
        ],
        breadcrumb_jsonld([
            ['首页', site_url('')],
            ['博客', $url],
        ]),
    ]);
}

function works_jsonld(array $works): array
{
    $url = site_url('works.html');
    $items = [];
    foreach ($works as $w) {
        $items[] = [
            '@type' => $w['app_type'] ?: 'SoftwareApplication',
            'name' => $w['title'],
            'url' => site_url($w['url']),
            'applicationCategory' => $w['application_category'] ?: 'UtilitiesApplication',
            'operatingSystem' => $w['operating_system'] ?: 'Web',
            'author' => ['@id' => site_url('#person')],
        ];
    }
    return graph_jsonld([
        [
            '@type' => 'CollectionPage',
            '@id' => $url . '#page',
            'url' => $url,
            'name' => '作品',
            'inLanguage' => 'zh-CN',
            'about' => ['@id' => site_url('#person')],
            'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $items],
        ],
        breadcrumb_jsonld([
            ['首页', site_url('')],
            ['作品', $url],
        ]),
    ]);
}

function breadcrumb_jsonld(array $items): array
{
    $list = [];
    foreach ($items as $i => [$name, $url]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}
