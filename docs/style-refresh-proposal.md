# 界面视觉优化建议 —— 「电脑小百科 · 技术笔记」

> 基于对 `app/templates/`（index / blog / post / works / work）与 `app/assets/style.css`（1723 行）、`main.js` 的通读分析。
> 前提：站点有非常明确且自洽的「旧书纸 + 印刷氧化红」美学（about 页里作者自己也写明了），本方案所有建议都在**不推翻这套语言**的前提下增强丰富度，而不是改成通用 SaaS 渐变风。

---

## 一、诊断结论

「素」的根源不是缺内容——页面细节其实很多（像素工位动画、雨、猫尾、蒸汽、logbook 跑马灯、eq 音条、noise 纹理都已存在），而是**三个系统性问题**：

1. **色彩配比失衡**：全局定义了 `--accent: #a6432b`（氧化红）和 `--accent-soft`，但实际只在链接 hover、按钮下划线、`note-card` 序号等零星处使用。大面积是 `--paper` / `--ink` 的纸墨双色，**红色占比估计不到 2%**，页面像一张没盖章的校样纸。
2. **零深度**：整份 CSS 没有一处 `box-shadow`。`note-card`、`stack`、`figures`、`notes` 全是「1px 边框 + 平色填充」，所有区块在同一平面上，缺少纸叠纸的物理层次。
3. **版式节奏平**：section 之间只靠 1px `--rule` 分隔；标题层级 `clamp(20~25px)` 偏小；`kicker` / `f-label` / `rail-note` 全是 mono 10px 灰字，滚动时每屏的视觉重量几乎相同，没有「呼吸」。

**一句话**：这是一个把「克制」执行得很好、但把「强调」几乎完全放弃的设计。印刷品的克制来自大量留白 + 极少量的高饱和强调，目前是前者满格、后者为零。

---

## 二、必须保留的设计基线（改样式时的红线）

| 基线 | 理由 |
|---|---|
| 纸色系 `--paper/--paper-2/--paper-3` + 墨色系 `--ink/--ink-soft/--muted` | 站点的身份认同，about 页已向读者声明 |
| Noto Serif SC 正文 / mono 标注的组合 | 印刷感的来源 |
| 像素 SVG 动画与 `@keyframes` 全家桶（rain/steam/typing/cat-tail/eq/logbook） | 已是最有个性的部分，不要再叠加动画 |
| `prefers-reduced-motion` 全局兜底（style.css 1710 行） | 写得非常规范，新动画必须继续被它覆盖 |
| `@media print`（1698 行）与暗色主题 `html[data-theme="dark"]` 变量覆盖 | 新增颜色必须同时过「暗色 + 打印」两道关 |

---

## 三、问题清单（定位到具体代码）

| # | 位置 | 现状 | 为什么显素 |
|---|---|---|---|
| 1 | `:root`（01 变量段） | 只有 2 个 accent 变量，且缺少暗色下的 accent 提亮（dark 块未单独调红） | 强调色体系太薄，且暗色下氧化红对深纸底对比不足 |
| 2 | `.pull-band`（首页摘录带） | 与普通 section 无异的纸底 + 细边 | 这是全页最适合做「视觉锚点」的大色块机会，现在浪费了 |
| 3 | `.note-card` / `.stack div` / `notes` 网格 | `background: var(--paper)` + 1px 边框，全平 | 所有卡片贴在同一平面，滚动时无层次感 |
| 4 | `.btn` / `.btn--ghost` | 推测为线框风格（ghost 明确是），主按钮不够「重」 | 首屏 CTA「读文章 →」是纸面上一根细线，抓不住眼睛 |
| 5 | `.figures`（数字条） | mono 10.5px 灰标签 + 15px 数字 | 数字是首页最有信息量的元素，字重和字号都被压到最小 |
| 6 | `.rail-title` / section 标题 | 纯文字 + 底部 rule | 缺少印刷品常见的「章节数字 / 红色标尺」锚点 |
| 7 | `.kicker` / `f-label` | mono 灰色小字 | 全页统一的灰色小标签没有形成节奏，红色可以作为它们的「编号色」 |
| 8 | 全局交互 | hover 基本只有 `color` 变化（`.site-nav a`、`.work-title h2 a:hover`） | 反馈过轻，且卡片整体没有任何 hover 态 |

---

## 四、优化建议

### P0 · 色彩配比（预计 +30 分钟，收益最大）

