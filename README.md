# 陈屿 · 技术笔记

个人技术博客。前台是纯静态 HTML，后台是 PHP 8.2 + MySQL，内容写完生成整站 `dist/`，手动提交推送到 GitHub Pages。

- 前台：无框架、无构建、无统计脚本，一份 CSS、一份 JS
- 后台：文章 / 作品 / 分类的增删改，作品可上传封面与正文插图，发布时生成 `dist/`
- 站点设置：顶部导航、页脚链接与备案、首页 Logbook、各页 SEO（含关于/专栏等内页）
- 部署：本地生成 `dist/` 入库，Pages 直接发布

## 目录

```
blog/
├─ app/                  后端
│  ├─ config.example.php 配置模板（复制为 config.php）
│  ├─ config.php         本地配置（已 gitignore）
│  ├─ schema.sql         posts / works / settings 表
│  ├─ content/seed.json  现有 4 篇文章 + 3 个作品
│  ├─ lib/
│  │  ├─ bootstrap.php   配置、DB、会话、CSRF、校验
│  │  ├─ content.php     文章与作品 CRUD、相邻、统计
│  │  ├─ settings.php    导航 / 页脚 / Logbook / SEO 设置
│  │  ├─ render.php      head / 导航 / 列表 / JSON-LD
│  │  ├─ publish.php     生成 dist/、sitemap、RSS
│  │  └─ install.php     建表、迁移、导入种子
│  ├─ templates/
│  │  ├─ index.php       首页（含像素工位场景）
│  │  ├─ blog.php        文章归档 + 标签筛选
│  │  ├─ works.php       作品列表 + 草稿箱
│  │  ├─ post.php        文章详情
│  │  ├─ work.php        作品详情
│  │  └─ static/         about / column / 404
│  └─ assets/            style.css、main.js、uploads/（作品图片）
├─ public/               虚拟主机 Web 根
│  ├─ admin.php          后台
│  └─ install.php        一次性初始化
├─ dist/                 生成的静态站（发布产物，随代码提交）
├─ tools/
│  ├─ password.php       生成后台密码哈希
│  ├─ publish.php        命令行生成静态站
│  ├─ render-preview.php 不连数据库，用 seed 渲染预览
│  ├─ selftest.php       建表 / 导入 / CRUD / 生成 全流程自检
│  └─ extract-seed.mjs   从旧静态页提取种子
├─ docs/
│  └─ DEPLOY.md          本地生成 + Pages 部署说明
├─ .github/workflows/deploy-pages.yml
└─ index.html …          重构前的手写静态版，留作参考；线上用的是 dist/
```

## 本地跑起来

需要 PHP 8.2+、MySQL 5.7+。

```bash
cp app/config.example.php app/config.php   # 改 db 段
mysql -u root -p -e "CREATE DATABASE chenyu_blog CHARACTER SET utf8mb4"

php -r 'require "app/lib/install.php"; install_schema(); import_seed(); publish_site();'
php -S 127.0.0.1:8080 -t public             # 后台 /admin.php
php -S 127.0.0.1:8081 -t dist              # 前台
```

没有数据库也能看模板：

```bash
php tools/render-preview.php .preview-dist
```

## 写一篇

1. `admin.php` → 新建文章
2. 填标题、slug、描述、栏目、标签、日期，状态选「已发布」
3. 正文写 HTML；每个 `<h2>` 带 `id`，右侧目录会自动生成
4. 保存 → 回列表 → 点「生成静态站」
5. `git add dist/` → commit → push，Pages 随即部署

作品的「参数表」是一行一条 `名称 | 内容`，「要点」每行一条，列表里只展示前三条。
作品编辑器支持「封面图」和「往正文插入图片」：图片上传到 `app/assets/uploads/works/`，发布时随 `assets/` 一起进 `dist/`。

分类在左侧「分类」里单独管理，文章编辑页用下拉选择；删除分类不会删文章，只把文章变成未分类。旧文章的「栏目」在迁移时已经自动建成分类。

## 站点设置

`admin.php` → 「站点设置」，一页管理：

- 顶部导航：每行 `key | 标签 | 链接`，key 用于当前页高亮
- 页脚：简介、目录链接、联系链接、版权、备案号、底栏说明
- 首页 Logbook：每行 `YYYY-MM-DD | 事项`，按顺序滚动
- SEO：全站关键词、`og:image`、文章/作品标题模板，以及首页、博客、作品、关于、专栏各页的 title/description

保存后需要点一次「生成静态站」。关于页、专栏页是静态 HTML，发布时会重写它们的 title、description、canonical 与 OG。

## SEO

- 每页独立 `title`、`description`、`canonical`、Open Graph
- JSON-LD：`WebSite`、`Person`、`Blog`、`BlogPosting`、`BreadcrumbList`、`SoftwareApplication`、`VideoGame`
- `sitemap.xml` 由数据库生成，`robots.txt` 指向它；RSS 2.0 带全文
- 文章页有 `h1`、面包屑、`article`/`section` 语义结构、`time[datetime]`
- 草稿不进 sitemap、不进 RSS、不生成页面

## 设计

- 纸白 `#F7F3E9`，墨色 `#191712`，氧化红 `#A6432B`，分隔线 `#DDD5C4`
- 标题 Noto Serif SC，正文 Noto Sans SC，元信息用系统等宽
- 版心 1120px，文章 43em，左栏 176px
- 深色模式走 `html[data-theme="dark"]`，存 `cy-theme`，首次跟随系统
- 首页像素工位是内联 SVG + CSS 动画：雨、蒸汽、屏幕光标、打字手臂、日志带
- 关掉 JS 仍可读；`prefers-reduced-motion` 下停止动画

## 安全

后台密码用 `password_hash`，表单全部校验 CSRF，slug 限定 `[a-z0-9-]`，正文只接受管理员提交的 HTML。`app/` 不在 Web 根内；`config.php` 不进 Git，`dist/` 是发布产物、随代码提交。上线后删除 `install.php`。

## 已知边界

- 没有图片上传，图片走外链或手动放进 `app/assets/`
- 没有全文搜索，只有标签筛选
- 删除内容后需要手动再生成一次静态站
- 发布后需要手动 commit + push，不是保存即上线
