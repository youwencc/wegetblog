<?php
/** @var array $work */
/** @var array|null $prevWork */
/** @var array|null $nextWork */
page_head([
    'title' => $work['seo_title'] ?: seo_item_title('work', $work['title']),
    'description' => $work['description'],
    'path' => $work['url'],
    'type' => 'article',
    'depth' => 1,
    'keywords' => implode(', ', array_merge([$work['title']], $work['points'])),
    'jsonld' => work_jsonld($work),
]);
site_header(1, 'works', true);
?>
<main id="main">
  <div class="shell">
    <nav class="breadcrumb" aria-label="面包屑" style="padding-top:30px">
      <a href="../index.html">首页</a><span>/</span><a href="../works.html">作品</a><span>/</span><span aria-current="page"><?= h($work['title']) ?></span>
    </nav>

    <header class="post-head">
      <p class="eyebrow"><?= h($work['eyebrow']) ?></p>
      <h1 class="post-title"><?= h($work['title']) ?></h1>
      <?php if ($work['lede'] !== ''): ?>
      <p class="page-lede" style="margin-top:22px"><?= h($work['lede']) ?></p>
      <?php endif; ?>
      <?php if ($work['status_label'] !== '' || $work['meta_chips']): ?>
      <p class="post-meta" style="margin-top:22px">
        <?php if ($work['status_label'] !== ''): ?>
        <span class="status<?= $work['status_kind'] ? ' status--' . h($work['status_kind']) : '' ?>"><?= h($work['status_label']) ?></span>
        <?php endif; ?>
        <?php
        $chips = array_values(array_filter($work['meta_chips'], static fn ($c) => trim((string) $c) !== '' && trim((string) $c) !== '/'));
        foreach ($chips as $i => $chip):
        ?>
        <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
        <span><?= h((string) $chip) ?></span>
        <?php endforeach; ?>
      </p>
      <?php endif; ?>
    </header>

    <?php if ($work['specs']): ?>
    <section class="section section--spec" aria-label="项目参数">
      <dl class="spec">
        <?php foreach ($work['specs'] as $spec): ?>
        <div><dt><?= h((string) ($spec['label'] ?? '')) ?></dt><dd><?= h((string) ($spec['value'] ?? '')) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </section>
    <?php endif; ?>

    <?php if (trim((string) $work['cover_image']) !== ''): ?>
    <figure class="work-cover">
      <img src="../<?= h(ltrim((string) $work['cover_image'], '/')) ?>" alt="<?= h($work['title']) ?>" loading="lazy">
    </figure>
    <?php endif; ?>

    <section class="section">
      <article class="prose" data-prose>
        <?= $work['body_html'] ?>
      </article>
    </section>

    <section class="section">
      <div class="split">
        <div class="rail">
          <h2 class="rail-title">相关</h2>
          <p class="rail-note">Links</p>
        </div>
        <div>
          <?php if (trim($work['related_html']) !== ''): ?>
          <div class="about-body" style="margin-bottom:24px">
            <?= $work['related_html'] ?>
          </div>
          <?php endif; ?>
          <nav class="post-nav" aria-label="项目翻页">
            <?php if ($prevWork): ?>
            <a href="<?= h($prevWork['slug']) ?>.html">
              <span class="dir">← 上一个项目</span>
              <span class="t"><?= h($prevWork['title']) ?><?= $prevWork['en'] !== '' ? ' · ' . h($prevWork['en']) : '' ?></span>
            </a>
            <?php else: ?>
            <a href="../works.html">
              <span class="dir">← 返回列表</span>
              <span class="t">全部作品</span>
            </a>
            <?php endif; ?>
            <?php if ($nextWork): ?>
            <a href="<?= h($nextWork['slug']) ?>.html">
              <span class="dir">下一个项目 →</span>
              <span class="t"><?= h($nextWork['title']) ?><?= $nextWork['en'] !== '' ? ' · ' . h($nextWork['en']) : '' ?></span>
            </a>
            <?php else: ?>
            <a href="../works.html">
              <span class="dir">回到列表 →</span>
              <span class="t">全部作品</span>
            </a>
            <?php endif; ?>
          </nav>
        </div>
      </div>
    </section>
  </div>
</main>
<?php site_footer(1); ?>
</body>
</html>
