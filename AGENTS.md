# AGENTS.md — 项目约定

## 项目概述

个人技术博客「电脑小百科 · 技术笔记」：后台 PHP 8.2 + MySQL（无框架、无 composer），前台纯静态 HTML/CSS/JS（无构建、单份 CSS/JS）。内容入库后由 `publish_site()` 生成整站 `dist/`，随代码提交，GitHub Pages 直接发布 `dist/`（`.github/workflows/deploy-pages.yml` 只上传该目录，push master 触发）。部署与写作流程详见 `docs/DEPLOY.md`。

## 目录地图

- `app/` → PHP 后端：`lib/`（bootstrap/content/settings/render/publish/install）、`templates/`、`assets/`（style.css、main.js、uploads/）；`config.php` 为本机私有配置不入库
- `app/storage/` → 预留空目录，全仓库无引用；勿往里放东西
- `app/content/` → `seed.json` 种子数据（`install.php` / `render-preview.php` 依赖）
- `public/` → 虚拟主机 Web 根：`admin.php` 后台、`install.php` 一次性初始化（上线后删除）
- `dist/` → 发布产物（整站静态页 + sitemap/feed），随代码提交、Pages 部署源；可整目录重新生成，不手改
- `tools/` → 命令行 PHP 脚本（导入/导出/发布/预览/自检/改密），从仓库根运行
- `posts/`、`intro/` → 重构前的手写静态页留档（intro/ 含 audio 演示页），`publish_site()` 会把 `posts/` 原样复制进 dist；不作为新内容入口
- `assets/` → 全站共享的 `style.css` / `main.js` / `fonts/`（构建时复制进 dist；与 `app/assets/` 是两份不同文件）
- `docs/` → 项目文档与任务台账（`docs/task/<日期>.md`）
- `ai_temp/` → AI 临时文件与中间产物；只入暂存物，正式交付一律不放这里
- `tmp/` → 本机临时文件（已 gitignore），AI 验证脚本与待导入 JSON 也放这里，任务结束可整目录清空
- `Aitemp/` → 旧 AI 临时产物目录（已 gitignore），仅存历史残留，新产物勿入
- `.github/workflows/deploy-pages.yml` → Pages 部署：push master 触发，只发布 `dist/`

## 常用命令

命令均在仓库根执行；前五条需要 `app/config.php` 连好数据库（模板 `app/config.example.php`，复制后填 db 段）。本机 PHP CLI：`D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe`（已入 PATH，直接写 `php`）。

```bash
php tools/selftest.php                # 全流程自检（建表/导入/CRUD/发布，会清空并重写测试库数据）
php tools/publish.php                 # 命令行生成静态站到 dist/
php tools/posts-import.php ai_temp/batch.json            # 批量导入文章并自动发布；--force 覆盖同 slug，--no-publish 只入库
php tools/posts-export.php            # 文章导出 JSON（备份 / 复用）；可加 slug/status 过滤参数
php tools/render-preview.php ai_temp/preview-dist        # 无数据库，用 seed.json 渲染预览站
php tools/password.php "新密码"       # 生成后台密码哈希
php tools/reset-password.php "新密码" "新用户名"   # 直接重置后台账号（写库）
node tools/extract-seed.mjs           # 从旧静态页提取种子（维护用）
php -S 127.0.0.1:8080 -t public       # 本地后台 /admin.php
php -S 127.0.0.1:8081 -t dist         # 本地前台预览 dist/
```

没有 `tools/` 不成立的场景；脚本不在 PATH 里时用本机完整路径替换 `php`。改 PHP 后的快速语法检查：`php -l app/lib/content.php`。

## 代码约定

