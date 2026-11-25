<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * 站点设置：导航、页脚、Logbook、SEO，统一存在 settings 表的一行 JSON 里。
 * 读取失败（表还没建、数据库没连）时退回内置默认值，前台不会因此打不开。
 */

function settings_defaults(): array
{
    return [
        'nav' => [
            ['key' => 'home', 'label' => '首页', 'url' => 'index.html'],
            ['key' => 'about', 'label' => '关于', 'url' => 'about.html'],
            ['key' => 'blog', 'label' => '博客', 'url' => 'blog.html'],
            ['key' => 'column', 'label' => '专栏', 'url' => 'column.html'],
            ['key' => 'works', 'label' => '作品', 'url' => 'works.html'],
        ],
        'footer' => [
            'intro' => '桌面软件、像素游戏，以及把东西做完的过程。更新不固定，写清楚了才发。',
            'links' => [
                ['label' => '关于', 'url' => 'about.html'],
                ['label' => '博客', 'url' => 'blog.html'],
                ['label' => '专栏', 'url' => 'column.html'],
                ['label' => '作品', 'url' => 'works.html'],
            ],
            'contact' => [
                ['label' => 'GitHub', 'url' => (string) cfg('site.github')],
                ['label' => (string) cfg('site.email'), 'url' => 'mailto:' . (string) cfg('site.email')],
                ['label' => 'RSS 订阅', 'url' => 'feed.xml'],
            ],
            'copyright' => '',
            'icp' => '',
            'note' => '手写 HTML · 无统计脚本',
            'host' => '本站由静态托管服务提供',
        ],
        'logbook' => [
            ['date' => '2026-11-22', 'text' => '收银台的找零逻辑重写完，删掉一个 if'],
            ['date' => '2026-11-21', 'text' => '楼下修电脑的师傅问我在写什么，我说在写修电脑的游戏'],
            ['date' => '2026-11-18', 'text' => '帧预算压到 13.3 毫秒，旧笔记本能稳住 60 帧了'],
            ['date' => '2026-11-15', 'text' => '猫把显示器线踩掉了，重接之后驱动又闹了一次'],
            ['date' => '2026-11-12', 'text' => '在读 Game Engine Architecture，第三章做了七页笔记'],
            ['date' => '2026-11-08', 'text' => 'TinyPlayer 的托盘菜单第一次点没反应，原来是线程的问题'],
            ['date' => '2026-11-02', 'text' => '把 WinLite 的回滚脚本改成可中断的，断了也能恢复'],
            ['date' => '2026-10-30', 'text' => '有人写信说靠 WinLite 救活了一台 2012 年的机器'],
        ],
        'seo' => [
            'keywords' => '',
            'og_image' => '',
            'post_title_tpl' => '{title} | {site}',
            'work_title_tpl' => '{title} | 作品 | {site}',
            'home_title' => '',
            'home_desc' => '',
            'blog_title' => '',
            'blog_desc' => '',
            'works_title' => '',
            'works_desc' => '',
            'about_title' => '',
            'about_desc' => '',
            'column_title' => '',
            'column_desc' => '',
        ],
        'contact' => [
            'email'    => (string) cfg('site.email'),
            'wechat'   => '',
            'qq'       => '',
            'github'   => (string) cfg('site.github'),
            'weibo'    => '',
            'bilibili' => '',
            'extra'    => [],
        ],
    ];
}

function settings_ensure_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS settings (
        k          VARCHAR(40) NOT NULL,
        v          MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (k)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function settings_all(): array
{
    if (isset($GLOBALS['cy_settings_cache']) && is_array($GLOBALS['cy_settings_cache'])) {
        return $GLOBALS['cy_settings_cache'];
    }
    $cache = settings_defaults();
    try {
        settings_ensure_table();
        $stmt = db()->prepare('SELECT v FROM settings WHERE k = ?');
        $stmt->execute(['site']);
        $json = (string) ($stmt->fetchColumn() ?: '');
        if ($json !== '') {
            $saved = json_decode($json, true);
            if (is_array($saved)) {
                $cache = settings_merge($cache, $saved);
            }
        }
    } catch (Throwable $e) {
        // 没有数据库时用默认值，前台仍可渲染
    }
    $GLOBALS['cy_settings_cache'] = $cache;
    return $cache;
}

function settings_merge(array $defaults, array $saved): array
{
    foreach ($saved as $key => $value) {
        if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
            // 列表类字段整体替换；关联字段逐项合并
            if (array_is_list($value) || array_is_list($defaults[$key])) {
                $defaults[$key] = $value;
            } else {
                $defaults[$key] = settings_merge($defaults[$key], $value);
            }
            continue;
        }
        $defaults[$key] = $value;
    }
    return $defaults;
}