**思路：把红色从「点缀」提升到「结构」，目标全页占比 5% 左右——像一页纸上的几处朱批。**

#### 4.1 摘录带 `pull-band` 改为氧化红实底

这是单条收益最大的改动——全页唯一的大色块，形成滚动节奏中的强拍：

```css
.pull-band {
  background: var(--accent);
  color: #faf6ea;            /* 复用 --st-paper */
  border-block: 1px solid var(--ink);
}
.pull-band .f-label,
.pull-band .pull-source { color: rgb(250 246 234 / .75); }
.pull-band .pull-quote a { color: #faf6ea; }
.pull-band .pull-quote a:hover { text-decoration-thickness: 2px; }
```

暗色主题下同样成立（红底不需要变），打印时可回退纸底。

#### 4.2 派生一套「朱批」微色（不是加新颜色，是从主色派生）

```css
:root {
  --accent-wash: color-mix(in oklab, var(--accent) 7%, var(--paper));   /* 极淡红纸 */
  --accent-line: color-mix(in oklab, var(--accent) 35%, var(--rule));   /* 红灰界线 */
}
html[data-theme="dark"] {
  --accent: #c2674c;        /* 暗色下提亮一档，保证 3:1 以上对比 */
}
```

用途：`.note` 的左边线、`.figures` 的上下边、TOC 高亮条、`::selection`。
> 依据现代网页指南 `web/visual-design/color`：派生色用 `color-mix(in oklab, ...)` 从主色生成可保证可访问性随主色联动；红色仅用于强调时，**不要让状态只靠颜色区分**（本站状态均有文字/形状，合规）。

#### 4.3 文字选中色（一行，印刷感极强）

```css
::selection { background: var(--accent); color: #faf6ea; }
```

#### 4.4 章节编号上色

`.rail-title` / `essay-head` 前加 mono 编号（`01` / `02`…）或把 `.f-label` 的前缀方块染红——把全页散落的灰色小标签变成「红色刻度线」：

```css
.f-label::before {
  content: "";
  display: inline-block;
  width: 6px; height: 6px;
  background: var(--accent);
  margin-right: 8px;
  vertical-align: 1px;
}
```

### P0 · 纸面深度（预计 +20 分钟）

#### 4.5 卡片极轻双层阴影

克制是关键——是「纸叠在纸上」，不是浮起：

```css
.note-card, .stack div {
  box-shadow:
    0 1px 2px rgb(25 23 18 / .05),
    0 10px 28px rgb(25 23 18 / .05);
}
```

暗色下把阴影换成更深的墨色（`rgb(0 0 0 / .3)`）即可，色值写进 `html[data-theme="dark"]` 覆盖。

#### 4.6 主按钮「盖章」化

```css
.btn {
  background: var(--accent);
  color: #faf6ea;
  border: 1px solid var(--ink);
  box-shadow: 2px 2px 0 var(--ink);          /* 印刷套版错位感 */
  transition: transform .15s var(--ease), box-shadow .15s var(--ease);
}
.btn:hover {
  transform: translate(-1px, -1px);
  box-shadow: 3px 3px 0 var(--ink);
  background: var(--accent-soft);
}
.btn:active { transform: translate(1px, 1px); box-shadow: 0 0 0 var(--ink); }
.btn--ghost { background: transparent; color: var(--ink); }  /* 保持现状 */
```

`btn--ghost` 保持线框不动，形成「一实一虚」的层级。

### P1 · 版式节奏（预计 +30 分钟）

#### 4.7 section 标题层级拉开

- `.rail-title` / `.section` 标题从 `clamp(20px, 2.4vw, 25px)` 提到 `clamp(24px, 3vw, 30px)`，并保留现有 letter-spacing。
- 标题上方加红色小刻度（4.4 的 `.f-label::before` 方案），下方 rule 改 `--accent-line` 派生线，形成「红头文件」式的章节锚点。

#### 4.8 `figures` 数字条加重

```css
.figures b {
  font-size: 22px;                 /* 15px → 22px */
  font-weight: 500;
  color: var(--ink);
  display: block;
}
.figures { border-block-color: var(--accent-line); }
```

数字是首页的信息锚点，值得占视觉重量；上下边染红灰后整条带会从「灰线夹灰字」变成一个独立视觉单元。

#### 4.9 留白节奏

首页各 section 之间的 padding 目前统一，可改成「强 section（hero / pull-band / 关于本站）前后大、普通列表 section 紧凑」的两档节奏，让滚动产生快慢交替。