- 命名与风格：PHP 文件 `declare(strict_types=1)`、4 空格缩进、函数 snake_case；`app/lib/` 平铺按域拆文件；CSS/JS 各一个文件，不拆分、无构建。
- 安全与内容：表单全走 CSRF 校验（`bootstrap.php`）；slug 限定 `[a-z0-9-]`；作品 `mark_key` 走白名单；后台密码 `password_hash`。
- 文章正文 HTML：每个 `<h2>` 必须带 `id`（右侧目录自动生成）；作品「参数表」一行一条 `名称 | 内容`、「要点」每行一条（列表只展示前三条）。
- 数据库迁移：改表结构改 `app/schema.sql` 并在 `app/lib/install.php` 加迁移分支，不写独立迁移脚本。
- 目录卫生：新文件先查上方目录地图，地图没覆盖时看相邻同类文件归属，不堆仓库根；临时/验证脚本、日志、导出数据、中间产物一律进 `tmp/` 或 `ai_temp/`（均已 gitignore），收尾只交代这两目录之外的新增文件；构建产物只进 `dist/`（构建系统输出）；文件命名跟随既有风格（小写连字符），路径只用 ASCII；删除或移动文件时同步清理旧引用。
- 内容复用通道：AI 写文章走 `posts-import.php`（JSON 驱动），导出复用走 `posts-export.php`。

## 工作纪律

1. **先合并，再生成**：生成或更新 AGENTS.md 前，先读仓库已有的 AI 约定（`.cursor/rules/**`、`.cursorrules`、`CLAUDE.md`、`.github/copilot-instructions.md`、`.windsurfrules`、`GEMINI.md`），逐条保留后合并，不得整篇覆盖。冲突裁决顺序：用户当前指令 > 仓库既有约定 > 本模板，取舍写进文件。AGENTS.md 目标 ≤120 行，放不下的挪到 `docs/` 并在约定里留一行指针。
2. **记录按改动面分档，一个自然日一个文件**：不写——问答、查资料、单文件小修（≤20 行且不需要重新构建）；追加——改动 2–5 个文件、或需要重编/多轮验证，写进 `docs/task/<YYYY-MM-DD>.md` 新增 `## 任务名` 小节；新开文件——当日文件已超 500 行，或跨 ≥3 个模块 / 会跨天，另建 `docs/task/<YYYY-MM-DD>-<任务名>.md`。程序自动生成的任务台账与上表同一判据，不另设数值门槛。
3. **记录只有六个字段**：状态、目标、改动、验证、遗留、风险。顺序固定，缺项写「无」。状态用枚举：进行中 / 已完成 / 阻塞 / 废弃（原因放括号）。「改动」一行一个文件，写清路径与做了什么。
4. **收尾交三样证据**：命令 `→` 结果（错误数 / 通过数 / 耗时）；构建给产物路径与时间戳；UI 给截图绝对路径。没跑就写「未跑」，不写「应该没问题」。临时/验证产物放 `ai_temp/`（约定的 AI 临时目录，可随时整目录清空，无需逐个交代）；要长期保留的结果移出该目录放进正式位置，并在收尾说明去处。
5. **踩过的环境坑写回约定**：内存与并发上限、端口占用、只读与生成目录、代理镜像、日志落点等环境事实，一条一行写进对应小节（构建类坑归构建小节，其余归「注意事项」），只写踩过的，不写通用最佳实践；日志等中间产物不落仓库根目录，合法落点由项目在约定中写明。

## 注意事项

- 本仓库 .gitignore 与 README 说法相反：`*.php`、`*.sql`、`tools/` 实际被忽略（只排除机密与本机私有文件的注释是意图说明），改 PHP 源码后需 `git add -f` 才能入库；README「源码入库」表述已过时，以 .gitignore 为准。
- `dist/` 故意不进 .gitignore：它是 Pages 唯一部署源，别把它加进忽略规则，也别手改。
- 本地预览要同时起两个内置服务器：8080 挂 `public/`（后台）、8081 挂 `dist/`（前台）；无数据库时只跑 `render-preview.php`。
- 内容改动只在数据库与 `dist/` 生效，`posts/`、`intro/` 是旧版留档，别往里加新文章。
- 删除内容后需重新生成一次静态站；发布后需手动 commit + push（不走自动部署管线）。
- `tools/posts-import.php` 首参是 JSON 文件路径（相对仓库根），传目录会退出码 1；导入成功才自动发布，失败只入库不发布。

<!-- AGENTS.md 由 /init 更新于 2026-11-28 -->

## 目录地图

