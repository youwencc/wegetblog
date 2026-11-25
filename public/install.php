<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/lib/bootstrap.php';
require_once __DIR__ . '/../app/lib/install.php';

$key = (string) cfg('install_key', '');
$given = get_str('key');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>安装 · <?= h(cfg('site.name')) ?></title>
<style>
body{margin:0;background:#f6f3ec;color:#1b1913;font:15px/1.7 -apple-system,"Segoe UI","Microsoft YaHei",sans-serif}
.wrap{max-width:720px;margin:60px auto;padding:0 24px}
h1{font-size:22px;margin:0 0 6px}
.card{background:#fffdf8;border:1px solid #ddd5c4;padding:22px 24px;margin-top:18px}
p{margin:8px 0}
code{font-family:ui-monospace,Consolas,monospace;font-size:13px;background:#efe9da;padding:1px 5px}
.ok{color:#2f6b46}
.err{color:#a6432b}
ul{margin:8px 0 0;padding-left:20px}
a{color:#a6432b}
</style>
</head>
<body>
<div class="wrap">
<h1>安装 / 初始化</h1>
<p>这个脚本会建表、导入现有文章与作品，并生成一次静态站。</p>
<?php
if (!$key || $given === '' || !hash_equals($key, $given)) {
    echo '<div class="card err"><p>安装密钥不正确。请在 <code>app/config.php</code> 里检查 <code>install_key</code>，然后用 '
        . '<code>install.php?key=你的密钥</code> 访问。</p></div></div></body></html>';
    exit;
}

$steps = [];
try {
    $steps[] = '开始检查数据库连接…';
    db()->query('SELECT 1');
    $steps[] = '数据库连接正常。';

    install_schema();
    $steps[] = '表结构已就绪（posts / works）。';

    $imported = import_seed(false);
    $steps[] = sprintf('导入结果：新增 %d 篇文章、%d 个作品%s。',
        $imported['posts'], $imported['works'],
        $imported['skipped'] ? '，已有数据，跳过导入' : '');

    $stats = publish_site();
    $steps[] = sprintf('静态站已生成：%d 个页面，资产 %d 个文件，输出到 %s。',
        $stats['pages'], $stats['assets'], $stats['dist']);
    $ok = true;
} catch (Throwable $e) {
    $ok = false;
    $steps[] = '出错：' . $e->getMessage();
}
?>
<div class="card">
  <p class="<?= $ok ? 'ok' : 'err' ?>"><strong><?= $ok ? '初始化完成' : '初始化未完成' ?></strong></p>
  <ul>
    <?php foreach ($steps as $step): ?>
    <li><?= h($step) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if ($ok): ?>
<div class="card">
  <p>接下来：</p>
  <ol>
    <li>把 <code>app/config.php</code> 里的 <code>admin.password</code> 换成你自己的密码哈希（或改掉明文密码）。</li>
    <li>登录 <a href="admin.php">admin.php</a>，确认文章和作品都在。</li>
    <li><strong>删除本文件（install.php）</strong>，避免别人重复触发初始化。</li>
  </ol>
</div>
<?php endif; ?>
</div>
</body>
</html>
