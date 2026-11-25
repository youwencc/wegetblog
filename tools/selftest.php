<?php
declare(strict_types=1);

/**
 * 本地自检：建表、导入种子、生成静态站、跑一遍 CRUD。
 * 用法：php tools/selftest.php
 * 会写入数据库，只应在测试库运行。
 */

require_once __DIR__ . '/../app/lib/install.php';

$ok = 0;
$fail = 0;
function check(string $label, bool $cond, string $detail = ''): void
{
    global $ok, $fail;
    if ($cond) {
        $ok++;
        echo "  ✓ " . $label . "\n";
    } else {
        $fail++;
        echo "  ✗ " . $label . ($detail !== '' ? " — " . $detail : '') . "\n";
    }
}

echo "== 建表 / 导入 ==\n";
install_schema();
db()->exec('DELETE FROM posts');
db()->exec('DELETE FROM works');
check('posts 表按预期清空', table_count('posts') === 0);
$imported = import_seed(false);
check('导入 4 篇文章', $imported['posts'] === 4, (string) $imported['posts']);
check('导入 3 个作品', $imported['works'] === 3, (string) $imported['works']);

$posts = posts('published');
check('文章按日期倒序', $posts[0]['slug'] === 'pixijs-frame-budget', $posts[0]['slug'] ?? '');
check('标签被拆成数组', is_array($posts[0]['tags']) && $posts[0]['tags'][0] === 'PixiJS');
check('更新日期保留', ($posts[0]['updated_on'] ?? '') !== '' && $posts[0]['updated_on'] !== $posts[0]['published_at'], (string) ($posts[0]['updated_on'] ?? ''));

$works = works('published');
check('作品顺序：WinLite 在前', $works[0]['slug'] === 'winlite', $works[0]['slug'] ?? '');
check('作品 specs 是数组', is_array($works[0]['specs']) && count($works[0]['specs']) > 0);
check('作品 status_kind', $works[2]['status_kind'] === 'wip', $works[2]['status_kind'] ?? '');

echo "== 文章 CRUD ==\n";
[$okSave, $msg] = post_save([
    'title' => '测试文章：一帧的预算',
    'slug' => 'selftest-frame',
    'body_html' => '<p>这是一段测试正文，用来验证新建、编辑和删除。</p><h2 id="a">小节</h2><p>更多文字。</p>',
    'tags' => '测试, 性能',
    'published_at' => '2026-11-23',
    'status' => 'published',
]);
check('新建文章成功', $okSave, $msg);
$new = post_by_slug('selftest-frame');
check('新文章可查到', $new !== null);
check('字数自动计算', $new && $new['word_count'] > 0, (string) ($new['word_count'] ?? ''));
check('阅读分钟 >= 1', $new && $new['reading_minutes'] >= 1);
[$okUpd, $msgUpd] = post_save(['title' => '测试文章（已改）', 'slug' => 'selftest-frame',
    'body_html' => '<p>改过的正文。</p>', 'published_at' => '2026-11-23', 'status' => 'published'], (int) $new['id']);
check('编辑文章成功', $okUpd, $msgUpd);
check('标题已更新', post_by_slug('selftest-frame')['title'] === '测试文章（已改）');

[$okDup, $msgDup] = post_save(['title' => '重复 slug', 'slug' => 'pixijs-frame-budget', 'body_html' => '<p>x</p>', 'status' => 'published']);
check('重复 slug 被拒绝', !$okDup, $msgDup);

[$badSlug, $badMsg] = post_save(['title' => '坏 slug', 'slug' => 'Bad Slug', 'body_html' => '<p>x</p>']);
check('非法 slug 被拒绝', !$badSlug, $badMsg);

[$newer, $older] = post_adjacent($new);
check('相邻文章查询', $older !== null && $newer === null);

check('删除文章', post_delete((int) $new['id']) && post_by_slug('selftest-frame') === null);

echo "== 作品 CRUD ==\n";
[$okWork, $msgWork] = work_save([
    'title' => '测试作品',
    'slug' => 'selftest-work',
    'body_html' => '<p>测试作品正文。</p>',
    'points' => "TypeScript\nPixiJS",
    'meta_chips' => "v0.1\nWeb",
    'specs' => "平台 | Web\n状态 | 测试",
    'status_label' => '测试中',
    'status_kind' => 'wip',
    'mark_key' => 'pixel',
    'sort_order' => 9,
    'is_published' => '1',
]);
check('新建作品成功', $okWork, $msgWork);
$w2 = null;
foreach (works('all') as $item) {
    if ($item['slug'] === 'selftest-work') {
        $w2 = $item;
    }
}
check('新作品可查到', $w2 !== null);
check('points 解析', $w2 && $w2['points'] === ['TypeScript', 'PixiJS']);
check('specs 解析', $w2 && ($w2['specs'][0]['label'] ?? '') === '平台');
check('mark_key 白名单', $w2 && $w2['mark_key'] === 'pixel');
check('删除作品', work_delete((int) $w2['id']));

echo "== 统计 / 生成 ==\n";
$stats = site_stats();
check('已发布 4 篇', $stats['posts'] === 4, (string) $stats['posts']);
check('作品 3 个', $stats['works'] === 3, (string) $stats['works']);

$published = publish_site();
check('生成首页', is_file(dist_path('index.html')));
check('手写 wiki 未被清理', is_file(dist_path('wiki/git.html')) && is_file(dist_path('wiki/ai-agent-wiki.html')));
check('生成文章页', is_file(dist_path('posts/pixijs-frame-budget.html')));
check('生成作品页', is_file(dist_path('works/tinyplayer.html')));
check('生成 sitemap', is_file(dist_path('sitemap.xml')));
check('生成 feed', is_file(dist_path('feed.xml')));
check('生成 404', is_file(dist_path('404.html')));
check('复制 assets', is_file(dist_path('assets/style.css')));

$index = (string) file_get_contents(dist_path('index.html'));
check('首页含最新文章标题', str_contains($index, '把 60 帧塞进一台 2013 年的笔记本'));
check('首页含工作室插画', str_contains($index, 'class="studio"'));
check('首页含 JSON-LD', str_contains($index, 'application/ld+json'));
check('首页作品顺序', strpos($index, 'works/winlite.html') < strpos($index, 'works/pixel-workshop.html'));

$postHtml = (string) file_get_contents(dist_path('posts/pixijs-frame-budget.html'));
check('文章页 canonical', str_contains($postHtml, 'https://chenyu.me/posts/pixijs-frame-budget.html'));
check('文章页上一篇', str_contains($postHtml, '← 返回列表'));
check('文章页下一篇', str_contains($postHtml, 'wpf-memory.html'));

$workHtml = (string) file_get_contents(dist_path('works/tinyplayer.html'));
check('作品页参数表', str_contains($workHtml, 'class="spec"'));
check('作品页相关区块', str_contains($workHtml, 'wpf-state-machine.html'));

$sitemap = (string) file_get_contents(dist_path('sitemap.xml'));
$feed = (string) file_get_contents(dist_path('feed.xml'));
check('sitemap 含 12 个 URL', substr_count($sitemap, '<loc>') === 12, (string) substr_count($sitemap, '<loc>'));
check('feed 是合法 XML', simplexml_load_string($feed) !== false);
check('sitemap 是合法 XML', simplexml_load_string($sitemap) !== false);

echo "\n结果：{$ok} 通过，{$fail} 失败\n";
exit($fail === 0 ? 0 : 1);