- `app/` → 后端 PHP（`lib/` 按域平铺、`templates/` 页面模板、`assets/` style.css 与 main.js、`content/seed.json` 种子数据）；不放前台页面
- `app/config.php` → 本地数据库配置，已被 gitignore，不入库
- `app/storage/` → 目前为空且全仓库无引用；暂不放置任何文件
- `public/` → 虚拟主机 Web 根，只有 `admin.php` 与一次性 `install.php`（上线后删除）
- `dist/` → 发布产物（生成后随代码提交，Pages 直接发布）；不手改、不手写页面
- `tools/` → PHP/Node 命令行工具（自检、导入导出、发布、渲染预览）；被 .gitignore 忽略，改动无需 `git add -f`
- `posts/`、`intro/`、`assets/`（根） → 重构前手写静态版留存，线上用的是 `dist/`；不要在此新增页面
- `docs/` → 项目文档与任务记录（`docs/task/<YYYY-MM-DD>.md`，见工作纪律）
- `.github/workflows/deploy-pages.yml` → Pages 部署：push master 即发布仓库里的 `dist/`
- `tmp/` → 临时预览文件（已 gitignore）；一次性脚本与中间产物优先放这里，任务结束即清
- `ai_temp/` → AI 临时文件与中间产物；只入暂存物，正式交付一律不放这里
- `Aitemp/` → AI 历史临时产物目录（已 gitignore），不再使用、不新增文件

## 常用命令

命令均在仓库根执行；前五条需要 `app/config.php` 连好数据库（模板 `app/config.example.php`，复制后填 db 段）。

```bash
php tools/selftest.php                # 全流程自检（建表/导入/CRUD/发布，会清空并重写测试库数据）
php tools/publish.php                 # 命令行生成静态站到 dist/
php tools/posts-import.php <posts.json>          # 批量导入文章并自动发布；--force 覆盖同名 slug，--no-publish 只入库
php tools/posts-export.php            # 文章导出 JSON（备份 / 供 posts-import 复用）
php tools/render-preview.php <输出目录>           # 不连数据库、用 seed.json 渲染预览（默认 .preview-dist）
php tools/password.php "<明文密码>"    # 生成后台密码哈希
php tools/reset-password.php "<新密码>" ["<新用户名>"]   # 重置后台账号（写数据库）

php -S 127.0.0.1:8080 -t public       # 本地后台 /admin.php
php -S 127.0.0.1:8081 -t dist         # 本地前台预览 dist/
```

Windows 本机 PHP 不在 PATH，命令行调用写全路径：`"D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe" tools/selftest.php`（其余命令同理替换）。

## 常用命令

命令均在仓库根执行；需要 `app/config.php` 连好数据库（模板 `app/config.example.php`）。

```bash
php tools/selftest.php                # 全流程自检（会清空并重写库数据，只在测试库跑）
php tools/publish.php                 # 命令行生成 dist/
php tools/posts-import.php <posts.json>          # 批量导入文章并发布（AI 内容复用通道）
php tools/posts-import.php <posts.json> --force  # slug 相同则覆盖
php tools/posts-export.php            # 文章导出 JSON（备份 / 喂回 import）
php tools/render-preview.php .preview-dist       # 不连库，用 seed 渲染预览
php tools/password.php "你的密码"                 # 生成后台密码哈希
php tools/reset-password.php "新密码" "用户名"    # 重置后台账号（连库）
"D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe" -S 127.0.0.1:8080 -t public   # 后台 /admin.php
"D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe" -S 127.0.0.1:8081 -t dist     # 前台预览
node tools/extract-seed.mjs           # 从旧静态页提取种子
```

- 本机 PHP 不在 PATH：命令行调用写全路径 `"D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe"`（已验证 8.2.9 CLI 可用），把 `php` 换成该全路径即可；工具脚本里的 `php` 均照此替换。
- `posts-import.php` 的 JSON 路径参数是必填的；路径无效或导入失败会以退出码 1 结束——先确认 JSON 文件存在且 `posts` 数组非空再调用。
- 项目无构建系统：改动前台样式/脚本后直接 `php tools/publish.php` 重新生成 `dist/`，没有 lint / typecheck / 单测；`tools/selftest.php` 就是全量检查。

<!-- AGENTS.md 由 /init 更新于 2026-11-28 -->