function settings_store(array $data): void
{
    settings_ensure_table();
    $stmt = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $stmt->execute(['site', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $GLOBALS['cy_settings_cache'] = settings_merge(settings_defaults(), $data);
}

/* ---------------- 展示用读取 ---------------- */

function settings_nav(): array
{
    $items = [];
    foreach (settings_all()['nav'] as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }
        $items[] = [
            'key' => trim((string) ($item['key'] ?? '')),
            'label' => $label,
            'url' => $url,
        ];
    }
    return $items;
}

function settings_footer(): array
{
    return settings_all()['footer'];
}

/**
 * 页脚底栏：版权 + 可选备案号/说明。生成页和静态页共用，避免两边不一致。
 */
function settings_colophon_html(): string
{
    $footer = settings_footer();
    $copyright = trim((string) ($footer['copyright'] ?? ''));
    if ($copyright === '') {
        $copyright = '© ' . (int) cfg('site.start_year', 2021) . '–' . date('Y') . ' ' . (string) cfg('site.author');
    } else {
        $copyright = seo_fill($copyright);
    }

    $html = '    <div class="colophon">' . "\n";
    $html .= '      <span>' . h($copyright) . '</span>' . "\n";
    if (trim((string) ($footer['icp'] ?? '')) !== '') {
        $html .= '      <span><a href="https://beian.miit.gov.cn" rel="noopener nofollow" target="_blank">' . h((string) $footer['icp']) . '</a></span>' . "\n";
    }
    if (trim((string) ($footer['note'] ?? '')) !== '') {
        $html .= '      <span>' . h((string) $footer['note']) . '</span>' . "\n";
    }
    if (trim((string) ($footer['host'] ?? '')) !== '') {
        $html .= '      <span>' . h((string) $footer['host']) . '</span>' . "\n";
    }
    return $html . '    </div>';
}

/**
 * 联系方式统一成列表，页脚、关于页都从这里取，改一处全站同步。
 * 返回 [['label','value','url'], ...]
 */
function settings_contact(): array
{
    $contact = settings_all()['contact'] ?? [];
    $items = [];
    $email = trim((string) ($contact['email'] ?? ''));
    if ($email !== '') {
        $items[] = ['label' => '邮箱', 'value' => $email, 'url' => 'mailto:' . $email];
    }
    foreach ([
        'github'   => 'GitHub',
        'weibo'    => '微博',
        'bilibili' => 'B站',
    ] as $key => $label) {
        $url = trim((string) ($contact[$key] ?? ''));
        if ($url !== '') {
            $items[] = ['label' => $label, 'value' => $url, 'url' => $url];
        }
    }
    foreach (['wechat' => '微信', 'qq' => 'QQ'] as $key => $label) {
        $value = trim((string) ($contact[$key] ?? ''));
        if ($value !== '') {
            $items[] = ['label' => $label, 'value' => $value, 'url' => ''];
        }
    }
    foreach (($contact['extra'] ?? []) as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        if ($label !== '' && $url !== '') {
            $items[] = ['label' => $label, 'value' => $url, 'url' => $url];
        }
    }
    $items[] = ['label' => 'RSS 订阅', 'value' => 'feed.xml', 'url' => 'feed.xml'];
    return $items;
}

function settings_contact_links_html(string $root = ''): string
{
    $html = '<ul class="f-links">' . "\n";
    foreach (settings_contact() as $item) {
        $label = h($item['label']);
        if ($item['url'] !== '') {
            $rel = preg_match('#^https?:#i', $item['url']) ? ' rel="me noopener"' : '';
            $html .= '          <li><a href="' . h(settings_href($item['url'], $root)) . '"' . $rel . '>' . $label . '</a></li>' . "\n";
        } else {
            $html .= '          <li><span>' . $label . '：' . h($item['value']) . '</span></li>' . "\n";
        }
    }
    return $html . '        </ul>';
}

/**
 * 关于页的联系段落，发布静态页时按设置重写。
 */
function settings_about_contact_html(): string
{
    $contact = settings_contact();
    $email = '';
    $github = '';
    foreach ($contact as $item) {
        if ($item['label'] === '邮箱') {
            $email = $item['value'];
        }
        if ($item['label'] === 'GitHub') {
            $github = $item['url'];
        }
    }
    $parts = [];
    if ($email !== '') {
        $parts[] = '合作或技术交流请写信：<a href="mailto:' . h($email) . '">' . h($email) . '</a>。附上时间线、预算范围和大致的交付形态，能省掉来回两轮。';
    }
    $tail = '';
    if ($github !== '') {
        $tail .= '代码在 <a href="' . h($github) . '" rel="me noopener">GitHub</a>，';
    }
    $tail .= '文章更新可以订阅 <a href="feed.xml">RSS</a>。';
    $others = [];
    foreach ($contact as $item) {
        if (in_array($item['label'], ['微信', 'QQ', '微博', 'B站'], true)) {
            $others[] = $item['label'] . '：' . ($item['url'] !== '' ? '<a href="' . h($item['url']) . '" rel="me noopener">' . h($item['url']) . '</a>' : h($item['value']));
        }
    }
    if ($others) {
        $tail .= '<br>也可以在这些地方找到我：' . implode('，', $others) . '。';
    }
    if ($tail !== '') {
        $parts[] = $tail;
    }
    return $parts ? '<p>' . implode("</p>\n          <p>", $parts) . '</p>' : '';
}

function settings_logbook(): array
{
    $items = [];
    foreach (settings_all()['logbook'] as $item) {
        $date = trim((string) ($item['date'] ?? ''));
        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $items[] = ['date' => $date, 'text' => $text];
    }
    return $items;
}

function settings_seo(): array
{
    return settings_all()['seo'];
}

function settings_href(string $url, string $root = ''): string
{
    if (preg_match('#^(?:https?:|mailto:|tel:)#i', $url)) {
        return $url;
    }
    return $root . ltrim($url, '/');
}

function seo_fill(string $tpl, array $vars = []): string
{
    return strtr($tpl, [
        '{title}' => (string) ($vars['title'] ?? ''),
        '{site}' => (string) cfg('site.name'),
        '{author}' => (string) cfg('site.author'),
        '{tagline}' => (string) cfg('site.tagline', '桌面软件、像素游戏与工程实践'),
    ]);
}

/**
 * 单页 SEO 覆盖（首页/博客/作品/关于/专栏），空值表示沿用模板默认。
 */
function seo_for_page(string $key): array
{
    $seo = settings_seo();
    $title = trim((string) ($seo[$key . '_title'] ?? ''));
    $desc = trim((string) ($seo[$key . '_desc'] ?? ''));
    return [
        'title' => $title !== '' ? seo_fill($title) : '',
        'desc' => $desc !== '' ? seo_fill($desc) : '',
    ];
}

/**
 * 详情页（文章/作品）标题模板，当条目没有单独填 SEO 标题时使用。
 */
function seo_item_title(string $kind, string $title): string
{
    $seo = settings_seo();
    $tpl = $kind === 'work'
        ? (string) ($seo['work_title_tpl'] ?? '{title} | 作品 | {site}')
        : (string) ($seo['post_title_tpl'] ?? '{title} | {site}');
    if (trim($tpl) === '') {
        $tpl = '{title} | {site}';
    }
    return seo_fill($tpl, ['title' => $title]);
}

function seo_og_image(): string
{
    $image = trim((string) (settings_seo()['og_image'] ?? ''));
    if ($image === '') {
        return '';
    }
    return preg_match('#^https?:#i', $image) ? $image : site_url($image);
}

/**
 * 静态页（about/column）发布时改写 title、description、canonical 与 OG。
 */
function apply_static_seo(string $html, string $key, string $path, string $fallbackTitle): string
{
    $seo = seo_for_page($key);
    $title = $seo['title'] !== '' ? $seo['title'] : $fallbackTitle;
    $html = (string) preg_replace('#<title>.*?</title>#su', '<title>' . h($title) . '</title>', $html, 1);
    if ($seo['desc'] !== '') {
        $html = (string) preg_replace(
            '#<meta name="description" content=".*?">#su',
            '<meta name="description" content="' . h($seo['desc']) . '">',
            $html,
            1
        );
        $html = (string) preg_replace(
            '#<meta property="og:description" content=".*?">#su',
            '<meta property="og:description" content="' . h($seo['desc']) . '">',
            $html,
            1
        );
    }
    $html = (string) preg_replace(
        '#<link rel="canonical" href=".*?">#su',
        '<link rel="canonical" href="' . h(site_url($path)) . '">',
        $html,
        1
    );
    $html = (string) preg_replace(
        '#<meta property="og:title" content=".*?">#su',
        '<meta property="og:title" content="' . h($title) . '">',
        $html,
        1
    );
    $html = (string) preg_replace(
        '#<meta property="og:url" content=".*?">#su',
        '<meta property="og:url" content="' . h(site_url($path)) . '">',
        $html,
        1
    );
    return $html;
}

/**
 * 静态页（about/column/404）发布时同步联系方式：页脚联系列表 + 关于页联系段落。
 */
function apply_static_contact(string $html, string $path): string
{
    $links = settings_contact_links_html('');
    $html = (string) preg_replace_callback(
        '#(<p class="f-label">联系</p>\s*)<ul class="f-links">.*?</ul>#su',
        static fn(array $m): string => $m[1] . $links,
        $html,
        1
    );

    $colophon = settings_colophon_html();
    $html = (string) preg_replace('#<div class="colophon">.*?</div>#su', $colophon, $html, 1);

    if ($path === 'about.html') {
        $body = settings_about_contact_html();
        if ($body !== '') {
            $html = (string) preg_replace_callback(
                '#(<section class="section shell" aria-labelledby="contact-title">.*?<div class="about-body">).*?(</div>\s*</div>\s*</section>)#su',
                static fn(array $m): string => $m[1] . "\n          " . $body . "\n        " . $m[2],
                $html,
                1
            );
        }
    }
    return $html;
}

/* ---------------- 后台表单 ---------------- */

function settings_parse_rows(string $text, int $columns): array
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, $columns));
        if (count($parts) < $columns) {
            $parts = array_pad($parts, $columns, '');
        }
        $rows[] = $parts;
    }
    return $rows;
}

