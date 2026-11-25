<?php
/** @var array $post */
/** @var array|null $newer */
/** @var array|null $older */
/** @var string $projectUrl */
$crumbs = [
    ['首页', site_url('')],
    ['博客', site_url('blog.html')],
    [$post['title'], site_url($post['url'])],
];
page_head([
    'title' => $post['seo_title'] ?: seo_item_title('post', $post['title']),
    'description' => $post['description'],
    'path' => $post['url'],
    'type' => 'article',
    'depth' => 1,
    'published' => $post['published_at'] ? $post['published_at'] . 'T09:00:00+08:00' : '',
    'section' => $post['section'],
    'keywords' => $post['keywords'],
    'jsonld' => post_jsonld($post),
]);
site_header(1, 'blog', true);
?>
<main id="main">
  <div class="shell">
    <nav class="breadcrumb" aria-label="面包屑" style="padding-top:30px">
      <a href="../index.html">首页</a><span>/</span><a href="../blog.html">博客</a><span>/</span><span aria-current="page"><?= h($post['title']) ?></span>
    </nav>

    <header class="post-head">
      <p class="eyebrow"><?= h($post['eyebrow']) ?></p>
      <h1 class="post-title"><?= h($post['title']) ?></h1>
      <p class="post-meta">
        <time datetime="<?= h((string) $post['published_at']) ?>"><?= h(fmt_date($post['published_at'])) ?></time>
        <span class="sep">/</span>
        <span><?= (int) $post['reading_minutes'] ?> 分钟 · 约 <?= number_format((int) $post['word_count']) ?> 字</span>
        <?php if ($post['section'] !== ''): ?>
        <span class="sep">/</span>
        <span><?= h($post['section']) ?></span>
        <?php endif; ?>
        <?php if ($post['project'] !== ''): ?>
        <span class="sep">/</span>
        <span><?= h($post['project']) ?></span>
        <?php endif; ?>
        <?php if (!empty($post['updated_on']) && $post['updated_on'] !== $post['published_at']): ?>
        <span class="sep">/</span>
        <span>更新于 <?= h(fmt_date($post['updated_on'])) ?></span>
        <?php endif; ?>
      </p>
    </header>

    <div class="post-grid">
      <article class="prose" data-prose>
        <?= $post['body_html'] ?>
      </article>

      <aside class="toc" aria-label="本文目录">
        <h2>目录 / Contents</h2>
        <nav data-toc></nav>
      </aside>
    </div>

    <footer class="post-foot">
      <ul class="tags" aria-label="标签">
        <?php foreach ($post['tags'] as $tag): ?>
        <li><a class="tag" href="../blog.html"><?= h($tag) ?></a></li>
        <?php endforeach; ?>
        <?php if ($post['project'] !== '' && $projectUrl !== ''): ?>
        <li><a class="tag" href="<?= h($projectUrl) ?>"><?= h($post['project']) ?></a></li>
        <?php endif; ?>
      </ul>

      <nav class="post-nav" aria-label="文章翻页">
        <?php if ($newer): ?>
        <a href="<?= h($newer['slug']) ?>.html">
          <span class="dir">← 上一篇</span>
          <span class="t"><?= h($newer['title']) ?></span>
        </a>
        <?php else: ?>
        <a href="../blog.html">
          <span class="dir">← 返回列表</span>
          <span class="t">全部文章</span>
        </a>
        <?php endif; ?>
        <?php if ($older): ?>
        <a href="<?= h($older['slug']) ?>.html">
          <span class="dir">下一篇 →</span>
          <span class="t"><?= h($older['title']) ?></span>
        </a>
        <?php else: ?>
        <a href="../blog.html">
          <span class="dir">回到列表 →</span>
          <span class="t">全部文章</span>
        </a>
        <?php endif; ?>
      </nav>
    </footer>
  </div>
</main>
<?php site_footer(1); ?>
</body>
</html>
