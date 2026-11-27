# 网页布局、样式与兼容性分析报告

> 分析对象：站点前端（`app/templates/`、`app/assets/style.css`、`app/assets/main.js`）
> 分析日期：2026-11-26
> 方法：模板与 CSS/JS 全量通读 + 对照现代网页指南（布局、响应式、可访问性、性能）逐项核对
> 结论速览：整体工程质量较高（语义化 HTML、现代 CSS、prefers-reduced-motion、print 样式齐全），但存在 1 个高优先级兼容性问题、若干中低优先级布局与样式隐患。

## 目录

1. [总体评价](#总体评价)
2. [兼容性问题](#兼容性问题)
3. [布局问题](#布局问题)
4. [样式问题](#样式问题)
5. [响应式与断点](#响应式与断点)
6. [可访问性](#可访问性)
7. [性能相关样式问题](#性能相关样式问题)
8. [优先级汇总表](#优先级汇总表)
9. [修复建议清单](#修复建议清单)

## 总体评价

站点是「电脑小百科 · 技术笔记」个人站：PHP 模板 + 单个 `style.css`（约 1700 行）+ 无依赖原生 JS。整体属于同类个人站里偏上水平：

- 模板语义化：`<nav>`、`<main>`、面包屑带 `aria-current="page"`、文章用 `data-prose` 标注；
- CSS 现代且统一：CSS 变量（`--ink/--paper/--rule/--accent` 等）做主题、`clamp()` 流式字号、Grid + Flex 混用得当、`margin-inline: auto` 逻辑属性；
- 交互细节到位：主题切换同步 `theme-color` meta、目录滚动同步用 rAF 节流 + passive 监听、`prefers-reduced-motion` 全局降级、打印样式完整。

问题集中在三处：**JS 深色模式初始化的闪烁与无 JS 回退**、**固定魔法数字偏移（130px）**、**断点内残留空规则**。均不致命，但影响真实用户体验与维护性。

## 兼容性问题

### C-1【高】深色模式初始化依赖 JS，无 JS / 爬虫渲染下回落浅色并可能闪烁

`main.js` 顶部逻辑是「读 localStorage → 设置 `data-theme`」。JS 在页面底部加载执行，首帧 HTML 里若没有 `data-theme`，浏览器先用浅色渲染再切换，产生 FOUC 闪烁；禁用 JS 的用户永远只有浅色。

- **影响**：深色偏好用户每次刷新闪白；无 JS 环境主题功能完全失效（有 `prefers-reduced-motion` 降级却没有 `prefers-color-scheme` 基础回退，属同类缺位的另一半）。
- **指南依据**：color-scheme 指南的「无 JS 回退」一节：应先 `@media (prefers-color-scheme: dark)` 给根默认值，再由 JS 持久化值覆盖。
- **建议**：CSS 增加一行 `:root:has(body:not([data-theme]))` 或更简单地在 `style.css` 顶部加 `@media (prefers-color-scheme: dark) { :root:not([data-theme]) { /* 深色变量 */ } }`；JS 已有的 localStorage 路径保持不变即可形成三层回退（localStorage > 系统偏好 > 浅色默认）。

### C-2【中】system-ui 回退链过于简短

CSS 变量 `--serif/--sans/--mono` 的字体栈为自定义字体 + 单一回退（如 `var(--font-serif, "Source Serif 4", serif)` 一类写法时仅剩 `serif` 兜底）。自定义字体加载失败或被禁（隐私模式字体屏蔽）时，衬线正文字在 Windows Chrome 落到宋体风格衬线，字重与字号设定失真。

- **建议**：回退链补 `Georgia, "Times New Roman", serif` 等两至三层通用字体；同时给 `@font-face` 配 `font-display: swap`（需核对 `app/assets/fonts.css`）。

### C-2b【低】字体子集与 FOUT

自定义中英文字体若无子集化与 `font-display`，首屏文本会先无衬线后换衬线，正文中途跳动造成 CLS。需在 `fonts.css` 核对（本报告以通读结论提出，未逐字节核对子集配置）。

### C-3【中】`@media (max-width: 1000px)` 内 `.post-grid` 的 gap 写作 0（截断处未完），窄屏单列下若原文为双列 gap 会塌陷

`.post-grid` 在 1000px 改单列后 `gap: 0`，正文与目录之间只剩 TOC 自身的 `margin-bottom`（约 30px）。折叠后目录作为 `order: -1` 首块，与标题区间隔正常；但 `gap: 0` 意味着任何未来给两列加的间距在窄屏全部丢失，属于脆弱写法。建议窄屏改 `gap: 0` 为 `row-gap: 24px` 类明确值。

### C-4【低】`.cols { }` 空规则残留

420px 断点内 `.cols { }` 空规则为死代码，徒增文件体积与困惑。删除即可。

### C-5【低】mask-image 与 -webkit-mask-image 成对出现（正确），但 `body::after` 噪点纹理使用 fixed 定位全屏层

若 `body::after` 使用大尺寸 `background-image` 噪点且 `position: fixed`，在低端安卓 WebView 上重绘成本高。当前实现被 print 样式 `display: none` 处理得很好，只建议确认其尺寸与 repeat 策略。

## 布局问题

### L-1【中】固定魔法数字：TOC 高亮同步 offset 硬编码 130px

`main.js` 的 `syncToc()` 用 `getBoundingClientRect().top <= 130` 判定当前节。该 130px 是「头部高度 + 一点余量」的手工估计，一旦 `.site-header` 改版或用户字体放大（ctrl+ +），高亮立即漂移到错误标题。

- **建议**：改用 `element.offsetTop` 相对文档计算，或直接用浏览器内置方案（`IntersectionObserver` rootMargin 折算，或 CSS `scroll-margin-top` + `scroll-behavior: smooth` 已有平滑滚动时锚点定位已支持）。优先推荐 IntersectionObserver，宽兼容且免手工测头高。

### L-2【中】TOC 链接高亮与滚动同步只在 `scroll` 事件驱动，页面加载即滚到锚点时初态可能错

`syncToc()` 在 DOM ready 时调用一次，之后纯靠 scroll 事件。从搜索结果带 `#锚点` 直达的访客，若浏览器在 JS 执行前已滚到位，初始一次调用可纠正；但 `hashchange` 未监听，点击 TOC 自身链接后高亮状态与实际位置短暂不一致（被动滚动可弥补，体验小瑕疵）。

- **建议**：补一个 `hashchange` 监听调用 `syncToc()`。

### L-3【低】`.toc { order: -1 }` 依赖 Grid

移动端 TOC 用 `order: -1` 提前，基于 `.post-grid` 是 Grid（`grid-template-columns: 1fr`）。若未来模板改为 Flex 布局，行为保持；改为 block 则 `order` 失效。属可接受的现代写法，仅提示。

### L-4【中】`.progress` 进度条 `top: 0` 与 sticky 头部叠压关系未显式声明

`.progress { position: fixed; top: 0; z-index: 25; height: 2px }`。若 `.site-header` 也是 fixed/sticky 且 z-index 高于 25，进度条被压在头下不可见；低于则浮在头文字上。建议核对 header 的 z-index 并给 progress 一个明确高于 header 的层级或贴在 header 下沿。

### L-5【低】420px 断点 `body { font-size: 16px }` 覆盖基础字号，但 `--prose` 行高等未同步微调

极窄屏正文 16.5px 行高约 1.9，视觉密度 OK，无实际问题，仅记录。

### L-6【低】时间线/规格表在 720px 折叠为单列，折叠后 `.yr` 仍保留 `padding-top: 4px`

单列下 `.yr` 在上、`.what` 在下，`padding-top: 4px` 让年份与正文基线错开 4px，肉眼可见轻微不对齐。720px 断点补 `.timeline .yr { padding-top: 0 }` 即可。

### L-7【中】TOC 移动端从「侧栏」变「顶部胶囊行」，is-active 靠 `color` 区分

移动端 TOC 换行后仍用颜色区分当前项，弱色觉用户在多行链接里不易定位。建议移动端用 `border-bottom` 或加粗替代纯色差（也照顾小屏对比度）。

### L-8【低】`data-filters` 筛选后 `hidden` 组对屏幕阅读器已良好，但无「无结果」空态提示

筛选 tags 后某年分组整组隐藏。全部隐藏时无空态提示文案。加一行 `data-empty` 显示「该分类下暂无文章」即可。

### L-9【低】`main.js` 时钟固定 UTC+8 偏移

`offset = now.getTimezoneOffset() + 480` 固定按东八区换算，境外访客看到的是「汕头时间」标注明确，逻辑上是有意设计，无问题，仅记录。

## 样式问题

### S-1【中】主题色变量在浅色下对比度需复核

浅色主题 `--muted`（约 #8b8574 一类暖灰）用于 `.rail-note`、`.entry-date`、figcaption 等大量辅助文字。正文衬线 11-12px 小字 + 暖灰，在低分屏浅色下逼近 WCAG AA 边界（4.5:1 需逐值核算）。建议用工具核算一轮 `--ink-soft`/`--muted` 的对比度，不足 4.5:1 的使用场景加深。

### S-2【中】`.prose pre` 横向滚动在小屏缺 `max-height` 或 `overflow` 换行策略

代码块 `white-space: pre` + 横向滚动是标准做法，但小屏长代码行需要横向拖拽。可接受；若想优化，给 `pre` 加 `overflow-wrap`-无关的提示（小屏出现细微横向滚动的视觉提示，如右侧渐隐 mask）。logbook 的 mask 成对写法已经处理得很好。

### S-3【低】`transition-duration: .01ms !important` 的 reduced-motion 处理为业界惯用，但 `animation-iteration-count: 1 !important` 会让蒸汽/眨眼动画直接停在中间帧

`prefers-reduced-motion` 下 `cat-tail`、`steam`、`blink` 全部停帧。`.desk .steam` 的 `opacity: 0` 初值保证蒸汽停隐，处理正确；`.blink` 停在随机帧也无碍。正确实现，仅确认。

### S-4【低】`.toc nav a { font-size: 12px }` 移动端 11.5px 小字

移动端 TOC 链接 11.5px（约 8.7pt）低于常见舒适下限 12px，且 hover 状态在触屏无意义。建议移动端 ≥12px。

### S-5【低】`.fig .lbl`/`.val` SVG 图内文字 10-11px 固定像素

SVG 图内文字用固定 px，不随正文缩放。SVG `width: 100%` 缩放时文字同比缩，整体一致，无兼容问题；但极窄屏同比缩后可能小于 8px 不可读。可在 <420px 给 `.prose figure` 的 SVG 文字阈值缩放，属润色项。

### S-6【低】打印样式把正文行高降到 1.7、11pt 处理规范

打印样式完整（隐藏导航/进度/TOC、避免代码块分页断行），质量高于多数个人站，仅确认。

## 响应式与断点

断点梯度合理：1000 / 820 / 720 / 560 / 420px，覆盖平板横竖屏与手机。两点观察：

### R-1【低】`.shell { width: calc(100% - 40px) }` 固定 40px 边距

无 `max-width` 限制时超宽屏（>1600px）shell 是否被容器 `max-width` 约束需核对；若约束存在（base 部分 `max-width: 1080px` 一类），则无问题。

### R-2【中】720px 头部换行后 `.site-nav` 横向滚动隐藏滚动条

`overflow-x: auto` + `scrollbar-width: none` + `::-webkit-scrollbar { display: none }`，导航超出时无任何可视指示（无渐隐 mask、无滚动箭头），用户可能意识不到可横滑。logbook 已有 mask 方案可复用。

### R-2b【中】头部换行结构依赖 order 与 flex-wrap

720px 头部靠 `flex-wrap: wrap` + order 重排（brand/theme-toggle/nav）。属正确现代写法；风险仅在后续把 header 改成 Grid 时 order 语义变化，维护提示。

## 可访问性

整体语义与 ARIA 使用规范（aria-pressed、aria-current、aria-label）。两个改进点：

### A-1【中】主题切换按钮无可见文字/图标状态变化

`theme-toggle` 只有 `aria-label` 文字变化（「切换到浅色/深色模式」），按钮上若只有图标，屏幕阅读器能得到正确标签，但低视力用户看不出当前是深还是浅。建议图标随主题切换（太阳/月亮对调）。

### A-1b【中】JS 增强不可用时博客筛选完全不可用

`data-filters` 筛选是纯 JS 实现（hidden 属性切换）。禁用 JS 时按钮渲染出来但点击无响应。渐进增强做法：noscript 隐藏筛选条，或服务端带 `?tag=` 参数渲染。个人站可接受，记录即可。

### A-2【低】heading 结构需核对首页

index.php 若在 hero 后直接进 section h2，结构 OK；需核对「草稿箱」等区块 heading 层级是否跳级（works.php 的 h2 使用正常）。

### A-3【中】按用户反馈：金雀花的“编写”教程文章里 prism 高亮的行号列明显塌陷

（此条为待核实项：站内如使用第三方高亮库的行号列，建议核对行号列 `td`/`div` 的 `white-space` 与 `width`；本报告以通读结论提出。）

## 性能相关样式问题

### P-1【低】`body::after` 全屏噪点层为 fixed 层，参与每次重绘

fixed 装饰层在滚动时通常走合成器线程，问题不大；仅在低端设备 + 噪点为 PNG 时需要 preload。

### P-2【低】多个 steps() 像素动画常驻

`blink`、`cat-tail`、`steam`、`rain`、`typing`、`eq`、`pulse`、`logbook` 等 steps() 动画常驻循环，reduced-motion 已降级，正常用户下 CPU 占用可忽略。仅提示动画数量较多，注意未来膨胀。

## 优先级汇总表

| 编号 | 级别 | 位置 | 一句话问题 |
|---|---|---|---|
| C-1 | 高 | main.js 主题初始化 | 深色初始化依赖 JS，无系统偏好回退，刷新闪烁 |
| C-2/C-2b | 中 | 字体栈/fonts.css | 回退链过短、FOUT/CLS 与 font-display 需核对 |
| L-1 | 中 | main.js syncToc | 130px 魔法数字，头高变化即漂移 |
| L-4 | 中 | .progress z-index | 进度条与 header 层叠关系未声明 |
| R-2 | 中 | 720px site-nav | 横向滚动无可视指示 |
| A-1 | 中 | theme-toggle | 仅有 aria-label 变化，无视觉状态 |
| A-1b | 中 | data-filters | JS 禁用时筛选不可用 |
| L-2 | 中 | syncToc hashchange | 锚点直达后高亮短暂不一致 |
| C-3 | 中 | .post-grid gap: 0 | 窄屏 gap 全丢，脆弱写法 |
| L-6 | 低 | .timeline .yr | 单列折叠后 4px 基线错位 |
| C-4 | 低 | .cols {} | 空规则死代码 |
| S-1 | 中 | --muted 对比度 | 浅色辅助文字对比度需核算 |
| S-4 | 低 | TOC 12px/11.5px | 移动端小字号 |
| S-5 | 低 | SVG 图内 10px 文字 | 极窄屏不可读风险 |
| L-8 | 低 | 筛选空态 | 全部隐藏时无空态提示 |
| L-7 | 低 | 移动端 TOC 高亮 | 纯色区分，弱色觉不友好 |
| P-1/P-2 | 低 | body::after/动画 | fixed 噪点层与常驻动画，可观察项 |

## 修复建议清单

按投入产出排序的推荐修复顺序（均为外科手术式小改）：

1. **C-1 深色回退**（约 3 行 CSS）：`@media (prefers-color-scheme: dark) { :root:not([data-theme]) { …深色变量… } }`，JS 保持不变，形成三层回退。
2. **L-1 TOC offset**（约 10 行 JS）：IntersectionObserver 替代 130px 手工值，或用 `scroll-margin-top` 声明式处理。
3. **C-4 删空规则**：`.cols { }` 一行删除。
4. **L-4 进度条层级**：核对 header z-index 后给 `.progress` 明确层级。
5. **R-2 导航滚动指示**：复用 logbook 的 mask 方案给 `.site-nav`。
6. **S-1 对比度核算**：对 `--muted`/`--ink-soft` 逐场景跑对比度计算，不足处加深。
7. **A-1 主题图标**：图标随主题对调。
8. 其余低优先级项可在改版时顺手处理。

## 现代网页指南

- web/css/color-scheme：采纳「无 JS 回退 = CSS 系统偏好先行」策略识别出 C-1 的根因；提醒 `color-scheme` 属性本身应在 `:root` 声明以同步表单控件与滚动条配色（本站未声明，见 C-1 建议一并处理）。
- web/css/responsive-layout：断点梯度 1000/820/720/560/420 的评价基于该篇的单列塌缩与 max-width 约束原则。
- web/css/typography：clamp() 流式字号与现代字体栈回退链的建议（C-2）来自该篇。
- web/css/prefers-reduced-motion：确认现有 `.01ms !important` 降级符合指南推荐做法。
