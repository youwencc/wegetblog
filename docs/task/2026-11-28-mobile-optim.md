# 2026-11-28 移动端适配优化

- 状态：已完成
- 目标：修复手机端显示问题——正文表格/iframe 在窄屏溢出、420px 断点存在空规则 `.cols { }` 无效占位、筛选按钮触控目标偏小、360px 级窄屏无兜底、导航链接触控区偏小。
- 改动：
  - `app/assets/style.css`：720px 断点补表格横向滚动、iframe/video 宽度约束、`overflow-wrap: break-word`、导航链接 padding 增至 8px 0、`.filter-btn` 最小高度 36px；420px 断点删除空规则 `.cols { }`；新增 360px 断点（窄 shell、数字条单列、按钮全宽）。
- 验证：`php tools/publish.php` → 退出码 0，生成 20 个页面；`findstr` 确认 `dist/assets/style.css` 含 `max-width: 360px` 与 `overflow-scrolling: touch`、`.cols {` 零命中。
- 遗留：根目录 `assets/style.css`（重构前留档）同样存在 `.cols { }` 空规则，因不参与发布未改动，如需清理另开任务。
- 风险：无。`prose table` 用 `display: block` 方案，极宽表格在移动端改为块级横向滚动，不影响桌面端（规则仅在 ≤720px 生效）。

## 导航对齐 / 页脚排版 / 作品页错位 / 视频全屏 四项修复

- 状态：已完成
- 目标：①「知识库」下拉项比其他菜单高（`.nav-drop` 与内部链接双重 `padding: 6px 0`）；②页脚 560px 级窄屏排版松散；③作品列表页在 ≤1100px 断点错位（断点把 `.work` 三列网格改两列，`.work-meta` 仍钉在第二列与标题挤压，且新旧两个 560px 块规则冲突）；④手机端演示视频无法全屏（iframe 缺 `fullscreen` 授权，iOS/旧版 Safari 缺 webkit 退出与全屏样式分支）。
- 改动：
  - `app/assets/style.css`：`.nav-drop` 基础与 720px 断点的 padding 改 0，与平级菜单等高；1100px 断点 `.work` 改 `76px minmax(0,1fr) auto`、560px 断点改 `56px minmax(0,1fr) auto` 保持三列；删除旧 560px 块中与新块冲突的 `.work` 单列/隐藏 logo 规则，并入页脚收窄（`.wm-name` 30px、`footer-grid` gap 20px、wordmark 间距压缩）；补 `:-webkit-full-screen` 全屏样式。
  - `app/assets/main.js`：全屏逻辑补 `webkitFullscreenElement`/`webkitExitFullscreen` 分支；新增运行时兜底——检测 iframe `allow` 缺 fullscreen 时自动补 `allow` 与 `allowfullscreen`。
  - `app/content/seed.json`：两处演示 iframe 改 `allow="autoplay; fullscreen" allowfullscreen`（今后重新导入生效）。
- 验证：`node --check app/assets/main.js` → 通过；`node -e JSON.parse(seed.json)` → 通过；`php tools/publish.php` → 退出码 0、20 页面；`findstr` 确认 dist 样式含三列断点、旧冲突规则已移除、JS 兜底在产物中。已发布页 iframe 属性仍为旧值（存于数据库），由 JS 兜底覆盖。
- 遗留：作品数据在数据库中，iframe 属性直接落库需经后台编辑或重新导入，当前由 JS 运行时兜底等效覆盖；如需彻底落库可在后台编辑器里重存两件作品。
- 风险：低。`grid-column` 相关规则未动，桌面端三列布局不受影响；JS 兜底仅修改 iframe 属性，无其他副作用。

## 菜单对齐二次处理：资产缓存版本号

- 状态：已完成
- 目标：用户反馈菜单对齐修复未生效——排查发现页面引用 CSS/JS 无版本号，浏览器缓存旧文件导致任何样式改动都到不了设备；加 mtime 版本号根治（顺带让后续所有样式改动即时生效）。
- 改动：
  - `app/lib/render.php`：`page_head()` 里 style.css / main.js 引用追加 `?v=<filemtime>`，文件一改版本号即变。
- 验证：`php -l app/lib/render.php` → 无语法错误；`php tools/publish.php` → 退出码 0；`findstr` 确认 `dist/index.html` 已输出 `style.css?v=1795866361` 与 `main.js?v=1795866361`。
- 遗留：无。
- 风险：无。仅 URL 追加查询参数，Pages 静态托管按新 URL 直接回源。

## 页脚移动端三列并排

- 状态：已完成
- 目标：手机端页脚三个栏目（简介 / 目录 / 联系）保持三列并排，不再纵向堆叠。
- 改动：
  - `app/assets/style.css`：720px 断点 `.footer-grid` 改 `1fr 1fr 1fr`，配 `footer-name/footer-note/f-label/f-links` 字号与间距收紧。
