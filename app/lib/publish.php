<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/render.php';

/**
 * dist 目录绝对路径，禁止指向项目根。
 */
function dist_path(string $path = ''): string
{
    $base = (string) cfg('site.dist_dir', 'dist');
    if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $base)) {
        $base = CY_ROOT . '/' . $base;
    }
    $base = rtrim(str_replace('\\', '/', $base), '/');
    if ($base === '' || $base === str_replace('\\', '/', CY_ROOT)) {
        throw new RuntimeException('site.dist_dir 不能是项目根目录');
    }
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('无法创建目录: ' . $dir);
    }
}

function write_out(string $rel, string $contents): void
{
    $target = dist_path($rel);
    ensure_dir(dirname($target));
    if (file_put_contents($target, $contents) === false) {
        throw new RuntimeException('写入失败: ' . $rel);
    }
}

function copy_tree(string $from, string $to): int
{
    if (!is_dir($from)) {
        return 0;
    }
    ensure_dir($to);
    $count = 0;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)) + 1);
        if ($item->isDir()) {
            ensure_dir($to . '/' . $rel);
            continue;
        }
        if (str_starts_with($item->getFilename(), '.')) {
            continue;
        }
        ensure_dir(dirname($to . '/' . $rel));
        copy($item->getPathname(), $to . '/' . $rel);
        $count++;
    }
    return $count;
}

function remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/**
 * 生成整站静态文件，返回统计信息。
 */
function publish_site(): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }
    $dist = dist_path();
    $write = [];
    $copy = [];

    $posts = posts('published');
    $works = works('published');
    $stats = site_stats();

    // 先清掉会随 slug 变动的目录，避免遗留旧页
    remove_tree($dist . '/posts');
    remove_tree($dist . '/works');
    foreach (['index.html', 'blog.html', 'works.html', 'sitemap.xml', 'feed.xml', '404.html'] as $stale) {
        if (is_file($dist . '/' . $stale)) {
            @unlink($dist . '/' . $stale);
        }
    }

    $write['index.html']       = render_template('index', ['posts' => $posts, 'works' => $works, 'stats' => $stats]);
    $write['blog.html']        = render_template('blog', ['posts' => $posts]);
    $write['works.html']       = render_template('works', ['works' => $works]);

    foreach (['404.html', 'about.html', 'column.html'] as $staticPage) {
        $from = CY_APP . '/templates/static/' . $staticPage;
        if (is_file($from)) {
            $html = (string) file_get_contents($from);
            if ($staticPage !== '404.html') {
                $fallback = preg_match('#<title>(.*?)</title>#su', $html, $m) ? trim($m[1]) : cfg('site.name');
                $html = apply_static_seo($html, basename($staticPage, '.html'), $staticPage, $fallback);
            }
            $html = apply_static_contact($html, $staticPage);
            ensure_dir($dist);
            if (file_put_contents(dist_path($staticPage), $html) === false) {
                throw new RuntimeException('写入失败: ' . $staticPage);
            }
        }
    }

    foreach ($posts as $p) {
        [$newer, $older] = post_adjacent($p);
        $projectUrl = '';
        if ($p['project'] !== '') {
            foreach ($works as $w) {
                if ($w['title'] === $p['project']) {
                    $projectUrl = '../works/' . $w['slug'] . '.html';
                    break;
                }
            }
        }
        $write['posts/' . $p['slug'] . '.html'] = render_template('post', [
            'post' => $p,
            'newer' => $newer,
            'older' => $older,
            'projectUrl' => $projectUrl,
        ]);
    }

    foreach ($works as $w) {
        [$prevWork, $nextWork] = work_adjacent($w);
        $write['works/' . $w['slug'] . '.html'] = render_template('work', [
            'work' => $w,
            'prevWork' => $prevWork,
            'nextWork' => $nextWork,
        ]);
    }

    $write['sitemap.xml'] = render_sitemap($posts, $works, $stats);
    $write['feed.xml']    = render_feed($posts);
    $write['robots.txt']  = render_robots();
    $write['.nojekyll']   = '';

    foreach ($write as $rel => $html) {
        write_out($rel, $html);
    }

    $copy['assets'] = copy_tree(CY_APP . '/assets', $dist . '/assets');

    return [
        'dist'   => $dist,
        'pages'  => count($posts) + count($works) + 6,
        'posts'  => count($posts),
        'works'  => count($works),
        'assets' => $copy['assets'],
        'files'  => count($write),
        'time'   => date('Y-m-d H:i:s'),
    ];
}

function render_sitemap(array $posts, array $works, array $stats): string
{
    $urls = [
        [site_url(''), $stats['latest_date'] ?: today(), '1.0', 'daily'],
        [site_url('about.html'), '', '0.6', 'monthly'],
        [site_url('blog.html'), $stats['latest_date'] ?: '', '0.8', 'daily'],
        [site_url('column.html'), '', '0.6', 'monthly'],
        [site_url('works.html'), '', '0.8', 'monthly'],
    ];
    foreach ($posts as $p) {
        $urls[] = [site_url($p['url']), $p['updated_on'] ?: $p['published_at'], '0.9', 'monthly'];
    }
    foreach ($works as $w) {
        $urls[] = [site_url($w['url']), '', '0.8', 'monthly'];
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $lastmod, $priority, $freq]) {
        $xml .= '  <url><loc>' . h($loc) . '</loc>';
        if ($lastmod) {
            $ts = strtotime((string) $lastmod);
            $xml .= '<lastmod>' . date('Y-m-d', $ts ?: time()) . '</lastmod>';
        }
        $xml .= '<changefreq>' . $freq . '</changefreq><priority>' . $priority . '</priority></url>' . "\n";
    }
    return $xml . '</urlset>' . "\n";
}

function render_feed(array $posts): string
{
    $site = cfg('site');
    $latest = $posts[0]['published_at'] ?? today();
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n<channel>\n";
    $xml .= '<title>' . h($site['name']) . "</title>\n";
    $xml .= '<link>' . h(site_url('')) . "</link>\n";
    $xml .= '<description>桌面软件、像素游戏与工程实践的个人技术博客</description>' . "\n";
    $xml .= '<language>zh-cn</language>' . "\n";
    $xml .= '<lastBuildDate>' . date(DATE_RSS, strtotime((string) $latest) ?: time()) . "</lastBuildDate>\n";
    $xml .= '<atom:link href="' . h(site_url('feed.xml')) . '" rel="self" type="application/rss+xml"/>' . "\n";
    foreach ($posts as $p) {
        $url = site_url($p['url']);
        $xml .= "<item>\n";
        $xml .= '<title>' . h($p['title']) . "</title>\n";
        $xml .= '<link>' . h($url) . "</link>\n";
        $xml .= '<guid isPermaLink="true">' . h($url) . "</guid>\n";
        $xml .= '<pubDate>' . date(DATE_RSS, strtotime((string) $p['published_at']) ?: time()) . "</pubDate>\n";
        foreach ($p['tags'] as $tag) {
            $xml .= '<category>' . h($tag) . "</category>\n";
        }
        $xml .= '<description>' . h($p['description']) . "</description>\n";
        $xml .= "<content:encoded><![CDATA[" . str_replace(']]>', ']]&gt;', $p['body_html']) . ']]></content:encoded>' . "\n";
        $xml .= "</item>\n";
    }
    return $xml . "</channel>\n</rss>\n";
}

function render_robots(): string
{
    return "User-agent: *\nAllow: /\n\nSitemap: " . site_url('sitemap.xml') . "\n";
}
