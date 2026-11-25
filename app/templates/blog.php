<?php
/** @var array $posts */
page_head([
    'title' => '博客 — ' . cfg('site.name'),
    'description' => '按时间倒序的技术文章：PixiJS 性能、WPF 内存、Godot 迁移与桌面软件架构。只写自己真正改过、量过的代码。',
    'path' => 'blog.html',
    'og_title' => '博客 — ' . cfg('site.name'),
    'og_desc' => '按时间倒序的技术文章：PixiJS 性能、WPF 内存、Godot 迁移与桌面软件架构。',
    'page' => 'blog',
    'jsonld' => blog_jsonld($posts),
]);
site_header(0, 'blog');
?>
<main id="main">
  <div class="shell">
    <nav class="breadcrumb" aria-label="面包屑" style="padding-top:34px">
      <a href="index.html">首页</a><span>/</span><span aria-current="page">博客</span>
    </nav>

    <section class="page-head" style="padding-top:26px">
      <p class="eyebrow">Writing / Archive</p>
      <h1 class="page-title">博客</h1>
      <p class="page-lede">按时间倒序。技术文章只写自己真正改过、量过或者删掉的代码，写不清楚的一律不发。偏向桌面和游戏，因为日常就在这两块打转。</p>
      <?= blog_filters_html($posts) ?>
    </section>

    <?= blog_archive_html($posts) ?>
  </div>
</main>
<?php site_footer(0); ?>
</body>
</html>