- 验证：`php tools/publish.php` → 退出码 0；`findstr` 确认 dist 样式含 `footer-grid { grid-template-columns: 1fr 1fr 1fr`。
- 遗留：无。
- 风险：无。规则仅 ≤720px 生效，桌面端不受影响。

## 移除页脚 RSS 订阅链接

- 状态：已完成
- 目标：页脚「联系」栏目不再显示 RSS 订阅入口（feed.xml 文件与浏览器订阅发现保留）。
- 改动：
  - `app/lib/settings.php`：`settings_contact()` 默认列表移除 RSS 项；删除「数据库无联系项时强制追加 RSS 订阅」的兜底分支。
- 验证：`php -l app/lib/settings.php` → 无语法错误；`php tools/publish.php` → 退出码 0；`findstr` 确认 `dist/index.html`、`blog.html`、`about.html`、`works.html` 页脚均无 RSS 字样（head 里的订阅发现 `<link rel="alternate">` 按预期保留）。
- 遗留：无。
- 风险：无。仅移除页脚可见链接，RSS 输出链路（feed.xml 生成、sitemap）不受影响。

## 页脚联系方式显示方式（最终：联系邮箱 / GitHub开源）

- 状态：已完成
- 目标：页脚两个联系项的显示文案定为「联系邮箱」与「GitHub开源」（用户先要求显示具体地址、后要求还原名称、再指定本文案）。
- 改动：
  - `app/lib/settings.php`：`settings_contact()` 中邮箱 label 改「联系邮箱」、github label 改「GitHub开源」。
- 验证：`php -l` → 通过；`php tools/publish.php` → 退出码 0；grep 确认 `dist/index.html` 页脚输出 `<a href="mailto:...">联系邮箱</a>` 与 `<a href="https://github.com/youwencc/">GitHub开源</a>`。
- 遗留：无。
- 风险：无。仅显示文案，链接目标未动。

## 手机端导航重排 + 下拉卡片浮层

- 状态：已完成
- 目标：手机端导航任意折行导致「知识库」孤行、下拉就地展开无背景无缩进样式差；重排导航并优化下拉。
- 改动：
  - `app/assets/style.css`：720px 断点导航从 flex-wrap 任意折行改为 3 列等宽网格（两行各三项）；删除「下拉就地展开」旧规则；下拉改为沿用桌面卡片浮层（纸底/描边/阴影/动画），锚定单元格左缘弹出（left:0），菜单项 padding 11px 12px、字号 13px；同步修正上轮 44px 盒高被旧规则叠加的问题。
- 验证：`php tools/publish.php` → 退出码 0；grep 确认 dist 样式含 `repeat(3, 1fr)` 与 `translate(0, -4px)` 新规则。
- 遗留：无。
- 风险：低。360px 极窄屏三项仍可能紧，已留 nowrap + 12.5px 字号兜底。

## 手机端「知识库」网格内居中对齐

- 状态：已完成
- 目标：2×3 网格导航里普通项文字居中，而「知识库 ⌄」组合贴单元格左缘，两行文字不同轴。
- 改动：
  - `app/assets/style.css`：720px 断点 `.site-nav .nav-drop` 补 `justify-content: center`；`.nav-drop-toggle` 加 `margin-right: -18px` 让箭头不参与居中计算，「知识库」文字与其他项同轴。
- 验证：`php tools/publish.php` → 退出码 0；grep 确认 dist 样式含新规则。
- 遗留：无。
- 风险：无。桌面端不受影响（规则在 720px 断点内）。

## 作品演示横屏播放按钮

- 状态：已完成
- 目标：手机端作品页演示播放器支持横屏观看，播放器右下角增加横屏图标。
- 改动：
  - `app/assets/main.js`：运行时向 `.demo-stage` 注入 `.demo-rot` 横屏按钮（演示 HTML 存库，避免改数据）；点击进入全屏并尝试 `screen.orientation.lock("landscape")`（Android 生效），退出时解锁还原；图标旋转 180° 提示当前状态。
  - `app/assets/style.css`：`.demo-rot` 按钮样式（全屏按钮左侧、仅 ≤720px 显示）；`.force-landscape` 强制横屏模式（fixed 全屏覆盖 + iframe 旋转 90°，iOS 无方向锁定 API 时兜底）。
- 验证：`node --check app/assets/main.js` → 通过；`php tools/publish.php` → 退出码 0；grep 确认 dist 的 CSS 含 `force-landscape`（3 处）、JS 含 `demo-rot`（3 处）。
- 遗留：无。
- 风险：低。旋转兜底方案在 iOS Safari 上为视觉旋转，系统状态栏方向不随之变化，属平台限制。