### P1 · 微交互（预计 +20 分钟）

#### 4.10 链接 hover：颜色 → 下划线动效

```css
.entry-title a, .work-title h2 a, .work-title h3 a {
  background-image: linear-gradient(currentColor, currentColor);
  background-size: 0% 1px;
  background-repeat: no-repeat;
  background-position: 0 100%;
  transition: background-size .25s var(--ease);
}
.entry-title a:hover { background-size: 100% 1px; }
```

比纯变色反馈强，且不引入任何新视觉元素。

#### 4.11 卡片 hover（可选，注意克制）

`.note-card:hover` 边框变 `--accent-line` + 阴影加深一档即可；不要位移——大面积 transform 会破坏印刷的稳定感。

#### 4.12 滚动进入的淡入（可选，需 JS/IO）

现代做法是 CSS Scroll-driven Animations：

```css
@media (prefers-reduced-motion: no-preference) {
  .entry, .work-item {
    animation: fade-up linear both;
    animation-timeline: view();
    animation-range: entry 0% / entry 60%;
  }
  @keyframes fade-up {
    from { opacity: 0; translate: 0 14px; }
    to   { opacity: 1; translate: 0 0; }
  }
}
```

> 依据现代网页指南 `web/ui-behaviors/scroll-entry-exit-effects`：entry 效果用 `animation-timeline: view()` + `animation-range: entry`，必须整体包在 `prefers-reduced-motion: no-preference` 里（本站已有 reduce 兜底，会被现有 1710 行的全局规则接住）。
> 兼容性：Chrome/Edge 115+ 原生支持；Firefox / Safari 旧版不支持时动画不执行、内容直接静态呈现（progress-based animation 的优雅降级），可接受，无需 polyfill。

### P2 · 暗色主题打磨（预计 +15 分钟）

#### 4.13 暗色下的红要提亮

`--accent: #a6432b` 在深纸底上会脏。`html[data-theme="dark"]` 里覆盖为 `--accent: #c2674c`（正好复用现有 `--accent-soft`），链接、TOC 高亮、按钮都会自动受益。

#### 4.14 暗色卡片背景分层

现有暗色把 `--paper` 直接压暗。建议三档纸色在暗色下间距拉大（`--paper` 最深、`--paper-2` 略浅做卡片底、`--paper-3` 做边线），卡片从背景里「浮」出来一度，配合 4.5 的深色阴影。

### P2 · 其他细节

- **4.15 字体渲染**：正文 `text-rendering: optimizeLegibility`（如未设置）。
- **4.16 `::selection`** 已在 4.3；补 `html[data-theme="dark"] ::selection { color: #14130e; }` 反色。
- **4.17 滚动条**：`scrollbar-color: var(--rule-strong) transparent; scrollbar-width: thin;`（Baseline，印刷风格加分）。
- **4.18 首页 hero 的 CTA**：配合 4.6 的「盖章」按钮，hero 右侧或下方放唯一的红色实底按钮「读最新文章 →」，全页只此一个，强化「一页一强拍」。

---

## 五、实施顺序与工作量

| 优先级 | 条目 | 预计 |
|---|---|---|
| P0 | 4.1 pull-band 红底 + 4.2 派生色 + 4.3 selection + 4.5 阴影 + 4.6 盖章按钮 | ~50 分钟 |
| P1 | 4.4 编号红刻度 + 4.7 标题层级 + 4.8 数字条 + 4.10 链接动效 | ~50 分钟 |
| P2 | 4.12 滚动淡入 + 4.13/4.14 暗色打磨 + 4.17 滚动条 | ~35 分钟 |

全部落地预计 2~2.5 小时。每一项都只动 `app/assets/style.css`（4.4 的编号需模板加 mono 数字，约 6 处），不碰 `main.js` 与构建流程。

---

## 六、验证清单

- [ ] 浏览器开 `dist/` 或本地预览，逐页过：index / blog / post / works / about / column
- [ ] 切换暗色主题，确认红色变亮、卡片分层、无「深底深字」
- [ ] `prefers-reduced-motion: reduce` 下无新增动画（4.12 整段失效为静态）
- [ ] 键盘 Tab 遍历首页，焦点态清晰（若红底上焦点环不清楚，需补 `:focus-visible` outline）
- [ ] 打印预览：pull-band 红底应在 `@media print` 中回退纸底（或确认红底打印可接受）
- [ ] 红色占比目测控制在 5% 上下（pull-band + 按钮 + 刻度 + selection）
