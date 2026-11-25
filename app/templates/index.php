<?php
/** @var array $posts */
/** @var array $works */
/** @var array $stats */
$jsonld = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id' => site_url('#website'),
            'url' => site_url(''),
            'name' => cfg('site.name'),
            'description' => '桌面软件、像素游戏与工程实践的个人技术博客',
            'inLanguage' => 'zh-CN',
            'publisher' => ['@id' => site_url('#person')],
        ],
        [
            '@type' => 'Person',
            '@id' => site_url('#person'),
            'name' => cfg('site.author'),
            'alternateName' => 'Chen Yu',
            'url' => site_url('about.html'),
            'jobTitle' => '独立开发者',
            'description' => 'Windows 桌面软件与网页像素游戏开发者',
            'knowsAbout' => ['C#', 'WPF', 'TypeScript', 'PixiJS', 'Godot', 'Windows 桌面开发'],
            'sameAs' => [cfg('site.github')],
        ],
    ],
];

page_head([
    'title' => cfg('site.name') . ' — 桌面软件、像素游戏与工程实践',
    'description' => '陈屿的个人技术博客。写 Windows 桌面软件、PixiJS 网页像素游戏与 Godot，记录项目、性能取舍和踩过的坑。',
    'path' => '',
    'og_title' => cfg('site.name'),
    'og_desc' => '桌面软件、像素游戏与工程实践。Windows 工具开发、PixiJS 性能、Godot 与 WPF 的实践记录。',
    'page' => 'home',
    'jsonld' => $jsonld,
]);
site_header(0, 'home');
?>
<main id="main">
  <!-- ===== 首页 · 自我介绍 ===== -->
  <section class="hero shell" aria-labelledby="hero-title">
    <div class="hero-grid">
      <div>
        <p class="kicker">独立开发者 · 桌面软件与像素游戏</p>
        <h1 id="hero-title"><?= h(cfg('site.author')) ?></h1>
        <span class="name-roman">Chen Yu — Shantou, CN</span>
        <p class="lede">
          我写 Windows 桌面小工具，也写网页像素游戏。这里放项目的来龙去脉、技术上的取舍，和一些没地方放的长文字。最近在把《像素数码工坊》从一个原型推到<strong>能真正玩下去</strong>的版本。
        </p>
        <div class="actions">
          <a class="btn" href="blog.html">读文章 →</a>
          <a class="btn btn--ghost" href="works.html">看作品</a>
          <a class="link-quiet" href="about.html">关于我</a>
        </div>
      </div>

      <aside class="hero-side" aria-label="工位">
        <figure class="studio">
          <svg viewBox="0 0 56 36" shape-rendering="crispEdges" role="img" aria-label="像素动画：一个穿红卫衣、戴耳机的人坐在木桌前敲代码，窗外在下雨">
            <rect width="56" height="36" fill="var(--st-wall)"/>
            <rect y="30" width="56" height="6" fill="var(--st-floor)"/>
            <rect y="30" width="56" height="1" fill="var(--st-rule)"/>

            <rect x="1" y="4" width="10" height="8" fill="var(--st-ink)"/>
            <rect x="2" y="5" width="8" height="6" fill="var(--st-floor)"/>
            <rect x="5" y="5" width="1" height="6" fill="var(--st-ink)"/>
            <rect x="2" y="8" width="8" height="1" fill="var(--st-ink)"/>
            <rect x="3" y="6" width="2" height="2" fill="var(--st-muted)"/>
            <rect x="7" y="9" width="2" height="2" fill="var(--st-muted)"/>
            <rect class="rain r1" x="4" y="6" width="1" height="2" fill="var(--st-ink)"/>
            <rect class="rain r2" x="8" y="7" width="1" height="2" fill="var(--st-ink)"/>

            <rect x="13" y="12" width="3" height="1" fill="var(--st-chair)"/>
            <rect x="12" y="13" width="3" height="11" fill="var(--st-chair)"/>
            <rect x="12" y="14" width="1" height="9" fill="var(--st-chair-edge)"/>
            <rect x="12" y="26" width="13" height="1" fill="var(--st-chair)"/>
            <rect x="18" y="27" width="2" height="3" fill="var(--st-muted)"/>
            <rect x="13" y="30" width="12" height="1" fill="var(--st-chair)"/>
            <rect x="12" y="31" width="2" height="2" fill="var(--st-chair)"/>
            <rect x="13" y="32" width="1" height="1" fill="var(--st-chair-edge)"/>
            <rect x="24" y="31" width="2" height="2" fill="var(--st-chair)"/>
            <rect x="25" y="32" width="1" height="1" fill="var(--st-chair-edge)"/>

            <rect x="20" y="14" width="2" height="1" fill="var(--st-skin)"/>
            <rect x="17" y="8" width="6" height="6" fill="var(--st-ink)"/>
            <rect x="23" y="9" width="2" height="5" fill="var(--st-skin)"/>
            <rect x="24" y="10" width="1" height="1" fill="var(--st-ink)"/>
            <rect x="25" y="11" width="1" height="1" fill="var(--st-skin)"/>
            <rect x="17" y="7" width="8" height="1" fill="var(--st-accent-soft)"/>
            <rect x="16" y="9" width="1" height="3" fill="var(--st-accent-soft)"/>
            <rect x="16" y="14" width="8" height="1" fill="var(--st-accent-soft)"/>
            <rect x="15" y="15" width="10" height="1" fill="var(--st-accent)"/>
            <rect x="15" y="16" width="10" height="8" fill="var(--st-accent)"/>
            <rect x="24" y="16" width="1" height="8" fill="var(--st-accent-soft)"/>

            <rect x="1" y="24" width="54" height="1" fill="var(--st-wood)"/>
            <rect x="1" y="25" width="54" height="1" fill="var(--st-wood-dark)"/>
            <rect x="7" y="26" width="2" height="7" fill="var(--st-wood-dark)"/>
            <rect x="49" y="26" width="2" height="7" fill="var(--st-wood-dark)"/>

            <rect x="6" y="21" width="2" height="2" fill="var(--st-accent)"/>
            <rect x="8" y="22" width="1" height="1" fill="var(--st-accent)"/>
            <rect class="steam s1" x="6" y="19" width="1" height="1" fill="var(--st-paper)"/>
            <rect class="steam s2" x="7" y="18" width="1" height="1" fill="var(--st-paper)"/>
            <rect class="steam s3" x="6" y="17" width="1" height="1" fill="var(--st-paper)"/>

            <rect x="49" y="21" width="3" height="2" fill="var(--st-wood-dark)"/>
            <rect x="50" y="19" width="1" height="2" fill="var(--st-leaf)"/>
            <rect x="51" y="17" width="1" height="3" fill="var(--st-leaf)"/>
            <rect x="48" y="19" width="1" height="2" fill="var(--st-leaf)"/>
            <rect x="52" y="18" width="1" height="2" fill="var(--st-leaf)"/>

            <rect x="31" y="8" width="16" height="13" fill="var(--st-ink)"/>
            <rect x="33" y="10" width="12" height="9" fill="var(--st-paper)"/>
            <rect x="33" y="10" width="12" height="1" fill="var(--st-wall)"/>
            <rect x="35" y="11" width="5" height="1" fill="var(--st-ink)"/>
            <rect x="37" y="13" width="7" height="1" fill="var(--st-leaf)"/>
            <rect x="35" y="15" width="4" height="1" fill="var(--st-accent)"/>
            <rect x="36" y="17" width="7" height="1" fill="var(--st-muted)"/>
            <rect class="screen-cursor" x="44" y="17" width="1" height="1" fill="var(--st-accent)"/>
            <rect x="39" y="21" width="2" height="2" fill="var(--st-ink)"/>
            <rect x="36" y="23" width="8" height="1" fill="var(--st-ink)"/>

            <rect x="26" y="23" width="9" height="1" fill="var(--st-wall)"/>
            <rect x="27" y="23" width="1" height="1" fill="var(--st-muted)"/>
            <rect x="29" y="23" width="1" height="1" fill="var(--st-muted)"/>
            <rect x="31" y="23" width="1" height="1" fill="var(--st-muted)"/>
            <rect x="33" y="23" width="1" height="1" fill="var(--st-muted)"/>

            <rect x="23" y="16" width="2" height="4" fill="var(--st-accent)"/>
            <rect x="23" y="19" width="3" height="2" fill="var(--st-accent)"/>
            <g class="typing">
              <rect x="25" y="21" width="4" height="2" fill="var(--st-accent)"/>
              <rect x="29" y="22" width="2" height="1" fill="var(--st-skin)"/>
            </g>
          </svg>
          <figcaption><span class="studio-tag">Studio / <?= h(date('Y.m')) ?></span>房间加载改到第四版，屏幕上还没跑通</figcaption>
        </figure>
      </aside>
    </div>

    <?= figures_html($stats) ?>

    <div class="logbook" aria-label="工作日志">
      <p class="logbook-tag"><i class="logbook-dot" aria-hidden="true"></i>Logbook<span>近期</span></p>
      <div class="logbook-viewport">
        <div class="logbook-track">
          <?php $logbook = settings_logbook(); ?>
          <?php for ($run = 0; $run < 2; $run++): ?>
          <ul class="logbook-run"<?= $run === 1 ? ' aria-hidden="true"' : '' ?>>
            <?php foreach ($logbook as $item): ?>
            <li><time datetime="<?= h($item['date']) ?>"><?= h(fmt_date($item['date'], 'm.d')) ?></time><?= h($item['text']) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== 首页 · 最近写的 ===== -->
  <section class="section shell" aria-labelledby="recent-title">
    <div class="split">
      <div class="rail">
        <h2 class="rail-title" id="recent-title">最近写的</h2>
        <p class="rail-note">Writing / <?= h(date('Y')) ?></p>
        <a class="rail-more" href="blog.html">全部文章 →</a>
      </div>
      <?= home_recent_html($posts, 4) ?>
    </div>
  </section>

  <!-- ===== 首页 · 作品 ===== -->
  <section class="section shell" aria-labelledby="works-title">
    <div class="split">
      <div class="rail">
        <h2 class="rail-title" id="works-title">作品</h2>
        <p class="rail-note">Selected / <?= count($works) ?></p>
        <a class="rail-more" href="works.html">全部作品 →</a>
      </div>
      <ul class="works">
        <?= works_list_html($works, 'h3', false, 3) ?>
      </ul>
    </div>
  </section>

  <!-- ===== 首页 · 摘录 ===== -->
  <section class="pull-band" aria-labelledby="pull-title">
    <div class="shell pull-band-inner">
      <div class="pull-meta">
        <p class="f-label" id="pull-title">Excerpt / 专栏</p>
        <p class="pull-source">《把手艺做成产品》<br>2026.10.10</p>
      </div>
      <blockquote class="pull-quote">
        <p>手艺的标准是自己满意，<br>产品的标准是别人不用问。</p>
        <a href="column.html">读全文 →</a>
      </blockquote>
    </div>
  </section>

  <!-- ===== 首页 · 关于本站 ===== -->
  <section class="section shell" aria-labelledby="site-title">
    <div class="split">
      <div class="rail">
        <h2 class="rail-title" id="site-title">关于本站</h2>
        <p class="rail-note">Colophon</p>
      </div>
      <div class="about-body">
        <p>这个站点是手写的静态页面，后端只有一套很轻的发布工具：文章和作品存在 MySQL 里，发布时生成静态 HTML，再同步到托管服务。没有前端框架，没有评论系统，也没有埋点脚本。</p>
        <p>正文字体是 Noto Serif SC 与 Noto Sans SC，配色取自旧书纸和印刷用的氧化红。页面在 1280 宽的屏幕上排成两栏，窄屏会自然收成一栏，打印时会去掉导航和深色底。</p>
        <p>如果你要引用这里的文字，注明出处和链接就行；代码片段随便用。<a href="about.html">更多关于我</a>，或者直接<a href="<?= h(cfg('site.github')) ?>" rel="me noopener">看 GitHub</a>。</p>
      </div>
    </div>
  </section>
</main>
<?php site_footer(0); ?>
</body>
</html>
