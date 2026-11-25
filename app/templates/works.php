<?php
/** @var array $works */
page_head([
    'title' => '作品 — ' . cfg('site.name'),
    'description' => 'WinLite、TinyPlayer、像素数码工坊：三个能装进具体的人一天里的项目。写清为什么做、卡在哪、代价是什么。',
    'path' => 'works.html',
    'og_title' => '作品 — ' . cfg('site.name'),
    'og_desc' => 'WinLite、TinyPlayer、像素数码工坊：三个能装进具体的人一天里的项目。',
    'page' => 'works',
    'jsonld' => works_jsonld($works),
]);
site_header(0, 'works');
?>
<main id="main">
  <div class="shell">
    <nav class="breadcrumb" aria-label="面包屑" style="padding-top:34px">
      <a href="index.html">首页</a><span>/</span><span aria-current="page">作品</span>
    </nav>

    <section class="page-head" style="padding-top:26px">
      <p class="eyebrow">Works / 2024—<?= h(date('Y')) ?></p>
      <h1 class="page-title">做完的，<br>和正在做完的。</h1>
      <p class="page-lede">我挑项目的一个标准是：它得能装进一个具体的人的一天里。下面这些都是这样的东西，每个都写清了为什么做、卡在哪、代价是什么。</p>
    </section>

    <section class="section section--flush" aria-label="作品列表">
      <ul class="works">
        <?= works_list_html($works, 'h2', false) ?>
      </ul>

      <div class="works-foot">
        代码大多在 <a href="<?= h(cfg('site.github')) ?>" rel="noopener">GitHub</a>。商业项目没有公开源码，作品页里写的是技术部分。
      </div>
    </section>

    <!-- ===== 草稿箱 ===== -->
    <section class="section" aria-labelledby="draft-title">
      <div class="split">
        <div class="rail">
          <h2 class="rail-title" id="draft-title">草稿箱</h2>
          <p class="rail-note">Not shipped / 4</p>
        </div>
        <div>
          <ol class="timeline">
            <li>
              <span class="yr">2026.07</span>
              <div class="what"><b>文件清理大师</b>想做一个能看清「哪些文件是谁放的」的清理工具。卡在权限和文件占用检测，暂时搁置。</div>
            </li>
            <li>
              <span class="yr">2026.05</span>
              <div class="what"><b>Weget 口播提词器</b>给短视频作者用的悬浮提词窗口。原型能跑，但滚动节奏一直调不好，先放着。</div>
            </li>
            <li>
              <span class="yr">2025.12</span>
              <div class="what"><b>LibreTV 换肤</b>一套更安静的播放器主题。做完没发布，因为上游改了结构。</div>
            </li>
            <li>
              <span class="yr">2025.08</span>
              <div class="what"><b>修罗论坛皮肤</b>给老 BBS 做的一套克制版模板。需求方后来没上线，源码留在本地。</div>
            </li>
          </ol>
        </div>
      </div>
    </section>
  </div>
</main>
<?php site_footer(0); ?>
</body>
</html>