/**
 * 后台「站点设置」提交，返回 [ok, message]。
 */
function settings_save_form(array $in): array
{
    $current = settings_all();

    $nav = [];
    foreach (settings_parse_rows((string) ($in['nav_rows'] ?? ''), 3) as [$key, $label, $url]) {
        if ($label === '' || $url === '') {
            continue;
        }
        $nav[] = ['key' => $key === '-' ? '' : $key, 'label' => $label, 'url' => $url];
    }
    if (!$nav) {
        return [false, '导航至少保留一条，格式：key | 标签 | 链接'];
    }

    $footer = $current['footer'];
    $footer['intro'] = trim((string) ($in['footer_intro'] ?? ''));
    $footer['links'] = [];
    foreach (settings_parse_rows((string) ($in['footer_links'] ?? ''), 2) as [$label, $url]) {
        if ($label !== '' && $url !== '') {
            $footer['links'][] = ['label' => $label, 'url' => $url];
        }
    }
    $footer['contact'] = [];
    foreach (settings_parse_rows((string) ($in['footer_contact'] ?? ''), 2) as [$label, $url]) {
        if ($label !== '' && $url !== '') {
            $footer['contact'][] = ['label' => $label, 'url' => $url];
        }
    }
    $footer['copyright'] = trim((string) ($in['footer_copyright'] ?? ''));
    $footer['icp'] = trim((string) ($in['footer_icp'] ?? ''));
    $footer['note'] = trim((string) ($in['footer_note'] ?? ''));
    $footer['host'] = trim((string) ($in['footer_host'] ?? ''));

    $contact = $current['contact'] ?? [];
    foreach (['email', 'wechat', 'qq', 'github', 'weibo', 'bilibili'] as $field) {
        $contact[$field] = trim((string) ($in['contact_' . $field] ?? ''));
    }
    $contact['extra'] = [];
    foreach (settings_parse_rows((string) ($in['contact_extra'] ?? ''), 2) as [$label, $url]) {
        if ($label !== '' && $url !== '') {
            $contact['extra'][] = ['label' => $label, 'url' => $url];
        }
    }

    $logbook = [];
    foreach (settings_parse_rows((string) ($in['logbook_rows'] ?? ''), 2) as [$date, $text]) {
        if ($text === '') {
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !valid_date($date)) {
            return [false, 'Logbook 日期格式应为 YYYY-MM-DD：' . $date];
        }
        $logbook[] = ['date' => $date, 'text' => $text];
    }

    $seo = $current['seo'];
    foreach ([
        'keywords', 'og_image', 'post_title_tpl', 'work_title_tpl',
        'home_title', 'home_desc', 'blog_title', 'blog_desc',
        'works_title', 'works_desc', 'about_title', 'about_desc',
        'column_title', 'column_desc',
    ] as $field) {
        $seo[$field] = trim((string) ($in['seo_' . $field] ?? ''));
    }

    $data = ['nav' => $nav, 'footer' => $footer, 'logbook' => $logbook, 'seo' => $seo, 'contact' => $contact];
    try {
        settings_store($data);
    } catch (Throwable $e) {
        return [false, '保存失败：' . $e->getMessage()];
    }
    return [true, '站点设置已保存。记得重新生成一次静态站，改动才会出现在前台。'];
}
